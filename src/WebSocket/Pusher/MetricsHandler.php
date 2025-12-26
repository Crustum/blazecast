<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher;

use Cake\Collection\Collection;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\Pusher\Trait\ChannelInformationTrait;

/**
 * Metrics Handler
 *
 * Centralized metrics collection and processing for Pusher protocol.
 *
 * @phpstan-import-type ApplicationConfig from \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
 * @phpstan-type MetricsOptions array<string, mixed>
 * @phpstan-type ChannelMetrics array<string, mixed>
 * @phpstan-type ChannelsMetrics array<string, ChannelMetrics>
 * @phpstan-type ChannelUsersMetrics array<string, mixed>
 * @phpstan-type ConnectionMetrics array<string, mixed>
 * @phpstan-type DebugMetrics array<string, mixed>
 */
class MetricsHandler
{
    use ChannelInformationTrait;

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager $channelManager Channel manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager $connectionManager Connection manager
     */
    public function __construct(
        protected ApplicationManager $applicationManager,
        protected ChannelManager $channelManager,
        protected ChannelConnectionManager $connectionManager,
    ) {
    }

    /**
     * Gather metrics for the given type and options
     *
     * This is the main entry point used by controllers.
     * Currently synchronous but designed to support async in the future.
     *
     * @param ApplicationConfig $application Application configuration
     * @param string $type Metrics type (channel, channels, channel_users, connections)
     * @param MetricsOptions $options Options for metric collection
     * @return array<string, mixed> Metrics data
     */
    public function gather(array $application, string $type, array $options = []): array
    {
        BlazeCastLogger::debug(__('MetricsHandler: Gathering metrics for application {0} with type {1} and options {2}', $application['id'], $type, json_encode($options)), [
            'scope' => ['socket.metrics'],
        ]);

        $metrics = $this->get($application, $type, $options);

        BlazeCastLogger::debug(__('MetricsHandler: {0} Metrics gathered successfully for application {1} with type {2} and options {3}', count($metrics), $application['id'], $type, json_encode($options)), [
            'scope' => ['socket.metrics'],
        ]);

        return $metrics;
    }

    /**
     * Get metrics for the given type
     *
     * @param ApplicationConfig $application Application configuration
     * @param string $type Metrics type
     * @param MetricsOptions $options Collection options
     * @return array<string, mixed> Metrics data
     */
    public function get(array $application, string $type, array $options = []): array
    {
        return match ($type) {
            'channel' => $this->channel($application, $options),
            'channels' => $this->channels($application, $options),
            'channel_users' => $this->channelUsers($application, $options),
            'connections' => $this->connections($application),
            default => [],
        };
    }

    /**
     * Get channel metrics for a single channel
     * Delegates to ChannelInformation trait
     *
     * @param ApplicationConfig $application Application configuration
     * @param MetricsOptions $options Collection options
     * @return ChannelMetrics Channel metrics
     */
    protected function channel(array $application, array $options): array
    {
        $channelName = $options['channel'] ?? null;
        if (!$channelName) {
            return [];
        }

        return $this->info($application, $channelName, $options['info'] ?? '');
    }

    /**
     * Get metrics for multiple channels
     * Delegates to ChannelInformation trait with CakePHP Collections
     *
     * @param ApplicationConfig $application Application configuration
     * @param MetricsOptions|array<array-key, mixed> $options Collection options
     * @return ChannelsMetrics Channels metrics
     */
    protected function channels(array $application, array $options): array
    {
        if (isset($options['channels'])) {
            return $this->infoForChannels($application, $options['channels'], $options['info'] ?? '');
        }

        $channelNames = $this->connectionManager->getActiveChannelNames();
        $channels = (new Collection($channelNames))
            ->map(fn($name) => $this->channelManager->getChannel($name))
            ->filter(fn($channel) => count($this->connectionManager->getConnectionsForChannel($channel)) > 0);

        $filterPrefix = $options['filter'] ?? null;
        if ($filterPrefix) {
            $channels = $channels->filter(fn($channel) => str_starts_with($channel->getName(), $filterPrefix));
        }

        return $this->infoForChannels(
            $application,
            $channels->toArray(),
            $options['info'] ?? '',
        );
    }

    /**
     * Get users for a channel (presence channels only)
     * Uses trait methods for consistency
     *
     * @param ApplicationConfig $application Application configuration
     * @param MetricsOptions $options Collection options
     * @return ChannelUsersMetrics Channel users
     */
    protected function channelUsers(array $application, array $options): array
    {
        $channelName = $options['channel'] ?? null;
        if (!$channelName || !$this->isPresenceChannel($channelName)) {
            return [];
        }

        $channel = $this->channelManager->getChannel($channelName);

        $connections = $this->connectionManager->getConnectionsForChannel($channel);
        $uniqueUsers = $this->extractUniqueUsers($connections);

        return (new Collection($uniqueUsers))
            ->map(fn($userId) => ['id' => $userId])
            ->toArray();
    }

    /**
     * Get connections for the application
     *
     * @param ApplicationConfig $application Application configuration
     * @return ConnectionMetrics Connection IDs
     */
    protected function connections(array $application): array
    {
        $appId = $application['id'];

        return $this->connectionManager->getConnectionsForApp($appId);
    }

    /**
     * Get comprehensive debug metrics
     *
     * @param ApplicationConfig $application Application configuration
     * @return DebugMetrics Debug metrics
     */
    public function getDebugMetrics(array $application): array
    {
        $appId = $application['id'];
        $activeChannelNames = $this->connectionManager->getActiveChannelNames();
        $activeConnectionIds = $this->connectionManager->getConnectionsForApp($appId);
        $channelStats = $this->channelManager->getStats();
        $connectionStats = $this->connectionManager->getStats();
        $appStats = $this->connectionManager->getAppStats($appId);

        return [
            'timestamp' => time(),
            'application' => [
                'id' => $application['id'],
                'name' => $application['name'] ?? 'unknown',
            ],
            'channels' => [
                'active_count' => count($activeChannelNames),
                'active_names' => $activeChannelNames,
                'stats' => $channelStats,
            ],
            'connections' => [
                'active_count' => count($activeConnectionIds),
                'active_ids' => $activeConnectionIds,
                'stats' => $connectionStats,
                'app_stats' => $appStats,
            ],
            'mapping_info' => $this->connectionManager->getMappingInfo(),
        ];
    }
}
