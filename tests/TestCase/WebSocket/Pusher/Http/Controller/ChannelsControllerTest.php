<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ChannelsController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Crustum\BlazeCast\WebSocket\Pusher\MetricsHandler;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

/**
 * Unit tests for ChannelsController
 */
class ChannelsControllerTest extends TestCase
{
    /**
     * @var PusherControllerInterface
     */
    private PusherControllerInterface $controller;

    /**
     * @var PusherControllerTestHelper
     */
    private PusherControllerTestHelper $helper;

    /**
     * @var array<string, mixed>
     */
    private array $testApp = [
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

        $this->controller = $this->helper->createController(ChannelsController::class);

        $this->helper->configureController($this->controller);
    }

    /**
     * Test index returns all channels
     *
     * @return void
     */
    public function testIndexReturnsAllChannels(): void
    {
        $expectedChannels = [
            'test-channel-1' => [
                'occupied' => true,
                'user_count' => 5,
                'subscription_count' => 5,
            ],
            'test-channel-2' => [
                'occupied' => false,
                'user_count' => 0,
                'subscription_count' => 0,
            ],
        ];

        $metricsHandler = $this->getMockBuilder(MetricsHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['gather'])
            ->getMock();

        $metricsHandler->expects($this->once())
            ->method('gather')
            ->with($this->testApp, 'channels', [
                'filter_by_prefix' => null,
                'info' => null,
            ])
            ->willReturn($expectedChannels);

        $controller = $this->getMockBuilder(ChannelsController::class)
            ->setConstructorArgs([
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ])
            ->onlyMethods(['getMetricsHandler'])
            ->getMock();

        $controller->expects($this->any())
            ->method('getMetricsHandler')
            ->willReturn($metricsHandler);

        $this->helper->configureController($controller, [
            'application' => $this->testApp,
        ]);

        $result = $this->helper->callProtectedMethod($controller, 'handle', [
            $this->createRequest('GET', '/apps/test-app/channels'),
            $this->helper->getConnection(),
            ['appId' => 'test-app'],
        ]);

        $this->assertEquals(200, $result->getStatusCode());
        $responseData = json_decode($result->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('channels', $responseData);
        $this->assertEquals($expectedChannels, $responseData['channels']);
    }

    /**
     * Test index filters channels by prefix
     *
     * @return void
     */
    public function testIndexFiltersChannelsByPrefix(): void
    {
        $expectedChannels = [
            'chat-channel-1' => [
                'occupied' => true,
                'user_count' => 5,
                'subscription_count' => 5,
            ],
        ];

        $metricsHandler = $this->getMockBuilder(MetricsHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['gather'])
            ->getMock();

        $metricsHandler->expects($this->once())
            ->method('gather')
            ->with($this->testApp, 'channels', [
                'filter_by_prefix' => 'chat-',
                'info' => null,
            ])
            ->willReturn($expectedChannels);

        $controller = $this->getMockBuilder(ChannelsController::class)
            ->setConstructorArgs([
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ])
            ->onlyMethods(['getMetricsHandler'])
            ->getMock();

        $controller->expects($this->any())
            ->method('getMetricsHandler')
            ->willReturn($metricsHandler);

        $this->helper->configureController($controller, [
            'application' => $this->testApp,
            'query' => ['filter_by_prefix' => 'chat-'],
        ]);

        $request = $this->createRequest('GET', '/apps/test-app/channels?filter_by_prefix=chat-');
        $result = $this->helper->callProtectedMethod($controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            ['appId' => 'test-app'],
        ]);

        $this->assertEquals(200, $result->getStatusCode());
        $responseData = json_decode($result->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('channels', $responseData);
        $this->assertEquals($expectedChannels, $responseData['channels']);
    }

    /**
     * Test index includes additional info when requested
     *
     * @return void
     */
    public function testIndexIncludesAdditionalInfoWhenRequested(): void
    {
        $expectedChannels = [
            'test-channel-1' => [
                'occupied' => true,
                'user_count' => 5,
                'subscription_count' => 5,
            ],
            'presence-channel-1' => [
                'occupied' => true,
                'user_count' => 8,
                'subscription_count' => 10,
            ],
        ];

        $metricsHandler = $this->getMockBuilder(MetricsHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['gather'])
            ->getMock();

        $metricsHandler->expects($this->once())
            ->method('gather')
            ->with($this->testApp, 'channels', [
                'filter_by_prefix' => null,
                'info' => 'user_count,subscription_count',
            ])
            ->willReturn($expectedChannels);

        $controller = $this->getMockBuilder(ChannelsController::class)
            ->setConstructorArgs([
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ])
            ->onlyMethods(['getMetricsHandler'])
            ->getMock();

        $controller->expects($this->any())
            ->method('getMetricsHandler')
            ->willReturn($metricsHandler);

        $this->helper->configureController($controller, [
            'application' => $this->testApp,
            'query' => ['info' => 'user_count,subscription_count'],
        ]);

        $request = $this->createRequest('GET', '/apps/test-app/channels?info=user_count,subscription_count');
        $result = $this->helper->callProtectedMethod($controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            ['appId' => 'test-app'],
        ]);

        $this->assertEquals(200, $result->getStatusCode());
        $responseData = json_decode($result->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('channels', $responseData);
        $this->assertEquals($expectedChannels, $responseData['channels']);
    }

    /**
     * Test handle method with multiple channels
     *
     * @return void
     */
    public function testHandleWithMultipleChannels(): void
    {
        $appId = 'test-app';
        $params = ['appId' => $appId];

        $this->helper->configureController($this->controller, [
            'application' => $this->testApp,
        ]);

        // Mock MetricsHandler::gather to return channel info
        $metricsHandler = $this->getMockBuilder(MetricsHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['gather'])
            ->getMock();

        $metricsHandler->expects($this->once())
            ->method('gather')
            ->with($this->testApp, 'channels', $this->anything())
            ->willReturn([
                'test-channel-1' => [
                    'occupied' => true,
                    'user_count' => 3,
                    'subscription_count' => 3,
                ],
                'test-channel-2' => [
                    'occupied' => true,
                    'user_count' => 5,
                    'subscription_count' => 5,
                ],
                'presence-channel' => [
                    'occupied' => true,
                    'user_count' => 2,
                    'subscription_count' => 2,
                ],
            ]);

        $controller = $this->getMockBuilder(ChannelsController::class)
            ->setConstructorArgs([
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ])
            ->onlyMethods(['getMetricsHandler'])
            ->getMock();
        $controller->expects($this->any())
            ->method('getMetricsHandler')
            ->willReturn($metricsHandler);
        $this->helper->configureController($controller, [
            'application' => $this->testApp,
        ]);

        $request = $this->createRequest('GET', "/apps/{$appId}/channels");
        $connection = $this->helper->getConnection();
        $response = $this->helper->callProtectedMethod($controller, 'handle', [$request, $connection, $params]);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('channels', $responseData);
        $this->assertCount(3, $responseData['channels']);
        $this->assertArrayHasKey('test-channel-1', $responseData['channels']);
        $this->assertTrue($responseData['channels']['test-channel-1']['occupied']);
        $this->assertEquals(3, $responseData['channels']['test-channel-1']['user_count']);
        $this->assertEquals(3, $responseData['channels']['test-channel-1']['subscription_count']);
        $this->assertArrayHasKey('test-channel-2', $responseData['channels']);
        $this->assertTrue($responseData['channels']['test-channel-2']['occupied']);
        $this->assertEquals(5, $responseData['channels']['test-channel-2']['user_count']);
        $this->assertEquals(5, $responseData['channels']['test-channel-2']['subscription_count']);
        $this->assertArrayHasKey('presence-channel', $responseData['channels']);
        $this->assertTrue($responseData['channels']['presence-channel']['occupied']);
        $this->assertEquals(2, $responseData['channels']['presence-channel']['user_count']);
        $this->assertEquals(2, $responseData['channels']['presence-channel']['subscription_count']);
    }

    /**
     * Test handle method with no channels
     *
     * @return void
     */
    public function testHandleWithNoChannels(): void
    {
        $appId = 'test-app';
        $params = ['appId' => $appId];
        $this->helper->configureController($this->controller, [
            'application' => $this->testApp,
        ]);
        $metricsHandler = $this->getMockBuilder(MetricsHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['gather'])
            ->getMock();
        $metricsHandler->expects($this->once())
            ->method('gather')
            ->with($this->testApp, 'channels', $this->anything())
            ->willReturn([]);
        $controller = $this->getMockBuilder(ChannelsController::class)
            ->setConstructorArgs([
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ])
            ->onlyMethods(['getMetricsHandler'])
            ->getMock();
        $controller->expects($this->any())
            ->method('getMetricsHandler')
            ->willReturn($metricsHandler);
        $this->helper->configureController($controller, [
            'application' => $this->testApp,
        ]);
        $request = $this->createRequest('GET', "/apps/{$appId}/channels");
        $connection = $this->helper->getConnection();
        $response = $this->helper->callProtectedMethod($controller, 'handle', [$request, $connection, $params]);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('channels', $responseData);
        $this->assertCount(0, $responseData['channels']);
    }

    /**
     * Test handle method with filter parameter
     *
     * @return void
     */
    public function testHandleWithFilterParameter(): void
    {
        $appId = 'test-app';
        $params = ['appId' => $appId];
        $this->helper->configureController($this->controller, [
            'application' => $this->testApp,
            'query' => ['filter_by_prefix' => 'test-'],
        ]);
        $metricsHandler = $this->getMockBuilder(MetricsHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['gather'])
            ->getMock();
        $metricsHandler->expects($this->once())
            ->method('gather')
            ->with($this->testApp, 'channels', $this->anything())
            ->willReturn([
                'test-channel-1' => [
                    'occupied' => true,
                    'user_count' => 3,
                    'subscription_count' => 3,
                ],
                'test-channel-2' => [
                    'occupied' => true,
                    'user_count' => 5,
                    'subscription_count' => 5,
                ],
            ]);
        $controller = $this->getMockBuilder(ChannelsController::class)
            ->setConstructorArgs([
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ])
            ->onlyMethods(['getMetricsHandler'])
            ->getMock();
        $controller->expects($this->any())
            ->method('getMetricsHandler')
            ->willReturn($metricsHandler);
        $this->helper->configureController($controller, [
            'application' => $this->testApp,
            'query' => ['filter_by_prefix' => 'test-'],
        ]);
        $request = $this->createRequest('GET', "/apps/{$appId}/channels?filter_by_prefix=test-");
        $connection = $this->helper->getConnection();
        $response = $this->helper->callProtectedMethod($controller, 'handle', [$request, $connection, $params]);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('channels', $responseData);
        $this->assertCount(2, $responseData['channels']);
        $this->assertArrayHasKey('test-channel-1', $responseData['channels']);
        $this->assertArrayHasKey('test-channel-2', $responseData['channels']);
        $this->assertArrayNotHasKey('other-channel', $responseData['channels']);
    }

    /**
     * Test handle method with attributes parameter
     *
     * @return void
     */
    public function testHandleWithAttributesParameter(): void
    {
        $appId = 'test-app';
        $params = ['appId' => $appId];
        $this->helper->configureController($this->controller, [
            'application' => $this->testApp,
            'query' => ['info' => 'user_count,subscription_count'],
        ]);
        $metricsHandler = $this->getMockBuilder(MetricsHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['gather'])
            ->getMock();
        $metricsHandler->expects($this->once())
            ->method('gather')
            ->with($this->testApp, 'channels', $this->anything())
            ->willReturn([
                'test-channel-1' => [
                    'user_count' => 3,
                    'subscription_count' => 3,
                ],
            ]);
        $controller = $this->getMockBuilder(ChannelsController::class)
            ->setConstructorArgs([
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ])
            ->onlyMethods(['getMetricsHandler'])
            ->getMock();
        $controller->expects($this->any())
            ->method('getMetricsHandler')
            ->willReturn($metricsHandler);
        $this->helper->configureController($controller, [
            'application' => $this->testApp,
            'query' => ['info' => 'user_count,subscription_count'],
        ]);
        $request = $this->createRequest('GET', "/apps/{$appId}/channels?info=user_count,subscription_count");
        $connection = $this->helper->getConnection();
        $response = $this->helper->callProtectedMethod($controller, 'handle', [$request, $connection, $params]);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('channels', $responseData);
        $this->assertCount(1, $responseData['channels']);
        $channelInfo = $responseData['channels']['test-channel-1'];
        $this->assertArrayHasKey('user_count', $channelInfo);
        $this->assertArrayHasKey('subscription_count', $channelInfo);
        $this->assertArrayNotHasKey('occupied', $channelInfo); // Should not be included
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
