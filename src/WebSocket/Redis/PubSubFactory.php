<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Redis;

use Cake\Core\Configure;
use Crustum\BlazeCast\WebSocket\Http\PusherRouter;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use React\EventLoop\Factory as LoopFactory;
use Symfony\Component\Routing\RouteCollection;

/**
 * Factory for Redis PubSub
 */
class PubSubFactory
{
    /**
     * Singleton instance
     *
     * @var \Crustum\BlazeCast\WebSocket\Redis\PubSub|null
     */
    protected static ?PubSub $instance = null;

    /**
     * Get the PubSub instance
     *
     * @return \Crustum\BlazeCast\WebSocket\Redis\PubSub
     */
    public static function getInstance(): PubSub
    {
        if (static::$instance === null) {
            static::createInstance();
        }

        return static::$instance;
    }

    /**
     * Create a PubSub instance
     *
     * @return void
     */
    protected static function createInstance(): void
    {
        $config = Configure::read('BlazeCast.redis');

        if (empty($config)) {
            $config = [
                'uri' => 'redis://localhost:6379',
                'options' => [
                    'database' => 0,
                ],
            ];
        }

        $loop = LoopFactory::create();

        $routes = new RouteCollection();
        $httpRouter = new PusherRouter($routes);
        $channelManager = new ChannelManager();
        $connectionManager = new ChannelConnectionManager();
        $applicationManager = new ApplicationManager();

        $server = new Server(
            $httpRouter,
            $channelManager,
            $connectionManager,
            $applicationManager,
            '127.0.0.1',
            8999,
            ['test_mode' => true],
            $loop,
        );

        static::$instance = new PubSub($loop, $server, $config);
    }

    /**
     * Set the PubSub instance
     *
     * @param \Crustum\BlazeCast\WebSocket\Redis\PubSub $pubSub PubSub instance
     * @return void
     */
    public static function setInstance(PubSub $pubSub): void
    {
        static::$instance = $pubSub;
    }
}
