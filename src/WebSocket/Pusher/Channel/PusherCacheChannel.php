<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Pusher\Channel\Trait\CacheChannelsTrait;

/**
 * Pusher Cache Channel
 *
 * Cache channel implementation for Pusher protocol with message caching.
 */
class PusherCacheChannel extends PusherChannel
{
    use CacheChannelsTrait;
}
