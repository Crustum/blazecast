<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel\Trait;

use Cake\Collection\Collection;
use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\Connection;

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
        $userId = $userData['user_id'] ?? null;

        if (!$this->userIsSubscribed($userData['user_id'] ?? null)) {
            parent::subscribe($connection, $auth, $data);
            $this->addMember($connection, $userData);
            $this->broadcastMemberAdded($userData, $connection);
        } else {
            parent::subscribe($connection, $auth, $data);
            if ($userId) {
                $connection->setAttribute('user_id', $userId);
            }
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

        if ($userId && !$this->userIsSubscribed($userId)) {
            Log::info("User removed from presence channel - Channel: {$this->getName()}, User: {$userId}");
            $this->removeMember($userId);
            $this->broadcastMemberRemoved($userId);
        }
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
        if (!$userId) {
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
        $this->broadcast([
            'event' => 'pusher_internal:member_added',
            'data' => json_encode((object)$userData),
            'channel' => $this->getName(),
        ], $except);
    }

    /**
     * Broadcast member_removed event
     *
     * @param string $userId User ID
     * @return void
     */
    protected function broadcastMemberRemoved(string $userId): void
    {
        $this->broadcast([
            'event' => 'pusher_internal:member_removed',
            'data' => json_encode(['user_id' => $userId]),
            'channel' => $this->getName(),
        ]);
    }

    /**
     * Get channel data for presence channel
     *
     * @return array<string, mixed> Presence data
     */
    public function data(): array
    {
        $connections = new Collection($this->getConnections());
        $uniqueMembers = $connections
            ->map(fn($connection) => $connection->getAttribute('user_id'))
            ->filter()
            ->toList();

        $uniqueMembers = array_unique($uniqueMembers);

        if (empty($uniqueMembers)) {
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
                'ids' => array_values($uniqueMembers),
                'hash' => $this->members,
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
            ->map(fn($connection) => (string)$connection->getAttribute('user_id'))
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
