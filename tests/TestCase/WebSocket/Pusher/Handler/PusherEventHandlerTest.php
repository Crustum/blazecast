<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Handler;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\ChannelOperationsManager;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Handler\PusherEventHandler;
use Crustum\BlazeCast\WebSocket\WebSocketServerInterface;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use ReflectionClass;

/**
 * PusherEventHandler Test Case
 */
class PusherEventHandlerTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * Test subject
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Handler\PusherEventHandler
     */
    protected PusherEventHandler $pusherEventHandler;

    /**
     * Stub WebSocket server
     *
     * @var \Crustum\BlazeCast\WebSocket\WebSocketServerInterface&\JMac\Testing\DoubleInterface
     */
    protected WebSocketServerInterface $mockServer;

    /**
     * Stub connection
     *
     * @var \Crustum\BlazeCast\WebSocket\Connection&\JMac\Testing\DoubleInterface
     */
    protected Connection $mockConnection;

    /**
     * Stub message
     *
     * @var \Crustum\BlazeCast\WebSocket\Protocol\Message&\JMac\Testing\DoubleInterface
     */
    protected Message $mockMessage;

    /**
     * Stub application manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager&\JMac\Testing\DoubleInterface
     */
    protected ApplicationManager $mockApplicationManager;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServer = Double::for(WebSocketServerInterface::class);
        $this->mockConnection = Double::for(Connection::class);
        $this->mockMessage = Double::for(Message::class);
        $this->mockApplicationManager = Double::for(ApplicationManager::class);

        $this->pusherEventHandler = new PusherEventHandler();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->pusherEventHandler);
        unset($this->mockServer);
        unset($this->mockConnection);
        unset($this->mockMessage);
        unset($this->mockApplicationManager);

        parent::tearDown();
    }

    /**
     * Test setServer method
     *
     * @return void
     */
    public function testSetServer(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->pusherEventHandler->setServer($this->mockServer);

        $this->assertInstanceOf(PusherEventHandler::class, $this->pusherEventHandler);
    }

    /**
     * Test supports method with valid events
     *
     * @return void
     */
    public function testSupportsWithValidEvents(): void
    {
        $this->assertTrue($this->pusherEventHandler->supports('pusher:ping'));
        $this->assertTrue($this->pusherEventHandler->supports('pusher:subscribe'));
        $this->assertTrue($this->pusherEventHandler->supports('pusher:unsubscribe'));
        $this->assertTrue($this->pusherEventHandler->supports('client-event'));
    }

    /**
     * Test supports method with invalid events
     *
     * @return void
     */
    public function testSupportsWithInvalidEvents(): void
    {
        $this->assertFalse($this->pusherEventHandler->supports('invalid:event'));
        $this->assertFalse($this->pusherEventHandler->supports('server-event'));
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function testInitialize(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->pusherEventHandler->initialize($this->mockServer);

        $this->assertInstanceOf(PusherEventHandler::class, $this->pusherEventHandler);
    }

    /**
     * Test canHandle method with valid events
     *
     * @return void
     */
    public function testCanHandleWithValidEvents(): void
    {
        $this->mockMessage->allows('getEvent')
            ->returns('pusher:ping');

        $this->assertTrue($this->pusherEventHandler->canHandle($this->mockMessage));
    }

    /**
     * Test canHandle method with client events
     *
     * @return void
     */
    public function testCanHandleWithClientEvents(): void
    {
        $this->mockMessage->allows('getEvent')
            ->returns('client-typing');

        $this->assertTrue($this->pusherEventHandler->canHandle($this->mockMessage));
    }

    /**
     * Test canHandle method with invalid events
     *
     * @return void
     */
    public function testCanHandleWithInvalidEvents(): void
    {
        $this->mockMessage->allows('getEvent')
            ->returns('invalid:event');

        $this->assertFalse($this->pusherEventHandler->canHandle($this->mockMessage));
    }

    /**
     * Test handle method with ping event
     *
     * @return void
     */
    public function testHandleWithPingEvent(): void
    {
        $connection = Double::for(Connection::class);
        $connection->allows('getId')
            ->returns('test-connection-id');

        $this->mockMessage->allows('getEvent')
            ->returns('pusher:ping');

        $connection->expects('send')
            ->with(Argument::satisfies(function ($data): bool {
                $decoded = json_decode($data, true);

                return $decoded['event'] === 'pusher:pong' && $decoded['data'] === '{}';
            }));

        $this->pusherEventHandler->setServer($this->mockServer);
        $this->pusherEventHandler->handle($connection, $this->mockMessage);
    }

    /**
     * Test handle method with subscribe event
     *
     * @return void
     */
    public function testHandleWithSubscribeEvent(): void
    {
        $connection = Double::for(Connection::class);
        $connection->allows('getId')
            ->returns('test-connection-id');

        $this->mockMessage->allows('getEvent')
            ->returns('pusher:subscribe');

        $this->mockMessage->allows('getData')
            ->returns([
                'channel' => 'test-channel',
                'auth' => 'test-auth',
                'channel_data' => '{"user_id": 123}',
            ]);

        // Mock ChannelOperationsManager
        $mockChannelOperationsManager = Double::for(ChannelOperationsManager::class);
        $mockChannelOperationsManager->expects('subscribeToChannelWithAuth')
            ->with($connection, 'test-channel', 'test-auth', '{"user_id": 123}');

        $server = Double::for(WebSocketServerInterface::class);
        $server->expects('getChannelOperationsManager')
            ->returns($mockChannelOperationsManager);

        $connection->expects('send')
            ->with(Argument::satisfies(function ($data): bool {
                $decoded = json_decode($data, true);

                return $decoded['event'] === 'pusher_internal:subscription_succeeded'
                    && $decoded['channel'] === 'test-channel';
            }));

        $this->pusherEventHandler->setServer($server);
        $this->pusherEventHandler->handle($connection, $this->mockMessage);
    }

    /**
     * Test handle method with subscribe event missing channel
     *
     * @return void
     */
    public function testHandleWithSubscribeEventMissingChannel(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockMessage->allows('getEvent')
            ->returns('pusher:subscribe');

        $this->mockMessage->allows('getData')
            ->returns([]);

        $server = Double::for(WebSocketServerInterface::class);
        $server->expects('subscribeToChannelWithAuth')->never();

        $this->pusherEventHandler->setServer($server);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with subscribe event server not available
     *
     * @return void
     */
    public function testHandleWithSubscribeEventServerNotAvailable(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockMessage->allows('getEvent')
            ->returns('pusher:subscribe');

        $this->mockMessage->allows('getData')
            ->returns(['channel' => 'test-channel']);

        // Create a stub server that returns null for getApplicationManager
        $mockServer = Double::for(WebSocketServerInterface::class);
        $mockServer->allows('getApplicationManager')
            ->returns(null);

        $this->pusherEventHandler->setServer($mockServer);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with unsubscribe event
     *
     * @return void
     */
    public function testHandleWithUnsubscribeEvent(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockMessage->allows('getEvent')
            ->returns('pusher:unsubscribe');

        $this->mockMessage->allows('getData')
            ->returns(['channel' => 'test-channel']);

        $mockChannelOperationsManager = Double::for(ChannelOperationsManager::class);
        $mockChannelOperationsManager->expects('unsubscribeFromChannel')
            ->with($this->mockConnection, 'test-channel');

        $server = Double::for(WebSocketServerInterface::class);
        $server->expects('getChannelOperationsManager')
            ->returns($mockChannelOperationsManager);

        $this->pusherEventHandler->setServer($server);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with unsubscribe event missing channel
     *
     * @return void
     */
    public function testHandleWithUnsubscribeEventMissingChannel(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockMessage->allows('getEvent')
            ->returns('pusher:unsubscribe');

        $this->mockMessage->allows('getData')
            ->returns([]);

        $server = Double::for(WebSocketServerInterface::class);
        $server->expects('unsubscribeFromChannel')->never();

        $this->pusherEventHandler->setServer($server);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with client event
     *
     * @return void
     */
    public function testHandleWithClientEvent(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockMessage->allows('getEvent')
            ->returns('client-typing');

        $this->mockMessage->allows('getData')
            ->returns(['user' => 'test-user']);

        $this->mockMessage->allows('getChannel')
            ->returns('test-channel');

        $this->mockConnection->allows('getAttribute')
            ->with('app_id')
            ->returns('test-app-id');
        $this->mockConnection->allows('getAttribute')
            ->with('app_key')
            ->returns(null);

        $this->mockServer->allows('getApplicationManager')
            ->returns($this->mockApplicationManager);

        $this->pusherEventHandler->setServer($this->mockServer);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with client event missing channel
     *
     * @return void
     */
    public function testHandleWithClientEventMissingChannel(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockMessage->allows('getEvent')
            ->returns('client-typing');

        $this->mockMessage->allows('getChannel')
            ->returns(null);

        $this->pusherEventHandler->setServer($this->mockServer);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with unhandled event
     *
     * @return void
     */
    public function testHandleWithUnhandledEvent(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockMessage->allows('getEvent')
            ->returns('unknown:event');

        $this->pusherEventHandler->setServer($this->mockServer);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test getAppIdFromConnection with app_id attribute
     *
     * @return void
     */
    public function testGetAppIdFromConnectionWithAppIdAttribute(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockConnection->allows('getAttribute')
            ->with('app_id')
            ->returns('test-app-id');

        $this->pusherEventHandler->setServer($this->mockServer);

        $reflection = new ReflectionClass($this->pusherEventHandler);
        $method = $reflection->getMethod('getAppIdFromConnection');

        $result = $method->invoke($this->pusherEventHandler, $this->mockConnection);
        $this->assertEquals('test-app-id', $result);
    }

    /**
     * Test getAppIdFromConnection with app_key attribute
     *
     * @return void
     */
    public function testGetAppIdFromConnectionWithAppKeyAttribute(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockConnection->allows('getAttribute')
            ->with('app_id')
            ->returns(null);
        $this->mockConnection->allows('getAttribute')
            ->with('app_key')
            ->returns('test-app-key');

        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplicationByKey')
            ->with('test-app-key')
            ->returns(['id' => 'test-app-id']);

        $server = Double::for(WebSocketServerInterface::class);
        $server->allows('getApplicationManager')
            ->returns($applicationManager);

        $this->pusherEventHandler->setServer($server);

        $reflection = new ReflectionClass($this->pusherEventHandler);
        $method = $reflection->getMethod('getAppIdFromConnection');
        $result = $method->invoke($this->pusherEventHandler, $this->mockConnection);
        $this->assertEquals('test-app-id', $result);
    }

    /**
     * Test getAppIdFromConnection with first available app
     *
     * @return void
     */
    public function testGetAppIdFromConnectionWithFirstAvailableApp(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockConnection->allows('getAttribute')
            ->with('app_id')
            ->returns(null);
        $this->mockConnection->allows('getAttribute')
            ->with('app_key')
            ->returns(null);

        $this->mockServer->allows('getApplicationManager')
            ->returns($this->mockApplicationManager);

        $this->mockApplicationManager->allows('getApplications')
            ->returns(['app1' => ['id' => 'test-app-id']]);

        $this->pusherEventHandler->setServer($this->mockServer);

        $reflection = new ReflectionClass($this->pusherEventHandler);
        $method = $reflection->getMethod('getAppIdFromConnection');

        $result = $method->invoke($this->pusherEventHandler, $this->mockConnection);
        $this->assertEquals('test-app-id', $result);
    }

    /**
     * Test getAppIdFromConnection with no app found
     *
     * @return void
     */
    public function testGetAppIdFromConnectionWithNoAppFound(): void
    {
        $this->mockConnection->allows('getId')
            ->returns('test-connection-id');

        $this->mockConnection->allows('getAttribute')
            ->with('app_id')
            ->returns(null);
        $this->mockConnection->allows('getAttribute')
            ->with('app_key')
            ->returns(null);

        $this->mockServer->allows('getApplicationManager')
            ->returns($this->mockApplicationManager);

        $this->mockApplicationManager->allows('getApplications')
            ->returns([]);

        $this->pusherEventHandler->setServer($this->mockServer);

        $reflection = new ReflectionClass($this->pusherEventHandler);
        $method = $reflection->getMethod('getAppIdFromConnection');

        $result = $method->invoke($this->pusherEventHandler, $this->mockConnection);
        $this->assertNull($result);
    }

    /**
     * Test getApplicationManager method
     *
     * @return void
     */
    public function testGetApplicationManager(): void
    {
        $this->mockServer->allows('getApplicationManager')
            ->returns($this->mockApplicationManager);

        $this->pusherEventHandler->setServer($this->mockServer);

        $reflection = new ReflectionClass($this->pusherEventHandler);
        $method = $reflection->getMethod('getApplicationManager');

        $result = $method->invoke($this->pusherEventHandler);
        $this->assertSame($this->mockApplicationManager, $result);
    }

    /**
     * Test getApplicationManager method with no server
     *
     * @return void
     */
    public function testGetApplicationManagerWithNoServer(): void
    {
        // Create a stub server that returns null for getApplicationManager
        $mockServer = Double::for(WebSocketServerInterface::class);
        $mockServer->allows('getApplicationManager')
            ->returns(null);

        $this->pusherEventHandler->setServer($mockServer);

        $reflectionMethod = new ReflectionClass($this->pusherEventHandler);
        $method = $reflectionMethod->getMethod('getApplicationManager');

        $result = $method->invoke($this->pusherEventHandler);
        $this->assertNull($result);
    }
}
