<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ConnectionsController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

/**
 * ConnectionsControllerTest
 *
 * Tests the ConnectionsController for GET /apps/{appId}/connections endpoint
 */
class ConnectionsControllerTest extends TestCase
{
    /**
     * @var PusherControllerInterface
     */
    protected PusherControllerInterface $controller;

    /**
     * @var PusherControllerTestHelper
     */
    protected PusherControllerTestHelper $helper;

    /**
     * @var array<string, mixed>
     */
    protected array $testApp = [
        'id' => 'test-app',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'name' => 'Test Application',
        'cluster' => 'us-east-1',
        'enabled' => true,
    ];

    /**
     * Setup method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new PusherControllerTestHelper($this);
        $this->controller = $this->helper->createController(ConnectionsController::class);
        $this->helper->configureController($this->controller);
    }

    /**
     * Test handle method with connections
     *
     * @return void
     */
    public function testHandleWithConnections(): void
    {
        $appId = 'test-app';
        $params = ['appId' => $appId];

        // Configure controller with test application data
        $this->helper->configureController($this->controller, [
            'application' => $this->testApp,
        ]);

        // Mock connection manager to return connection data
        $this->helper->getConnectionManager()
            ->expects($this->once())
            ->method('getConnectionsForApp')
            ->with($appId)
            ->willReturn(['conn1', 'conn2', 'conn3']);

        $this->helper->getConnectionManager()
            ->expects($this->once())
            ->method('getAppStats')
            ->with($appId)
            ->willReturn([
                'connections' => 10,
                'subscriptions' => 15,
                'http_requests' => 5,
            ]);

        $request = $this->createRequest('GET', "/apps/{$appId}/connections");
        $connection = $this->helper->getConnection();

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [$request, $connection, $params]);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertEquals(10, $responseData['connections']);
        $this->assertEquals(3, $responseData['active_connections']);
        $this->assertEquals(15, $responseData['total_subscriptions']);
    }

    /**
     * Test handle method with no connections
     *
     * @return void
     */
    public function testHandleWithNoConnections(): void
    {
        $appId = 'test-app';
        $params = ['appId' => $appId];

        // Configure controller with test application data
        $this->helper->configureController($this->controller, [
            'application' => $this->testApp,
        ]);

        // Mock connection manager to return empty data
        $this->helper->getConnectionManager()
            ->expects($this->once())
            ->method('getConnectionsForApp')
            ->with($appId)
            ->willReturn([]);

        $this->helper->getConnectionManager()
            ->expects($this->once())
            ->method('getAppStats')
            ->with($appId)
            ->willReturn([
                'connections' => 0,
                'subscriptions' => 0,
                'http_requests' => 0,
            ]);

        $request = $this->createRequest('GET', "/apps/{$appId}/connections");
        $connection = $this->helper->getConnection();

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [$request, $connection, $params]);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertEquals(0, $responseData['connections']);
        $this->assertEquals(0, $responseData['active_connections']);
        $this->assertEquals(0, $responseData['total_subscriptions']);
    }

    /**
     * Create a request with the specified method and path
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array<string, mixed> $headers Request headers
     * @param string|null $body Request body
     * @return RequestInterface
     */
    private function createRequest(string $method, string $path, array $headers = [], ?string $body = null): RequestInterface
    {
        $uri = new Uri("http://localhost{$path}");
        $headers = array_merge([
            'Host' => ['localhost'],
            'Content-Type' => ['application/json'],
        ], $headers);

        $request = new ServerRequest($method, $uri, $headers);

        if ($body !== null) {
            $request = $request->withBody($this->createStream($body));
        }

        return $request;
    }

    /**
     * Create a stream for request body
     *
     * @param string $content Stream content
     * @return \Psr\Http\Message\StreamInterface
     */
    private function createStream(string $content)
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        fseek($stream, 0);

        return Utils::streamFor($stream);
    }
}
