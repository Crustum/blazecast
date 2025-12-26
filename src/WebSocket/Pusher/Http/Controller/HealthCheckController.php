<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * HealthCheckController
 *
 * Simple health check endpoint for Pusher API
 *
 * @phpstan-import-type RouteParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 */
class HealthCheckController extends PusherController
{
    /**
     * Handle the request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        return $this->jsonResponse(['health' => 'OK']);
    }
}
