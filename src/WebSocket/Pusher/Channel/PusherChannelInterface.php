<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Connection;

/**
 * Pusher Channel Interface
 *
 * Extended channel interface for Pusher protocol with connection management.
 *
 * @phpstan-import-type ChannelMetadata from \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel
 * @phpstan-import-type ChannelData from \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel
 * @phpstan-import-type BroadcastPayload from \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel
 * @phpstan-import-type ChannelStats from \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel
 */
interface PusherChannelInterface
{
    /**
     * Get the channel name
     *
     * @return string Channel name
     */
    public function getName(): string;

    /**
     * Get the channel type
     *
     * @return string Channel type (public, private, presence, etc.)
     */
    public function getType(): string;

    /**
     * Get channel metadata
     *
     * @return ChannelMetadata Channel metadata
     */
    public function getMetadata(): array;

    /**
     * Convert channel to array representation
     *
     * @return ChannelData Channel data as array
     */
    public function toArray(): array;

    /**
     * Create channel from array representation
     *
     * @param ChannelData $data Channel data
     * @return static Channel instance
     */
    public static function fromArray(array $data): static;

    /**
     * Subscribe connection to channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string|null $auth Authentication token
     * @param string|null $data Channel data
     * @return void
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void;

    /**
     * Unsubscribe connection from channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return void
     */
    public function unsubscribe(Connection $connection): void;

    /**
     * Check if connection is subscribed to channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return bool
     */
    public function isSubscribed(Connection $connection): bool;

    /**
     * Get all connections for the channel
     *
     * @return array<string, \Crustum\BlazeCast\WebSocket\Connection>
     */
    public function getConnections(): array;

    /**
     * Get connection count
     *
     * @return int
     */
    public function getConnectionCount(): int;

    /**
     * Check if channel is empty
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Get channel statistics
     *
     * @return ChannelStats
     */
    public function getStats(): array;

    /**
     * Broadcast message to channel connections
     *
     * @param BroadcastPayload $payload Message payload
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $except Connection to exclude
     * @return void
     */
    public function broadcast(array $payload, ?Connection $except = null): void;

    /**
     * Find a connection by ID
     *
     * @param string $connectionId Connection ID
     * @return \Crustum\BlazeCast\WebSocket\Connection|null
     */
    public function findConnection(string $connectionId): ?Connection;

    /**
     * Check if connection exists in channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return bool
     */
    public function hasConnection(Connection $connection): bool;

    /**
     * Get members for presence channels
     *
     * @return array<string, mixed> Members data
     */
    public function getMembers(): array;

    /**
     * Get member count for presence channels
     *
     * @return int Member count
     */
    public function getMemberCount(): int;

    /**
     * Get presence channel statistics
     *
     * @return array<string, mixed> Presence statistics
     */
    public function getPresenceStats(): array;

    /**
     * Get cache channel statistics
     *
     * @return array<string, mixed> Cache statistics
     */
    public function getCacheStats(): array;
}
