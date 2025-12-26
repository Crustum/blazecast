<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Handler;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Handler\AbstractHandler;
use Crustum\BlazeCast\WebSocket\Handler\HandlerInterface;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\Event\EventDispatcher;
use Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult;
use Crustum\BlazeCast\WebSocket\WebSocketServerInterface;
use Exception;
use Throwable;

/**
 * PusherEventHandler
 *
 * Handles all Pusher protocol WebSocket events
 */
class PusherEventHandler extends AbstractHandler implements HandlerInterface
{
    /**
     * Events that this handler can handle
     *
     * @var array<string>
     */
    protected array $handledEvents = [
        'pusher:ping',
        'pusher:pong',
        'pusher:subscribe',
        'pusher:unsubscribe',
        'client-*',
    ];

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
        if (in_array($eventType, $this->handledEvents)) {
            return true;
        }

        if (str_starts_with($eventType, 'client-') && in_array('client-*', $this->handledEvents)) {
            return true;
        }

        return false;
    }

    /**
     * Initialize the handler with the server
     *
     * @param \Crustum\BlazeCast\WebSocket\WebSocketServerInterface $server The WebSocket server
     * @return void
     */
    public function initialize(WebSocketServerInterface $server): void
    {
        parent::initialize($server);
        BlazeCastLogger::info(__('PusherEventHandler: Pusher event handler initialized'), [
            'scope' => ['socket.handler', 'socket.handler.pusher'],
        ]);
    }

    /**
     * Check if handler can handle the message
     *
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message Message
     * @return bool Whether handler can handle message
     */
    public function canHandle(Message $message): bool
    {
        $event = $message->getEvent();

        if (in_array($event, $this->handledEvents)) {
            return true;
        }

        if (str_starts_with($event, 'client-') && in_array('client-*', $this->handledEvents)) {
            return true;
        }

        return false;
    }

    /**
     * Handle a message
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message Message
     * @return void
     */
    public function handle(Connection $connection, Message $message): void
    {
        $event = $message->getEvent();
        $data = $message->getData();

        BlazeCastLogger::info(__('PusherEventHandler: Handling Pusher event: {0} {1}', $event, $connection->getId()), [
            'scope' => ['socket.handler', 'socket.handler.pusher'],
            'connection_id' => $connection->getId(),
        ]);

        switch ($event) {
            case 'pusher:ping':
                $this->handlePing($connection);
                break;

            case 'pusher:pong':
                $this->handlePong($connection);
                break;

            case 'pusher:subscribe':
                $this->handleSubscribe($connection, $data);
                break;

            case 'pusher:unsubscribe':
                $this->handleUnsubscribe($connection, $data);
                break;

            default:
                if (str_starts_with($event, 'client-')) {
                    $this->handleClientEvent($connection, $message);
                } else {
                    BlazeCastLogger::warning(__('PusherEventHandler: Unhandled Pusher event {0} on connection {1}', $event, $connection->getId()), [
                        'scope' => ['socket.handler', 'socket.handler.pusher'],
                    ]);
                }
                break;
        }
    }

    /**
     * Handle ping event
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return void
     */
    protected function handlePing(Connection $connection): void
    {
        $pongData = json_encode([
            'event' => 'pusher:pong',
            'data' => '{}',
        ]);

        $connection->send((string)$pongData);

        BlazeCastLogger::info(__('PusherEventHandler: Pusher pong sent to connection {0}', $connection->getId()), [
            'scope' => ['socket.handler', 'socket.handler.pusher'],
        ]);
    }

    /**
     * Handle pong event
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return void
     */
    protected function handlePong(Connection $connection): void
    {
        $connection->pong('pusher');

        BlazeCastLogger::info(__('PusherEventHandler: Pusher pong received from connection {0}', $connection->getId()), [
            'scope' => ['socket.handler', 'socket.handler.pusher'],
        ]);
    }

    /**
     * Handle subscribe event
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param array<string, mixed> $data Message data
     * @return void
     */
    protected function handleSubscribe(Connection $connection, array $data): void
    {
        if (!isset($data['channel'])) {
            BlazeCastLogger::warning(__('PusherEventHandler: Missing channel in subscribe request for connection {0}', $connection->getId()), [
                'scope' => ['socket.handler', 'socket.handler.pusher'],
            ]);

            return;
        }

        $channelName = $data['channel'];
        $auth = $data['auth'] ?? null;
        $channelData = $data['channel_data'] ?? null;

        BlazeCastLogger::info(__('PusherEventHandler: Subscription request for channel {0} from connection {1}', $channelName, $connection->getId()), [
            'scope' => ['socket.handler', 'socket.handler.pusher'],
        ]);

        try {
            $channelOperationsManager = $this->getChannelOperationsManager();
            if (!$channelOperationsManager) {
                BlazeCastLogger::warning(__('PusherEventHandler: No channel operations manager available for connection {0}', $connection->getId()), [
                    'scope' => ['socket.handler', 'socket.handler.pusher'],
                ]);

                return;
            }

            $channelOperationsManager->subscribeToChannelWithAuth($connection, $channelName, $auth, $channelData);

            if (!str_starts_with($channelName, 'presence-')) {
                $successData = [
                    'event' => 'pusher_internal:subscription_succeeded',
                    'channel' => $channelName,
                    'data' => '{}',
                ];

                $connection->send(json_encode($successData));
            }

            BlazeCastLogger::info(__('PusherEventHandler: Subscription confirmed for channel {0}', $channelName), [
                'scope' => ['socket.handler', 'socket.handler.pusher'],
            ]);
        } catch (Exception $e) {
            BlazeCastLogger::error(__('PusherEventHandler: Error handling WebSocket for channel {0}. Error message: {1}', $channelName, $e->getMessage()), [
                'scope' => ['socket.handler', 'socket.handler.pusher'],
            ]);

            $errorData = [
                'event' => 'pusher_internal:subscription_error',
                'channel' => $channelName,
                'data' => json_encode([
                    'type' => 'AuthError',
                    'error' => $e->getMessage(),
                    'status' => 401,
                ]),
            ];

            $connection->send((string)json_encode($errorData));
        }
    }

    /**
     * Handle unsubscribe event
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param array<string, mixed> $data Message data
     * @return void
     */
    protected function handleUnsubscribe(Connection $connection, array $data): void
    {
        if (!isset($data['channel'])) {
            BlazeCastLogger::warning(__('PusherEventHandler: Missing channel in unsubscribe request for connection {0}', $connection->getId()), [
                'scope' => ['socket.handler', 'socket.handler.pusher'],
            ]);

            return;
        }

        $channelName = $data['channel'];

        BlazeCastLogger::info(__('PusherEventHandler: Unsubscribe request for channel {0}', $channelName), [
            'scope' => ['socket.handler', 'socket.handler.pusher'],
            'connection_id' => $connection->getId(),
        ]);

        $channelOperationsManager = $this->getChannelOperationsManager();
        if (!$channelOperationsManager) {
            BlazeCastLogger::warning(__('PusherEventHandler: No channel operations manager available for connection {0}', $connection->getId()), [
                'scope' => ['socket.handler', 'socket.handler.pusher'],
                'connection_id' => $connection->getId(),
            ]);

            return;
        }

        $channelOperationsManager->unsubscribeFromChannel($connection, $channelName);
    }

    /**
     * Handle client event using multi-application EventDispatcher
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message Message
     * @return void
     */
    protected function handleClientEvent(Connection $connection, Message $message): void
    {
        $data = $message->getData();
        $event = $message->getEvent();
        $channelName = $message->getChannel();

        BlazeCastLogger::info(sprintf('PusherEventHandler: Client event received: %s on channel %s. connection_id=%s', $event, $channelName, $connection->getId()), [
            'scope' => ['socket.handler', 'socket.handler.pusher'],
        ]);

        if (!$channelName) {
            BlazeCastLogger::warning(sprintf('PusherEventHandler: Client event missing channel name for connection %s. event=%s', $connection->getId(), $event), [
                'scope' => ['socket.handler', 'socket.handler.pusher'],
            ]);

            return;
        }

        $appId = $this->getAppIdFromConnection($connection);
        if (!$appId) {
            BlazeCastLogger::error(__('PusherEventHandler: Cannot determine application ID for client event for connection {0} on channel {1} and event {2}', $connection->getId(), $channelName, $event), [
                'scope' => ['socket.handler', 'socket.handler.pusher'],
            ]);

            return;
        }

        $rateLimiter = $this->getRateLimiter();
        if ($rateLimiter !== null) {
            if ($rateLimiter instanceof AsyncRateLimiterInterface) {
                $rateLimiter->consumeFrontendEventPoints(1, $appId, $connection->getId())->then(
                    function (RateLimitResult $rateLimitResult) use ($connection, $appId, $channelName, $event, $data): void {
                        if ($rateLimitResult->isExceeded()) {
                            $this->sendRateLimitError($connection, $rateLimitResult);
                            BlazeCastLogger::warning(__('PusherEventHandler: Rate limit exceeded for frontend event on connection {0}, application {1}, channel {2}, event {3}', $connection->getId(), $appId, $channelName, $event), [
                                'scope' => ['socket.handler', 'socket.handler.pusher'],
                            ]);

                            return;
                        }

                        $this->processClientEvent($connection, $appId, $channelName, $event, $data);
                    },
                    function (Throwable $error): void {
                        BlazeCastLogger::error(sprintf('PusherEventHandler: Rate limiter error: %s', $error->getMessage()), [
                            'scope' => ['socket.handler', 'socket.handler.pusher'],
                        ]);
                    },
                );

                return;
            }

            $rateLimitResult = $rateLimiter->consumeFrontendEventPoints(1, $appId, $connection->getId());

            if ($rateLimitResult->isExceeded()) {
                $this->sendRateLimitError($connection, $rateLimitResult);
                BlazeCastLogger::warning(__('PusherEventHandler: Rate limit exceeded for frontend event on connection {0}, application {1}, channel {2}, event {3}', $connection->getId(), $appId, $channelName, $event), [
                    'scope' => ['socket.handler', 'socket.handler.pusher'],
                ]);

                return;
            }
        }

        $this->processClientEvent($connection, $appId, $channelName, $event, $data);
    }

    /**
     * Process client event after rate limiting check
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $appId Application ID
     * @param string $channelName Channel name
     * @param string $event Event name
     * @param mixed $data Event data
     * @return void
     */
    protected function processClientEvent(Connection $connection, string $appId, string $channelName, string $event, mixed $data): void
    {
        $applicationManager = $this->getApplicationManager();
        if (!$applicationManager) {
            BlazeCastLogger::error(__('PusherEventHandler: ApplicationManager not available for client event broadcasting for connection {0} on channel {1} and event {2}', $connection->getId(), $channelName, $event), [
                'scope' => ['socket.handler', 'socket.handler.pusher'],
            ]);

            return;
        }

        EventDispatcher::dispatch(
            $applicationManager,
            $appId,
            $channelName,
            $event,
            is_string($data) ? $data : json_encode($data),
            $connection,
        );

        BlazeCastLogger::info(__('PusherEventHandler: Client event {0} for application {1} dispatched via EventDispatcher to channel {2} for connection {3}', $event, $appId, $channelName, $connection->getId()), [
            'scope' => ['socket.handler', 'socket.handler.pusher'],
        ]);
    }

    /**
     * Send rate limit error to client
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult $rateLimitResult Rate limit result
     * @return void
     */
    protected function sendRateLimitError(Connection $connection, RateLimitResult $rateLimitResult): void
    {
        $errorData = [
            'event' => 'pusher:error',
            'data' => json_encode([
                'code' => 4200,
                'message' => 'Rate limit exceeded',
                'retry_after' => $rateLimitResult->getRetryAfterSeconds(),
            ]),
        ];

        $connection->send((string)json_encode($errorData));
    }

    /**
     * Get application ID from connection context
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return string|null Application ID or null if not found
     */
    protected function getAppIdFromConnection(Connection $connection): ?string
    {
        $appId = $this->getAppIdForConnection($connection);
        if ($appId) {
            return $appId;
        }

        $appId = $connection->getAttribute('app_id');
        if ($appId) {
            return $appId;
        }

        $appKey = $connection->getAttribute('app_key');
        if ($appKey) {
            $applicationManager = $this->getApplicationManager();
            if ($applicationManager) {
                $application = $applicationManager->getApplicationByKey($appKey);
                if ($application) {
                    return $application['id'];
                }
            }
        }

        $applicationManager = $this->getApplicationManager();
        if ($applicationManager) {
            $applications = $applicationManager->getApplications();
            if (!empty($applications)) {
                $firstApp = array_values($applications)[0];
                BlazeCastLogger::warning(__('PusherEventHandler: Using first available app ID {0} for connection {1}', $firstApp['id'], $connection->getId()), [
                    'scope' => ['socket.handler', 'socket.handler.pusher'],
                ]);

                return $firstApp['id'];
            }
        }

                    BlazeCastLogger::error(__('PusherEventHandler: No application ID found for connection {0}', $connection->getId()), [
            'scope' => ['socket.handler', 'socket.handler.pusher'],
                    ]);

        return null;
    }
}
