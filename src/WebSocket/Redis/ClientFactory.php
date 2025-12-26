<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Redis;

use Cake\Core\Configure;
use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use React\EventLoop\LoopInterface;

/**
 * Factory for creating Redis clients
 *
 * @phpstan-type RedisConfig array{
 *   host?: string,
 *   port?: int,
 *   database?: int,
 *   driver?: string,
 *   scheme?: string,
 *   username?: string,
 *   password?: string,
 *   timeout?: float,
 * }
 */
class ClientFactory
{
    /**
     * Create a new Redis client.
     *
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @param RedisConfig|null $config Redis configuration
     * @return \Clue\React\Redis\Client
     */
    public function create(LoopInterface $loop, ?array $config = null): Client
    {
        if ($config === null) {
            $config = Configure::read('BlazeCast.redis');
        }

        $redisUrl = $this->buildRedisUrl($config);
        $factory = new Factory($loop);

        return $factory->createLazyClient($redisUrl);
    }

    /**
     * Build Redis URL from configuration
     *
     * @param RedisConfig $config Redis configuration
     * @return string
     */
    protected function buildRedisUrl(array $config): string
    {
        $auth = '';
        if (!empty($config['password'])) {
            $auth = ":{$config['password']}@";
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $database = $config['database'] ?? 0;

        return "redis://{$auth}{$host}:{$port}/{$database}";
    }
}
