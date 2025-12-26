<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Handler;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Handler\RedisPubSubHandler;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use Crustum\BlazeCast\WebSocket\Redis\PubSub;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for RedisPubSubHandler
 */
class RedisPubSubHandlerTest extends TestCase
{
    private RedisPubSubHandler $handler;
    private PubSub&MockObject $mockPubSub;
    private Server&MockObject $mockServer;
    private Connection&MockObject $mockConnection;

    protected function setUp(): void
    {
        $this->mockPubSub = $this->createMock(PubSub::class);
        $this->mockServer = $this->createMock(Server::class);
        $this->mockConnection = $this->createMock(Connection::class);

        $this->handler = new RedisPubSubHandler($this->mockPubSub);
        $this->handler->setServer($this->mockServer);
    }

    /**
     * @test
     */
    public function canBeInstantiatedWithoutPubSub(): void
    {
        $handler = new RedisPubSubHandler();
        $this->assertInstanceOf(RedisPubSubHandler::class, $handler);
    }

    /**
     * @test
     */
    public function canBeInstantiatedWithPubSub(): void
    {
        $handler = new RedisPubSubHandler($this->mockPubSub);
        $this->assertInstanceOf(RedisPubSubHandler::class, $handler);
    }

    /**
     * @test
     */
    public function setPubSubStoresPubSubInstance(): void
    {
        $handler = new RedisPubSubHandler();
        $handler->setPubSub($this->mockPubSub);

        $reflection = new ReflectionClass($handler);
        $pubSubProperty = $reflection->getProperty('pubSub');
        $storedPubSub = $pubSubProperty->getValue($handler);

        $this->assertSame($this->mockPubSub, $storedPubSub);
    }

    /**
     * @test
     */
    public function setServerStoresServerInstance(): void
    {
        $handler = new RedisPubSubHandler();
        $handler->setServer($this->mockServer);

        $reflection = new ReflectionClass($handler);
        $serverProperty = $reflection->getProperty('server');
        $storedServer = $serverProperty->getValue($handler);

        $this->assertSame($this->mockServer, $storedServer);
    }

    /**
     * @test
     */
    public function supportsSupportedEventTypes(): void
    {
        $this->assertTrue($this->handler->supports('redis.publish'));
        $this->assertTrue($this->handler->supports('redis.subscribe'));
        $this->assertTrue($this->handler->supports('redis.unsubscribe'));
    }

    /**
     * @test
     */
    public function doesNotSupportUnsupportedEventTypes(): void
    {
        $this->assertFalse($this->handler->supports('unsupported.event'));
        $this->assertFalse($this->handler->supports('subscribe'));
        $this->assertFalse($this->handler->supports('publish'));
    }

    /**
     * @test
     */
    public function handlePublishWithValidData(): void
    {
        $data = [
            'channel' => 'test-channel',
            'message' => 'test message',
        ];
        $message = new Message('redis.publish', $data);

        $this->mockPubSub->expects($this->once())
            ->method('publish')
            ->with('test-channel', 'test message');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('redis.published'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handlePublishWithArrayMessage(): void
    {
        $data = [
            'channel' => 'test-channel',
            'message' => ['type' => 'notification', 'content' => 'hello'],
        ];
        $message = new Message('redis.publish', $data);

        $expectedJson = json_encode(['type' => 'notification', 'content' => 'hello']);

        $this->mockPubSub->expects($this->once())
            ->method('publish')
            ->with('test-channel', $expectedJson);

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('redis.published'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handlePublishWithMissingChannel(): void
    {
        $data = ['message' => 'test message'];
        $message = new Message('redis.publish', $data);

        $this->mockPubSub->expects($this->never())
            ->method('publish');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Missing channel or message'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handlePublishWithMissingMessage(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.publish', $data);

        $this->mockPubSub->expects($this->never())
            ->method('publish');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Missing channel or message'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleSubscribeWithValidData(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.subscribe', $data);

        $this->mockConnection->expects($this->once())
            ->method('getId')
            ->willReturn('conn-123');

        $this->mockConnection->expects($this->once())
            ->method('getAttribute')
            ->with('redis_channels', [])
            ->willReturn([]);

        $this->mockConnection->expects($this->once())
            ->method('setAttribute')
            ->with('redis_channels', ['test-channel']);

        $this->mockPubSub->expects($this->once())
            ->method('subscribe')
            ->with('test-channel', $this->isType('callable'));

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('redis.subscribed'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleSubscribeWithAlreadySubscribedChannel(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.subscribe', $data);

        $this->mockConnection->expects($this->once())
            ->method('getId')
            ->willReturn('conn-123');

        $this->mockConnection->expects($this->once())
            ->method('getAttribute')
            ->with('redis_channels', [])
            ->willReturn(['test-channel']);

        $this->mockConnection->expects($this->never())
            ->method('setAttribute');

        $this->mockPubSub->expects($this->never())
            ->method('subscribe');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Already subscribed'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleSubscribeWithMissingChannel(): void
    {
        $data = [];
        $message = new Message('redis.subscribe', $data);

        $this->mockPubSub->expects($this->never())
            ->method('subscribe');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Missing channel'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleUnsubscribeWithValidData(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.unsubscribe', $data);

        $this->mockConnection->expects($this->once())
            ->method('getId')
            ->willReturn('conn-123');

        $this->mockConnection->expects($this->once())
            ->method('getAttribute')
            ->with('redis_channels', [])
            ->willReturn(['test-channel', 'other-channel']);

        $this->mockConnection->expects($this->once())
            ->method('setAttribute')
            ->with('redis_channels', [1 => 'other-channel']);

        $this->mockPubSub->expects($this->once())
            ->method('unsubscribe')
            ->with('test-channel');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('redis.unsubscribed'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleUnsubscribeWithMissingChannel(): void
    {
        $data = [];
        $message = new Message('redis.unsubscribe', $data);

        $this->mockPubSub->expects($this->never())
            ->method('unsubscribe');

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Missing channel'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleWithNoPubSubInstance(): void
    {
        $handler = new RedisPubSubHandler();
        $message = new Message('redis.publish', ['channel' => 'test', 'message' => 'test']);

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Redis PubSub is not available'));

        $handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handlePublishWithException(): void
    {
        $data = [
            'channel' => 'test-channel',
            'message' => 'test message',
        ];
        $message = new Message('redis.publish', $data);

        $this->mockPubSub->expects($this->once())
            ->method('publish')
            ->willThrowException(new Exception('Redis connection failed'));

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Failed to publish message to Redis'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleSubscribeWithException(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.subscribe', $data);

        $this->mockConnection->expects($this->once())
            ->method('getId')
            ->willReturn('conn-123');

        $this->mockConnection->expects($this->once())
            ->method('getAttribute')
            ->with('redis_channels', [])
            ->willReturn([]);

        $this->mockPubSub->expects($this->once())
            ->method('subscribe')
            ->willThrowException(new Exception('Redis connection failed'));

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Failed to subscribe to Redis channel'));

        $this->handler->handle($this->mockConnection, $message);
    }

    /**
     * @test
     */
    public function handleUnsubscribeWithException(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.unsubscribe', $data);

        $this->mockConnection->expects($this->once())
            ->method('getId')
            ->willReturn('conn-123');

        $this->mockConnection->expects($this->once())
            ->method('getAttribute')
            ->with('redis_channels', [])
            ->willReturn(['test-channel']);

        $this->mockPubSub->expects($this->once())
            ->method('unsubscribe')
            ->willThrowException(new Exception('Redis connection failed'));

        $this->mockConnection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Failed to unsubscribe from Redis channel'));

        $this->handler->handle($this->mockConnection, $message);
    }
}
