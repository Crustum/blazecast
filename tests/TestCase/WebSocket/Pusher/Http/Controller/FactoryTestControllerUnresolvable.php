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
    private string $unresolvableParam;

    /**
     * @param string $unresolvableParam Intentionally unresolvable for factory tests
     */
    public function __construct(string $unresolvableParam)
    {
        $this->unresolvableParam = $unresolvableParam;
    }

    public function __invoke(RequestInterface $request, Connection $connection, array $params = []): Response
    {
        return $this->handle($request, $connection, $params);
    }

    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        return new Response($this->unresolvableParam === '' ? 'test' : 'test');
    }
}
