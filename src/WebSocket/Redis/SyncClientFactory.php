<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Redis;

use Cake\Core\Configure;
use Redis;

/**
 * Factory for creating synchronous Redis clients
 *
 * @phpstan-import-type RedisConfig from \Crustum\BlazeCast\WebSocket\Redis\ClientFactory
 */
class SyncClientFactory
{
    /**
     * Create a new synchronous Redis client
     *
     * @param RedisConfig|null $config Redis configuration
     * @return \Redis
     */
    public function create(?array $config = null): Redis
    {
        if ($config === null) {
            $config = Configure::read('BlazeCast.redis');
        }

        return $this->createRedisConnection($config);
    }

    /**
     * Create a Redis client for testing
     *
     * @param RedisConfig|null $config Redis configuration override
     * @return \Redis
     */
    public function createForTesting(?array $config = null): Redis
    {
        if ($config === null) {
            $config = Configure::read('BlazeCast.redis_test');
        }

        return $this->createRedisConnection($config);
    }

    /**
     * Create Redis connection
     *
     * @param RedisConfig $config Redis configuration
     * @return \Redis
     */
    protected function createRedisConnection(array $config): Redis
    {
        $redis = new Redis();

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $database = $config['database'] ?? 0;

        $redis->connect($host, $port);

        if (!empty($config['password'])) {
            $redis->auth($config['password']);
        }

        if ($database > 0) {
            $redis->select($database);
        }

        return $redis;
    }
}
