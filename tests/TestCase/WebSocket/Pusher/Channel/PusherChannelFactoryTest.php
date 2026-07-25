<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Channel;

use Cake\Core\Configure;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherCacheChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelFactory;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPresenceChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPrivateChannel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PusherChannelFactory
 */
class PusherChannelFactoryTest extends TestCase
{
    private PusherChannelFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        Configure::write('Pusher.private_channel_auth', [
            'enable_auth' => true,
            'auth_endpoint' => '/pusher/auth',
        ]);

        $this->factory = new PusherChannelFactory();
    }

    protected function tearDown(): void
    {
        Configure::delete('Pusher.private_channel_auth');
        parent::tearDown();
    }

    #[Test]
    public function factoryCanCreatePublicChannel(): void
    {
        $channel = $this->factory->create('public-channel');

        $this->assertInstanceOf(PusherChannel::class, $channel);
        $this->assertInstanceOf(PusherChannelInterface::class, $channel);
        $this->assertEquals('public-channel', $channel->getName());
        $this->assertEquals('public', $channel->getType());
    }

    #[Test]
    public function factoryCanCreatePrivateChannel(): void
    {
        $channel = $this->factory->create('private-test');

        $this->assertInstanceOf(PusherPrivateChannel::class, $channel);
        $this->assertInstanceOf(PusherChannelInterface::class, $channel);
        $this->assertEquals('private-test', $channel->getName());
        $this->assertEquals('private', $channel->getType());
    }

    #[Test]
    public function factoryCanCreatePresenceChannel(): void
    {
        $channel = $this->factory->create('presence-room-1');

        $this->assertInstanceOf(PusherPresenceChannel::class, $channel);
        $this->assertInstanceOf(PusherChannelInterface::class, $channel);
        $this->assertEquals('presence-room-1', $channel->getName());
        $this->assertEquals('presence', $channel->getType());
    }

    #[Test]
    public function factoryCanCreateCacheChannel(): void
    {
        $channel = $this->factory->create('cache-messages');

        $this->assertInstanceOf(PusherCacheChannel::class, $channel);
        $this->assertInstanceOf(PusherChannelInterface::class, $channel);
        $this->assertEquals('cache-messages', $channel->getName());
        $this->assertEquals('cache', $channel->getType());
    }

    #[Test]
    public function factoryDetectsChannelTypeByPrefix(): void
    {
        $testCases = [
            'public-test' => PusherChannel::class,
            'test-channel' => PusherChannel::class,
            'simple' => PusherChannel::class,
            'private-test' => PusherPrivateChannel::class,
            'private-user-123' => PusherPrivateChannel::class,
            'presence-chat' => PusherPresenceChannel::class,
            'presence-room-456' => PusherPresenceChannel::class,
            'cache-data' => PusherCacheChannel::class,
            'cache-notifications' => PusherCacheChannel::class,
        ];

        foreach ($testCases as $channelName => $expectedClass) {
            $channel = $this->factory->create($channelName);
            $this->assertInstanceOf($expectedClass, $channel);
            $this->assertEquals($channelName, $channel->getName());
        }
    }

    #[Test]
    public function factoryCanCreateChannelWithMetadata(): void
    {
        $metadata = ['priority' => 'high', 'category' => 'notifications'];
        $channel = $this->factory->create('test-channel', $metadata);

        $this->assertInstanceOf(PusherChannel::class, $channel);
        $this->assertEquals($metadata, $channel->getMetadata());
    }

    #[Test]
    public function factoryValidatesChannelName(): void
    {
        $isValid = $this->factory->isValidChannelName('');
        $this->assertFalse($isValid);
    }

    #[Test]
    public function factoryValidatesChannelNameLength(): void
    {
        $longName = str_repeat('a', 201);

        $isValid = $this->factory->isValidChannelName($longName);
        $this->assertFalse($isValid);
    }

    #[Test]
    public function factoryValidatesChannelNameCharacters(): void
    {
        $invalidNames = [
            'channel with spaces',
            'channel#with#hash',
            'channel%with%percent',
        ];

        foreach ($invalidNames as $invalidName) {
            $isValid = $this->factory->isValidChannelName($invalidName);
            $this->assertFalse($isValid, "Channel name should be invalid: {$invalidName}");
        }
    }

    #[Test]
    public function factoryAcceptsValidChannelNameCharacters(): void
    {
        $validNames = [
            'valid-channel',
            'valid_channel',
            'valid.channel',
            'valid123',
            'VALID-CHANNEL',
            'presence-room.1',
            'private-user_123',
            'cache-data.store',
        ];

        foreach ($validNames as $validName) {
            $channel = $this->factory->create($validName);
            $this->assertEquals($validName, $channel->getName());
        }
    }

    #[Test]
    public function factoryCanDetectChannelTypeFromName(): void
    {
        $testCases = [
            'simple-channel' => 'public',
            'private-test' => 'private',
            'presence-room' => 'presence',
            'cache-data' => 'cache',
        ];

        foreach ($testCases as $channelName => $expectedType) {
            $type = $this->factory->getChannelType($channelName);
            $this->assertEquals($expectedType, $type);
        }
    }

    #[Test]
    public function factoryCanGetSupportedChannelTypes(): void
    {
        $types = $this->factory->getSupportedTypes();

        $this->assertContains('public', $types);
        $this->assertContains('private', $types);
        $this->assertContains('presence', $types);
        $this->assertContains('cache', $types);
    }

    #[Test]
    public function factoryCanCreateFromArrayData(): void
    {
        $channelData = [
            'name' => 'configured-channel',
            'metadata' => ['configured' => true],
        ];

        $channel = $this->factory->fromArray($channelData);

        $this->assertInstanceOf(PusherChannelInterface::class, $channel);
        $this->assertEquals('configured-channel', $channel->getName());
        $this->assertEquals(['configured' => true], $channel->getMetadata());
    }

    #[Test]
    public function factoryCanCreateMultipleChannels(): void
    {
        $channelNames = ['channel-1', 'private-channel-2', 'presence-room-3'];

        $channels = $this->factory->createMultiple($channelNames);

        $this->assertCount(3, $channels);
        $this->assertArrayHasKey('channel-1', $channels);
        $this->assertArrayHasKey('private-channel-2', $channels);
        $this->assertArrayHasKey('presence-room-3', $channels);
    }

    #[Test]
    public function factoryCanGetBasicStatistics(): void
    {
        $stats = $this->factory->getStats();

        $this->assertArrayHasKey('channels_created', $stats);
        $this->assertArrayHasKey('config', $stats);
    }

    #[Test]
    public function factoryPreventsCircularDependencies(): void
    {
        $channel1 = $this->factory->create('test-circular');
        $channel2 = $this->factory->create('test-circular');

        $this->assertNotSame($channel1, $channel2);
        $this->assertEquals($channel1->getName(), $channel2->getName());
    }
}
