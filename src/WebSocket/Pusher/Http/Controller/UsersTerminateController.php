<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Exception;
use Psr\Http\Message\RequestInterface;

/**
 * Users Terminate Controller
 *
 * Handles POST /apps/{appId}/users/{userId}/terminate_connections endpoint
 * for terminating all connections for a specific user.
 *
 * @phpstan-import-type RouteParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-type UserConnections array<string>
 */
class UsersTerminateController extends PusherController
{
    /**
     * Handle the request to terminate all connections for a user
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param RouteParams $params Route parameters containing appId and userId
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        $appId = $params['appId'] ?? null;
        $userId = $params['userId'] ?? null;

        if (!$appId || !$userId) {
            BlazeCastLogger::warning(sprintf('UsersTerminateController: Missing required parameters. app_id=%s, user_id=%s', $appId ?? 'null', $userId ?? 'null'), [
                'scope' => ['socket.controller', 'socket.controller.users'],
            ]);

            return $this->errorResponse('Missing appId or userId parameters', 400);
        }

        BlazeCastLogger::info(sprintf('UsersTerminateController: Terminating connections for user. app_id=%s, user_id=%s', $appId, $userId), [
            'scope' => ['socket.controller', 'socket.controller.users'],
        ]);

        try {
            $terminatedCount = $this->terminateUserConnections($userId);

            BlazeCastLogger::info(sprintf('UsersTerminateController: User connections terminated successfully. app_id=%s, user_id=%s, terminated_count=%d', $appId, $userId, $terminatedCount), [
                'scope' => ['socket.controller', 'socket.controller.users'],
            ]);

            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'User connections terminated',
                'terminated_connections' => $terminatedCount,
            ]);
        } catch (Exception $e) {
            BlazeCastLogger::error(sprintf('UsersTerminateController: Error terminating user connections. app_id=%s, user_id=%s, error=%s, trace=%s', $appId, $userId, $e->getMessage(), $e->getTraceAsString()), [
                'scope' => ['socket.controller', 'socket.controller.users'],
            ]);

            return $this->errorResponse('Failed to terminate user connections', 500);
        }
    }

    /**
     * Terminate all connections for a specific user
     *
     * @param string $userId User ID to terminate connections for
     * @return int Number of connections terminated
     */
    protected function terminateUserConnections(string $userId): int
    {
        $terminatedCount = 0;
        $activeChannelNames = $this->connectionManager->getActiveChannelNames();
        $channelManager = $this->getCurrentChannelManager();

        BlazeCastLogger::debug(sprintf('UsersTerminateController: Scanning connections for user. user_id=%s, active_channels=%d', $userId, count($activeChannelNames)), [
            'scope' => ['socket.controller', 'socket.controller.users'],
        ]);

        foreach ($activeChannelNames as $channelName) {
            $channel = $channelManager->getChannel($channelName);
            $connections = $this->connectionManager->getConnectionsForChannel($channel);

            foreach ($connections as $connectionId => $connection) {
                if ($this->isUserConnection($connection, $userId)) {
                    try {
                        $this->disconnectConnection($connectionId, $connection);
                        $terminatedCount++;

                        BlazeCastLogger::debug(sprintf('UsersTerminateController: Connection terminated. user_id=%s, connection_id=%s, channel=%s', $userId, $connectionId, $channelName), [
                            'scope' => ['socket.controller', 'socket.controller.users'],
                        ]);
                    } catch (Exception $e) {
                        BlazeCastLogger::warning(sprintf('UsersTerminateController: Failed to terminate connection. user_id=%s, connection_id=%s, error=%s', $userId, $connectionId, $e->getMessage()), [
                            'scope' => ['socket.controller', 'socket.controller.users'],
                        ]);
                    }
                }
            }
        }

        return $terminatedCount;
    }

    /**
     * Check if a connection belongs to the specified user
     *
     * @param mixed $connection Connection object
     * @param string $userId User ID to check against
     * @return bool True if connection belongs to user
     */
    protected function isUserConnection(mixed $connection, string $userId): bool
    {
        $connectionUserId = $connection->getAttribute('user_id')
            ?? $connection->getAttribute('userId')
            ?? null;

        if (!$connectionUserId && method_exists($connection, 'getAttributes')) {
            $attributes = $connection->getAttributes();
            $connectionUserId = $attributes['user_id'] ?? $attributes['userId'] ?? null;
        }

        return $connectionUserId && (string)$connectionUserId === (string)$userId;
    }

    /**
     * Disconnect a specific connection
     *
     * @param string $connectionId Connection ID
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection object
     * @return void
     */
    protected function disconnectConnection(string $connectionId, Connection $connection): void
    {
        $connection->close();
        $this->connectionManager->unsubscribeAll($connection);
    }

    /**
     * Get user connections for debugging
     *
     * @param string $userId User ID
     * @return UserConnections List of connection IDs for the user
     */
    public function getUserConnections(string $userId): array
    {
        $userConnections = [];
        $activeChannelNames = $this->connectionManager->getActiveChannelNames();
        $channelManager = $this->getCurrentChannelManager();

        foreach ($activeChannelNames as $channelName) {
            $channel = $channelManager->getChannel($channelName);
            $connections = $this->connectionManager->getConnectionsForChannel($channel);

            foreach ($connections as $connectionId => $connection) {
                if ($this->isUserConnection($connection, $userId) && !in_array($connectionId, $userConnections)) {
                    $userConnections[] = $connectionId;
                }
            }
        }

        return $userConnections;
    }
}
