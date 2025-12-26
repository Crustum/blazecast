<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\RateLimiter;

/**
 * Local Rate Limiter
 *
 * In-memory rate limiting implementation using token bucket algorithm.
 * Manages separate rate limiters for each application and event type.
 *
 * @phpstan-type RateLimiterState array{
 *   points: int,
 *   last_reset: int,
 *   duration: int
 * }
 */
class LocalRateLimiter implements RateLimiterInterface
{
    /**
     * Rate limiters storage
     *
     * @var array<string, RateLimiterState>
     */
    protected array $rateLimiters = [];

    /**
     * Application configurations
     *
     * @var array<string, array<string, int>>
     */
    protected array $appConfigs = [];

    /**
     * Constructor
     *
     * @param array<string, array<string, int>> $appConfigs Application rate limit configurations
     */
    public function __construct(array $appConfigs = [])
    {
        $this->appConfigs = $appConfigs;
    }

    /**
     * Consume points for backend-received events (HTTP API)
     *
     * @param int $points Number of points to consume
     * @param string $appId Application ID
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult Result with rate limit information
     */
    public function consumeBackendEventPoints(int $points, string $appId): RateLimitResult
    {
        $key = "{$appId}:backend:events";
        $maxPoints = $this->getAppConfig($appId, 'max_backend_events_per_second', -1);

        return $this->consume($key, $points, $maxPoints);
    }

    /**
     * Consume points for frontend-received events (WebSocket client messages)
     *
     * @param int $points Number of points to consume
     * @param string $appId Application ID
     * @param string $connectionId WebSocket connection ID
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult Result with rate limit information
     */
    public function consumeFrontendEventPoints(int $points, string $appId, string $connectionId): RateLimitResult
    {
        $key = "{$appId}:frontend:events:{$connectionId}";
        $maxPoints = $this->getAppConfig($appId, 'max_frontend_events_per_second', -1);

        return $this->consume($key, $points, $maxPoints);
    }

    /**
     * Consume points for HTTP read requests
     *
     * @param int $points Number of points to consume
     * @param string $appId Application ID
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult Result with rate limit information
     */
    public function consumeReadRequestPoints(int $points, string $appId): RateLimitResult
    {
        $key = "{$appId}:backend:read_requests";
        $maxPoints = $this->getAppConfig($appId, 'max_read_requests_per_second', -1);

        return $this->consume($key, $points, $maxPoints);
    }

    /**
     * Disconnect and cleanup rate limiter resources
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->rateLimiters = [];
    }

    /**
     * Get application configuration value
     *
     * @param string $appId Application ID
     * @param string $key Configuration key
     * @param int $default Default value if not found
     * @return int Configuration value
     */
    protected function getAppConfig(string $appId, string $key, int $default): int
    {
        return $this->appConfigs[$appId][$key] ?? $default;
    }

    /**
     * Initialize rate limiter for given key
     *
     * @param string $key Rate limiter key
     * @param int $maxPoints Maximum points per duration
     * @return void
     */
    protected function initializeRateLimiter(string $key, int $maxPoints): void
    {
        if (isset($this->rateLimiters[$key])) {
            return;
        }

        $this->rateLimiters[$key] = [
            'points' => $maxPoints,
            'last_reset' => time(),
            'duration' => 1,
            'max_points' => $maxPoints,
        ];
    }

    /**
     * Consume points for a given key
     *
     * @param string $key Rate limiter key
     * @param int $points Number of points to consume
     * @param int $maxPoints Maximum points per second
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult Result with rate limit information
     */
    protected function consume(string $key, int $points, int $maxPoints): RateLimitResult
    {
        if ($maxPoints < 0) {
            return new RateLimitResult(
                canContinue: true,
                remainingPoints: -1,
                msBeforeNext: 0,
                totalHits: 0,
            );
        }

        $this->initializeRateLimiter($key, $maxPoints);

        $currentTime = time();
        $limiter = &$this->rateLimiters[$key];

        if ($currentTime - $limiter['last_reset'] >= $limiter['duration']) {
            $limiter['points'] = $maxPoints;
            $limiter['last_reset'] = $currentTime;
        }

        $remainingPoints = $limiter['points'];

        if ($remainingPoints >= $points) {
            $limiter['points'] -= $points;
            $newRemaining = $limiter['points'];

            return new RateLimitResult(
                canContinue: true,
                remainingPoints: $newRemaining,
                msBeforeNext: 0,
                totalHits: $maxPoints,
            );
        }

        $msBeforeNext = ($limiter['last_reset'] + $limiter['duration'] - $currentTime) * 1000;

        return new RateLimitResult(
            canContinue: false,
            remainingPoints: $remainingPoints,
            msBeforeNext: max(0, $msBeforeNext),
            totalHits: $maxPoints,
        );
    }
}
