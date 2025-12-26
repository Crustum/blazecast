<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Pusher\Channel\Trait\PresenceChannelsTrait;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\Trait\PrivateChannelsTrait;

/**
 * Pusher Presence Channel
 *
 * Presence channel implementation for Pusher protocol with member management.
 */
class PusherPresenceChannel extends PusherChannel
{
    use PrivateChannelsTrait;
    use PresenceChannelsTrait {
        PresenceChannelsTrait::subscribe insteadof PrivateChannelsTrait;
        PresenceChannelsTrait::getType insteadof PrivateChannelsTrait;
    }
}
