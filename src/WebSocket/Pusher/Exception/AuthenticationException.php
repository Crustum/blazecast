<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Exception;

use RuntimeException;
use Throwable;

/**
 * AuthenticationException
 *
 * Exception thrown when authentication fails
 */
class AuthenticationException extends RuntimeException
{
    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = 'Authentication failed', int $code = 401, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
