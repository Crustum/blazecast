<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection as WebSocketConnection;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannelInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\UsersTerminateController;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * UsersTerminateController Test Case
 *
 * Tests the UsersTerminateController functionality for terminating
 * all connections belonging to a specific user.
 */
class UsersTerminateControllerTest extends TestCase
{
    /**
     * Controller instance
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\UsersTerminateController
     */
    private UsersTerminateController $controller;

    /**
     * Stub application manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager&\PHPUnit\Framework\MockObject\Stub
     */
    private ApplicationManager&Stub $applicationManager;

    /**
     * Stub channel manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\PHPUnit\Framework\MockObject\Stub
     */
    private ChannelManager&Stub $channelManager;

    /**
     * Stub connection manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager&\PHPUnit\Framework\MockObject\Stub
     */
    private ChannelConnectionManager&Stub $connectionManager;

    /**
     * Test data
     *
     * @var array<string, mixed>
     */
    private array $testApplication = [
        'id' => 'test-app',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'name' => 'Test Application',
    ];

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationManager = $this->createStub(ApplicationManager::class);
        $this->channelManager = $this->createStub(ChannelManager::class);
        $this->connectionManager = $this->createStub(ChannelConnectionManager::class);

        $this->controller = new UsersTerminateController(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
        );
    }

    /**
     * Test successful termination of user connections
     *
     * @return void
     */
    public function testTerminateUserConnectionsSuccess(): void
    {
        $userId = 'user-123';
        $appId = 'test-app';

        $request = $this->createMockRequest('POST');
        $connection = $this->createStub(Connection::class);
        $params = ['appId' => $appId, 'userId' => $userId];

        $mockConnection1 = $this->createMockConnection('conn-1', $userId);
        $mockConnection2 = $this->createMockConnection('conn-2', $userId);
        $mockConnection3 = $this->createMockConnection('conn-3', 'other-user');

        $mockChannel = $this->createStub(PusherChannelInterface::class);
        $mockChannel->method('getName')->willReturn('test-channel');

        $channelConnections = [
            'test-channel' => [
                'conn-1' => $mockConnection1,
                'conn-2' => $mockConnection2,
                'conn-3' => $mockConnection3,
            ],
        ];

        $this->setupApplicationManagerMock($appId);
        $this->setupChannelManagerMock(['test-channel' => $mockChannel]);

        $connectionManager = $this->createMock(ChannelConnectionManager::class);
        $connectionManager->method('getActiveChannelNames')
            ->willReturn(array_keys($channelConnections));
        $connectionManager->method('getConnectionsForChannel')
            ->willReturnCallback(function ($channel) use ($channelConnections): array {
                $channelName = $channel->getName();

                return $channelConnections[$channelName] ?? [];
            });

        $mockConnection1->expects($this->once())->method('close');
        $mockConnection2->expects($this->once())->method('close');
        $mockConnection3->expects($this->never())->method('close');

        $connectionManager->expects($this->exactly(2))
            ->method('unsubscribeAll')
            ->with($this->logicalOr($mockConnection1, $mockConnection2));

        $controller = new UsersTerminateController(
            $this->applicationManager,
            $this->channelManager,
            $connectionManager,
        );

        $response = $controller->__invoke($request, $connection, $params);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('User connections terminated', $responseData['message']);
        $this->assertEquals(2, $responseData['terminated_connections']);
    }

    /**
     * Test termination when no connections exist for user
     *
     * @return void
     */
    public function testTerminateUserConnectionsNoConnections(): void
    {
        $userId = 'user-999';
        $appId = 'test-app';

        $request = $this->createMockRequest('POST');
        $connection = $this->createStub(Connection::class);
        $params = ['appId' => $appId, 'userId' => $userId];

        $this->setupApplicationManagerMock($appId);
        $this->setupConnectionManagerMock([]);
        $this->setupChannelManagerMock([]);

        $response = $this->controller->__invoke($request, $connection, $params);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals(0, $responseData['terminated_connections']);
    }

    /**
     * Test error handling when missing appId parameter
     *
     * @return void
     */
    public function testTerminateUserConnectionsMissingAppId(): void
    {
        $request = $this->createMockRequest('POST');
        $connection = $this->createStub(Connection::class);
        $params = ['userId' => 'user-123'];

        $response = $this->controller->__invoke($request, $connection, $params);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Missing appId or userId parameters', $responseData['error']);
    }

    /**
     * Test error handling when missing userId parameter
     *
     * @return void
     */
    public function testTerminateUserConnectionsMissingUserId(): void
    {
        $request = $this->createMockRequest('POST');
        $connection = $this->createStub(Connection::class);
        $params = ['appId' => 'test-app'];

        $this->setupApplicationManagerMock('test-app');

        $response = $this->controller->__invoke($request, $connection, $params);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Missing appId or userId parameters', $responseData['error']);
    }

    /**
     * Test getUserConnections method
     *
     * @return void
     */
    public function testGetUserConnections(): void
    {
        $userId = 'user-123';

        $mockConnection1 = $this->createStubConnection('conn-1', $userId);
        $mockConnection2 = $this->createStubConnection('conn-2', $userId);
        $mockConnection3 = $this->createStubConnection('conn-3', 'other-user');

        $mockChannel = $this->createStub(PusherChannelInterface::class);
        $mockChannel->method('getName')->willReturn('test-channel');

        $this->setupConnectionManagerMock([
            'test-channel' => [
                'conn-1' => $mockConnection1,
                'conn-2' => $mockConnection2,
                'conn-3' => $mockConnection3,
            ],
        ]);
        $this->setupChannelManagerMock(['test-channel' => $mockChannel]);

        $userConnections = $this->controller->getUserConnections($userId);

        $this->assertCount(2, $userConnections);
        $this->assertContains('conn-1', $userConnections);
        $this->assertContains('conn-2', $userConnections);
        $this->assertNotContains('conn-3', $userConnections);
    }

    /**
     * Test connection identification with various user ID formats
     *
     * @return void
     */
    public function testUserConnectionIdentificationFormats(): void
    {
        $userId = 'user-123';

        $connectionWithGetAttribute = $this->createStub(WebSocketConnection::class);
        $connectionWithGetAttribute->method('getAttribute')
            ->willReturnMap([
                ['user_id', null, $userId],
                ['userId', null, null],
            ]);

        $connectionWithGetAttributes = $this->createStub(WebSocketConnection::class);
        $connectionWithGetAttributes->method('getAttribute')->willReturn(null);
        $connectionWithGetAttributes->method('getAttributes')
            ->willReturn(['userId' => $userId]);

        $mockChannel = $this->createStub(PusherChannelInterface::class);
        $mockChannel->method('getName')->willReturn('test-channel');

        $this->setupConnectionManagerMock([
            'test-channel' => [
                'conn-1' => $connectionWithGetAttribute,
                'conn-2' => $connectionWithGetAttributes,
            ],
        ]);
        $this->setupChannelManagerMock(['test-channel' => $mockChannel]);

        $userConnections = $this->controller->getUserConnections($userId);

        $this->assertCount(2, $userConnections);
        $this->assertContains('conn-1', $userConnections);
        $this->assertContains('conn-2', $userConnections);
    }

    /**
     * Test that a socket subscribed to several channels is terminated once.
     *
     * Port of upstream 52ce5fd: per-channel maps hold the same connection,
     * so terminate/count must be deduplicated by connection ID.
     *
     * @return void
     */
    public function testTerminateUserConnectionsDedupesMultiChannelSocket(): void
    {
        $userId = 'user-123';
        $appId = 'test-app';

        $request = $this->createMockRequest('POST');
        $connection = $this->createStub(Connection::class);
        $params = ['appId' => $appId, 'userId' => $userId];

        $sharedConnection = $this->createMockConnection('conn-1', $userId);

        $channelOne = $this->createStub(PusherChannelInterface::class);
        $channelOne->method('getName')->willReturn('channel-one');
        $channelTwo = $this->createStub(PusherChannelInterface::class);
        $channelTwo->method('getName')->willReturn('channel-two');

        $channelConnections = [
            'channel-one' => ['conn-1' => $sharedConnection],
            'channel-two' => ['conn-1' => $sharedConnection],
        ];

        $this->setupApplicationManagerMock($appId);
        $this->setupChannelManagerMock([
            'channel-one' => $channelOne,
            'channel-two' => $channelTwo,
        ]);

        $connectionManager = $this->createMock(ChannelConnectionManager::class);
        $connectionManager->method('getActiveChannelNames')
            ->willReturn(array_keys($channelConnections));
        $connectionManager->method('getConnectionsForChannel')
            ->willReturnCallback(function ($channel) use ($channelConnections): array {
                $channelName = $channel->getName();

                return $channelConnections[$channelName] ?? [];
            });

        $sharedConnection->expects($this->once())->method('close');
        $connectionManager->expects($this->once())->method('unsubscribeAll');

        $controller = new UsersTerminateController(
            $this->applicationManager,
            $this->channelManager,
            $connectionManager,
        );

        $response = $controller->__invoke($request, $connection, $params);

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals(1, $responseData['terminated_connections']);
    }

    /**
     * Create stub connection with user ID (no verification)
     *
     * @param string $connectionId Connection ID
     * @param string $userId User ID
     * @return \Crustum\BlazeCast\WebSocket\Connection
     */
    private function createStubConnection(string $connectionId, string $userId): WebSocketConnection
    {
        $connection = $this->createStub(WebSocketConnection::class);
        $connection->method('getId')->willReturn($connectionId);
        $connection->method('getAttribute')
            ->willReturnMap([
                ['user_id', null, $userId],
                ['userId', null, null],
            ]);

        return $connection;
    }

    /**
     * Create mock connection with user ID
     *
     * @param string $connectionId Connection ID
     * @param string $userId User ID
     * @return \Crustum\BlazeCast\WebSocket\Connection&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockConnection(string $connectionId, string $userId): WebSocketConnection&MockObject
    {
        $connection = $this->createMock(WebSocketConnection::class);
        $connection->method('getId')->willReturn($connectionId);
        $connection->method('getAttribute')
            ->willReturnMap([
                ['user_id', null, $userId],
                ['userId', null, null],
            ]);
        $connection->method('close')->willReturnCallback(function (): void {
        });

        return $connection;
    }

    /**
     * Create stub HTTP request
     *
     * @param string $method HTTP method
     * @return \Psr\Http\Message\RequestInterface
     */
    private function createMockRequest(string $method): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getMethod')->willReturn($method);

        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/pusher/health');
        $uri->method('getQuery')->willReturn('');
        $request->method('getUri')->willReturn($uri);

        $body = $this->createStub(StreamInterface::class);
        $body->method('getContents')->willReturn('{"test": "data"}');
        $request->method('getBody')->willReturn($body);

        return $request;
    }

    /**
     * Setup application manager mock
     *
     * @param string $appId Application ID
     * @return void
     */
    private function setupApplicationManagerMock(string $appId): void
    {
        $this->applicationManager->method('getApplication')
            ->willReturnMap([
                [$appId, $this->testApplication],
            ]);
    }

    /**
     * Setup connection manager mock
     *
     * @param array<string, mixed> $channelConnections Channel to connections mapping
     * @return void
     */
    private function setupConnectionManagerMock(array $channelConnections): void
    {
        $this->connectionManager->method('getActiveChannelNames')
            ->willReturn(array_keys($channelConnections));

        $this->connectionManager->method('getConnectionsForChannel')
            ->willReturnCallback(function ($channel) use ($channelConnections) {
                $channelName = $channel->getName();

                return $channelConnections[$channelName] ?? [];
            });
    }

    /**
     * Setup channel manager mock
     *
     * @param array<string, mixed> $channels Channel name to object mapping
     * @return void
     */
    private function setupChannelManagerMock(array $channels): void
    {
        $this->channelManager->method('getChannel')
            ->willReturnCallback(fn($channelName) => $channels[$channelName] ?? null);
    }
}
