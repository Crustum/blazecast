<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Publish;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisClientFactory;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

/**
 * Tests for RedisClientFactory
 */
class RedisClientFactoryTest extends TestCase
{
    private RedisClientFactory $factory;
    private LoopInterface $mockLoop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLoop = $this->createMock(LoopInterface::class);
        $this->factory = new RedisClientFactory();
    }

    /**
     * Test constructor
     */
    public function testConstructor(): void
    {
        $factory = new RedisClientFactory();

        $this->assertInstanceOf(RedisClientFactory::class, $factory);
    }

    /**
     * Test make method returns promise
     */
    public function testMakeMethodReturnsPromise(): void
    {
        $url = 'redis://127.0.0.1:6379';
        $promise = $this->factory->make($this->mockLoop, $url);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    /**
     * Test make method with different URLs
     */
    public function testMakeMethodWithDifferentUrls(): void
    {
        $urls = [
            'redis://127.0.0.1:6379',
            'redis://user:pass@redis.example.com:6380?db=1',
            'rediss://redis.example.com:6380',
        ];

        foreach ($urls as $url) {
            $promise = $this->factory->make($this->mockLoop, $url);
            $this->assertInstanceOf(PromiseInterface::class, $promise);
        }
    }
}
