<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Publish;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\Pusher\Publish\PubSubIncomingMessageHandler;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use React\Socket\ConnectionInterface as ReactConnection;

/**
 * PubSubIncomingMessageHandlerTest
 */
class PubSubIncomingMessageHandlerTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * Test incoming Redis payload excludes by raw socket_id.
     *
     * @return void
     */
    public function testExcludesBySocketId(): void
    {
        $channelManager = new ChannelManager();
        $applicationManager = new ApplicationManager([
            'applications' => [[
                'id' => 'app-1',
                'key' => 'key',
                'secret' => 'secret',
                'channel_manager' => $channelManager,
            ]],
        ]);

        $reactExcluded = Double::for(ReactConnection::class);
        $excluded = new Connection($reactExcluded);
        $excluded->setSocketId('1.excluded');

        $reactOther = Double::for(ReactConnection::class);
        $other = new Connection($reactOther);
        $other->setSocketId('2.other');

        $sent = [];
        $reactOther->allows('write')->resolves(function (mixed ...$args) use (&$sent): mixed {
            $sent[] = $args[0] ?? null;

            return true;
        });

        $channel = $channelManager->getChannel('my-channel');
        $channel->subscribe($excluded);
        $channel->subscribe($other);

        $connectionManager = Double::for(ChannelConnectionManager::class);
        $connectionManager->allows('getConnection')->resolves(fn(mixed ...$args): mixed => ($args[0] ?? null) === '1.excluded' ? $excluded : null);

        $handler = new PubSubIncomingMessageHandler($applicationManager, $connectionManager);
        $handler->handle(json_encode([
            'type' => 'message',
            'app_id' => 'app-1',
            'socket_id' => '1.excluded',
            'payload' => [
                'event' => 'client-typing',
                'channel' => 'my-channel',
                'data' => '{"ok":true}',
            ],
        ]));

        $this->assertNotEmpty($sent);
        foreach ($sent as $message) {
            $this->assertStringContainsString('client-typing', (string)$message);
        }
    }
}
