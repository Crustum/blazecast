<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Handler;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Handler\RedisPubSubHandler;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use Crustum\BlazeCast\WebSocket\Redis\PubSub;
use Exception;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for RedisPubSubHandler
 */
class RedisPubSubHandlerTest extends TestCase
{
    use VerifiesDoubles;

    private RedisPubSubHandler $handler;

    private PubSub $mockPubSub;

    private Server $stubServer;

    protected function setUp(): void
    {
        $this->mockPubSub = Double::for(PubSub::class);
        $this->stubServer = Double::for(Server::class);

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

        $pubSub = Double::for(PubSub::class);
        $pubSub->expects('publish')
            ->with('test-channel', 'test message');

        $connection = Double::for(Connection::class);
        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'redis.published')));

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

        $pubSub = Double::for(PubSub::class);
        $pubSub->expects('publish')
            ->with('test-channel', $expectedJson);

        $connection = Double::for(Connection::class);
        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'redis.published')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handlePublishWithMissingChannel(): void
    {
        $data = ['message' => 'test message'];
        $message = new Message('redis.publish', $data);

        $pubSub = Double::for(PubSub::class);
        $pubSub->expects('publish')->never();

        $connection = Double::for(Connection::class);
        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'Missing channel or message')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handlePublishWithMissingMessage(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.publish', $data);

        $pubSub = Double::for(PubSub::class);
        $pubSub->expects('publish')->never();

        $connection = Double::for(Connection::class);
        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'Missing channel or message')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleSubscribeWithValidData(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.subscribe', $data);

        $pubSub = Double::for(PubSub::class);
        $connection = Double::for(Connection::class);

        $connection->expects('getId')
            ->returns('conn-123');

        $connection->expects('getAttribute')
            ->with('redis_channels', [])
            ->returns([]);

        $connection->expects('setAttribute')
            ->with('redis_channels', ['test-channel']);

        $pubSub->expects('subscribe')
            ->with('test-channel', Argument::type('callable'));

        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'redis.subscribed')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleSubscribeWithAlreadySubscribedChannel(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.subscribe', $data);

        $pubSub = Double::for(PubSub::class);
        $connection = Double::for(Connection::class);

        $connection->expects('getId')
            ->returns('conn-123');

        $connection->expects('getAttribute')
            ->with('redis_channels', [])
            ->returns(['test-channel']);

        $connection->expects('setAttribute')->never();

        $pubSub->expects('subscribe')->never();

        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'Already subscribed')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleSubscribeWithMissingChannel(): void
    {
        $data = [];
        $message = new Message('redis.subscribe', $data);

        $pubSub = Double::for(PubSub::class);
        $pubSub->expects('subscribe')->never();

        $connection = Double::for(Connection::class);
        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'Missing channel')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleUnsubscribeWithValidData(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.unsubscribe', $data);

        $pubSub = Double::for(PubSub::class);
        $connection = Double::for(Connection::class);

        $connection->expects('getId')
            ->returns('conn-123');

        $connection->expects('getAttribute')
            ->with('redis_channels', [])
            ->returns(['test-channel', 'other-channel']);

        $connection->expects('setAttribute')
            ->with('redis_channels', [1 => 'other-channel']);

        $pubSub->expects('unsubscribe')
            ->with('test-channel');

        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'redis.unsubscribed')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleUnsubscribeWithMissingChannel(): void
    {
        $data = [];
        $message = new Message('redis.unsubscribe', $data);

        $pubSub = Double::for(PubSub::class);
        $pubSub->expects('unsubscribe')->never();

        $connection = Double::for(Connection::class);
        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'Missing channel')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleWithNoPubSubInstance(): void
    {
        $handler = new RedisPubSubHandler();
        $message = new Message('redis.publish', ['channel' => 'test', 'message' => 'test']);

        $connection = Double::for(Connection::class);
        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'Redis PubSub is not available')));

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

        $pubSub = Double::for(PubSub::class);
        $pubSub->expects('publish')
            ->throws(new Exception('Redis connection failed'));

        $connection = Double::for(Connection::class);
        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'Failed to publish message to Redis')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleSubscribeWithException(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.subscribe', $data);

        $pubSub = Double::for(PubSub::class);
        $connection = Double::for(Connection::class);

        $connection->expects('getId')
            ->returns('conn-123');

        $connection->expects('getAttribute')
            ->with('redis_channels', [])
            ->returns([]);

        $pubSub->expects('subscribe')
            ->throws(new Exception('Redis connection failed'));

        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'Failed to subscribe to Redis channel')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }

    #[Test]
    public function handleUnsubscribeWithException(): void
    {
        $data = ['channel' => 'test-channel'];
        $message = new Message('redis.unsubscribe', $data);

        $pubSub = Double::for(PubSub::class);
        $connection = Double::for(Connection::class);

        $connection->expects('getId')
            ->returns('conn-123');

        $connection->expects('getAttribute')
            ->with('redis_channels', [])
            ->returns(['test-channel']);

        $pubSub->expects('unsubscribe')
            ->throws(new Exception('Redis connection failed'));

        $connection->expects('send')
            ->with(Argument::satisfies(fn($v): bool => is_string($v) && str_contains($v, 'Failed to unsubscribe from Redis channel')));

        $handler = new RedisPubSubHandler($pubSub);
        $handler->setServer($this->stubServer);
        $handler->handle($connection, $message);
    }
}
