<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\Integration;

use Cake\Core\Configure;
use Crustum\BlazeCast\Test\Support\TestServer;
use Crustum\BlazeCast\Test\Support\WebSocketTestClient;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use Redis;

/**
 * Rate Limiting Integration Tests
 *
 * Tests rate limiting functionality with real WebSocket connections
 */
class RateLimitingIntegrationTest extends TestCase
{
    private TestServer $server;
    private WebSocketTestClient $client;
    private string $appId = 'test-rate-limit-app';
    private string $appKey = 'test-rate-limit-key';
    private string $appSecret = 'test-rate-limit-secret';
    private ?Redis $redis = null;
    private string $keyPrefix = 'blazecast:rate_limit:';

    /**
     * Data provider for rate limiter drivers
     *
     * @return array<int, array<int, string>>
     */
    public static function rateLimiterDriverProvider(): array
    {
        return [
            ['local'],
            // ['redis'],
        ];
    }

    /**
     * Setup test server with rate limiter configuration
     *
     * @param string $driver Rate limiter driver
     * @return void
     */
    protected function setupServerWithDriver(string $driver): void
    {
        Configure::write('BlazeCast.apps', [
            [
                'id' => $this->appId,
                'key' => $this->appKey,
                'secret' => $this->appSecret,
                'allowed_origins' => ['*'],
                'max_frontend_events_per_second' => 5,
                'enable_client_messages' => true,
            ],
        ]);

        $rateLimiterConfig = [
            'enabled' => true,
            'driver' => $driver,
            'default_limits' => [
                'max_backend_events_per_second' => 100,
                'max_frontend_events_per_second' => 5,
                'max_read_requests_per_second' => 50,
            ],
        ];

        if ($driver === 'redis') {
            $rateLimiterConfig['redis'] = [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => (int)env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD', null),
                'database' => (int)env('REDIS_DB_TEST', 1),
            ];
            $this->cleanupRedisKeys($rateLimiterConfig['redis']);
        }

        Configure::write('BlazeCast.rate_limiter', $rateLimiterConfig);

        $this->server = new TestServer([
            'app_id' => $this->appId,
            'app_key' => $this->appKey,
            'app_secret' => $this->appSecret,
        ], true);

        $this->server->start();
        $this->client = $this->server->createClient();
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->client)) {
            $this->client->close();
        }
        if (isset($this->server)) {
            $this->server->stop();
        }

        $rateLimiterConfig = Configure::read('BlazeCast.rate_limiter', []);
        if (isset($rateLimiterConfig['driver']) && $rateLimiterConfig['driver'] === 'redis' && isset($rateLimiterConfig['redis'])) {
            $this->cleanupRedisKeys($rateLimiterConfig['redis']);
        }

        if (isset($this->redis)) {
            $this->redis->close();
            $this->redis = null;
        }

        Configure::delete('BlazeCast.rate_limiter');
        Configure::delete('BlazeCast.apps');
        parent::tearDown();
    }

    /**
     * Clean up Redis keys created during tests
     *
     * @param array<string, mixed> $redisConfig Redis configuration
     * @return void
     */
    protected function cleanupRedisKeys(array $redisConfig): void
    {
        if (!extension_loaded('redis')) {
            return;
        }

        try {
            if ($this->redis === null) {
                $this->redis = new Redis();
                $connected = $this->redis->connect($redisConfig['host'], $redisConfig['port'], 2);
                if (!$connected) {
                    return;
                }

                if (!empty($redisConfig['password'])) {
                    $this->redis->auth($redisConfig['password']);
                }

                if (!empty($redisConfig['database'])) {
                    $this->redis->select($redisConfig['database']);
                }
            }

            $keys = $this->redis->keys($this->keyPrefix . '*');
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
        } catch (Exception $e) {
        }
    }

    #[Test]
    #[DataProvider('rateLimiterDriverProvider')]
    public function testFrontendEventRateLimitSuccess(string $driver): void
    {
        $this->setupServerWithDriver($driver);

        $loop = Loop::get();
        $uri = $this->server->getWebSocketUri();

        $connected = false;
        $subscriptionConfirmed = false;
        $this->client->connect($uri)->then(function () use (&$connected, &$subscriptionConfirmed, $loop) {
            $connected = true;

            $this->client->onMessage(function ($message) use (&$subscriptionConfirmed, $loop) {
                $decoded = json_decode($message, true);
                if (isset($decoded['event']) && ($decoded['event'] === 'pusher_internal:subscription_succeeded' || $decoded['event'] === 'subscription_succeeded')) {
                    if (!$subscriptionConfirmed) {
                        $subscriptionConfirmed = true;

                        $clientEventMessage = json_encode([
                            'event' => 'client-test-event',
                            'channel' => 'public-test',
                            'data' => json_encode(['message' => 'test']),
                        ]);

                        $this->client->send($clientEventMessage);

                        $loop->addTimer(0.2, function () use ($loop) {
                            $messages = $this->client->getReceivedMessages();
                            $this->assertNotEmpty($messages, 'Should receive messages from server');

                            $hasError = false;
                            $errorMessages = [];
                            foreach ($messages as $message) {
                                $decoded = json_decode($message, true);
                                if (isset($decoded['event']) && $decoded['event'] === 'pusher:error') {
                                    $errorData = json_decode($decoded['data'] ?? '{}', true);
                                    $errorMessages[] = $errorData;
                                    if (isset($errorData['code']) && $errorData['code'] === 4200) {
                                        $hasError = true;
                                    }
                                }
                            }

                            $this->assertFalse($hasError, 'Should not receive rate limit error for single event. Total messages: ' . count($messages) . ', Errors: ' . json_encode($errorMessages) . ', All messages: ' . json_encode(array_slice($messages, -5)));
                            $loop->stop();
                        });
                    }
                }
            });

            $subscribeMessage = json_encode([
                'event' => 'pusher:subscribe',
                'data' => [
                    'channel' => 'public-test',
                ],
            ]);

            $this->client->send($subscribeMessage);
        });

        $loop->run();
        $this->assertTrue($connected, 'Client should connect successfully');
        $this->assertTrue($subscriptionConfirmed, 'Subscription should be confirmed');
    }

    #[Test]
    #[DataProvider('rateLimiterDriverProvider')]
    public function testFrontendEventRateLimitExceeded(string $driver): void
    {
        $this->setupServerWithDriver($driver);

        $loop = Loop::get();
        $uri = $this->server->getWebSocketUri();

        $connected = false;
        $subscriptionConfirmed = false;
        $this->client->connect($uri)->then(function () use (&$connected, &$subscriptionConfirmed, $loop) {
            $connected = true;

            $this->client->onMessage(function ($message) use (&$subscriptionConfirmed, $loop) {
                $decoded = json_decode($message, true);
                if (isset($decoded['event']) && ($decoded['event'] === 'pusher_internal:subscription_succeeded' || $decoded['event'] === 'subscription_succeeded')) {
                    if (!$subscriptionConfirmed) {
                        $subscriptionConfirmed = true;

                        $clientEventMessage = json_encode([
                            'event' => 'client-test-event',
                            'channel' => 'public-test',
                            'data' => json_encode(['message' => 'test']),
                        ]);

                        $sentCount = 0;
                        $sendNext = function () use (&$sentCount, $clientEventMessage, $loop, &$sendNext) {
                            if ($sentCount < 10) {
                                $this->client->send($clientEventMessage);
                                $sentCount++;
                                $loop->addTimer(0.01, $sendNext);
                            } else {
                                $loop->addTimer(0.5, function () use ($loop) {
                                    $messages = $this->client->getReceivedMessages();
                                    $this->assertNotEmpty($messages, 'Should receive messages from server');

                                    $rateLimitErrors = 0;
                                    $allErrors = [];
                                    foreach ($messages as $message) {
                                        $decoded = json_decode($message, true);
                                        if (isset($decoded['event']) && $decoded['event'] === 'pusher:error') {
                                            $errorData = json_decode($decoded['data'] ?? '{}', true);
                                            $allErrors[] = $errorData;
                                            if (isset($errorData['code']) && $errorData['code'] === 4200) {
                                                $rateLimitErrors++;
                                            }
                                        }
                                    }

                                    $this->assertGreaterThan(0, $rateLimitErrors, 'Should receive rate limit error when limit exceeded. Total messages: ' . count($messages) . ', Errors: ' . json_encode($allErrors) . ', All messages: ' . json_encode(array_slice($messages, -10)));
                                    $loop->stop();
                                });
                            }
                        };
                        $sendNext();
                    }
                }
            });

            $subscribeMessage = json_encode([
                'event' => 'pusher:subscribe',
                'data' => [
                    'channel' => 'public-test',
                ],
            ]);

            $this->client->send($subscribeMessage);
        });

        $loop->run();
        $this->assertTrue($connected, 'Client should connect successfully');
        $this->assertTrue($subscriptionConfirmed, 'Subscription should be confirmed');
    }

    #[Test]
    #[DataProvider('rateLimiterDriverProvider')]
    public function testFrontendEventBroadcastWithRateLimiting(string $driver): void
    {
        $this->setupServerWithDriver($driver);

        $loop = Loop::get();
        $uri = $this->server->getWebSocketUri();

        $senderClient = $this->server->createClient();
        $receiverClient = $this->server->createClient();

        $senderConnected = false;
        $senderSubscribed = false;
        $receiverConnected = false;
        $receiverSubscribed = false;
        $receivedEvents = [];
        $rateLimitErrors = 0;

        $senderClient->connect($uri)->then(function () use (&$senderConnected, &$senderSubscribed, $senderClient) {
            $senderConnected = true;

            $senderClient->onMessage(function ($message) use (&$senderSubscribed) {
                $decoded = json_decode($message, true);
                if (isset($decoded['event']) && ($decoded['event'] === 'pusher_internal:subscription_succeeded' || $decoded['event'] === 'subscription_succeeded')) {
                    if (!$senderSubscribed) {
                        $senderSubscribed = true;
                    }
                }
            });

            $subscribeMessage = json_encode([
                'event' => 'pusher:subscribe',
                'data' => [
                    'channel' => 'public-test',
                ],
            ]);

            $senderClient->send($subscribeMessage);
        });

        $receiverClient->connect($uri)->then(function () use (&$receiverConnected, &$receiverSubscribed, $receiverClient, &$receivedEvents) {
            $receiverConnected = true;

            $receiverClient->onMessage(function ($message) use (&$receiverSubscribed, &$receivedEvents) {
                $decoded = json_decode($message, true);
                if (isset($decoded['event']) && ($decoded['event'] === 'pusher_internal:subscription_succeeded' || $decoded['event'] === 'subscription_succeeded')) {
                    if (!$receiverSubscribed) {
                        $receiverSubscribed = true;
                    }
                } elseif (isset($decoded['event']) && $decoded['event'] === 'client-test-event') {
                    $receivedEvents[] = $decoded;
                }
            });

            $subscribeMessage = json_encode([
                'event' => 'pusher:subscribe',
                'data' => [
                    'channel' => 'public-test',
                ],
            ]);

            $receiverClient->send($subscribeMessage);
        });

        $loop->addTimer(0.3, function () use ($loop, $senderClient, &$rateLimitErrors, &$receivedEvents) {
            if (!$senderClient->isConnected()) {
                $loop->stop();

                return;
            }

            $clientEventMessage = json_encode([
                'event' => 'client-test-event',
                'channel' => 'public-test',
                'data' => json_encode(['message' => 'test']),
            ]);

            $senderClient->onMessage(function ($message) use (&$rateLimitErrors) {
                $decoded = json_decode($message, true);
                if (isset($decoded['event']) && $decoded['event'] === 'pusher:error') {
                    $errorData = json_decode($decoded['data'] ?? '{}', true);
                    if (isset($errorData['code']) && $errorData['code'] === 4200) {
                        $rateLimitErrors++;
                    }
                }
            });

            $sentCount = 0;
            $sendNext = function () use (&$sentCount, $clientEventMessage, $loop, &$sendNext, $senderClient, &$receivedEvents, &$rateLimitErrors) {
                if ($sentCount < 10) {
                    $senderClient->send($clientEventMessage);
                    $sentCount++;
                    $loop->addTimer(0.01, $sendNext);
                } else {
                    $loop->addTimer(0.5, function () use ($loop, &$receivedEvents, &$rateLimitErrors) {
                        $this->assertCount(5, $receivedEvents, 'Receiver should receive exactly 5 client events (within rate limit)');
                        $this->assertGreaterThan(0, $rateLimitErrors, 'Sender should receive rate limit errors for messages exceeding the limit');
                        $this->assertLessThanOrEqual(5, $rateLimitErrors, 'Sender should receive at most 5 rate limit errors (for messages 6-10)');
                        $loop->stop();
                    });
                }
            };
            $sendNext();
        });

        $loop->run();

        $this->assertTrue($senderConnected, 'Sender client should connect successfully');
        $this->assertTrue($senderSubscribed, 'Sender should subscribe successfully');
        $this->assertTrue($receiverConnected, 'Receiver client should connect successfully');
        $this->assertTrue($receiverSubscribed, 'Receiver should subscribe successfully');

        $senderClient->close();
        $receiverClient->close();
    }
}
