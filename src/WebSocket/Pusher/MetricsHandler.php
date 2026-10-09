<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher;

use Cake\Collection\Collection;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPresenceChannel;
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
 * @phpstan-type PresenceDataMetrics array<string, mixed>
 * @phpstan-type PresenceConnectionMetrics array{id: string, subscribed_at: float|null}
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
     * @param string $type Metrics type (channel, channels, channel_users, connections, presence_data, presence_connections)
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
            'presence_data' => $this->presenceData($application, $options),
            'presence_connections' => $this->presenceConnections($application, $options),
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
            ->map(fn(string $name): PusherChannelInterface => $this->channelManager->getChannel($name))
            ->filter(fn($channel): bool => $this->connectionManager->getConnectionsForChannel($channel) !== []);

        $filterPrefix = $options['filter'] ?? null;
        if ($filterPrefix) {
            $channels = $channels->filter(fn($channel): bool => str_starts_with((string)$channel->getName(), (string)$filterPrefix));
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
     * @return list<array{id: string}> Channel users
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

        /** @var list<array{id: string}> $users */
        $users = array_values((new Collection($uniqueUsers))
            ->map(fn(string $userId): array => ['id' => $userId])
            ->toArray());

        return $users;
    }

    /**
     * Get the presence data for the given channel.
     *
     * @param ApplicationConfig $application Application configuration
     * @param MetricsOptions $options Collection options
     * @return PresenceDataMetrics Presence data
     */
    public function presenceData(array $application, array $options): array
    {
        $channelName = $options['channel'] ?? null;
        if (!$channelName || !$this->isPresenceChannel((string)$channelName)) {
            return [];
        }

        if (!$this->channelManager->hasChannel((string)$channelName)) {
            return [];
        }

        $channel = $this->channelManager->getChannel((string)$channelName);
        if (!$channel instanceof PusherPresenceChannel) {
            return [];
        }

        return $channel->data();
    }

    /**
     * Get the given user's connections to the given presence channel.
     *
     * @param ApplicationConfig $application Application configuration
     * @param MetricsOptions $options Collection options
     * @return list<PresenceConnectionMetrics> User connections with subscription times
     */
    public function presenceConnections(array $application, array $options): array
    {
        $channelName = $options['channel'] ?? null;
        $userId = $options['user_id'] ?? null;
        if (!$channelName || $userId === null || $userId === '') {
            return [];
        }

        if (!$this->channelManager->hasChannel((string)$channelName)) {
            return [];
        }

        $channel = $this->channelManager->getChannel((string)$channelName);
        $connections = $this->connectionManager->getConnectionsForChannel($channel);

        /** @var list<PresenceConnectionMetrics> $userConnections */
        $userConnections = (new Collection($connections))
            ->filter(fn($connection): bool => (string)$connection->getAttribute('user_id') === (string)$userId)
            ->map(fn($connection): array => [
                'id' => $connection->getId(),
                'subscribed_at' => $connection->getAttribute("subscribed_at_{$channel->getName()}"),
            ])
            ->values()
            ->toArray();

        return $userConnections;
    }

    /**
     * Merge multiple sets of presence data into a single result set.
     *
     * @param list<PresenceDataMetrics> $metrics Presence data sets from each server
     * @return PresenceDataMetrics Merged presence data
     */
    public function mergePresenceData(array $metrics): array
    {
        /** @var list<string> $ids */
        $ids = array_values(array_unique(array_map(
            fn($id): string => (string)$id,
            (new Collection($metrics))->reduce(
                fn(array $carry, $item): array => array_merge($carry, array_values($item['presence']['ids'] ?? [])),
                [],
            ),
        )));

        /** @var array<string, mixed> $hash */
        $hash = (new Collection(
            (new Collection($metrics))->reduce(
                fn(array $carry, $item): array => $carry + ($item['presence']['hash'] ?? []),
                [],
            ),
        ))
            ->map(fn($info): mixed => $info !== null && $info !== [] ? $info : (object)[])
            ->toArray();

        return [
            'presence' => [
                'count' => count($ids),
                'ids' => $ids,
                'hash' => $hash,
            ],
        ];
    }

    /**
     * Merge presence connections gathered from all servers into a single list.
     *
     * @param list<list<PresenceConnectionMetrics>> $metrics Connection sets from each server
     * @return list<PresenceConnectionMetrics> Merged connections
     */
    public function mergePresenceConnections(array $metrics): array
    {
        /** @var list<PresenceConnectionMetrics> $merged */
        $merged = (new Collection($metrics))->reduce(
            fn(array $carry, $item): array => array_merge($carry, array_values($item)),
            [],
        );

        return $merged;
    }

    /**
     * Merge channel users gathered from all servers into a deduplicated list.
     *
     * @param list<list<array{id: mixed}>> $metrics User sets from each server
     * @return list<array{id: mixed}> Merged users
     */
    public function mergeChannelUsers(array $metrics): array
    {
        /** @var list<array{id: mixed}> $merged */
        $merged = (new Collection(
            (new Collection($metrics))->reduce(
                fn(array $carry, $item): array => array_merge($carry, array_values($item)),
                [],
            ),
        ))
            ->unique()
            ->values()
            ->toArray();

        return $merged;
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
