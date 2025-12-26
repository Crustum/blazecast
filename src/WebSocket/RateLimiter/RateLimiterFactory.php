<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\RateLimiter;

use InvalidArgumentException;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

/**
 * Rate Limiter Factory
 *
 * Creates rate limiter instances based on driver configuration.
 * Supports local, redis, and cluster drivers.
 */
class RateLimiterFactory
{
    /**
     * Create a rate limiter instance
     *
     * @param string $driver Driver name (local, redis, async_redis)
     * @param array<string, mixed> $config Configuration for the driver
     * @param \React\EventLoop\LoopInterface|null $loop Event loop (required for async_redis)
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|\Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface Rate limiter instance
     * @throws \InvalidArgumentException If driver is not supported
     */
    public static function create(string $driver, array $config = [], ?LoopInterface $loop = null): RateLimiterInterface|AsyncRateLimiterInterface
    {
        return match ($driver) {
            'local' => new LocalRateLimiter($config['app_configs'] ?? []),
            'redis' => new RedisRateLimiter($config['app_configs'] ?? [], $config['redis'] ?? []),
            'async_redis' => new AsyncRedisRateLimiter($loop ?? Loop::get(), $config['app_configs'] ?? [], $config['redis'] ?? []),
            default => throw new InvalidArgumentException("Unknown rate limiter driver: {$driver}")
        };
    }

    /**
     * Create an async rate limiter instance
     *
     * @param string $driver Driver name (async_redis)
     * @param array<string, mixed> $config Configuration for the driver
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface Async rate limiter instance
     * @throws \InvalidArgumentException If driver is not supported
     */
    public static function createAsync(string $driver, array $config, LoopInterface $loop): AsyncRateLimiterInterface
    {
        return match ($driver) {
            'async_redis' => new AsyncRedisRateLimiter($loop, $config['app_configs'] ?? [], $config['redis'] ?? []),
            default => throw new InvalidArgumentException("Unknown async rate limiter driver: {$driver}")
        };
    }

    /**
     * Check if a driver is supported
     *
     * @param string $driver Driver name
     * @return bool True if driver is supported
     */
    public static function isSupported(string $driver): bool
    {
        return in_array($driver, ['local', 'redis', 'async_redis'], true);
    }

    /**
     * Get list of supported drivers
     *
     * @return array<int, string> List of supported driver names
     */
    public static function getSupportedDrivers(): array
    {
        return ['local', 'redis', 'async_redis'];
    }
}
