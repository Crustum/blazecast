<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel\Trait;

use Cake\Collection\Collection;
use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\Event\EventDispatcher;
use Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPubSubProvider;

/**
 * Presence Channels
 *
 * Provides member management for presence channels using CakePHP collections.
 *
 * @phpstan-type UserData array<string, mixed>
 * @phpstan-type Members array<string, UserData>
 * @phpstan-type PresenceStats array<string, mixed>
 */
trait PresenceChannelsTrait
{
    use PrivateChannelsTrait;

    /**
     * Channel members data
     *
     * @var Members
     */
    protected array $members = [];

    /**
     * Subscribe connection to presence channel with member data
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string|null $auth Authentication token
     * @param string|null $data Channel data (member info)
     * @return void
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        $this->verify($connection, $auth, $data);

        $userData = $data ? json_decode($data, true, 512, JSON_THROW_ON_ERROR) : [];
        $userId = isset($userData['user_id']) ? (string)$userData['user_id'] : null;

        if ($userId !== null && $userId !== '' && $this->userIsSubscribed($userId)) {
            parent::subscribe($connection, $auth, $data);
            $connection->setAttribute('user_id', $userId);

            $connection->send(json_encode([
                'event' => 'pusher_internal:subscription_succeeded',
                'channel' => $this->getName(),
                'data' => $this->data(),
            ]));

            return;
        }

        parent::subscribe($connection, $auth, $data);
        $this->addMember($connection, $userData);

        if ($userId === null || $userId === '' || !$this->hasEarlierConnection($this->userConnections($connection, $userId), $connection)) {
            $this->broadcastMemberAdded($userData, $connection);
        }

        $connection->send(json_encode([
            'event' => 'pusher_internal:subscription_succeeded',
            'channel' => $this->getName(),
            'data' => $this->data(),
        ]));
    }

    /**
     * Unsubscribe connection from presence channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return void
     */
    public function unsubscribe(Connection $connection): void
    {
        $userId = $connection->getAttribute('user_id');
        parent::unsubscribe($connection);

        if ($userId === null || $userId === '') {
            return;
        }

        $userId = (string)$userId;

        if ($this->userIsSubscribed($userId)) {
            return;
        }

        if ($this->userConnections($connection, $userId) !== []) {
            return;
        }

        Log::info("User removed from presence channel - Channel: {$this->getName()}, User: {$userId}");
        $this->removeMember($userId);
        $this->broadcastMemberRemoved($userId, $connection);
    }

    /**
     * Add member to presence channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param UserData $userData User data
     * @return void
     */
    protected function addMember(Connection $connection, array $userData): void
    {
        $userId = (string)($userData['user_id'] ?? '');
        if ($userId === '' || $userId === '0') {
            return;
        }

        $this->members[$userId] = $userData;
        $connection->setAttribute('user_id', $userId);
    }

    /**
     * Remove member from presence channel
     *
     * @param string $userId User ID
     * @return void
     */
    protected function removeMember(string $userId): void
    {
        unset($this->members[$userId]);
    }

    /**
     * Broadcast member_added event
     *
     * @param UserData $userData User data
     * @param \Crustum\BlazeCast\WebSocket\Connection $except Connection to exclude
     * @return void
     */
    protected function broadcastMemberAdded(array $userData, Connection $except): void
    {
        $payload = [
            'event' => 'pusher_internal:member_added',
            'data' => json_encode((object)$userData),
            'channel' => $this->getName(),
        ];

        if (!$this->dispatchMemberEvent($except, $payload)) {
            $this->broadcast($payload, $except);
        }
    }

    /**
     * Broadcast member_removed event
     *
     * @param string $userId User ID
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $except Connection to exclude
     * @return void
     */
    protected function broadcastMemberRemoved(string $userId, ?Connection $except = null): void
    {
        $payload = [
            'event' => 'pusher_internal:member_removed',
            'data' => json_encode(['user_id' => $userId]),
            'channel' => $this->getName(),
        ];

        if (!$this->dispatchMemberEvent($except, $payload)) {
            $this->broadcast($payload, $except);
        }
    }

    /**
     * Dispatch a member event through the event dispatcher so it fans out when scaling.
     *
     * Falls back to local broadcast when no application context or scaling provider is available.
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $except Connection to exclude
     * @param array{event: string, data: string|false, channel: string} $payload Broadcast payload
     * @return bool True when dispatched, false when the caller should broadcast locally
     */
    protected function dispatchMemberEvent(?Connection $except, array $payload): bool
    {
        if ($this->applicationManager === null || !EventDispatcher::getPubSubProvider() instanceof RedisPubSubProvider) {
            return false;
        }

        $appId = $this->resolveAppId($except);
        if ($appId === null) {
            return false;
        }

        EventDispatcher::dispatch(
            $this->applicationManager,
            $appId,
            $this->getName(),
            $payload['event'],
            (string)$payload['data'],
            $except,
        );

        return true;
    }

    /**
     * Resolve the application ID for a member event.
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $connection Connection
     * @return string|null Application ID or null when it cannot be resolved
     */
    protected function resolveAppId(?Connection $connection): ?string
    {
        $appId = $connection?->getAttribute('app_id');
        if (is_string($appId) && $appId !== '') {
            return $appId;
        }

        if ($this->applicationManager === null) {
            return null;
        }

        $applications = $this->applicationManager->getApplications();
        if (count($applications) === 1) {
            $single = array_first($applications);

            return $single['id'] !== '' ? $single['id'] : null;
        }

        return null;
    }

    /**
     * Get the given user's connections to the channel.
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Current connection
     * @param string|int $userId User ID
     * @return list<array{id: string, subscribed_at: float|null}> User connections with subscription times
     */
    protected function userConnections(Connection $connection, int|string $userId): array
    {
        $channelName = $this->getName();
        $wanted = (string)$userId;

        /** @var list<array{id: string, subscribed_at: float|null}> $userConnections */
        $userConnections = new Collection($this->getConnections())
            ->filter(fn(Connection $channelConnection): bool => (string)$channelConnection->getAttribute('user_id') === $wanted)
            ->map(function (Connection $channelConnection) use ($channelName): array {
                $subscribedAt = $channelConnection->getAttribute("subscribed_at_{$channelName}");

                return [
                    'id' => $channelConnection->getId(),
                    'subscribed_at' => is_numeric($subscribedAt) ? (float)$subscribedAt : null,
                ];
            })
            ->values()
            ->toArray();

        return $userConnections;
    }

    /**
     * Determine if the user has an earlier connection than the one given.
     *
     * Orders by subscription time, breaking ties by connection ID.
     *
     * @param list<array{id: string, subscribed_at: float|null}> $connections User connections
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Current connection
     * @return bool True when another connection subscribed earlier
     */
    protected function hasEarlierConnection(array $connections, Connection $connection): bool
    {
        $sorted = $connections;
        usort(
            $sorted,
            static fn(array $a, array $b): int => [$a['subscribed_at'] ?? 0.0, $a['id']] <=> [$b['subscribed_at'] ?? 0.0, $b['id']],
        );
        $earliest = $sorted[0] ?? null;

        return isset($earliest['id']) && $earliest['id'] !== $connection->getId();
    }

    /**
     * Get channel data for presence channel
     *
     * @return array<string, mixed> Presence data
     */
    public function data(): array
    {
        $userIds = [];
        foreach ($this->getConnections() as $connection) {
            $userId = $connection->getAttribute('user_id');
            if (is_string($userId) && $userId !== '') {
                $userIds[] = $userId;
            } elseif (is_int($userId) || is_float($userId)) {
                $userIds[] = (string)$userId;
            }
        }

        /** @var list<string> $uniqueMembers */
        $uniqueMembers = array_values(array_unique($userIds));

        if ($uniqueMembers === []) {
            return [
                'presence' => [
                    'count' => 0,
                    'ids' => [],
                    'hash' => [],
                ],
            ];
        }

        return [
            'presence' => [
                'count' => count($uniqueMembers),
                'ids' => $uniqueMembers,
                'hash' => new Collection($this->members)
                    ->map(function (array $member): array {
                        $userInfo = $member['user_info'] ?? null;

                        return array_merge($member, [
                            'user_info' => $userInfo !== null && $userInfo !== [] ? $userInfo : (object)[],
                        ]);
                    })
                    ->toArray(),
            ],
        ];
    }

    /**
     * Check if user is subscribed to channel
     *
     * @param string|null $userId User ID
     * @return bool
     */
    protected function userIsSubscribed(?string $userId): bool
    {
        if (!$userId) {
            return false;
        }

        $connections = new Collection($this->getConnections());

        return $connections
            ->map(fn($connection): string => (string)$connection->getAttribute('user_id'))
            ->contains($userId);
    }

    /**
     * Get channel type
     *
     * @return string
     */
    public function getType(): string
    {
        return 'presence';
    }

    /**
     * Get presence channel statistics
     *
     * @return PresenceStats
     */
    public function getPresenceStats(): array
    {
        return [
            'member_count' => count($this->members),
            'members' => array_values($this->members),
        ];
    }

    /**
     * Get members for presence channel
     *
     * @return array<UserData> Members data
     */
    public function getMembers(): array
    {
        return array_values($this->members);
    }

    /**
     * Get member count for presence channel
     *
     * @return int Member count
     */
    public function getMemberCount(): int
    {
        return count($this->members);
    }

    /**
     * Check if member exists in presence channel
     *
     * @param string $userId User ID
     * @return bool
     */
    public function hasMember(string $userId): bool
    {
        return isset($this->members[$userId]);
    }
}
