<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * ChannelUsersController
 *
 * Controller for retrieving users in a presence channel.
 *
 * @phpstan-import-type RouteParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 */
class ChannelUsersController extends PusherController
{
    /**
     * Handle the request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection HTTP connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        $channelName = $params['channelName'] ?? $params['channel'] ?? null;

        if (!$channelName) {
            return $this->errorResponse('Channel name is required', 400);
        }

        if (!str_starts_with($channelName, 'presence-')) {
            return $this->errorResponse('Only presence channels have users', 400);
        }

        $users = $this->getMetricsHandler()->gather($this->application, 'channel_users', [
            'channel' => $channelName,
        ]);

        return $this->jsonResponse(['users' => $users]);
    }

    /**
     * Index method for compatibility with tests
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection HTTP connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function index(RequestInterface $request, Connection $connection, array $params): Response
    {
        return $this->handle($request, $connection, $params);
    }
}
