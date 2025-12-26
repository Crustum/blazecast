<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Cake\Event\EventManager;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\Pusher\MetricsHandler;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface;
use Exception;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

/**
 * Pusher Controller
 *
 * Base controller for Pusher HTTP API endpoints with authentication and common functionality.
 *
 * @phpstan-import-type RouteParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type QueryParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type RequestData from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type PayloadData from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type ResponseData from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type ResponseHeaders from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type CustomMessages from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type FieldTypes from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type LogOptions from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type ApplicationConfig from \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
 */
abstract class PusherController implements PusherControllerInterface
{
    use AuthenticationTrait;
    use ResponseTrait;

    /**
     * Application manager for multi-app support
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    protected ApplicationManager $applicationManager;

    /**
     * Channel manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager
     */
    protected ChannelManager $channelManager;

    /**
     * Channel connection manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager
     */
    protected ChannelConnectionManager $connectionManager;

    /**
     * Event manager
     *
     * @var \Cake\Event\EventManager
     */
    protected EventManager $eventManager;

    /**
     * Rate limiter
     *
     * @var \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|null
     */
    protected ?RateLimiterInterface $rateLimiter = null;

    /**
     * Current application
     *
     * @var ApplicationConfig|null
     */
    protected ?array $application = null;

    /**
     * Query parameters
     *
     * @var QueryParams
     */
    protected array $query = [];

    /**
     * Request body
     *
     * @var string|null
     */
    protected ?string $body = null;

    /**
     * Request data
     *
     * @var RequestData|null
     */
    protected ?array $requestData = null;

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager $channelManager Channel manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager $connectionManager Connection manager
     * @param \Cake\Event\EventManager|null $eventManager Event manager
     * @param \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|null $rateLimiter Rate limiter
     */
    public function __construct(
        ApplicationManager $applicationManager,
        ChannelManager $channelManager,
        ChannelConnectionManager $connectionManager,
        ?EventManager $eventManager = null,
        ?RateLimiterInterface $rateLimiter = null,
    ) {
        $this->applicationManager = $applicationManager;
        $this->channelManager = $channelManager;
        $this->connectionManager = $connectionManager;
        $this->eventManager = $eventManager ?: EventManager::instance();
        $this->rateLimiter = $rateLimiter;
        $this->initialize();
    }

    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * Invoke the controller
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function __invoke(RequestInterface $request, Connection $connection, array $params = []): Response
    {
        try {
            $this->log('info', 'Controller: Invoked ' . static::class, [
                'scope' => ['socket.controller', 'socket.controller.pusher'],
                'controller' => static::class,
                'params' => $params,
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
            ]);

            $this->query = $this->parseQueryParams($request);
            $this->body = $this->parseRequestBody($request);
            $this->requestData = [
                'query' => $this->query,
                'body' => $this->body,
                'params' => $params,
            ];

            $this->log('info', 'Controller: Request data parsed', [
                'scope' => ['socket.controller', 'socket.controller.pusher'],
                'controller' => static::class,
                'query' => $this->query,
                'body_length' => $this->body ? strlen($this->body) : 0,
            ]);

            if ($request->getMethod() === 'OPTIONS') {
                $this->log('info', 'Controller: Handling OPTIONS request', [
                    'scope' => ['socket.controller', 'socket.controller.pusher'],
                    'controller' => static::class,
                ]);

                return $this->handleOptions();
            }

            if (isset($params['appId'])) {
                $this->log('info', 'Controller: Verifying request with appId', [
                    'scope' => ['socket.controller', 'socket.controller.pusher'],
                    'controller' => static::class,
                    'app_id' => $params['appId'],
                ]);
                $this->verify($request, $params['appId']);
            }

            $response = $this->handle($request, $connection, $params);

            if (isset($params['appId'])) {
                $bodyContents = $request->getBody()->getContents();
                $this->connectionManager->recordHttpRequest($params['appId'], strlen($bodyContents), 200);
            }

            $this->log('info', 'Controller: Request handled successfully', [
                'scope' => ['socket.controller', 'socket.controller.pusher'],
                'controller' => static::class,
                'status_code' => $response->getStatusCode(),
                'response_size' => strlen($response->getContent()),
            ]);

            return $response;
        } catch (Exception $e) {
            $errorMsg = sprintf(
                'Controller: Error processing request - %s in %s:%d - Trace: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString(),
            );
            BlazeCastLogger::error($errorMsg, [
                'scope' => ['socket.controller', 'socket.controller.pusher'],
                'controller' => static::class,
            ]);

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Verify that the incoming request is valid
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param string $appId Application ID
     * @return void
     * @throws \InvalidArgumentException If verification fails
     */
    protected function verify(RequestInterface $request, string $appId): void
    {
        $app = $this->applicationManager->getApplication($appId);
        if (!$app) {
            throw new InvalidArgumentException("Application not found: {$appId}");
        }

        $this->application = $app;

        $this->switchToAppSpecificManagers($app);

        if ($this->requiresAuthentication($request)) {
            $authSignature = $this->query['auth_signature'] ?? null;
            if (!$authSignature) {
                throw new InvalidArgumentException('Missing authentication signature');
            }

            $params = array_filter($this->query, function ($key) {
                return !in_array($key, ['body_md5', 'appId', 'appKey', 'channelName']);
            }, ARRAY_FILTER_USE_KEY);

            if ($this->body !== null && $this->body !== '') {
                $params['body_md5'] = md5($this->body);
            }

            if (!$this->verifySignature($request, $params, $app['secret'])) {
                throw new InvalidArgumentException('Invalid authentication signature');
            }
        }
    }

    /**
     * Determine if the request requires authentication
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @return bool True if authentication is required
     */
    protected function requiresAuthentication(RequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        $healthEndpoints = ['/up', '/pusher/health'];

        return !in_array($path, $healthEndpoints);
    }

    /**
     * Switch to app-specific managers for this request
     *
     * @param ApplicationConfig $application Application configuration
     * @return void
     */
    protected function switchToAppSpecificManagers(array $application): void
    {
        if (isset($application['channel_manager'])/* && $application['channel_manager'] instanceof ChannelManager*/) {
            $this->channelManager = $application['channel_manager'];

            $this->log('info', 'Switched to app-specific ChannelManager', [
                'scope' => ['socket.controller', 'socket.controller.pusher'],
                'controller' => static::class,
                'app_id' => $application['id'],
                'channel_count' => $this->channelManager->getChannelCount(),
            ]);
        } else {
            BlazeCastLogger::warning('No app-specific ChannelManager found, using default', [
                'scope' => ['socket.controller', 'socket.controller.pusher'],
                'controller' => static::class,
                'app_id' => $application['id'],
            ]);
        }
    }

    /**
     * Handle OPTIONS request for CORS
     *
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    protected function handleOptions(): Response
    {
        return new Response(null, 204, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Max-Age' => '86400',
        ]);
    }

    /**
     * Handle the request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    abstract public function handle(RequestInterface $request, Connection $connection, array $params): Response;

    /**
     * Get application manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    public function getApplicationManager(): ApplicationManager
    {
        return $this->applicationManager;
    }

    /**
     * Get channel manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager
     */
    public function getChannelManager(): ChannelManager
    {
        return $this->channelManager;
    }

    /**
     * Get connection manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager
     */
    public function getConnectionManager(): ChannelConnectionManager
    {
        return $this->connectionManager;
    }

    /**
     * Get the correct ChannelManager for the current application context
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager
     */
    protected function getCurrentChannelManager(): ChannelManager
    {
        if ($this->application && isset($this->application['channel_manager'])) {
            return $this->application['channel_manager'];
        }

        return $this->channelManager;
    }

    /**
     * Get metrics handler instance
     *
     * Provides centralized metrics collection with proper dependencies.
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\MetricsHandler
     */
    protected function getMetricsHandler(): MetricsHandler
    {
        return new MetricsHandler(
            $this->applicationManager,
            $this->getCurrentChannelManager(),
            $this->connectionManager,
        );
    }

    /**
     * Validate required fields in payload
     *
     * @param PayloadData $payload Payload to validate
     * @param array<string> $requiredFields Array of field names that are required
     * @param FieldTypes $fieldTypes Optional array mapping field names to expected types
     * @param CustomMessages $customMessages Optional array mapping field names to custom error messages
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validateRequiredFields(array $payload, array $requiredFields, array $fieldTypes = [], array $customMessages = []): void
    {
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                $message = $customMessages[$field] ?? "Field '{$field}' is required";
                throw new InvalidArgumentException($message);
            }

            if (isset($fieldTypes[$field])) {
                $expectedType = $fieldTypes[$field];

                if ($expectedType === 'array' && !is_array($payload[$field])) {
                    $message = $customMessages["{$field}_type"] ?? "Field '{$field}' must be an array";
                    throw new InvalidArgumentException($message);
                }

                if ($expectedType === 'string' && !is_string($payload[$field])) {
                    $message = $customMessages["{$field}_type"] ?? "Field '{$field}' must be a string";
                    throw new InvalidArgumentException($message);
                }

                if ($expectedType === 'integer' && !is_int($payload[$field])) {
                    $message = $customMessages["{$field}_type"] ?? "Field '{$field}' must be an integer";
                    throw new InvalidArgumentException($message);
                }
            }
        }
    }

    protected bool $verbose = false;

    /**
     * Log a message if verbose mode is enabled
     *
     * @param string $type Log level type
     * @param string $message Log message
     * @param LogOptions $options Log options
     * @return void
     */
    public function log(string $type, string $message, array $options): void
    {
        if ($this->verbose) {
            BlazeCastLogger::write($type, $message, $options);
        }
    }
}
