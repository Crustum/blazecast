<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\Support;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use JMac\Testing\Double;
use ReflectionClass;
use stdClass;

/**
 * Helper class for testing Pusher controllers
 *
 * Provides utility methods for testing controller methods directly
 * instead of going through the full HTTP request/response cycle
 */
class PusherControllerTestHelper
{
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
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    private ApplicationManager $applicationManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\JMac\Testing\DoubleInterface
     */
    private ChannelManager $channelManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager&\JMac\Testing\DoubleInterface
     */
    private ChannelConnectionManager $connectionManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Connection
     */
    private Connection $connection;

    /**
     * @var TestCase
     */
    private TestCase $testCase;

    /**
     * Constructor
     *
     * @param TestCase $testCase Reference to the test case for assertions and mocking
     */
    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
        $this->setupTestEnvironment();
    }

    /**
     * Setup the test environment
     *
     * @return void
     */
    public function setupTestEnvironment(): void
    {
        $this->applicationManager = new ApplicationManager();
        $reflection = new ReflectionClass($this->applicationManager);
        $appsProperty = $reflection->getProperty('applications');
        $appsProperty->setValue($this->applicationManager, ['test-app' => $this->testApp]);

        $this->channelManager = Double::for(ChannelManager::class);

        $this->connectionManager = Double::for(ChannelConnectionManager::class);

        $reactConnection = new TestConnection();
        $this->connection = new Connection($reactConnection);
    }

    /**
     * Create a new controller instance
     *
     * @param string $controllerClass Controller class name
     * @param array<mixed> $additionalDependencies Additional dependencies for constructor
     * @return PusherControllerInterface
     */
    public function createController(string $controllerClass, array $additionalDependencies = []): PusherControllerInterface
    {
        $dependencies = [
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
        ];

        if ($additionalDependencies !== []) {
            $dependencies = array_merge($dependencies, $additionalDependencies);
        }

        return new $controllerClass(...$dependencies);
    }

    /**
     * Configure controller for testing
     *
     * Directly sets internal properties needed for testing
     *
     * @param PusherControllerInterface $controller The controller to configure
     * @param array<string, mixed> $values Values to set on the controller
     * @return void
     */
    public function configureController(PusherControllerInterface $controller, array $values = []): void
    {
        $testAppWithChannelManager = array_merge($this->testApp, [
            'channel_manager' => $this->channelManager,
        ]);

        $reflectionManager = new ReflectionClass($this->applicationManager);
        $appsProperty = $reflectionManager->getProperty('applications');
        $appsProperty->setValue($this->applicationManager, [
            $this->testApp['id'] => $testAppWithChannelManager,
        ]);

        $defaults = [
            'application' => $testAppWithChannelManager,
            'query' => [],
            'body' => null,
            'requestData' => ['query' => [], 'body' => null, 'params' => []],
        ];

        $values = array_merge($defaults, $values);

        $reflection = new ReflectionClass($controller);

        foreach ($values as $property => $value) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setValue($controller, $value);
            }
        }
    }

    /**
     * Create a stub PusherChannel
     *
     * @param string $name Channel name
     * @param int $connectionCount Connection count
     * @return PusherChannel
     */
    public function createChannelMock(string $name, int $connectionCount = 5): PusherChannel
    {
        $channel = Double::for(PusherChannel::class);

        $channel->allows('getName')
            ->returns($name);

        $channel->allows('getConnectionCount')
            ->returns($connectionCount);

        $channel->allows('getConnections')
            ->returns(array_fill(0, $connectionCount, null));

        return $channel;
    }

    /**
     * Setup channel manager to return specific channels
     *
     * @param array<mixed> $channels Channels to return
     * @return void
     */
    public function setupChannels(array $channels): void
    {
        $this->channelManager->allows('getChannels')
            ->returns($channels);

        foreach ($channels as $channel) {
            $this->channelManager->allows('getChannel')
                ->with($channel->getName())
                ->returns($channel);
        }
    }

    /**
     * Call a protected method on a controller
     *
     * @param object $controller The controller
     * @param string $methodName Method name
     * @param array<mixed> $parameters Method parameters
     * @return mixed
     */
    public function callProtectedMethod(object $controller, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($controller, $parameters);
    }

    /**
     * Get application manager
     *
     * @return ApplicationManager
     */
    public function getApplicationManager(): ApplicationManager
    {
        return $this->applicationManager;
    }

    /**
     * Get channel manager
     *
     * @return ChannelManager
     */
    public function getChannelManager(): ChannelManager
    {
        return $this->channelManager;
    }

    /**
     * Get connection manager
     *
     * @return ChannelConnectionManager
     */
    public function getConnectionManager(): ChannelConnectionManager
    {
        return $this->connectionManager;
    }

    /**
     * Get connection
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Get test application data
     *
     * @return array<string, mixed>
     */
    public function getTestApp(): array
    {
        return $this->testApp;
    }

    /**
     * Assert response matches expectations
     *
     * @param Response $response The response
     * @param int $expectedStatusCode Expected status code
     * @param array<string, mixed>|null $expectedBody Expected body
     * @param array<string, string> $expectedHeaders Expected headers
     */
    public function assertResponse(
        Response $response,
        int $expectedStatusCode,
        ?array $expectedBody = null,
        array $expectedHeaders = [],
    ): void {
        $this->testCase->assertInstanceOf(Response::class, $response);
        $this->testCase->assertEquals($expectedStatusCode, $response->getStatusCode());

        if ($expectedBody !== null) {
            $body = $response->getBody();

            if ($body instanceof stdClass) {
                $body = json_decode(json_encode($body), true);
            }

            $this->testCase->assertEquals($expectedBody, $body);
        }

        foreach ($expectedHeaders as $key => $value) {
            $this->testCase->assertArrayHasKey($key, $response->getHeaders());
            $this->testCase->assertEquals($value, $response->getHeaders()[$key]);
        }
    }
}
