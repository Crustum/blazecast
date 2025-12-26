<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Test controller implementation
 */
class TestController implements PusherControllerInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(RequestInterface $request, Connection $connection, array $params = []): Response
    {
        return new Response('Test response');
    }

    /**
     * @param RequestInterface $request
     * @param Connection $connection
     * @param array<string, mixed> $params
     * @return Response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        return new Response('Test response');
    }
}
