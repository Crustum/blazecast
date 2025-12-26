<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket;

use Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface;

/**
 * WebSocketServerInterface
 *
 * Minimal interface that WebSocket servers must implement to work with handlers.
 * Provides the essential methods that handlers expect from a server.
 */
interface WebSocketServerInterface
{
    /**
     * Get a connection by ID
     *
     * @param string $connectionId Connection ID
     * @return \Crustum\BlazeCast\WebSocket\Connection|null
     */
    public function getConnection(string $connectionId): ?Connection;

    /**
     * Get all active connections
     *
     * @return array<string, \Crustum\BlazeCast\WebSocket\Connection>
     */
    public function getConnections(): array;

    /**
     * Subscribe connection to channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $channelName Channel name
     * @return void
     */
    public function subscribeToChannel(Connection $connection, string $channelName): void;

    /**
     * Unsubscribe connection from channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $channelName Channel name
     * @return void
     */
    public function unsubscribeFromChannel(Connection $connection, string $channelName): void;

    /**
     * Get all connections subscribed to a channel
     *
     * @param string $channelName Channel name
     * @return array<\Crustum\BlazeCast\WebSocket\Connection>
     */
    public function getChannelConnections(string $channelName): array;

    /**
     * Broadcast a message to all active connections
     *
     * @param string $message Message to broadcast
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     */
    public function broadcast(string $message, ?string $exceptConnectionId = null): void;

    /**
     * Broadcast a message to all connections in a channel
     *
     * @param string $channelName Channel name
     * @param string $message Message to broadcast
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     */
    public function broadcastToChannel(string $channelName, string $message, ?string $exceptConnectionId = null): void;

    /**
     * Get application manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager|null
     */
    public function getApplicationManager(): mixed;

    /**
     * Get channel manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager|null
     */
    public function getChannelManager(): mixed;

    /**
     * Get connection manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager|null
     */
    public function getConnectionManager(): mixed;

    /**
     * Get application context resolver
     *
     * @return \Crustum\BlazeCast\WebSocket\ApplicationContextResolver|null
     */
    public function getApplicationContextResolver(): mixed;

    /**
     * Get channel operations manager
     *
     * @return \Crustum\BlazeCast\WebSocket\ChannelOperationsManager|null
     */
    public function getChannelOperationsManager(): mixed;

    /**
     * Get connection registry
     *
     * @return \Crustum\BlazeCast\WebSocket\ConnectionRegistry|null
     */
    public function getConnectionRegistry(): mixed;

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
    public function subscribeToChannelWithAuth(Connection $connection, string $channelName, ?string $auth = null, ?string $channelData = null): void;

    /**
     * Get application ID for a connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return string|null Application ID or null if not found
     */
    public function getAppIdForConnection(Connection $connection): ?string;

    /**
     * Get rate limiter
     *
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|\Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface|null
     */
    public function getRateLimiter(): RateLimiterInterface|AsyncRateLimiterInterface|null;
}
