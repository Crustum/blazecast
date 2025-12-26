<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket;

use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\ApplicationContextResolver;
use Crustum\BlazeCast\WebSocket\ChannelOperationsManager;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\ConnectionRegistry;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager as PusherChannelManager;

/**
 * ChannelOperationsManagerTest
 */
class ChannelOperationsManagerTest extends TestCase
{
    /**
     * @var ChannelOperationsManager
     */
    private ChannelOperationsManager $channelManager;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&ApplicationManager
     */
    private ApplicationManager $applicationManager;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&ConnectionRegistry
     */
    private ConnectionRegistry $connectionRegistry;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&ChannelConnectionManager
     */
    private ChannelConnectionManager $connectionManager;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&EventManager
     */
    private EventManager $eventManager;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&ApplicationContextResolver
     */
    private ApplicationContextResolver $contextResolver;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->applicationManager = $this->createMock(ApplicationManager::class);
        $this->connectionRegistry = $this->createMock(ConnectionRegistry::class);
        $this->connectionManager = $this->createMock(ChannelConnectionManager::class);
        $this->eventManager = $this->createMock(EventManager::class);
        $this->contextResolver = $this->createMock(ApplicationContextResolver::class);

        $this->channelManager = new ChannelOperationsManager(
            $this->applicationManager,
            $this->connectionRegistry,
            $this->connectionManager,
            $this->eventManager,
            $this->contextResolver,
        );
    }

    /**
     * Test broadcast to all connections
     *
     * @return void
     */
    public function testBroadcastToAllConnections(): void
    {
        $connection1 = $this->createMock(Connection::class);
        $connection1->method('getId')->willReturn('conn-1');

        $connection2 = $this->createMock(Connection::class);
        $connection2->method('getId')->willReturn('conn-2');

        $connections = [
            'conn-1' => $connection1,
            'conn-2' => $connection2,
        ];

        $this->connectionRegistry
            ->method('getConnections')
            ->willReturn($connections);

        $connection1->expects($this->once())->method('send')->with('test message');
        $connection2->expects($this->once())->method('send')->with('test message');

        $this->channelManager->broadcast('test message');
    }

    /**
     * Test broadcast with exception
     *
     * @return void
     */
    public function testBroadcastWithException(): void
    {
        $connection1 = $this->createMock(Connection::class);
        $connection1->method('getId')->willReturn('conn-1');

        $connection2 = $this->createMock(Connection::class);
        $connection2->method('getId')->willReturn('conn-2');

        $exceptConnection = $this->createMock(Connection::class);
        $exceptConnection->method('getId')->willReturn('conn-1');

        $connections = [
            'conn-1' => $connection1,
            'conn-2' => $connection2,
        ];

        $this->connectionRegistry
            ->method('getConnections')
            ->willReturn($connections);

        $this->connectionRegistry
            ->method('getConnection')
            ->with('conn-1')
            ->willReturn($exceptConnection);

        $connection1->expects($this->never())->method('send');
        $connection2->expects($this->once())->method('send')->with('test message');

        $this->channelManager->broadcast('test message', 'conn-1');
    }

    /**
     * Test broadcastToChannelForApp
     *
     * @return void
     */
    public function testBroadcastToChannelForApp(): void
    {
        $channelManager = $this->createMock(PusherChannelManager::class);
        $channel = $this->createMock(PusherChannel::class);

        $application = [
            'id' => 'app-123',
            'channel_manager' => $channelManager,
        ];

        $this->applicationManager
            ->method('getApplication')
            ->with('app-123')
            ->willReturn($application);

        $channelManager
            ->method('getChannel')
            ->with('test-channel')
            ->willReturn($channel);

        $channel->method('getConnectionCount')->willReturn(5);
        $channel->expects($this->once())->method('broadcast');

        $this->channelManager->broadcastToChannelForApp('app-123', 'test-channel', 'test message');
    }

    /**
     * Test getChannelConnections
     *
     * @return void
     */
    public function testGetChannelConnections(): void
    {
        $connection1 = $this->createMock(Connection::class);
        $connection1->method('getId')->willReturn('conn-1');

        $connection2 = $this->createMock(Connection::class);
        $connection2->method('getId')->willReturn('conn-2');

        $channelManager = $this->createMock(PusherChannelManager::class);
        $channel = $this->createMock(PusherChannel::class);

        $application = [
            'id' => 'app-123',
            'channel_manager' => $channelManager,
        ];

        $this->applicationManager
            ->method('getApplications')
            ->willReturn(['app-123' => $application]);

        $channelManager
            ->method('getChannel')
            ->with('test-channel')
            ->willReturn($channel);

        $channel
            ->method('getConnections')
            ->willReturn([$connection1, $connection2]);

        $result = $this->channelManager->getChannelConnections('test-channel');

        $this->assertCount(2, $result);
        $this->assertContains($connection1, $result);
        $this->assertContains($connection2, $result);
    }

    /**
     * Test getTotalConnectionCount
     *
     * @return void
     */
    public function testGetTotalConnectionCount(): void
    {
        $this->connectionRegistry
            ->method('getConnectionCount')
            ->willReturn(10);

        $result = $this->channelManager->getTotalConnectionCount();

        $this->assertEquals(10, $result);
    }

    /**
     * Test getConnectionCountForApp
     *
     * @return void
     */
    public function testGetConnectionCountForApp(): void
    {
        $connection1 = $this->createMock(Connection::class);
        $connection1->method('getId')->willReturn('conn-1');

        $connection2 = $this->createMock(Connection::class);
        $connection2->method('getId')->willReturn('conn-2');

        $connections = [
            'conn-1' => $connection1,
            'conn-2' => $connection2,
        ];

        $this->connectionRegistry
            ->method('getConnections')
            ->willReturn($connections);

        $this->connectionRegistry
            ->method('getConnectionInfo')
            ->willReturnMap([
                ['conn-1', ['app_context' => ['app_id' => 'app-123']]],
                ['conn-2', ['app_context' => ['app_id' => 'app-456']]],
            ]);

        $result = $this->channelManager->getConnectionCountForApp('app-123');

        $this->assertEquals(1, $result);
    }

    /**
     * Test broadcastToChannelsForApp
     *
     * @return void
     */
    public function testBroadcastToChannelsForApp(): void
    {
        $channelManager = $this->createMock(PusherChannelManager::class);
        $channel1 = $this->createMock(PusherChannel::class);
        $channel2 = $this->createMock(PusherChannel::class);

        $application = [
            'id' => 'app-123',
            'channel_manager' => $channelManager,
        ];

        $this->applicationManager
            ->method('getApplication')
            ->with('app-123')
            ->willReturn($application);

        $channelManager
            ->method('getChannel')
            ->willReturnMap([
                ['channel-1', $channel1],
                ['channel-2', $channel2],
            ]);

        $channel1->method('getConnectionCount')->willReturn(3);
        $channel2->method('getConnectionCount')->willReturn(2);

        $channel1->expects($this->once())->method('broadcast');
        $channel2->expects($this->once())->method('broadcast');

        $this->channelManager->broadcastToChannelsForApp('app-123', ['channel-1', 'channel-2'], 'test message');
    }

    /**
     * Test getApplicationManager
     *
     * @return void
     */
    public function testGetApplicationManager(): void
    {
        $result = $this->channelManager->getApplicationManager();

        $this->assertSame($this->applicationManager, $result);
    }

    /**
     * Test getConnectionRegistry
     *
     * @return void
     */
    public function testGetConnectionRegistry(): void
    {
        $result = $this->channelManager->getConnectionRegistry();

        $this->assertSame($this->connectionRegistry, $result);
    }
}
