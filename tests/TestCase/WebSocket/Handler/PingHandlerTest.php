<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Handler;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Handler\PingHandler;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for PingHandler
 */
class PingHandlerTest extends TestCase
{
    private PingHandler $handler;
    private Server&MockObject $mockServer;
    private Connection&MockObject $mockConnection;

    protected function setUp(): void
    {
        $this->handler = new PingHandler();
        $this->mockServer = $this->createMock(Server::class);
        $this->mockConnection = $this->createMock(Connection::class);

        $this->handler->setServer($this->mockServer);
    }

    /**
     * @test
     */
    public function canBeInstantiated(): void
    {
        $handler = new PingHandler();
        $this->assertInstanceOf(PingHandler::class, $handler);
    }

    /**
     * @test
     */
    public function setServerStoresServerInstance(): void
    {
        $handler = new PingHandler();
        $handler->setServer($this->mockServer);

        $reflection = new ReflectionClass($handler);
        $serverProperty = $reflection->getProperty('server');
        $storedServer = $serverProperty->getValue($handler);

        $this->assertSame($this->mockServer, $storedServer);
    }

    /**
     * @test
     */
    public function supportsPingEvent(): void
    {
        $this->assertTrue($this->handler->supports('ping'));
    }

    /**
     * @test
     */
    public function doesNotSupportOtherEvents(): void
    {
        $this->assertFalse($this->handler->supports('pong'));
        $this->assertFalse($this->handler->supports('subscribe'));
        $this->assertFalse($this->handler->supports('publish'));
        $this->assertFalse($this->handler->supports('authenticate'));
        $this->assertFalse($this->handler->supports(''));
        $this->assertFalse($this->handler->supports('unknown_event'));
    }

    /**
     * @test
     */
    public function handlePingUpdatesConnectionActivity(): void
    {
        $message = new Message('ping');

        $this->mockConnection->expects($this->once())
            ->method('updateActivity');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->isType('string'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handlePingSendsPongResponse(): void
    {
        $message = new Message('ping');

        $this->mockConnection->expects($this->once())
            ->method('updateActivity');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'pong' &&
                       isset($decoded['data']['time']) &&
                       isset($decoded['data']['server_time']) &&
                       is_float($decoded['data']['time']) &&
                       is_int($decoded['data']['server_time']);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function pongResponseIncludesAccurateTimestamps(): void
    {
        $message = new Message('ping');
        $beforeTime = microtime(true);
        $beforeServerTime = time();

        $this->mockConnection->expects($this->once())
            ->method('updateActivity');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) use ($beforeTime, $beforeServerTime) {
                $decoded = json_decode($jsonMessage, true);
                $time = $decoded['data']['time'] ?? 0;
                $serverTime = $decoded['data']['server_time'] ?? 0;

                return $time >= $beforeTime &&
                       $time <= microtime(true) + 0.1 &&
                       $serverTime >= $beforeServerTime &&
                       $serverTime <= time() + 1;
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handlePingWithData(): void
    {
        $pingData = ['client_time' => microtime(true), 'sequence' => 123];
        $message = new Message('ping', $pingData);

        $this->mockConnection->expects($this->once())
            ->method('updateActivity');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'pong' &&
                       isset($decoded['data']['time']) &&
                       isset($decoded['data']['server_time']);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handlePingWithChannel(): void
    {
        $message = new Message('ping', null, 'test-channel');

        $this->mockConnection->expects($this->once())
            ->method('updateActivity');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'pong' &&
                       isset($decoded['data']['time']) &&
                       isset($decoded['data']['server_time']);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function multiplePingHandlesWorkCorrectly(): void
    {
        $messages = [
            new Message('ping'),
            new Message('ping', ['seq' => 1]),
            new Message('ping', ['seq' => 2]),
        ];

        $this->mockConnection->expects($this->exactly(3))
            ->method('updateActivity');

        $this->mockConnection->expects($this->exactly(3))
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'pong';
            }));

        foreach ($messages as $message) {
            $this->handler->handle($this->mockConnection, $message);
        }
    }

    /**
     * @test
     */
    public function pongResponseIsValidJson(): void
    {
        $message = new Message('ping');

        $this->mockConnection->expects($this->once())
            ->method('updateActivity');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return json_last_error() === JSON_ERROR_NONE && is_array($decoded);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handlerMaintainsConnectionAliveness(): void
    {
        $message = new Message('ping');

        $this->mockConnection->expects($this->once())
            ->method('updateActivity');

        $this->mockConnection->expects($this->once())
            ->method('send');

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function getSupportedEventsReturnsCorrectArray(): void
    {
        $reflection = new ReflectionClass($this->handler);
        $supportedEventsProperty = $reflection->getProperty('supportedEvents');
        $supportedEvents = $supportedEventsProperty->getValue($this->handler);

        $this->assertIsArray($supportedEvents);
        $this->assertContains('ping', $supportedEvents);
        $this->assertCount(1, $supportedEvents);
    }

    /**
     * @test
     */
    public function handlerWorksWithoutServerSet(): void
    {
        $handler = new PingHandler();
        $message = new Message('ping');

        $this->mockConnection->expects($this->once())
            ->method('updateActivity');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'pong';
            }));

        $handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function pongDataStructureIsConsistent(): void
    {
        $message = new Message('ping');

        $this->mockConnection->expects($this->once())
            ->method('updateActivity');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                $this->assertArrayHasKey('event', $decoded);
                $this->assertArrayHasKey('data', $decoded);
                $this->assertArrayHasKey('time', $decoded['data']);
                $this->assertArrayHasKey('server_time', $decoded['data']);

                $this->assertIsString($decoded['event']);
                $this->assertIsArray($decoded['data']);
                $this->assertIsFloat($decoded['data']['time']);
                $this->assertIsInt($decoded['data']['server_time']);

                return true;
            }));

        $this->handler->handle($this->mockConnection, $message);
    }
}
