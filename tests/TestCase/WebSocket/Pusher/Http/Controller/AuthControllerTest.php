<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\AuthController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;

/**
 * Unit tests for AuthController
 */
class AuthControllerTest extends TestCase
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
     * Test application data
     *
     * @var array<string, mixed>
     */
    private array $testApp = [
        'id' => 'test-app',
        'key' => 'test-key',
        'secret' => 'test-secret',
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

        $this->controller = $this->helper->createController(AuthController::class);

        $this->helper->configureController($this->controller);
    }

    /**
     * Test auth handles OPTIONS request
     *
     * @return void
     */
    public function testAuthHandlesOptionsRequest(): void
    {
        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $this->createRequest('OPTIONS', '/pusher/auth'),
            $this->helper->getConnection(),
            [],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $response->getHeaders());
    }

    /**
     * Test auth returns error for missing socket_id
     *
     * @return void
     */
    public function testAuthReturnsErrorForMissingSocketId(): void
    {
        $request = $this->createFormRequest('POST', '/pusher/auth', [
            'channel_name' => 'private-channel',
        ]);

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            [],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertArrayHasKey('error', $responseBody);
        $this->assertStringContainsString('socket_id', $responseBody['error']);
    }

    /**
     * Test auth returns error for missing channel_name
     *
     * @return void
     */
    public function testAuthReturnsErrorForMissingChannelName(): void
    {
        $request = $this->createFormRequest('POST', '/pusher/auth', [
            'socket_id' => '123.456',
        ]);

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            [],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertArrayHasKey('error', $responseBody);
        $this->assertStringContainsString('channel_name', $responseBody['error']);
    }

    /**
     * Test auth returns error for public channel
     *
     * @return void
     */
    public function testAuthReturnsErrorForPublicChannel(): void
    {
        $request = $this->createFormRequest('POST', '/pusher/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'public-channel',
        ]);

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            [],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertArrayHasKey('error', $responseBody);
        $this->assertStringContainsString('private and presence', $responseBody['error']);
    }

    /**
     * Test auth generates valid signature for private channel
     *
     * @return void
     */
    public function testAuthGeneratesValidSignatureForPrivateChannel(): void
    {
        $request = $this->createFormRequest('POST', '/pusher/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-channel',
        ]);

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            [],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertArrayHasKey('auth', $responseBody);

        $auth = $responseBody['auth'];
        $this->assertStringStartsWith('test-key:', $auth);

        $authString = '123.456:private-channel';
        $expectedSignature = hash_hmac('sha256', $authString, $this->testApp['secret']);
        $expectedAuth = "{$this->testApp['key']}:{$expectedSignature}";

        $this->assertEquals($expectedAuth, $auth);
    }

    /**
     * Test auth generates valid signature for presence channel
     *
     * @return void
     */
    public function testAuthGeneratesValidSignatureForPresenceChannel(): void
    {
        $userData = json_encode([
            'user_id' => 'user-123',
            'user_info' => [
                'name' => 'Test User',
            ],
        ]);

        $request = $this->createFormRequest('POST', '/pusher/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-channel',
            'channel_data' => $userData,
        ]);

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            [],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertArrayHasKey('auth', $responseBody);
        $this->assertArrayHasKey('channel_data', $responseBody);

        $auth = $responseBody['auth'];
        $this->assertStringStartsWith('test-key:', $auth);

        $authString = "123.456:presence-channel:{$userData}";
        $expectedSignature = hash_hmac('sha256', $authString, $this->testApp['secret']);
        $expectedAuth = "{$this->testApp['key']}:{$expectedSignature}";

        $this->assertEquals($expectedAuth, $auth);
        $this->assertEquals($userData, $responseBody['channel_data']);
    }

    /**
     * Test auth generates default user data for presence channel when not provided
     *
     * @return void
     */
    public function testAuthGeneratesDefaultUserDataForPresenceChannelWhenNotProvided(): void
    {
        $request = $this->createFormRequest('POST', '/pusher/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-channel',
        ]);

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $request,
            $this->helper->getConnection(),
            [],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertArrayHasKey('auth', $responseBody);
        $this->assertArrayHasKey('channel_data', $responseBody);

        $channelData = $responseBody['channel_data'];
        $decodedData = json_decode($channelData, true);

        $this->assertIsArray($decodedData);
        $this->assertArrayHasKey('user_id', $decodedData);
        $this->assertArrayHasKey('user_info', $decodedData);
        $this->assertEquals('123.456', $decodedData['user_id']);
        $this->assertIsArray($decodedData['user_info']);
    }

    /**
     * Create a request with the specified method and path
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array<string, mixed> $headers Optional headers
     * @return \Psr\Http\Message\RequestInterface
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

    /**
     * Create a form request with the specified method, path, and form data
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array<string, mixed> $formData Form data
     * @param array<string, mixed> $headers Optional headers
     * @return \Psr\Http\Message\RequestInterface
     */
    private function createFormRequest(
        string $method,
        string $path,
        array $formData,
        array $headers = [],
    ): RequestInterface {
        $uri = new Uri("http://localhost{$path}");
        $headers = array_merge([
            'Host' => ['localhost'],
            'Content-Type' => ['application/x-www-form-urlencoded'],
        ], $headers);

        $body = http_build_query($formData);

        return new ServerRequest($method, $uri, $headers, $body);
    }
}
