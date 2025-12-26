<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Test controller with no constructor
 */
class FactoryTestController implements PusherControllerInterface
{
    public function __invoke(RequestInterface $request, Connection $connection, array $params = []): Response
    {
        return $this->handle($request, $connection, $params);
    }

    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        return new Response('test');
    }
}
