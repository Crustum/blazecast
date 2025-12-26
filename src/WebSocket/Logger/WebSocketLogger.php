<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Logger;

use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\Event\ChannelCreatedEvent;
use Crustum\BlazeCast\WebSocket\Event\ChannelRemovedEvent;
use Crustum\BlazeCast\WebSocket\Event\ConnectionPrunedEvent;
use Crustum\BlazeCast\WebSocket\Event\MessageReceivedEvent;
use Crustum\BlazeCast\WebSocket\Event\MessageSentEvent;

/**
 * Logger for WebSocket events
 */
class WebSocketLogger implements EventListenerInterface
{
    /**
     * Constructor
     *
     * @param bool $debug Enable detailed debug logging
     */
    public function __construct(
        protected bool $debug = false,
    ) {
    }

    /**
     * Get the implemented events
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'BlazeCast.WebSocket.channelCreated' => 'onChannelCreated',
            'BlazeCast.WebSocket.channelRemoved' => 'onChannelRemoved',
            'BlazeCast.WebSocket.connectionPruned' => 'onConnectionPruned',
            'BlazeCast.WebSocket.messageReceived' => 'onMessageReceived',
            'BlazeCast.WebSocket.messageSent' => 'onMessageSent',
        ];
    }

    /**
     * Register the logger with the event manager
     *
     * @return void
     */
    public function register(): void
    {
        $eventManager = EventManager::instance();
        $eventManager->on($this);
    }

    /**
     * Handle channel created event
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\ChannelCreatedEvent $event The event
     * @return void
     */
    public function onChannelCreated(ChannelCreatedEvent $event): void
    {
        $channel = $event->getChannelName();
        Log::info("Channel created: {$channel}");
    }

    /**
     * Handle channel removed event
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\ChannelRemovedEvent $event The event
     * @return void
     */
    public function onChannelRemoved(ChannelRemovedEvent $event): void
    {
        $channel = $event->getChannelName();
        Log::info("Channel removed: {$channel}");
    }

    /**
     * Handle connection pruned event
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\ConnectionPrunedEvent $event The event
     * @return void
     */
    public function onConnectionPruned(ConnectionPrunedEvent $event): void
    {
        $connection = $event->getConnection();
        $reason = $event->getReason();
        Log::info("Connection pruned: {$connection->getId()} (reason: {$reason})");
    }

    /**
     * Handle message received event
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\MessageReceivedEvent $event The event
     * @return void
     */
    public function onMessageReceived(MessageReceivedEvent $event): void
    {
        if (!$this->debug) {
            return;
        }

        $connection = $event->getConnection();
        $message = '...to do';
        // $message = $event->getMessage();

        // if (strlen($message) > 200) {
        //     $message = substr($message, 0, 197) . '...';
        // }

        Log::info("Message received from {$connection->getId()}: {$message}");
    }

    /**
     * Handle message sent event
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\MessageSentEvent $event The event
     * @return void
     */
    public function onMessageSent(MessageSentEvent $event): void
    {
        if (!$this->debug) {
            return;
        }

        $connection = $event->getConnection();
        $message = $event->getMessage();

        if (strlen($message) > 200) {
            $message = substr($message, 0, 197) . '...';
        }

        Log::info("Message sent to {$connection->getId()}: {$message}");
    }
}
