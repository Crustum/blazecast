<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\Pusher\MetricsHandler;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for MetricsHandler presence metrics
 */
class MetricsHandlerTest extends TestCase
{
    use VerifiesDoubles;

    private ApplicationManager $applicationManager;

    private ChannelManager $channelManager;

    private ChannelConnectionManager $connectionManager;

    private MetricsHandler $metricsHandler;

    /**
     * @var array<string, mixed>
     */
    private array $testApp = [
        'id' => 'test-app',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'name' => 'Test Application',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationManager = new ApplicationManager();
        $reflection = new ReflectionClass($this->applicationManager);
        $appsProperty = $reflection->getProperty('applications');
        $appsProperty->setValue($this->applicationManager, ['test-app' => $this->testApp]);

        $this->channelManager = new ChannelManager();
        $this->connectionManager = new ChannelConnectionManager();
        $this->metricsHandler = new MetricsHandler(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
        );
    }

    /**
     * Create a stub connection with attribute storage
     *
     * @param string $id Connection ID
     * @param array<string, mixed> $attributes Initial attributes
     * @return \Crustum\BlazeCast\WebSocket\Connection
     */
    private function createMetricsConnection(string $id, array $attributes = []): Connection
    {
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns($id);
        $connection->allows('getAttribute')
            ->resolves(function (mixed ...$args) use (&$attributes): mixed {
                $key = $args[0];

                return $attributes[$key] ?? null;
            });
        $connection->allows('setAttribute')
            ->resolves(function (mixed ...$args) use (&$attributes): mixed {
                $key = $args[0];
                $value = $args[1];
                $attributes[$key] = $value;

                return null;
            });

        return $connection;
    }

    #[Test]
    public function presenceDataReturnsEmptyForUnknownChannel(): void
    {
        $result = $this->metricsHandler->gather($this->testApp, 'presence_data', [
            'channel' => 'presence-unknown',
        ]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function presenceDataReturnsEmptyForNonPresenceChannel(): void
    {
        $channel = $this->channelManager->getChannel('public-channel');
        $connection = $this->createMetricsConnection('conn-1');
        $channel->subscribe($connection);
        $this->connectionManager->subscribe($connection, $channel);

        $result = $this->metricsHandler->gather($this->testApp, 'presence_data', [
            'channel' => 'public-channel',
        ]);

        $this->assertSame([], $result);
    }

    /**
     * Attach a connection to a channel without going through auth verification
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface $channel Channel
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return void
     */
    private function attachConnection(PusherChannelInterface $channel, Connection $connection): void
    {
        $reflection = new ReflectionClass($channel);
        $property = $reflection->getProperty('connections');
        $connections = $property->getValue($channel);
        $connections[$connection->getId()] = $connection;
        $property->setValue($channel, $connections);

        $this->connectionManager->subscribe($connection, $channel);
    }

    #[Test]
    public function presenceConnectionsListsUserConnectionsWithSubscriptionTimes(): void
    {
        $channel = $this->channelManager->getChannel('presence-test-channel');

        $first = $this->createMetricsConnection('conn-1', [
            'user_id' => 'user-1',
            'subscribed_at_presence-test-channel' => 1000.5,
        ]);
        $this->attachConnection($channel, $first);

        $second = $this->createMetricsConnection('conn-2', [
            'user_id' => 'user-1',
            'subscribed_at_presence-test-channel' => 1001.5,
        ]);
        $this->attachConnection($channel, $second);

        $other = $this->createMetricsConnection('conn-3', [
            'user_id' => 'user-2',
            'subscribed_at_presence-test-channel' => 1002.5,
        ]);
        $this->attachConnection($channel, $other);

        $result = $this->metricsHandler->gather($this->testApp, 'presence_connections', [
            'channel' => 'presence-test-channel',
            'user_id' => 'user-1',
        ]);

        $this->assertCount(2, $result);
        $ids = array_column($result, 'id');
        $this->assertContains('conn-1', $ids);
        $this->assertContains('conn-2', $ids);
        foreach ($result as $row) {
            $this->assertIsFloat($row['subscribed_at']);
        }
    }

    #[Test]
    public function presenceDataReturnsChannelData(): void
    {
        $channel = $this->channelManager->getChannel('presence-data-channel');
        $connection = $this->createMetricsConnection('conn-1', ['user_id' => 'user-1']);
        $this->attachConnection($channel, $connection);

        $result = $this->metricsHandler->gather($this->testApp, 'presence_data', [
            'channel' => 'presence-data-channel',
        ]);

        $this->assertSame(1, $result['presence']['count']);
        $this->assertSame(['user-1'], $result['presence']['ids']);
    }

    #[Test]
    public function presenceConnectionsReturnsEmptyForUnknownChannel(): void
    {
        $result = $this->metricsHandler->gather($this->testApp, 'presence_connections', [
            'channel' => 'presence-unknown',
            'user_id' => 'user-1',
        ]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function mergePresenceDataUnionsIdsAndHashes(): void
    {
        $merged = $this->metricsHandler->mergePresenceData([
            ['presence' => [
                'count' => 2,
                'ids' => ['1', '2'],
                'hash' => ['1' => ['name' => 'Joe'], '2' => ['name' => 'Jane']],
            ]],
            ['presence' => [
                'count' => 3,
                'ids' => ['2', '3', '4'],
                'hash' => ['2' => ['name' => 'Jane'], '3' => ['name' => 'Jim'], '4' => []],
            ]],
            [],
        ]);

        $this->assertSame(['1', '2', '3', '4'], $merged['presence']['ids']);
        $this->assertSame(4, $merged['presence']['count']);
        $this->assertSame(['name' => 'Joe'], $merged['presence']['hash']['1']);
        $this->assertSame(['name' => 'Jane'], $merged['presence']['hash']['2']);
        $this->assertSame(['name' => 'Jim'], $merged['presence']['hash']['3']);
        $this->assertEquals((object)[], $merged['presence']['hash']['4']);
    }

    #[Test]
    public function mergePresenceDataHandlesEmptyInput(): void
    {
        $this->assertSame(
            ['presence' => ['count' => 0, 'ids' => [], 'hash' => []]],
            $this->metricsHandler->mergePresenceData([[], []]),
        );
    }

    #[Test]
    public function mergePresenceConnectionsConcatenatesAllSubscribers(): void
    {
        $merged = $this->metricsHandler->mergePresenceConnections([
            [['id' => 'socket-one', 'subscribed_at' => 100.0]],
            [],
            [
                ['id' => 'socket-two', 'subscribed_at' => 100.5],
                ['id' => 'socket-three', 'subscribed_at' => 101.0],
            ],
        ]);

        $this->assertSame(
            [
                ['id' => 'socket-one', 'subscribed_at' => 100.0],
                ['id' => 'socket-two', 'subscribed_at' => 100.5],
                ['id' => 'socket-three', 'subscribed_at' => 101.0],
            ],
            $merged,
        );
    }

    #[Test]
    public function mergeChannelUsersDeduplicatesIntoList(): void
    {
        $merged = $this->metricsHandler->mergeChannelUsers([
            [['id' => 1], ['id' => 2]],
            [['id' => 2], ['id' => 3]],
        ]);

        $this->assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $merged);
    }

    #[Test]
    public function channelUsersReturnsListShape(): void
    {
        $channel = $this->channelManager->getChannel('presence-users-channel');

        $first = $this->createMetricsConnection('conn-1', ['user_id' => 'user-1']);
        $this->attachConnection($channel, $first);

        $second = $this->createMetricsConnection('conn-2', ['user_id' => 'user-2']);
        $this->attachConnection($channel, $second);

        $result = $this->metricsHandler->gather($this->testApp, 'channel_users', [
            'channel' => 'presence-users-channel',
        ]);

        $this->assertSame([['id' => 'user-1'], ['id' => 'user-2']], array_values($result));
    }
}
