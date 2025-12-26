<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket;

use Cake\Event\EventManager;
use Crustum\BlazeCast\WebSocket\Event\ConnectionClosedEvent;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;

/**
 * ConnectionRegistry
 *
 * Centralized connection tracking and lifecycle management.
 *
 * @phpstan-import-type ConnectionInfo from \Crustum\BlazeCast\WebSocket\ApplicationContextResolver
 */
class ConnectionRegistry
{
    /**
     * Active connections
     *
     * @var array<string, ConnectionInfo|Connection>
     */
    protected array $activeConnections = [];

    /**
     * Connection manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager
     */
    protected ChannelConnectionManager $connectionManager;

    /**
     * Event manager
     *
     * @var \Cake\Event\EventManager
     */
    protected EventManager $eventManager;

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager $connectionManager Connection manager
     * @param \Cake\Event\EventManager $eventManager Event manager
     */
    public function __construct(ChannelConnectionManager $connectionManager, EventManager $eventManager)
    {
        $this->connectionManager = $connectionManager;
        $this->eventManager = $eventManager;
    }

    /**
     * Register a new connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection to register
     * @param array<string, mixed> $connectionInfo Additional connection information
     * @return void
     */
    public function register(Connection $connection, array $connectionInfo = []): void
    {
        $connectionId = $connection->getId();

        $appContext = [];
        if ($connection->getAttribute('app_id')) {
            $appContext['app_id'] = $connection->getAttribute('app_id');
        }
        if ($connection->getAttribute('app_key')) {
            $appContext['app_key'] = $connection->getAttribute('app_key');
        }
        if ($connection->getAttribute('app_context')) {
            $appContext['app_context'] = $connection->getAttribute('app_context');
        }

        $this->activeConnections[$connectionId] = array_merge([
            'connection' => $connection,
            'registered_at' => time(),
        ], $connectionInfo, $appContext);

        BlazeCastLogger::debug(sprintf('Connection %s registered', $connectionId), [
            'scope' => ['socket.registry', 'socket.registry.connection'],
        ]);
    }

    /**
     * Update connection ID (for socket ID changes)
     *
     * @param string $oldId Old connection ID
     * @param string $newId New connection ID
     * @return void
     */
    public function updateConnectionId(string $oldId, string $newId): void
    {
        if (isset($this->activeConnections[$oldId])) {
            $connectionInfo = $this->activeConnections[$oldId];
            unset($this->activeConnections[$oldId]);
            $this->activeConnections[$newId] = $connectionInfo;

            BlazeCastLogger::debug(sprintf('Connection ID updated from %s to %s', $oldId, $newId), [
                'scope' => ['socket.registry', 'socket.registry.connection'],
            ]);
        }
    }

    /**
     * Get a connection by ID
     *
     * @param string $connectionId Connection ID
     * @return \Crustum\BlazeCast\WebSocket\Connection|null
     */
    public function getConnection(string $connectionId): ?Connection
    {
        $connectionInfo = $this->activeConnections[$connectionId] ?? null;

        if (!$connectionInfo) {
            return null;
        }

        if ($connectionInfo instanceof Connection) {
            return $connectionInfo;
        }

        return $connectionInfo['connection'] ?? null;
    }

    /**
     * Get all active connections
     *
     * @return array<string, \Crustum\BlazeCast\WebSocket\Connection>
     */
    public function getConnections(): array
    {
        $connections = [];

        foreach ($this->activeConnections as $connectionId => $connectionInfo) {
            if ($connectionInfo instanceof Connection) {
                $connections[$connectionId] = $connectionInfo;
            } elseif (isset($connectionInfo['connection'])) {
                $connections[$connectionId] = $connectionInfo['connection'];
            }
        }

        return $connections;
    }

    /**
     * Get connection info
     *
     * @param string $connectionId Connection ID
     * @return ConnectionInfo|null
     */
    public function getConnectionInfo(string $connectionId): ?array
    {
        return $this->activeConnections[$connectionId] ?? null;
    }

    /**
     * Update connection info
     *
     * @param string $connectionId Connection ID
     * @param array<string, mixed> $info Information to merge
     * @return void
     */
    public function updateConnectionInfo(string $connectionId, array $info): void
    {
        if (isset($this->activeConnections[$connectionId])) {
            $this->activeConnections[$connectionId] = array_merge(
                $this->activeConnections[$connectionId],
                $info,
            );
        }
    }

    /**
     * Handle connection disconnect
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection that disconnected
     * @param callable|null $unsubscribeCallback Callback to unsubscribe from channels
     * @return void
     */
    public function handleConnectionDisconnect(Connection $connection, ?callable $unsubscribeCallback = null): void
    {
        $connectionId = $connection->getId();

        if ($connection->isConnected()) {
            $connection->close();
        }

        if ($unsubscribeCallback) {
            $subscribedChannelNames = $this->connectionManager->getChannelNamesForConnection($connection);

            foreach ($subscribedChannelNames as $channelName) {
                $unsubscribeCallback($connection, $channelName);
            }
        }

        $this->connectionManager->unsubscribeAll($connection);
        $this->eventManager->dispatch(new ConnectionClosedEvent($connection));

        $this->unregister($connectionId);

            BlazeCastLogger::info(__('Connection {0} disconnected and cleaned up from all channels', $connectionId), [
            'scope' => ['socket.registry', 'socket.registry.connection'],
            ]);
    }

    /**
     * Unregister a connection
     *
     * @param string $connectionId Connection ID to unregister
     * @return void
     */
    public function unregister(string $connectionId): void
    {
        if (isset($this->activeConnections[$connectionId])) {
            unset($this->activeConnections[$connectionId]);

            BlazeCastLogger::debug(sprintf('Connection %s unregistered', $connectionId), [
                'scope' => ['socket.registry', 'socket.registry.connection'],
            ]);
        }
    }

    /**
     * Get total connection count
     *
     * @return int
     */
    public function getConnectionCount(): int
    {
        return count($this->activeConnections);
    }

    /**
     * Clear all connections
     *
     * @return void
     */
    public function clear(): void
    {
        foreach ($this->activeConnections as $connectionInfo) {
            if (isset($connectionInfo['connection'])) {
                $connection = $connectionInfo['connection'];
                if ($connection->isConnected()) {
                    $connection->close();
                }
            }
        }

        $this->activeConnections = [];

            BlazeCastLogger::info('All connections cleared from registry', [
            'scope' => ['socket.registry', 'socket.registry.connection'],
            ]);
    }

    /**
     * Get the connection manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager
     */
    public function getConnectionManager(): ChannelConnectionManager
    {
        return $this->connectionManager;
    }
}
