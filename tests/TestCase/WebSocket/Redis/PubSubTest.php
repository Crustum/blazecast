<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Redis;

use Clue\React\Redis\Client;
use Crustum\BlazeCast\WebSocket\ChannelOperationsManager;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\ConnectionRegistry;
use Crustum\BlazeCast\WebSocket\Filter\DefaultMessageFilter;
use Crustum\BlazeCast\WebSocket\Filter\MessageFilterInterface;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use Crustum\BlazeCast\WebSocket\Redis\PubSub;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use ReflectionClass;

/**
 * Tests for Redis PubSub functionality
 */
class PubSubTest extends TestCase
{
    private LoopInterface $mockLoop;
    private Server&MockObject $mockServer;
    private Client&MockObject $mockClient;
    private PubSub $pubSub;
    /**
     * @var array<string, mixed>
     */
    private array $redisConfig;

    protected function setUp(): void
    {
        $this->mockLoop = $this->createStub(LoopInterface::class);
        $this->mockServer = $this->createMock(Server::class);
        $this->mockClient = $this->createMock(Client::class);

        $this->redisConfig = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
        ];

        $this->pubSub = new PubSub($this->mockLoop, $this->mockServer, $this->redisConfig);

        $reflection = new ReflectionClass($this->pubSub);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($this->pubSub, $this->mockClient);
    }

    #[Test]
    public function canBeInstantiatedWithValidParameters(): void
    {
        $pubSub = new PubSub($this->mockLoop, $this->mockServer, $this->redisConfig);

        $this->assertInstanceOf(PubSub::class, $pubSub);
    }

    #[Test]
    public function subscribeCallsRedisSubscribeWithCorrectParameters(): void
    {
        $channel = 'test-channel';
        $callback = function ($message, $channel) {
        };

        $mockPromise = $this->createMock(PromiseInterface::class);
        $mockPromise->expects($this->once())
            ->method('then')
            ->with(
                $this->isCallable(),
                $this->isCallable(),
            );

        $this->mockClient->expects($this->once())
            ->method('__call')
            ->with('subscribe', [$channel])
            ->willReturn($mockPromise);

        $this->mockClient->expects($this->once())
            ->method('on')
            ->with('message', $this->isCallable());

        $this->pubSub->subscribe($channel, $callback);
    }

    #[Test]
    public function unsubscribeCallsRedisUnsubscribeWithCorrectParameters(): void
    {
        $channel = 'test-channel';

        $mockPromise = $this->createMock(PromiseInterface::class);
        $mockPromise->expects($this->once())
            ->method('then')
            ->with(
                $this->isCallable(),
                $this->isCallable(),
            );

        $this->mockClient->expects($this->once())
            ->method('__call')
            ->with('unsubscribe', [$channel])
            ->willReturn($mockPromise);

        $this->pubSub->unsubscribe($channel);
    }

    #[Test]
    public function publishCallsRedisPublishWithCorrectParameters(): void
    {
        $channel = 'test-channel';
        $message = 'test message';

        $mockPromise = $this->createMock(PromiseInterface::class);
        $mockPromise->expects($this->once())
            ->method('then')
            ->with(
                $this->isCallable(),
                $this->isCallable(),
            );

        $this->mockClient->expects($this->once())
            ->method('__call')
            ->with('publish', [$channel, $message])
            ->willReturn($mockPromise);

        $this->pubSub->publish($channel, $message);
    }

    #[Test]
    public function subscribePatternCallsRedisPsubscribeWithCorrectParameters(): void
    {
        $pattern = 'user:*';
        $callback = function ($message, $channel, $pattern) {
        };

        $mockPromise = $this->createMock(PromiseInterface::class);
        $mockPromise->expects($this->once())
            ->method('then')
            ->with(
                $this->isCallable(),
                $this->isCallable(),
            );

        $this->mockClient->expects($this->once())
            ->method('__call')
            ->with('psubscribe', [$pattern])
            ->willReturn($mockPromise);

        $this->mockClient->expects($this->once())
            ->method('on')
            ->with('pmessage', $this->isCallable());

        $this->pubSub->subscribePattern($pattern, $callback);
    }

    #[Test]
    public function unsubscribePatternCallsRedisPunsubscribeWithCorrectParameters(): void
    {
        $pattern = 'user:*';

        $mockPromise = $this->createMock(PromiseInterface::class);
        $mockPromise->expects($this->once())
            ->method('then')
            ->with(
                $this->isCallable(),
                $this->isCallable(),
            );

        $this->mockClient->expects($this->once())
            ->method('__call')
            ->with('punsubscribe', [$pattern])
            ->willReturn($mockPromise);

        $this->pubSub->unsubscribePattern($pattern);
    }

    #[Test]
    public function setMessageFilterStoresFilterInstance(): void
    {
        $filter = new DefaultMessageFilter();
        $this->pubSub->setMessageFilter($filter);

        $reflection = new ReflectionClass($this->pubSub);
        $filterProperty = $reflection->getProperty('messageFilter');
        $storedFilter = $filterProperty->getValue($this->pubSub);

        $this->assertSame($filter, $storedFilter);
    }

    #[Test]
    public function publishWithFilterAppliesFilterCriteria(): void
    {
        /** @var MessageFilterInterface&MockObject $filter */
        $filter = $this->createMock(MessageFilterInterface::class);
        $this->pubSub->setMessageFilter($filter);

        $channel = 'test-channel';
        $message = '{"event":"test","data":{"user_id":123}}';
        $criteria = ['user_id' => 123];

        $messageObj = Message::fromJson($message);

        $filter->expects($this->once())
            ->method('filter')
            ->with($messageObj, $criteria)
            ->willReturn(true);

        // Should call publish since filter returns true
        $mockPromise = $this->createMock(PromiseInterface::class);
        $mockPromise->expects($this->once())
            ->method('then');

        $this->mockClient->expects($this->once())
            ->method('__call')
            ->with('publish', [$channel, $message])
            ->willReturn($mockPromise);

        $this->pubSub->publishWithFilter($channel, $message, $criteria);
    }

    #[Test]
    public function publishWithFilterSkipsPublishWhenFilterReturnsFalse(): void
    {
        /** @var MessageFilterInterface&MockObject $filter */
        $filter = $this->createMock(MessageFilterInterface::class);
        $this->pubSub->setMessageFilter($filter);

        $channel = 'test-channel';
        $message = '{"event":"test","data":{"user_id":123}}';
        $criteria = ['user_id' => 456];

        $messageObj = Message::fromJson($message);

        $filter->expects($this->once())
            ->method('filter')
            ->with($messageObj, $criteria)
            ->willReturn(false);

        // Should NOT call publish since filter returns false
        $this->mockClient->expects($this->never())
            ->method('__call')
            ->with('publish');

        $this->pubSub->publishWithFilter($channel, $message, $criteria);
    }

    #[Test]
    public function publishWithTransformAppliesTransformationRules(): void
    {
        /** @var MessageFilterInterface&MockObject $filter */
        $filter = $this->createMock(MessageFilterInterface::class);
        $this->pubSub->setMessageFilter($filter);

        $channel = 'test-channel';
        $originalMessage = '{"event":"test","data":{"content":"hello"}}';
        $transformedMessage = '{"event":"test","data":{"content":"hello","timestamp":1234567890}}';
        $rules = ['add_timestamp' => true];

        $originalMessageObj = Message::fromJson($originalMessage);
        $transformedMessageObj = Message::fromJson($transformedMessage);

        $filter->expects($this->once())
            ->method('transform')
            ->with($originalMessageObj, $rules)
            ->willReturn($transformedMessageObj);

        $mockPromise = $this->createMock(PromiseInterface::class);
        $mockPromise->expects($this->once())
            ->method('then');

        $this->mockClient->expects($this->once())
            ->method('__call')
            ->with('publish', [$channel, $transformedMessage])
            ->willReturn($mockPromise);

        $this->pubSub->publishWithTransform($channel, $originalMessage, $rules);
    }

    #[Test]
    public function setupDefaultChannelsConfiguresStandardChannels(): void
    {
        $mockChannelOperationsManager = $this->createMock(ChannelOperationsManager::class);
        $mockConnectionRegistry = $this->createMock(ConnectionRegistry::class);

        $this->mockServer->expects($this->once())
            ->method('getChannelOperationsManager')
            ->willReturn($mockChannelOperationsManager);

        $this->mockServer->expects($this->once())
            ->method('getConnectionRegistry')
            ->willReturn($mockConnectionRegistry);

        $mockChannelOperationsManager->expects($this->once())
            ->method('broadcast');

        $mockConnectionRegistry->expects($this->once())
            ->method('getConnection')
            ->willReturn(null);

        $mockPromise = $this->createMock(PromiseInterface::class);
        $mockPromise->method('then')
            ->willReturnCallback(function ($successCallback) use ($mockPromise) {
                $successCallback();

                return $mockPromise;
            });

        $this->mockClient->expects($this->exactly(2))
            ->method('__call')
            ->with('subscribe', $this->isArray())
            ->willReturn($mockPromise);

        $this->mockClient->expects($this->exactly(2))
            ->method('on');

        $this->pubSub->setupDefaultChannels();

        $reflection = new ReflectionClass($this->pubSub);
        $subscriptionsProperty = $reflection->getProperty('subscriptions');
        $subscriptions = $subscriptionsProperty->getValue($this->pubSub);

        if (isset($subscriptions['blaze:broadcast'])) {
            $callback = $subscriptions['blaze:broadcast'];
            $callback('test broadcast message');
        }

        if (isset($subscriptions['blaze:private'])) {
            $callback = $subscriptions['blaze:private'];
            $callback('{"socket_id":"test-id","message":"test private message"}');
        }
    }

    #[Test]
    public function setupEnhancedChannelsConfiguresPatternSubscriptions(): void
    {
        $mockPromise = $this->createMock(PromiseInterface::class);
        $mockPromise->method('then')
            ->willReturnCallback(function ($successCallback) use ($mockPromise) {
                $successCallback();

                return $mockPromise;
            });

        $this->mockClient->expects($this->exactly(5))
            ->method('__call')
            ->willReturn($mockPromise);

        $this->mockClient->expects($this->exactly(5))
            ->method('on');

        $this->pubSub->setupEnhancedChannels();

        $reflection = new ReflectionClass($this->pubSub);
        $patternSubscriptionsProperty = $reflection->getProperty('patternSubscriptions');
        $patternSubscriptions = $patternSubscriptionsProperty->getValue($this->pubSub);

        $this->assertArrayHasKey('user:*', $patternSubscriptions);
        $this->assertArrayHasKey('room:*', $patternSubscriptions);
        $this->assertArrayHasKey('notifications:*', $patternSubscriptions);
    }

    #[Test]
    public function handleUserMessageSendsToCorrectConnections(): void
    {
        $userId = '123';
        $message = 'test user message';

        $mockConnection1 = $this->createMock(Connection::class);
        $mockConnection1->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($userId);
        $mockConnection1->expects($this->once())
            ->method('send')
            ->with($message);
        $mockConnection1->expects($this->once())
            ->method('getId')
            ->willReturn('conn-1');

        $mockConnection2 = $this->createMock(Connection::class);
        $mockConnection2->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn('456');
        $mockConnection2->expects($this->never())
            ->method('send');

        $mockConnectionRegistry = $this->createMock(ConnectionRegistry::class);
        $mockConnectionRegistry->expects($this->once())
            ->method('getConnections')
            ->willReturn(['conn-1' => $mockConnection1, 'conn-2' => $mockConnection2]);

        $this->mockServer->expects($this->once())
            ->method('getConnectionRegistry')
            ->willReturn($mockConnectionRegistry);

        $reflection = new ReflectionClass($this->pubSub);
        $method = $reflection->getMethod('handleUserMessage');
        $method->invoke($this->pubSub, $message, $userId);
    }

    #[Test]
    public function handleRoomMessageBroadcastsToChannel(): void
    {
        $roomId = 'lobby';
        $message = 'test room message';
        $expectedChannel = 'room-lobby';

        $mockChannelOperationsManager = $this->createMock(ChannelOperationsManager::class);
        $mockChannelOperationsManager->expects($this->once())
            ->method('broadcastToChannel')
            ->with($expectedChannel, $message);

        $this->mockServer->expects($this->once())
            ->method('getChannelOperationsManager')
            ->willReturn($mockChannelOperationsManager);

        $reflection = new ReflectionClass($this->pubSub);
        $method = $reflection->getMethod('handleRoomMessage');
        $method->invoke($this->pubSub, $message, $roomId);
    }

    #[Test]
    public function handleSystemNotificationBroadcastsBasedOnType(): void
    {
        $message = 'system notification';

        $mockChannelOperationsManager = $this->createMock(ChannelOperationsManager::class);
        $mockChannelOperationsManager->expects($this->once())
            ->method('broadcast')
            ->with($message);

        $mockChannelOperationsManager->expects($this->once())
            ->method('broadcastToChannel')
            ->with('admin-notifications', $message);

        $this->mockServer->expects($this->exactly(2))
            ->method('getChannelOperationsManager')
            ->willReturn($mockChannelOperationsManager);

        $reflection = new ReflectionClass($this->pubSub);
        $method = $reflection->getMethod('handleSystemNotification');
        $method->invoke($this->pubSub, $message, 'system');
        $method->invoke($this->pubSub, $message, 'maintenance');
    }
}
