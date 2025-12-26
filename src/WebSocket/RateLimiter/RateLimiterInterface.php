<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\RateLimiter;

/**
 * Rate Limiter Interface
 *
 * Defines the contract for rate limiting implementations.
 * Supports different rate limiting strategies for backend events,
 * frontend events, and read requests.
 */
interface RateLimiterInterface
{
    /**
     * Consume points for backend-received events (HTTP API).
     *
     * Backend events are rate limited per application, shared across all HTTP requests.
     *
     * @param int $points Number of points to consume
     * @param string $appId Application ID
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult Result with rate limit information
     */
    public function consumeBackendEventPoints(int $points, string $appId): RateLimitResult;

    /**
     * Consume points for frontend-received events (WebSocket client messages).
     *
     * Frontend events are rate limited per connection within each application.
     *
     * @param int $points Number of points to consume
     * @param string $appId Application ID
     * @param string $connectionId WebSocket connection ID
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult Result with rate limit information
     */
    public function consumeFrontendEventPoints(int $points, string $appId, string $connectionId): RateLimitResult;

    /**
     * Consume points for HTTP read requests.
     *
     * Read requests are rate limited per application, shared across all read operations.
     *
     * @param int $points Number of points to consume
     * @param string $appId Application ID
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult Result with rate limit information
     */
    public function consumeReadRequestPoints(int $points, string $appId): RateLimitResult;

    /**
     * Disconnect and cleanup rate limiter resources.
     *
     * @return void
     */
    public function disconnect(): void;
}
