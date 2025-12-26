<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherController;
use Psr\Http\Message\RequestInterface;

/**
 * Test controller to expose protected methods
 */
class TestSignatureController extends PusherController
{
    /**
     * Handle method (required by abstract parent)
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param array<string, mixed> $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        return new Response('test');
    }

    /**
     * Expose verifySignature method for testing
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param array<string, mixed> $params Parameters to include in signature
     * @param string $secret Application secret key
     * @return bool
     */
    public function testVerifySignature(RequestInterface $request, array $params, string $secret): bool
    {
        return $this->verifySignature($request, $params, $secret);
    }
}
