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
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

/**
 * ChannelOperationsManagerTest
 */
class ChannelOperationsManagerTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @var ChannelOperationsManager
     */
    private ChannelOperationsManager $channelManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager&\JMac\Testing\DoubleInterface
     */
    private ApplicationManager $applicationManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\ConnectionRegistry&\JMac\Testing\DoubleInterface
     */
    private ConnectionRegistry $connectionRegistry;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager&\JMac\Testing\DoubleInterface
     */
    private ChannelConnectionManager $connectionManager;

    /**
     * @var EventManager
     */
    private EventManager $eventManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\ApplicationContextResolver&\JMac\Testing\DoubleInterface
     */
    private ApplicationContextResolver $contextResolver;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationManager = Double::for(ApplicationManager::class);
        $this->connectionRegistry = Double::for(ConnectionRegistry::class);
        $this->connectionManager = Double::for(ChannelConnectionManager::class);
        // Double::for() cannot double EventManager (its `instance` method
        // collides with Double internals); the stub configured no methods,
        // so a real instance is behaviorally identical here.
        $this->eventManager = new EventManager();
        $this->contextResolver = Double::for(ApplicationContextResolver::class);

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
        $connection1 = Double::for(Connection::class);
        $connection1->allows('getId')->returns('conn-1');

        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('conn-2');

        $connections = [
            'conn-1' => $connection1,
            'conn-2' => $connection2,
        ];

        $this->connectionRegistry
            ->allows('getConnections')
            ->returns($connections);

        $connection1->expects('send')->with('test message');
        $connection2->expects('send')->with('test message');

        $this->channelManager->broadcast('test message');
    }

    /**
     * Test broadcast with exception
     *
     * @return void
     */
    public function testBroadcastWithException(): void
    {
        $connection1 = Double::for(Connection::class);
        $connection1->allows('getId')->returns('conn-1');

        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('conn-2');

        $exceptConnection = Double::for(Connection::class);
        $exceptConnection->allows('getId')->returns('conn-1');

        $connections = [
            'conn-1' => $connection1,
            'conn-2' => $connection2,
        ];

        $connectionRegistry = Double::for(ConnectionRegistry::class);
        $connectionRegistry->allows('getConnections')->returns($connections);
        $connectionRegistry->expects('getConnection')
            ->with('conn-1')
            ->returns($exceptConnection);

        $channelManager = new ChannelOperationsManager(
            $this->applicationManager,
            $connectionRegistry,
            $this->connectionManager,
            $this->eventManager,
            $this->contextResolver,
        );

        $connection1->expects('send')->never();
        $connection2->expects('send')->with('test message');

        $channelManager->broadcast('test message', 'conn-1');
    }

    /**
     * Test broadcastToChannelForApp
     *
     * @return void
     */
    public function testBroadcastToChannelForApp(): void
    {
        $channelManager = Double::for(PusherChannelManager::class);
        $channel = Double::for(PusherChannel::class);

        $application = [
            'id' => 'app-123',
            'channel_manager' => $channelManager,
        ];

        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplication')
            ->with('app-123')
            ->returns($application);

        $channelManager->expects('getChannel')
            ->with('test-channel')
            ->returns($channel);

        $channel->allows('getConnectionCount')->returns(5);
        $channel->expects('broadcast');

        $channelOperationsManager = new ChannelOperationsManager(
            $applicationManager,
            $this->connectionRegistry,
            $this->connectionManager,
            $this->eventManager,
            $this->contextResolver,
        );
        $channelOperationsManager->broadcastToChannelForApp('app-123', 'test-channel', 'test message');
    }

    /**
     * Test getChannelConnections
     *
     * @return void
     */
    public function testGetChannelConnections(): void
    {
        $connection1 = Double::for(Connection::class);
        $connection1->allows('getId')->returns('conn-1');

        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('conn-2');

        $channelManager = Double::for(PusherChannelManager::class);
        $channel = Double::for(PusherChannel::class);

        $application = [
            'id' => 'app-123',
            'channel_manager' => $channelManager,
        ];

        $this->applicationManager
            ->allows('getApplications')
            ->returns(['app-123' => $application]);

        $channelManager->expects('getChannel')
            ->with('test-channel')
            ->returns($channel);

        $channel
            ->allows('getConnections')
            ->returns([$connection1, $connection2]);

        $channelOperationsManager = new ChannelOperationsManager(
            $this->applicationManager,
            $this->connectionRegistry,
            $this->connectionManager,
            $this->eventManager,
            $this->contextResolver,
        );
        $result = $channelOperationsManager->getChannelConnections('test-channel');

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
            ->allows('getConnectionCount')
            ->returns(10);

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
        $connection1 = Double::for(Connection::class);
        $connection1->allows('getId')->returns('conn-1');

        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('conn-2');

        $connections = [
            'conn-1' => $connection1,
            'conn-2' => $connection2,
        ];

        $this->connectionRegistry
            ->allows('getConnections')
            ->returns($connections);

        $this->connectionRegistry
            ->allows('getConnectionInfo')
            ->with('conn-1')
            ->returns(['app_context' => ['app_id' => 'app-123']]);

        $this->connectionRegistry
            ->allows('getConnectionInfo')
            ->with('conn-2')
            ->returns(['app_context' => ['app_id' => 'app-456']]);

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
        $channelManager = Double::for(PusherChannelManager::class);
        $channel1 = Double::for(PusherChannel::class);
        $channel2 = Double::for(PusherChannel::class);

        $application = [
            'id' => 'app-123',
            'channel_manager' => $channelManager,
        ];

        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplication')
            ->times(2)
            ->with('app-123')
            ->returns($application);

        $channelManager
            ->allows('getChannel')
            ->with('channel-1')
            ->returns($channel1);

        $channelManager
            ->allows('getChannel')
            ->with('channel-2')
            ->returns($channel2);

        $channel1->allows('getConnectionCount')->returns(3);
        $channel2->allows('getConnectionCount')->returns(2);

        $channel1->expects('broadcast');
        $channel2->expects('broadcast');

        $channelOperationsManager = new ChannelOperationsManager(
            $applicationManager,
            $this->connectionRegistry,
            $this->connectionManager,
            $this->eventManager,
            $this->contextResolver,
        );
        $channelOperationsManager->broadcastToChannelsForApp('app-123', ['channel-1', 'channel-2'], 'test message');
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
