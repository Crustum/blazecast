<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Test controller with unresolvable parameters
 */
class FactoryTestControllerUnresolvable implements PusherControllerInterface
{
    /** @phpstan-ignore-next-line TODO: Test class - unused parameters are intentional for testing */
    public function __construct(string $unresolvableParam)
    {
        // Constructor with unresolvable parameter
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
