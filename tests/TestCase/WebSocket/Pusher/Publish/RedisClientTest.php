<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Publish;

use Cake\TestSuite\TestCase;
use Clue\React\Redis\Client;
use Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisClient;
use React\EventLoop\Loop;
use ReflectionClass;

/**
 * Tests for RedisClient
 */
class RedisClientTest extends TestCase
{
    private RedisClient $redisClient;
    private string $testChannel = 'test:channel';
    /**
     * @var array<string, mixed>
     */
    private array $testServer = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'timeout' => 60,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $loop = Loop::get();
        $this->redisClient = new RedisClient(
            $loop,
            $this->testChannel,
            $this->testServer,
        );
    }

    /**
     * Test constructor with default configuration
     */
    public function testConstructorWithDefaults(): void
    {
        $loop = Loop::get();
        $client = new RedisClient($loop, 'test:channel');

        $this->assertInstanceOf(RedisClient::class, $client);
    }

    /**
     * Test constructor with custom server configuration
     */
    public function testConstructorWithCustomConfig(): void
    {
        $loop = Loop::get();
        $customServer = [
            'host' => 'redis.example.com',
            'port' => 6380,
            'database' => 1,
            'timeout' => 30,
        ];

        $client = new RedisClient($loop, 'test:channel', $customServer);

        $this->assertInstanceOf(RedisClient::class, $client);
    }

    /**
     * Test constructor with onConnect callback
     */
    public function testConstructorWithOnConnectCallback(): void
    {
        $loop = Loop::get();
        $onConnect = function (Client $client): void {
            // Test callback
        };

        $client = new RedisClient($loop, 'test:channel', [], $onConnect);

        $this->assertInstanceOf(RedisClient::class, $client);
    }

    /**
     * Test isConnected returns false when no client
     */
    public function testIsConnectedReturnsFalseWhenNoClient(): void
    {
        $this->assertFalse($this->redisClient->isConnected());
    }

    /**
     * Test isConnected returns true when client exists
     */
    public function testIsConnectedReturnsTrueWhenClientExists(): void
    {
        $mockClient = $this->createStub(Client::class);

        // Use reflection to set the protected client property
        $reflection = new ReflectionClass($this->redisClient);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($this->redisClient, $mockClient);

        $this->assertTrue($this->redisClient->isConnected());
    }

    /**
     * Test disconnect sets shouldRetry to false
     */
    public function testDisconnectSetsShouldRetryToFalse(): void
    {
        $mockClient = $this->createStub(Client::class);

        // Use reflection to set the protected client property
        $reflection = new ReflectionClass($this->redisClient);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($this->redisClient, $mockClient);

        $this->redisClient->disconnect();

        // Check that shouldRetry is set to false
        $shouldRetryProperty = $reflection->getProperty('shouldRetry');
        $this->assertFalse($shouldRetryProperty->getValue($this->redisClient));
    }

    /**
     * Test redisUrl generates correct URL with default config
     */
    public function testRedisUrlGeneratesCorrectUrlWithDefaultConfig(): void
    {
        $loop = Loop::get();
        $client = new RedisClient($loop, 'test:channel');

        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('redisUrl');

        $url = $method->invoke($client);

        $this->assertEquals('redis://127.0.0.1:6379', $url);
    }

    /**
     * Test redisUrl generates correct URL with custom config
     */
    public function testRedisUrlGeneratesCorrectUrlWithCustomConfig(): void
    {
        $loop = Loop::get();
        $customServer = [
            'host' => 'redis.example.com',
            'port' => 6380,
            'database' => 1,
            'username' => 'testuser',
            'password' => 'testpass',
        ];

        $client = new RedisClient($loop, 'test:channel', $customServer);

        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('redisUrl');

        $url = $method->invoke($client);

        $expectedUrl = 'redis://redis.example.com:6380?username=testuser&password=testpass&db=1';
        $this->assertEquals($expectedUrl, $url);
    }

    /**
     * Test redisUrl handles TLS configuration
     */
    public function testRedisUrlHandlesTlsConfiguration(): void
    {
        $loop = Loop::get();
        $tlsServer = [
            'host' => 'redis.example.com',
            'port' => 6380,
            'scheme' => 'tls',
        ];

        $client = new RedisClient($loop, 'test:channel', $tlsServer);

        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('redisUrl');

        $url = $method->invoke($client);

        $this->assertEquals('rediss://redis.example.com:6380', $url);
    }

    /**
     * Test parseUrl handles valid Redis URL
     */
    public function testParseUrlHandlesValidRedisUrl(): void
    {
        $url = 'redis://user:pass@redis.example.com:6380?db=1';

        $reflection = new ReflectionClass($this->redisClient);
        $method = $reflection->getMethod('parseUrl');

        $config = $method->invoke($this->redisClient, $url);

        $expected = [
            'host' => 'redis.example.com',
            'port' => 6380,
            'username' => 'user',
            'password' => 'pass',
            'database' => 1,
        ];

        $this->assertEquals($expected, $config);
    }

    /**
     * Test retryTimeout returns configured timeout
     */
    public function testRetryTimeoutReturnsConfiguredTimeout(): void
    {
        $loop = Loop::get();
        $customServer = ['timeout' => 30];
        $client = new RedisClient($loop, 'test:channel', $customServer);

        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('retryTimeout');

        $timeout = $method->invoke($client);

        $this->assertEquals(30, $timeout);
    }

    /**
     * Test retryTimeout returns default timeout when not configured
     */
    public function testRetryTimeoutReturnsDefaultTimeoutWhenNotConfigured(): void
    {
        $reflection = new ReflectionClass($this->redisClient);
        $method = $reflection->getMethod('retryTimeout');

        $timeout = $method->invoke($this->redisClient);

        $this->assertEquals(60, $timeout);
    }
}
