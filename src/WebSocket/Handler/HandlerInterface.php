<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Handler;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\WebSocketServerInterface;

/**
 * Interface for WebSocket message handlers
 */
interface HandlerInterface
{
    /**
     * Set the WebSocket server instance
     *
     * @param \Crustum\BlazeCast\WebSocket\WebSocketServerInterface $server WebSocket server
     * @return void
     */
    public function setServer(WebSocketServerInterface $server): void;

    /**
     * Check if this handler supports the given message event type
     *
     * @param string $eventType Event type to check
     * @return bool
     */
    public function supports(string $eventType): bool;

    /**
     * Handle a WebSocket message
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection that received the message
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message WebSocket message
     * @return void
     */
    public function handle(Connection $connection, Message $message): void;
}
