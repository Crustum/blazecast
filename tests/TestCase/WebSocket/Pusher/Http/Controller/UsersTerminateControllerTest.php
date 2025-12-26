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
     * Mock application manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager&\PHPUnit\Framework\MockObject\MockObject
     */
    private ApplicationManager&MockObject $applicationManager;

    /**
     * Mock channel manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\PHPUnit\Framework\MockObject\MockObject
     */
    private ChannelManager&MockObject $channelManager;

    /**
     * Mock connection manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager&\PHPUnit\Framework\MockObject\MockObject
     */
    private ChannelConnectionManager&MockObject $connectionManager;

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
    public function setUp(): void
    {
        parent::setUp();

        $this->applicationManager = $this->createMock(ApplicationManager::class);
        $this->channelManager = $this->createMock(ChannelManager::class);
        $this->connectionManager = $this->createMock(ChannelConnectionManager::class);

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
        $connection = $this->createMock(Connection::class);
        $params = ['appId' => $appId, 'userId' => $userId];

        $mockConnection1 = $this->createMockConnection('conn-1', $userId);
        $mockConnection2 = $this->createMockConnection('conn-2', $userId);
        $mockConnection3 = $this->createMockConnection('conn-3', 'other-user');

        $mockChannel = $this->createMock(PusherChannelInterface::class);
        $mockChannel->method('getName')->willReturn('test-channel');

        $this->setupApplicationManagerMock($appId);
        $this->setupConnectionManagerMock([
            'test-channel' => [
                'conn-1' => $mockConnection1,
                'conn-2' => $mockConnection2,
                'conn-3' => $mockConnection3,
            ],
        ]);
        $this->setupChannelManagerMock(['test-channel' => $mockChannel]);

        $mockConnection1->expects($this->once())->method('close');
        $mockConnection2->expects($this->once())->method('close');
        $mockConnection3->expects($this->never())->method('close');

        $this->connectionManager->expects($this->exactly(2))
            ->method('unsubscribeAll')
            ->with($this->logicalOr($mockConnection1, $mockConnection2));

        $response = $this->controller->__invoke($request, $connection, $params);

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
        $connection = $this->createMock(Connection::class);
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
        $connection = $this->createMock(Connection::class);
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
        $connection = $this->createMock(Connection::class);
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

        $mockConnection1 = $this->createMockConnection('conn-1', $userId);
        $mockConnection2 = $this->createMockConnection('conn-2', $userId);
        $mockConnection3 = $this->createMockConnection('conn-3', 'other-user');

        $mockChannel = $this->createMock(PusherChannelInterface::class);
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

        $connectionWithGetAttribute = $this->createMock(WebSocketConnection::class);
        $connectionWithGetAttribute->method('getAttribute')
            ->willReturnMap([
                ['user_id', null, $userId],
                ['userId', null, null],
            ]);

        $connectionWithGetAttributes = $this->createMock(WebSocketConnection::class);
        $connectionWithGetAttributes->method('getAttribute')->willReturn(null);
        $connectionWithGetAttributes->method('getAttributes')
            ->willReturn(['userId' => $userId]);

        $mockChannel = $this->createMock(PusherChannelInterface::class);
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
        $connection->method('close')->willReturnCallback(function () {
        });

        return $connection;
    }

    /**
     * Create mock HTTP request
     *
     * @param string $method HTTP method
     * @return \Psr\Http\Message\RequestInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockRequest(string $method): RequestInterface&MockObject
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn($method);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/pusher/health');
        $uri->method('getQuery')->willReturn('');
        $request->method('getUri')->willReturn($uri);

        $body = $this->createMock(StreamInterface::class);
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
            ->with($appId)
            ->willReturn($this->testApplication);
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
            ->willReturnCallback(function ($channelName) use ($channels) {
                return $channels[$channelName] ?? null;
            });
    }
}
