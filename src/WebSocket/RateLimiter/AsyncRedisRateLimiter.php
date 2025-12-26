<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\RateLimiter;

use Clue\React\Redis\Client;
use Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisClientFactory;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Throwable;

/**
 * Async Redis Rate Limiter
 *
 * Distributed rate limiting implementation using async Redis with token bucket algorithm.
 * Supports multiple server instances with shared rate limit state.
 * Non-blocking for WebSocket events in ReactPHP event loop.
 */
class AsyncRedisRateLimiter implements AsyncRateLimiterInterface
{
    /**
     * Redis client
     *
     * @var \Clue\React\Redis\Client|null
     */
    protected ?Client $redis = null;

    /**
     * Event loop
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected LoopInterface $loop;

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
     * Redis connection URL
     *
     * @var string
     */
    protected string $redisUrl;

    /**
     * Constructor
     *
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @param array<string, array<string, int>> $appConfigs Application rate limit configurations
     * @param array<string, mixed> $redisConfig Redis configuration
     */
    public function __construct(LoopInterface $loop, array $appConfigs = [], array $redisConfig = [])
    {
        $this->loop = $loop;
        $this->appConfigs = $appConfigs;
        $this->redisUrl = $this->buildRedisUrl($redisConfig);
        $this->initializeLuaScript();
        $this->connect();
    }

    /**
     * Connect to Redis
     *
     * @return void
     */
    protected function connect(): void
    {
        $factory = new RedisClientFactory();
        $factory->make($this->loop, $this->redisUrl)->then(
            function (Client $client): void {
                $this->redis = $client;
                $this->loadLuaScript();
            },
            function (Throwable $error): void {
                // Connection failed, will retry on first use
            },
        );
    }

    /**
     * Load Lua script into Redis
     *
     * @return void
     */
    protected function loadLuaScript(): void
    {
        if ($this->redis === null) {
            return;
        }

        /** @phpstan-ignore-next-line */
        $this->redis->script('LOAD', $this->luaScript)->then(
            function (string $sha): void {
                $this->luaScriptSha = $sha;
            },
            function (Throwable $error): void {
                // Script loading failed, will use EVAL instead
            },
        );
    }

    /**
     * Build Redis URL from configuration
     *
     * @param array<string, mixed> $config Redis configuration
     * @return string Redis URL
     */
    protected function buildRedisUrl(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;

        $url = 'redis://';
        if ($password !== null) {
            $url .= ":$password@";
        }
        $url .= "$host:$port";
        if ($database > 0) {
            $url .= "/$database";
        }

        return $url;
    }

    /**
     * Consume points for backend-received events (HTTP API)
     *
     * @param int $points Number of points to consume
     * @param string $appId Application ID
     * @return \React\Promise\PromiseInterface<\Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult> Promise with rate limit information
     */
    public function consumeBackendEventPoints(int $points, string $appId): PromiseInterface
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
     * @return \React\Promise\PromiseInterface<\Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult> Promise with rate limit information
     */
    public function consumeFrontendEventPoints(int $points, string $appId, string $connectionId): PromiseInterface
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
     * @return \React\Promise\PromiseInterface<\Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult> Promise with rate limit information
     */
    public function consumeReadRequestPoints(int $points, string $appId): PromiseInterface
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
        $this->redis?->close();
        $this->redis = null;
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
     * @return \React\Promise\PromiseInterface<\Crustum\BlazeCast\WebSocket\RateLimiter\RateLimitResult> Promise with rate limit information
     */
    protected function consume(string $key, int $points, int $maxPoints): PromiseInterface
    {
        if ($maxPoints < 0) {
            return new Promise(function ($resolve): void {
                $resolve(new RateLimitResult(
                    canContinue: true,
                    remainingPoints: -1,
                    msBeforeNext: 0,
                    totalHits: 0,
                ));
            });
        }

        if ($this->redis === null) {
            return new Promise(function ($resolve) use ($maxPoints): void {
                $resolve(new RateLimitResult(
                    canContinue: true,
                    remainingPoints: $maxPoints,
                    msBeforeNext: 0,
                    totalHits: $maxPoints,
                ));
            });
        }

        $currentTime = time();
        $numKeys = 1;
        $keys = [$key];
        $args = [$points, $maxPoints, $this->duration, $currentTime];

        if ($this->luaScriptSha !== null) {
            /** @phpstan-ignore-next-line */
            return $this->redis->evalsha($this->luaScriptSha, $numKeys, ...$keys, ...$args)->then(
                function ($result) use ($maxPoints): RateLimitResult {
                    return $this->parseResult($result, $maxPoints);
                },
                function (Throwable $error) use ($maxPoints, $numKeys, $keys, $args): PromiseInterface {
                    /** @phpstan-ignore-next-line */
                    return $this->redis->eval($this->luaScript, $numKeys, ...$keys, ...$args)->then(
                        function ($result) use ($maxPoints): RateLimitResult {
                            return $this->parseResult($result, $maxPoints);
                        },
                        function (Throwable $error) use ($maxPoints): RateLimitResult {
                            return new RateLimitResult(
                                canContinue: true,
                                remainingPoints: $maxPoints,
                                msBeforeNext: 0,
                                totalHits: $maxPoints,
                            );
                        },
                    );
                },
            );
        }

        /** @phpstan-ignore-next-line */
        return $this->redis->eval($this->luaScript, $numKeys, ...$keys, ...$args)->then(
            function ($result) use ($maxPoints): RateLimitResult {
                return $this->parseResult($result, $maxPoints);
            },
            function (Throwable $error) use ($maxPoints): RateLimitResult {
                return new RateLimitResult(
                    canContinue: true,
                    remainingPoints: $maxPoints,
                    msBeforeNext: 0,
                    totalHits: $maxPoints,
                );
            },
        );
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
