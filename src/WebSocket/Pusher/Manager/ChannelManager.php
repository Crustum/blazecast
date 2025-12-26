<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Manager;

use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherCacheChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPresenceChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPrivateChannel;

/**
 * Channel Manager
 *
 * Manages Pusher channels creation, storage, and retrieval.
 *
 * @phpstan-type ChannelStats array{
 *   total_channels: int,
 *   public_channels: int,
 *   private_channels: int,
 *   presence_channels: int,
 *   cache_channels: int
 * }
 * @phpstan-type ChannelInfo array{
 *   name: string,
 *   type: string,
 *   connection_count: int,
 *   stats: array<string, mixed>
 * }
 * @phpstan-type ChannelsInfo array<string, ChannelInfo>|ChannelInfo
 * @phpstan-type ChannelMetadata array<string, mixed>
 */
class ChannelManager
{
    /**
     * Channel storage
     *
     * @var array<string, \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface>
     */
    protected array $channels = [];

    /**
     * Channel statistics
     *
     * @var ChannelStats
     */
    protected array $stats = [
        'total_channels' => 0,
        'public_channels' => 0,
        'private_channels' => 0,
        'presence_channels' => 0,
        'cache_channels' => 0,
    ];

    /**
     * Get or create channel by name
     *
     * @param string $channelName Channel name
     * @param ChannelMetadata $metadata Channel metadata
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface
     */
    public function getChannel(string $channelName, array $metadata = []): PusherChannelInterface
    {
        if (isset($this->channels[$channelName])) {
            return $this->channels[$channelName];
        }

        return $this->createChannel($channelName, $metadata);
    }

    /**
     * Create new channel by name and type
     *
     * @param string $channelName Channel name
     * @param ChannelMetadata $metadata Channel metadata
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface
     */
    protected function createChannel(string $channelName, array $metadata = []): PusherChannelInterface
    {
        $channel = $this->instantiateChannel($channelName, $metadata);

        $this->channels[$channelName] = $channel;
        $this->updateStats($channel, 'add');

        BlazeCastLogger::info(sprintf('Channel created. channel=%s, type=%s, total_channels=%d', $channelName, $channel->getType(), $this->stats['total_channels']), [
            'scope' => ['socket.manager', 'socket.manager.channel'],
        ]);

        return $channel;
    }

    /**
     * Instantiate appropriate channel type based on name
     *
     * @param string $channelName Channel name
     * @param ChannelMetadata $metadata Channel metadata
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface
     */
    protected function instantiateChannel(string $channelName, array $metadata = []): PusherChannelInterface
    {
        if (str_starts_with($channelName, 'presence-')) {
            return new PusherPresenceChannel($channelName, $metadata);
        }

        if (str_starts_with($channelName, 'private-')) {
            return new PusherPrivateChannel($channelName, $metadata);
        }

        if (str_starts_with($channelName, 'cache-')) {
            return new PusherCacheChannel($channelName, $metadata);
        }

        return new PusherChannel($channelName, $metadata);
    }

    /**
     * Remove channel if it has no connections
     *
     * @param string $channelName Channel name
     * @return bool True if channel was removed
     */
    public function removeChannelIfEmpty(string $channelName): bool
    {
        if (!isset($this->channels[$channelName])) {
            return false;
        }

        $channel = $this->channels[$channelName];

        if ($channel->getConnectionCount() === 0) {
            $this->updateStats($channel, 'remove');
            unset($this->channels[$channelName]);

            BlazeCastLogger::info('Empty channel removed', [
                'scope' => ['socket.manager', 'socket.manager.channel'],
                'channel' => $channelName,
                'type' => $channel->getType(),
                'total_channels' => $this->stats['total_channels'],
            ]);

            return true;
        }

        return false;
    }

    /**
     * Get all channels
     *
     * @return array<string, \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Get channels by type
     *
     * @param string $type Channel type (public, private, presence, cache)
     * @return array<string, \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface>
     */
    public function getChannelsByType(string $type): array
    {
        return array_filter($this->channels, function (PusherChannelInterface $channel) use ($type) {
            return $channel->getType() === $type;
        });
    }

    /**
     * Get channel names by pattern
     *
     * @param string $pattern Pattern to match (supports wildcards)
     * @return array<string>
     */
    public function getChannelNamesByPattern(string $pattern): array
    {
        $escaped = preg_quote($pattern, '/');
        $regex = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], $escaped) . '$/';

        return array_keys(array_filter($this->channels, function ($channel, $name) use ($regex) {
            return preg_match($regex, $name);
        }, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Check if channel exists
     *
     * @param string $channelName Channel name
     * @return bool
     */
    public function hasChannel(string $channelName): bool
    {
        return isset($this->channels[$channelName]);
    }

    /**
     * Get channel count
     *
     * @return int
     */
    public function getChannelCount(): int
    {
        return count($this->channels);
    }

    /**
     * Get channel statistics
     *
     * @return ChannelStats
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get detailed channel information
     *
     * @param string|null $channelName Specific channel name or null for all
     * @return ChannelsInfo
     */
    public function getChannelInfo(?string $channelName = null): array
    {
        if ($channelName !== null) {
            if (!isset($this->channels[$channelName])) {
                return [];
            }

            $channel = $this->channels[$channelName];

            return [
                'name' => $channelName,
                'type' => $channel->getType(),
                'connection_count' => $channel->getConnectionCount(),
                'stats' => $channel->getStats(),
            ];
        }

        $info = [];
        foreach ($this->channels as $name => $channel) {
            $info[$name] = [
                'name' => $name,
                'type' => $channel->getType(),
                'connection_count' => $channel->getConnectionCount(),
                'stats' => $channel->getStats(),
            ];
        }

        return $info;
    }

    /**
     * Clear all channels
     *
     * @return void
     */
    public function clear(): void
    {
        $channelCount = count($this->channels);
        $this->channels = [];
        $this->stats = [
            'total_channels' => 0,
            'public_channels' => 0,
            'private_channels' => 0,
            'presence_channels' => 0,
            'cache_channels' => 0,
        ];

        BlazeCastLogger::info('All channels cleared', [
            'scope' => ['socket.manager', 'socket.manager.channel'],
            'cleared_channels' => $channelCount,
        ]);
    }

    /**
     * Update channel statistics
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface $channel Channel
     * @param string $operation Operation (add or remove)
     * @return void
     */
    protected function updateStats(PusherChannelInterface $channel, string $operation): void
    {
        $delta = $operation === 'add' ? 1 : -1;

        $this->stats['total_channels'] += $delta;

        $type = $channel->getType();
        $statKey = $type . '_channels';

        if (isset($this->stats[$statKey])) {
            $this->stats[$statKey] += $delta;
        }

        foreach ($this->stats as $key => $value) {
            if ($value < 0) {
                $this->stats[$key] = 0;
            }
        }
    }
}
