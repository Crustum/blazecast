<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Publish;

use Clue\React\Redis\Client;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use RuntimeException;

/**
 * Redis Publish Client
 *
 * Handles Redis publishing with event queuing during disconnection.
 *
 * @phpstan-import-type BroadcastPayload from \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel
 */
class RedisPublishClient extends RedisClient
{
    /**
     * The name of the Redis connection
     *
     * @var string
     */
    protected string $name = 'publisher';

    /**
     * Stream of events queued while disconnected from Redis
     *
     * @var array<BroadcastPayload>
     */
    protected array $queuedEvents = [];

    /**
     * Queue the given publish event
     *
     * @param BroadcastPayload $payload Event payload
     * @return void
     */
    protected function queueEvent(array $payload): void
    {
        $this->queuedEvents[] = $payload;
    }

    /**
     * Process the queued events
     *
     * @return void
     */
    protected function processQueuedEvents(): void
    {
        foreach ($this->queuedEvents as $event) {
            $this->publish($event);
        }

        $this->queuedEvents = [];
    }

    /**
     * Publish an event to the given channel
     *
     * @param BroadcastPayload $payload Event payload
     * @return \React\Promise\PromiseInterface<mixed>
     */
    public function publish(array $payload): PromiseInterface
    {
        if (!$this->isConnected()) {
            $this->queueEvent($payload);

            return new Promise(fn($resolve, $reject) => $reject(new RuntimeException('Redis not connected')));
        }

        /** @phpstan-ignore-next-line */
        return $this->client->publish($this->channel, json_encode($payload))->then(
            fn($result) => $result,
            fn($error) => new RuntimeException('Redis publish failed: ' . $error->getMessage()),
        );
    }

    /**
     * Handle a successful connection to the Redis server
     *
     * @param \Clue\React\Redis\Client $client Redis client
     * @return void
     */
    protected function onConnection(Client $client): void
    {
        parent::onConnection($client);

        $this->processQueuedEvents();
    }
}
