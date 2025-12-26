<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\RateLimiter;

use React\Promise\PromiseInterface;

/**
 * Async Rate Limiter Interface
 *
 * Defines the contract for asynchronous rate limiting implementations.
 * Used for WebSocket events to avoid blocking the ReactPHP event loop.
 */
interface AsyncRateLimiterInterface
{
    /**
     * Consume points for backend-received events (HTTP API).
     *
     * Backend events are rate limited per application, shared across all HTTP requests.
     *
     * @param int $points Number of points to consume
     * @param string $appId Application ID
     * @return \React\Promise\PromiseInterface<\Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult> Promise with rate limit information
     */
    public function consumeBackendEventPoints(int $points, string $appId): PromiseInterface;

    /**
     * Consume points for frontend-received events (WebSocket client messages).
     *
     * Frontend events are rate limited per connection within each application.
     *
     * @param int $points Number of points to consume
     * @param string $appId Application ID
     * @param string $connectionId WebSocket connection ID
     * @return \React\Promise\PromiseInterface<\Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult> Promise with rate limit information
     */
    public function consumeFrontendEventPoints(int $points, string $appId, string $connectionId): PromiseInterface;

    /**
     * Consume points for HTTP read requests.
     *
     * Read requests are rate limited per application, shared across all read operations.
     *
     * @param int $points Number of points to consume
     * @param string $appId Application ID
     * @return \React\Promise\PromiseInterface<\Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult> Promise with rate limit information
     */
    public function consumeReadRequestPoints(int $points, string $appId): PromiseInterface;

    /**
     * Disconnect and cleanup rate limiter resources.
     *
     * @return void
     */
    public function disconnect(): void;
}
