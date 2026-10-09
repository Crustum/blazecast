<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Event;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Event\EventDispatcher;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use ReflectionClass;

/**
 * EventDispatcherTest
 *
 * TDD tests for EventDispatcher multi-application refactoring
 */
class EventDispatcherTest extends TestCase
{
    use VerifiesDoubles;

    protected ChannelManager $channelManager;

    protected ApplicationManager $applicationManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channelManager = new ChannelManager();

        $this->applicationManager = new ApplicationManager([
            'applications' => [
                [
                    'id' => 'test-app-1',
                    'key' => 'test-key-1',
                    'secret' => 'test-secret-1',
                    'name' => 'Test App 1',
                    'channel_manager' => $this->channelManager,
                ],
            ],
        ]);
    }

    /**
     * Test that EventDispatcher can dispatch to application-specific ChannelManager
     */
    public function testCanDispatchToApplicationChannelManager(): void
    {
        $channel = $this->channelManager->getChannel('test-channel');
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('test-connection-1');
        $connection->allows('send')->with(Argument::type('string'));

        $channel->subscribe($connection);

        EventDispatcher::dispatch(
            $this->applicationManager,
            'test-app-1',
            'test-channel',
            'client-test',
            json_encode(['message' => 'test data']),
        );

        $this->assertTrue(true);
    }

    /**
     * Test that EventDispatcher can exclude a connection in multi-app setup
     */
    public function testCanExcludeConnectionInMultiApp(): void
    {
        $channel = $this->channelManager->getChannel('test-channel');

        $connection1 = Double::for(Connection::class);
        $connection1->allows('getId')->returns('connection-1');
        $connection1->expects('send')->never();

        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('connection-2');
        $connection2->expects('send');

        $channel->subscribe($connection1);
        $channel->subscribe($connection2);

        EventDispatcher::dispatch(
            $this->applicationManager,
            'test-app-1',
            'test-channel',
            'client-test',
            json_encode(['message' => 'test data']),
            $connection1,
        );
    }

    /**
     * Test that EventDispatcher handles non-existent application gracefully
     */
    public function testHandlesNonExistentApplication(): void
    {
        EventDispatcher::dispatch(
            $this->applicationManager,
            'non-existent-app',
            'test-channel',
            'client-test',
            json_encode(['message' => 'test data']),
        );

        $this->assertTrue(true);
    }

    /**
     * Test that EventDispatcher handles non-existent channels gracefully
     */
    public function testHandlesNonExistentChannel(): void
    {
        EventDispatcher::dispatch(
            $this->applicationManager,
            'test-app-1',
            'non-existent-channel',
            'client-test',
            json_encode(['message' => 'test data']),
        );

        $this->assertTrue(true);
    }

    /**
     * Test that EventDispatcher creates proper payload format
     */
    public function testCreatesProperPayloadFormat(): void
    {
        $channel = $this->channelManager->getChannel('test-channel');
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('test-connection-1');

        $connection->expects('send')
            ->with(Argument::satisfies(function ($message): bool {
                $data = json_decode($message, true);

                return isset($data['event']) &&
                       isset($data['channel']) &&
                       isset($data['data']) &&
                       $data['event'] === 'client-test' &&
                       $data['channel'] === 'test-channel';
            }));

        $channel->subscribe($connection);

        EventDispatcher::dispatch(
            $this->applicationManager,
            'test-app-1',
            'test-channel',
            'client-test',
            json_encode(['message' => 'test data']),
        );
    }

    /**
     * Test that EventDispatcher can dispatch to multiple channels
     */
    public function testCanDispatchToMultipleChannels(): void
    {
        $channel1 = $this->channelManager->getChannel('channel-1');
        $channel2 = $this->channelManager->getChannel('channel-2');

        $connection1 = Double::for(Connection::class);
        $connection1->allows('getId')->returns('connection-1');
        $connection1->expects('send');

        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('connection-2');
        $connection2->expects('send');

        $channel1->subscribe($connection1);
        $channel2->subscribe($connection2);

        EventDispatcher::dispatchToMultiple(
            $this->applicationManager,
            'test-app-1',
            ['channel-1', 'channel-2'],
            'multi-channel-test',
            json_encode(['message' => 'broadcast test']),
        );
    }

    /**
     * Test application isolation - different apps don't interfere
     */
    public function testApplicationIsolation(): void
    {
        $app2ChannelManager = new ChannelManager();
        $applicationManager = new ApplicationManager([
            'applications' => [
                [
                    'id' => 'app-1',
                    'key' => 'key-1',
                    'secret' => 'secret-1',
                    'name' => 'App 1',
                    'channel_manager' => $this->channelManager,
                ],
                [
                    'id' => 'app-2',
                    'key' => 'key-2',
                    'secret' => 'secret-2',
                    'name' => 'App 2',
                    'channel_manager' => $app2ChannelManager,
                ],
            ],
        ]);

        $app1Channel = $this->channelManager->getChannel('shared-channel');
        $app2Channel = $app2ChannelManager->getChannel('shared-channel');

        $app1Connection = Double::for(Connection::class);
        $app1Connection->allows('getId')->returns('app1-connection');
        $app1Connection->expects('send')->never();

        $app2Connection = Double::for(Connection::class);
        $app2Connection->allows('getId')->returns('app2-connection');
        $app2Connection->expects('send');

        $app1Channel->subscribe($app1Connection);
        $app2Channel->subscribe($app2Connection);

        EventDispatcher::dispatch(
            $applicationManager,
            'app-2',
            'shared-channel',
            'app-isolation-test',
            json_encode(['message' => 'app2 only']),
        );
    }

    /**
     * Test that internal events route through broadcastInternally
     */
    public function testInternalEventsRouteThroughBroadcastInternally(): void
    {
        $spy = new class ('spy-channel') extends PusherChannel {
            /**
             * @var list<array<string, mixed>>
             */
            public array $internal = [];

            /**
             * @var list<array<string, mixed>>
             */
            public array $external = [];

            /**
             * @param array<string, mixed> $payload Message payload
             * @param \Crustum\BlazeCast\WebSocket\Connection|null $except Connection to exclude
             * @return list<string>
             */
            public function broadcast(array $payload, ?Connection $except = null): array
            {
                $this->external[] = $payload;

                return [];
            }

            /**
             * @param array<string, mixed> $payload Message payload
             * @param \Crustum\BlazeCast\WebSocket\Connection|null $except Connection to exclude
             * @return void
             */
            public function broadcastInternally(array $payload, ?Connection $except = null): void
            {
                $this->internal[] = $payload;
            }
        };

        $reflection = new ReflectionClass($this->channelManager);
        $property = $reflection->getProperty('channels');
        $property->setValue($this->channelManager, ['spy-channel' => $spy]);

        EventDispatcher::dispatch(
            $this->applicationManager,
            'test-app-1',
            'spy-channel',
            'pusher_internal:member_added',
            (string)json_encode(['user_id' => 'user-1']),
        );

        $this->assertCount(1, $spy->internal);
        $this->assertCount(0, $spy->external);

        EventDispatcher::dispatch(
            $this->applicationManager,
            'test-app-1',
            'spy-channel',
            'client-event',
            (string)json_encode(['message' => 'test data']),
        );

        $this->assertCount(1, $spy->external);
        $this->assertCount(1, $spy->internal);
    }
}
