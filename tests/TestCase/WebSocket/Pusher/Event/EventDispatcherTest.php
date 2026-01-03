<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Event;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Event\EventDispatcher;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;

/**
 * EventDispatcherTest
 *
 * TDD tests for EventDispatcher multi-application refactoring
 */
class EventDispatcherTest extends TestCase
{
    protected ChannelManager $channelManager;
    protected ApplicationManager $applicationManager;

    public function setUp(): void
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
        $connection = $this->createMock(Connection::class);
        $connection->method('getId')->willReturn('test-connection-1');
        $connection->method('send')->with($this->isString());

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

        $connection1 = $this->createMock(Connection::class);
        $connection1->method('getId')->willReturn('connection-1');
        $connection1->expects($this->never())->method('send');

        $connection2 = $this->createMock(Connection::class);
        $connection2->method('getId')->willReturn('connection-2');
        $connection2->expects($this->once())->method('send');

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
        $connection = $this->createMock(Connection::class);
        $connection->method('getId')->willReturn('test-connection-1');

        $connection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($message) {
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

        $connection1 = $this->createMock(Connection::class);
        $connection1->method('getId')->willReturn('connection-1');
        $connection1->expects($this->once())->method('send');

        $connection2 = $this->createMock(Connection::class);
        $connection2->method('getId')->willReturn('connection-2');
        $connection2->expects($this->once())->method('send');

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

        $app1Connection = $this->createMock(Connection::class);
        $app1Connection->method('getId')->willReturn('app1-connection');
        $app1Connection->expects($this->never())->method('send');

        $app2Connection = $this->createMock(Connection::class);
        $app2Connection->method('getId')->willReturn('app2-connection');
        $app2Connection->expects($this->once())->method('send');

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
}
