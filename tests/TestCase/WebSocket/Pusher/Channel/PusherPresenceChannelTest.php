<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPresenceChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionUnauthorizedException;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for PusherPresenceChannel
 */
class PusherPresenceChannelTest extends TestCase
{
    /**
     * @var TestablePresenceChannel
     */
    private TestablePresenceChannel $channel;

    /**
     * Mock connection object
     *
     * @var \PHPUnit\Framework\MockObject\MockObject|\Crustum\BlazeCast\WebSocket\Connection
     */
    private $connection;

    /**
     * Mock application manager
     *
     * @var \PHPUnit\Framework\MockObject\MockObject&\Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    private $applicationManager;

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

        $this->connection = $this->createStub(Connection::class);
        $this->connection->method('getId')->willReturn('connection-123');

        $this->connection->method('setAttribute')
            ->willReturnCallback(function ($key, $value) {
                $this->connectionAttributes[$key] = $value;
            });

        $this->connection->method('getAttribute')
            ->willReturnCallback(function ($key) {
                return $this->connectionAttributes[$key] ?? null;
            });

        $this->connection->method('removeAttribute')
            ->willReturnCallback(function ($key) {
                unset($this->connectionAttributes[$key]);
            });

        $this->connection->method('send')
            ->willReturnCallback(function (string $data) {
            });

        $this->applicationManager = $this->createStub(ApplicationManager::class);
        $this->applicationManager->method('getApplicationByKey')
            ->with('app-key')
            ->willReturn([
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

        $connection2 = $this->createMock(Connection::class);
        $connection2->method('getId')->willReturn('connection-456');

        $connection2Attributes = [];
        $connection2->method('setAttribute')
            ->willReturnCallback(function ($key, $value) use (&$connection2Attributes) {
                $connection2Attributes[$key] = $value;
            });

        $connection2->method('getAttribute')
            ->willReturnCallback(function ($key) use (&$connection2Attributes) {
                return $connection2Attributes[$key] ?? null;
            });

        $connection2->method('send')
            ->willReturnCallback(function (string $data) {
            });

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

        $connection2 = $this->createMock(Connection::class);
        $connection2->method('getId')->willReturn('connection-456');
        $connection2Attributes = [];
        $connection2->method('setAttribute')
            ->willReturnCallback(function ($key, $value) use (&$connection2Attributes) {
                $connection2Attributes[$key] = $value;
            });
        $connection2->method('getAttribute')
            ->willReturnCallback(function ($key) use (&$connection2Attributes) {
                return $connection2Attributes[$key] ?? null;
            });
        $connection2->method('send')
            ->willReturnCallback(function (string $data) {
            });

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
        $connection1 = $this->createMock(Connection::class);
        $connection1->method('getId')->willReturn('connection-1');

        $receivedMessages = [];
        $connection2 = $this->createMock(Connection::class);
        $connection2->method('getId')->willReturn('connection-2');
        $connection2->method('send')->willReturnCallback(function (string $data) use (&$receivedMessages) {
            $receivedMessages[] = $data;
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
        $connection = $this->createMock(Connection::class);
        $connection->method('getId')->willReturn('connection-1');
        $connection->method('send')->willReturnCallback(function (string $data) use (&$receivedMessages) {
            $receivedMessages[] = $data;
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
     * @param mixed $connection Connection mock
     * @param string $userId User ID
     * @param array<string, mixed> $userInfo User info
     * @return void
     */
    private function subscribeTestMember($connection, string $userId, array $userInfo): void
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
}
