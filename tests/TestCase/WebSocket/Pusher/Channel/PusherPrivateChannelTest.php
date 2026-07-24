<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPrivateChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionUnauthorizedException;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PusherPrivateChannel
 */
class PusherPrivateChannelTest extends TestCase
{
    /**
     * @var PusherPrivateChannel
     */
    private PusherPrivateChannel $channel;

    /**
     * Mock connection object
     */
    private MockObject|Connection $connection;

    /**
     * Mock application manager
     *
     * @var \PHPUnit\Framework\MockObject\MockObject&\Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    private ApplicationManager&MockObject $applicationManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = new PusherPrivateChannel('private-test-channel');

        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getId')->willReturn('connection-123');

        $this->applicationManager = $this->createMock(ApplicationManager::class);
        $this->channel->setApplicationManager($this->applicationManager);
    }

    public function testPrivateChannelReturnsCorrectType(): void
    {
        $this->assertEquals('private', $this->channel->getType());
    }

    public function testPrivateChannelAllowsClientEvents(): void
    {
        $this->assertTrue($this->channel->allowsClientEvents());
    }

    public function testPrivateChannelRequiresAuthentication(): void
    {
        $this->expectException(ConnectionUnauthorizedException::class);
        $this->expectExceptionMessage('Invalid authentication format');

        $this->channel->subscribe($this->connection);
    }

    public function testPrivateChannelRejectsInvalidAuthFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid authentication format');

        $this->channel->subscribe($this->connection, 'invalid-auth-format');
    }

    public function testPrivateChannelRejectsInvalidApplicationKey(): void
    {
        $this->applicationManager->method('getApplicationByKey')
            ->with('app-key')
            ->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid application key');

        $this->channel->subscribe($this->connection, 'app-key:signature');
    }

    public function testPrivateChannelRejectsInvalidSignature(): void
    {
        $this->applicationManager->method('getApplicationByKey')
            ->with('app-key')
            ->willReturn([
                'id' => 'app-id',
                'key' => 'app-key',
                'secret' => 'app-secret',
            ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid authentication signature');

        $this->channel->subscribe($this->connection, 'app-key:invalid-signature');
    }

    public function testPrivateChannelAcceptsValidAuthentication(): void
    {
        $connectionId = 'connection-123';
        $channelName = 'private-test-channel';
        $signatureString = "{$connectionId}:{$channelName}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $this->applicationManager->method('getApplicationByKey')
            ->with('app-key')
            ->willReturn([
                'id' => 'app-id',
                'key' => 'app-key',
                'secret' => $secret,
            ]);

        $this->channel->subscribe($this->connection, "app-key:{$validSignature}");

        $this->assertTrue($this->channel->hasConnection($this->connection));
    }

    public function testPrivateChannelHandlesChannelData(): void
    {
        $connectionId = 'connection-123';
        $channelName = 'private-test-channel';
        $channelData = '{"user_id":"123","user_info":{"name":"Test User"}}';
        $signatureString = "{$connectionId}:{$channelName}:{$channelData}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $this->applicationManager->method('getApplicationByKey')
            ->with('app-key')
            ->willReturn([
                'id' => 'app-id',
                'key' => 'app-key',
                'secret' => $secret,
            ]);

        $attributes = [];
        $this->connection->expects($this->once())
            ->method('setAttribute')
            ->with("channel_data_{$channelName}", $this->anything())
            ->willReturnCallback(function ($key, $value) use (&$attributes): void {
                $attributes[$key] = $value;
            });

        $this->channel->subscribe($this->connection, "app-key:{$validSignature}", $channelData);

        $this->assertTrue($this->channel->hasConnection($this->connection));
    }

    public function testPrivateChannelCanGetAndSetApplicationManager(): void
    {
        $newManager = $this->createMock(ApplicationManager::class);
        $this->channel->setApplicationManager($newManager);

        $this->assertSame($newManager, $this->channel->getApplicationManager());
    }

    public function testPrivateChannelCanBeCreatedWithDifferentNames(): void
    {
        $validNames = [
            'private-test',
            'private-user-123',
            'private-group.456',
            'private-with_underscore',
            'private-with-dash',
        ];

        foreach ($validNames as $name) {
            $channel = new PusherPrivateChannel($name);
            $this->assertEquals($name, $channel->getName());
            $this->assertEquals('private', $channel->getType());
        }
    }

    public function testPrivateChannelCanUnsubscribeConnection(): void
    {
        $connectionId = 'connection-123';
        $channelName = 'private-test-channel';
        $signatureString = "{$connectionId}:{$channelName}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $this->applicationManager->method('getApplicationByKey')
            ->with('app-key')
            ->willReturn([
                'id' => 'app-id',
                'key' => 'app-key',
                'secret' => $secret,
            ]);

        $this->connection->expects($this->once())
            ->method('removeAttribute')
            ->with("channel_data_{$channelName}");

        $this->channel->subscribe($this->connection, "app-key:{$validSignature}");

        $this->channel->unsubscribe($this->connection);

        $this->assertFalse($this->channel->hasConnection($this->connection));
    }
}
