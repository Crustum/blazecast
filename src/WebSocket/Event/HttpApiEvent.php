<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

use Cake\Event\Event;

/**
 * Event triggered when HTTP API calls are made (events, batch_events, etc.)
 */
class HttpApiEvent extends Event
{
    /**
     * Constructor
     *
     * @param string $appId Application ID
     * @param string $eventType Type of HTTP API event (e.g., 'event', 'batch_event')
     * @param string $messageData Message data that was processed
     * @param int $bytes Number of bytes processed
     */
    public function __construct(
        protected string $appId,
        protected string $eventType,
        protected string $messageData,
        protected int $bytes,
    ) {
        parent::__construct(self::class, null, [
            'app_id' => $appId,
            'event_type' => $eventType,
            'message_data' => $messageData,
            'bytes' => $bytes,
        ]);
    }

    /**
     * Get the application ID
     *
     * @return string
     */
    public function getAppId(): string
    {
        return $this->appId;
    }

    /**
     * Get the event type
     *
     * @return string
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * Get the message data
     *
     * @return string
     */
    public function getMessageData(): string
    {
        return $this->messageData;
    }

    /**
     * Get the number of bytes
     *
     * @return int
     */
    public function getBytes(): int
    {
        return $this->bytes;
    }
}
