<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket\Event;

use Cake\Event\Event;
use Cake\Event\EventList;
use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\TestServer;
use Crustum\BlazeCast\Test\Support\WebSocketTestClient;
use Crustum\BlazeCast\WebSocket\Event\ChannelSubscribedEvent;
use Crustum\BlazeCast\WebSocket\Event\ChannelUnsubscribedEvent;
use Crustum\BlazeCast\WebSocket\Event\ConnectionClosedEvent;
use Crustum\BlazeCast\WebSocket\Event\ConnectionEstablishedEvent;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;

class EventDispatchingTest extends TestCase
{
    private TestServer $testServer;
    private WebSocketTestClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test server with dedicated event loop
        $this->testServer = new TestServer([
            'app_key' => 'test-app-key',
            'app_secret' => 'test-app-secret',
        ], false); // Use dedicated loop for isolation

        $this->client = $this->testServer->createClient();
    }

    protected function tearDown(): void
    {
        $this->client->close();
        $this->testServer->stop();

        parent::tearDown();
    }

    public function testServerCanStartAndListen(): void
    {
        $this->testServer->start();

        // Verify server socket is created and listening
        $socket = $this->testServer->getSocket();
        $this->assertNotNull($socket, 'Server socket should be created');

        // Wait for server to be ready to accept connections
        $this->assertTrue(
            $this->testServer->waitForReady(2.0),
            'Server should be ready to accept connections',
        );

        $this->assertTrue($this->testServer->isRunning(), 'Server should be running');
    }

    public function testConnectionEvents(): void
    {
        $eventManager = $this->testServer->getServer()->getEventManager();

        $this->testServer->start();
        $this->assertTrue($this->testServer->waitForReady(2.0), 'Server should be ready');

        $loop = $this->testServer->getLoop();

        $promise = async(function () use ($loop) {
            await($this->client->connect($this->testServer->getWebSocketUri()));
            await(sleep(0.1, $loop));
            $this->client->close();
            await(sleep(0.1, $loop));
        });

        $promise()->then(
            fn() => $loop->stop(),
            function ($reason) use ($loop) {
                $loop->stop();
                throw $reason;
            },
        );

        $loop->run();

        $this->assertEventFired(ConnectionEstablishedEvent::class, $eventManager);
        $this->assertEventFired(ConnectionClosedEvent::class, $eventManager);
    }

    public function testSubscriptionEvents(): void
    {
        $eventManager = $this->testServer->getServer()->getEventManager();
        $this->testServer->start();
        $this->assertTrue($this->testServer->waitForReady(2.0), 'Server should be ready');

        $loop = $this->testServer->getLoop();

        $promise = async(function () use ($loop) {
            await($this->client->connect($this->testServer->getWebSocketUri()));
            await(sleep(0.1, $loop));

            // Subscribe to a channel
            $subscribeMessage = json_encode([
                'event' => 'pusher:subscribe',
                'data' => ['channel' => 'test-channel'],
            ]);
            $this->client->send($subscribeMessage);
            await(sleep(0.1, $loop));

            // Unsubscribe from the channel
            $unsubscribeMessage = json_encode([
                'event' => 'pusher:unsubscribe',
                'data' => ['channel' => 'test-channel'],
            ]);
            $this->client->send($unsubscribeMessage);
            await(sleep(0.1, $loop));

            $this->client->close();
        });

        $promise()->then(
            fn() => $loop->stop(),
            function ($reason) use ($loop) {
                $loop->stop();
                throw $reason;
            },
        );

        $loop->run();

        $this->assertEventFired(ChannelSubscribedEvent::class, $eventManager);
        $this->assertEventFired(ChannelUnsubscribedEvent::class, $eventManager);
    }

    public function testEventTrackingWorks(): void
    {
        $this->testServer->start();

        // Get the server's event manager
        $eventManager = $this->testServer->getServer()->getEventManager();

        // Verify event tracking is enabled
        $this->assertInstanceOf(EventList::class, $eventManager->getEventList());

        // Manually dispatch a test event
        $testEvent = new Event('Test.event', $this);
        $eventManager->dispatch($testEvent);

        // Check if the event was tracked
        $this->assertEventFired('Test.event', $eventManager);
    }

    public function testSimpleConnection(): void
    {
        $this->testServer->start();

        // Wait for server to be ready
        $this->assertTrue(
            $this->testServer->waitForReady(2.0),
            'Server should be ready',
        );

        $connected = false;

        $promise = async(function () use (&$connected) {
            // Connect to the server
            $uri = $this->testServer->getWebSocketUri();
            await($this->client->connect($uri));
            $connected = true;

            // Small delay
            await(sleep(0.1, $this->testServer->getLoop()));

            $this->client->close();
        })();

        $promise->then(
            fn() => $this->testServer->getLoop()->stop(),
            function ($reason) {
                $this->testServer->getLoop()->stop();
                throw $reason;
            },
        );

        $this->testServer->run();

        $this->assertTrue($connected, 'Should be able to connect to WebSocket server');
    }
}
