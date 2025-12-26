<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Http;

use Closure;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * PusherRouteBuilder
 *
 * Helper for building Pusher HTTP API routes
 *
 * @phpstan-type HttpMethods array<string>
 */
class PusherRouteBuilder
{
    /**
     * Route collection
     *
     * @var \Symfony\Component\Routing\RouteCollection
     */
    protected RouteCollection $routes;

    /**
     * Constructor
     *
     * @param \Symfony\Component\Routing\RouteCollection $routes Route collection
     */
    public function __construct(RouteCollection $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Build and return route collection
     *
     * @return \Symfony\Component\Routing\RouteCollection
     */
    public function buildRoutes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Add a GET route
     *
     * @param string $path Route path
     * @param \Closure|callable|string $controller Controller callable or class name
     * @param string $name Route name
     * @return $this
     */
    public function get(string $path, callable|string|Closure $controller, string $name)
    {
        return $this->addRoute($path, $controller, ['GET'], $name);
    }

    /**
     * Add a POST route
     *
     * @param string $path Route path
     * @param \Closure|callable|string $controller Controller callable or class name
     * @param string $name Route name
     * @return $this
     */
    public function post(string $path, callable|string|Closure $controller, string $name)
    {
        return $this->addRoute($path, $controller, ['POST'], $name);
    }

    /**
     * Add a route that responds to OPTIONS requests
     *
     * @param string $path Route path
     * @param \Closure|callable|string $controller Controller callable or class name
     * @param string $name Route name
     * @return $this
     */
    public function options(string $path, callable|string|Closure $controller, string $name)
    {
        return $this->addRoute($path, $controller, ['OPTIONS'], $name);
    }

    /**
     * Add a route that responds to multiple methods
     *
     * @param string $path Route path
     * @param \Closure|callable|string $controller Controller callable or class name
     * @param HttpMethods $methods HTTP methods
     * @param string $name Route name
     * @return $this
     */
    public function addRoute(string $path, callable|string|Closure $controller, array $methods, string $name)
    {
        $route = new Route($path, ['_controller' => $controller]);
        $route->setMethods($methods);

        $this->routes->add($name, $route);

            BlazeCastLogger::info(__('Route added {0} {1} {2}', $name, $path, implode(',', $methods)), [
            'scope' => ['socket.router'],
            ]);

        return $this;
    }

    /**
     * Add a route that responds to all methods
     *
     * @param string $path Route path
     * @param \Closure|callable|string $controller Controller callable or class name
     * @param string $name Route name
     * @return $this
     */
    public function any(string $path, callable|string|Closure $controller, string $name)
    {
        return $this->addRoute($path, $controller, ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], $name);
    }

    /**
     * Add prefix to all routes
     *
     * @param string $prefix Path prefix
     * @return $this
     */
    public function addPrefix(string $prefix)
    {
        $this->routes->addPrefix($prefix);

        return $this;
    }
}
