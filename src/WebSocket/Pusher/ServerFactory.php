<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher;

use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Event\EventManager;
use Crustum\BlazeCast\WebSocket\Http\PusherRouteBuilder;
use Crustum\BlazeCast\WebSocket\Http\PusherRouter;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ControllerFactory;
use Crustum\BlazeCast\WebSocket\Pusher\Http\DefaultPusherRouteLoader;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterFactory;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Factory for creating WebSocket Server instances
 *
 * Handles server creation with proper dependency injection and configuration.
 *
 * @phpstan-import-type ApplicationConfig from \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
 * @phpstan-type ServerFactoryConfig array{
 *   app_id?: string,
 *   app_key?: string,
 *   app_secret?: string,
 *   app_name?: string,
 *   max_connections?: int,
 *   enable_client_messages?: bool,
 *   enable_statistics?: bool,
 *   enable_debug?: bool
 * }
 */
class ServerFactory
{
    /**
     * Create a new unified Pusher server with multi-application support
     *
     * @param string $host Server host
     * @param int $port Server port
     * @param ServerFactoryConfig $config Server configuration
     * @param \React\EventLoop\LoopInterface|null $loop Event loop
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager|null $applicationManager Application manager from container
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager|null $connectionManager Connection manager from container
     * @param \Cake\Core\ContainerInterface|null $container Container for Pulse integration
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Server
     */
    public static function create(
        string $host = '0.0.0.0',
        int $port = 8080,
        array $config = [],
        ?LoopInterface $loop = null,
        ?ApplicationManager $applicationManager = null,
        ?ChannelConnectionManager $connectionManager = null,
        ?ContainerInterface $container = null,
        RateLimiterInterface|AsyncRateLimiterInterface|null $rateLimiter = null,
    ): Server {
        $loop = $loop ?: Loop::get();

        $applicationManager = $applicationManager ?: static::createApplicationManager($config);
        $connectionManager = $connectionManager ?: new ChannelConnectionManager();

        if ($rateLimiter === null) {
            $rateLimiter = static::createWebSocketRateLimiter($applicationManager, $loop);
        }

        $httpRateLimiter = static::createHttpRateLimiter($applicationManager);
        $router = static::createPusherRouter($config, $applicationManager, $connectionManager, $httpRateLimiter);

        $placeholderChannelManager = new ChannelManager();
        $server = new Server(
            $router,
            $placeholderChannelManager,
            $connectionManager,
            $applicationManager,
            $host,
            $port,
            $config,
            $loop,
            $container,
            $rateLimiter,
        );

        return $server;
    }

    /**
     * Create ApplicationManager with per-application ChannelManagers
     *
     * @param ServerFactoryConfig $config Configuration
     * @return \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    protected static function createApplicationManager(array $config): ApplicationManager
    {
        $applicationManager = new ApplicationManager($config);

        $applications = $applicationManager->getApplications();

        foreach ($applications as $appId => $application) {
            $channelManager = new ChannelManager();

            $applicationManager->updateApplication((string)$appId, [
                'channel_manager' => $channelManager,
            ]);
        }

        if (empty($applications)) {
            $defaultApp = [
                'id' => 'default-app',
                'key' => $config['app_key'] ?? 'default-key',
                'secret' => $config['app_secret'] ?? 'default-secret',
                'name' => 'Default Application',
                'channel_manager' => new ChannelManager(),
            ];

            $applicationManager->registerApplication($defaultApp);
        }

        return $applicationManager;
    }

    /**
     * Create Pusher router with multi-app support
     *
     * @param ServerFactoryConfig $config Configuration
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager $connectionManager Connection manager
     * @param \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|null $rateLimiter Rate limiter (nullable if disabled)
     * @return \Crustum\BlazeCast\WebSocket\Http\PusherRouter
     */
    protected static function createPusherRouter(
        array $config,
        ApplicationManager $applicationManager,
        ChannelConnectionManager $connectionManager,
        ?RateLimiterInterface $rateLimiter,
    ): PusherRouter {
        $routes = new RouteCollection();

        $routeLoader = new DefaultPusherRouteLoader($routes);

        $routeBuilder = new PusherRouteBuilder($routes);
        $routeLoader->registerRoutes($routeBuilder);

        $placeholderChannelManager = new ChannelManager();
        $controllerFactory = new ControllerFactory(
            $applicationManager,
            $placeholderChannelManager,
            $connectionManager,
            EventManager::instance(),
            null,
            $rateLimiter,
        );

        $router = new PusherRouter($routes, $controllerFactory);

        return $router;
    }

    /**
     * Create rate limiter instance for WebSocket server
     *
     * For 'redis' driver, creates async_redis for non-blocking WebSocket events.
     * For other drivers, creates standard rate limiter.
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|\Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface Rate limiter instance
     */
    protected static function createWebSocketRateLimiter(ApplicationManager $applicationManager, LoopInterface $loop): RateLimiterInterface|AsyncRateLimiterInterface|null
    {
        $rateLimiterConfig = Configure::read('BlazeCast.rate_limiter', []);

        $enabled = $rateLimiterConfig['enabled'] ?? true;
        if (!$enabled) {
            return null;
        }

        $driver = $rateLimiterConfig['driver'] ?? 'local';
        if ($driver === 'none') {
            return null;
        }

        $appConfigs = static::buildAppConfigs($applicationManager, $rateLimiterConfig);

        $factoryConfig = [
            'app_configs' => $appConfigs,
        ];

        if ($driver === 'redis') {
            $factoryConfig['redis'] = $rateLimiterConfig['redis'] ?? [];

            return RateLimiterFactory::create('async_redis', $factoryConfig, $loop);
        }

        return RateLimiterFactory::create($driver, $factoryConfig, $loop);
    }

    /**
     * Create rate limiter instance for HTTP controllers
     *
     * For 'redis' driver, creates sync RedisRateLimiter (blocking is OK for HTTP).
     * For other drivers, creates standard rate limiter.
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface HTTP rate limiter instance (always sync)
     */
    protected static function createHttpRateLimiter(ApplicationManager $applicationManager): ?RateLimiterInterface
    {
        $rateLimiterConfig = Configure::read('BlazeCast.rate_limiter', []);

        $enabled = $rateLimiterConfig['enabled'] ?? true;
        if (!$enabled) {
            return null;
        }

        $driver = $rateLimiterConfig['driver'] ?? 'local';
        if ($driver === 'none') {
            return null;
        }

        $appConfigs = static::buildAppConfigs($applicationManager, $rateLimiterConfig);

        $factoryConfig = [
            'app_configs' => $appConfigs,
        ];

        if ($driver === 'redis' || $driver === 'async_redis') {
            $factoryConfig['redis'] = $rateLimiterConfig['redis'] ?? [];

            return RateLimiterFactory::create('redis', $factoryConfig);
        }

        return RateLimiterFactory::create($driver, $factoryConfig);
    }

    /**
     * Build application rate limit configurations
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param array<string, mixed> $rateLimiterConfig Rate limiter configuration
     * @return array<string, array<string, int>> Application configurations
     */
    protected static function buildAppConfigs(ApplicationManager $applicationManager, array $rateLimiterConfig): array
    {
        $appConfigs = [];

        foreach ($applicationManager->getApplications() as $appId => $appConfig) {
            $appConfigs[$appId] = [
                'max_backend_events_per_second' => $appConfig['max_backend_events_per_second'] ?? $rateLimiterConfig['default_limits']['max_backend_events_per_second'] ?? 100,
                'max_frontend_events_per_second' => $appConfig['max_frontend_events_per_second'] ?? $rateLimiterConfig['default_limits']['max_frontend_events_per_second'] ?? 10,
                'max_read_requests_per_second' => $appConfig['max_read_requests_per_second'] ?? $rateLimiterConfig['default_limits']['max_read_requests_per_second'] ?? 50,
            ];
        }

        return $appConfigs;
    }
}
