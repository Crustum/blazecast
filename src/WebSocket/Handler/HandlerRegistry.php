<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Handler;

use Cake\Core\Configure;
use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\WebSocketServerInterface;
use Exception;

/**
 * Registry for WebSocket message handlers
 */
class HandlerRegistry
{
    /**
     * The handlers
     *
     * @var array<\Crustum\BlazeCast\WebSocket\Handler\HandlerInterface>
     */
    protected array $handlers = [];

    /**
     * Server instance
     *
     * @var \Crustum\BlazeCast\WebSocket\WebSocketServerInterface|null
     */
    protected ?WebSocketServerInterface $server = null;

    /**
     * Set the WebSocket server
     *
     * @param \Crustum\BlazeCast\WebSocket\WebSocketServerInterface $server WebSocket server
     * @return void
     */
    public function setServer(WebSocketServerInterface $server): void
    {
        $this->server = $server;

        foreach ($this->handlers as $handler) {
            $handler->setServer($server);
        }
    }

    /**
     * Register a message handler
     *
     * @param \Crustum\BlazeCast\WebSocket\Handler\HandlerInterface $handler Handler to register
     * @return void
     */
    public function register(HandlerInterface $handler): void
    {
        if ($this->server !== null) {
            $handler->setServer($this->server);
        }

        $this->handlers[] = $handler;
    }

    /**
     * Get all registered handlers
     *
     * @return array<\Crustum\BlazeCast\WebSocket\Handler\HandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Handle a WebSocket message
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection that received the message
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message WebSocket message
     * @return bool True if any handler processed the message, false otherwise
     */
    public function handle(Connection $connection, Message $message): bool
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($message->getEvent())) {
                $handler->handle($connection, $message);

                return true;
            }
        }

        return false;
    }

    /**
     * Load handlers configured in the application
     *
     * @return void
     */
    protected function loadConfiguredHandlers(): void
    {
        $handlers = Configure::read('BlazeCast.handlers', []);

        foreach ($handlers as $handlerClass) {
            try {
                if (!class_exists($handlerClass)) {
                    Log::warning("WebSocket handler class not found: {$handlerClass}");
                    continue;
                }

                $handler = new $handlerClass();

                if (!$handler instanceof HandlerInterface) {
                    Log::warning("Class does not implement HandlerInterface: {$handlerClass}");
                    continue;
                }

                $this->register($handler);
            } catch (Exception $e) {
                Log::error("Failed to load WebSocket handler {$handlerClass}: " . $e->getMessage());
            }
        }
    }
}
