<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

use Cake\Event\Event;

/**
 * Base class for channel-related events
 *
 * @phpstan-type ChannelEventData array{
 *   channel: string,
 *   event: string,
 *   data?: array<string, mixed>,
 *   socket_id?: string
 * }
 */
abstract class ChannelEvent extends Event
{
    /**
     * Channel name
     *
     * @var string
     */
    protected string $channel;

    /**
     * Constructor
     *
     * @param string $name Event name
     * @param string $channel Channel name
     * @param array<string, mixed> $data Event data
     * @param object|null $subject Event subject
     */
    public function __construct(string $name, string $channel, array $data = [], ?object $subject = null)
    {
        $this->channel = $channel;

        $data = array_merge(['channel' => $channel], $data);

        parent::__construct($name, $subject ?? $this, $data);
    }

    /**
     * Get the channel name
     *
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channel;
    }
}
