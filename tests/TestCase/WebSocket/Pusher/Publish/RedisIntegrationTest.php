<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Publish;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPubSubProvider;

/**
 * Integration tests for Redis implementation
 */
class RedisIntegrationTest extends TestCase
{
    private string $testChannel = 'test:integration';
    /**
     * @var array<string, mixed>
     */
    private array $testServer = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
    ];

    /**
     * Test basic provider creation
     */
    public function testBasicProviderCreation(): void
    {
        $provider = new RedisPubSubProvider($this->testChannel, $this->testServer);

        $this->assertInstanceOf(RedisPubSubProvider::class, $provider);
    }

    /**
     * Test provider with message handler
     */
    public function testProviderWithMessageHandler(): void
    {
        $messageReceived = false;
        $messageHandler = function (string $message) use (&$messageReceived): void {
            $messageReceived = true;
        };

        $provider = new RedisPubSubProvider($this->testChannel, $this->testServer, $messageHandler);

        $this->assertInstanceOf(RedisPubSubProvider::class, $provider);
    }

    /**
     * Test multiple providers
     */
    public function testMultipleProviders(): void
    {
        $provider1 = new RedisPubSubProvider('channel1', $this->testServer);
        $provider2 = new RedisPubSubProvider('channel2', $this->testServer);

        $this->assertInstanceOf(RedisPubSubProvider::class, $provider1);
        $this->assertInstanceOf(RedisPubSubProvider::class, $provider2);

        // Verify both providers were created successfully
        $this->assertNotSame($provider1, $provider2);
    }

    /**
     * Test provider with custom server configuration
     */
    public function testProviderWithCustomServerConfiguration(): void
    {
        $customServer = [
            'host' => 'redis.example.com',
            'port' => 6380,
            'database' => 1,
            'username' => 'testuser',
            'password' => 'testpass',
        ];

        $provider = new RedisPubSubProvider($this->testChannel, $customServer);

        $this->assertInstanceOf(RedisPubSubProvider::class, $provider);
    }
}
