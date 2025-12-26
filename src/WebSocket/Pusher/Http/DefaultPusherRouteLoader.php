<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http;

use Crustum\BlazeCast\WebSocket\Http\PusherRouteBuilder;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\AppInfoController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\AuthController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ChannelController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ChannelsController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ChannelUsersController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ConnectionsController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\EventsController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\HealthCheckController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\MetricsController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\UsersTerminateController;
use Symfony\Component\Routing\RouteCollection;

/**
 * DefaultPusherRouteLoader
 *
 * Default implementation of PusherRouteLoaderInterface that provides
 * standard Pusher API routes.
 *
 * @phpstan-type Controllers array<string, \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface>
 */
class DefaultPusherRouteLoader implements PusherRouteLoaderInterface
{
    /**
     * Route collection
     *
     * @var \Symfony\Component\Routing\RouteCollection
     */
    protected RouteCollection $routes;

    /**
     * Controller registry
     *
     * @var array<string, \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface>
     */
    protected array $controllers = [];

    /**
     * Constructor
     *
     * @param \Symfony\Component\Routing\RouteCollection|null $routes Optional route collection to use
     */
    public function __construct(?RouteCollection $routes = null)
    {
        $this->routes = $routes ?? new RouteCollection();
    }

    /**
     * @inheritDoc
     */
    public function registerRoutes(PusherRouteBuilder $builder): void
    {
        $builder->post('/apps/{appId}/events', EventsController::class, 'pusher.events.trigger');
        $builder->post('/apps/{appId}/batch_events', EventsController::class, 'pusher.events.batch');

        $builder->get('/apps/{appId}/channels', ChannelsController::class, 'pusher.channels.index');
        $builder->get('/apps/{appId}/channels/{channelName}', ChannelController::class, 'pusher.channels.show');

        $builder->get('/apps/{appId}/channels/{channelName}/users', ChannelUsersController::class, 'pusher.channels.users');
        $builder->post('/apps/{appId}/users/{userId}/terminate_connections', UsersTerminateController::class, 'pusher.users.terminate');

        $builder->get('/apps/{appId}/connections', ConnectionsController::class, 'blaze.connections.index');
        $builder->get('/metrics', MetricsController::class, 'blaze.metrics.prometheus');

        $builder->post('/pusher/auth', AuthController::class, 'pusher.auth');

        $builder->get('/up', HealthCheckController::class, 'blaze.health.up');
        $builder->get('/pusher/health', HealthCheckController::class, 'pusher.health');

        $builder->get('/apps/{appId}', AppInfoController::class, 'pusher.apps.show');

        $builder->options(
            '/apps/{appId}',
            AppInfoController::class,
            'pusher.apps.show.options',
        );
        $builder->options(
            '/apps/{appId}/events',
            EventsController::class,
            'pusher.events.trigger.options',
        );
        $builder->options(
            '/apps/{appId}/batch_events',
            EventsController::class,
            'pusher.events.batch.options',
        );
        $builder->options(
            '/apps/{appId}/channels',
            ChannelsController::class,
            'pusher.channels.index.options',
        );
        $builder->options(
            '/apps/{appId}/channels/{channelName}',
            ChannelController::class,
            'pusher.channels.show.options',
        );
        $builder->options(
            '/apps/{appId}/channels/{channelName}/users',
            ChannelUsersController::class,
            'pusher.channels.users.options',
        );
        $builder->options(
            '/apps/{appId}/users/{userId}/terminate_connections',
            UsersTerminateController::class,
            'pusher.users.terminate.options',
        );
        $builder->options(
            '/apps/{appId}/connections',
            ConnectionsController::class,
            'blaze.connections.index.options',
        );
        $builder->options(
            '/pusher/auth',
            AuthController::class,
            'pusher.auth.options',
        );
        $builder->options(
            '/up',
            HealthCheckController::class,
            'blaze.health.up.options',
        );
        $builder->options(
            '/pusher/health',
            HealthCheckController::class,
            'pusher.health.options',
        );

            BlazeCastLogger::info('Default Pusher and BlazeCast routes registered', [
            'scope' => ['socket.router', 'socket.router.loader'],
            'route_count' => count($builder->buildRoutes()),
            ]);
    }

    /**
     * @inheritDoc
     */
    public function getRouteCollection(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Register controllers
     *
     * @param Controllers $controllers Array of controllers keyed by name
     * @return void
     */
    public function registerControllers(array $controllers): void
    {
        $this->controllers = $controllers;
    }
}
