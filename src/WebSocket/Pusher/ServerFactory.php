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
use Crustum\BlazeCast\WebSocket\RateLimiter\ConnectionMessageRateLimiter;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterFactory;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface;
use Crustum\BlazeCast\WebSocket\Support\ServerPath;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Factory for creating WebSocket Server instances
 *
 * Handles server creation with proper dependency injection and configuration.
 *
 * @phpstan-import-type ApplicationConfig from \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
 * @phpstan-type TlsOptions array{
 *   local_cert?: string|null,
 *   local_pk?: string|null,
 *   verify_peer?: bool|null
 * }
 * @phpstan-type ServerOptions array{
 *   tls?: TlsOptions
 * }
 * @phpstan-type ScalingServerConfig array{
 *   url?: string|null,
 *   host?: string,
 *   port?: string|int,
 *   username?: string|null,
 *   password?: string|null,
 *   database?: string|int,
 *   timeout?: int|string
 * }
 * @phpstan-type ScalingConfig array{
 *   enabled?: bool,
 *   channel?: string,
 *   server?: ScalingServerConfig
 * }
 * @phpstan-type BlazeCastServerConfig array{
 *   host?: string,
 *   port?: int|string,
 *   path?: string,
 *   hostname?: string|null,
 *   protocol_version?: string,
 *   options?: ServerOptions,
 *   max_request_size?: int,
 *   scaling?: ScalingConfig,
 *   ping_interval?: int,
 *   activity_timeout?: int
 * }
 * @phpstan-type ServerFactoryConfig array{
 *   app_id?: string,
 *   app_key?: string,
 *   app_secret?: string,
 *   app_name?: string,
 *   max_connections?: int,
 *   enable_client_messages?: bool,
 *   enable_statistics?: bool,
 *   enable_debug?: bool,
 *   max_request_size?: int,
 *   test_mode?: bool,
 *   debug?: bool,
 *   log_level?: string,
 *   servers?: array{
 *     blazecast?: BlazeCastServerConfig
 *   },
 *   applications?: array<ApplicationConfig>
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
        ?ConnectionMessageRateLimiter $connectionMessageRateLimiter = null,
    ): Server {
        $loop = $loop ?: Loop::get();

        $applicationManager = $applicationManager ?: static::createApplicationManager($config);
        $connectionManager = $connectionManager ?: new ChannelConnectionManager();

        if ($rateLimiter === null) {
            $rateLimiter = static::createWebSocketRateLimiter($applicationManager, $loop);
        }

        if (!$connectionMessageRateLimiter instanceof ConnectionMessageRateLimiter) {
            $connectionMessageRateLimiter = static::createConnectionMessageRateLimiter($applicationManager);
        }

        $httpRateLimiter = static::createHttpRateLimiter($applicationManager);
        $router = static::createPusherRouter($config, $applicationManager, $connectionManager, $httpRateLimiter);

        $placeholderChannelManager = new ChannelManager();

        return new Server(
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
            $connectionMessageRateLimiter,
        );
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

        foreach (array_keys($applications) as $appId) {
            $channelManager = new ChannelManager();

            $applicationManager->updateApplication((string)$appId, [
                'channel_manager' => $channelManager,
            ]);
        }

        if ($applications === []) {
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

        $serverConfig = $config['servers']['blazecast'] ?? Configure::read('BlazeCast.servers.blazecast', []);
        if (!is_array($serverConfig)) {
            $serverConfig = [];
        }

        /** @var BlazeCastServerConfig $serverConfig */
        $pathPrefix = ServerPath::prefix($serverConfig);
        if ($pathPrefix !== '') {
            $routes->addPrefix(ltrim($pathPrefix, '/'));
        }

        $placeholderChannelManager = new ChannelManager();
        $controllerFactory = new ControllerFactory(
            $applicationManager,
            $placeholderChannelManager,
            $connectionManager,
            EventManager::instance(),
            null,
            $rateLimiter,
        );

        return new PusherRouter($routes, $controllerFactory);
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

    /**
     * Create local per-connection WebSocket message rate limiter.
     *
     * Independent of Soketi frontend/backend/read buckets. Always in-process.
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\ConnectionMessageRateLimiter|null
     */
    protected static function createConnectionMessageRateLimiter(ApplicationManager $applicationManager): ?ConnectionMessageRateLimiter
    {
        $rateLimiterConfig = Configure::read('BlazeCast.rate_limiter', []);
        $connectionConfig = is_array($rateLimiterConfig['connection'] ?? null)
            ? $rateLimiterConfig['connection']
            : [];

        $enabled = $connectionConfig['enabled'] ?? false;
        if (!$enabled) {
            return null;
        }

        $defaultMax = (int)($connectionConfig['max_messages_per_second'] ?? 60);
        $defaultTerminate = (bool)($connectionConfig['terminate_on_limit'] ?? false);

        $appConfigs = [];
        foreach ($applicationManager->getApplications() as $appId => $appConfig) {
            $overrides = [];
            if (isset($appConfig['max_connection_messages_per_second'])) {
                $overrides['max_messages_per_second'] = $appConfig['max_connection_messages_per_second'];
            }

            if (array_key_exists('connection_rate_limit_terminate', $appConfig)) {
                $overrides['terminate_on_limit'] = $appConfig['connection_rate_limit_terminate'];
            }

            if ($overrides !== []) {
                $appConfigs[(string)$appId] = $overrides;
            }
        }

        return new ConnectionMessageRateLimiter($defaultMax, $defaultTerminate, $appConfigs);
    }
}
