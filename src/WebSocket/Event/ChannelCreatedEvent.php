<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

/**
 * Event triggered when a channel is created
 */
class ChannelCreatedEvent extends ChannelEvent
{
    /**
     * Event name
     *
     * @var string
     */
    protected const EVENT_NAME = 'BlazeCast.channel.created';

    /**
     * Constructor
     *
     * @param string $channel Channel name
     * @param array<string, mixed> $data Additional event data
     * @param object|null $subject Event subject
     */
    public function __construct(string $channel, array $data = [], ?object $subject = null)
    {
        parent::__construct(self::class, $channel, $data, $subject);
    }

    /**
     * Get the channel name
     *
     * @return string
     */
    public function getChannelName(): string
    {
        return $this->channel;
    }

    /**
     * Get channel metadata
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->getData();
    }
}
