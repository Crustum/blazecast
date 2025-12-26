<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Handler;

use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\WebSocketServerInterface;

/**
 * Ping handler for WebSocket connections
 *
 * Handles ping messages to keep connections alive
 */
class PingHandler implements HandlerInterface
{
    /**
     * WebSocket server instance
     *
     * @var \Crustum\BlazeCast\WebSocket\WebSocketServerInterface|null
     */
    protected ?WebSocketServerInterface $server = null;

    /**
     * Supported event types
     *
     * @var array<string>
     */
    protected array $supportedEvents = [
        'ping',
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
        $connection->pong('pusher');
        $connection->updateActivity();

        $response = new Message('pong', [
            'time' => microtime(true),
            'server_time' => time(),
        ]);

        $connection->send($response->toJson());

        if (Log::getConfig('debug')) {
            Log::info("Ping received from connection {$connection->getId()}");
        }
    }
}
