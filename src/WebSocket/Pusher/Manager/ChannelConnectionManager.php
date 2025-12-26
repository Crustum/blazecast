<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Manager;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;

/**
 * Channel Connection Manager
 *
 * Manages connection-to-channel mappings for Pusher protocol with per-app tracking.
 *
 * @phpstan-type ConnectionStats array{
 *   total_connections: int,
 *   total_subscriptions: int
 * }
 * @phpstan-type AppStats array{
 *   connections: int,
 *   subscriptions: int,
 *   http_requests: int
 * }
 * @phpstan-type MappingInfo array{
 *   connection_channels: array<string, array<string>>,
 *   channel_connections: array<string, array<string>>,
 *   stats: ConnectionStats
 * }
 */
class ChannelConnectionManager
{
    /**
     * Connection to channels mapping (global)
     *
     * @var array<string, array<string, \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface>>
     */
    protected array $connectionChannels = [];

    /**
     * Channel to connections mapping (global)
     *
     * @var array<string, array<string, \Crustum\BlazeCast\WebSocket\Connection>>
     */
    protected array $channelConnections = [];

    /**
     * Per-app connection tracking
     *
     * @var array<string, array<string, array<string, \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface>>>
     */
    protected array $appConnections = [];

    /**
     * Per-app statistics
     *
     * @var array<string, AppStats>
     */
    protected array $appStats = [];

    /**
     * @var array<string, int>
     */
    protected array $appBytesReceived = [];

    /**
     * @var array<string, int>
     */
    protected array $appBytesTransmitted = [];

    /**
     * @var array<string, int>
     */
    protected array $appWsMessagesReceived = [];

    /**
     * @var array<string, int>
     */
    protected array $appWsMessagesSent = [];

    /**
     * @var array<string, int>
     */
    protected array $appHttpBytesReceived = [];

    /**
     * @var array<string, int>
     */
    protected array $appHttpBytesTransmitted = [];

    /**
     * @var array<string, int>
     */
    protected array $appNewConnections = [];

    /**
     * @var array<string, int>
     */
    protected array $appDisconnections = [];

    /**
     * Global connection statistics
     *
     * @var ConnectionStats
     */
    protected array $stats = [
        'total_connections' => 0,
        'total_subscriptions' => 0,
    ];

    /**
     * Subscribe connection to channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface $channel Channel
     * @return void
     */
    public function subscribe(Connection $connection, PusherChannelInterface $channel): void
    {
        $connectionId = $connection->getId();
        $channelName = $channel->getName();
        $appId = $this->getAppIdFromConnection($connection);

        // Global tracking (existing)
        if (!isset($this->connectionChannels[$connectionId])) {
            $this->connectionChannels[$connectionId] = [];
            $this->stats['total_connections']++;
        }

        $this->connectionChannels[$connectionId][$channelName] = $channel;

        if (!isset($this->channelConnections[$channelName])) {
            $this->channelConnections[$channelName] = [];
        }

        $this->channelConnections[$channelName][$connectionId] = $connection;
        $this->stats['total_subscriptions']++;

        $this->ensureAppExists($appId);
        if (!isset($this->appConnections[$appId][$connectionId])) {
            $this->appConnections[$appId][$connectionId] = [];
            $this->appStats[$appId]['connections']++;
        }

        $this->appConnections[$appId][$connectionId][$channelName] = $channel;
        $this->appStats[$appId]['subscriptions']++;

            BlazeCastLogger::info(__('ChannelConnectionManager: Connection {0} subscribed to channel {1} for app {2}', $connectionId, $channelName, $appId), [
            'scope' => ['socket.manager', 'socket.manager.connection'],
            ]);
    }

    /**
     * Unsubscribe connection from channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface $channel Channel
     * @return void
     */
    public function unsubscribe(Connection $connection, PusherChannelInterface $channel): void
    {
        $connectionId = $connection->getId();
        $channelName = $channel->getName();
        $appId = $this->getAppIdFromConnection($connection);

        // Global tracking (existing)
        if (isset($this->connectionChannels[$connectionId][$channelName])) {
            unset($this->connectionChannels[$connectionId][$channelName]);
            $this->stats['total_subscriptions']--;

            if (empty($this->connectionChannels[$connectionId])) {
                unset($this->connectionChannels[$connectionId]);
                $this->stats['total_connections']--;
            }
        }

        if (isset($this->channelConnections[$channelName][$connectionId])) {
            unset($this->channelConnections[$channelName][$connectionId]);

            if (empty($this->channelConnections[$channelName])) {
                unset($this->channelConnections[$channelName]);
            }
        }

        if (isset($this->appConnections[$appId][$connectionId][$channelName])) {
            unset($this->appConnections[$appId][$connectionId][$channelName]);
            $this->appStats[$appId]['subscriptions']--;

            if (empty($this->appConnections[$appId][$connectionId])) {
                unset($this->appConnections[$appId][$connectionId]);
                $this->appStats[$appId]['connections']--;
            }
        }

            BlazeCastLogger::info(__('ChannelConnectionManager: Connection {0} unsubscribed from channel {1} for app {2}', $connectionId, $channelName, $appId), [
            'scope' => ['socket.manager', 'socket.manager.connection'],
            ]);
    }

    /**
     * Unsubscribe connection from all channels
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return void
     */
    public function unsubscribeAll(Connection $connection): void
    {
        $connectionId = $connection->getId();
        $appId = $this->getAppIdFromConnection($connection);

        if (!isset($this->connectionChannels[$connectionId])) {
            return;
        }

        $channels = array_keys($this->connectionChannels[$connectionId]);
        $channelCount = count($channels);

        // Global tracking (existing)
        foreach ($channels as $channelName) {
            if (isset($this->channelConnections[$channelName][$connectionId])) {
                unset($this->channelConnections[$channelName][$connectionId]);

                if (empty($this->channelConnections[$channelName])) {
                    unset($this->channelConnections[$channelName]);
                }
            }
        }

        unset($this->connectionChannels[$connectionId]);
        $this->stats['total_connections']--;
        $this->stats['total_subscriptions'] -= $channelCount;

        $this->stats['total_connections'] = max(0, $this->stats['total_connections']);
        $this->stats['total_subscriptions'] = max(0, $this->stats['total_subscriptions']);

        if (isset($this->appConnections[$appId][$connectionId])) {
            $appChannelCount = count($this->appConnections[$appId][$connectionId]);
            unset($this->appConnections[$appId][$connectionId]);
            $this->appStats[$appId]['connections']--;
            $this->appStats[$appId]['subscriptions'] -= $appChannelCount;

            $this->appStats[$appId]['connections'] = max(0, $this->appStats[$appId]['connections']);
            $this->appStats[$appId]['subscriptions'] = max(0, $this->appStats[$appId]['subscriptions']);
        }

            BlazeCastLogger::info(__('ChannelConnectionManager: Connection {0} unsubscribed from all channels for app {1}', $connectionId, $appId), [
            'scope' => ['socket.manager', 'socket.manager.connection'],
            ]);
    }

    /**
     * Get channels for connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return array<string, \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface>
     */
    public function getChannelsForConnection(Connection $connection): array
    {
        return $this->connectionChannels[$connection->getId()] ?? [];
    }

    /**
     * Get connections for channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface $channel Channel
     * @return array<string, \Crustum\BlazeCast\WebSocket\Connection>
     */
    public function getConnectionsForChannel(PusherChannelInterface $channel): array
    {
        return $this->channelConnections[$channel->getName()] ?? [];
    }

    /**
     * Get connection by connection ID
     *
     * @param string $connectionId Connection ID
     * @return \Crustum\BlazeCast\WebSocket\Connection|null Connection or null if not found
     */
    public function getConnection(string $connectionId): ?Connection
    {
        foreach ($this->channelConnections as $connections) {
            if (isset($connections[$connectionId])) {
                return $connections[$connectionId];
            }
        }

        return null;
    }

    /**
     * Get channel names for connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return array<string>
     */
    public function getChannelNamesForConnection(Connection $connection): array
    {
        return array_keys($this->connectionChannels[$connection->getId()] ?? []);
    }

    /**
     * Get connection IDs for channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface $channel Channel
     * @return array<string>
     */
    public function getConnectionIdsForChannel(PusherChannelInterface $channel): array
    {
        return array_keys($this->channelConnections[$channel->getName()] ?? []);
    }

    /**
     * Check if connection is subscribed to channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface $channel Channel
     * @return bool
     */
    public function isSubscribed(Connection $connection, PusherChannelInterface $channel): bool
    {
        return isset($this->connectionChannels[$connection->getId()][$channel->getName()]);
    }

    /**
     * Get connection count for channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface $channel Channel
     * @return int
     */
    public function getConnectionCountForChannel(PusherChannelInterface $channel): int
    {
        return count($this->channelConnections[$channel->getName()] ?? []);
    }

    /**
     * Get channel count for connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return int
     */
    public function getChannelCountForConnection(Connection $connection): int
    {
        return count($this->connectionChannels[$connection->getId()] ?? []);
    }

    /**
     * Get all channel names with connections
     *
     * @return array<string>
     */
    public function getActiveChannelNames(): array
    {
        return array_keys($this->channelConnections);
    }

    /**
     * Get all connection IDs with subscriptions
     *
     * @return array<string>
     */
    public function getActiveConnectionIds(): array
    {
        return array_keys($this->connectionChannels);
    }

    /**
     * Get statistics
     *
     * @return ConnectionStats
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get detailed mapping information
     *
     * @return MappingInfo
     */
    public function getMappingInfo(): array
    {
        return [
            'connection_channels' => array_map(function ($channels) {
                return array_keys($channels);
            }, $this->connectionChannels),
            'channel_connections' => array_map(function ($connections) {
                return array_keys($connections);
            }, $this->channelConnections),
            'stats' => $this->getStats(),
        ];
    }

    /**
     * Clear all mappings
     *
     * @return void
     */
    public function clear(): void
    {
        $totalSubscriptions = $this->stats['total_subscriptions'];
        $totalConnections = $this->stats['total_connections'];

        $this->connectionChannels = [];
        $this->channelConnections = [];
        $this->appConnections = [];
        $this->appStats = [];
        $this->stats = [
            'total_connections' => 0,
            'total_subscriptions' => 0,
        ];

            BlazeCastLogger::info(__('ChannelConnectionManager: All channel-connection mappings cleared. Total connections: {0}, total subscriptions: {1}', $totalConnections, $totalSubscriptions), [
            'scope' => ['socket.manager', 'socket.manager.connection'],
            ]);
    }

    /**
     * Get connections for a specific app
     *
     * @param string $appId Application ID
     * @return array<string> Connection IDs
     */
    public function getConnectionsForApp(string $appId): array
    {
        return array_keys($this->appConnections[$appId] ?? []);
    }

    /**
     * Get subscriptions count for a specific app
     *
     * @param string $appId Application ID
     * @return int Subscription count
     */
    public function getSubscriptionsForApp(string $appId): int
    {
        return $this->appStats[$appId]['subscriptions'] ?? 0;
    }

    /**
     * Get HTTP requests count for a specific app
     *
     * @param string $appId Application ID
     * @return int HTTP requests count
     */
    public function getHttpRequestsForApp(string $appId): int
    {
        return $this->appStats[$appId]['http_requests'] ?? 0;
    }

    /**
     * Get all app statistics
     *
     * @return array<string, AppStats> App statistics
     */
    public function getAllAppStats(): array
    {
        return $this->appStats;
    }

    /**
     * Get app statistics for a specific app
     *
     * @param string $appId Application ID
     * @return AppStats App statistics
     */
    public function getAppStats(string $appId): array
    {
        return $this->appStats[$appId] ?? [
            'connections' => 0,
            'subscriptions' => 0,
            'http_requests' => 0,
        ];
    }

    /**
     * Get app ID from connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return string App ID
     */
    protected function getAppIdFromConnection(Connection $connection): string
    {
        return $connection->getAttribute('app_id') ?? 'default';
    }

    /**
     * Ensure app exists in tracking arrays
     *
     * @param string $appId Application ID
     * @return void
     */
    protected function ensureAppExists(string $appId): void
    {
        if (!isset($this->appConnections[$appId])) {
            $this->appConnections[$appId] = [];
        }

        if (!isset($this->appStats[$appId])) {
            $this->appStats[$appId] = [
                'connections' => 0,
                'subscriptions' => 0,
                'http_requests' => 0,
            ];
        }
    }

    /**
     * Record WebSocket message received
     *
     * @param string $appId Application ID
     * @param int $bytes Number of bytes
     * @return void
     */
    public function recordWsMessageReceived(string $appId, int $bytes): void
    {
        $this->ensureAppExists($appId);
        $this->appWsMessagesReceived[$appId] = ($this->appWsMessagesReceived[$appId] ?? 0) + 1;
        $this->appBytesReceived[$appId] = ($this->appBytesReceived[$appId] ?? 0) + $bytes;

            BlazeCastLogger::info('ChannelConnectionManager: WebSocket message received recorded', [
            'scope' => ['socket.manager', 'socket.manager.connection'],
            'app_id' => $appId,
            'bytes' => $bytes,
            'total_messages' => $this->appWsMessagesReceived[$appId],
            'total_bytes' => $this->appBytesReceived[$appId],
            ]);
    }

    /**
     * Record WebSocket message sent
     *
     * @param string $appId Application ID
     * @param int $bytes Number of bytes
     * @return void
     */
    public function recordWsMessageSent(string $appId, int $bytes): void
    {
        $this->ensureAppExists($appId);
        $this->appWsMessagesSent[$appId] = ($this->appWsMessagesSent[$appId] ?? 0) + 1;
        $this->appBytesTransmitted[$appId] = ($this->appBytesTransmitted[$appId] ?? 0) + $bytes;
    }

    /**
     * Record HTTP request
     *
     * @param string $appId Application ID
     * @param int $bytesReceived Number of bytes received
     * @param int $bytesTransmitted Number of bytes transmitted
     * @return void
     */
    public function recordHttpRequest(string $appId, int $bytesReceived = 0, int $bytesTransmitted = 0): void
    {
        $this->ensureAppExists($appId);
        $this->appStats[$appId]['http_requests'] = ($this->appStats[$appId]['http_requests'] ?? 0) + 1;
        $this->appHttpBytesReceived[$appId] = ($this->appHttpBytesReceived[$appId] ?? 0) + $bytesReceived;
        $this->appHttpBytesTransmitted[$appId] = ($this->appHttpBytesTransmitted[$appId] ?? 0) + $bytesTransmitted;
    }

    /**
     * Record new connection
     *
     * @param string $appId Application ID
     * @return void
     */
    public function recordNewConnection(string $appId): void
    {
        $this->ensureAppExists($appId);
        $this->appNewConnections[$appId] = ($this->appNewConnections[$appId] ?? 0) + 1;
    }

    /**
     * Record disconnection
     *
     * @param string $appId Application ID
     * @return void
     */
    public function recordDisconnection(string $appId): void
    {
        $this->ensureAppExists($appId);
        $this->appDisconnections[$appId] = ($this->appDisconnections[$appId] ?? 0) + 1;
    }

    /**
     * Get app detailed stats
     *
     * @param string $appId Application ID
     * @return array<string, mixed> App detailed stats
     */
    public function getAppDetailedStats(string $appId): array
    {
        $this->ensureAppExists($appId);

        return [
            'connections' => $this->appStats[$appId]['connections'],
            'subscriptions' => $this->appStats[$appId]['subscriptions'],
            'http_requests' => $this->appStats[$appId]['http_requests'],
            'ws_messages_received' => $this->appWsMessagesReceived[$appId] ?? 0,
            'ws_messages_sent' => $this->appWsMessagesSent[$appId] ?? 0,
            'bytes_received' => $this->appBytesReceived[$appId] ?? 0,
            'bytes_transmitted' => $this->appBytesTransmitted[$appId] ?? 0,
            'http_bytes_received' => $this->appHttpBytesReceived[$appId] ?? 0,
            'http_bytes_transmitted' => $this->appHttpBytesTransmitted[$appId] ?? 0,
            'new_connections' => $this->appNewConnections[$appId] ?? 0,
            'disconnections' => $this->appDisconnections[$appId] ?? 0,
        ];
    }

    /**
     * Get all detailed stats
     *
     * @return array<string, array<string, mixed>> All detailed stats
     */
    public function getAllDetailedStats(): array
    {
        $stats = [];
        foreach (array_keys($this->appStats) as $appId) {
            $stats[$appId] = $this->getAppDetailedStats($appId);
        }

        return $stats;
    }
}
