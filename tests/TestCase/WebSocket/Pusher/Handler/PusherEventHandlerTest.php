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
     * Mock WebSocket server
     *
     * @var \Crustum\BlazeCast\WebSocket\WebSocketServerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    protected WebSocketServerInterface $mockServer;

    /**
     * Mock connection
     *
     * @var \Crustum\BlazeCast\WebSocket\Connection&\PHPUnit\Framework\MockObject\MockObject
     */
    protected Connection $mockConnection;

    /**
     * Mock message
     *
     * @var \Crustum\BlazeCast\WebSocket\Protocol\Message&\PHPUnit\Framework\MockObject\MockObject
     */
    protected Message $mockMessage;

    /**
     * Mock application manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager&\PHPUnit\Framework\MockObject\MockObject
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

        $this->mockServer = $this->createMock(WebSocketServerInterface::class);
        $this->mockConnection = $this->createMock(Connection::class);
        $this->mockMessage = $this->createMock(Message::class);
        $this->mockApplicationManager = $this->createMock(ApplicationManager::class);

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
        $this->mockConnection->expects($this->any())
            ->method('getId')
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
        $this->mockConnection->expects($this->any())
            ->method('getId')
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
        $this->mockMessage->expects($this->any())
            ->method('getEvent')
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
        $this->mockMessage->expects($this->any())
            ->method('getEvent')
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
        $this->mockMessage->expects($this->any())
            ->method('getEvent')
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
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->expects($this->any())
            ->method('getEvent')
            ->willReturn('pusher:ping');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($data) {
                $decoded = json_decode($data, true);

                return $decoded['event'] === 'pusher:pong' && $decoded['data'] === '{}';
            }));

        $this->pusherEventHandler->setServer($this->mockServer);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with subscribe event
     *
     * @return void
     */
    public function testHandleWithSubscribeEvent(): void
    {
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->expects($this->any())
            ->method('getEvent')
            ->willReturn('pusher:subscribe');

        $this->mockMessage->expects($this->any())
            ->method('getData')
            ->willReturn([
                'channel' => 'test-channel',
                'auth' => 'test-auth',
                'channel_data' => '{"user_id": 123}',
            ]);

        // Mock ChannelOperationsManager
        $mockChannelOperationsManager = $this->createMock(ChannelOperationsManager::class);
        $mockChannelOperationsManager->expects($this->once())
            ->method('subscribeToChannelWithAuth')
            ->with($this->mockConnection, 'test-channel', 'test-auth', '{"user_id": 123}');

        $this->mockServer->expects($this->once())
            ->method('getChannelOperationsManager')
            ->willReturn($mockChannelOperationsManager);

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($data) {
                $decoded = json_decode($data, true);

                return $decoded['event'] === 'pusher_internal:subscription_succeeded'
                    && $decoded['channel'] === 'test-channel';
            }));

        $this->pusherEventHandler->setServer($this->mockServer);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with subscribe event missing channel
     *
     * @return void
     */
    public function testHandleWithSubscribeEventMissingChannel(): void
    {
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->expects($this->any())
            ->method('getEvent')
            ->willReturn('pusher:subscribe');

        $this->mockMessage->expects($this->any())
            ->method('getData')
            ->willReturn([]);

        $this->mockServer->expects($this->never())
            ->method('subscribeToChannelWithAuth');

        $this->pusherEventHandler->setServer($this->mockServer);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with subscribe event server not available
     *
     * @return void
     */
    public function testHandleWithSubscribeEventServerNotAvailable(): void
    {
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->expects($this->any())
            ->method('getEvent')
            ->willReturn('pusher:subscribe');

        $this->mockMessage->expects($this->any())
            ->method('getData')
            ->willReturn(['channel' => 'test-channel']);

        // Create a mock server that returns null for getApplicationManager
        $mockServer = $this->createMock(WebSocketServerInterface::class);
        $mockServer->expects($this->any())
            ->method('getApplicationManager')
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
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->expects($this->any())
            ->method('getEvent')
            ->willReturn('pusher:unsubscribe');

        $this->mockMessage->expects($this->any())
            ->method('getData')
            ->willReturn(['channel' => 'test-channel']);

        $mockChannelOperationsManager = $this->createMock(ChannelOperationsManager::class);
        $mockChannelOperationsManager->expects($this->once())
            ->method('unsubscribeFromChannel')
            ->with($this->mockConnection, 'test-channel');

        $this->mockServer->expects($this->once())
            ->method('getChannelOperationsManager')
            ->willReturn($mockChannelOperationsManager);

        $this->pusherEventHandler->setServer($this->mockServer);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with unsubscribe event missing channel
     *
     * @return void
     */
    public function testHandleWithUnsubscribeEventMissingChannel(): void
    {
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->expects($this->any())
            ->method('getEvent')
            ->willReturn('pusher:unsubscribe');

        $this->mockMessage->expects($this->any())
            ->method('getData')
            ->willReturn([]);

        $this->mockServer->expects($this->never())
            ->method('unsubscribeFromChannel');

        $this->pusherEventHandler->setServer($this->mockServer);
        $this->pusherEventHandler->handle($this->mockConnection, $this->mockMessage);
    }

    /**
     * Test handle method with client event
     *
     * @return void
     */
    public function testHandleWithClientEvent(): void
    {
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->expects($this->any())
            ->method('getEvent')
            ->willReturn('client-typing');

        $this->mockMessage->expects($this->any())
            ->method('getData')
            ->willReturn(['user' => 'test-user']);

        $this->mockMessage->expects($this->any())
            ->method('getChannel')
            ->willReturn('test-channel');

        $this->mockConnection->expects($this->any())
            ->method('getAttribute')
            ->willReturnMap([
                ['app_id', null, 'test-app-id'],
                ['app_key', null, null],
            ]);

        $this->mockServer->expects($this->any())
            ->method('getApplicationManager')
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
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->expects($this->any())
            ->method('getEvent')
            ->willReturn('client-typing');

        $this->mockMessage->expects($this->any())
            ->method('getChannel')
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
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockMessage->expects($this->any())
            ->method('getEvent')
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
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockConnection->expects($this->any())
            ->method('getAttribute')
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
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockConnection->expects($this->any())
            ->method('getAttribute')
            ->willReturnMap([
                ['app_id', null, null],
                ['app_key', null, 'test-app-key'],
            ]);

        $this->mockServer->expects($this->any())
            ->method('getApplicationManager')
            ->willReturn($this->mockApplicationManager);

        $this->mockApplicationManager->expects($this->any())
            ->method('getApplicationByKey')
            ->with('test-app-key')
            ->willReturn(['id' => 'test-app-id']);

        $this->pusherEventHandler->setServer($this->mockServer);

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
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockConnection->expects($this->any())
            ->method('getAttribute')
            ->willReturnMap([
                ['app_id', null, null],
                ['app_key', null, null],
            ]);

        $this->mockServer->expects($this->any())
            ->method('getApplicationManager')
            ->willReturn($this->mockApplicationManager);

        $this->mockApplicationManager->expects($this->any())
            ->method('getApplications')
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
        $this->mockConnection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-id');

        $this->mockConnection->expects($this->any())
            ->method('getAttribute')
            ->willReturnMap([
                ['app_id', null, null],
                ['app_key', null, null],
            ]);

        $this->mockServer->expects($this->any())
            ->method('getApplicationManager')
            ->willReturn($this->mockApplicationManager);

        $this->mockApplicationManager->expects($this->any())
            ->method('getApplications')
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
        $this->mockServer->expects($this->any())
            ->method('getApplicationManager')
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
        // Create a mock server that returns null for getApplicationManager
        $mockServer = $this->createMock(WebSocketServerInterface::class);
        $mockServer->expects($this->any())
            ->method('getApplicationManager')
            ->willReturn(null);

        $this->pusherEventHandler->setServer($mockServer);

        $reflectionMethod = new ReflectionClass($this->pusherEventHandler);
        $method = $reflectionMethod->getMethod('getApplicationManager');

        $result = $method->invoke($this->pusherEventHandler);
        $this->assertNull($result);
    }
}
