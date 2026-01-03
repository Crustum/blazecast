<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Manager;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\Pusher\MetricsHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ChannelManager
 */
class ChannelManagerTest extends TestCase
{
    private ChannelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ChannelManager();
    }

    #[Test]
    public function managerCanBeCreated(): void
    {
        $manager = new ChannelManager();
        $this->assertInstanceOf(ChannelManager::class, $manager);
    }

    #[Test]
    public function managerStartsWithNoChannels(): void
    {
        $this->assertEquals(0, $this->manager->getChannelCount());
        $this->assertEmpty($this->manager->getChannels());
    }

    #[Test]
    public function managerCanGetOrCreateChannel(): void
    {
        $this->assertFalse($this->manager->hasChannel('test-channel'));

        $channel = $this->manager->getChannel('test-channel');

        $this->assertEquals(1, $this->manager->getChannelCount());
        $this->assertTrue($this->manager->hasChannel('test-channel'));
        $this->assertInstanceOf(PusherChannel::class, $channel);
        $this->assertEquals('test-channel', $channel->getName());
    }

    #[Test]
    public function managerCanRemoveEmptyChannel(): void
    {
        $this->manager->getChannel('test-channel');
        $this->assertTrue($this->manager->hasChannel('test-channel'));

        $removed = $this->manager->removeChannelIfEmpty('test-channel');

        $this->assertTrue($removed);
        $this->assertEquals(0, $this->manager->getChannelCount());
        $this->assertFalse($this->manager->hasChannel('test-channel'));
    }

    #[Test]
    public function managerCanGetAllChannels(): void
    {
        $channel1 = $this->manager->getChannel('channel-1');
        $channel2 = $this->manager->getChannel('channel-2');

        $channels = $this->manager->getChannels();

        $this->assertCount(2, $channels);
        $this->assertArrayHasKey('channel-1', $channels);
        $this->assertArrayHasKey('channel-2', $channels);
        $this->assertSame($channel1, $channels['channel-1']);
        $this->assertSame($channel2, $channels['channel-2']);
    }

    #[Test]
    public function managerCanGetChannelsByType(): void
    {
        $this->manager->getChannel('public-channel');
        $this->manager->getChannel('private-test');
        $this->manager->getChannel('presence-room');

        $publicChannels = $this->manager->getChannelsByType('public');
        $privateChannels = $this->manager->getChannelsByType('private');
        $presenceChannels = $this->manager->getChannelsByType('presence');

        $this->assertCount(1, $publicChannels);
        $this->assertCount(1, $privateChannels);
        $this->assertCount(1, $presenceChannels);
    }

    #[Test]
    public function managerCanClearAllChannels(): void
    {
        $this->manager->getChannel('channel-1');
        $this->manager->getChannel('channel-2');

        $this->assertEquals(2, $this->manager->getChannelCount());

        $this->manager->clear();

        $this->assertEquals(0, $this->manager->getChannelCount());
        $this->assertEmpty($this->manager->getChannels());
    }

    #[Test]
    public function managerCanGetChannelStatistics(): void
    {
        $this->manager->getChannel('public-chat');
        $this->manager->getChannel('test-channel');

        $stats = $this->manager->getStats();

        $this->assertArrayHasKey('total_channels', $stats);
        $this->assertEquals(2, $stats['total_channels']);
    }

    #[Test]
    public function managerReturnsExistingChannel(): void
    {
        $channel1 = $this->manager->getChannel('test-channel');
        $channel2 = $this->manager->getChannel('test-channel');

        $this->assertEquals(1, $this->manager->getChannelCount());
        $this->assertSame($channel1, $channel2);
    }

    #[Test]
    public function managerCanCheckChannelExistence(): void
    {
        $this->assertFalse($this->manager->hasChannel('non-existent'));

        $this->manager->getChannel('existing-channel');

        $this->assertTrue($this->manager->hasChannel('existing-channel'));
        $this->assertFalse($this->manager->hasChannel('still-non-existent'));
    }

    #[Test]
    public function managerCanRemoveNonExistentChannel(): void
    {
        $removed = $this->manager->removeChannelIfEmpty('non-existent');
        $this->assertFalse($removed);
        $this->assertEquals(0, $this->manager->getChannelCount());
    }

    #[Test]
    public function managerCanCreateDifferentChannelTypes(): void
    {
        $publicChannel = $this->manager->getChannel('public-test');
        $privateChannel = $this->manager->getChannel('private-test');
        $presenceChannel = $this->manager->getChannel('presence-test');
        $cacheChannel = $this->manager->getChannel('cache-test');

        $this->assertEquals('public', $publicChannel->getType());
        $this->assertEquals('private', $privateChannel->getType());
        $this->assertEquals('presence', $presenceChannel->getType());
        $this->assertEquals('cache', $cacheChannel->getType());
    }

    #[Test]
    public function managerCanGetChannelInfo(): void
    {
        $this->manager->getChannel('test-channel');

        $info = $this->manager->getChannelInfo('test-channel');

        $this->assertArrayHasKey('name', $info);
        $this->assertEquals('test-channel', $info['name']);
    }

    #[Test]
    public function managerCanGetChannelNamesByPattern(): void
    {
        $this->manager->getChannel('test-alpha');
        $this->manager->getChannel('test-beta');
        $this->manager->getChannel('other-gamma');

        $testChannels = $this->manager->getChannelNamesByPattern('test-*');

        $this->assertCount(2, $testChannels);
        $this->assertContains('test-alpha', $testChannels);
        $this->assertContains('test-beta', $testChannels);
        $this->assertNotContains('other-gamma', $testChannels);
    }

    #[Test]
    public function managerCanHandleChannelWithMetadata(): void
    {
        $metadata = ['priority' => 'high', 'region' => 'us-east-1'];
        $channel = $this->manager->getChannel('meta-channel', $metadata);

        $this->assertEquals($metadata, $channel->getMetadata());
    }

    #[Test]
    public function metricsHandlerWorksWithRealChannels(): void
    {
        // Setup managers
        $applicationManager = new ApplicationManager([
            'app_id' => 'test-app',
            'app_key' => 'key',
            'app_secret' => 'secret',
            'app_name' => 'Test App',
        ]);
        $app = $applicationManager->getApplication('test-app');
        $connectionManager = new ChannelConnectionManager();
        $metricsHandler = new MetricsHandler(
            $applicationManager,
            $this->manager,
            $connectionManager,
        );

        // Create channels
        $channel1 = $this->manager->getChannel('public-channel');
        $channel2 = $this->manager->getChannel('presence-channel');

        // Create simple mock connections
        $connection1 = $this->createMock(Connection::class);
        $connection1->method('getId')->willReturn('conn-1');
        $connection1->method('getAttribute')->willReturnCallback(function ($key) {
            return $key === 'app_id' ? 'test-app' : null;
        });

        $connection2 = $this->createMock(Connection::class);
        $connection2->method('getId')->willReturn('conn-2');
        $connection2->method('getAttribute')->willReturnCallback(function ($key) {
            return $key === 'app_id' ? 'test-app' : ($key === 'user_id' ? 'user-2' : null);
        });

        // Subscribe connections to public channel (no auth needed)
        $channel1->subscribe($connection1);

        // Register in connection manager
        $connectionManager->subscribe($connection1, $channel1);
        $connectionManager->subscribe($connection2, $channel2);

        // Test gather: channels
        $channelsMetrics = $metricsHandler->gather($app, 'channels', []);
        $this->assertArrayHasKey('public-channel', $channelsMetrics);

        // Test gather: channel
        $channelMetrics = $metricsHandler->gather($app, 'channel', ['channel' => 'public-channel']);
        $this->assertArrayHasKey('occupied', $channelMetrics);
        $this->assertTrue($channelMetrics['occupied']);

        // Test gather: channel_users (should be empty for non-presence)
        $usersNonPresence = $metricsHandler->gather($app, 'channel_users', ['channel' => 'public-channel']);
        $this->assertCount(0, $usersNonPresence);

        // Test gather: connections
        $connections = $metricsHandler->gather($app, 'connections', []);
        $this->assertContains('conn-1', $connections);

        $debugMetrics = $metricsHandler->getDebugMetrics($app);
        $this->assertArrayHasKey('channels', $debugMetrics);
        $this->assertArrayHasKey('connections', $debugMetrics);
        $this->assertArrayHasKey('mapping_info', $debugMetrics);
    }
}
