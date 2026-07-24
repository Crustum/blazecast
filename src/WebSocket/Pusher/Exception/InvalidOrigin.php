<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Exception;

use RuntimeException;
use Throwable;

/**
 * Invalid Origin Exception
 *
 * Thrown when a WebSocket connection Origin is not in the application allow-list.
 */
class InvalidOrigin extends RuntimeException
{
    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = 'Origin not allowed', int $code = 4009, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
