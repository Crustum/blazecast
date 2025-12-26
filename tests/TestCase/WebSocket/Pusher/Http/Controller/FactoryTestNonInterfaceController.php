<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Test controller that doesn't implement interface
 */
class FactoryTestNonInterfaceController
{
    /**
     * @param RequestInterface $request
     * @param Connection $connection
     * @param array<string, mixed> $params
     * @return Response
     */
    public function __invoke(RequestInterface $request, Connection $connection, array $params = []): Response
    {
        return new Response('test');
    }
}
