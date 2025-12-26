<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Publish;

use Cake\Log\Log;

/**
 * Redis subscribe client
 */
class RedisSubscribeClient extends RedisClient
{
    /**
     * The name of the Redis connection
     *
     * @var string
     */
    protected string $name = 'subscriber';

    /**
     * Subscribe to the given Redis channel
     *
     * @return void
     */
    public function subscribe(): void
    {
        if ($this->isConnected()) {
            /** @phpstan-ignore-next-line */
            $this->client->subscribe($this->channel)->then(
                fn($result) => Log::info("Subscribed to channel: {$this->channel}"),
                fn($error) => Log::error("Failed to subscribe to channel {$this->channel}: " . $error->getMessage()),
            );
        } else {
            Log::error('RedisSubscribeClient is not connected to Redis');
        }
    }
}
