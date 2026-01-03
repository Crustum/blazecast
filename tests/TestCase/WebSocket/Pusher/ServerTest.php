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
        $this->assertNull($maxConnections, 'max_connections should be null (unlimited) by default, matching Reverb behavior');

        $currentConnections = $this->connectionManager->getConnectionsForApp('1');
        $connectionCountBefore = count($currentConnections);

        $method->invoke($server, '1');

        $this->assertCount($connectionCountBefore, $this->connectionManager->getConnectionsForApp('1'), 'Connection count should remain unchanged after ensureWithinConnectionLimit when no limit is set (null)');
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
