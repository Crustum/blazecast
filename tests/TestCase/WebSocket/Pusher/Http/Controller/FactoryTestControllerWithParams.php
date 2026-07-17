<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager as PusherApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Psr\Http\Message\RequestInterface;

/**
 * Test controller with constructor parameters
 */
class FactoryTestControllerWithParams implements PusherControllerInterface
{
    private PusherApplicationManager $applicationManager;

    private ChannelManager $channelManager;

    private ChannelConnectionManager $connectionManager;

    /**
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager $channelManager Channel manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager $connectionManager Connection manager
     */
    public function __construct(
        PusherApplicationManager $applicationManager,
        ChannelManager $channelManager,
        ChannelConnectionManager $connectionManager,
    ) {
        $this->applicationManager = $applicationManager;
        $this->channelManager = $channelManager;
        $this->connectionManager = $connectionManager;
    }

    public function __invoke(RequestInterface $request, Connection $connection, array $params = []): Response
    {
        return $this->handle($request, $connection, $params);
    }

    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        return new Response(
            $this->applicationManager::class
            . $this->channelManager::class
            . $this->connectionManager::class,
        );
    }
}
