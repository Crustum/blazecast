<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Handler;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Handler\DefaultHandler;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for DefaultHandler
 */
class DefaultHandlerTest extends TestCase
{
    private DefaultHandler $handler;
    private Server&MockObject $mockServer;
    private Connection&MockObject $mockConnection;

    protected function setUp(): void
    {
        $this->handler = new DefaultHandler();
        $this->mockServer = $this->createMock(Server::class);
        $this->mockConnection = $this->createMock(Connection::class);

        $this->handler->setServer($this->mockServer);
    }

    /**
     * @test
     */
    public function canBeInstantiated(): void
    {
        $handler = new DefaultHandler();
        $this->assertInstanceOf(DefaultHandler::class, $handler);
    }

    /**
     * @test
     */
    public function setServerStoresServerInstance(): void
    {
        $handler = new DefaultHandler();
        $handler->setServer($this->mockServer);

        $reflection = new ReflectionClass($handler);
        $serverProperty = $reflection->getProperty('server');
        $storedServer = $serverProperty->getValue($handler);

        $this->assertSame($this->mockServer, $storedServer);
    }

    /**
     * @test
     */
    public function supportsAllEventTypes(): void
    {
        $this->assertTrue($this->handler->supports('any_event'));
        $this->assertTrue($this->handler->supports('custom_event'));
        $this->assertTrue($this->handler->supports('unknown_event'));
        $this->assertTrue($this->handler->supports(''));
        $this->assertTrue($this->handler->supports('test.event'));
    }

    /**
     * @test
     */
    public function handleEchoesMessageBackToClient(): void
    {
        $eventType = 'test_event';
        $data = ['key' => 'value', 'number' => 123];
        $message = new Message($eventType, $data);

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) use ($eventType, $data) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'echo' &&
                       $decoded['data']['original_event'] === $eventType &&
                       $decoded['data']['data'] === $data &&
                       isset($decoded['data']['timestamp']);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleIncludesTimestampInResponse(): void
    {
        $message = new Message('test_event', ['test' => 'data']);
        $beforeTime = time();

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) use ($beforeTime) {
                $decoded = json_decode($jsonMessage, true);
                $timestamp = $decoded['data']['timestamp'] ?? 0;

                return $timestamp >= $beforeTime && $timestamp <= time() + 1;
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleWorksWithEmptyData(): void
    {
        $message = new Message('empty_event');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'echo' &&
                       $decoded['data']['original_event'] === 'empty_event' &&
                       $decoded['data']['data'] === null &&
                       isset($decoded['data']['timestamp']);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleWorksWithComplexData(): void
    {
        $complexData = [
            'user' => ['id' => 123, 'name' => 'John'],
            'items' => [1, 2, 3],
            'metadata' => ['created' => '2025-06-14', 'version' => '1.0'],
        ];
        $message = new Message('complex_event', $complexData);

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) use ($complexData) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'echo' &&
                       $decoded['data']['original_event'] === 'complex_event' &&
                       $decoded['data']['data'] === $complexData &&
                       isset($decoded['data']['timestamp']);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleWorksWithChannelMessages(): void
    {
        $message = new Message('channel_event', ['content' => 'hello'], 'test-channel');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'echo' &&
                       $decoded['data']['original_event'] === 'channel_event' &&
                       $decoded['data']['data']['content'] === 'hello' &&
                       isset($decoded['data']['timestamp']);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleWorksWithStringData(): void
    {
        $message = new Message('string_event', 'simple string data');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'echo' &&
                       $decoded['data']['original_event'] === 'string_event' &&
                       $decoded['data']['data'] === 'simple string data' &&
                       isset($decoded['data']['timestamp']);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleWorksWithNumericData(): void
    {
        $message = new Message('numeric_event', 42);

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'echo' &&
                       $decoded['data']['original_event'] === 'numeric_event' &&
                       $decoded['data']['data'] === 42 &&
                       isset($decoded['data']['timestamp']);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleWorksWithBooleanData(): void
    {
        $message = new Message('boolean_event', true);

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($jsonMessage) {
                $decoded = json_decode($jsonMessage, true);

                return $decoded['event'] === 'echo' &&
                       $decoded['data']['original_event'] === 'boolean_event' &&
                       $decoded['data']['data'] === true &&
                       isset($decoded['data']['timestamp']);
            }));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function responseIsValidJson(): void
    {
        $message = new Message('json_test', ['test' => 'data']);

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
    public function handlerActsAsFallbackForAllEvents(): void
    {
        $eventTypes = [
            'unknown_event',
            'custom.event',
            'user_action',
            'system_notification',
            'error_event',
            'debug_message',
        ];

        foreach ($eventTypes as $eventType) {
            $this->assertTrue(
                $this->handler->supports($eventType),
                "DefaultHandler should support event type: {$eventType}",
            );
        }
    }

    /**
     * @test
     */
    public function multipleHandleCallsWorkCorrectly(): void
    {
        $messages = [
            new Message('event1', ['data' => 1]),
            new Message('event2', ['data' => 2]),
            new Message('event3', ['data' => 3]),
        ];

        $callCount = 0;
        $this->mockConnection->expects($this->exactly(3))
            ->method('send')
            ->willReturnCallback(function ($json) use (&$callCount) {
                $decoded = json_decode($json, true);
                $callCount++;

                switch ($callCount) {
                    case 1:
                        $this->assertEquals('event1', $decoded['data']['original_event']);
                        break;
                    case 2:
                        $this->assertEquals('event2', $decoded['data']['original_event']);
                        break;
                    case 3:
                        $this->assertEquals('event3', $decoded['data']['original_event']);
                        break;
                }
            });

        foreach ($messages as $message) {
            $this->handler->handle($this->mockConnection, $message);
        }
    }
}
