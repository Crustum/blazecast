<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Service;

use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Crustum\BlazeCast\WebSocket\Event\MessageReceivedEvent;
use Crustum\BlazeCast\WebSocket\Event\MessageSentEvent;
use Rhythm\Event\SharedBeat;

/**
 * Event Dispatcher Service
 *
 * Handles periodic event dispatching for Rhythm recorders.
 * Dispatches SharedBeat events every second for connection recording.
 */
class EventDispatcherService
{
    /**
     * Event manager
     *
     * @var \Cake\Event\EventManager
     */
    protected EventManager $eventManager;

    /**
     * Constructor
     *
     * @param \Cake\Event\EventManager $eventManager Event manager
     */
    public function __construct(EventManager $eventManager)
    {
        echo '>>> EventDispatcherService constructor' . "\n";
        $this->eventManager = $eventManager;
    }

    /**
     * Dispatch SharedBeat event
     *
     * @return void
     */
    public function dispatchSharedBeat(): void
    {
        $event = new SharedBeat(new DateTime(), 'blazecast');
        $this->eventManager->dispatch($event);
    }

    /**
     * Dispatch message sent event
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\MessageSentEvent $event Message sent event
     * @return void
     */
    public function dispatchMessageSent(MessageSentEvent $event): void
    {
        $this->eventManager->dispatch($event);
    }

    /**
     * Dispatch message received event
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\MessageReceivedEvent $event Message received event
     * @return void
     */
    public function dispatchMessageReceived(MessageReceivedEvent $event): void
    {
        $this->eventManager->dispatch($event);
    }
}
