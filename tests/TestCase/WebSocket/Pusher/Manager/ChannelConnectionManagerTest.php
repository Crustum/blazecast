<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Manager;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ChannelConnectionManager
 */
#[AllowMockObjectsWithoutExpectations]
class ChannelConnectionManagerTest extends TestCase
{
    private ChannelConnectionManager $manager;
    private Connection|MockObject $mockConnection1;
    private Connection|MockObject $mockConnection2;
    private PusherChannelInterface|MockObject $mockChannel1;
    private PusherChannelInterface|MockObject $mockChannel2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ChannelConnectionManager();

        $this->mockConnection1 = $this->createMock(Connection::class);
        $this->mockConnection1->method('getId')->willReturn('conn-1');

        $this->mockConnection2 = $this->createMock(Connection::class);
        $this->mockConnection2->method('getId')->willReturn('conn-2');

        $this->mockChannel1 = $this->createMock(PusherChannelInterface::class);
        $this->mockChannel1->method('getName')->willReturn('channel-1');

        $this->mockChannel2 = $this->createMock(PusherChannelInterface::class);
        $this->mockChannel2->method('getName')->willReturn('channel-2');
    }

    #[Test]
    public function managerCanBeCreated(): void
    {
        $manager = new ChannelConnectionManager();
        $this->assertInstanceOf(ChannelConnectionManager::class, $manager);
    }

    #[Test]
    public function managerStartsEmpty(): void
    {
        $stats = $this->manager->getStats();
        $this->assertEquals(0, $stats['total_connections']);
        $this->assertEquals(0, $stats['total_subscriptions']);
        $this->assertEmpty($this->manager->getActiveConnectionIds());
        $this->assertEmpty($this->manager->getActiveChannelNames());
    }

    #[Test]
    public function managerCanSubscribeConnectionToChannel(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);

        $stats = $this->manager->getStats();
        $this->assertEquals(1, $stats['total_connections']);
        $this->assertEquals(1, $stats['total_subscriptions']);
        $this->assertTrue($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));
    }

    #[Test]
    public function managerCanUnsubscribeConnectionFromChannel(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->assertTrue($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));

        $this->manager->unsubscribe($this->mockConnection1, $this->mockChannel1);

        $this->assertFalse($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));
        $stats = $this->manager->getStats();
        $this->assertEquals(0, $stats['total_subscriptions']);
    }

    #[Test]
    public function managerCanGetChannelsForConnection(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel2);

        $channels = $this->manager->getChannelsForConnection($this->mockConnection1);

        $this->assertCount(2, $channels);
        $this->assertArrayHasKey('channel-1', $channels);
        $this->assertArrayHasKey('channel-2', $channels);
    }

    #[Test]
    public function managerCanGetConnectionsForChannel(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection2, $this->mockChannel1);

        $connections = $this->manager->getConnectionsForChannel($this->mockChannel1);

        $this->assertCount(2, $connections);
        $this->assertArrayHasKey('conn-1', $connections);
        $this->assertArrayHasKey('conn-2', $connections);
    }

    #[Test]
    public function managerCanCheckSubscription(): void
    {
        $this->assertFalse($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));

        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);

        $this->assertTrue($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));
        $this->assertFalse($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel2));
    }

    #[Test]
    public function managerCanUnsubscribeFromAllChannels(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel2);

        $this->assertCount(2, $this->manager->getChannelsForConnection($this->mockConnection1));

        $this->manager->unsubscribeAll($this->mockConnection1);

        $this->assertEmpty($this->manager->getChannelsForConnection($this->mockConnection1));
        $this->assertFalse($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));
    }

    #[Test]
    public function managerCanGetChannelNames(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel2);

        $channelNames = $this->manager->getChannelNamesForConnection($this->mockConnection1);

        $this->assertCount(2, $channelNames);
        $this->assertContains('channel-1', $channelNames);
        $this->assertContains('channel-2', $channelNames);
    }

    #[Test]
    public function managerCanGetConnectionIds(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection2, $this->mockChannel1);

        $connectionIds = $this->manager->getConnectionIdsForChannel($this->mockChannel1);

        $this->assertCount(2, $connectionIds);
        $this->assertContains('conn-1', $connectionIds);
        $this->assertContains('conn-2', $connectionIds);
    }

    #[Test]
    public function managerCanGetConnectionCounts(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection2, $this->mockChannel1);

        $count = $this->manager->getConnectionCountForChannel($this->mockChannel1);
        $this->assertEquals(2, $count);
    }

    #[Test]
    public function managerCanGetChannelCounts(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel2);

        $count = $this->manager->getChannelCountForConnection($this->mockConnection1);
        $this->assertEquals(2, $count);
    }

    #[Test]
    public function managerCanGetActiveItems(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection2, $this->mockChannel2);

        $activeChannels = $this->manager->getActiveChannelNames();
        $activeConnections = $this->manager->getActiveConnectionIds();

        $this->assertCount(2, $activeChannels);
        $this->assertCount(2, $activeConnections);
        $this->assertContains('channel-1', $activeChannels);
        $this->assertContains('conn-1', $activeConnections);
    }

    #[Test]
    public function managerCanGetMappingInfo(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);

        $info = $this->manager->getMappingInfo();

        $this->assertArrayHasKey('connection_channels', $info);
        $this->assertArrayHasKey('channel_connections', $info);
    }

    #[Test]
    public function managerCanClearAllMappings(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection2, $this->mockChannel2);

        $stats = $this->manager->getStats();
        $this->assertEquals(2, $stats['total_connections']);

        $this->manager->clear();

        $stats = $this->manager->getStats();
        $this->assertEquals(0, $stats['total_connections']);
        $this->assertEquals(0, $stats['total_subscriptions']);
    }

    #[Test]
    public function managerHandlesNonExistentConnections(): void
    {
        $channels = $this->manager->getChannelsForConnection($this->mockConnection1);
        $this->assertEmpty($channels);

        $channelNames = $this->manager->getChannelNamesForConnection($this->mockConnection1);
        $this->assertEmpty($channelNames);

        $count = $this->manager->getChannelCountForConnection($this->mockConnection1);
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function managerHandlesNonExistentChannels(): void
    {
        $connections = $this->manager->getConnectionsForChannel($this->mockChannel1);
        $this->assertEmpty($connections);

        $connectionIds = $this->manager->getConnectionIdsForChannel($this->mockChannel1);
        $this->assertEmpty($connectionIds);

        $count = $this->manager->getConnectionCountForChannel($this->mockChannel1);
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function managerCanRemoveNonExistentMapping(): void
    {
        $this->manager->unsubscribe($this->mockConnection1, $this->mockChannel1);
        $stats = $this->manager->getStats();
        $this->assertEquals(0, $stats['total_connections']);
    }

    #[Test]
    public function managerPreventsDoubleSubscription(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);

        $channels = $this->manager->getChannelsForConnection($this->mockConnection1);
        $this->assertCount(1, $channels);

        $stats = $this->manager->getStats();
        $this->assertEquals(1, $stats['total_connections']);
        $this->assertEquals(2, $stats['total_subscriptions']);
    }
}
