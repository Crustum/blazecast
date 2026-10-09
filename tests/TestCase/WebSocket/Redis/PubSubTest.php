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
use Evenement\EventEmitterTrait;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use ReflectionClass;

/**
 * Tests for Redis PubSub functionality
 */
class PubSubTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @var \React\EventLoop\LoopInterface&\JMac\Testing\DoubleInterface
     */
    private LoopInterface $mockLoop;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Server&\JMac\Testing\DoubleInterface
     */
    private Server $mockServer;

    /**
     * @var Client&object{commandCalls: list<array{0: string, 1: array<mixed>}>}
     */
    private Client $mockClient;

    private PubSub $pubSub;

    /**
     * @var array<string, mixed>
     */
    private array $redisConfig;

    protected function setUp(): void
    {
        $this->mockLoop = Double::for(LoopInterface::class);
        $this->mockServer = Double::for(Server::class);
        $this->mockClient = $this->createFakeClient(Double::for(PromiseInterface::class));

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

    /**
     * Create a fake Redis client recording command invocations.
     *
     * Double::for() cannot double Clue\React\Redis\Client (it declares the
     * magic __call method), so this hand-rolled fake is used instead. Every
     * command returns the given promise; invocations are recorded in the
     * public $commandCalls list as [command, args] pairs.
     *
     * @param \React\Promise\PromiseInterface $promise Promise returned from every command
     * @return \Clue\React\Redis\Client&object{commandCalls: list<array{0: string, 1: array<mixed>}>}
     */
    private function createFakeClient(PromiseInterface $promise): Client
    {
        return new class ($promise) implements Client {
            use EventEmitterTrait;

            /**
             * @var list<array{0: string, 1: array<mixed>}>
             */
            public array $commandCalls = [];

            private PromiseInterface $promise;

            public function __construct(PromiseInterface $promise)
            {
                $this->promise = $promise;
            }

            /**
             * @param array<mixed> $args
             * @return \React\Promise\PromiseInterface
             */
            public function __call($name, $args)
            {
                $this->commandCalls[] = [$name, $args];

                return $this->promise;
            }

            public function end(): void
            {
            }

            public function close(): void
            {
            }
        };
    }

    /**
     * Assert a fake client recorded exactly the given commands.
     *
     * @param \Clue\React\Redis\Client&object{commandCalls: list<array{0: string, 1: array<mixed>}>} $client Fake client created via createFakeClient()
     * @param list<array{0: string, 1: array<mixed>}> $expected Expected [command, args] pairs
     * @return void
     */
    private function assertClientCommands(Client $client, array $expected): void
    {
        $this->assertSame($expected, $client->commandCalls);
    }

    /**
     * Assert a fake client registered exactly one callable listener for the event.
     *
     * @param \Clue\React\Redis\Client&object{commandCalls: list<array{0: string, 1: array<mixed>}>} $client Fake client created via createFakeClient()
     * @param string $event Event name
     * @return void
     */
    private function assertClientListenerRegistered(Client $client, string $event): void
    {
        $listeners = $client->listeners($event);
        $this->assertCount(1, $listeners);
        $this->assertTrue(is_callable($listeners[0]));
    }

    /**
     * Inject a client into the PubSub instance
     *
     * @param \Clue\React\Redis\Client&object{commandCalls: list<array{0: string, 1: array<mixed>}>} $client Client to inject
     * @return void
     */
    private function injectClient(Client $client): void
    {
        $reflection = new ReflectionClass($this->pubSub);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($this->pubSub, $client);
    }

    /**
     * Create a PubSub instance with the given server and client
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Server $server Server instance
     * @param \Clue\React\Redis\Client&object{commandCalls: list<array{0: string, 1: array<mixed>}>} $client Client instance
     * @return \Crustum\BlazeCast\WebSocket\Redis\PubSub
     */
    private function createPubSub(Server $server, Client $client): PubSub
    {
        $pubSub = new PubSub($this->mockLoop, $server, $this->redisConfig);

        $reflection = new ReflectionClass($pubSub);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($pubSub, $client);

        return $pubSub;
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
        $callback = function ($message, $channel): void {
        };

        $mockPromise = Double::for(PromiseInterface::class);
        $mockPromise->expects('then')
            ->with(
                Argument::satisfies('is_callable'),
                Argument::satisfies('is_callable'),
            );

        $client = $this->createFakeClient($mockPromise);

        $this->injectClient($client);

        $this->pubSub->subscribe($channel, $callback);

        $this->assertClientCommands($client, [['subscribe', [$channel]]]);
        $this->assertClientListenerRegistered($client, 'message');
    }

    #[Test]
    public function unsubscribeCallsRedisUnsubscribeWithCorrectParameters(): void
    {
        $channel = 'test-channel';

        $mockPromise = Double::for(PromiseInterface::class);
        $mockPromise->expects('then')
            ->with(
                Argument::satisfies('is_callable'),
                Argument::satisfies('is_callable'),
            );

        $client = $this->createFakeClient($mockPromise);

        $this->injectClient($client);

        $this->pubSub->unsubscribe($channel);

        $this->assertClientCommands($client, [['unsubscribe', [$channel]]]);
    }

    #[Test]
    public function publishCallsRedisPublishWithCorrectParameters(): void
    {
        $channel = 'test-channel';
        $message = 'test message';

        $mockPromise = Double::for(PromiseInterface::class);
        $mockPromise->expects('then')
            ->with(
                Argument::satisfies('is_callable'),
                Argument::satisfies('is_callable'),
            );

        $client = $this->createFakeClient($mockPromise);

        $this->injectClient($client);

        $this->pubSub->publish($channel, $message);

        $this->assertClientCommands($client, [['publish', [$channel, $message]]]);
    }

    #[Test]
    public function subscribePatternCallsRedisPsubscribeWithCorrectParameters(): void
    {
        $pattern = 'user:*';
        $callback = function ($message, $channel, $pattern): void {
        };

        $mockPromise = Double::for(PromiseInterface::class);
        $mockPromise->expects('then')
            ->with(
                Argument::satisfies('is_callable'),
                Argument::satisfies('is_callable'),
            );

        $client = $this->createFakeClient($mockPromise);

        $this->injectClient($client);

        $this->pubSub->subscribePattern($pattern, $callback);

        $this->assertClientCommands($client, [['psubscribe', [$pattern]]]);
        $this->assertClientListenerRegistered($client, 'pmessage');
    }

    #[Test]
    public function unsubscribePatternCallsRedisPunsubscribeWithCorrectParameters(): void
    {
        $pattern = 'user:*';

        $mockPromise = Double::for(PromiseInterface::class);
        $mockPromise->expects('then')
            ->with(
                Argument::satisfies('is_callable'),
                Argument::satisfies('is_callable'),
            );

        $client = $this->createFakeClient($mockPromise);

        $this->injectClient($client);

        $this->pubSub->unsubscribePattern($pattern);

        $this->assertClientCommands($client, [['punsubscribe', [$pattern]]]);
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
        /** @var \Crustum\BlazeCast\WebSocket\Filter\MessageFilterInterface&\JMac\Testing\DoubleInterface $filter */
        $filter = Double::for(MessageFilterInterface::class);
        $this->pubSub->setMessageFilter($filter);

        $channel = 'test-channel';
        $message = '{"event":"test","data":{"user_id":123}}';
        $criteria = ['user_id' => 123];

        $messageObj = Message::fromJson($message);

        $filter->expects('filter')
            ->with($messageObj, $criteria)
            ->returns(true);

        // Should call publish since filter returns true
        $mockPromise = Double::for(PromiseInterface::class);
        $mockPromise->expects('then');

        $client = $this->createFakeClient($mockPromise);

        $this->injectClient($client);

        $this->pubSub->publishWithFilter($channel, $message, $criteria);

        $this->assertClientCommands($client, [['publish', [$channel, $message]]]);
    }

    #[Test]
    public function publishWithFilterSkipsPublishWhenFilterReturnsFalse(): void
    {
        /** @var \Crustum\BlazeCast\WebSocket\Filter\MessageFilterInterface&\JMac\Testing\DoubleInterface $filter */
        $filter = Double::for(MessageFilterInterface::class);
        $this->pubSub->setMessageFilter($filter);

        $channel = 'test-channel';
        $message = '{"event":"test","data":{"user_id":123}}';
        $criteria = ['user_id' => 456];

        $messageObj = Message::fromJson($message);

        $filter->expects('filter')
            ->with($messageObj, $criteria)
            ->returns(false);

        // Should NOT call publish since filter returns false
        $client = $this->createFakeClient(Double::for(PromiseInterface::class));

        $this->injectClient($client);

        $this->pubSub->publishWithFilter($channel, $message, $criteria);

        $this->assertClientCommands($client, []);
    }

    #[Test]
    public function publishWithTransformAppliesTransformationRules(): void
    {
        /** @var \Crustum\BlazeCast\WebSocket\Filter\MessageFilterInterface&\JMac\Testing\DoubleInterface $filter */
        $filter = Double::for(MessageFilterInterface::class);
        $this->pubSub->setMessageFilter($filter);

        $channel = 'test-channel';
        $originalMessage = '{"event":"test","data":{"content":"hello"}}';
        $transformedMessage = '{"event":"test","data":{"content":"hello","timestamp":1234567890}}';
        $rules = ['add_timestamp' => true];

        $originalMessageObj = Message::fromJson($originalMessage);
        $transformedMessageObj = Message::fromJson($transformedMessage);

        $filter->expects('transform')
            ->with($originalMessageObj, $rules)
            ->returns($transformedMessageObj);

        $mockPromise = Double::for(PromiseInterface::class);
        $mockPromise->expects('then');

        $client = $this->createFakeClient($mockPromise);

        $this->injectClient($client);

        $this->pubSub->publishWithTransform($channel, $originalMessage, $rules);

        $this->assertClientCommands($client, [['publish', [$channel, $transformedMessage]]]);
    }

    #[Test]
    public function setupDefaultChannelsConfiguresStandardChannels(): void
    {
        $mockChannelOperationsManager = Double::for(ChannelOperationsManager::class);
        $mockConnectionRegistry = Double::for(ConnectionRegistry::class);

        $server = Double::for(Server::class);
        $server->expects('getChannelOperationsManager')
            ->returns($mockChannelOperationsManager);

        $server->expects('getConnectionRegistry')
            ->returns($mockConnectionRegistry);

        $mockChannelOperationsManager->expects('broadcast');

        $mockConnectionRegistry->expects('getConnection')
            ->returns(null);

        $mockPromise = Double::for(PromiseInterface::class);
        $mockPromise->allows('then')
            ->resolves(function (mixed ...$args) use ($mockPromise): mixed {
                $successCallback = $args[0] ?? null;
                assert(is_callable($successCallback));
                $successCallback();

                return $mockPromise;
            });

        $client = $this->createFakeClient($mockPromise);

        $pubSub = $this->createPubSub($server, $client);
        $pubSub->setupDefaultChannels();

        $this->assertCount(2, $client->commandCalls);
        foreach ($client->commandCalls as [$command, $args]) {
            $this->assertSame('subscribe', $command);
            $this->assertCount(1, $args);
        }

        $this->assertCount(2, $client->listeners('message'));

        $reflection = new ReflectionClass($pubSub);
        $subscriptionsProperty = $reflection->getProperty('subscriptions');
        $subscriptions = $subscriptionsProperty->getValue($pubSub);

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
        $mockPromise = Double::for(PromiseInterface::class);
        $mockPromise->allows('then')
            ->resolves(function (mixed ...$args) use ($mockPromise): mixed {
                $successCallback = $args[0] ?? null;
                assert(is_callable($successCallback));
                $successCallback();

                return $mockPromise;
            });

        $client = $this->createFakeClient($mockPromise);

        $this->injectClient($client);

        $this->pubSub->setupEnhancedChannels();

        $this->assertCount(5, $client->commandCalls);
        $this->assertCount(2, $client->listeners('message'));
        $this->assertCount(3, $client->listeners('pmessage'));

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

        $mockConnection1 = Double::for(Connection::class);
        $mockConnection1->expects('getAttribute')
            ->with('user_id')
            ->returns($userId);
        $mockConnection1->expects('send')
            ->with($message);
        $mockConnection1->expects('getId')
            ->returns('conn-1');

        $mockConnection2 = Double::for(Connection::class);
        $mockConnection2->expects('getAttribute')
            ->with('user_id')
            ->returns('456');
        $mockConnection2->expects('send')
            ->never();

        $mockConnectionRegistry = Double::for(ConnectionRegistry::class);
        $mockConnectionRegistry->expects('getConnections')
            ->returns(['conn-1' => $mockConnection1, 'conn-2' => $mockConnection2]);

        $server = Double::for(Server::class);
        $server->expects('getConnectionRegistry')
            ->returns($mockConnectionRegistry);

        $pubSub = $this->createPubSub($server, $this->mockClient);

        $reflection = new ReflectionClass($pubSub);
        $method = $reflection->getMethod('handleUserMessage');
        $method->invoke($pubSub, $message, $userId);
    }

    #[Test]
    public function handleRoomMessageBroadcastsToChannel(): void
    {
        $roomId = 'lobby';
        $message = 'test room message';
        $expectedChannel = 'room-lobby';

        $mockChannelOperationsManager = Double::for(ChannelOperationsManager::class);
        $mockChannelOperationsManager->expects('broadcastToChannel')
            ->with($expectedChannel, $message);

        $server = Double::for(Server::class);
        $server->expects('getChannelOperationsManager')
            ->returns($mockChannelOperationsManager);

        $pubSub = $this->createPubSub($server, $this->mockClient);

        $reflection = new ReflectionClass($pubSub);
        $method = $reflection->getMethod('handleRoomMessage');
        $method->invoke($pubSub, $message, $roomId);
    }

    #[Test]
    public function handleSystemNotificationBroadcastsBasedOnType(): void
    {
        $message = 'system notification';

        $mockChannelOperationsManager = Double::for(ChannelOperationsManager::class);
        $mockChannelOperationsManager->expects('broadcast')
            ->with($message);

        $mockChannelOperationsManager->expects('broadcastToChannel')
            ->with('admin-notifications', $message);

        $server = Double::for(Server::class);
        $server->expects('getChannelOperationsManager')
            ->times(2)
            ->returns($mockChannelOperationsManager);

        $pubSub = $this->createPubSub($server, $this->mockClient);

        $reflection = new ReflectionClass($pubSub);
        $method = $reflection->getMethod('handleSystemNotification');
        $method->invoke($pubSub, $message, 'system');
        $method->invoke($pubSub, $message, 'maintenance');
    }
}
