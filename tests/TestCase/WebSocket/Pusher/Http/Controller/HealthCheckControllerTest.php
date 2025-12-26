<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\HealthCheckController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;

/**
 * HealthCheckControllerTest
 */
class HealthCheckControllerTest extends TestCase
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
     * Setup test
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->helper = new PusherControllerTestHelper($this);

        $this->controller = $this->helper->createController(HealthCheckController::class);

        $this->helper->configureController($this->controller);
    }

    /**
     * Test health check endpoint returns OK status
     *
     * @return void
     */
    public function testHealthCheckReturnsOkStatus(): void
    {
        $request = $this->createRequest('GET', '/pusher/health');

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            [],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertIsArray($responseBody);
        $this->assertArrayHasKey('health', $responseBody);
        $this->assertEquals('OK', $responseBody['health']);
    }

    /**
     * Test health check endpoint handles exceptions
     *
     * @return void
     */
    public function testHealthCheckHandlesExceptions(): void
    {
        $request = $this->createRequest('GET', '/pusher/health');

        $controller = $this->getMockBuilder(HealthCheckController::class)
            ->setConstructorArgs([
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
            ])
            ->onlyMethods(['handle'])
            ->getMock();

        $controller->method('handle')
            ->willThrowException(new Exception('Test exception'));

        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('__invoke');
        $response = $method->invokeArgs($controller, [$request, $this->helper->getConnection(), []]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertIsArray($responseBody);
        $this->assertArrayHasKey('error', $responseBody);
    }

    /**
     * Create a request with the specified method and path
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array<string, mixed> $headers Optional headers
     * @return RequestInterface
     */
    private function createRequest(string $method, string $path, array $headers = []): RequestInterface
    {
        $uri = new Uri("http://localhost{$path}");
        $headers = array_merge([
            'Host' => ['localhost'],
            'Content-Type' => ['application/json'],
        ], $headers);

        return new ServerRequest($method, $uri, $headers);
    }
}
