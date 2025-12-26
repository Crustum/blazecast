<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Redis;

use Cake\Log\Log;
use Clue\React\Redis\Client;
use Crustum\BlazeCast\WebSocket\Filter\MessageFilterInterface;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use Exception;
use React\EventLoop\LoopInterface;

/**
 * Redis PubSub handler for WebSocket
 *
 * @phpstan-import-type RedisConfig from \Crustum\BlazeCast\WebSocket\Redis\ClientFactory
 * @phpstan-type FilterCriteria array<string, mixed>
 * @phpstan-type TransformRules array<string, mixed>
 */
class PubSub
{
    /**
     * Redis client
     *
     * @var \Clue\React\Redis\Client
     */
    protected Client $client;

    /**
     * Event subscriptions
     *
     * @var array<string, callable>
     */
    protected array $subscriptions = [];

    /**
     * Pattern subscriptions
     *
     * @var array<string, callable>
     */
    protected array $patternSubscriptions = [];

    /**
     * Message filter instance
     *
     * @var \Crustum\BlazeCast\WebSocket\Filter\MessageFilterInterface|null
     */
    protected ?MessageFilterInterface $messageFilter = null;

    /**
     * Constructor
     *
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Server $server WebSocket server
     * @param RedisConfig|null $config Redis configuration
     */
    public function __construct(
        protected LoopInterface $loop,
        protected Server $server,
        ?array $config = null,
    ) {
        $factory = new ClientFactory();
        $this->client = $factory->create($loop, $config);
    }

    /**
     * Subscribe to a channel
     *
     * @param string $channel Channel name
     * @param callable $callback Callback to execute when a message is received
     * @return void
     */
    public function subscribe(string $channel, callable $callback): void
    {
        /** @phpstan-ignore-next-line */
        $this->client->subscribe($channel)->then(
            function () use ($channel, $callback): void {
                Log::info("Subscribed to Redis channel: {$channel}");
                $this->subscriptions[$channel] = $callback;
            },
            function (Exception $e) use ($channel): void {
                Log::error("Failed to subscribe to Redis channel {$channel}: " . $e->getMessage());
            },
        );

        $this->client->on('message', function ($channel, $message): void {
            if (isset($this->subscriptions[$channel])) {
                $callback = $this->subscriptions[$channel];
                $callback($message, $channel);
            }
        });
    }

    /**
     * Unsubscribe from a channel
     *
     * @param string $channel Channel name
     * @return void
     */
    public function unsubscribe(string $channel): void
    {
        /** @phpstan-ignore-next-line */
        $this->client->unsubscribe($channel)->then(
            function () use ($channel): void {
                Log::info("Unsubscribed from Redis channel: {$channel}");
                unset($this->subscriptions[$channel]);
            },
            function (Exception $e) use ($channel): void {
                Log::error("Failed to unsubscribe from Redis channel {$channel}: " . $e->getMessage());
            },
        );
    }

    /**
     * Publish a message to a channel
     *
     * @param string $channel Channel name
     * @param string $message Message to publish
     * @return void
     */
    public function publish(string $channel, string $message): void
    {
        /** @phpstan-ignore-next-line */
        $this->client->publish($channel, $message)->then(
            function () use ($channel): void {
                Log::info("Published message to Redis channel: {$channel}");
            },
            function (Exception $e) use ($channel): void {
                Log::error("Failed to publish to Redis channel {$channel}: " . $e->getMessage());
            },
        );
    }

    /**
     * Set message filter
     *
     * @param \Crustum\BlazeCast\WebSocket\Filter\MessageFilterInterface $filter Message filter
     * @return void
     */
    public function setMessageFilter(MessageFilterInterface $filter): void
    {
        $this->messageFilter = $filter;
    }

    /**
     * Subscribe to a channel pattern
     *
     * @param string $pattern Channel pattern (supports wildcards)
     * @param callable $callback Callback to execute when a message is received
     * @return void
     */
    public function subscribePattern(string $pattern, callable $callback): void
    {
        /** @phpstan-ignore-next-line */
        $this->client->psubscribe($pattern)->then(
            function () use ($pattern, $callback): void {
                Log::info("Subscribed to Redis pattern: {$pattern}");
                $this->patternSubscriptions[$pattern] = $callback;
            },
            function (Exception $e) use ($pattern): void {
                Log::error("Failed to subscribe to Redis pattern {$pattern}: " . $e->getMessage());
            },
        );

        $this->client->on('pmessage', function ($pattern, $channel, $message): void {
            if (isset($this->patternSubscriptions[$pattern])) {
                $callback = $this->patternSubscriptions[$pattern];
                $callback($message, $channel, $pattern);
            }
        });
    }

    /**
     * Unsubscribe from a channel pattern
     *
     * @param string $pattern Channel pattern
     * @return void
     */
    public function unsubscribePattern(string $pattern): void
    {
        /** @phpstan-ignore-next-line */
        $this->client->punsubscribe($pattern)->then(
            function () use ($pattern): void {
                Log::info("Unsubscribed from Redis pattern: {$pattern}");
                unset($this->patternSubscriptions[$pattern]);
            },
            function (Exception $e) use ($pattern): void {
                Log::error("Failed to unsubscribe from Redis pattern {$pattern}: " . $e->getMessage());
            },
        );
    }

    /**
     * Publish a message with optional filtering
     *
     * @param string $channel Channel name
     * @param string $message Message to publish
     * @param FilterCriteria $filterCriteria Optional filter criteria
     * @return void
     */
    public function publishWithFilter(string $channel, string $message, array $filterCriteria = []): void
    {
        if (!empty($filterCriteria) && $this->messageFilter !== null) {
            try {
                $messageObj = Message::fromJson($message);

                if (!$this->messageFilter->filter($messageObj, $filterCriteria)) {
                    Log::info("Message filtered out for channel {$channel}");

                    return;
                }
            } catch (Exception $e) {
                Log::warning('Failed to apply message filter: ' . $e->getMessage());
            }
        }

        $this->publish($channel, $message);
    }

    /**
     * Transform and publish a message
     *
     * @param string $channel Channel name
     * @param string $message Message to publish
     * @param TransformRules $transformRules Transformation rules
     * @return void
     */
    public function publishWithTransform(string $channel, string $message, array $transformRules = []): void
    {
        if (!empty($transformRules) && $this->messageFilter !== null) {
            try {
                $messageObj = Message::fromJson($message);
                $transformedMessage = $this->messageFilter->transform($messageObj, $transformRules);
                $message = $transformedMessage->toJson();
            } catch (Exception $e) {
                Log::warning('Failed to transform message: ' . $e->getMessage());
            }
        }

        $this->publish($channel, $message);
    }

    /**
     * Setup standard WebSocket channels
     *
     * @return void
     */
    public function setupDefaultChannels(): void
    {
        $this->subscribe('blaze:broadcast', function ($message): void {
            $channelOperationsManager = $this->server->getChannelOperationsManager();
            $channelOperationsManager->broadcast($message);
        });

        $this->subscribe('blaze:private', function ($message): void {
            $data = json_decode($message, true);
            if (isset($data['socket_id'], $data['message'])) {
                $connectionRegistry = $this->server->getConnectionRegistry();
                $connection = $connectionRegistry->getConnection($data['socket_id']);
                if ($connection) {
                    $connection->send($data['message']);
                }
            }
        });
    }

    /**
     * Setup enhanced WebSocket channels with patterns
     *
     * @return void
     */
    public function setupEnhancedChannels(): void
    {
        $this->setupDefaultChannels();

        $this->subscribePattern('user:*', function ($message, $channel, $pattern): void {
            $userId = str_replace('user:', '', $channel);
            $this->handleUserMessage($message, $userId);
        });

        $this->subscribePattern('room:*', function ($message, $channel, $pattern): void {
            $roomId = str_replace('room:', '', $channel);
            $this->handleRoomMessage($message, $roomId);
        });

        $this->subscribePattern('notifications:*', function ($message, $channel, $pattern): void {
            $notificationType = str_replace('notifications:', '', $channel);
            $this->handleSystemNotification($message, $notificationType);
        });
    }

    /**
     * Handle user-specific messages
     *
     * @param string $message Message content
     * @param string $userId User ID
     * @return void
     */
    protected function handleUserMessage(string $message, string $userId): void
    {
        $connectionRegistry = $this->server->getConnectionRegistry();
        foreach ($connectionRegistry->getConnections() as $connection) {
            $connectionUserId = $connection->getAttribute('user_id');
            if ($connectionUserId === $userId) {
                $connection->send($message);
                Log::info("Sent user message to connection {$connection->getId()} for user {$userId}");
            }
        }
    }

    /**
     * Handle room-based messages
     *
     * @param string $message Message content
     * @param string $roomId Room ID
     * @return void
     */
    protected function handleRoomMessage(string $message, string $roomId): void
    {
        $channelName = "room-{$roomId}";
        $channelOperationsManager = $this->server->getChannelOperationsManager();
        $channelOperationsManager->broadcastToChannel($channelName, $message);
        Log::info("Broadcasted room message to channel {$channelName}");
    }

    /**
     * Handle system notifications
     *
     * @param string $message Message content
     * @param string $notificationType Notification type
     * @return void
     */
    protected function handleSystemNotification(string $message, string $notificationType): void
    {
        $channelOperationsManager = $this->server->getChannelOperationsManager();

        switch ($notificationType) {
            case 'system':
                $channelOperationsManager->broadcast($message);
                break;
            case 'maintenance':
                $channelOperationsManager->broadcastToChannel('admin-notifications', $message);
                break;
            default:
                Log::info("Unknown notification type: {$notificationType}");
        }
    }
}
