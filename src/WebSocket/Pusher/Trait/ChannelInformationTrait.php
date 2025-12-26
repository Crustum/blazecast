<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Trait;

use Cake\Collection\Collection;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;

/**
 * Channel Information Trait
 *
 * Provides methods for gathering and formatting channel information
 * using CakePHP Collections for efficient data processing.
 *
 * @phpstan-import-type ApplicationConfig from \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
 * @phpstan-type ChannelInfo array<string, mixed>
 * @phpstan-type ChannelConnections array<string, \Crustum\BlazeCast\WebSocket\Connection>
 * @phpstan-type InfoFields array<string>
 * @phpstan-type UniqueUsers array<string>
 */
trait ChannelInformationTrait
{
    /**
     * Get information for a single channel
     *
     * @param ApplicationConfig $application Application configuration
     * @param string $channelName Channel name
     * @param string $info Comma-separated list of info fields to include
     * @return ChannelInfo Channel information
     */
    protected function info(array $application, string $channelName, string $info = ''): array
    {
        $infoFields = $this->parseInfoFields($info);

        /** @phpstan-ignore-next-line */
        if (!$this->channelManager || !$this->connectionManager) {
            return $this->buildUnoccupiedInfo($infoFields);
        }

        $channel = $this->channelManager->getChannel($channelName);
        $connections = $this->connectionManager->getConnectionsForChannel($channel);

        return empty($connections)
            ? $this->buildUnoccupiedInfo($infoFields)
            : $this->buildOccupiedInfo($channelName, $connections, $infoFields);
    }

    /**
     * Get information for multiple channels
     *
     * @param ApplicationConfig $application Application configuration
     * @param array<string|\Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface> $channels List of channels (names or objects)
     * @param string $info Comma-separated list of info fields to include
     * @return array<string, ChannelInfo> Channel information keyed by channel name
     */
    protected function infoForChannels(array $application, array $channels, string $info = ''): array
    {
        return (new Collection($channels))
            ->map(fn($channel) => $channel instanceof PusherChannelInterface ? $channel->getName() : (string)$channel)
            ->combine(
                fn($channelName) => $channelName,
                fn($channelName) => $this->info($application, $channelName, $info),
            )
            ->toArray();
    }

    /**
     * Build occupied channel information
     *
     * @param string $channelName Channel name
     * @param ChannelConnections $connections Channel connections
     * @param InfoFields $infoFields Fields to include in information
     * @return ChannelInfo Channel information
     */
    protected function buildOccupiedInfo(string $channelName, array $connections, array $infoFields): array
    {
        $connectionCount = count($connections);
        $info = ['occupied' => true];

        if (in_array('subscription_count', $infoFields)) {
            $info['subscription_count'] = $connectionCount;
        }

        if (in_array('user_count', $infoFields) || in_array('member_count', $infoFields)) {
            $userCount = $this->isPresenceChannel($channelName)
                ? count($this->extractUniqueUsers($connections))
                : $connectionCount;

            if (in_array('user_count', $infoFields)) {
                $info['user_count'] = $userCount;
            }

            if (in_array('member_count', $infoFields) && $this->isPresenceChannel($channelName)) {
                $info['member_count'] = $userCount;
            }
        }

        return $info;
    }

    /**
     * Build unoccupied channel information
     *
     * @param InfoFields $infoFields Fields to include in information
     * @return ChannelInfo Channel information
     */
    protected function buildUnoccupiedInfo(array $infoFields): array
    {
        $info = ['occupied' => false];

        foreach (['user_count', 'subscription_count', 'member_count'] as $field) {
            if (in_array($field, $infoFields)) {
                $info[$field] = 0;
            }
        }

        return $info;
    }

    /**
     * Extract unique user IDs from connections
     *
     * @param ChannelConnections $connections Channel connections
     * @return UniqueUsers Array of unique user IDs
     */
    protected function extractUniqueUsers(array $connections): array
    {
        return (new Collection($connections))
            ->map(fn($connection) => $this->extractUserId($connection))
            ->filter(fn($userId) => $userId !== null)
            ->unique()
            ->toList();
    }

    /**
     * Extract user ID from connection
     *
     * @param mixed $connection Connection object
     * @return string|null User ID or null if not found
     */
    protected function extractUserId(mixed $connection): ?string
    {
        if (is_object($connection) && method_exists($connection, 'getAttribute')) {
            return $connection->getAttribute('user_id') ?? $connection->getAttribute('userId');
        }

        if (is_object($connection) && method_exists($connection, 'getAttributes')) {
            $attributes = $connection->getAttributes();

            return $attributes['user_id'] ?? $attributes['userId'] ?? null;
        }

        if (is_array($connection)) {
            return $connection['user_id'] ?? $connection['userId'] ?? null;
        }

        if (is_object($connection)) {
            return $connection->user_id ?? $connection->userId ?? null;
        }

        return null;
    }

    /**
     * Parse info parameter into valid fields
     *
     * @param string $info Comma-separated info fields
     * @return InfoFields Valid info fields
     */
    protected function parseInfoFields(string $info): array
    {
        if (!$info) {
            return [];
        }

        $allowedFields = ['user_count', 'subscription_count', 'member_count', 'occupied'];
        $requestedFields = array_map('trim', explode(',', $info));

        return array_intersect($requestedFields, $allowedFields);
    }

    /**
     * Check if channel is a presence channel
     *
     * @param string $channelName Channel name
     * @return bool True if presence channel
     */
    protected function isPresenceChannel(string $channelName): bool
    {
        return str_starts_with($channelName, 'presence-');
    }

    /**
     * Check if channel is a private channel
     *
     * @param string $channelName Channel name
     * @return bool True if private channel
     */
    protected function isPrivateChannel(string $channelName): bool
    {
        return str_starts_with($channelName, 'private-');
    }

    /**
     * Check if channel is a cache channel
     *
     * @param string $channelName Channel name
     * @return bool True if cache channel
     */
    protected function isCacheChannel(string $channelName): bool
    {
        return str_starts_with($channelName, 'cache-');
    }
}
