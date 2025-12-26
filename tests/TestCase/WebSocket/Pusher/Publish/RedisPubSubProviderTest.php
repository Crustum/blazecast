<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Publish;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPubSubProvider;

/**
 * Tests for RedisPubSubProvider
 */
class RedisPubSubProviderTest extends TestCase
{
    private RedisPubSubProvider $provider;
    private string $testChannel = 'test:channel';
    /**
     * @var array<string, mixed>
     */
    private array $testServer = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new RedisPubSubProvider(
            $this->testChannel,
            $this->testServer,
        );
    }

    /**
     * Test constructor with default configuration
     */
    public function testConstructorWithDefaults(): void
    {
        $provider = new RedisPubSubProvider('test:channel');

        $this->assertInstanceOf(RedisPubSubProvider::class, $provider);
    }

    /**
     * Test constructor with custom server configuration
     */
    public function testConstructorWithCustomConfig(): void
    {
        $customServer = [
            'host' => 'redis.example.com',
            'port' => 6380,
            'database' => 1,
        ];

        $provider = new RedisPubSubProvider('test:channel', $customServer);

        $this->assertInstanceOf(RedisPubSubProvider::class, $provider);
    }

    /**
     * Test constructor with message handler
     */
    public function testConstructorWithMessageHandler(): void
    {
        $messageHandler = function (string $message): void {
            // Test callback
        };

        $provider = new RedisPubSubProvider('test:channel', [], $messageHandler);

        $this->assertInstanceOf(RedisPubSubProvider::class, $provider);
    }

    /**
     * Test unsubscribeFromChannel method
     */
    public function testUnsubscribeFromChannelMethod(): void
    {
        // Should not throw any exceptions
        $this->provider->unsubscribeFromChannel('test:channel');

        // Verify method executed without exceptions
        $this->assertInstanceOf(RedisPubSubProvider::class, $this->provider);
    }
}
