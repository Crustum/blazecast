<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

use Cake\Event\Event;

/**
 * Dispatched before a channel fan-out is written to WebSocket clients.
 *
 * Listeners may return an array result to replace the payload that clients receive.
 * BlazeCast always strips reserved `__crustum` metadata after listeners run.
 */
class BeforeClientSendEvent extends Event
{
    /**
     * Cake event name.
     *
     * @var string
     */
    public const EVENT_NAME = 'BlazeCast.WebSocket.beforeClientSend';

    /**
     * @param string $appId Application id.
     * @param string $channel Channel name.
     * @param string $eventName Pusher event name.
     * @param array<string, mixed> $payload Decoded enriched payload (may include `__crustum`).
     */
    public function __construct(
        string $appId,
        string $channel,
        string $eventName,
        array $payload,
    ) {
        parent::__construct(self::EVENT_NAME, null, [
            'app_id' => $appId,
            'channel' => $channel,
            'event' => $eventName,
            'payload' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        $payload = $this->getData('payload');

        return is_array($payload) ? $payload : [];
    }
}
