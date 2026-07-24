<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Event;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPubSubProvider;

/**
 * EventDispatcher
 *
 * @phpstan-import-type ApplicationConfig from \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
 * @phpstan-import-type ScalingMessagePayload from \Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPubSubProvider
 */
class EventDispatcher
{
    /**
     * Optional Redis pub/sub provider for horizontal fan-out
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPubSubProvider|null
     */
    protected static ?RedisPubSubProvider $pubSubProvider = null;

    /**
     * Set the Redis pub/sub provider used when scaling is enabled.
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPubSubProvider|null $provider Provider
     * @return void
     */
    public static function setPubSubProvider(?RedisPubSubProvider $provider): void
    {
        self::$pubSubProvider = $provider;
    }

    /**
     * Get the Redis pub/sub provider.
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPubSubProvider|null
     */
    public static function getPubSubProvider(): ?RedisPubSubProvider
    {
        return self::$pubSubProvider;
    }

    /**
     * Dispatch an event to a single channel within a specific application
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param string $appId Application ID
     * @param string $channelName Channel name
     * @param string $event Event name
     * @param string $data Event data (JSON string)
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $excludeConnection Connection to exclude
     * @param string|null $socketId Raw socket id for cross-node toOthers (even when connection is unresolved)
     * @return void
     */
    public static function dispatch(
        ApplicationManager $applicationManager,
        string $appId,
        string $channelName,
        string $event,
        string $data,
        ?Connection $excludeConnection = null,
        ?string $socketId = null,
    ): void {
        $excludeConnectionId = $excludeConnection?->getId();
        BlazeCastLogger::info(sprintf('Dispatching event. app_id=%s, event=%s, channel=%s, exclude_connection=%s', $appId, $event, $channelName, $excludeConnectionId ?? 'null'), [
            'scope' => ['socket.handler', 'socket.handler.dispatcher'],
        ]);

        self::dispatchToMultiple(
            $applicationManager,
            $appId,
            [$channelName],
            $event,
            $data,
            $excludeConnection,
            $socketId,
        );
    }

    /**
     * Dispatch an event to multiple channels within a specific application
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param string $appId Application ID
     * @param array<string> $channels Channel names
     * @param string $event Event name
     * @param string $data Event data (JSON string)
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $excludeConnection Connection to exclude
     * @param string|null $socketId Raw socket id for cross-node toOthers
     * @return void
     */
    public static function dispatchToMultiple(
        ApplicationManager $applicationManager,
        string $appId,
        array $channels,
        string $event,
        string $data,
        ?Connection $excludeConnection = null,
        ?string $socketId = null,
    ): void {
        $excludeConnectionId = $excludeConnection?->getId();
        $channelsList = implode(', ', $channels);
        BlazeCastLogger::info(sprintf('Dispatching event to multiple channels. app_id=%s, event=%s, channels=[%s], exclude_connection=%s', $appId, $event, $channelsList, $excludeConnectionId ?? 'null'), [
            'scope' => ['socket.handler', 'socket.handler.dispatcher'],
        ]);

        $resolvedSocketId = $socketId ?? $excludeConnection?->getSocketId() ?? $excludeConnection?->getId();

        if (self::$pubSubProvider instanceof RedisPubSubProvider) {
            /** @var ScalingMessagePayload $payload */
            $payload = [
                'type' => 'message',
                'app_id' => $appId,
                'payload' => [
                    'event' => $event,
                    'channels' => array_values($channels),
                    'data' => $data,
                ],
            ];

            if ($resolvedSocketId !== null) {
                $payload['socket_id'] = $resolvedSocketId;
            }

            self::$pubSubProvider->publish($payload);

            return;
        }

        self::dispatchLocal(
            $applicationManager,
            $appId,
            $channels,
            $event,
            $data,
            $excludeConnection,
        );
    }

    /**
     * Broadcast locally without publishing to Redis (used by incoming pub/sub handler).
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param string $appId Application ID
     * @param array<string> $channels Channel names
     * @param string $event Event name
     * @param string $data Event data
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $excludeConnection Connection to exclude
     * @return void
     */
    public static function dispatchLocal(
        ApplicationManager $applicationManager,
        string $appId,
        array $channels,
        string $event,
        string $data,
        ?Connection $excludeConnection = null,
    ): void {
        foreach ($channels as $channelName) {
            self::broadcastToChannel($applicationManager, $appId, $channelName, $event, $data, $excludeConnection);
        }
    }

    /**
     * Broadcast event to a single channel using application-specific ChannelManager
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param string $appId Application ID
     * @param string $channelName Channel name
     * @param string $event Event name
     * @param string $data Event data
     * @param \Crustum\BlazeCast\WebSocket\Connection|null $excludeConnection Connection to exclude
     * @return void
     */
    protected static function broadcastToChannel(
        ApplicationManager $applicationManager,
        string $appId,
        string $channelName,
        string $event,
        string $data,
        ?Connection $excludeConnection = null,
    ): void {
        $application = $applicationManager->getApplication($appId);
        if (!$application) {
            BlazeCastLogger::error(sprintf('Application not found for broadcasting. app_id=%s, channel=%s, event=%s', $appId, $channelName, $event), [
                'scope' => ['socket.handler', 'socket.handler.dispatcher'],
            ]);

            return;
        }

        $channelManager = self::getChannelManagerFromApplication($application);
        if (!$channelManager instanceof ChannelManager) {
            BlazeCastLogger::error(sprintf('ChannelManager not found for application. app_id=%s, channel=%s, event=%s', $appId, $channelName, $event), [
                'scope' => ['socket.handler', 'socket.handler.dispatcher'],
            ]);

            return;
        }

        $channel = $channelManager->getChannel($channelName);

        $message = [
            'event' => $event,
            'channel' => $channelName,
            'data' => $data,
        ];

        $channel->broadcast($message, $excludeConnection);

        $connectionCount = count($channel->getConnections());
        $excludeConnectionId = $excludeConnection?->getId();
        BlazeCastLogger::info(sprintf('Event broadcasted to channel via application-specific ChannelManager. app_id=%s, channel=%s, event=%s, connection_count=%d, excluded_connection=%s', $appId, $channelName, $event, $connectionCount, $excludeConnectionId ?? 'null'), [
            'scope' => ['socket.handler', 'socket.handler.dispatcher'],
        ]);
    }

    /**
     * Get ChannelManager from application array
     *
     * @param ApplicationConfig $application Application configuration
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager|null
     */
    protected static function getChannelManagerFromApplication(array $application): ?ChannelManager
    {
        if (isset($application['channel_manager'])/* && $application['channel_manager'] instanceof ChannelManager*/) {
            return $application['channel_manager'];
        }

            BlazeCastLogger::warning(__('ChannelManager not found in application {0}, creating new instance', $application['id']), [
            'scope' => ['socket.handler', 'socket.handler.dispatcher'],
            ]);

        return new ChannelManager();
    }
}
