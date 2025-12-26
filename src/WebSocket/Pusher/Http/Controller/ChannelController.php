<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * ChannelController
 *
 * Controller for retrieving information about a specific channel.
 *
 * @phpstan-import-type RouteParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 */
class ChannelController extends PusherController
{
    /**
     * Handle the request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        $channelName = $params['channelName'] ?? null;

        if (!$channelName) {
            return $this->errorResponse('Channel name is required', 400);
        }

        $info = isset($this->query['info']) ? $this->query['info'] . ',occupied' : 'occupied';

        $metricsHandler = $this->getMetricsHandler();
        $channelInfo = $metricsHandler->gather($this->application, 'channel', [
            'channel' => $channelName,
            'info' => $info,
        ]);

        return $this->jsonResponse($channelInfo);
    }
}
