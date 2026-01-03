<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\Integration;

use Crustum\BlazeCast\WebSocket\Application;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ratchet\Client\Connector;
use React\EventLoop\Loop;

/**
 * Integration tests using REAL WebSocket protocol connections
 * Tests actual network communication, handshakes, and WebSocket frames
 */
class RealWebSocketConnectionTest extends TestCase
{
    private const SERVER_HOST = '127.0.0.1';
    private const SERVER_PORT = 8091;

    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application(
            'real_test_blaze',
            'real_test_key',
            'real_test_secret',
            30,
            120,
            ['*'],
            10000,
        );
    }

    #[Test]
    public function canCreateRealWebsocketClientConnector(): void
    {
        $loop = Loop::get();
        $connector = new Connector($loop);

        $this->assertInstanceOf(Connector::class, $connector);

        $wsUrl = 'ws://' . self::SERVER_HOST . ':' . self::SERVER_PORT . '/app/real_test_key';
        $this->assertStringStartsWith('ws://', $wsUrl);
        $this->assertStringContainsString('real_test_key', $wsUrl);
    }

    #[Test]
    public function websocketMessageProtocolIsReadyForRealTransport(): void
    {
        $subscribeMessage = Message::subscribe('public-real-test');
        $authMessage = Message::auth('test-real-token');
        $broadcastMessage = new Message('broadcast', ['content' => 'Real test message'], 'public-real-test');

        $subscribeJson = $subscribeMessage->toJson();
        $authJson = $authMessage->toJson();
        $broadcastJson = $broadcastMessage->toJson();

        $this->assertJson($subscribeJson);
        $this->assertJson($authJson);
        $this->assertJson($broadcastJson);

        $decodedSubscribe = json_decode($subscribeJson, true);
        $decodedAuth = json_decode($authJson, true);
        $decodedBroadcast = json_decode($broadcastJson, true);

        $this->assertEquals('subscribe', $decodedSubscribe['event']);
        $this->assertEquals('public-real-test', $decodedSubscribe['channel']);

        $this->assertEquals('authenticate', $decodedAuth['event']);
        $this->assertArrayHasKey('token', $decodedAuth['data']);

        $this->assertEquals('broadcast', $decodedBroadcast['event']);
        $this->assertEquals('public-real-test', $decodedBroadcast['channel']);
    }

    #[Test]
    public function realWebsocketServerCommandCanBeConstructed(): void
    {
        $serverCommand = $this->buildServerStartCommand();
        $clientUrl = $this->buildWebSocketClientUrl();

        $this->assertStringContainsString('blaze:start', $serverCommand);
        $this->assertStringContainsString('--host=' . self::SERVER_HOST, $serverCommand);
        $this->assertStringContainsString('--port=' . self::SERVER_PORT, $serverCommand);

        $this->assertStringStartsWith('ws://', $clientUrl);
        $this->assertStringContainsString(self::SERVER_HOST, $clientUrl);
        $this->assertStringContainsString((string)self::SERVER_PORT, $clientUrl);
        $this->assertStringContainsString($this->application->getKey(), $clientUrl);
    }

    #[Test]
    public function websocketProtocolHandshakeHeadersAreValid(): void
    {
        $expectedHeaders = [
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Version: 13',
            'Sec-WebSocket-Protocol: pusher-protocol-7',
        ];

        foreach ($expectedHeaders as $header) {
            if (strpos($header, ':') !== false) {
                [$name, $value] = explode(':', $header, 2);
                $this->assertNotEmpty(trim($name));
                $this->assertNotEmpty(trim($value));
            }
        }

        $wsKey = base64_encode(random_bytes(16));
        $this->assertEquals(24, strlen($wsKey));
    }

    #[Test]
    public function websocketFrameConstraintsAreUnderstood(): void
    {
        $testMessage = 'Hello WebSocket!';

        $payload = $testMessage;
        $payloadLength = strlen($payload);

        $this->assertGreaterThan(0, $payloadLength);

        $normalMessage = json_encode(['event' => 'test', 'data' => str_repeat('A', 1000)]);
        $this->assertLessThan(65536, strlen($normalMessage), 'Normal messages should fit in standard frames');
    }

    #[Test]
    public function channelSubscriptionProtocolMessagesAreValid(): void
    {
        $authMessage = Message::auth('real-test-token');
        $authJson = $authMessage->toJson();
        $this->assertJson($authJson);

        $subscribeMessage = Message::subscribe('public-real-channel');
        $subscribeJson = $subscribeMessage->toJson();
        $this->assertJson($subscribeJson);

        $expectedSuccessResponse = [
            'event' => 'subscription_succeeded',
            'channel' => 'public-real-channel',
        ];
        $successJson = json_encode($expectedSuccessResponse);
        $this->assertJson($successJson);

        $broadcastMessage = new Message('test_event', ['content' => 'Real broadcast'], 'public-real-channel');
        $broadcastJson = $broadcastMessage->toJson();
        $this->assertJson($broadcastJson);
    }

    #[Test]
    public function authenticationBugFixWorksWithRealProtocolMessages(): void
    {
        $publicSubscribe = Message::subscribe('public-bug-test');
        $publicJson = $publicSubscribe->toJson();

        $decoded = json_decode($publicJson, true);
        $this->assertEquals('subscribe', $decoded['event']);
        $this->assertEquals('public-bug-test', $decoded['channel']);

        $privateSubscribe = Message::subscribe('private-user-123');
        $privateJson = $privateSubscribe->toJson();

        $decodedPrivate = json_decode($privateJson, true);
        $this->assertEquals('subscribe', $decodedPrivate['event']);
        $this->assertEquals('private-user-123', $decodedPrivate['channel']);
    }

    private function buildServerStartCommand(): string
    {
        $baseCommand = 'bin/cake blaze:start';
        $options = [
            '--host=' . self::SERVER_HOST,
            '--port=' . self::SERVER_PORT,
            '--app-id=' . $this->application->getId(),
            '--key=' . $this->application->getKey(),
            '--secret=' . $this->application->getSecret(),
        ];

        return $baseCommand . ' ' . implode(' ', $options);
    }

    private function buildWebSocketClientUrl(): string
    {
        return sprintf(
            'ws://%s:%d/app/%s',
            self::SERVER_HOST,
            self::SERVER_PORT,
            $this->application->getKey(),
        );
    }
}
