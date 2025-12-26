<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher;

/**
 * Pusher protocol helper class
 */
class Pusher
{
    /**
     * Create a new Pusher error message.
     *
     * @param int $code The error code
     * @param string $message The error message
     * @return string
     */
    public static function error(int $code, string $message): string
    {
        return json_encode([
            'event' => 'pusher:error',
            'data' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }
}
