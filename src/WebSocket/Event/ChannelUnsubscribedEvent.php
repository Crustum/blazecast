<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

use Cake\Event\Event;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;

/**
 * Dispatched when a connection unsubscribes from a channel.
 */
class ChannelUnsubscribedEvent extends Event
{
    /**
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The connection that unsubscribed.
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface $channel The channel unsubscribed from.
     */
    public function __construct(public Connection $connection, public PusherChannelInterface $channel)
    {
        parent::__construct(self::class, $connection, [
            'connection' => $connection,
            'channel' => $channel,
        ]);
    }
}
