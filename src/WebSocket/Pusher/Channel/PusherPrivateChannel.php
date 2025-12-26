<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Pusher\Channel\Trait\PrivateChannelsTrait;

/**
 * Pusher Private Channel
 *
 * Private channel implementation for Pusher protocol with authentication.
 */
class PusherPrivateChannel extends PusherChannel
{
    use PrivateChannelsTrait;
}
