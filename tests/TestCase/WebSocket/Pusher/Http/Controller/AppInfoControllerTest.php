<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\AppInfoController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

/**
 * AppInfoControllerTest
 *
 * Tests the AppInfoController for GET /apps/{appId} endpoint
 */
class AppInfoControllerTest extends TestCase
{
    /**
     * @var ApplicationManager
     */
    protected ApplicationManager $applicationManager;

    /**
     * @var ChannelManager
     */
    protected ChannelManager $channelManager;

    /**
     * @var ChannelConnectionManager
     */
    protected ChannelConnectionManager $connectionManager;

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
        'max_connections' => 1000,
        'enable_client_messages' => true,
        'enable_statistics' => true,
        'enable_debug' => false,
        'created_at' => 1234567890,
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
        $this->controller = $this->helper->createController(AppInfoController::class);
        $this->helper->configureController($this->controller);
    }

    /**
     * Test handle method with valid app ID
     *
     * @return void
     */
    public function testHandleWithValidAppId(): void
    {
        $appId = 'test-app';
        $params = ['appId' => $appId];

        // Configure controller with test application data
        $this->helper->configureController($this->controller, [
            'application' => $this->testApp,
        ]);

        // Mock channel count
        $this->helper->getChannelManager()
            ->expects($this->once())
            ->method('getChannelCount')
            ->willReturn(5);

        // Mock active channel names
        $this->helper->getConnectionManager()
            ->expects($this->once())
            ->method('getActiveChannelNames')
            ->willReturn(['channel1', 'channel2', 'channel3']);

        $request = $this->createRequest('GET', "/apps/{$appId}");
        $connection = $this->helper->getConnection();

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [$request, $connection, $params]);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertEquals($appId, $responseData['id']);
        $this->assertEquals('Test Application', $responseData['name']);
        $this->assertEquals('us-east-1', $responseData['cluster']);
        $this->assertTrue($responseData['enabled']);
        $this->assertEquals(1000, $responseData['max_connections']);
        $this->assertTrue($responseData['enable_client_messages']);
        $this->assertTrue($responseData['enable_statistics']);
        $this->assertFalse($responseData['enable_debug']);
        $this->assertEquals(1234567890, $responseData['created_at']);
        $this->assertEquals(5, $responseData['channel_count']);
        $this->assertEquals(['channel1', 'channel2', 'channel3'], $responseData['channel_names']);
    }

    /**
     * Test handle method with missing app ID
     *
     * @return void
     */
    public function testHandleWithMissingAppId(): void
    {
        $params = [];
        $request = $this->createRequest('GET', '/apps/unknown');
        $connection = $this->helper->getConnection();

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [$request, $connection, $params]);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertEquals('unknown', $responseData['id']);
        $this->assertEquals('Unknown App', $responseData['name']);
    }

    /**
     * Test handle method with minimal app data
     *
     * @return void
     */
    public function testHandleWithMinimalAppData(): void
    {
        $appId = 'minimal-app';
        $params = ['appId' => $appId];

        $minimalApp = [
            'id' => $appId,
            'key' => 'minimal-key',
            'secret' => 'minimal-secret',
        ];

        // Configure controller with minimal application data
        $this->helper->configureController($this->controller, [
            'application' => $minimalApp,
        ]);

        $this->helper->getChannelManager()
            ->expects($this->once())
            ->method('getChannelCount')
            ->willReturn(0);

        $this->helper->getConnectionManager()
            ->expects($this->once())
            ->method('getActiveChannelNames')
            ->willReturn([]);

        $request = $this->createRequest('GET', "/apps/{$appId}");
        $connection = $this->helper->getConnection();

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [$request, $connection, $params]);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertEquals($appId, $responseData['id']);
        $this->assertEquals('Unknown App', $responseData['name']); // Fallback
        $this->assertEquals('mt1', $responseData['cluster']); // Default
        $this->assertTrue($responseData['enabled']); // Default
        $this->assertEquals(100, $responseData['max_connections']); // Default
        $this->assertTrue($responseData['enable_client_messages']); // Default
        $this->assertTrue($responseData['enable_statistics']); // Default
        $this->assertFalse($responseData['enable_debug']); // Default
        $this->assertNull($responseData['created_at']); // No fallback
        $this->assertEquals(0, $responseData['channel_count']);
        $this->assertEquals([], $responseData['channel_names']);
    }

    /**
     * Test handle method with empty channel data
     *
     * @return void
     */
    public function testHandleWithEmptyChannelData(): void
    {
        $appId = 'empty-app';
        $params = ['appId' => $appId];

        // Configure controller with test application data
        $this->helper->configureController($this->controller, [
            'application' => $this->testApp,
        ]);

        $this->helper->getChannelManager()
            ->expects($this->once())
            ->method('getChannelCount')
            ->willReturn(0);

        $this->helper->getConnectionManager()
            ->expects($this->once())
            ->method('getActiveChannelNames')
            ->willReturn([]);

        $request = $this->createRequest('GET', "/apps/{$appId}");
        $connection = $this->helper->getConnection();

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [$request, $connection, $params]);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertEquals(0, $responseData['channel_count']);
        $this->assertEquals([], $responseData['channel_names']);
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
