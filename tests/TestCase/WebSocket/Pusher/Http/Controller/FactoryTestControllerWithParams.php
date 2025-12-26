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
    /** @phpstan-ignore-next-line TODO: Test class - unused parameters are intentional for testing */
    public function __construct(
        PusherApplicationManager $applicationManager,
        ChannelManager $channelManager,
        ChannelConnectionManager $connectionManager,
    ) {
        // Constructor with required parameters
    }

    public function __invoke(RequestInterface $request, Connection $connection, array $params = []): Response
    {
        return $this->handle($request, $connection, $params);
    }

    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        return new Response('test');
    }
}
