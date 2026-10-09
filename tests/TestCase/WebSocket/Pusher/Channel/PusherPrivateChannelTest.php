<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPrivateChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionUnauthorizedException;
use InvalidArgumentException;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PusherPrivateChannel
 */
class PusherPrivateChannelTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @var PusherPrivateChannel
     */
    private PusherPrivateChannel $channel;

    /**
     * Stub connection object
     */
    private Connection $connection;

    /**
     * Stub application manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    private ApplicationManager $applicationManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = new PusherPrivateChannel('private-test-channel');

        $this->connection = Double::for(Connection::class);
        $this->connection->allows('getId')->returns('connection-123');

        $this->applicationManager = Double::for(ApplicationManager::class);
        $this->channel->setApplicationManager($this->applicationManager);
    }

    #[Test]
    public function privateChannelReturnsCorrectType(): void
    {
        $this->assertEquals('private', $this->channel->getType());
    }

    #[Test]
    public function privateChannelAllowsClientEvents(): void
    {
        $this->assertTrue($this->channel->allowsClientEvents());
    }

    #[Test]
    public function privateChannelRequiresAuthentication(): void
    {
        $this->expectException(ConnectionUnauthorizedException::class);
        $this->expectExceptionMessage('Invalid authentication format');

        $this->channel->subscribe($this->connection);
    }

    #[Test]
    public function privateChannelRejectsInvalidAuthFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid authentication format');

        $this->channel->subscribe($this->connection, 'invalid-auth-format');
    }

    #[Test]
    public function privateChannelRejectsInvalidApplicationKey(): void
    {
        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplicationByKey')
            ->with('app-key')
            ->returns(null);
        $this->channel->setApplicationManager($applicationManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid application key');

        $this->channel->subscribe($this->connection, 'app-key:signature');
    }

    #[Test]
    public function privateChannelRejectsInvalidSignature(): void
    {
        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplicationByKey')
            ->with('app-key')
            ->returns([
                'id' => 'app-id',
                'key' => 'app-key',
                'secret' => 'app-secret',
            ]);
        $this->channel->setApplicationManager($applicationManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid authentication signature');

        $this->channel->subscribe($this->connection, 'app-key:invalid-signature');
    }

    #[Test]
    public function privateChannelAcceptsValidAuthentication(): void
    {
        $connectionId = 'connection-123';
        $channelName = 'private-test-channel';
        $signatureString = "{$connectionId}:{$channelName}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplicationByKey')
            ->with('app-key')
            ->returns([
                'id' => 'app-id',
                'key' => 'app-key',
                'secret' => $secret,
            ]);
        $this->channel->setApplicationManager($applicationManager);

        $this->channel->subscribe($this->connection, "app-key:{$validSignature}");

        $this->assertTrue($this->channel->hasConnection($this->connection));
    }

    #[Test]
    public function privateChannelHandlesChannelData(): void
    {
        $connectionId = 'connection-123';
        $channelName = 'private-test-channel';
        $channelData = '{"user_id":"123","user_info":{"name":"Test User"}}';
        $signatureString = "{$connectionId}:{$channelName}:{$channelData}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplicationByKey')
            ->with('app-key')
            ->returns([
                'id' => 'app-id',
                'key' => 'app-key',
                'secret' => $secret,
            ]);
        $this->channel->setApplicationManager($applicationManager);

        $attributes = [];
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('connection-123');
        $connection->expects('setAttribute')
            ->times(2)
            ->resolves(function (mixed ...$args) use (&$attributes): mixed {
                $key = $args[0];
                $value = $args[1];
                $attributes[$key] = $value;

                return null;
            });

        $this->channel->subscribe($connection, "app-key:{$validSignature}", $channelData);

        $this->assertTrue($this->channel->hasConnection($connection));
        $this->assertArrayHasKey("channel_data_{$channelName}", $attributes);
        $this->assertArrayHasKey("subscribed_at_{$channelName}", $attributes);
        $this->assertIsFloat($attributes["subscribed_at_{$channelName}"]);
    }

    #[Test]
    public function privateChannelCanGetAndSetApplicationManager(): void
    {
        $newManager = Double::for(ApplicationManager::class);
        $this->channel->setApplicationManager($newManager);

        $this->assertSame($newManager, $this->channel->getApplicationManager());
    }

    #[Test]
    public function privateChannelCanBeCreatedWithDifferentNames(): void
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

    #[Test]
    public function privateChannelCanUnsubscribeConnection(): void
    {
        $connectionId = 'connection-123';
        $channelName = 'private-test-channel';
        $signatureString = "{$connectionId}:{$channelName}";
        $secret = 'app-secret';
        $validSignature = hash_hmac('sha256', $signatureString, $secret);

        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplicationByKey')
            ->with('app-key')
            ->returns([
                'id' => 'app-id',
                'key' => 'app-key',
                'secret' => $secret,
            ]);
        $this->channel->setApplicationManager($applicationManager);

        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('connection-123');
        $connection->expects('removeAttribute')
            ->with("channel_data_{$channelName}");
        $connection->expects('removeAttribute')
            ->with("subscribed_at_{$channelName}");

        $this->channel->subscribe($connection, "app-key:{$validSignature}");

        $this->channel->unsubscribe($connection);

        $this->assertFalse($this->channel->hasConnection($connection));
    }
}
