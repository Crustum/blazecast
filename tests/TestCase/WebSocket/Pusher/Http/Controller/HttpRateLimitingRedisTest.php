<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ChannelsController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ConnectionsController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\EventsController;
use Crustum\BlazeCast\WebSocket\Pusher\MetricsHandler;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface;
use Crustum\BlazeCast\WebSocket\RateLimiter\RedisRateLimiter;
use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use PHPUnit\Framework\Attributes\Test;
use Redis;

/**
 * HTTP Rate Limiting Tests with Redis
 *
 * Tests rate limiting functionality for HTTP API endpoints using Redis rate limiter
 */
class HttpRateLimitingRedisTest extends TestCase
{
    use VerifiesDoubles;

    private PusherControllerTestHelper $helper;

    private RateLimiterInterface $rateLimiter;

    private Redis $redis;

    private string $appId = 'test-http-rate-limit-app-redis';

    private string $appKey = 'test-http-rate-limit-key-redis';

    private string $appSecret = 'test-http-rate-limit-secret-redis';

    private string $keyPrefix = 'blazecast:rate_limit:';

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not loaded');
        }

        $redisConfig = Configure::read('BlazeCast.redis_test') ?? [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int)env('REDIS_PORT', 6379),
            'database' => (int)env('REDIS_DB_TEST', 1),
            'password' => env('REDIS_PASSWORD'),
        ];

        try {
            $this->redis = new Redis();
            $connected = $this->redis->connect($redisConfig['host'], $redisConfig['port'], 2);
            if (!$connected) {
                $this->markTestSkipped('Redis server is not available');
            }

            if (!empty($redisConfig['password'])) {
                $this->redis->auth($redisConfig['password']);
            }

            if (!empty($redisConfig['database'])) {
                $this->redis->select($redisConfig['database']);
            }
        } catch (Exception $exception) {
            $this->markTestSkipped('Could not connect to Redis: ' . $exception->getMessage());
        }

        $this->cleanupRedisKeys();

        Configure::write('BlazeCast.apps', [
            [
                'id' => $this->appId,
                'key' => $this->appKey,
                'secret' => $this->appSecret,
                'allowed_origins' => ['*'],
                'max_backend_events_per_second' => 5,
                'max_read_requests_per_second' => 10,
            ],
        ]);

        $this->helper = new PusherControllerTestHelper($this);
        /** @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\JMac\Testing\DoubleInterface $channelManager */
        $channelManager = $this->helper->getChannelManager();
        $channelManager
            ->allows('getChannel')
            ->with('test-channel')
            ->returns($this->helper->createChannelMock('test-channel'));
        $this->rateLimiter = new RedisRateLimiter(
            [
                $this->appId => [
                    'max_backend_events_per_second' => 5,
                    'max_read_requests_per_second' => 10,
                ],
            ],
            $redisConfig,
        );
    }

    protected function tearDown(): void
    {
        $this->cleanupRedisKeys();
        if (isset($this->rateLimiter)) {
            $this->rateLimiter->disconnect();
        }

        if (isset($this->redis)) {
            $this->redis->close();
        }

        Configure::delete('BlazeCast.apps');
        parent::tearDown();
    }

    /**
     * Clean up Redis keys created during tests
     *
     * @return void
     */
    protected function cleanupRedisKeys(): void
    {
        if (!isset($this->redis)) {
            return;
        }

        $keys = $this->redis->keys($this->keyPrefix . '*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }

    #[Test]
    public function testBackendEventRateLimitSuccess(): void
    {
        $controller = $this->helper->createController(EventsController::class, [null, $this->rateLimiter]);
        $this->helper->configureController($controller, [
            'application' => [
                'id' => $this->appId,
                'key' => $this->appKey,
                'secret' => $this->appSecret,
            ],
            'body' => json_encode([
                'name' => 'test-event',
                'channel' => 'test-channel',
                'data' => json_encode(['message' => 'test']),
            ]),
        ]);

        $request = new ServerRequest('POST', new Uri('/apps/' . $this->appId . '/events'));
        $response = $controller->handle($request, $this->helper->getConnection(), []);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Rate limit exceeded', $response->getContent());
    }

    #[Test]
    public function testBackendEventRateLimitExceeded(): void
    {
        $request = new ServerRequest('POST', new Uri('/apps/' . $this->appId . '/events'));

        for ($i = 0; $i < 5; $i++) {
            $controller = $this->helper->createController(EventsController::class, [null, $this->rateLimiter]);
            $this->helper->configureController($controller, [
                'application' => [
                    'id' => $this->appId,
                    'key' => $this->appKey,
                    'secret' => $this->appSecret,
                ],
                'body' => json_encode([
                    'name' => 'test-event',
                    'channel' => 'test-channel',
                    'data' => json_encode(['message' => 'test']),
                ]),
            ]);
            $response = $controller->handle($request, $this->helper->getConnection(), []);
            $this->assertEquals(200, $response->getStatusCode(), "Request {$i} should succeed");
        }

        $controller = $this->helper->createController(EventsController::class, [null, $this->rateLimiter]);
        $this->helper->configureController($controller, [
            'application' => [
                'id' => $this->appId,
                'key' => $this->appKey,
                'secret' => $this->appSecret,
            ],
            'body' => json_encode([
                'name' => 'test-event',
                'channel' => 'test-channel',
                'data' => json_encode(['message' => 'test']),
            ]),
        ]);
        $response = $controller->handle($request, $this->helper->getConnection(), []);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertStringContainsString('Rate limit exceeded', $response->getContent());
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
    }

    #[Test]
    public function testBackendEventBatchRateLimitExceeded(): void
    {
        $controller = $this->helper->createController(EventsController::class, [null, $this->rateLimiter]);
        $this->helper->configureController($controller, [
            'application' => [
                'id' => $this->appId,
                'key' => $this->appKey,
                'secret' => $this->appSecret,
            ],
            'body' => json_encode([
                'batch' => array_fill(0, 6, [
                    'name' => 'test-event',
                    'channel' => 'test-channel',
                    'data' => json_encode(['message' => 'test']),
                ]),
            ]),
        ]);

        $request = new ServerRequest('POST', new Uri('/apps/' . $this->appId . '/batch_events'));
        $response = $controller->handle($request, $this->helper->getConnection(), []);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertStringContainsString('Rate limit exceeded', $response->getContent());
    }

    #[Test]
    public function testReadRequestRateLimitSuccess(): void
    {
        $metricsHandler = Double::for(MetricsHandler::class);

        $metricsHandler->expects('gather')
            ->returns([]);

        /** @var \JMac\Testing\OverriddenDouble $controllerDouble */
        $controllerDouble = Double::for(ChannelsController::class, override: true)->passthru(
            new ChannelsController(
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
                null,
                $this->rateLimiter,
            ),
        );

        $controllerDouble->expects('getMetricsHandler')
            ->returns($metricsHandler);

        /** @var ChannelsController $controller */
        $controller = $controllerDouble->instance();

        $this->helper->configureController($controller, [
            'application' => [
                'id' => $this->appId,
                'key' => $this->appKey,
                'secret' => $this->appSecret,
            ],
        ]);

        $request = new ServerRequest('GET', new Uri('/apps/' . $this->appId . '/channels'));
        $response = $controller->handle($request, $this->helper->getConnection(), []);

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function testReadRequestRateLimitExceeded(): void
    {
        $metricsHandler = Double::for(MetricsHandler::class);

        $metricsHandler->expects('gather')
            ->times(minimum: 1)
            ->returns([]);

        $request = new ServerRequest('GET', new Uri('/apps/' . $this->appId . '/channels'));

        for ($i = 0; $i < 10; $i++) {
            /** @var \JMac\Testing\OverriddenDouble $controllerDouble */
            $controllerDouble = Double::for(ChannelsController::class, override: true)->passthru(
                new ChannelsController(
                    $this->helper->getApplicationManager(),
                    $this->helper->getChannelManager(),
                    $this->helper->getConnectionManager(),
                    null,
                    $this->rateLimiter,
                ),
            );

            $controllerDouble->expects('getMetricsHandler')
                ->returns($metricsHandler);

            /** @var ChannelsController $controller */
            $controller = $controllerDouble->instance();

            $this->helper->configureController($controller, [
                'application' => [
                    'id' => $this->appId,
                    'key' => $this->appKey,
                    'secret' => $this->appSecret,
                ],
            ]);
            $response = $controller->handle($request, $this->helper->getConnection(), []);
            $this->assertEquals(200, $response->getStatusCode(), "Request {$i} should succeed");
        }

        /** @var \JMac\Testing\OverriddenDouble $controllerDouble */
        $controllerDouble = Double::for(ChannelsController::class, override: true)->passthru(
            new ChannelsController(
                $this->helper->getApplicationManager(),
                $this->helper->getChannelManager(),
                $this->helper->getConnectionManager(),
                null,
                $this->rateLimiter,
            ),
        );

        $controllerDouble->expects('getMetricsHandler')
            ->never();

        /** @var ChannelsController $controller */
        $controller = $controllerDouble->instance();

        $this->helper->configureController($controller, [
            'application' => [
                'id' => $this->appId,
                'key' => $this->appKey,
                'secret' => $this->appSecret,
            ],
        ]);
        $response = $controller->handle($request, $this->helper->getConnection(), []);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertStringContainsString('Rate limit exceeded', $response->getContent());
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
    }

    #[Test]
    public function testConnectionsReadRequestRateLimitExceeded(): void
    {
        $request = new ServerRequest('GET', new Uri('/apps/' . $this->appId . '/connections'));
        $params = ['appId' => $this->appId];

        /** @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager&\JMac\Testing\DoubleInterface $connectionManager */
        $connectionManager = $this->helper->getConnectionManager();
        $connectionManager
            ->allows('getConnectionsForApp')
            ->with($this->appId)
            ->returns([]);

        $connectionManager
            ->allows('getAppStats')
            ->with($this->appId)
            ->returns([
                'connections' => 0,
                'subscriptions' => 0,
                'http_requests' => 0,
            ]);

        for ($i = 0; $i < 10; $i++) {
            $controller = $this->helper->createController(ConnectionsController::class, [null, $this->rateLimiter]);
            $this->helper->configureController($controller, [
                'application' => [
                    'id' => $this->appId,
                    'key' => $this->appKey,
                    'secret' => $this->appSecret,
                ],
            ]);
            $response = $controller->handle($request, $this->helper->getConnection(), $params);
            $this->assertEquals(200, $response->getStatusCode(), "Request {$i} should succeed");
        }

        $controller = $this->helper->createController(ConnectionsController::class, [null, $this->rateLimiter]);
        $this->helper->configureController($controller, [
            'application' => [
                'id' => $this->appId,
                'key' => $this->appKey,
                'secret' => $this->appSecret,
            ],
        ]);
        $response = $controller->handle($request, $this->helper->getConnection(), $params);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertStringContainsString('Rate limit exceeded', $response->getContent());
    }
}
