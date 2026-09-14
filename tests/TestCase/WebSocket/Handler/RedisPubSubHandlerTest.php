<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Handler;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Handler\RedisPubSubHandler;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use Crustum\BlazeCast\WebSocket\Redis\PubSub;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for RedisPubSubHandler
 */
class RedisPubSubHandlerTest extends TestCase
{
    private RedisPubSubHandler $handler;

    private PubSub $mockPubSub;

    /**
     * @var \PHPUnit\Framework\MockObject\Stub&Server
     */
    private Server&Stub $stubServer;

    protected function setUp(): void
    {
        $this->mockPubSub = $this->createStub(PubSub::class);
        $this->stubServer = $this->createStub(Server::class);

        $this->handler = new RedisPubSubHandler($this->mockPubSub);
        $this->handler->setServer($this->stubServer);
    }

    #[Test]
    public function canBeInstantiatedWithoutPubSub(): void
    {
        $handler = new RedisPubSubHandler();
        $this->assertInstanceOf(RedisPubSubHandler::class, $handler);
    }

    #[Test]
    public function canBeInstantiatedWithPubSub(): void
    {
        $handler = new RedisPubSubHandler($this->mockPubSub);
        $this->assertInstanceOf(RedisPubSubHandler::class, $handler);
    }

    #[Test]
    public function setPubSubStoresPubSubInstance(): void
    {
        $handler = new RedisPubSubHandler();
        $handler->setPubSub($this->mockPubSub);

        $reflection = new ReflectionClass($handler);
        $pubSubProperty = $reflection->getProperty('pubSub');
        $storedPubSub = $pubSubProperty->getValue($handler);

        $this->assertSame($this->mockPubSub, $storedPubSub);
    }

    #[Test]
    public function setServerStoresServerInstance(): void
    {
        $handler = new RedisPubSubHandler();
        $handler->setServer($this->stubServer);

        $reflection = new ReflectionClass($handler);
        $serverProperty = $reflection->getProperty('server');
        $storedServer = $serverProperty->getValue($handler);

        $this->assertSame($this->stubServer, $storedServer);
    }

    #[Test]
    public function supportsSupportedEventTypes(): void
    {
        $this->assertTrue($this->handler->supports('redis.publish'));
        $this->assertTrue($this->handler->supports('redis.subscribe'));
        $this->assertTrue($this->handler->supports('redis.unsubscribe'));
    }

    #[Test]
    public function doesNotSupportUnsupportedEventTypes(): void
    {
        $this->assertFalse($this->handler->supports('unsupported.event'));
        $this->assertFalse($this->handler->supports('subscribe'));
        $this->assertFalse($this->handler->supports('publish'));
    }

    #[Test]
    public function handlePublishWithValidData(): void
    {
        $data = [
            'channel' => 'test-channel',
            'message' => 'test message',
        ];
        $message = new Message('redis.publish', $data);

        $pubSub = $this->createMock(PubSub::class);
        $pubSub->expects($this->once())
            ->method('publish')
            ->with('test-channel', 'test message');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('redis.published'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handlePublishWithArrayMessage(): void
    {
        $data = [
            'channel' => 'test-channel',
            'message' => ['type' => 'notification', 'content' => 'hello'],
        ];
        $message = new Message('redis.publish', $data);

        $expectedJson = json_encode(['type' => 'notification', 'content' => 'hello']);

        $pubSub = $this->createMock(PubSub::class);
        $pubSub->expects($this->once())
            ->method('publish')
            ->with('test-channel', $expectedJson);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('redis.published'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handlePublishWithMissingChannel(): void
    {
        $data = ['message' => 'test message'];
        $message = new Message('redis.publish', $data);

        $pubSub = $this->createMock(PubSub::class);
        $pubSub->expects($this->never())
            ->method('publish');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Missing channel or message'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handlePublishWithMissingMessage(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.publish', $data);

        $pubSub = $this->createMock(PubSub::class);
        $pubSub->expects($this->never())
            ->method('publish');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Missing channel or message'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleSubscribeWithValidData(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.subscribe', $data);

        $pubSub = $this->createMock(PubSub::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('getId')
            ->willReturn('conn-123');

        $connection->expects($this->once())
            ->method('getAttribute')
            ->with('redis_channels', [])
            ->willReturn([]);

        $connection->expects($this->once())
            ->method('setAttribute')
            ->with('redis_channels', ['test-channel']);

        $pubSub->expects($this->once())
            ->method('subscribe')
            ->with('test-channel', $this->isCallable());

        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('redis.subscribed'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleSubscribeWithAlreadySubscribedChannel(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.subscribe', $data);

        $pubSub = $this->createMock(PubSub::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('getId')
            ->willReturn('conn-123');

        $connection->expects($this->once())
            ->method('getAttribute')
            ->with('redis_channels', [])
            ->willReturn(['test-channel']);

        $connection->expects($this->never())
            ->method('setAttribute');

        $pubSub->expects($this->never())
            ->method('subscribe');

        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Already subscribed'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleSubscribeWithMissingChannel(): void
    {
        $data = [];
        $message = new Message('redis.subscribe', $data);

        $pubSub = $this->createMock(PubSub::class);
        $pubSub->expects($this->never())
            ->method('subscribe');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Missing channel'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleUnsubscribeWithValidData(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.unsubscribe', $data);

        $pubSub = $this->createMock(PubSub::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('getId')
            ->willReturn('conn-123');

        $connection->expects($this->once())
            ->method('getAttribute')
            ->with('redis_channels', [])
            ->willReturn(['test-channel', 'other-channel']);

        $connection->expects($this->once())
            ->method('setAttribute')
            ->with('redis_channels', [1 => 'other-channel']);

        $pubSub->expects($this->once())
            ->method('unsubscribe')
            ->with('test-channel');

        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('redis.unsubscribed'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleUnsubscribeWithMissingChannel(): void
    {
        $data = [];
        $message = new Message('redis.unsubscribe', $data);

        $pubSub = $this->createMock(PubSub::class);
        $pubSub->expects($this->never())
            ->method('unsubscribe');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Missing channel'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleWithNoPubSubInstance(): void
    {
        $handler = new RedisPubSubHandler();
        $message = new Message('redis.publish', ['channel' => 'test', 'message' => 'test']);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Redis PubSub is not available'));

        $handler->handle($connection, $message);
    }

    #[Test]
    public function handlePublishWithException(): void
    {
        $data = [
            'channel' => 'test-channel',
            'message' => 'test message',
        ];
        $message = new Message('redis.publish', $data);

        $pubSub = $this->createMock(PubSub::class);
        $pubSub->expects($this->once())
            ->method('publish')
            ->willThrowException(new Exception('Redis connection failed'));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Failed to publish message to Redis'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleSubscribeWithException(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.subscribe', $data);

        $pubSub = $this->createMock(PubSub::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('getId')
            ->willReturn('conn-123');

        $connection->expects($this->once())
            ->method('getAttribute')
            ->with('redis_channels', [])
            ->willReturn([]);

        $pubSub->expects($this->once())
            ->method('subscribe')
            ->willThrowException(new Exception('Redis connection failed'));

        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Failed to subscribe to Redis channel'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleUnsubscribeWithException(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.unsubscribe', $data);

        $pubSub = $this->createMock(PubSub::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('getId')
            ->willReturn('conn-123');

        $connection->expects($this->once())
            ->method('getAttribute')
            ->with('redis_channels', [])
            ->willReturn(['test-channel']);

        $pubSub->expects($this->once())
            ->method('unsubscribe')
            ->willThrowException(new Exception('Redis connection failed'));

        $connection->expects($this->once())
            ->method('send')
            ->with($this->stringContains('Failed to unsubscribe from Redis channel'));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }
}
