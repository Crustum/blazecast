<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Event\HttpApiEvent;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\RequestInterface;

/**
 * EventsController
 *
 * Controller for handling Pusher events broadcasting
 *
 * @phpstan-import-type RouteParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type PayloadData from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-type SingleEventPayload array{
 *   name?: string,
 *   data?: array<string, mixed>,
 *   channel?: string,
 *   channels?: array<string>,
 *   socket_id?: string,
 *   info?: string|array<string>
 * }
 * @phpstan-type BatchEventPayload array{
 *   batch?: array<SingleEventPayload>
 * }
 * @phpstan-type EventPayload SingleEventPayload|BatchEventPayload
 * @phpstan-type ChannelInfo array<string, mixed>
 * @phpstan-type ChannelsInfo array<string, ChannelInfo>
 */
class EventsController extends PusherController
{
    /**
     * Handle the request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        try {
            $payload = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);

            $this->validateEventPayload($payload);

            $appId = $this->application['id'] ?? 'unknown';
            $points = 1;

            if (isset($payload['batch'])) {
                $points = count($payload['batch']);
            } elseif (isset($payload['channels'])) {
                $points = count($payload['channels']);
            }

            if ($this->rateLimiter !== null) {
                $rateLimitResult = $this->rateLimiter->consumeBackendEventPoints($points, $appId);

                if ($rateLimitResult->isExceeded()) {
                    $response = $this->errorResponse('Rate limit exceeded', 429);
                    foreach ($rateLimitResult->getHeaders() as $name => $value) {
                        $response = $response->withHeader($name, (string)$value);
                    }

                    return $response;
                }
            }

            if (isset($payload['batch'])) {
                return $this->handleBatchEvents($payload);
            }

            return $this->handleSingleEvent($payload);
        } catch (JsonException $e) {
            return $this->errorResponse('Invalid JSON payload', 400);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Handle single event
     *
     * @param SingleEventPayload $payload Event payload
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    protected function handleSingleEvent(array $payload): Response
    {
        $appId = $this->application['id'] ?? 'unknown';
        $channels = $payload['channels'] ?? [$payload['channel']];
        $event = $payload['name'];
        $data = $payload['data'];
        $socketId = $payload['socket_id'] ?? null;

        BlazeCastLogger::info(sprintf('Broadcasting event: app_id=%s, event=%s, channels=%s, socket_id=%s', $appId, $event, implode(', ', $channels), $socketId ?? 'null'), [
            'scope' => ['socket.controller', 'socket.controller.events'],
        ]);

        $appChannelManager = $this->getChannelManagerForCurrentApp();

        $exceptConnection = null;
        if ($socketId !== null) {
            $exceptConnection = $this->connectionManager->getConnection($socketId);
        }

        foreach ($channels as $channelName) {
            $channel = $appChannelManager->getChannel($channelName);
            $message = [
                'event' => $event,
                'channel' => $channelName,
                'data' => $data,
            ];

            $channel->broadcast($message, $exceptConnection);

            $messageBytes = strlen(json_encode($message));

            BlazeCastLogger::info(sprintf('About to call recordWsMessageReceived (single event). app_id=%s, message_bytes=%d', $appId, $messageBytes), [
                'scope' => ['socket.controller', 'socket.controller.events'],
            ]);

            $this->connectionManager->recordWsMessageReceived($appId, $messageBytes);

            $this->eventManager->dispatch(new HttpApiEvent(
                $appId,
                'event',
                json_encode($message),
                $messageBytes,
            ));

            BlazeCastLogger::info(sprintf('WebSocket message received recorded via HTTP API (single event). app_id=%s, message_bytes=%d', $appId, $messageBytes), [
                'scope' => ['socket.controller', 'socket.controller.events'],
            ]);

            BlazeCastLogger::info(sprintf('Event broadcasted to channel via application-specific ChannelManager: app_id=%s, channel=%s, event=%s, connections=%d', $appId, $channelName, $event, $channel->getConnectionCount()), [
                'scope' => ['socket.controller', 'socket.controller.events'],
            ]);
        }

        if (isset($payload['info'])) {
            $channelInfo = $this->getChannelsInfo($channels, $payload['info']);

            return $this->jsonResponse(['channels' => $channelInfo]);
        }

        return $this->successResponse();
    }

    /**
     * Handle batch events
     *
     * @param BatchEventPayload $payload Batch payload
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    protected function handleBatchEvents(array $payload): Response
    {
        $appId = $this->application['id'] ?? 'unknown';
        $results = [];

        foreach ($payload['batch'] as $item) {
            if (!isset($item['name'], $item['data'], $item['channel'])) {
                continue;
            }

            $appChannelManager = $this->getChannelManagerForCurrentApp();

            $exceptConnection = null;
            if (isset($item['socket_id'])) {
                $exceptConnection = $this->connectionManager->getConnection($item['socket_id']);
            }

            $channel = $appChannelManager->getChannel($item['channel']);
            $message = [
                'event' => $item['name'],
                'channel' => $item['channel'],
                'data' => $item['data'],
            ];
            $channel->broadcast($message, $exceptConnection);

            $messageBytes = strlen(json_encode($message));
            $this->connectionManager->recordWsMessageReceived($appId, $messageBytes);

            $this->eventManager->dispatch(new HttpApiEvent(
                $appId,
                'batch_event',
                json_encode($message),
                $messageBytes,
            ));

            BlazeCastLogger::info(sprintf('WebSocket message received recorded via HTTP API (batch event): app_id=%s, message_bytes=%d', $appId, $messageBytes), [
                'scope' => ['socket.controller', 'socket.controller.events'],
            ]);

            BlazeCastLogger::info(sprintf('Batch event broadcasted to channel via application-specific ChannelManager: app_id=%s, channel=%s, event=%s, connections=%d', $appId, $item['channel'], $item['name'], $channel->getConnectionCount()), [
                'scope' => ['socket.controller', 'socket.controller.events'],
            ]);

            if (isset($item['info'])) {
                $results[] = $this->getChannelInfo($item['channel'], $item['info']);
            }
        }

        return $this->jsonResponse(['batch' => $results]);
    }

    /**
     * Validate event payload
     *
     * @param EventPayload $payload Event payload
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validateEventPayload(array $payload): void
    {
        BlazeCastLogger::info(sprintf('Validating event payload: %s', json_encode($payload)), [
            'scope' => ['socket.controller', 'socket.controller.events'],
        ]);

        if (isset($payload['batch'])) {
            $customMessages = [
                'batch' => 'Batch must be an array',
                'batch_type' => 'Batch must be an array',
            ];
            $this->validateRequiredFields($payload, ['batch'], ['batch' => 'array'], $customMessages);

            foreach ($payload['batch'] as $index => $item) {
                try {
                    $this->validateRequiredFields((array)$item, ['name', 'data', 'channel']);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException("Batch item {$index}: " . $e->getMessage());
                }
            }

            return;
        }

        $customMessages = [
            'name' => 'Event name is required',
            'data' => 'Event data is required',
            'channels' => 'Channels must be an array',
            'channels_type' => 'Channels must be an array',
        ];
        $this->validateRequiredFields($payload, ['name', 'data'], [], $customMessages);

        if (!isset($payload['channel']) && !isset($payload['channels'])) {
            throw new InvalidArgumentException('Channel or channels is required');
        }

        if (isset($payload['channels'])) {
            $this->validateRequiredFields($payload, ['channels'], ['channels' => 'array'], $customMessages);
        }
    }

    /**
     * Get channel information for multiple channels
     *
     * @param array<string> $channels List of channel names
     * @param array<string>|string $info Information to include
     * @return ChannelsInfo Channel information
     */
    protected function getChannelsInfo(array $channels, string|array $info): array
    {
        $result = [];
        foreach ($channels as $channel) {
            $result[$channel] = $this->getChannelInfo($channel, $info);
        }

        return $result;
    }

    /**
     * Get channel information for a single channel
     *
     * @param string $channelName Channel name
     * @param array<string>|string $info Information to include
     * @return ChannelInfo Channel information
     */
    protected function getChannelInfo(string $channelName, string|array $info): array
    {
        $appChannelManager = $this->getChannelManagerForCurrentApp();
        $channel = $appChannelManager->getChannel($channelName);
        $result = [
            'success' => true,
            'connection_count' => $channel->getConnectionCount(),
        ];

        if (isset($this->requestData['body']['socket_id'])) {
            $result['excluded_socket'] = $this->requestData['body']['socket_id'];
        }

        return $result;
    }

    /**
     * Get channel manager for current application context
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager
     */
    protected function getChannelManagerForCurrentApp(): ChannelManager
    {
        return $this->getCurrentChannelManager();
    }
}
