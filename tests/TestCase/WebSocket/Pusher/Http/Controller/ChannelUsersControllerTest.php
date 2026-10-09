<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPresenceChannel;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ChannelUsersController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use Psr\Http\Message\RequestInterface;

/**
 * Unit tests for ChannelUsersController
 */
class ChannelUsersControllerTest extends TestCase
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
     * Setup method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new PusherControllerTestHelper($this);

        $this->controller = $this->helper->createController(ChannelUsersController::class);

        $this->helper->configureController($this->controller);
    }

    /**
     * Test OPTIONS request returns correct response
     *
     * @return void
     */
    public function testOptionsRequestReturnsCorrectResponse(): void
    {
        $this->helper->configureController($this->controller, [
            'requestData' => [
                'method' => 'OPTIONS',
                'path' => '/apps/test-app/channels/presence-channel/users',
            ],
        ]);

        $response = $this->controller->__invoke(
            $this->createRequest('OPTIONS', '/apps/test-app/channels/presence-channel/users'),
            $this->helper->getConnection(),
            [
                'channel' => 'presence-channel',
            ],
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $response->getHeaders());
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $response->getHeaders());
        $this->assertArrayHasKey('Access-Control-Allow-Headers', $response->getHeaders());
    }

    /**
     * Test index returns error for non-presence channel
     *
     * @return void
     */
    public function testIndexReturnsErrorForNonPresenceChannel(): void
    {
        $regularChannelDouble = Double::for(PusherChannel::class);

        $regularChannelDouble->allows('getName')
            ->returns('public-channel');

        $regularChannel = $regularChannelDouble->instance();

        /** @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\JMac\Testing\DoubleInterface $channelManager */
        $channelManager = $this->helper->getChannelManager();
        $channelManager
            ->allows('getChannel')
            ->with('public-channel')
            ->returns($regularChannel);

        $this->helper->configureController($this->controller, [
            'requestData' => [
                'method' => 'GET',
                'path' => '/apps/test-app/channels/public-channel/users',
            ],
        ]);

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $this->createAuthenticatedRequest('GET', '/apps/test-app/channels/public-channel/users'),
            $this->helper->getConnection(),
            [
                'channel' => 'public-channel',
            ],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertArrayHasKey('error', $responseBody);
    }

    /**
     * Test index handles non-existent channel
     *
     * @return void
     */
    public function testIndexReturns404ForNonExistentChannel(): void
    {
        $this->helper->setupChannels([]);
        /** @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\JMac\Testing\DoubleInterface $channelManager */
        $channelManager = $this->helper->getChannelManager();
        $channelManager
            ->allows('getChannel')
            ->with('presence-non-existent')
            ->returns($this->helper->createChannelMock('presence-non-existent', 0));

        $this->helper->configureController($this->controller, [
            'requestData' => [
                'method' => 'GET',
                'path' => '/apps/test-app/channels/presence-non-existent/users',
            ],
        ]);

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $this->createAuthenticatedRequest('GET', '/apps/test-app/channels/presence-non-existent/users'),
            $this->helper->getConnection(),
            [
                'channel' => 'presence-non-existent',
            ],
        ]);

        $this->assertInstanceOf(Response::class, $response);
    }

    /**
     * Test index returns channel users
     *
     * @return void
     */
    public function testIndexReturnsChannelUsers(): void
    {
        $users = [
            ['user_id' => 'user1', 'user_info' => ['name' => 'User 1']],
            ['user_id' => 'user2', 'user_info' => ['name' => 'User 2']],
        ];

        $presenceChannelDouble = Double::for(PusherPresenceChannel::class, override: true);

        $presenceChannelDouble->allows('getName')
            ->returns('presence-channel');

        $presenceChannelDouble->allows('getMembers')
            ->returns($users);

        $presenceChannel = $presenceChannelDouble->instance();

        /** @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\JMac\Testing\DoubleInterface $channelManager */
        $channelManager = $this->helper->getChannelManager();
        $channelManager
            ->allows('getChannel')
            ->with('presence-channel')
            ->returns($presenceChannel);

        $channelManager
            ->allows('hasChannel')
            ->with('presence-channel')
            ->returns(true);

        $this->helper->configureController($this->controller, [
            'requestData' => [
                'method' => 'GET',
                'path' => '/apps/test-app/channels/presence-channel/users',
            ],
        ]);

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $this->createAuthenticatedRequest('GET', '/apps/test-app/channels/presence-channel/users'),
            $this->helper->getConnection(),
            [
                'channel' => 'presence-channel',
            ],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertArrayHasKey('users', $responseBody);
    }

    /**
     * Test index returns empty user list
     *
     * @return void
     */
    public function testIndexReturnsEmptyUserList(): void
    {
        $presenceChannelDouble = Double::for(PusherPresenceChannel::class, override: true);

        $presenceChannelDouble->allows('getName')
            ->returns('presence-empty');

        $presenceChannelDouble->allows('getMembers')
            ->returns([]);

        $presenceChannel = $presenceChannelDouble->instance();

        /** @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\JMac\Testing\DoubleInterface $channelManager */
        $channelManager = $this->helper->getChannelManager();
        $channelManager
            ->allows('getChannel')
            ->with('presence-empty')
            ->returns($presenceChannel);

        $channelManager
            ->allows('hasChannel')
            ->with('presence-empty')
            ->returns(true);

        $this->helper->configureController($this->controller, [
            'requestData' => [
                'method' => 'GET',
                'path' => '/apps/test-app/channels/presence-empty/users',
            ],
        ]);

        $response = $this->helper->callProtectedMethod($this->controller, 'handle', [
            $this->createAuthenticatedRequest('GET', '/apps/test-app/channels/presence-empty/users'),
            $this->helper->getConnection(),
            [
                'channel' => 'presence-empty',
            ],
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $this->assertArrayHasKey('users', $responseBody);
        $this->assertEmpty($responseBody['users']);
    }

    /**
     * Create a request with the specified method and path
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array<string, mixed> $headers Request headers
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

    /**
     * Create a request with authentication parameters
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array<string, mixed> $headers Optional headers
     * @return RequestInterface
     */
    private function createAuthenticatedRequest(string $method, string $path, array $headers = []): RequestInterface
    {
        $authPath = $path . (str_contains($path, '?') ? '&' : '?') . 'auth_key=test-key&auth_signature=test-signature';

        return $this->createRequest($method, $authPath, $headers);
    }
}
