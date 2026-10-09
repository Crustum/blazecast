<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ConnectionsController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * ConnectionsControllerTest
 *
 * Tests the ConnectionsController for GET /apps/{appId}/connections endpoint
 */
class ConnectionsControllerTest extends TestCase
{
    use VerifiesDoubles;

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

        $connectionManager = Double::for(ChannelConnectionManager::class);
        $connectionManager->expects('getConnectionsForApp')
            ->with($appId)
            ->returns(['conn1', 'conn2', 'conn3']);

        $connectionManager->expects('getAppStats')
            ->with($appId)
            ->returns([
                'connections' => 10,
                'subscriptions' => 15,
                'http_requests' => 5,
            ]);

        $controller = new ConnectionsController(
            $this->helper->getApplicationManager(),
            $this->helper->getChannelManager(),
            $connectionManager,
        );

        // Configure controller with test application data
        $this->helper->configureController($controller, [
            'application' => $this->testApp,
        ]);

        $request = $this->createRequest('GET', "/apps/{$appId}/connections");
        $connection = $this->helper->getConnection();

        $response = $this->helper->callProtectedMethod($controller, 'handle', [$request, $connection, $params]);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode((string)$response->getContent(), true);

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

        $connectionManager = Double::for(ChannelConnectionManager::class);
        $connectionManager->expects('getConnectionsForApp')
            ->with($appId)
            ->returns([]);

        $connectionManager->expects('getAppStats')
            ->with($appId)
            ->returns([
                'connections' => 0,
                'subscriptions' => 0,
                'http_requests' => 0,
            ]);

        $controller = new ConnectionsController(
            $this->helper->getApplicationManager(),
            $this->helper->getChannelManager(),
            $connectionManager,
        );

        // Configure controller with test application data
        $this->helper->configureController($controller, [
            'application' => $this->testApp,
        ]);

        $request = $this->createRequest('GET', "/apps/{$appId}/connections");
        $connection = $this->helper->getConnection();

        $response = $this->helper->callProtectedMethod($controller, 'handle', [$request, $connection, $params]);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode((string)$response->getContent(), true);

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
            return $request->withBody($this->createStream($body));
        }

        return $request;
    }

    /**
     * Create a stream for request body
     *
     * @param string $content Stream content
     * @return \Psr\Http\Message\StreamInterface
     */
    private function createStream(string $content): StreamInterface
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        fseek($stream, 0);

        return Utils::streamFor($stream);
    }
}
