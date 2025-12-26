<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Exception;

use InvalidArgumentException;

/**
 * Connection Unauthorized Exception
 *
 * Thrown when a connection fails authentication for private or presence channels.
 */
class ConnectionUnauthorizedException extends InvalidArgumentException
{
}
