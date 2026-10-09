<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPresenceChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Event\EventDispatcher;
use Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionUnauthorizedException;
use Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPubSubProvider;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use function React\Promise\resolve;

/**
 * Unit tests for PusherPresenceChannel
 */
class PusherPresenceChannelTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @var TestablePresenceChannel
     */
    private TestablePresenceChannel $channel;

    /**
     * Mock connection object
     */
    private Connection $connection;

    /**
     * Mock application manager
     */
    private ApplicationManager $applicationManager;

    /**
     * Connection attributes storage
     *
     * @var array<string, mixed>
     */
    private array $connectionAttributes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = new TestablePresenceChannel('presence-test-channel');

        $this->connectionAttributes = [];

        $this->connection = Double::for(Connection::class);
        $this->connection->allows('getId')->returns('connection-123');

        $this->connection->allows('setAttribute')
            ->resolves(function (mixed ...$args): mixed {
                $key = $args[0];
                $value = $args[1];
                $this->connectionAttributes[$key] = $value;

                return null;
            });

        $this->connection->allows('getAttribute')
            ->resolves(function (mixed ...$args): mixed {
                $key = $args[0];
                if (!array_key_exists($key, $this->connectionAttributes)) {
                    return null;
                }

                return $this->connectionAttributes[$key];
            });

        $this->connection->allows('removeAttribute')
            ->resolves(function (mixed ...$args): mixed {
                $key = $args[0];
                unset($this->connectionAttributes[$key]);

                return null;
            });

        $this->connection->allows('send')
            ->resolves(fn(mixed ...$args): mixed => null);

        $this->applicationManager = Double::for(ApplicationManager::class);
        $this->applicationManager->allows('getApplicationByKey')
            ->returns([
                'id' => 'app-id',
                'key' => 'app-key',
                'secret' => 'app-secret',
            ]);

        $this->channel->setApplicationManager($this->applicationManager);
    }

    #[Test]
    public function presenceChannelReturnsCorrectType(): void
    {
        $this->assertEquals('presence', $this->channel->getType());
    }

    #[Test]
    public function presenceChannelAllowsClientEvents(): void
    {
        $this->assertTrue($this->channel->allowsClientEvents());
    }

    #[Test]
    public function presenceChannelRequiresAuthentication(): void
    {
        $this->expectException(ConnectionUnauthorizedException::class);
        $this->expectExceptionMessage('Invalid authentication format');

        $this->channel->subscribe($this->connection);
    }

    #[Test]
    public function presenceChannelAcceptsSubscriptionWithoutMemberData(): void
    {
        $connectionId = 'connection-123';
        $channelName = 'presence-test-channel';
        $signatureString = "{$connectionId}:{$channelName}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $this->channel->subscribe($this->connection, "app-key:{$validSignature}");

        $this->assertTrue($this->channel->hasConnection($this->connection));
    }

    #[Test]
    public function presenceChannelRejectsInvalidMemberDataJson(): void
    {
        $connectionId = 'connection-123';
        $channelName = 'presence-test-channel';
        $invalidData = '{invalid-json';
        $signatureString = "{$connectionId}:{$channelName}:{$invalidData}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Syntax error');

        $this->channel->subscribe($this->connection, "app-key:{$validSignature}", $invalidData);
    }

    #[Test]
    public function presenceChannelAcceptsDataWithoutUserId(): void
    {
        $connectionId = 'connection-123';
        $channelName = 'presence-test-channel';
        $validData = '{"user_info":{"name":"Test User"}}';
        $signatureString = "{$connectionId}:{$channelName}:{$validData}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $this->channel->subscribe($this->connection, "app-key:{$validSignature}", $validData);

        $this->assertTrue($this->channel->hasConnection($this->connection));
    }

    #[Test]
    public function presenceChannelAcceptsValidSubscription(): void
    {
        $connectionId = 'connection-123';
        $channelName = 'presence-test-channel';
        $validData = '{"user_id":"user-123","user_info":{"name":"Test User"}}';
        $signatureString = "{$connectionId}:{$channelName}:{$validData}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $this->channel->subscribe($this->connection, "app-key:{$validSignature}", $validData);

        $this->assertTrue($this->channel->hasConnection($this->connection));

        $this->assertEquals('user-123', $this->connectionAttributes['user_id']);

        $this->assertEquals(1, $this->channel->getMemberCount());
    }

    #[Test]
    public function presenceChannelHandlesMultipleMembers(): void
    {
        $this->subscribeTestMember($this->connection, 'user-123', ['name' => 'User 1']);

        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('connection-456');

        $connection2Attributes = [];
        $connection2->allows('setAttribute')
            ->resolves(function (mixed ...$args) use (&$connection2Attributes): mixed {
                $key = $args[0];
                $value = $args[1];
                $connection2Attributes[$key] = $value;

                return null;
            });

        $connection2->allows('getAttribute')
            ->resolves(function (mixed ...$args) use (&$connection2Attributes): mixed {
                $key = $args[0];

                return $connection2Attributes[$key] ?? null;
            });

        $connection2->allows('send')
            ->resolves(fn(mixed ...$args): mixed => null);

        $this->subscribeTestMember($connection2, 'user-456', ['name' => 'User 2']);

        $this->assertEquals(2, $this->channel->getMemberCount());

        $members = $this->channel->getMembers();
        $this->assertCount(2, $members);

        $memberIds = array_column($members, 'user_id');
        $this->assertContains('user-123', $memberIds);
        $this->assertContains('user-456', $memberIds);
    }

    #[Test]
    public function presenceChannelCanUnsubscribeMembers(): void
    {
        $this->subscribeTestMember($this->connection, 'user-123', ['name' => 'User 1']);

        $this->assertEquals(1, $this->channel->getMemberCount());
        $this->assertTrue($this->channel->hasMember('user-123'));

        $this->channel->unsubscribe($this->connection);

        $this->assertEquals(0, $this->channel->getMemberCount());
        $this->assertFalse($this->channel->hasMember('user-123'));

        $this->assertFalse($this->channel->hasConnection($this->connection));
    }

    #[Test]
    public function presenceChannelProvidesPresenceStats(): void
    {
        $this->subscribeTestMember($this->connection, 'user-123', ['name' => 'User 1']);

        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('connection-456');
        $connection2Attributes = [];
        $connection2->allows('setAttribute')
            ->resolves(function (mixed ...$args) use (&$connection2Attributes): mixed {
                $key = $args[0];
                $value = $args[1];
                $connection2Attributes[$key] = $value;

                return null;
            });
        $connection2->allows('getAttribute')
            ->resolves(function (mixed ...$args) use (&$connection2Attributes): mixed {
                $key = $args[0];

                return $connection2Attributes[$key] ?? null;
            });
        $connection2->allows('send')
            ->resolves(fn(mixed ...$args): mixed => null);

        $this->subscribeTestMember($connection2, 'user-456', ['name' => 'User 2']);

        $stats = $this->channel->getPresenceStats();

        $this->assertArrayHasKey('member_count', $stats);
        $this->assertEquals(2, $stats['member_count']);
    }

    #[Test]
    public function memberAddedEventHasJsonEncodedDataString(): void
    {
        $channel = new PusherPresenceChannel('presence-test-channel');
        $channel->setApplicationManager($this->applicationManager);

        $userData = ['user_id' => 'user-123', 'user_info' => ['name' => 'Test User']];
        $connection1 = Double::for(Connection::class);
        $connection1->allows('getId')->returns('connection-1');

        $receivedMessages = [];
        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('connection-2');
        $connection2->allows('send')->resolves(function (mixed ...$args) use (&$receivedMessages): mixed {
            $receivedMessages[] = $args[0];

            return null;
        });

        $reflection = new ReflectionClass($channel);
        $connectionsProperty = $reflection->getProperty('connections');
        $connectionsProperty->setValue($channel, [$connection2->getId() => $connection2]);

        $method = $reflection->getMethod('broadcastMemberAdded');
        $method->invoke($channel, $userData, $connection1);

        $this->assertNotEmpty($receivedMessages, 'Connection should receive member_added event');

        $message = json_decode($receivedMessages[0], true);
        $this->assertEquals('pusher_internal:member_added', $message['event']);
        $this->assertIsString($message['data'], 'data field should be a JSON-encoded string');
        $this->assertEquals(json_encode((object)$userData), $message['data']);
    }

    #[Test]
    public function memberRemovedEventHasJsonEncodedDataString(): void
    {
        $channel = new PusherPresenceChannel('presence-test-channel');
        $channel->setApplicationManager($this->applicationManager);

        $userId = 'user-123';
        $receivedMessages = [];
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('connection-1');
        $connection->allows('send')->resolves(function (mixed ...$args) use (&$receivedMessages): mixed {
            $receivedMessages[] = $args[0];

            return null;
        });

        $reflection = new ReflectionClass($channel);
        $connectionsProperty = $reflection->getProperty('connections');
        $connectionsProperty->setValue($channel, [$connection->getId() => $connection]);

        $method = $reflection->getMethod('broadcastMemberRemoved');
        $method->invoke($channel, $userId);

        $this->assertNotEmpty($receivedMessages, 'Connection should receive member_removed event');

        $message = json_decode($receivedMessages[0], true);
        $this->assertEquals('pusher_internal:member_removed', $message['event']);
        $this->assertIsString($message['data'], 'data field should be a JSON-encoded string');
        $this->assertEquals(json_encode(['user_id' => $userId]), $message['data']);
    }

    /**
     * Helper method to subscribe a test member
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection mock
     * @param string $userId User ID
     * @param array<string, mixed> $userInfo User info
     * @return void
     */
    private function subscribeTestMember(Connection $connection, string $userId, array $userInfo): void
    {
        $connectionId = $connection->getId();
        $channelName = 'presence-test-channel';
        $validData = json_encode([
            'user_id' => $userId,
            'user_info' => $userInfo,
        ]);
        $signatureString = "{$connectionId}:{$channelName}:{$validData}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $this->channel->subscribe($connection, "app-key:{$validSignature}", $validData);
    }

    /**
     * Create a stub connection with attribute storage and send capture
     *
     * @param string $id Connection ID
     * @param list<string> $received Captured sent payloads
     * @param array<string, mixed> $initialAttributes Initial attributes
     * @return \Crustum\BlazeCast\WebSocket\Connection
     */
    private function createPresenceConnection(string $id, array &$received, array $initialAttributes = []): Connection
    {
        $attributes = $initialAttributes;
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns($id);
        $connection->allows('setAttribute')
            ->resolves(function (mixed ...$args) use (&$attributes): mixed {
                $key = $args[0];
                $value = $args[1];
                $attributes[$key] = $value;

                return null;
            });
        $connection->allows('getAttribute')
            ->resolves(function (mixed ...$args) use (&$attributes): mixed {
                $key = $args[0];

                return $attributes[$key] ?? null;
            });
        $connection->allows('removeAttribute')
            ->resolves(function (mixed ...$args) use (&$attributes): mixed {
                $key = $args[0];
                unset($attributes[$key]);

                return null;
            });
        $connection->allows('send')
            ->resolves(function (mixed ...$args) use (&$received): mixed {
                $received[] = $args[0];

                return null;
            });

        return $connection;
    }

    /**
     * Subscribe a member to the given channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPresenceChannel $channel Channel
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $userId User ID
     * @param array<string, mixed> $userInfo User info
     * @return void
     */
    private function subscribeChannelMember(
        PusherPresenceChannel $channel,
        Connection $connection,
        string $userId,
        array $userInfo,
    ): void {
        $channelName = $channel->getName();
        $data = (string)json_encode(['user_id' => $userId, 'user_info' => $userInfo]);
        $signature = hash_hmac('sha256', "{$connection->getId()}:{$channelName}:{$data}", 'app-secret');

        $channel->subscribe($connection, "app-key:{$signature}", $data);
    }

    /**
     * Filter captured payloads by event name
     *
     * @param list<string> $received Captured sent payloads
     * @param string $event Event name
     * @return list<array<string, mixed>>
     */
    private function filterByEvent(array $received, string $event): array
    {
        $messages = [];
        foreach ($received as $payload) {
            $message = json_decode($payload, true);
            if (($message['event'] ?? null) === $event) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    #[Test]
    public function memberAddedAnnouncedOnlyForFirstConnectionOfUser(): void
    {
        $channel = new PusherPresenceChannel('presence-test-channel');
        $channel->setApplicationManager($this->applicationManager);

        $watcherReceived = [];
        $watcher = $this->createPresenceConnection('connection-watcher', $watcherReceived);
        $this->subscribeChannelMember($channel, $watcher, 'user-watcher', ['name' => 'Watcher']);

        $firstReceived = [];
        $first = $this->createPresenceConnection('connection-first', $firstReceived);
        $this->subscribeChannelMember($channel, $first, 'user-1', ['name' => 'User 1']);

        $memberAdded = $this->filterByEvent($watcherReceived, 'pusher_internal:member_added');
        $this->assertCount(1, $memberAdded);
        $this->assertSame('user-1', json_decode($memberAdded[0]['data'], true)['user_id']);

        $watcherReceived = [];
        $secondReceived = [];
        $second = $this->createPresenceConnection('connection-second', $secondReceived);
        $this->subscribeChannelMember($channel, $second, 'user-1', ['name' => 'User 1']);

        $this->assertCount(0, $this->filterByEvent($watcherReceived, 'pusher_internal:member_added'));
        $this->assertNotEmpty($this->filterByEvent($secondReceived, 'pusher_internal:subscription_succeeded'));
    }

    #[Test]
    public function memberRemovedAnnouncedOnlyWhenLastConnectionLeaves(): void
    {
        $channel = new PusherPresenceChannel('presence-test-channel');
        $channel->setApplicationManager($this->applicationManager);

        $watcherReceived = [];
        $watcher = $this->createPresenceConnection('connection-watcher', $watcherReceived);
        $this->subscribeChannelMember($channel, $watcher, 'user-watcher', ['name' => 'Watcher']);

        $firstReceived = [];
        $first = $this->createPresenceConnection('connection-first', $firstReceived);
        $this->subscribeChannelMember($channel, $first, 'user-1', ['name' => 'User 1']);

        $secondReceived = [];
        $second = $this->createPresenceConnection('connection-second', $secondReceived);
        $this->subscribeChannelMember($channel, $second, 'user-1', ['name' => 'User 1']);

        $watcherReceived = [];
        $channel->unsubscribe($first);

        $this->assertCount(0, $this->filterByEvent($watcherReceived, 'pusher_internal:member_removed'));

        $channel->unsubscribe($second);

        $memberRemoved = $this->filterByEvent($watcherReceived, 'pusher_internal:member_removed');
        $this->assertCount(1, $memberRemoved);
        $this->assertSame('user-1', json_decode($memberRemoved[0]['data'], true)['user_id']);
    }

    #[Test]
    public function presenceDataToleratesMembersWithoutUserInfo(): void
    {
        $channel = new PusherPresenceChannel('presence-test-channel');
        $channel->setApplicationManager($this->applicationManager);

        $received = [];
        $connection = $this->createPresenceConnection('connection-7', $received);
        $channelName = $channel->getName();
        $data = (string)json_encode(['user_id' => 'user-7']);
        $signature = hash_hmac('sha256', "connection-7:{$channelName}:{$data}", 'app-secret');

        $channel->subscribe($connection, "app-key:{$signature}", $data);

        $presence = $channel->data();
        $this->assertEquals((object)[], $presence['presence']['hash']['user-7']['user_info']);
    }

    #[Test]
    public function earliestConnectionDecidedByIdOnTimestampTie(): void
    {
        $channel = new PusherPresenceChannel('presence-test-channel');

        $received = [];
        $current = $this->createPresenceConnection('socket-two', $received);

        $method = new ReflectionMethod($channel, 'hasEarlierConnection');

        $this->assertTrue($method->invoke($channel, [
            ['id' => 'socket-two', 'subscribed_at' => 100.0],
            ['id' => 'socket-one', 'subscribed_at' => 100.0],
        ], $current));

        $this->assertFalse($method->invoke($channel, [
            ['id' => 'socket-two', 'subscribed_at' => 100.0],
        ], $current));

        $this->assertFalse($method->invoke($channel, [], $current));
    }

    #[Test]
    public function memberAddedDispatchedThroughEventDispatcherWhenScaling(): void
    {
        $published = [];
        $provider = Double::for(RedisPubSubProvider::class);
        $provider->expects('publish')
            ->times(2)
            ->resolves(function (mixed ...$args) use (&$published): mixed {
                $payload = $args[0];
                $published[] = $payload;

                return resolve(null);
            });

        EventDispatcher::setPubSubProvider($provider);
        try {
            $channel = new PusherPresenceChannel('presence-test-channel');
            $channel->setApplicationManager($this->applicationManager);

            $watcherReceived = [];
            $watcher = $this->createPresenceConnection('connection-watcher', $watcherReceived, ['app_id' => 'app-id']);
            $this->subscribeChannelMember($channel, $watcher, 'user-watcher', ['name' => 'Watcher']);

            $firstReceived = [];
            $first = $this->createPresenceConnection('connection-first', $firstReceived, ['app_id' => 'app-id']);
            $this->subscribeChannelMember($channel, $first, 'user-1', ['name' => 'User 1']);

            $this->assertCount(0, $this->filterByEvent($watcherReceived, 'pusher_internal:member_added'));
            $this->assertNotEmpty($this->filterByEvent($firstReceived, 'pusher_internal:subscription_succeeded'));

            $memberAdded = array_values(array_filter(
                $published,
                fn(array $payload): bool => ($payload['payload']['event'] ?? null) === 'pusher_internal:member_added'
                    && json_decode($payload['payload']['data'], true)['user_id'] === 'user-1',
            ));
            $this->assertCount(1, $memberAdded);
            $this->assertSame('message', $memberAdded[0]['type']);
            $this->assertSame('app-id', $memberAdded[0]['app_id']);
            $this->assertSame('connection-first', $memberAdded[0]['socket_id']);
            $this->assertSame('presence-test-channel', $memberAdded[0]['payload']['channels'][0]);
        } finally {
            EventDispatcher::setPubSubProvider(null);
        }
    }
}
