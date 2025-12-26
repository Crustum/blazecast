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

    /**
     * @test
     */
    public function channelCanBeCreatedWithName(): void
    {
        $channel = new PusherChannel('my-channel');

        $this->assertInstanceOf(PusherChannel::class, $channel);
        $this->assertEquals('my-channel', $channel->getName());
    }

    /**
     * @test
     */
    public function channelImplementsPusherChannelInterface(): void
    {
        $this->assertInstanceOf(PusherChannelInterface::class, $this->channel);
    }

    /**
     * @test
     */
    public function channelReturnsCorrectType(): void
    {
        $this->assertEquals('public', $this->channel->getType());
    }

    /**
     * @test
     */
    public function channelReturnsEmptyMembersByDefault(): void
    {
        $members = $this->channel->getMembers();
        $this->assertEmpty($members);
    }

    /**
     * @test
     */
    public function channelReturnsZeroMemberCountByDefault(): void
    {
        $count = $this->channel->getMemberCount();
        $this->assertEquals(0, $count);
    }

    /**
     * @test
     */
    public function channelReturnsEmptyPresenceStatsByDefault(): void
    {
        $stats = $this->channel->getPresenceStats();
        $this->assertEmpty($stats);
    }

    /**
     * @test
     */
    public function channelReturnsEmptyCacheStatsByDefault(): void
    {
        $stats = $this->channel->getCacheStats();
        $this->assertEmpty($stats);
    }

    /**
     * @test
     */
    public function channelCanConvertToArray(): void
    {
        $array = $this->channel->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertEquals('test-channel', $array['name']);
    }

    /**
     * @test
     */
    public function channelIsJsonSerializable(): void
    {
        $json = json_encode($this->channel);

        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('name', $decoded);
        $this->assertEquals('test-channel', $decoded['name']);
    }

    /**
     * @test
     */
    public function channelHandlesDifferentChannelNames(): void
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

    /**
     * @test
     */
    public function channelCanGetStatistics(): void
    {
        $stats = $this->channel->getStats();
        $this->assertArrayHasKey('name', $stats);
        $this->assertArrayHasKey('connection_count', $stats);
        $this->assertEquals('test-channel', $stats['name']);
        $this->assertEquals(0, $stats['connection_count']);
    }

    /**
     * @test
     */
    public function channelImplementsRequiredInterface(): void
    {
        $this->assertInstanceOf(PusherChannelInterface::class, $this->channel);
    }

    /**
     * @test
     */
    public function channelCanManageMetadata(): void
    {
        $metadata = ['custom' => 'value', 'priority' => 10];
        $this->channel->setMetadata($metadata);

        $this->assertEquals($metadata, $this->channel->getMetadata());
    }

    /**
     * @test
     */
    public function channelCanCheckIfEmpty(): void
    {
        $this->assertTrue($this->channel->isEmpty());
        $this->assertEquals(0, $this->channel->getConnectionCount());
    }

    /**
     * @test
     */
    public function channelCanGetConnectionsArray(): void
    {
        $connections = $this->channel->getConnections();
        $this->assertEmpty($connections);
    }

    /**
     * @test
     */
    public function channelCanFindConnectionById(): void
    {
        $connection = $this->channel->findConnection('non-existent');
        $this->assertNull($connection);
    }

    /**
     * @test
     */
    public function channelReturnsCorrectDataForApi(): void
    {
        $data = $this->channel->getData();
        $this->assertEmpty($data);
    }

    /**
     * @test
     */
    public function channelCanBeCreatedFromArray(): void
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
