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
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;

/**
 * PusherEventHandler Test Case
 */
class PusherEventHandlerTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Handler\PusherEventHandler
     */
    protected PusherEventHandler $pusherEventHandler;

    /**
     * Stub WebSocket server
     *
     * @var \Crustum\BlazeCast\WebSocket\WebSocketServerInterface&\PHPUnit\Framework\MockObject\Stub
     */
    protected WebSocketServerInterface&Stub $mockServer;

    /**
     * Stub connection
     *
     * @var \Crustum\BlazeCast\WebSocket\Connection&\PHPUnit\Framework\MockObject\Stub
     */
    protected Connection&Stub $mockConnection;

    /**
     * Stub message
     *
     * @var \Crustum\BlazeCast\WebSocket\Protocol\Message&\PHPUnit\Framework\MockObject\Stub
     */
    protected Message&Stub $mockMessage;

    /**
     * Stub application manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager&\PHPUnit\Framework\MockObject\Stub
     */
    protected ApplicationManager&Stub $mockApplicationManager;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServer = $this->createStub(WebSocketServerInterface::class);
        $this->mockConnection = $this->createStub(Connection::class);
        $this->mockMessage = $this->createStub(Message::class);
        $this->mockApplicationManager = $this->createStub(ApplicationManager::class);

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

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
        $this->mockMessage->method('getEvent')
            ->willReturn('pusher:ping');

        $this->assertTrue($this->pusherEventHandler->canHandle($this->mockMessage));
    }

    /**
     * Test canHandle method with client events
     *
     * @return void
     */
    public function testCanHandleWithClientEvents(): void
    {
        $this->mockMessage->method('getEvent')
            ->willReturn('client-typing');

        $this->assertTrue($this->pusherEventHandler->canHandle($this->mockMessage));
    }

    /**
     * Test canHandle method with invalid events
     *
     * @return void
     */
    public function testCanHandleWithInvalidEvents(): void
    {
        $this->mockMessage->method('getEvent')
            ->willReturn('invalid:event');

        $this->assertFalse($this->pusherEventHandler->canHandle($this->mockMessage));
    }

    /**
     * Test handle method with ping event
     *
     * @return void
     */
    public function testHandleWithPingEvent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->method('getEvent')
            ->willReturn('pusher:ping');

        $connection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($data): bool {
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
        $connection = $this->createMock(Connection::class);
        $connection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->method('getEvent')
            ->willReturn('pusher:subscribe');

        $this->mockMessage->method('getData')
            ->willReturn([
                'channel' => 'test-channel',
                'auth' => 'test-auth',
                'channel_data' => '{"user_id": 123}',
            ]);

        // Mock ChannelOperationsManager
        $mockChannelOperationsManager = $this->createMock(ChannelOperationsManager::class);
        $mockChannelOperationsManager->expects($this->once())
            ->method('subscribeToChannelWithAuth')
            ->with($connection, 'test-channel', 'test-auth', '{"user_id": 123}');

        $server = $this->createMock(WebSocketServerInterface::class);
        $server->expects($this->once())
            ->method('getChannelOperationsManager')
            ->willReturn($mockChannelOperationsManager);

        $connection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($data): bool {
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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->method('getEvent')
            ->willReturn('pusher:subscribe');

        $this->mockMessage->method('getData')
            ->willReturn([]);

        $server = $this->createMock(WebSocketServerInterface::class);
        $server->expects($this->never())
            ->method('subscribeToChannelWithAuth');

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->method('getEvent')
            ->willReturn('pusher:subscribe');

        $this->mockMessage->method('getData')
            ->willReturn(['channel' => 'test-channel']);

        // Create a stub server that returns null for getApplicationManager
        $mockServer = $this->createStub(WebSocketServerInterface::class);
        $mockServer->method('getApplicationManager')
            ->willReturn(null);

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->method('getEvent')
            ->willReturn('pusher:unsubscribe');

        $this->mockMessage->method('getData')
            ->willReturn(['channel' => 'test-channel']);

        $mockChannelOperationsManager = $this->createMock(ChannelOperationsManager::class);
        $mockChannelOperationsManager->expects($this->once())
            ->method('unsubscribeFromChannel')
            ->with($this->mockConnection, 'test-channel');

        $server = $this->createMock(WebSocketServerInterface::class);
        $server->expects($this->once())
            ->method('getChannelOperationsManager')
            ->willReturn($mockChannelOperationsManager);

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->method('getEvent')
            ->willReturn('pusher:unsubscribe');

        $this->mockMessage->method('getData')
            ->willReturn([]);

        $server = $this->createMock(WebSocketServerInterface::class);
        $server->expects($this->never())
            ->method('unsubscribeFromChannel');

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->method('getEvent')
            ->willReturn('client-typing');

        $this->mockMessage->method('getData')
            ->willReturn(['user' => 'test-user']);

        $this->mockMessage->method('getChannel')
            ->willReturn('test-channel');

        $this->mockConnection->method('getAttribute')
            ->willReturnMap([
                ['app_id', null, 'test-app-id'],
                ['app_key', null, null],
            ]);

        $this->mockServer->method('getApplicationManager')
            ->willReturn($this->mockApplicationManager);

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->method('getEvent')
            ->willReturn('client-typing');

        $this->mockMessage->method('getChannel')
            ->willReturn(null);

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->method('getEvent')
            ->willReturn('unknown:event');

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockConnection->method('getAttribute')
            ->willReturnMap([
                ['app_id', null, 'test-app-id'],
            ]);

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockConnection->method('getAttribute')
            ->willReturnMap([
                ['app_id', null, null],
                ['app_key', null, 'test-app-key'],
            ]);

        $applicationManager = $this->createMock(ApplicationManager::class);
        $applicationManager->expects($this->once())
            ->method('getApplicationByKey')
            ->with('test-app-key')
            ->willReturn(['id' => 'test-app-id']);

        $server = $this->createStub(WebSocketServerInterface::class);
        $server->method('getApplicationManager')
            ->willReturn($applicationManager);

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockConnection->method('getAttribute')
            ->willReturnMap([
                ['app_id', null, null],
                ['app_key', null, null],
            ]);

        $this->mockServer->method('getApplicationManager')
            ->willReturn($this->mockApplicationManager);

        $this->mockApplicationManager->method('getApplications')
            ->willReturn(['app1' => ['id' => 'test-app-id']]);

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
        $this->mockConnection->method('getId')
            ->willReturn('test-connection-id');

        $this->mockConnection->method('getAttribute')
            ->willReturnMap([
                ['app_id', null, null],
                ['app_key', null, null],
            ]);

        $this->mockServer->method('getApplicationManager')
            ->willReturn($this->mockApplicationManager);

        $this->mockApplicationManager->method('getApplications')
            ->willReturn([]);

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
        $this->mockServer->method('getApplicationManager')
            ->willReturn($this->mockApplicationManager);

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
        $mockServer = $this->createStub(WebSocketServerInterface::class);
        $mockServer->method('getApplicationManager')
            ->willReturn(null);

        $this->pusherEventHandler->setServer($mockServer);

        $reflectionMethod = new ReflectionClass($this->pusherEventHandler);
        $method = $reflectionMethod->getMethod('getApplicationManager');

        $result = $method->invoke($this->pusherEventHandler);
        $this->assertNull($result);
    }
}
