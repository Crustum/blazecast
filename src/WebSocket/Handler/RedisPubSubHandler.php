<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Handler;

use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Redis\PubSub;
use Crustum\BlazeCast\WebSocket\WebSocketServerInterface;
use Exception;

/**
 * Redis PubSub message handler
 *
 * Handles Redis PubSub-related messages from clients
 */
class RedisPubSubHandler implements HandlerInterface
{
    /**
     * WebSocket server instance
     *
     * @var \Crustum\BlazeCast\WebSocket\WebSocketServerInterface|null
     */
    protected ?WebSocketServerInterface $server = null;

    /**
     * Redis PubSub instance
     *
     * @var \Crustum\BlazeCast\WebSocket\Redis\PubSub|null
     */
    protected ?PubSub $pubSub = null;

    /**
     * Supported event types
     *
     * @var array<string>
     */
    protected array $supportedEvents = [
        'redis.publish',
        'redis.subscribe',
        'redis.unsubscribe',
    ];

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Redis\PubSub|null $pubSub Redis PubSub instance
     */
    public function __construct(?PubSub $pubSub = null)
    {
        $this->pubSub = $pubSub;
    }

    /**
     * Set the Redis PubSub instance
     *
     * @param \Crustum\BlazeCast\WebSocket\Redis\PubSub $pubSub Redis PubSub instance
     * @return void
     */
    public function setPubSub(PubSub $pubSub): void
    {
        $this->pubSub = $pubSub;
    }

    /**
     * Set the WebSocket server instance
     *
     * @param \Crustum\BlazeCast\WebSocket\WebSocketServerInterface $server WebSocket server
     * @return void
     */
    public function setServer(WebSocketServerInterface $server): void
    {
        $this->server = $server;
    }

    /**
     * Check if this handler supports the given message event type
     *
     * @param string $eventType Event type to check
     * @return bool
     */
    public function supports(string $eventType): bool
    {
        return in_array($eventType, $this->supportedEvents);
    }

    /**
     * Handle a WebSocket message
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection that received the message
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message WebSocket message
     * @return void
     */
    public function handle(Connection $connection, Message $message): void
    {
        if ($this->pubSub === null) {
            Log::warning('Redis PubSub handler called but no PubSub instance is available');
            $this->sendError($connection, 'Redis PubSub is not available');

            return;
        }

        $eventType = $message->getEvent();
        $data = $message->getData();

        switch ($eventType) {
            case 'redis.publish':
                $this->handlePublish($connection, $data);
                break;

            case 'redis.subscribe':
                $this->handleSubscribe($connection, $data);
                break;

            case 'redis.unsubscribe':
                $this->handleUnsubscribe($connection, $data);
                break;
        }
    }

    /**
     * Handle a publish message
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param array<string, mixed> $data Message data
     * @return void
     */
    protected function handlePublish(Connection $connection, array $data): void
    {
        if (!isset($data['channel']) || !isset($data['message'])) {
            $this->sendError($connection, 'Missing channel or message in publish request');

            return;
        }

        $channel = $data['channel'];
        $message = $data['message'];

        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        try {
            $this->pubSub->publish($channel, (string)$message);

            $response = new Message('redis.published', [
                'channel' => $channel,
                'success' => true,
            ]);
            $connection->send($response->toJson());

            Log::info("Published to Redis channel {$channel} from connection {$connection->getId()}");
        } catch (Exception $e) {
            Log::error('Error publishing to Redis: ' . $e->getMessage());
            $this->sendError($connection, 'Failed to publish message to Redis: ' . $e->getMessage());
        }
    }

    /**
     * Handle a subscribe message
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param array<string, mixed> $data Message data
     * @return void
     */
    protected function handleSubscribe(Connection $connection, array $data): void
    {
        if (!isset($data['channel'])) {
            $this->sendError($connection, 'Missing channel in subscribe request');

            return;
        }

        $channel = $data['channel'];
        $connectionId = $connection->getId();

        try {
            $redisChannels = $connection->getAttribute('redis_channels', []);

            if (!in_array($channel, $redisChannels)) {
                $this->pubSub->subscribe($channel, function ($message, $channel) use ($connection): void {
                    $response = new Message('redis.message', [
                        'channel' => $channel,
                        'message' => $message,
                    ]);
                    $connection->send($response->toJson());
                });

                $redisChannels[] = $channel;
                $connection->setAttribute('redis_channels', $redisChannels);

                $response = new Message('redis.subscribed', [
                    'channel' => $channel,
                    'success' => true,
                ]);
                $connection->send($response->toJson());

                Log::info("Connection {$connectionId} subscribed to Redis channel: {$channel}");
            } else {
                $response = new Message('redis.subscribed', [
                    'channel' => $channel,
                    'success' => true,
                    'info' => 'Already subscribed',
                ]);
                $connection->send($response->toJson());
            }
        } catch (Exception $e) {
            Log::error('Error subscribing to Redis: ' . $e->getMessage());
            $this->sendError($connection, 'Failed to subscribe to Redis channel: ' . $e->getMessage());
        }
    }

    /**
     * Handle an unsubscribe message
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param array<string, mixed> $data Message data
     * @return void
     */
    protected function handleUnsubscribe(Connection $connection, array $data): void
    {
        if (!isset($data['channel'])) {
            $this->sendError($connection, 'Missing channel in unsubscribe request');

            return;
        }

        $channel = $data['channel'];
        $connectionId = $connection->getId();

        try {
            $redisChannels = $connection->getAttribute('redis_channels', []);

            if (in_array($channel, $redisChannels)) {
                $this->pubSub->unsubscribe($channel);

                $redisChannels = array_filter($redisChannels, function ($c) use ($channel) {
                    return $c !== $channel;
                });
                $connection->setAttribute('redis_channels', $redisChannels);

                $response = new Message('redis.unsubscribed', [
                    'channel' => $channel,
                    'success' => true,
                ]);
                $connection->send($response->toJson());

                Log::info("Connection {$connectionId} unsubscribed from Redis channel: {$channel}");
            } else {
                $response = new Message('redis.unsubscribed', [
                    'channel' => $channel,
                    'success' => true,
                    'info' => 'Not subscribed',
                ]);
                $connection->send($response->toJson());
            }
        } catch (Exception $e) {
            Log::error('Error unsubscribing from Redis: ' . $e->getMessage());
            $this->sendError($connection, 'Failed to unsubscribe from Redis channel: ' . $e->getMessage());
        }
    }

    /**
     * Send an error response
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $errorMessage Error message
     * @return void
     */
    protected function sendError(Connection $connection, string $errorMessage): void
    {
        $response = new Message('redis.error', [
            'error' => $errorMessage,
        ]);
        $connection->send($response->toJson());
    }
}
