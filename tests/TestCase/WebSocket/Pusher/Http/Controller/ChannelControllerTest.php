<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ChannelController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Crustum\BlazeCast\WebSocket\Pusher\MetricsHandler;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Unit tests for ChannelController
 */
class ChannelControllerTest extends TestCase
{
    use VerifiesDoubles;

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

        $this->controller = $this->helper->createController(ChannelController::class);

        $this->helper->configureController($this->controller);
    }

    /**
     * Test show returns channel info
     *
     * @return void
     */
    public function testShowReturnsChannelInfo(): void
    {
        $expectedChannelInfo = [
            'occupied' => true,
            'user_count' => 5,
            'subscription_count' => 10,
        ];

        $metricsHandler = Double::for(MetricsHandler::class);

        $metricsHandler->expects('gather')
            ->with(
                $this->testApp,
                'channel',
                Argument::satisfies(fn(array $params): bool => isset($params['channel']) && $params['channel'] === 'test-channel'),
            )
            ->returns($expectedChannelInfo);

        /** @var \JMac\Testing\OverriddenDouble $controllerDouble */
        $controllerDouble = Double::for(ChannelController::class, override: true)->passthru(
            new ChannelController(
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ),
        );

        $controllerDouble->expects('getMetricsHandler')
            ->returns($metricsHandler);

        /** @var ChannelController $controller */
        $controller = $controllerDouble->instance();

        $this->helper->configureController($controller, [
            'application' => $this->testApp,
            'requestData' => [
                'query' => ['info' => 'user_count,subscription_count'],
            ],
        ]);

        $request = $this->createRequest('GET', '/apps/test-app/channels/test-channel?info=user_count,subscription_count');
        $result = $this->helper->callProtectedMethod($controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            ['appId' => 'test-app', 'channelName' => 'test-channel'],
        ]);

        $this->assertEquals(200, $result->getStatusCode());
        $responseData = json_decode((string)$result->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('occupied', $responseData);
        $this->assertArrayHasKey('user_count', $responseData);
        $this->assertArrayHasKey('subscription_count', $responseData);
    }

    /**
     * Test show returns 404 for non-existent channel
     *
     * @return void
     */
    public function testShowReturns404ForNonExistentChannel(): void
    {
        $metricsHandler = Double::for(MetricsHandler::class);

        $metricsHandler->expects('gather')
            ->with($this->testApp, 'channel', [
                'channel' => 'non-existent-channel',
                'info' => 'occupied',
            ])
            ->returns([]);

        /** @var \JMac\Testing\OverriddenDouble $controllerDouble */
        $controllerDouble = Double::for(ChannelController::class, override: true)->passthru(
            new ChannelController(
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ),
        );

        $controllerDouble->expects('getMetricsHandler')
            ->returns($metricsHandler);

        /** @var ChannelController $controller */
        $controller = $controllerDouble->instance();

        $this->helper->configureController($controller, [
            'application' => $this->testApp,
        ]);

        $request = $this->createRequest('GET', '/apps/test-app/channels/non-existent-channel');
        $result = $this->helper->callProtectedMethod($controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            ['appId' => 'test-app', 'channelName' => 'non-existent-channel'],
        ]);

        $this->assertEquals(200, $result->getStatusCode());
        $responseData = json_decode((string)$result->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertEmpty($responseData);
    }

    /**
     * Test show includes presence stats for presence channel
     *
     * @return void
     */
    public function testShowIncludesPresenceStatsForPresenceChannel(): void
    {
        $expectedChannelInfo = [
            'occupied' => true,
            'user_count' => 8,
            'subscription_count' => 10,
            'member_count' => 8,
        ];

        $metricsHandler = Double::for(MetricsHandler::class);

        $metricsHandler->expects('gather')
            ->with(
                $this->testApp,
                'channel',
                Argument::satisfies(fn(array $params): bool => isset($params['channel']) && $params['channel'] === 'presence-channel'),
            )
            ->returns($expectedChannelInfo);

        /** @var \JMac\Testing\OverriddenDouble $controllerDouble */
        $controllerDouble = Double::for(ChannelController::class, override: true)->passthru(
            new ChannelController(
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ),
        );

        $controllerDouble->expects('getMetricsHandler')
            ->returns($metricsHandler);

        /** @var ChannelController $controller */
        $controller = $controllerDouble->instance();

        $this->helper->configureController($controller, [
            'application' => $this->testApp,
            'requestData' => [
                'query' => ['info' => 'user_count,subscription_count,member_count'],
            ],
        ]);

        $request = $this->createRequest('GET', '/apps/test-app/channels/presence-channel?info=user_count,subscription_count,member_count');
        $result = $this->helper->callProtectedMethod($controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            ['appId' => 'test-app', 'channelName' => 'presence-channel'],
        ]);

        $this->assertEquals(200, $result->getStatusCode());
        $responseData = json_decode((string)$result->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('occupied', $responseData);
        $this->assertArrayHasKey('user_count', $responseData);
        $this->assertArrayHasKey('subscription_count', $responseData);
        $this->assertArrayHasKey('member_count', $responseData);
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
     * Create a stream from a string
     *
     * @param string $content Stream content
     * @return \Psr\Http\Message\StreamInterface
     */
    private function createStream(string $content): object
    {
        $stream = Double::for(StreamInterface::class);

        $stream->allows('__toString')
            ->returns($content);

        return $stream;
    }
}
