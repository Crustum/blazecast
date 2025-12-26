<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket;

use Cake\Core\Configure;
use Clue\React\Redis\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

/**
 * Factory for Redis clients (Legacy - use Redis\ClientFactory instead)
 *
 * @deprecated Use BlazeCast\WebSocket\Redis\ClientFactory instead
 * @phpstan-type RedisConfig array{
 *   host?: string,
 *   port?: int,
 *   password?: string,
 *   database?: int
 * }
 */
class ClientFactory
{
    /**
     * Create a Redis client
     *
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @param RedisConfig|null $config Redis configuration
     * @return \React\Promise\PromiseInterface<\Clue\React\Redis\Client>
     */
    public function create(LoopInterface $loop, ?array $config = null): PromiseInterface
    {
        if ($config === null) {
            $config = Configure::read('BlazeCast.redis');
        }

        $factory = new Factory($loop);

        $uri = $this->buildRedisUrl($config);

        /** @phpstan-ignore-next-line */
        return $factory->createClient($uri);
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
