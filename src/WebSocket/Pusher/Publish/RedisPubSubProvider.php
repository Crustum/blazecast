<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Publish;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

/**
 * Redis PubSub provider
 *
 * @phpstan-import-type RedisConfig from \Crustum\BlazeCast\WebSocket\Redis\ClientFactory
 * @phpstan-import-type BroadcastPayload from \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel
 */
class RedisPubSubProvider
{
    /**
     * The Redis publisher client
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPublishClient
     */
    public RedisPublishClient $publisher;

    /**
     * The Redis subscriber client
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisSubscribeClient
     */
    public RedisSubscribeClient $subscriber;

    /**
     * Active subscriptions for cross-platform communication
     *
     * @var array<string, callable>
     */
    protected array $subscriptions = [];

    /**
     * Instantiate a new instance of the provider
     *
     * @param string $channel Channel name
     * @param RedisConfig $server Server configuration
     * @param callable|null $messageHandler Message handler callback
     */
    public function __construct(
        protected string $channel,
        protected array $server = [],
        protected $messageHandler = null,
    ) {
    }

    /**
     * Connect to the publisher
     *
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @return void
     */
    public function connect(LoopInterface $loop): void
    {
        $properties = [$loop, $this->channel, $this->server];

        $this->publisher = new RedisPublishClient(...$properties);
        $this->subscriber = new RedisSubscribeClient(...array_merge($properties, [fn() => $this->subscribe()]));

        $this->publisher->connect();
        $this->subscriber->connect();
    }

    /**
     * Disconnect from the publisher
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->subscriber->disconnect();
        $this->publisher->disconnect();
    }

    /**
     * Subscribe to the publisher
     *
     * @return void
     */
    public function subscribe(): void
    {
        $this->subscriber->subscribe();

        $this->subscriber->on('message', function (string $channel, string $payload): void {
            if ($this->messageHandler) {
                call_user_func($this->messageHandler, $payload);
            }
        });
    }

    /**
     * Subscribe to a specific channel for cross-platform communication
     *
     * @param string $channel Channel name
     * @param callable $callback Message callback
     * @return void
     */
    public function subscribeToChannel(string $channel, callable $callback): void
    {
        $this->subscriptions[$channel] = $callback;

        $this->subscriber->on('message', function (string $msgChannel, string $payload) use ($channel, $callback): void {
            if ($msgChannel === $channel) {
                $callback($payload, $channel);
            }
        });
    }

    /**
     * Unsubscribe from a specific channel
     *
     * @param string $channel Channel name
     * @return void
     */
    public function unsubscribeFromChannel(string $channel): void
    {
        unset($this->subscriptions[$channel]);
    }

    /**
     * Listen for a given event
     *
     * @param string $event Event name
     * @param callable $callback Event callback
     * @return void
     */
    public function on(string $event, callable $callback): void
    {
        $this->subscriber->on('message', function (string $channel, string $payload) use ($event, $callback): void {
            $payload = json_decode($payload, associative: true, flags: JSON_THROW_ON_ERROR);

            if (($payload['type'] ?? null) === $event) {
                $callback($payload);
            }
        });
    }

    /**
     * Publish a payload to the channel
     *
     * @param BroadcastPayload $payload Payload to publish
     * @return \React\Promise\PromiseInterface<mixed>
     */
    public function publish(array $payload): PromiseInterface
    {
        return $this->publisher->publish($payload);
    }
}
