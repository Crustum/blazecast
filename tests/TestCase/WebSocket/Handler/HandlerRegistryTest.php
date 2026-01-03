<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Handler;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Handler\DefaultHandler;
use Crustum\BlazeCast\WebSocket\Handler\HandlerInterface;
use Crustum\BlazeCast\WebSocket\Handler\HandlerRegistry;
use Crustum\BlazeCast\WebSocket\Handler\PingHandler;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for HandlerRegistry
 */
class HandlerRegistryTest extends TestCase
{
    private HandlerRegistry $registry;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&Server
     */
    private Server $stubServer;
    private Connection&MockObject $mockConnection;

    protected function setUp(): void
    {
        $this->registry = new HandlerRegistry();
        $this->stubServer = $this->createStub(Server::class);
        $this->mockConnection = $this->createMock(Connection::class);
    }

    #[Test]
    public function canBeInstantiated(): void
    {
        $registry = new HandlerRegistry();
        $this->assertInstanceOf(HandlerRegistry::class, $registry);
    }

    #[Test]
    public function setServerStoresServerInstance(): void
    {
        $this->registry->setServer($this->stubServer);

        $reflection = new ReflectionClass($this->registry);
        $serverProperty = $reflection->getProperty('server');
        $storedServer = $serverProperty->getValue($this->registry);

        $this->assertSame($this->stubServer, $storedServer);
    }

    #[Test]
    public function setServerUpdatesAllRegisteredHandlers(): void
    {
        /** @var HandlerInterface&MockObject $handler1 */
        $handler1 = $this->createMock(HandlerInterface::class);
        /** @var HandlerInterface&MockObject $handler2 */
        $handler2 = $this->createMock(HandlerInterface::class);

        $this->registry->register($handler1);
        $this->registry->register($handler2);

        // Both handlers should receive the server when it's set
        $handler1->expects($this->once())
            ->method('setServer')
            ->with($this->stubServer);

        $handler2->expects($this->once())
            ->method('setServer')
            ->with($this->stubServer);

        $this->registry->setServer($this->stubServer);
    }

    #[Test]
    public function registerAddsHandlerToRegistry(): void
    {
        /** @var HandlerInterface $handler */
        $handler = $this->createStub(HandlerInterface::class);

        $this->registry->register($handler);

        $handlers = $this->registry->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertSame($handler, $handlers[0]);
    }

    #[Test]
    public function registerSetsServerOnHandlerIfServerExists(): void
    {
        $this->registry->setServer($this->stubServer);

        /** @var HandlerInterface&MockObject $handler */
        $handler = $this->createMock(HandlerInterface::class);
        $handler->expects($this->once())
            ->method('setServer')
            ->with($this->stubServer);

        $this->registry->register($handler);
    }

    #[Test]
    public function registerDoesNotSetServerOnHandlerIfNoServerExists(): void
    {
        /** @var HandlerInterface&MockObject $handler */
        $handler = $this->createMock(HandlerInterface::class);
        $handler->expects($this->never())
            ->method('setServer');

        $this->registry->register($handler);
    }

    #[Test]
    public function getHandlersReturnsAllRegisteredHandlers(): void
    {
        /** @var HandlerInterface $handler1 */
        $handler1 = $this->createStub(HandlerInterface::class);
        /** @var HandlerInterface $handler2 */
        $handler2 = $this->createStub(HandlerInterface::class);
        /** @var HandlerInterface $handler3 */
        $handler3 = $this->createStub(HandlerInterface::class);

        $this->registry->register($handler1);
        $this->registry->register($handler2);
        $this->registry->register($handler3);

        $handlers = $this->registry->getHandlers();
        $this->assertCount(3, $handlers);
        $this->assertSame($handler1, $handlers[0]);
        $this->assertSame($handler2, $handlers[1]);
        $this->assertSame($handler3, $handlers[2]);
    }

    #[Test]
    public function getHandlersReturnsEmptyArrayWhenNoHandlers(): void
    {
        $handlers = $this->registry->getHandlers();
        $this->assertEmpty($handlers);
    }

    #[Test]
    public function handleReturnsTrueWhenHandlerProcessesMessage(): void
    {
        $message = new Message('test_event', ['data' => 'test']);

        /** @var HandlerInterface&MockObject $handler */
        $handler = $this->createMock(HandlerInterface::class);
        $handler->expects($this->once())
            ->method('supports')
            ->with('test_event')
            ->willReturn(true);

        $handler->expects($this->once())
            ->method('handle')
            ->with($this->mockConnection, $message);

        $this->registry->register($handler);

        $result = $this->registry->handle($this->mockConnection, $message);
        $this->assertTrue($result);
    }

    #[Test]
    public function handleReturnsFalseWhenNoHandlerSupportsMessage(): void
    {
        $message = new Message('unsupported_event', ['data' => 'test']);

        /** @var HandlerInterface&MockObject $handler */
        $handler = $this->createMock(HandlerInterface::class);
        $handler->expects($this->once())
            ->method('supports')
            ->with('unsupported_event')
            ->willReturn(false);

        $handler->expects($this->never())
            ->method('handle');

        $this->registry->register($handler);

        $result = $this->registry->handle($this->mockConnection, $message);
        $this->assertFalse($result);
    }

    #[Test]
    public function handleUsesFirstSupportingHandler(): void
    {
        $message = new Message('test_event', ['data' => 'test']);

        /** @var HandlerInterface&MockObject $handler1 */
        $handler1 = $this->createMock(HandlerInterface::class);
        $handler1->expects($this->once())
            ->method('supports')
            ->with('test_event')
            ->willReturn(true);
        $handler1->expects($this->once())
            ->method('handle')
            ->with($this->mockConnection, $message);

        /** @var HandlerInterface&MockObject $handler2 */
        $handler2 = $this->createMock(HandlerInterface::class);
        $handler2->expects($this->never())
            ->method('supports');
        $handler2->expects($this->never())
            ->method('handle');

        $this->registry->register($handler1);
        $this->registry->register($handler2);

        $result = $this->registry->handle($this->mockConnection, $message);
        $this->assertTrue($result);
    }

    #[Test]
    public function handleChecksHandlersInRegistrationOrder(): void
    {
        $message = new Message('test_event', ['data' => 'test']);

        /** @var HandlerInterface&MockObject $handler1 */
        $handler1 = $this->createMock(HandlerInterface::class);
        $handler1->expects($this->once())
            ->method('supports')
            ->with('test_event')
            ->willReturn(false);
        $handler1->expects($this->never())
            ->method('handle');

        /** @var HandlerInterface&MockObject $handler2 */
        $handler2 = $this->createMock(HandlerInterface::class);
        $handler2->expects($this->once())
            ->method('supports')
            ->with('test_event')
            ->willReturn(true);
        $handler2->expects($this->once())
            ->method('handle')
            ->with($this->mockConnection, $message);

        $this->registry->register($handler1);
        $this->registry->register($handler2);

        $result = $this->registry->handle($this->mockConnection, $message);
        $this->assertTrue($result);
    }

    #[Test]
    public function handleWorksWithRealHandlers(): void
    {
        $pingHandler = new PingHandler();
        $defaultHandler = new DefaultHandler();

        $this->registry->register($pingHandler);
        $this->registry->register($defaultHandler);

        $pingMessage = new Message('ping');
        $this->mockConnection->expects($this->once())
            ->method('updateActivity');
        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($json) {
                $decoded = json_decode($json, true);

                return $decoded['event'] === 'pong';
            }));

        $result = $this->registry->handle($this->mockConnection, $pingMessage);
        $this->assertTrue($result);
    }

    #[Test]
    public function handleWorksWithMultipleRealHandlers(): void
    {
        $pingHandler = new PingHandler();
        $defaultHandler = new DefaultHandler();

        $this->registry->register($pingHandler);
        $this->registry->register($defaultHandler);

        $unknownMessage = new Message('unknown_event', ['test' => 'data']);
        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($json) {
                $decoded = json_decode($json, true);

                return $decoded['event'] === 'echo';
            }));

        $result = $this->registry->handle($this->mockConnection, $unknownMessage);
        $this->assertTrue($result);
    }

    #[Test]
    public function multipleHandlersCanBeRegistered(): void
    {
        $handlers = [];
        for ($i = 0; $i < 5; $i++) {
            /** @var HandlerInterface $handler */
            $handler = $this->createStub(HandlerInterface::class);
            $handlers[] = $handler;
            $this->registry->register($handler);
        }

        $registeredHandlers = $this->registry->getHandlers();
        $this->assertCount(5, $registeredHandlers);

        foreach ($handlers as $index => $handler) {
            $this->assertSame($handler, $registeredHandlers[$index]);
        }
    }

    #[Test]
    public function handleWorksWithEmptyRegistry(): void
    {
        $message = new Message('test_event', ['data' => 'test']);

        $result = $this->registry->handle($this->mockConnection, $message);
        $this->assertFalse($result);
    }

    #[Test]
    public function registryMaintainsHandlerOrder(): void
    {
        /** @var HandlerInterface $handler1 */
        $handler1 = $this->createStub(HandlerInterface::class);
        /** @var HandlerInterface $handler2 */
        $handler2 = $this->createStub(HandlerInterface::class);
        /** @var HandlerInterface $handler3 */
        $handler3 = $this->createStub(HandlerInterface::class);

        // Register in specific order
        $this->registry->register($handler2);
        $this->registry->register($handler1);
        $this->registry->register($handler3);

        $handlers = $this->registry->getHandlers();

        // Should maintain registration order
        $this->assertSame($handler2, $handlers[0]);
        $this->assertSame($handler1, $handlers[1]);
        $this->assertSame($handler3, $handlers[2]);
    }

    #[Test]
    public function handlePassesCorrectParametersToHandler(): void
    {
        $eventType = 'custom_event';
        $data = ['user_id' => 123, 'content' => 'test message'];
        $channel = 'test-channel';
        $message = new Message($eventType, $data, $channel);

        /** @var HandlerInterface&MockObject $handler */
        $handler = $this->createMock(HandlerInterface::class);
        $handler->expects($this->once())
            ->method('supports')
            ->with($eventType)
            ->willReturn(true);

        $handler->expects($this->once())
            ->method('handle')
            ->with(
                $this->identicalTo($this->mockConnection),
                $this->callback(function ($msg) use ($eventType, $data, $channel) {
                    return $msg instanceof Message &&
                           $msg->getEvent() === $eventType &&
                           $msg->getData() === $data &&
                           $msg->getChannel() === $channel;
                }),
            );

        $this->registry->register($handler);
        $this->registry->handle($this->mockConnection, $message);
    }

    #[Test]
    public function registryWorksWithMixedHandlerTypes(): void
    {
        /** @var HandlerInterface&MockObject $mockHandler */
        $mockHandler = $this->createStub(HandlerInterface::class);
        $mockHandler->method('supports')->willReturn(false);

        $pingHandler = new PingHandler();
        $defaultHandler = new DefaultHandler();

        $this->registry->register($mockHandler);
        $this->registry->register($pingHandler);
        $this->registry->register($defaultHandler);

        $handlers = $this->registry->getHandlers();
        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(HandlerInterface::class, $handlers[0]);
        $this->assertInstanceOf(PingHandler::class, $handlers[1]);
        $this->assertInstanceOf(DefaultHandler::class, $handlers[2]);
    }
}
