<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Handler;

use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\ApplicationContextResolver;
use Crustum\BlazeCast\WebSocket\ChannelOperationsManager;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface;
use Crustum\BlazeCast\WebSocket\WebSocketServerInterface;

/**
 * Abstract base handler for WebSocket messages
 */
abstract class AbstractHandler implements HandlerInterface
{
    /**
     * WebSocket server instance
     *
     * @var \Crustum\BlazeCast\WebSocket\WebSocketServerInterface
     */
    protected WebSocketServerInterface $server;

    /**
     * Events that this handler can handle
     *
     * @var array<string>
     */
    protected array $handledEvents = [];

    /**
     * Initialize the handler with the server
     *
     * @param \Crustum\BlazeCast\WebSocket\WebSocketServerInterface $server The WebSocket server
     * @return void
     */
    public function initialize(WebSocketServerInterface $server): void
    {
        $this->server = $server;
    }

    /**
     * Check if this handler can handle the given message
     *
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message The WebSocket message
     * @return bool True if this handler can handle the message
     */
    public function canHandle(Message $message): bool
    {
        return in_array($message->getEvent(), $this->handledEvents);
    }

    /**
     * Handle a WebSocket message
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The WebSocket connection
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message The WebSocket message
     * @return void
     */
    abstract public function handle(Connection $connection, Message $message): void;

    /**
     * Get application manager from server
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager|null
     */
    protected function getApplicationManager(): ?ApplicationManager
    {
        return $this->server->getApplicationManager();
    }

    /**
     * Get channel manager from server
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager|null
     */
    protected function getChannelManager(): ?ChannelManager
    {
        return $this->server->getChannelManager();
    }

    /**
     * Get connection manager from server
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager|null
     */
    protected function getConnectionManager(): ?ChannelConnectionManager
    {
        return $this->server->getConnectionManager();
    }

    /**
     * Get application context resolver from server
     *
     * @return \Crustum\BlazeCast\WebSocket\ApplicationContextResolver|null
     */
    protected function getApplicationContextResolver(): ?ApplicationContextResolver
    {
        return $this->server->getApplicationContextResolver();
    }

    /**
     * Get channel operations manager from server
     *
     * @return \Crustum\BlazeCast\WebSocket\ChannelOperationsManager|null
     */
    protected function getChannelOperationsManager(): ?ChannelOperationsManager
    {
        return $this->server->getChannelOperationsManager();
    }

    /**
     * Get rate limiter from server
     *
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|\Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface|null
     */
    protected function getRateLimiter(): RateLimiterInterface|AsyncRateLimiterInterface|null
    {
        return $this->server->getRateLimiter();
    }

    /**
     * Get application ID for a connection using context resolver
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return string|null Application ID or null if not found
     */
    protected function getAppIdForConnection(Connection $connection): ?string
    {
        $contextResolver = $this->getApplicationContextResolver();
        if ($contextResolver) {
            return $contextResolver->getAppIdForConnection($connection, []);
        }

        return null;
    }

    /**
     * Get channel manager for a connection using context resolver
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager|null
     */
    protected function getChannelManagerForConnection(Connection $connection): ?ChannelManager
    {
        $contextResolver = $this->getApplicationContextResolver();
        if ($contextResolver) {
            return $contextResolver->getChannelManagerForConnection($connection, []);
        }

        return null;
    }

    /**
     * Send a response message
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The WebSocket connection
     * @param string $event Event name
     * @param mixed $data Message data
     * @param string|null $channel Channel name
     * @return void
     */
    protected function sendResponse(Connection $connection, string $event, mixed $data = null, ?string $channel = null): void
    {
        $response = new Message($event, $data, $channel);
        $connection->send($response->toJson());
    }

    /**
     * Broadcast a message to all connections
     *
     * @param string $event Event name
     * @param mixed $data Message data
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     */
    protected function broadcast(string $event, mixed $data = null, ?string $exceptConnectionId = null): void
    {
        $message = new Message($event, $data);
        $channelOperationsManager = $this->getChannelOperationsManager();
        if ($channelOperationsManager) {
            $channelOperationsManager->broadcast($message->toJson(), $exceptConnectionId);
        }
    }

    /**
     * Broadcast a message to all connections in a channel
     *
     * @param string $channel Channel name
     * @param string $event Event name
     * @param mixed $data Message data
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     */
    protected function broadcastToChannel(
        string $channel,
        string $event,
        mixed $data = null,
        ?string $exceptConnectionId = null,
    ): void {
        $message = new Message($event, $data, $channel);
        $channelOperationsManager = $this->getChannelOperationsManager();
        if ($channelOperationsManager) {
            $channelOperationsManager->broadcastToChannel($channel, $message->toJson(), $exceptConnectionId);
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message Message to log
     * @return void
     */
    protected function debug(string $message): void
    {
        Log::info('[' . static::class . '] ' . $message);
    }

    /**
     * Log an info message
     *
     * @param string $message Message to log
     * @return void
     */
    protected function info(string $message): void
    {
        Log::info('[' . static::class . '] ' . $message);
    }

    /**
     * Log an error message
     *
     * @param string $message Message to log
     * @return void
     */
    protected function error(string $message): void
    {
        Log::error('[' . static::class . '] ' . $message);
    }
}
