<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Exception;

use RuntimeException;
use Throwable;

/**
 * Connection Limit Exceeded Exception
 *
 * Thrown when an application has reached its maximum connection limit.
 */
class ConnectionLimitExceeded extends RuntimeException
{
    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = 'Application is over connection quota', int $code = 4004, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
