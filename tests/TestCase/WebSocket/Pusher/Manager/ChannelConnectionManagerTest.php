<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Manager;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ChannelConnectionManager
 */
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

    public function testManagerCanBeCreated(): void
    {
        $manager = new ChannelConnectionManager();
        $this->assertInstanceOf(ChannelConnectionManager::class, $manager);
    }

    public function testManagerStartsEmpty(): void
    {
        $stats = $this->manager->getStats();
        $this->assertEquals(0, $stats['total_connections']);
        $this->assertEquals(0, $stats['total_subscriptions']);
        $this->assertEmpty($this->manager->getActiveConnectionIds());
        $this->assertEmpty($this->manager->getActiveChannelNames());
    }

    public function testManagerCanSubscribeConnectionToChannel(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);

        $stats = $this->manager->getStats();
        $this->assertEquals(1, $stats['total_connections']);
        $this->assertEquals(1, $stats['total_subscriptions']);
        $this->assertTrue($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));
    }

    public function testManagerCanUnsubscribeConnectionFromChannel(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->assertTrue($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));

        $this->manager->unsubscribe($this->mockConnection1, $this->mockChannel1);

        $this->assertFalse($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));
        $stats = $this->manager->getStats();
        $this->assertEquals(0, $stats['total_subscriptions']);
    }

    public function testManagerCanGetChannelsForConnection(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel2);

        $channels = $this->manager->getChannelsForConnection($this->mockConnection1);

        $this->assertCount(2, $channels);
        $this->assertArrayHasKey('channel-1', $channels);
        $this->assertArrayHasKey('channel-2', $channels);
    }

    public function testManagerCanGetConnectionsForChannel(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection2, $this->mockChannel1);

        $connections = $this->manager->getConnectionsForChannel($this->mockChannel1);

        $this->assertCount(2, $connections);
        $this->assertArrayHasKey('conn-1', $connections);
        $this->assertArrayHasKey('conn-2', $connections);
    }

    public function testManagerCanCheckSubscription(): void
    {
        $this->assertFalse($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));

        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);

        $this->assertTrue($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));
        $this->assertFalse($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel2));
    }

    public function testManagerCanUnsubscribeFromAllChannels(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel2);

        $this->assertCount(2, $this->manager->getChannelsForConnection($this->mockConnection1));

        $this->manager->unsubscribeAll($this->mockConnection1);

        $this->assertEmpty($this->manager->getChannelsForConnection($this->mockConnection1));
        $this->assertFalse($this->manager->isSubscribed($this->mockConnection1, $this->mockChannel1));
    }

    public function testManagerCanGetChannelNames(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel2);

        $channelNames = $this->manager->getChannelNamesForConnection($this->mockConnection1);

        $this->assertCount(2, $channelNames);
        $this->assertContains('channel-1', $channelNames);
        $this->assertContains('channel-2', $channelNames);
    }

    public function testManagerCanGetConnectionIds(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection2, $this->mockChannel1);

        $connectionIds = $this->manager->getConnectionIdsForChannel($this->mockChannel1);

        $this->assertCount(2, $connectionIds);
        $this->assertContains('conn-1', $connectionIds);
        $this->assertContains('conn-2', $connectionIds);
    }

    public function testManagerCanGetConnectionCounts(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection2, $this->mockChannel1);

        $count = $this->manager->getConnectionCountForChannel($this->mockChannel1);
        $this->assertEquals(2, $count);
    }

    public function testManagerCanGetChannelCounts(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel2);

        $count = $this->manager->getChannelCountForConnection($this->mockConnection1);
        $this->assertEquals(2, $count);
    }

    public function testManagerCanGetActiveItems(): void
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

    public function testManagerCanGetMappingInfo(): void
    {
        $this->manager->subscribe($this->mockConnection1, $this->mockChannel1);

        $info = $this->manager->getMappingInfo();

        $this->assertArrayHasKey('connection_channels', $info);
        $this->assertArrayHasKey('channel_connections', $info);
    }

    public function testManagerCanClearAllMappings(): void
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

    public function testManagerHandlesNonExistentConnections(): void
    {
        $channels = $this->manager->getChannelsForConnection($this->mockConnection1);
        $this->assertEmpty($channels);

        $channelNames = $this->manager->getChannelNamesForConnection($this->mockConnection1);
        $this->assertEmpty($channelNames);

        $count = $this->manager->getChannelCountForConnection($this->mockConnection1);
        $this->assertEquals(0, $count);
    }

    public function testManagerHandlesNonExistentChannels(): void
    {
        $connections = $this->manager->getConnectionsForChannel($this->mockChannel1);
        $this->assertEmpty($connections);

        $connectionIds = $this->manager->getConnectionIdsForChannel($this->mockChannel1);
        $this->assertEmpty($connectionIds);

        $count = $this->manager->getConnectionCountForChannel($this->mockChannel1);
        $this->assertEquals(0, $count);
    }

    public function testManagerCanRemoveNonExistentMapping(): void
    {
        $this->manager->unsubscribe($this->mockConnection1, $this->mockChannel1);
        $stats = $this->manager->getStats();
        $this->assertEquals(0, $stats['total_connections']);
    }

    public function testManagerPreventsDoubleSubscription(): void
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
