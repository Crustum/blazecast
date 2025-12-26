<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * ConnectionsController
 *
 * Controller for retrieving connection metrics
 *
 * @phpstan-import-type RouteParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 */
class ConnectionsController extends PusherController
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
        $appId = $params['appId'] ?? ($this->application['id'] ?? 'unknown');

        if ($this->rateLimiter !== null) {
            $rateLimitResult = $this->rateLimiter->consumeReadRequestPoints(1, $appId);

            if ($rateLimitResult->isExceeded()) {
                $response = $this->errorResponse('Rate limit exceeded', 429);
                foreach ($rateLimitResult->getHeaders() as $name => $value) {
                    $response = $response->withHeader($name, (string)$value);
                }

                return $response;
            }
        }

        if ($params['appId'] ?? null) {
            $appStats = $this->connectionManager->getAppStats($appId);
            $appConnections = $this->connectionManager->getConnectionsForApp($appId);

            return $this->jsonResponse([
                'connections' => $appStats['connections'],
                'active_connections' => count($appConnections),
                'total_subscriptions' => $appStats['subscriptions'],
                'http_requests' => $appStats['http_requests'],
            ]);
        }

        $globalStats = $this->connectionManager->getStats();
        $globalConnections = $this->connectionManager->getActiveConnectionIds();
        $allAppStats = $this->connectionManager->getAllAppStats();
        $apps = [];

        foreach ($allAppStats as $appIdKey => $appStats) {
            $appConnections = $this->connectionManager->getConnectionsForApp($appIdKey);
            $apps[$appIdKey] = [
                'connections' => $appStats['connections'],
                'active_connections' => count($appConnections),
                'total_subscriptions' => $appStats['subscriptions'],
                'http_requests' => $appStats['http_requests'],
            ];
        }

        return $this->jsonResponse([
            'connections' => $globalStats['total_connections'],
            'active_connections' => count($globalConnections),
            'total_subscriptions' => $globalStats['total_subscriptions'],
            'apps' => $apps,
        ]);
    }
}
