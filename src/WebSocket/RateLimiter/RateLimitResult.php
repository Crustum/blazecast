<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\RateLimiter;

/**
 * Rate Limit Result
 *
 * Contains the result of a rate limit check with metadata
 * about remaining points, retry timing, and HTTP headers.
 */
class RateLimitResult
{
    /**
     * Constructor
     *
     * @param bool $canContinue Whether the request can proceed
     * @param int $remainingPoints Number of points remaining in the current window
     * @param int $msBeforeNext Milliseconds before the next point is available
     * @param int $totalHits Total number of hits in the current window
     */
    public function __construct(
        public bool $canContinue,
        public int $remainingPoints,
        public int $msBeforeNext,
        public int $totalHits,
    ) {
    }

    /**
     * Get HTTP headers for rate limit response
     *
     * Returns standard rate limiting headers that can be added to HTTP responses.
     *
     * @return array<string, int|float> Array of header name => value pairs
     */
    public function getHeaders(): array
    {
        return [
            'X-RateLimit-Limit' => $this->totalHits,
            'X-RateLimit-Remaining' => $this->remainingPoints,
            'Retry-After' => (int)ceil($this->msBeforeNext / 1000),
        ];
    }

    /**
     * Check if the rate limit was exceeded
     *
     * @return bool True if rate limit was exceeded
     */
    public function isExceeded(): bool
    {
        return !$this->canContinue;
    }

    /**
     * Get retry delay in seconds
     *
     * @return int Number of seconds to wait before retrying
     */
    public function getRetryAfterSeconds(): int
    {
        return (int)ceil($this->msBeforeNext / 1000);
    }
}
