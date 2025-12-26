<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * AppInfoController
 *
 * Handles GET /apps/{appId} - App Information
 *
 * @phpstan-type RouteParams array<string, mixed>
 */
class AppInfoController extends PusherController
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
        $appId = $params['appId'] ?? 'unknown';

        $channelNames = $this->connectionManager->getActiveChannelNames();
        $channelCount = $this->getCurrentChannelManager()->getChannelCount();

        $appInfo = [
            'id' => $appId,
            'name' => $this->application['name'] ?? 'Unknown App',
            'cluster' => $this->application['cluster'] ?? 'mt1',
            'enabled' => $this->application['enabled'] ?? true,
            'max_connections' => $this->application['max_connections'] ?? 100,
            'enable_client_messages' => $this->application['enable_client_messages'] ?? true,
            'enable_statistics' => $this->application['enable_statistics'] ?? true,
            'enable_debug' => $this->application['enable_debug'] ?? false,
            'channel_count' => $channelCount,
            'channel_names' => $channelNames,
            'created_at' => $this->application['created_at'] ?? null,
        ];

        return $this->jsonResponse($appInfo);
    }
}
