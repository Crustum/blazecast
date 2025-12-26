<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Http;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ControllerFactory;
use Exception;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * PusherRouter
 *
 * Router for Pusher HTTP API requests
 *
 * @phpstan-type RouteData array<string, mixed>
 * @phpstan-type RouteParameters array<string, mixed>
 * @phpstan-type HeadersForLogging array<string, string>
 * @phpstan-type AvailableRoutes array<string, array{path: string, methods: array<string>}>
 */
class PusherRouter
{
    /**
     * Route collection
     *
     * @var \Symfony\Component\Routing\RouteCollection
     */
    protected RouteCollection $routes;

    /**
     * URL matcher
     *
     * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
     */
    protected UrlMatcherInterface $matcher;

    /**
     * Request context
     *
     * @var \Symfony\Component\Routing\RequestContext
     */
    protected RequestContext $context;

    /**
     * Controller factory
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ControllerFactory|null
     */
    protected ?ControllerFactory $controllerFactory = null;

    /**
     * Constructor
     *
     * @param \Symfony\Component\Routing\RouteCollection $routes Route collection
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ControllerFactory|null $controllerFactory Controller factory
     */
    public function __construct(RouteCollection $routes, ?ControllerFactory $controllerFactory = null)
    {
        $this->routes = $routes;
        $this->context = new RequestContext();
        $this->matcher = new UrlMatcher($routes, $this->context);
        $this->controllerFactory = $controllerFactory;
    }

    /**
     * Dispatch request to appropriate controller
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Unified WebSocket connection
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    public function dispatch(RequestInterface $request, Connection $connection): Response
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $method = $request->getMethod();
        $requestId = uniqid('req_');

        BlazeCastLogger::info("HTTP Router: Received request {$method} {$path}" . ($uri->getQuery() ? "?{$uri->getQuery()}" : ''), [
            'scope' => ['socket.router'],
        ]);

        $this->context->setMethod($method);
        $this->context->setHost($uri->getHost());
        $this->context->setPathInfo($path);

        try {
            BlazeCastLogger::info("HTTP Router: Attempting to match route {$path}", [
                'scope' => ['socket.router'],
            ]);

            $route = $this->matcher->match($path);

            $routeName = $route['_route'] ?? 'unknown';
            $routeParams = array_filter($route, function ($key) {
                return !str_starts_with($key, '_');
            }, ARRAY_FILTER_USE_KEY);
            $routeParamsJson = json_encode($routeParams);
            BlazeCastLogger::info(sprintf('HTTP Router: Route matched. request_id=%s, route_name=%s, route_params=%s', $requestId, $routeName, $routeParamsJson), [
                'scope' => ['socket.router'],
            ]);

            if (!isset($route['_controller'])) {
                $routeName = $route['_route'] ?? 'unknown';
                BlazeCastLogger::error(sprintf('HTTP Router: Controller not defined for route. request_id=%s, route=%s', $requestId, $routeName), [
                    'scope' => ['socket.router'],
                ]);

                return $this->errorResponse('Controller not defined for route', 500);
            }

            $controller = $route['_controller'];
            $params = $this->extractParameters($route);

            $routeName = $route['_route'] ?? 'unknown';
            $controllerName = is_string($controller) ? $controller : get_class($controller);
            $paramsJson = json_encode($params);
            BlazeCastLogger::info(sprintf('HTTP Router: Controller identified. request_id=%s, route=%s, controller=%s, params=%s', $requestId, $routeName, $controllerName, $paramsJson), [
                'scope' => ['socket.router'],
            ]);

            $controller = $this->resolveController($controller, $requestId);

            if (is_callable($controller)) {
                $controllerName = is_array($controller) ? get_class($controller[0]) . '::' . $controller[1] :
                                  (is_object($controller) ? get_class($controller) : $controller);
                BlazeCastLogger::info(sprintf('HTTP Router: Invoking controller. request_id=%s, controller=%s', $requestId, $controllerName), [
                    'scope' => ['socket.router'],
                ]);

                $startTime = microtime(true);
                $response = $controller($request, $connection, $params);
                $executionTime = microtime(true) - $startTime;

                $responseContent = $response->getContent();
                $responseSize = strlen($responseContent);
                $responseContentForLog = $responseSize <= 1024 ? $responseContent : '[Content too large to log]';

                $statusCode = $response->getStatusCode();
                $executionTimeMs = round($executionTime * 1000, 2);
                $responseHeadersJson = json_encode($response->getHeaders());
                BlazeCastLogger::info(sprintf('HTTP Router: Controller executed successfully. request_id=%s, status_code=%d, response_size=%d, execution_time_ms=%.2f, response_headers=%s, response_content=%s', $requestId, $statusCode, $responseSize, $executionTimeMs, $responseHeadersJson, $responseContentForLog), [
                    'scope' => ['socket.router'],
                ]);

                return $response;
            }

            $controllerType = is_string($controller) ? $controller : gettype($controller);
            BlazeCastLogger::error(sprintf('HTTP Router: Controller not callable. request_id=%s, controller=%s', $requestId, $controllerType), [
                'scope' => ['socket.router'],
            ]);

            return $this->errorResponse('Controller not callable', 500);
        } catch (ResourceNotFoundException $e) {
            BlazeCastLogger::warning("HTTP Router: Route not found {$path}", [
                'scope' => ['socket.router'],
            ]);

            return $this->notFoundResponse('Route not found');
        } catch (MethodNotAllowedException $e) {
            $allowedMethods = implode(', ', $e->getAllowedMethods());
            BlazeCastLogger::warning(sprintf('HTTP Router: Method not allowed. request_id=%s, path=%s, method=%s, allowed=[%s]', $requestId, $path, $method, $allowedMethods), [
                'scope' => ['socket.router'],
            ]);

            return $this->errorResponse('Method not allowed', 405);
        } catch (Exception $e) {
            BlazeCastLogger::error(sprintf('HTTP Router: Error dispatching request. request_id=%s, path=%s, error=%s, trace=%s', $requestId, $path, $e->getMessage(), $e->getTraceAsString()), [
                'scope' => ['socket.router'],
            ]);

            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * Resolve controller to callable
     *
     * @param mixed $controller Controller definition
     * @param string $requestId Request ID for logging
     * @return mixed|callable Resolved controller
     */
    protected function resolveController(mixed $controller, string $requestId): mixed
    {
        if (is_string($controller)) {
            if (class_exists($controller)) {
                BlazeCastLogger::info(sprintf('HTTP Router: Creating controller instance. request_id=%s, controller=%s', $requestId, $controller), [
                    'scope' => ['socket.router'],
                ]);

                if ($this->controllerFactory !== null) {
                    return $this->controllerFactory->create($controller);
                }

                BlazeCastLogger::warning(sprintf('HTTP Router: No factory, trying no-arg constructor. controller=%s', $controller), [
                    'scope' => ['socket.router'],
                ]);

                return new $controller();
            } elseif (strpos($controller, '::') !== false) {
                [$class, $method] = explode('::', $controller, 2);
                BlazeCastLogger::info(sprintf('HTTP Router: Using static controller method. request_id=%s, class=%s, method=%s', $requestId, $class, $method), [
                    'scope' => ['socket.router'],
                ]);

                return [$class, $method];
            }
        }

        return $controller;
    }

    /**
     * Extract parameters from route
     *
     * @param RouteData $route Route data
     * @return RouteParameters Parameters
     */
    protected function extractParameters(array $route): array
    {
        $params = [];
        foreach ($route as $key => $value) {
            if (!str_starts_with($key, '_')) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Create not found response
     *
     * @param string $message Error message
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    protected function notFoundResponse(string $message): Response
    {
        return new Response(
            ['error' => $message],
            404,
            ['Content-Type' => 'application/json'],
            true,
        );
    }

    /**
     * Create error response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    protected function errorResponse(string $message, int $statusCode = 400): Response
    {
        return new Response(
            ['error' => $message],
            $statusCode,
            ['Content-Type' => 'application/json'],
            true,
        );
    }

    /**
     * Get headers for logging
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @return HeadersForLogging
     */
    protected function getHeadersForLogging(RequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            if (strtolower((string)$name) === 'authorization') {
                $headers[$name] = '[REDACTED]';
            } else {
                $headers[$name] = implode(', ', $values);
            }
        }

        return $headers;
    }

    /**
     * Get available routes for debugging
     *
     * @return AvailableRoutes
     */
    protected function getAvailableRoutes(): array
    {
        $routes = [];
        foreach ($this->routes as $name => $route) {
            $routes[$name] = [
                'path' => $route->getPath(),
                'methods' => $route->getMethods(),
            ];
        }

        return $routes;
    }
}
