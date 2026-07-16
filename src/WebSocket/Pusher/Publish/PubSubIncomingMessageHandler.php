<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Publish;

use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Event\EventDispatcher;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use JsonException;
use Throwable;

/**
 * PubSubIncomingMessageHandler
 *
 * Handles Redis fan-out payloads published by other BlazeCast nodes.
 * Uses JSON (app_id) rather than PHP serialize, matching BlazeCast design.
 *
 * @phpstan-type DecodedPubSubEnvelope array{
 *   type?: mixed,
 *   app_id?: mixed,
 *   payload?: mixed,
 *   socket_id?: mixed
 * }
 * @phpstan-type DecodedEventPayload array{
 *   event?: mixed,
 *   channel?: mixed,
 *   channels?: mixed,
 *   data?: mixed
 * }
 */
class PubSubIncomingMessageHandler
{
    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager $connectionManager Connection manager
     */
    public function __construct(
        protected ApplicationManager $applicationManager,
        protected ChannelConnectionManager $connectionManager,
    ) {
    }

    /**
     * Handle an incoming Redis pub/sub payload.
     *
     * @param string $payload JSON-encoded message
     * @return void
     */
    public function handle(string $payload): void
    {
        try {
            /** @var DecodedPubSubEnvelope $event */
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            BlazeCastLogger::error(sprintf('PubSubIncomingMessageHandler: Invalid JSON payload: %s', $e->getMessage()), [
                'scope' => ['socket.server', 'socket.server.redis'],
            ]);

            return;
        }

        if (($event['type'] ?? null) !== 'message') {
            return;
        }

        $appId = $event['app_id'] ?? null;
        $messagePayload = $event['payload'] ?? null;
        if (!is_string($appId) || !is_array($messagePayload)) {
            return;
        }

        /** @var DecodedEventPayload $messagePayload */
        $exceptConnection = null;
        $socketId = $event['socket_id'] ?? null;
        if (is_string($socketId) && $socketId !== '') {
            $exceptConnection = $this->connectionManager->getConnection($socketId);
        }

        $channels = $this->resolveChannels($messagePayload);
        $eventName = $messagePayload['event'] ?? null;
        if (!is_string($eventName) || $eventName === '' || $channels === []) {
            return;
        }

        $data = $messagePayload['data'] ?? null;
        $encodedData = is_string($data) ? $data : (string)json_encode($data);

        try {
            EventDispatcher::dispatchLocal(
                $this->applicationManager,
                $appId,
                $channels,
                $eventName,
                $encodedData,
                $exceptConnection,
            );
        } catch (Throwable $e) {
            BlazeCastLogger::error(sprintf('PubSubIncomingMessageHandler: Failed to dispatch: %s', $e->getMessage()), [
                'scope' => ['socket.server', 'socket.server.redis'],
            ]);
        }
    }

    /**
     * Resolve channel names from an incoming payload.
     *
     * @param DecodedEventPayload $messagePayload Event payload
     * @return list<string>
     */
    protected function resolveChannels(array $messagePayload): array
    {
        if (isset($messagePayload['channels']) && is_array($messagePayload['channels'])) {
            $channels = [];
            foreach ($messagePayload['channels'] as $channelName) {
                if (is_string($channelName) && $channelName !== '') {
                    $channels[] = $channelName;
                }
            }

            return $channels;
        }

        if (isset($messagePayload['channel']) && is_string($messagePayload['channel']) && $messagePayload['channel'] !== '') {
            return [$messagePayload['channel']];
        }

        return [];
    }
}
