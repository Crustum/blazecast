<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection as WebSocketConnection;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\UsersTerminateController;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
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
    use VerifiesDoubles;

    /**
     * Controller instance
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\UsersTerminateController
     */
    private UsersTerminateController $controller;

    /**
     * Stub application manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager&\JMac\Testing\DoubleInterface
     */
    private ApplicationManager $applicationManager;

    /**
     * Stub channel manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\JMac\Testing\DoubleInterface
     */
    private ChannelManager $channelManager;

    /**
     * Stub connection manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager&\JMac\Testing\DoubleInterface
     */
    private ChannelConnectionManager $connectionManager;

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

        $this->applicationManager = Double::for(ApplicationManager::class);
        $this->channelManager = Double::for(ChannelManager::class);
        $this->connectionManager = Double::for(ChannelConnectionManager::class);

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
        $connection = Double::for(Connection::class);
        $params = ['appId' => $appId, 'userId' => $userId];

        $mockConnection1 = $this->createMockConnection('conn-1', $userId);
        $mockConnection2 = $this->createMockConnection('conn-2', $userId);
        $mockConnection3 = $this->createMockConnection('conn-3', 'other-user');

        $mockChannel = Double::for(PusherChannel::class);
        $mockChannel->allows('getName')->returns('test-channel');

        $channelConnections = [
            'test-channel' => [
                'conn-1' => $mockConnection1,
                'conn-2' => $mockConnection2,
                'conn-3' => $mockConnection3,
            ],
        ];

        $this->setupApplicationManagerMock($appId);
        $this->setupChannelManagerMock(['test-channel' => $mockChannel]);

        $connectionManager = Double::for(ChannelConnectionManager::class);
        $connectionManager->allows('getActiveChannelNames')
            ->returns(array_keys($channelConnections));
        $connectionManager->allows('getConnectionsForChannel')
            ->resolves(function (mixed ...$args) use ($channelConnections): mixed {
                $channel = $args[0];
                $channelName = $channel->getName();

                return $channelConnections[$channelName] ?? [];
            });

        $mockConnection1->expects('close');
        $mockConnection2->expects('close');
        $mockConnection3->expects('close')->never();

        $connectionManager->expects('unsubscribeAll')
            ->times(2)
            ->with(Argument::any($mockConnection1, $mockConnection2));

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
        $connection = Double::for(Connection::class);
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
        $connection = Double::for(Connection::class);
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
        $connection = Double::for(Connection::class);
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

        $mockChannel = Double::for(PusherChannel::class);
        $mockChannel->allows('getName')->returns('test-channel');

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

        $connectionWithGetAttribute = Double::for(WebSocketConnection::class);
        $connectionWithGetAttribute->allows('getAttribute')
            ->with('user_id')
            ->returns($userId);
        $connectionWithGetAttribute->allows('getAttribute')
            ->with('userId')
            ->returns(null);

        $connectionWithGetAttributes = Double::for(WebSocketConnection::class);
        $connectionWithGetAttributes->allows('getAttribute')->returns(null);
        $connectionWithGetAttributes->allows('getAttributes')
            ->returns(['userId' => $userId]);

        $mockChannel = Double::for(PusherChannel::class);
        $mockChannel->allows('getName')->returns('test-channel');

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
        $connection = Double::for(Connection::class);
        $params = ['appId' => $appId, 'userId' => $userId];

        $sharedConnection = $this->createMockConnection('conn-1', $userId);

        $channelOne = Double::for(PusherChannel::class);
        $channelOne->allows('getName')->returns('channel-one');
        $channelTwo = Double::for(PusherChannel::class);
        $channelTwo->allows('getName')->returns('channel-two');

        $channelConnections = [
            'channel-one' => ['conn-1' => $sharedConnection],
            'channel-two' => ['conn-1' => $sharedConnection],
        ];

        $this->setupApplicationManagerMock($appId);
        $this->setupChannelManagerMock([
            'channel-one' => $channelOne,
            'channel-two' => $channelTwo,
        ]);

        $connectionManager = Double::for(ChannelConnectionManager::class);
        $connectionManager->allows('getActiveChannelNames')
            ->returns(array_keys($channelConnections));
        $connectionManager->allows('getConnectionsForChannel')
            ->resolves(function (mixed ...$args) use ($channelConnections): mixed {
                $channel = $args[0];
                $channelName = $channel->getName();

                return $channelConnections[$channelName] ?? [];
            });

        $sharedConnection->expects('close');
        $connectionManager->expects('unsubscribeAll');

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
     * @return \Crustum\BlazeCast\WebSocket\Connection&\JMac\Testing\DoubleInterface
     */
    private function createStubConnection(string $connectionId, string $userId): WebSocketConnection
    {
        $connection = Double::for(WebSocketConnection::class);
        $connection->allows('getId')->returns($connectionId);
        $connection->allows('getAttribute')
            ->with('user_id')
            ->returns($userId);
        $connection->allows('getAttribute')
            ->with('userId')
            ->returns(null);

        return $connection;
    }

    /**
     * Create mock connection with user ID
     *
     * @param string $connectionId Connection ID
     * @param string $userId User ID
     * @return \Crustum\BlazeCast\WebSocket\Connection&\JMac\Testing\DoubleInterface
     */
    private function createMockConnection(string $connectionId, string $userId): WebSocketConnection
    {
        $connection = Double::for(WebSocketConnection::class);
        $connection->allows('getId')->returns($connectionId);
        $connection->allows('getAttribute')
            ->with('user_id')
            ->returns($userId);
        $connection->allows('getAttribute')
            ->with('userId')
            ->returns(null);
        $connection->allows('close')->resolves(function (): void {
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
        $request = Double::for(RequestInterface::class);
        $request->allows('getMethod')->returns($method);

        $uri = Double::for(UriInterface::class);
        $uri->allows('getPath')->returns('/pusher/health');
        $uri->allows('getQuery')->returns('');
        $request->allows('getUri')->returns($uri);

        $body = Double::for(StreamInterface::class);
        $body->allows('getContents')->returns('{"test": "data"}');
        $request->allows('getBody')->returns($body);

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
        $this->applicationManager->allows('getApplication')
            ->with($appId)
            ->returns($this->testApplication);
    }

    /**
     * Setup connection manager mock
     *
     * @param array<string, mixed> $channelConnections Channel to connections mapping
     * @return void
     */
    private function setupConnectionManagerMock(array $channelConnections): void
    {
        $this->connectionManager->allows('getActiveChannelNames')
            ->returns(array_keys($channelConnections));

        $this->connectionManager->allows('getConnectionsForChannel')
            ->resolves(function (mixed ...$args) use ($channelConnections): mixed {
                $channel = $args[0];
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
        $this->channelManager->allows('getChannel')
            ->resolves(fn(mixed ...$args): mixed => $channels[$args[0]] ?? null);
    }
}
