<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel\Trait;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;

/**
 * Cache Channels
 *
 * Trait for cache channel message caching logic.
 *
 * @phpstan-import-type BroadcastPayload from \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel
 * @phpstan-type CachedMessage array<string, mixed>
 * @phpstan-type CachedMessages array<CachedMessage>
 * @phpstan-type CacheStats array<string, mixed>
 */
trait CacheChannelsTrait
{
    /**
     * Cached messages for this channel
     *
     * @var CachedMessages
     */
    protected array $cachedMessages = [];

    /**
     * Maximum number of cached messages
     *
     * @var int
     */
    protected int $maxCachedMessages = 100;

    /**
     * Subscribe connection to cache channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string|null $auth Authentication token
     * @param string|null $data Channel data
     * @return void
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        parent::subscribe($connection, $auth, $data);

        $this->sendCachedMessages($connection);

        BlazeCastLogger::info(sprintf('CacheChannelsTrait: Connection %s subscribed to cache Pusher channel %s', $connection->getId(), $this->getName()), [
            'scope' => ['socket.channel', 'socket.channel.cache'],
        ]);
    }

    /**
     * Broadcast message to channel and cache it
     *
     * @param BroadcastPayload $payload Message payload
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $except Connection to exclude
     * @return void
     */
    public function broadcast(array $payload, ?Connection $except = null): void
    {
        $this->cacheMessage($payload);

        parent::broadcast($payload, $except);

        BlazeCastLogger::info(sprintf('CacheChannelsTrait: Message broadcasted and cached for channel %s', $this->getName()), [
            'scope' => ['socket.channel', 'socket.channel.cache'],
        ]);
    }

    /**
     * Cache a message for this channel
     *
     * @param BroadcastPayload $payload Message payload
     * @return void
     */
    protected function cacheMessage(array $payload): void
    {
        if (str_starts_with($payload['event'], 'pusher_internal:')) {
            return;
        }

        $cachedMessage = $payload;
        $cachedMessage['cached_at'] = time();

        $this->cachedMessages[] = $cachedMessage;

        if (count($this->cachedMessages) > $this->maxCachedMessages) {
            array_shift($this->cachedMessages);
        }
    }

    /**
     * Send cached messages to a connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return void
     */
    protected function sendCachedMessages(Connection $connection): void
    {
        foreach ($this->cachedMessages as $message) {
            $sendMessage = $message;
            unset($sendMessage['cached_at']);

            $connection->send(json_encode($sendMessage));
        }

        if (count($this->cachedMessages) > 0) {
            BlazeCastLogger::info(__('CacheChannelsTrait: Sent cached messages to new subscriber {0} for channel {1}', $connection->getId(), $this->getName()), [
                'scope' => ['socket.channel', 'socket.channel.cache'],
            ]);
        }
    }

    /**
     * Get cached messages
     *
     * @return CachedMessages Cached messages
     */
    public function getCachedMessages(): array
    {
        return $this->cachedMessages;
    }

    /**
     * Get cached message count
     *
     * @return int Number of cached messages
     */
    public function getCachedMessageCount(): int
    {
        return count($this->cachedMessages);
    }

    /**
     * Clear cached messages
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cachedMessages = [];

        BlazeCastLogger::info('Cache cleared for channel', [
            'scope' => ['socket.channel', 'socket.channel.cache'],
            'channel' => $this->getName(),
        ]);
    }

    /**
     * Set maximum cached messages
     *
     * @param int $max Maximum number of messages to cache
     * @return void
     */
    public function setMaxCachedMessages(int $max): void
    {
        $this->maxCachedMessages = max(1, $max);

        if (count($this->cachedMessages) > $this->maxCachedMessages) {
            $this->cachedMessages = array_slice($this->cachedMessages, -$this->maxCachedMessages);
        }
    }

    /**
     * Get maximum cached messages
     *
     * @return int Maximum number of messages to cache
     */
    public function getMaxCachedMessages(): int
    {
        return $this->maxCachedMessages;
    }

    /**
     * Get channel type
     *
     * @return string
     */
    public function getType(): string
    {
        return 'cache';
    }

    /**
     * Get cache channel statistics
     *
     * @return CacheStats
     */
    public function getCacheStats(): array
    {
        return [
            'cached_messages' => $this->getCachedMessageCount(),
            'max_cached_messages' => $this->getMaxCachedMessages(),
            'oldest_message_age' => $this->getOldestMessageAge(),
        ];
    }

    /**
     * Get age of oldest cached message in seconds
     *
     * @return int|null Age in seconds or null if no messages
     */
    protected function getOldestMessageAge(): ?int
    {
        if (empty($this->cachedMessages)) {
            return null;
        }

        $oldestMessage = reset($this->cachedMessages);

        return time() - ($oldestMessage['cached_at'] ?? time());
    }
}
