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
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for HandlerRegistry
 */
class HandlerRegistryTest extends TestCase
{
    use VerifiesDoubles;

    private HandlerRegistry $registry;

    private Server $stubServer;

    private Connection $mockConnection;

    protected function setUp(): void
    {
        $this->registry = new HandlerRegistry();
        $this->stubServer = Double::for(Server::class);
        $this->mockConnection = Double::for(Connection::class);
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
        $handler1 = Double::for(HandlerInterface::class);
        $handler2 = Double::for(HandlerInterface::class);

        $this->registry->register($handler1);
        $this->registry->register($handler2);

        // Both handlers should receive the server when it's set
        $handler1->expects('setServer')
            ->with($this->stubServer);

        $handler2->expects('setServer')
            ->with($this->stubServer);

        $this->registry->setServer($this->stubServer);
    }

    #[Test]
    public function registerAddsHandlerToRegistry(): void
    {
        $handler = Double::for(HandlerInterface::class);

        $this->registry->register($handler);

        $handlers = $this->registry->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertSame($handler, $handlers[0]);
    }

    #[Test]
    public function registerSetsServerOnHandlerIfServerExists(): void
    {
        $this->registry->setServer($this->stubServer);

        $handler = Double::for(HandlerInterface::class);
        $handler->expects('setServer')
            ->with($this->stubServer);

        $this->registry->register($handler);
    }

    #[Test]
    public function registerDoesNotSetServerOnHandlerIfNoServerExists(): void
    {
        $handler = Double::for(HandlerInterface::class);
        $handler->expects('setServer')->never();

        $this->registry->register($handler);
    }

    #[Test]
    public function getHandlersReturnsAllRegisteredHandlers(): void
    {
        $handler1 = Double::for(HandlerInterface::class);
        $handler2 = Double::for(HandlerInterface::class);
        $handler3 = Double::for(HandlerInterface::class);

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

        $handler = Double::for(HandlerInterface::class);
        $handler->expects('supports')
            ->with('test_event')
            ->returns(true);

        $handler->expects('handle')
            ->with($this->mockConnection, $message);

        $this->registry->register($handler);

        $result = $this->registry->handle($this->mockConnection, $message);
        $this->assertTrue($result);
    }

    #[Test]
    public function handleReturnsFalseWhenNoHandlerSupportsMessage(): void
    {
        $message = new Message('unsupported_event', ['data' => 'test']);

        $handler = Double::for(HandlerInterface::class);
        $handler->expects('supports')
            ->with('unsupported_event')
            ->returns(false);

        $handler->expects('handle')->never();

        $this->registry->register($handler);

        $result = $this->registry->handle($this->mockConnection, $message);
        $this->assertFalse($result);
    }

    #[Test]
    public function handleUsesFirstSupportingHandler(): void
    {
        $message = new Message('test_event', ['data' => 'test']);

        $handler1 = Double::for(HandlerInterface::class);
        $handler1->expects('supports')
            ->with('test_event')
            ->returns(true);
        $handler1->expects('handle')
            ->with($this->mockConnection, $message);

        $handler2 = Double::for(HandlerInterface::class);
        $handler2->expects('supports')->never();
        $handler2->expects('handle')->never();

        $this->registry->register($handler1);
        $this->registry->register($handler2);

        $result = $this->registry->handle($this->mockConnection, $message);
        $this->assertTrue($result);
    }

    #[Test]
    public function handleChecksHandlersInRegistrationOrder(): void
    {
        $message = new Message('test_event', ['data' => 'test']);

        $handler1 = Double::for(HandlerInterface::class);
        $handler1->expects('supports')
            ->with('test_event')
            ->returns(false);
        $handler1->expects('handle')->never();

        $handler2 = Double::for(HandlerInterface::class);
        $handler2->expects('supports')
            ->with('test_event')
            ->returns(true);
        $handler2->expects('handle')
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
        $connection = Double::for(Connection::class);
        $connection->expects('updateActivity');
        $connection->expects('send')
            ->with(Argument::satisfies(function ($json): bool {
                $decoded = json_decode($json, true);

                return $decoded['event'] === 'pong';
            }));

        $result = $this->registry->handle($connection, $pingMessage);
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
        $connection = Double::for(Connection::class);
        $connection->expects('send')
            ->with(Argument::satisfies(function ($json): bool {
                $decoded = json_decode($json, true);

                return $decoded['event'] === 'echo';
            }));

        $result = $this->registry->handle($connection, $unknownMessage);
        $this->assertTrue($result);
    }

    #[Test]
    public function multipleHandlersCanBeRegistered(): void
    {
        $handlers = [];
        for ($i = 0; $i < 5; $i++) {
            $handler = Double::for(HandlerInterface::class);
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
        $handler1 = Double::for(HandlerInterface::class);
        $handler2 = Double::for(HandlerInterface::class);
        $handler3 = Double::for(HandlerInterface::class);

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

        $handler = Double::for(HandlerInterface::class);
        $handler->expects('supports')
            ->with($eventType)
            ->returns(true);

        $handler->expects('handle')
            ->with(
                $this->mockConnection,
                Argument::satisfies(fn($msg): bool => $msg instanceof Message &&
                       $msg->getEvent() === $eventType &&
                       $msg->getData() === $data &&
                       $msg->getChannel() === $channel),
            );

        $this->registry->register($handler);
        $this->registry->handle($this->mockConnection, $message);
    }

    #[Test]
    public function registryWorksWithMixedHandlerTypes(): void
    {
        $mockHandler = Double::for(HandlerInterface::class);
        $mockHandler->allows('supports')->returns(false);

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
