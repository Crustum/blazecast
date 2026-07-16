<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\PusherRouter;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionLimitExceeded;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use Crustum\BlazeCast\WebSocket\RateLimiter\ConnectionMessageRateLimiter;
use React\EventLoop\LoopInterface;
use ReflectionClass;

/**
 * ServerTest
 *
 * TDD tests for Server refactoring
 */
class ServerTest extends TestCase
{
    protected PusherRouter $router;
    protected ChannelManager $channelManager;
    protected ChannelConnectionManager $connectionManager;
    protected ApplicationManager $applicationManager;
    protected LoopInterface $loop;

    public function setUp(): void
    {
        parent::setUp();

        $this->router = $this->createStub(PusherRouter::class);
        $this->channelManager = new ChannelManager();
        $this->connectionManager = new ChannelConnectionManager();
        $this->applicationManager = new ApplicationManager([
            'applications' => [
                [
                    'id' => '1',
                    'key' => 'test-key',
                    'secret' => 'test-secret',
                    'name' => 'Test App',
                ],
            ],
        ]);

        $this->loop = $this->createStub(LoopInterface::class);
    }

    /**
     * Test that Server can be instantiated with mocked loop
     */
    public function testCanInstantiateServerWithMockedLoop(): void
    {
        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            38081,
            ['app_id' => '1', 'app_key' => 'test-key', 'app_secret' => 'test-secret'],
            $this->loop,
        );

        $this->assertInstanceOf(Server::class, $server);
    }

    /**
     * Test that server has HTTP router
     */
    public function testServerHasHttpRouter(): void
    {
        $this->markTestSkipped('This test disabled because of port conflicts in non threadsafe mode');
        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            38081,
            ['app_id' => '1', 'app_key' => 'test-key', 'app_secret' => 'test-secret'],
            $this->loop,
        );

        $this->assertSame($this->router, $server->getHttpRouter());
    }

    /**
     * Test that server can set new HTTP router
     */
    public function testServerCanSetHttpRouter(): void
    {
        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            ['app_id' => '1', 'app_key' => 'test-key', 'app_secret' => 'test-secret', 'test_mode' => true],
            $this->loop,
        );

        $newRouter = $this->createStub(PusherRouter::class);
        $server->setHttpRouter($newRouter);

        $this->assertSame($newRouter, $server->getHttpRouter());
    }

    /**
     * Test that server can subscribe connection to Pusher channel
     */
    public function testCanSubscribeToPusherChannel(): void
    {
        $this->markTestSkipped('This test disabled because of port conflicts in non threadsafe mode');
        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            ['app_id' => '1', 'app_key' => 'test-key', 'app_secret' => 'test-secret', 'test_mode' => true],
            $this->loop,
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('getId')->willReturn('test-connection-1');

        $server->subscribeToPusherChannel($connection, 'test-channel');

        $channel = $server->getChannelManager()->getChannel('test-channel');
        $this->assertTrue($channel->isSubscribed($connection), 'Connection should be subscribed to the channel');
    }

    /**
     * Test that ChannelManager can be used independently
     */
    public function testChannelManagerWorks(): void
    {
        $channel = $this->channelManager->getChannel('test-channel');
        $this->assertSame('test-channel', $channel->getName());
        $this->assertTrue($this->channelManager->hasChannel('test-channel'));
    }

    /**
     * Test that ApplicationManager can be used independently
     */
    public function testApplicationManagerWorks(): void
    {
        $app = $this->applicationManager->getApplication('1');
        $this->assertIsArray($app);
        $this->assertEquals('1', $app['id']);
        $this->assertEquals('test-key', $app['key']);
    }

    /**
     * Test that ChannelConnectionManager can be used independently
     */
    public function testChannelConnectionManagerWorks(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getId')->willReturn('test-connection-1');

        $channel = $this->createMock(PusherChannelInterface::class);
        $channel->method('getName')->willReturn('test-channel');

        $this->connectionManager->subscribe($connection, $channel);
        $this->assertTrue($this->connectionManager->isSubscribed($connection, $channel));

        $channels = $this->connectionManager->getChannelsForConnection($connection);
        $this->assertCount(1, $channels);
        $this->assertArrayHasKey('test-channel', $channels);

        $connections = $this->connectionManager->getConnectionsForChannel($channel);
        $this->assertCount(1, $connections);
        $this->assertArrayHasKey('test-connection-1', $connections);

        $this->connectionManager->unsubscribe($connection, $channel);
        $this->assertFalse($this->connectionManager->isSubscribed($connection, $channel));
    }

    /**
     * Test that connection is rejected when app is over connection limit
     *
     * @return void
     */
    public function testConnectionRejectedWhenOverLimit(): void
    {
        $this->applicationManager = new ApplicationManager([
            'applications' => [
                [
                    'id' => '1',
                    'key' => 'test-key',
                    'secret' => 'test-secret',
                    'name' => 'Test App',
                    'max_connections' => 1,
                ],
            ],
        ]);

        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            ['app_id' => '1', 'app_key' => 'test-key', 'app_secret' => 'test-secret', 'test_mode' => true],
            $this->loop,
        );

        $connection1 = $this->createMock(Connection::class);
        $connection1->method('getId')->willReturn('connection-1');
        $connection1->method('getAttribute')->willReturn('1');

        $reflection = new ReflectionClass($this->connectionManager);
        $appConnectionsProperty = $reflection->getProperty('appConnections');
        $appConnectionsProperty->setValue($this->connectionManager, [
            '1' => [
                'connection-1' => [],
            ],
        ]);

        $this->expectException(ConnectionLimitExceeded::class);

        $serverReflection = new ReflectionClass($server);
        $method = $serverReflection->getMethod('ensureWithinConnectionLimit');
        $method->invoke($server, '1');
    }

    /**
     * Test that connection is accepted when under limit
     *
     * @return void
     */
    public function testConnectionAcceptedWhenUnderLimit(): void
    {
        $this->applicationManager = new ApplicationManager([
            'applications' => [
                [
                    'id' => '1',
                    'key' => 'test-key',
                    'secret' => 'test-secret',
                    'name' => 'Test App',
                    'max_connections' => 2,
                ],
            ],
        ]);

        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            ['app_id' => '1', 'app_key' => 'test-key', 'app_secret' => 'test-secret', 'test_mode' => true],
            $this->loop,
        );

        $reflection = new ReflectionClass($server);
        $method = $reflection->getMethod('ensureWithinConnectionLimit');

        $currentConnections = $this->connectionManager->getConnectionsForApp('1');
        $connectionCount = count($currentConnections);
        $maxConnections = 2;

        $this->assertLessThan($maxConnections, $connectionCount, 'Connection count should be under the limit before calling ensureWithinConnectionLimit');

        $method->invoke($server, '1');

        $this->assertCount($connectionCount, $this->connectionManager->getConnectionsForApp('1'), 'Connection count should remain unchanged after ensureWithinConnectionLimit when under limit');
    }

    /**
     * Test that connection is accepted when no limit is set
     *
     * @return void
     */
    public function testConnectionAcceptedWhenNoLimit(): void
    {
        $this->applicationManager = new ApplicationManager([
            'applications' => [
                [
                    'id' => '1',
                    'key' => 'test-key',
                    'secret' => 'test-secret',
                    'name' => 'Test App',
                ],
            ],
        ]);

        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            ['app_id' => '1', 'app_key' => 'test-key', 'app_secret' => 'test-secret', 'test_mode' => true],
            $this->loop,
        );

        $reflection = new ReflectionClass($server);
        $method = $reflection->getMethod('ensureWithinConnectionLimit');

        $application = $this->applicationManager->getApplication('1');
        $this->assertNotNull($application, 'Application should exist');

        $maxConnections = $application['max_connections'] ?? null;
        $this->assertNull($maxConnections, 'max_connections should be null (unlimited) by default');

        $currentConnections = $this->connectionManager->getConnectionsForApp('1');
        $connectionCountBefore = count($currentConnections);

        $method->invoke($server, '1');

        $this->assertCount($connectionCountBefore, $this->connectionManager->getConnectionsForApp('1'), 'Connection count should remain unchanged after ensureWithinConnectionLimit when no limit is set (null)');
    }

    /**
     * Welcome activity_timeout prefers per-application config.
     *
     * @return void
     */
    public function testResolveActivityTimeoutPrefersApplicationConfig(): void
    {
        $this->applicationManager = new ApplicationManager([
            'applications' => [
                [
                    'id' => '1',
                    'key' => 'test-key',
                    'secret' => 'test-secret',
                    'name' => 'Test App',
                    'activity_timeout' => 45,
                ],
            ],
        ]);

        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            [
                'test_mode' => true,
                'servers' => [
                    'blazecast' => [
                        'activity_timeout' => 120,
                    ],
                ],
            ],
            $this->loop,
        );

        $connection = $this->createStub(Connection::class);
        $connection->method('getAttribute')->willReturnMap([
            ['app_id', '1'],
        ]);

        $method = (new ReflectionClass($server))->getMethod('resolveActivityTimeout');

        $this->assertSame(45, $method->invoke($server, $connection));
    }

    /**
     * Welcome activity_timeout falls back to server config when app omits it.
     *
     * @return void
     */
    public function testResolveActivityTimeoutFallsBackToServerConfig(): void
    {
        $this->applicationManager = new ApplicationManager([
            'applications' => [
                [
                    'id' => '1',
                    'key' => 'test-key',
                    'secret' => 'test-secret',
                    'name' => 'Test App',
                ],
            ],
        ]);

        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            [
                'test_mode' => true,
                'servers' => [
                    'blazecast' => [
                        'activity_timeout' => 90,
                    ],
                ],
            ],
            $this->loop,
        );

        $connection = $this->createStub(Connection::class);
        $connection->method('getAttribute')->willReturnMap([
            ['app_id', '1'],
        ]);

        $method = (new ReflectionClass($server))->getMethod('resolveActivityTimeout');

        $this->assertSame(90, $method->invoke($server, $connection));
    }

    /**
     * Welcome activity_timeout defaults to 120 when neither app nor server sets it.
     *
     * @return void
     */
    public function testResolveActivityTimeoutDefaultsToOneHundredTwenty(): void
    {
        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            ['test_mode' => true],
            $this->loop,
        );

        $connection = $this->createStub(Connection::class);
        $connection->method('getAttribute')->willReturn(null);

        $method = (new ReflectionClass($server))->getMethod('resolveActivityTimeout');

        $this->assertSame(120, $method->invoke($server, $connection));
    }

    /**
     * Connection message rate limit sends error and keeps connection when terminate is off.
     *
     * @return void
     */
    public function testEnsureWithinConnectionMessageRateLimitRejectsWithoutTerminate(): void
    {
        $limiter = new ConnectionMessageRateLimiter(1, false);
        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            ['test_mode' => true],
            $this->loop,
            null,
            null,
            $limiter,
        );

        $sent = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('getId')->willReturn('conn-rate-1');
        $connection->method('getAttribute')->willReturn('1');
        $connection->expects($this->once())->method('send')->willReturnCallback(function (string $payload) use (&$sent): void {
            $sent[] = $payload;
        });
        $connection->expects($this->never())->method('close');

        $method = (new ReflectionClass($server))->getMethod('ensureWithinConnectionMessageRateLimit');

        $this->assertTrue($method->invoke($server, $connection));
        $this->assertFalse($method->invoke($server, $connection));
        $this->assertCount(1, $sent);

        $decoded = json_decode($sent[0], true);
        $this->assertSame('pusher:error', $decoded['event']);
        $data = json_decode($decoded['data'], true);
        $this->assertSame(4200, $data['code']);
        $this->assertSame('Rate limit exceeded', $data['message']);
    }

    /**
     * Connection message rate limit closes the connection when terminate_on_limit is enabled.
     *
     * @return void
     */
    public function testEnsureWithinConnectionMessageRateLimitTerminatesWhenConfigured(): void
    {
        $limiter = new ConnectionMessageRateLimiter(1, true);
        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            ['test_mode' => true],
            $this->loop,
            null,
            null,
            $limiter,
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('getId')->willReturn('conn-rate-2');
        $connection->method('getAttribute')->willReturn('1');
        $connection->expects($this->once())->method('send');
        $connection->expects($this->once())->method('close');

        $method = (new ReflectionClass($server))->getMethod('ensureWithinConnectionMessageRateLimit');

        $this->assertTrue($method->invoke($server, $connection));
        $this->assertFalse($method->invoke($server, $connection));
    }

    /**
     * Without a connection message rate limiter, messages are always allowed.
     *
     * @return void
     */
    public function testEnsureWithinConnectionMessageRateLimitAllowsWhenDisabled(): void
    {
        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            8080,
            ['test_mode' => true],
            $this->loop,
        );

        $connection = $this->createStub(Connection::class);
        $connection->method('getId')->willReturn('conn-rate-3');

        $method = (new ReflectionClass($server))->getMethod('ensureWithinConnectionMessageRateLimit');

        $this->assertTrue($method->invoke($server, $connection));
        $this->assertTrue($method->invoke($server, $connection));
    }

    /**
     * Test that ConnectionLimitExceeded exception has correct error code
     *
     * @return void
     */
    public function testConnectionLimitExceededHasCorrectErrorCode(): void
    {
        $exception = new ConnectionLimitExceeded();

        $this->assertEquals(4004, $exception->getCode());
        $this->assertEquals('Application is over connection quota', $exception->getMessage());
    }
}
