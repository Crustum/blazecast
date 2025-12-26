<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Publish;

use Clue\React\Redis\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

/**
 * Factory for creating Redis client instances
 * Following Laravel Reverb's RedisClientFactory pattern
 */
class RedisClientFactory
{
    /**
     * Create a new Redis client instance
     *
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @param string $url Redis connection URL
     * @return \React\Promise\PromiseInterface<\Clue\React\Redis\Client>
     */
    public function make(LoopInterface $loop, string $url): PromiseInterface
    {
        $factory = new Factory($loop);

        /** @phpstan-ignore-next-line */
        return $factory->createClient($url);
    }
}
