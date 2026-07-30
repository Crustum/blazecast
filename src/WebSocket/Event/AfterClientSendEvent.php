<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

use Cake\Event\Event;

/**
 * Dispatched after a channel fan-out has been written to WebSocket clients.
 *
 * Includes both the enriched (pre-strip) and clean (client) payloads for recorders.
 */
class AfterClientSendEvent extends Event
{
    /**
     * Cake event name.
     *
     * @var string
     */
    public const EVENT_NAME = 'BlazeCast.WebSocket.afterClientSend';

    /**
     * @param string $appId Application id.
     * @param string $channel Channel name.
     * @param string $eventName Pusher event name.
     * @param array<string, mixed> $payload Clean payload delivered to clients.
     * @param array<string, mixed> $enrichedPayload Payload before reserved-meta strip.
     * @param int $deliveredTo Recipient count after exclusions.
     * @param list<string> $connectionIds Sample of connection ids (capped).
     */
    public function __construct(
        string $appId,
        string $channel,
        string $eventName,
        array $payload,
        array $enrichedPayload,
        int $deliveredTo,
        array $connectionIds,
    ) {
        parent::__construct(self::EVENT_NAME, null, [
            'app_id' => $appId,
            'channel' => $channel,
            'event' => $eventName,
            'payload' => $payload,
            'enrichedPayload' => $enrichedPayload,
            'delivered_to' => $deliveredTo,
            'connection_ids' => $connectionIds,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getEnrichedPayload(): array
    {
        $payload = $this->getData('enrichedPayload');

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return list<string>
     */
    public function getConnectionIds(): array
    {
        $ids = $this->getData('connection_ids');
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_map(strval(...), $ids));
    }

    /**
     * @return int
     */
    public function getDeliveredTo(): int
    {
        return (int)$this->getData('delivered_to');
    }
}
