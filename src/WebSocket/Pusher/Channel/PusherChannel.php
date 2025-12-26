<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use JsonException;
use JsonSerializable;

/**
 * Pusher Channel
 *
 * Base channel implementation for Pusher protocol.
 *
 * @phpstan-consistent-constructor
 *
 * @phpstan-type ChannelMetadata array<string, mixed>
 *
 * @phpstan-type BroadcastPayload array{
 *   event: string,
 *   data: mixed,
 *   channel?: string,
 *   socket_id?: string
 * }
 *
 * @phpstan-type ChannelStats array{
 *   name: string,
 *   type: string,
 *   connection_count: int,
 *   occupied: bool
 * }
 *
 * @phpstan-type ChannelData array{
 *   name: string,
 *   type: string,
 *   metadata: array<string, mixed>,
 *   connection_count: int,
 *   connections: array<string>
 * }
 */
class PusherChannel implements PusherChannelInterface, JsonSerializable
{
    /**
     * Channel connections
     *
     * @var array<string, \Crustum\BlazeCast\WebSocket\Connection>
     */
    protected array $connections = [];

    /**
     * Channel metadata
     *
     * @var ChannelMetadata
     */
    protected array $metadata = [];

    /**
     * Constructor
     *
     * @param string $name Channel name
     * @param ChannelMetadata $metadata Channel metadata
     */
    public function __construct(
        protected string $name,
        array $metadata = [],
    ) {
        $this->metadata = $metadata;
    }

    /**
     * Get channel name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set channel name
     *
     * @param string $name Channel name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get channel type
     *
     * @return string
     */
    public function getType(): string
    {
        return 'public';
    }

    /**
     * Set channel type
     *
     * @param string $type Channel type
     * @return void
     */
    public function setType(string $type): void
    {
    }

    /**
     * Get channel metadata
     *
     * @return ChannelMetadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set channel metadata
     *
     * @param ChannelMetadata $metadata Channel metadata
     * @return void
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * Get all connections for the channel
     *
     * @return array<string, \Crustum\BlazeCast\WebSocket\Connection>
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Find a connection by ID
     *
     * @param string $connectionId Connection ID
     * @return \Crustum\BlazeCast\WebSocket\Connection|null
     */
    public function findConnection(string $connectionId): ?Connection
    {
        return $this->connections[$connectionId] ?? null;
    }

    /**
     * Check if connection exists in channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return bool
     */
    public function hasConnection(Connection $connection): bool
    {
        return isset($this->connections[$connection->getId()]);
    }

    /**
     * Subscribe connection to channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string|null $auth Authentication token
     * @param string|null $data Channel data
     * @return void
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        $this->connections[$connection->getId()] = $connection;

        $channelData = [];
        if ($data) {
            try {
                $channelData = json_decode($data, true, 512, JSON_THROW_ON_ERROR) ?? [];
            } catch (JsonException $e) {
                BlazeCastLogger::warning(sprintf('Invalid channel data JSON. channel=%s, connection_id=%s, error=%s', $this->name, $connection->getId(), $e->getMessage()), [
                    'scope' => ['socket.channel', 'socket.channel.pusher'],
                ]);
            }
        }

        $connection->setAttribute("channel_data_{$this->name}", $channelData);

        BlazeCastLogger::info(__('PusherChannel: Connection {0} subscribed to Pusher channel {1} with connection id {2}', $connection->getId(), $this->name, $connection->getId()), [
            'scope' => ['socket.channel', 'socket.channel.pusher'],
        ]);
    }

    /**
     * Unsubscribe connection from channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return void
     */
    public function unsubscribe(Connection $connection): void
    {
        unset($this->connections[$connection->getId()]);

        $connection->removeAttribute("channel_data_{$this->name}");

        BlazeCastLogger::info(__('PusherChannel: Connection {0} unsubscribed from Pusher channel {1} with connection id {2}', $connection->getId(), $this->name, $connection->getId()), [
            'scope' => ['socket.channel', 'socket.channel.pusher'],
        ]);
    }

    /**
     * Check if connection is subscribed to channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return bool
     */
    public function isSubscribed(Connection $connection): bool
    {
        return $this->hasConnection($connection);
    }

    /**
     * Broadcast message to all connections in channel
     *
     * @param BroadcastPayload $payload Message payload
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $except Connection to exclude
     * @return void
     */
    public function broadcast(array $payload, ?Connection $except = null): void
    {
        if ($except === null) {
            $this->broadcastToAll($payload);

            return;
        }

        $message = json_encode($payload);

        BlazeCastLogger::info(__('PusherChannel: Broadcasting to Pusher channel {0} for connection {1}', $this->name, $except->getId()), [
            'scope' => ['socket.channel', 'socket.channel.pusher'],
        ]);

        foreach ($this->connections as $connection) {
            if ($except->getId() === $connection->getId()) {
                continue;
            }

            $connection->send($message);
        }
    }

    /**
     * Broadcast message to all connections
     *
     * @param BroadcastPayload $payload Message payload
     * @return void
     */
    public function broadcastToAll(array $payload): void
    {
        $message = json_encode($payload);

        BlazeCastLogger::info(__('PusherChannel: Broadcasting to all connections in Pusher channel {0} for message {1}', $this->name, $message), [
            'scope' => ['socket.channel', 'socket.channel.pusher'],
        ]);

        foreach ($this->connections as $connection) {
            $connection->send($message);
        }
    }

    /**
     * Broadcast message triggered from internal source
     *
     * @param BroadcastPayload $payload Message payload
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $except Connection to exclude
     * @return void
     */
    public function broadcastInternally(array $payload, ?Connection $except = null): void
    {
        $this->broadcast($payload, $except);
    }

    /**
     * Get channel statistics
     *
     * @return ChannelStats
     */
    public function getStats(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->getType(),
            'connection_count' => count($this->connections),
            'occupied' => !empty($this->connections),
        ];
    }

    /**
     * Check if channel is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->connections);
    }

    /**
     * Get connection count
     *
     * @return int
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * Serialize channel to array
     *
     * @return ChannelData
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->getType(),
            'metadata' => $this->metadata,
            'connection_count' => count($this->connections),
            'connections' => array_keys($this->connections),
        ];
    }

    /**
     * Create channel from array data
     *
     * @param ChannelData $data Channel data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['name'],
            $data['metadata'],
        );
    }

    /**
     * Get channel data for API responses
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [];
    }

    /**
     * Get members for presence channels (default implementation)
     *
     * @return array<string, mixed> Members data
     */
    public function getMembers(): array
    {
        return [];
    }

    /**
     * Get member count for presence channels (default implementation)
     *
     * @return int Member count
     */
    public function getMemberCount(): int
    {
        return 0;
    }

    /**
     * Get presence channel statistics (default implementation)
     *
     * @return array<string, mixed> Presence statistics
     */
    public function getPresenceStats(): array
    {
        return [];
    }

    /**
     * Get cache channel statistics (default implementation)
     *
     * @return array<string, mixed> Cache statistics
     */
    public function getCacheStats(): array
    {
        return [];
    }

    /**
     * JSON serialize the channel
     *
     * @return ChannelData
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
