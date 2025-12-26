<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket;

use Cake\Event\EventManager;
use Crustum\BlazeCast\WebSocket\Event\ChannelSubscribedEvent;
use Crustum\BlazeCast\WebSocket\Event\ChannelUnsubscribedEvent;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Exception;

/**
 * ChannelOperationsManager
 *
 * Handles channel operations including message broadcasting and connection subscription.
 */
class ChannelOperationsManager
{
    protected ApplicationManager $applicationManager;
    protected ConnectionRegistry $connectionRegistry;
    protected ChannelConnectionManager $connectionManager;
    protected EventManager $eventManager;
    protected ApplicationContextResolver $contextResolver;

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param \Crustum\BlazeCast\WebSocket\ConnectionRegistry $connectionRegistry Connection registry
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager $connectionManager Connection manager
     * @param \Cake\Event\EventManager $eventManager Event manager
     * @param \Crustum\BlazeCast\WebSocket\ApplicationContextResolver $contextResolver Application context resolver
     */
    public function __construct(
        ApplicationManager $applicationManager,
        ConnectionRegistry $connectionRegistry,
        ChannelConnectionManager $connectionManager,
        EventManager $eventManager,
        ApplicationContextResolver $contextResolver,
    ) {
        $this->applicationManager = $applicationManager;
        $this->connectionRegistry = $connectionRegistry;
        $this->connectionManager = $connectionManager;
        $this->eventManager = $eventManager;
        $this->contextResolver = $contextResolver;
    }

    /**
     * Broadcast a message to all active connections
     *
     * @param string $message Message to broadcast
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     */
    public function broadcast(string $message, ?string $exceptConnectionId = null): void
    {
        $exceptConnection = $exceptConnectionId ? $this->connectionRegistry->getConnection($exceptConnectionId) : null;
        $connections = $this->connectionRegistry->getConnections();

        foreach ($connections as $connection) {
            if ($exceptConnection && $connection->getId() === $exceptConnection->getId()) {
                continue;
            }

            $connection->send($message);
        }

            BlazeCastLogger::info(__('ChannelOperationsManager: Message broadcasted to all connections. Total connections: {0}, except connection: {1}', count($connections), $exceptConnectionId), [
            'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);
    }

    /**
     * Broadcast a message to all connections in a channel (DEPRECATED - use broadcastToChannelForApp)
     *
     * @param string $channelName Channel name
     * @param string $message Message to broadcast
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     * @deprecated Use broadcastToChannelForApp() for proper multi-app isolation
     */
    public function broadcastToChannel(string $channelName, string $message, ?string $exceptConnectionId = null): void
    {
        BlazeCastLogger::warning(sprintf('ChannelOperationsManager: broadcastToChannel() called without app context - this breaks multi-app isolation on channel %s', $channelName), [
            'scope' => ['socket.manager', 'socket.manager.operations'],
        ]);

        $applications = $this->applicationManager->getApplications();
        if (!empty($applications)) {
            $firstApp = array_values($applications)[0];
            $this->broadcastToChannelForApp($firstApp['id'], $channelName, $message, $exceptConnectionId);
        } else {
            BlazeCastLogger::error(__('ChannelOperationsManager: No applications available for broadcast on channel {0}', $channelName), [
                'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);
        }
    }

    /**
     * Broadcast a message to connections in a channel for a specific application
     *
     * @param string $appId Application ID
     * @param string $channelName Channel name
     * @param string $message Message to broadcast
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     */
    public function broadcastToChannelForApp(string $appId, string $channelName, string $message, ?string $exceptConnectionId = null): void
    {
        $application = $this->applicationManager->getApplication($appId);
        if (!$application) {
            BlazeCastLogger::error(__('ChannelOperationsManager: Application not found for broadcast on channel {0} for app {1}', $channelName, $appId), [
                'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);

            return;
        }

        if (!isset($application['channel_manager'])) {
            BlazeCastLogger::error(__('ChannelOperationsManager: ChannelManager not found in application for app {0} on channel {1}', $appId, $channelName), [
                'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);

            return;
        }

        $channelManager = $application['channel_manager'];
        $channel = $channelManager->getChannel($channelName);
        $exceptConnection = $exceptConnectionId ? $this->connectionRegistry->getConnection($exceptConnectionId) : null;

        $messageData = json_decode($message, true);
        if (!$messageData) {
            $messageData = ['data' => $message];
        }

        $connectionCount = $channel->getConnectionCount();
        $channel->broadcast($messageData, $exceptConnection);

            BlazeCastLogger::info(__('ChannelOperationsManager: Message broadcasted to channel {0} for app {1}. Total connections: {2}, except connection: {3}', $channelName, $appId, $connectionCount, $exceptConnectionId), [
            'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);
    }

    /**
     * Get all connections subscribed to a channel across all applications
     *
     * @param string $channelName Channel name
     * @return array<\Crustum\BlazeCast\WebSocket\Connection>
     */
    public function getChannelConnections(string $channelName): array
    {
        $allConnections = [];

        $applications = $this->applicationManager->getApplications();

        foreach ($applications as $application) {
            if (!isset($application['channel_manager'])) {
                continue;
            }

            $appChannelManager = $application['channel_manager'];
            $channel = $appChannelManager->getChannel($channelName);
            $connections = $channel->getConnections();

            foreach ($connections as $connection) {
                $allConnections[$connection->getId()] = $connection;
            }
        }

        return array_values($allConnections);
    }

    /**
     * Get connections for a specific channel in a specific application
     *
     * @param string $appId Application ID
     * @param string $channelName Channel name
     * @return array<\Crustum\BlazeCast\WebSocket\Connection>
     */
    public function getChannelConnectionsForApp(string $appId, string $channelName): array
    {
        $application = $this->applicationManager->getApplication($appId);
        if (!$application) {
            BlazeCastLogger::warning(sprintf('ChannelOperationsManager: Application not found for channel connections on channel %s for app %s', $channelName, $appId), [
                'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);

            return [];
        }

        if (!isset($application['channel_manager'])) {
            BlazeCastLogger::warning(sprintf('ChannelOperationsManager: ChannelManager not found in application for channel connections on channel %s for app %s', $channelName, $appId), [
                'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);

            return [];
        }

        $channelManager = $application['channel_manager'];
        $channel = $channelManager->getChannel($channelName);

        return $channel->getConnections();
    }

    /**
     * Broadcast to multiple channels for a specific application
     *
     * @param string $appId Application ID
     * @param array<string> $channelNames Channel names
     * @param string $message Message to broadcast
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     */
    public function broadcastToChannelsForApp(string $appId, array $channelNames, string $message, ?string $exceptConnectionId = null): void
    {
        foreach ($channelNames as $channelName) {
            $this->broadcastToChannelForApp($appId, $channelName, $message, $exceptConnectionId);
        }

            BlazeCastLogger::info(__('ChannelOperationsManager: Message broadcasted to multiple channels {0} for app {1}. Total channels: {2}, except connection: {3}', implode(', ', $channelNames), $appId, count($channelNames), $exceptConnectionId), [
            'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);
    }

    /**
     * Get total connection count across all applications
     *
     * @return int
     */
    public function getTotalConnectionCount(): int
    {
        return $this->connectionRegistry->getConnectionCount();
    }

    /**
     * Get connection count for a specific application
     *
     * @param string $appId Application ID
     * @return int
     */
    public function getConnectionCountForApp(string $appId): int
    {
        $connections = $this->connectionRegistry->getConnections();
        $count = 0;

        foreach ($connections as $connection) {
            $connectionInfo = $this->connectionRegistry->getConnectionInfo($connection->getId());
            if ($connectionInfo && isset($connectionInfo['app_context']['app_id']) && $connectionInfo['app_context']['app_id'] === $appId) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get application manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    public function getApplicationManager(): ApplicationManager
    {
        return $this->applicationManager;
    }

    /**
     * Get connection registry
     *
     * @return \Crustum\BlazeCast\WebSocket\ConnectionRegistry
     */
    public function getConnectionRegistry(): ConnectionRegistry
    {
        return $this->connectionRegistry;
    }

    /**
     * Subscribe connection to channel using application-specific ChannelManager
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $channelName Channel name
     * @return void
     */
    public function subscribeToChannel(Connection $connection, string $channelName): void
    {
        $channelManager = $this->contextResolver->getChannelManagerForConnection($connection, []);
        if (!$channelManager) {
            BlazeCastLogger::error(__('ChannelOperationsManager: No ChannelManager found for connection {0} on channel {1}', $connection->getId(), $channelName), [
                'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);

            return;
        }

        $channel = $channelManager->getChannel($channelName);
        $channel->subscribe($connection);

        $this->connectionManager->subscribe($connection, $channel);

        $event = new ChannelSubscribedEvent($connection, $channel);
        $this->eventManager->dispatch($event);

            BlazeCastLogger::info(__('ChannelOperationsManager: Connection {0} subscribed to channel {1} via application-specific ChannelManager for app {2}', $connection->getId(), $channelName, $this->contextResolver->getAppIdForConnection($connection, [])), [
            'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);
    }

    /**
     * Subscribe connection to channel with authentication data
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $channelName Channel name
     * @param string|null $auth Authentication token
     * @param string|null $channelData Channel data
     * @return void
     * @throws \Exception If authentication fails
     */
    public function subscribeToChannelWithAuth(Connection $connection, string $channelName, ?string $auth = null, ?string $channelData = null): void
    {
        $channelManager = $this->contextResolver->getChannelManagerForConnection($connection, []);
        if (!$channelManager) {
            BlazeCastLogger::error('No ChannelManager found for connection', [
                'scope' => ['socket.manager', 'socket.manager.operations'],
                'connection_id' => $connection->getId(),
                'channel' => $channelName,
            ]);
            throw new Exception('ChannelManager not available');
        }

        $channel = $channelManager->getChannel($channelName);

        if (is_callable([$channel, 'setApplicationManager'])) {
            call_user_func([$channel, 'setApplicationManager'], $this->applicationManager);
        }

        $channel->subscribe($connection, $auth, $channelData);

        $this->connectionManager->subscribe($connection, $channel);

        $event = new ChannelSubscribedEvent($connection, $channel);
        $this->eventManager->dispatch($event);

            BlazeCastLogger::info(__('ChannelOperationsManager: Connection {0} subscribed to channel {1} via application-specific ChannelManager for app {2} with authentication: {3}', $connection->getId(), $channelName, $this->contextResolver->getAppIdForConnection($connection, []), !empty($auth)), [
            'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);
    }

    /**
     * Unsubscribe connection from channel using application-specific ChannelManager
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $channelName Channel name
     * @return void
     */
    public function unsubscribeFromChannel(Connection $connection, string $channelName): void
    {
        $channelManager = $this->contextResolver->getChannelManagerForConnection($connection, []);
        if (!$channelManager) {
            BlazeCastLogger::error(__('ChannelOperationsManager: No ChannelManager found for connection {0} on channel {1} for unsubscribe', $connection->getId(), $channelName), [
                'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);

            return;
        }

        $channel = $channelManager->getChannel($channelName);
        $channel->unsubscribe($connection);

        $this->connectionManager->unsubscribe($connection, $channel);

        $event = new ChannelUnsubscribedEvent($connection, $channel);
        $this->eventManager->dispatch($event);

            BlazeCastLogger::info(__('ChannelOperationsManager: Connection {0} unsubscribed from channel {1} via application-specific ChannelManager for app {2}', $connection->getId(), $channelName, $this->contextResolver->getAppIdForConnection($connection, [])), [
            'scope' => ['socket.manager', 'socket.manager.operations'],
            ]);
    }
}
