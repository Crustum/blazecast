<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use InvalidArgumentException;

/**
 * Pusher Channel Factory
 *
 * Factory for creating Pusher channels based on channel name patterns.
 *
 * @phpstan-type ChannelFactoryConfig array{
 *   cache_max_messages?: int,
 *   enable_client_events?: bool,
 *   log_channel_creation?: bool
 * }
 *
 * @phpstan-type ChannelFactoryStats array{
 *   channels_created: int,
 *   config: ChannelFactoryConfig
 * }
 */
class PusherChannelFactory
{
    /**
     * Application manager for private channel authentication
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager|null
     */
    protected ?ApplicationManager $applicationManager = null;

    /**
     * Channel configuration
     *
     * @var ChannelFactoryConfig
     */
    protected array $config = [
        'cache_max_messages' => 100,
        'enable_client_events' => true,
        'log_channel_creation' => true,
    ];

    /**
     * Number of channels created
     *
     * @var int
     */
    protected int $channelsCreated = 0;

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager|null $applicationManager Application manager
     * @param ChannelFactoryConfig $config Channel configuration
     */
    public function __construct(?ApplicationManager $applicationManager = null, array $config = [])
    {
        $this->applicationManager = $applicationManager;
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Create channel by name
     *
     * @param string $channelName Channel name
     * @param array<string, mixed> $metadata Channel metadata
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface
     */
    public function create(string $channelName, array $metadata = []): PusherChannelInterface
    {
        $channel = $this->instantiateChannel($channelName, $metadata);

        $this->configureChannel($channel);

        $this->channelsCreated++;

        if ($this->config['log_channel_creation']) {
            $hasAppManager = $this->applicationManager !== null ? 'true' : 'false';
            BlazeCastLogger::info(sprintf('Pusher channel created via factory. channel=%s, type=%s, has_app_manager=%s', $channelName, $channel->getType(), $hasAppManager), [
                'scope' => ['socket.channel', 'socket.channel.factory'],
            ]);
        }

        return $channel;
    }

    /**
     * Instantiate appropriate channel type based on name
     *
     * @param string $channelName Channel name
     * @param array<string, mixed> $metadata Channel metadata
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
     * Configure channel based on its type and settings
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface $channel Channel
     * @return void
     */
    protected function configureChannel(PusherChannelInterface $channel): void
    {
        if ($channel instanceof PusherPrivateChannel && $this->applicationManager) {
            $channel->setApplicationManager($this->applicationManager);
        }

        if ($channel instanceof PusherPresenceChannel && $this->applicationManager) {
            $channel->setApplicationManager($this->applicationManager);
        }

        if ($channel instanceof PusherCacheChannel) {
            $channel->setMaxCachedMessages($this->config['cache_max_messages']);
        }
    }

    /**
     * Create multiple channels from array of names
     *
     * @param array<string|array{name: string, metadata?: array<string, mixed>}> $channelNames Array of channel names or configs
     * @param array<string, mixed> $defaultMetadata Default metadata for all channels
     * @return array<string, \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface>
     */
    public function createMultiple(array $channelNames, array $defaultMetadata = []): array
    {
        $channels = [];

        foreach ($channelNames as $channelName) {
            $metadata = $defaultMetadata;

            if (is_array($channelName)) {
                $metadata = array_merge($defaultMetadata, $channelName['metadata'] ?? []);
                $channelName = $channelName['name'];
            }

            $channels[$channelName] = $this->create($channelName, $metadata);
        }

        return $channels;
    }

    /**
     * Determine channel type from name
     *
     * @param string $channelName Channel name
     * @return string Channel type
     */
    public function getChannelType(string $channelName): string
    {
        if (str_starts_with($channelName, 'presence-')) {
            return 'presence';
        }

        if (str_starts_with($channelName, 'private-')) {
            return 'private';
        }

        if (str_starts_with($channelName, 'cache-')) {
            return 'cache';
        }

        return 'public';
    }

    /**
     * Validate channel name format
     *
     * @param string $channelName Channel name
     * @return bool
     */
    public function isValidChannelName(string $channelName): bool
    {
        if (empty($channelName) || strlen($channelName) > 200) {
            return false;
        }

        if (preg_match('/[^a-zA-Z0-9_\-=@,.;]/', $channelName)) {
            return false;
        }

        $validPrefixes = ['presence-', 'private-', 'cache-'];
        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($channelName, $prefix)) {
                return strlen($channelName) > strlen($prefix);
            }
        }

        return !str_starts_with($channelName, 'pusher:');
    }

    /**
     * Get supported channel types
     *
     * @return array<string>
     */
    public function getSupportedTypes(): array
    {
        return ['public', 'private', 'presence', 'cache'];
    }

    /**
     * Set application manager
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @return void
     */
    public function setApplicationManager(ApplicationManager $applicationManager): void
    {
        $this->applicationManager = $applicationManager;
    }

    /**
     * Get application manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager|null
     */
    public function getApplicationManager(): ?ApplicationManager
    {
        return $this->applicationManager;
    }

    /**
     * Update configuration
     *
     * @param ChannelFactoryConfig $config Configuration updates
     * @return void
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get current configuration
     *
     * @return ChannelFactoryConfig
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Create channel from array data
     *
     * @param array<string, mixed> $data Channel data with name, type, metadata
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface
     * @throws \InvalidArgumentException If data is invalid
     */
    public function fromArray(array $data): PusherChannelInterface
    {
        if (!isset($data['name'])) {
            throw new InvalidArgumentException('Channel name is required');
        }

        $channelName = $data['name'];
        $metadata = $data['metadata'] ?? [];

        if (!$this->isValidChannelName($channelName)) {
            throw new InvalidArgumentException("Invalid channel name: {$channelName}");
        }

        return $this->create($channelName, $metadata);
    }

    /**
     * Get factory statistics
     *
     * @return ChannelFactoryStats
     */
    public function getStats(): array
    {
        return [
            'channels_created' => $this->channelsCreated,
            'has_application_manager' => $this->applicationManager !== null,
            'supported_types' => $this->getSupportedTypes(),
            'config' => $this->config,
        ];
    }
}
