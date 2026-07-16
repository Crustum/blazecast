<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\RateLimiter;

/**
 * Local per-connection WebSocket message rate limiter.
 *
 * Protects the server from floods of any inbound text frames (subscribe, ping,
 * client events, malformed payloads). Always process-local — connections do not
 * migrate across nodes. Complements Soketi-style frontend/backend/read buckets.
 *
 * @phpstan-type ConnectionRateLimitAppConfig array{
 *   max_messages_per_second?: int,
 *   terminate_on_limit?: bool
 * }
 */
class ConnectionMessageRateLimiter
{
    /**
     * Rate limiter state keyed by connection id.
     *
     * @var array<string, array{points: int, last_reset: int, duration: int, max_points: int}>
     */
    protected array $rateLimiters = [];

    /**
     * @param int $defaultMaxMessagesPerSecond Default max text messages per second
     * @param bool $defaultTerminateOnLimit Whether to terminate when exceeded by default
     * @param array<string, ConnectionRateLimitAppConfig> $appConfigs Per-application overrides
     */
    public function __construct(
        protected int $defaultMaxMessagesPerSecond = 60,
        protected bool $defaultTerminateOnLimit = false,
        protected array $appConfigs = [],
    ) {
    }

    /**
     * Consume one message slot for a connection.
     *
     * @param string $connectionId Connection identifier
     * @param string|null $appId Application id for per-app overrides
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult
     */
    public function consume(string $connectionId, ?string $appId = null): RateLimitResult
    {
        $maxPoints = $this->resolveMaxMessagesPerSecond($appId);
        if ($maxPoints < 0) {
            return new RateLimitResult(
                canContinue: true,
                remainingPoints: -1,
                msBeforeNext: 0,
                totalHits: 0,
            );
        }

        $key = $connectionId;
        if (!isset($this->rateLimiters[$key])) {
            $this->rateLimiters[$key] = [
                'points' => $maxPoints,
                'last_reset' => time(),
                'duration' => 1,
                'max_points' => $maxPoints,
            ];
        }

        $currentTime = time();
        $limiter = &$this->rateLimiters[$key];

        if ($limiter['max_points'] !== $maxPoints) {
            $limiter['max_points'] = $maxPoints;
        }

        if ($currentTime - $limiter['last_reset'] >= $limiter['duration']) {
            $limiter['points'] = $maxPoints;
            $limiter['last_reset'] = $currentTime;
        }

        if ($limiter['points'] >= 1) {
            $limiter['points'] -= 1;

            return new RateLimitResult(
                canContinue: true,
                remainingPoints: $limiter['points'],
                msBeforeNext: 0,
                totalHits: $maxPoints,
            );
        }

        $msBeforeNext = ($limiter['last_reset'] + $limiter['duration'] - $currentTime) * 1000;

        return new RateLimitResult(
            canContinue: false,
            remainingPoints: $limiter['points'],
            msBeforeNext: max(0, $msBeforeNext),
            totalHits: $maxPoints,
        );
    }

    /**
     * Whether the connection should be closed when the limit is exceeded.
     *
     * @param string|null $appId Application id
     * @return bool
     */
    public function shouldTerminateOnLimit(?string $appId = null): bool
    {
        if ($appId !== null && array_key_exists('terminate_on_limit', $this->appConfigs[$appId] ?? [])) {
            return (bool)$this->appConfigs[$appId]['terminate_on_limit'];
        }

        return $this->defaultTerminateOnLimit;
    }

    /**
     * Remove rate-limit state for a disconnected connection.
     *
     * @param string $connectionId Connection identifier
     * @return void
     */
    public function forget(string $connectionId): void
    {
        unset($this->rateLimiters[$connectionId]);
    }

    /**
     * Clear all rate-limit state.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->rateLimiters = [];
    }

    /**
     * @param string|null $appId Application id
     * @return int Max messages per second (-1 means unlimited)
     */
    protected function resolveMaxMessagesPerSecond(?string $appId): int
    {
        if ($appId !== null && isset($this->appConfigs[$appId]['max_messages_per_second'])) {
            return (int)$this->appConfigs[$appId]['max_messages_per_second'];
        }

        return $this->defaultMaxMessagesPerSecond;
    }
}
