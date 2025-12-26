<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\RateLimiter;

use Crustum\BlazeCast\WebSocket\Redis\SyncClientFactory;
use Redis;
use Throwable;

/**
 * Redis Rate Limiter
 *
 * Distributed rate limiting implementation using Redis with token bucket algorithm.
 * Supports multiple server instances with shared rate limit state.
 *
 * @phpstan-type RateLimiterState array{
 *   points: int,
 *   last_reset: int,
 *   duration: int
 * }
 */
class RedisRateLimiter implements RateLimiterInterface
{
    /**
     * Redis client
     *
     * @var \Redis
     */
    protected Redis $redis;

    /**
     * Application configurations
     *
     * @var array<string, array<string, int>>
     */
    protected array $appConfigs = [];

    /**
     * Key prefix for Redis keys
     *
     * @var string
     */
    protected string $keyPrefix = 'blazecast:rate_limit:';

    /**
     * Duration in seconds for rate limit window
     *
     * @var int
     */
    protected int $duration = 1;

    /**
     * Lua script for atomic rate limit consumption
     *
     * @var string
     */
    protected string $luaScript;

    /**
     * Cached SHA1 hash of Lua script
     *
     * @var string|null
     */
    protected ?string $luaScriptSha = null;

    /**
     * Constructor
     *
     * @param array<string, array<string, int>> $appConfigs Application rate limit configurations
     * @param array<string, mixed> $redisConfig Redis configuration
     */
    public function __construct(array $appConfigs = [], array $redisConfig = [])
    {
        $this->appConfigs = $appConfigs;
        $factory = new SyncClientFactory();
        $this->redis = $factory->create($redisConfig);
        $this->initializeLuaScript();
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
        $key = $this->keyPrefix . "{$appId}:backend:events";
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
        $key = $this->keyPrefix . "{$appId}:frontend:events:{$connectionId}";
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
        $key = $this->keyPrefix . "{$appId}:backend:read_requests";
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
        $this->redis->close();
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
     * Initialize Lua script for atomic operations
     *
     * @return void
     */
    protected function initializeLuaScript(): void
    {
        $this->luaScript = <<<'LUA'
local key = KEYS[1]
local points = tonumber(ARGV[1])
local maxPoints = tonumber(ARGV[2])
local duration = tonumber(ARGV[3])
local currentTime = tonumber(ARGV[4])

local data = redis.call('HMGET', key, 'points', 'last_reset')
local currentPoints = tonumber(data[1]) or maxPoints
local lastReset = tonumber(data[2]) or currentTime

if (currentTime - lastReset) >= duration then
    currentPoints = maxPoints
    lastReset = currentTime
end

local remainingPoints = currentPoints

if remainingPoints >= points then
    currentPoints = currentPoints - points
    redis.call('HSET', key, 'points', currentPoints, 'last_reset', lastReset)
    redis.call('EXPIRE', key, duration + 1)
    return {1, currentPoints, 0, maxPoints}
else
    local msBeforeNext = (lastReset + duration - currentTime) * 1000
    if msBeforeNext < 0 then
        msBeforeNext = 0
    end
    return {0, remainingPoints, msBeforeNext, maxPoints}
end
LUA;
        $this->luaScriptSha = sha1($this->luaScript);
    }

    /**
     * Consume points for a given key using Lua script
     *
     * @param string $key Rate limiter key
     * @param int $points Number of points to consume
     * @param int $maxPoints Maximum points per duration
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

        try {
            $currentTime = time();
            $args = [$key, $points, $maxPoints, $this->duration, $currentTime];

            if ($this->luaScriptSha !== null) {
                $result = $this->redis->evalSha($this->luaScriptSha, $args, 1);
                if ($result !== false) {
                    return $this->parseResult($result, $maxPoints);
                }
            }

            $loadedSha = $this->redis->script('LOAD', $this->luaScript);
            $this->luaScriptSha = $loadedSha;
            $result = $this->redis->eval($this->luaScript, $args, 1);

            return $this->parseResult($result, $maxPoints);
        } catch (Throwable $e) {
            return new RateLimitResult(
                canContinue: true,
                remainingPoints: $maxPoints,
                msBeforeNext: 0,
                totalHits: $maxPoints,
            );
        }
    }

    /**
     * Parse Lua script result into RateLimitResult
     *
     * @param mixed $result Lua script result
     * @param int $maxPoints Maximum points
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult Result with rate limit information
     */
    protected function parseResult(mixed $result, int $maxPoints): RateLimitResult
    {
        if (!is_array($result) || count($result) !== 4) {
            return new RateLimitResult(
                canContinue: true,
                remainingPoints: $maxPoints,
                msBeforeNext: 0,
                totalHits: $maxPoints,
            );
        }

        $canContinue = (bool)$result[0];
        $remainingPoints = (int)$result[1];
        $msBeforeNext = (int)$result[2];
        $totalHits = (int)$result[3];

        return new RateLimitResult(
            canContinue: $canContinue,
            remainingPoints: $remainingPoints,
            msBeforeNext: $msBeforeNext,
            totalHits: $totalHits,
        );
    }
}
