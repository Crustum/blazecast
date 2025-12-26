<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * ChannelsController
 *
 * Handles GET /apps/{appId}/channels - List channels
 *
 * @phpstan-import-type RouteParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 */
class ChannelsController extends PusherController
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
        $appId = $this->application['id'] ?? 'unknown';

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

        $filter = $this->query['filter_by_prefix'] ?? null;
        $info = $this->query['info'] ?? null;

        $metricsHandler = $this->getMetricsHandler();
        $channels = $metricsHandler->gather($this->application, 'channels', [
            'filter_by_prefix' => $filter,
            'info' => $info,
        ]);

        return $this->jsonResponse(['channels' => $channels]);
    }
}
