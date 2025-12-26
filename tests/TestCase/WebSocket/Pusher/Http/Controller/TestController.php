<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

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
        $paramValue = $params['param1'] ?? 'default';

        return new Response("Test response with {$paramValue}");
    }

    /**
     * Handle request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection HTTP connection
     * @param array<string, mixed> $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        return $this->__invoke($request, $connection, $params);
    }
}
