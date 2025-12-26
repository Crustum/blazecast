<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;

/**
 * SerializationTrait
 *
 * Provides methods for serializing channel data
 *
 * @phpstan-ignore-next-line Unused trait - kept for future use
 */
trait SerializationTrait
{
    /**
     * Get users from a presence channel
     *
     * @param object $channel Channel object
     * @return array
     */
    protected function getChannelUsers(object $channel): array
    {
        if (!method_exists($channel, 'getConnections')) {
            return [];
        }

        $users = [];
        $connections = $channel->getConnections();

        foreach ($connections as $connection) {
            if (!$connection->hasAttribute('user_data')) {
                continue;
            }

            $userData = $connection->getAttribute('user_data');
            if (!isset($userData['user_id'])) {
                continue;
            }

            $userId = (string)$userData['user_id'];
            $userInfo = $userData['user_info'] ?? [];

            $users[$userId] = [
                'id' => $userId,
                'info' => $userInfo,
            ];
        }

        return array_values($users);
    }

    /**
     * Format channel data for API response
     *
     * @param object $channel Channel object
     * @return array
     */
    protected function formatChannelData(object $channel): array
    {
        $name = method_exists($channel, 'getName') ? $channel->getName() : '';
        $connectionCount = method_exists($channel, 'getConnectionCount') ? $channel->getConnectionCount() : 0;

        $data = [
            'name' => $name,
            'occupied' => $connectionCount > 0,
        ];

        if (strpos($name, 'presence-') === 0) {
            $users = $this->getChannelUsers($channel);
            $data['user_count'] = count($users);
        }

        return $data;
    }

    /**
     * Prepare channel for serialization
     *
     * @return array Serialized channel data
     */
    public function __serialize(): array
    {
        BlazeCastLogger::info(sprintf('Serializing channel. channel=%s', $this->name), [
            'scope' => ['socket.channel', 'socket.channel.serialization'],
        ]);

        return [
            'name' => $this->name,
        ];
    }

    /**
     * Restore channel after serialization
     *
     * @param array $values Serialized channel data
     * @return void
     */
    public function __unserialize(array $values): void
    {
        $this->name = $values['name'];

        if (isset($this->connectionManager)) {
            $this->connections = $this->connectionManager->getConnections($this->name);
        // } elseif (class_exists(ChannelConnectionManager::class)) {
            // $connectionManager = new ChannelConnectionManager();
            // $this->connections = $connectionManager->getConnections($this->name);
        } else {
            $this->connections = [];
        }

        BlazeCastLogger::info('Unserialized channel', [
            'scope' => ['socket.channel', 'socket.channel.serialization'],
            'channel' => $this->name,
            'connection_count' => count($this->connections),
        ]);
    }
}
