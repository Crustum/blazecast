<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Handler;

use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\WebSocketServerInterface;

/**
 * Default message handler
 *
 * This handler serves as a fallback for messages that are not handled by other handlers
 */
class DefaultHandler implements HandlerInterface
{
    /**
     * WebSocket server instance
     *
     * @var \Crustum\BlazeCast\WebSocket\WebSocketServerInterface|null
     */
    protected ?WebSocketServerInterface $server = null;

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
     * Default handler supports all events, but with low priority
     *
     * @param string $eventType Event type to check
     * @return bool
     */
    public function supports(string $eventType): bool
    {
        return true;
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
        $eventType = $message->getEvent();

        if ($this->isProtocolEvent($eventType)) {
            $connection->send($message->toJson());

            return;
        }

        Log::info("DefaultHandler handling message event: {$eventType}");

        $response = new Message('echo', [
            'original_event' => $eventType,
            'data' => $message->getData(),
            'timestamp' => time(),
        ]);

        $connection->send($response->toJson());
    }

    /**
     * Check if event is a protocol event that should not be echoed
     *
     * @param string $eventType Event type to check
     * @return bool True if this is a protocol event
     */
    protected function isProtocolEvent(string $eventType): bool
    {
        return str_starts_with($eventType, 'pusher:') || str_starts_with($eventType, 'pusher_internal:');
    }
}
