<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PusherChannel base class
 */
class PusherChannelTest extends TestCase
{
    private PusherChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = new PusherChannel('test-channel');
    }

    public function testChannelCanBeCreatedWithName(): void
    {
        $channel = new PusherChannel('my-channel');

        $this->assertInstanceOf(PusherChannel::class, $channel);
        $this->assertEquals('my-channel', $channel->getName());
    }

    public function testChannelImplementsPusherChannelInterface(): void
    {
        $this->assertInstanceOf(PusherChannelInterface::class, $this->channel);
    }

    public function testChannelReturnsCorrectType(): void
    {
        $this->assertEquals('public', $this->channel->getType());
    }

    public function testChannelReturnsEmptyMembersByDefault(): void
    {
        $members = $this->channel->getMembers();
        $this->assertEmpty($members);
    }

    public function testChannelReturnsZeroMemberCountByDefault(): void
    {
        $count = $this->channel->getMemberCount();
        $this->assertEquals(0, $count);
    }

    public function testChannelReturnsEmptyPresenceStatsByDefault(): void
    {
        $stats = $this->channel->getPresenceStats();
        $this->assertEmpty($stats);
    }

    public function testChannelReturnsEmptyCacheStatsByDefault(): void
    {
        $stats = $this->channel->getCacheStats();
        $this->assertEmpty($stats);
    }

    public function testChannelCanConvertToArray(): void
    {
        $array = $this->channel->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertEquals('test-channel', $array['name']);
    }

    public function testChannelIsJsonSerializable(): void
    {
        $json = json_encode($this->channel);

        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('name', $decoded);
        $this->assertEquals('test-channel', $decoded['name']);
    }

    public function testChannelHandlesDifferentChannelNames(): void
    {
        $testNames = [
            'simple',
            'with-dashes',
            'with_underscores',
            'with.dots',
            'presence-test',
            'private-test',
            'cache-test',
            'channel123',
            'very-long-channel-name-with-many-parts',
        ];

        foreach ($testNames as $name) {
            $channel = new PusherChannel($name);
            $this->assertEquals($name, $channel->getName());
            $this->assertEquals('public', $channel->getType());
        }
    }

    public function testChannelCanGetStatistics(): void
    {
        $stats = $this->channel->getStats();
        $this->assertArrayHasKey('name', $stats);
        $this->assertArrayHasKey('connection_count', $stats);
        $this->assertEquals('test-channel', $stats['name']);
        $this->assertEquals(0, $stats['connection_count']);
    }

    public function testChannelImplementsRequiredInterface(): void
    {
        $this->assertInstanceOf(PusherChannelInterface::class, $this->channel);
    }

    public function testChannelCanManageMetadata(): void
    {
        $metadata = ['custom' => 'value', 'priority' => 10];
        $this->channel->setMetadata($metadata);

        $this->assertEquals($metadata, $this->channel->getMetadata());
    }

    public function testChannelCanCheckIfEmpty(): void
    {
        $this->assertTrue($this->channel->isEmpty());
        $this->assertEquals(0, $this->channel->getConnectionCount());
    }

    public function testChannelCanGetConnectionsArray(): void
    {
        $connections = $this->channel->getConnections();
        $this->assertEmpty($connections);
    }

    public function testChannelCanFindConnectionById(): void
    {
        $connection = $this->channel->findConnection('non-existent');
        $this->assertNull($connection);
    }

    public function testChannelReturnsCorrectDataForApi(): void
    {
        $data = $this->channel->getData();
        $this->assertEmpty($data);
    }

    public function testChannelCanBeCreatedFromArray(): void
    {
        $data = [
            'name' => 'test-from-array',
            'type' => 'public',
            'metadata' => ['test' => true],
            'connection_count' => 0,
            'connections' => [],
        ];

        $channel = PusherChannel::fromArray($data);
        $this->assertInstanceOf(PusherChannel::class, $channel);
        $this->assertEquals('test-from-array', $channel->getName());
        $this->assertEquals(['test' => true], $channel->getMetadata());
    }
}
