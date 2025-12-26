<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Channel\Trait;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\Trait\CacheChannelsTrait;

/**
 * Unit tests for CacheChannelsTrait
 */
class CacheChannelsTraitTest extends TestCase
{
    /**
     * @var object
     */
    private object $channel;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&Connection
     */
    private Connection $connection;

    /**
     * Setup method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = new class {
            use CacheChannelsTrait;

            protected string $name = 'test-cache-channel';

            public function getName(): string
            {
                return $this->name;
            }

            public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
            {
                $this->sendCachedMessages($connection);
            }

            /**
             * @param array<string, mixed> $payload
             * @param Connection|null $except
             */
            public function broadcast(array $payload, ?Connection $except = null): void
            {
                $this->cacheMessage($payload);
            }
        };

        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Test subscribe method
     *
     * @return void
     */
    public function testSubscribe(): void
    {
        $this->connection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-1');

        $this->connection->expects($this->any())
            ->method('send');

        $this->channel->subscribe($this->connection);

        $this->assertEquals(0, $this->channel->getCachedMessageCount());
    }

    /**
     * Test broadcast method with regular message
     *
     * @return void
     */
    public function testBroadcastWithRegularMessage(): void
    {
        $payload = [
            'event' => 'test-event',
            'data' => 'test-data',
        ];

        $this->channel->broadcast($payload);

        $this->assertEquals(1, $this->channel->getCachedMessageCount());
        $this->assertArrayHasKey('cached_at', $this->channel->getCachedMessages()[0]);
    }

    /**
     * Test broadcast method with internal message
     *
     * @return void
     */
    public function testBroadcastWithInternalMessage(): void
    {
        $payload = [
            'event' => 'pusher_internal:subscription_succeeded',
            'data' => 'test-data',
        ];

        $this->channel->broadcast($payload);

        $this->assertEquals(0, $this->channel->getCachedMessageCount());
    }

    /**
     * Test cacheMessage method
     *
     * @return void
     */
    public function testCacheMessage(): void
    {
        $payload = [
            'event' => 'test-event',
            'data' => 'test-data',
        ];

        $this->channel->broadcast($payload);

        $cachedMessages = $this->channel->getCachedMessages();
        $this->assertCount(1, $cachedMessages);
        $this->assertEquals('test-event', $cachedMessages[0]['event']);
        $this->assertEquals('test-data', $cachedMessages[0]['data']);
        $this->assertArrayHasKey('cached_at', $cachedMessages[0]);
    }

    /**
     * Test sendCachedMessages method
     *
     * @return void
     */
    public function testSendCachedMessages(): void
    {
        $payload = [
            'event' => 'test-event',
            'data' => 'test-data',
        ];

        $this->channel->broadcast($payload);

        $this->connection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($message) {
                $decoded = json_decode($message, true);

                return $decoded['event'] === 'test-event' && $decoded['data'] === 'test-data';
            }));

        $this->connection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-1');

        $this->channel->subscribe($this->connection);
    }

    /**
     * Test getCachedMessages method
     *
     * @return void
     */
    public function testGetCachedMessages(): void
    {
        $payload1 = ['event' => 'event-1', 'data' => 'data-1'];
        $payload2 = ['event' => 'event-2', 'data' => 'data-2'];

        $this->channel->broadcast($payload1);
        $this->channel->broadcast($payload2);

        $cachedMessages = $this->channel->getCachedMessages();
        $this->assertCount(2, $cachedMessages);
        $this->assertEquals('event-1', $cachedMessages[0]['event']);
        $this->assertEquals('event-2', $cachedMessages[1]['event']);
    }

    /**
     * Test getCachedMessageCount method
     *
     * @return void
     */
    public function testGetCachedMessageCount(): void
    {
        $this->assertEquals(0, $this->channel->getCachedMessageCount());

        $this->channel->broadcast(['event' => 'test-event']);
        $this->assertEquals(1, $this->channel->getCachedMessageCount());

        $this->channel->broadcast(['event' => 'test-event-2']);
        $this->assertEquals(2, $this->channel->getCachedMessageCount());
    }

    /**
     * Test clearCache method
     *
     * @return void
     */
    public function testClearCache(): void
    {
        $this->channel->broadcast(['event' => 'test-event']);
        $this->assertEquals(1, $this->channel->getCachedMessageCount());

        $this->channel->clearCache();
        $this->assertEquals(0, $this->channel->getCachedMessageCount());
    }

    /**
     * Test setMaxCachedMessages method
     *
     * @return void
     */
    public function testSetMaxCachedMessages(): void
    {
        $this->channel->setMaxCachedMessages(5);
        $this->assertEquals(5, $this->channel->getMaxCachedMessages());
    }

    /**
     * Test setMaxCachedMessages method with zero value
     *
     * @return void
     */
    public function testSetMaxCachedMessagesWithZeroValue(): void
    {
        $this->channel->setMaxCachedMessages(0);
        $this->assertEquals(1, $this->channel->getMaxCachedMessages());
    }

    /**
     * Test setMaxCachedMessages method with negative value
     *
     * @return void
     */
    public function testSetMaxCachedMessagesWithNegativeValue(): void
    {
        $this->channel->setMaxCachedMessages(-5);
        $this->assertEquals(1, $this->channel->getMaxCachedMessages());
    }

    /**
     * Test getMaxCachedMessages method
     *
     * @return void
     */
    public function testGetMaxCachedMessages(): void
    {
        $this->assertEquals(100, $this->channel->getMaxCachedMessages());
    }

    /**
     * Test getType method
     *
     * @return void
     */
    public function testGetType(): void
    {
        $this->assertEquals('cache', $this->channel->getType());
    }

    /**
     * Test getCacheStats method
     *
     * @return void
     */
    public function testGetCacheStats(): void
    {
        $this->channel->broadcast(['event' => 'test-event']);

        $stats = $this->channel->getCacheStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('cached_messages', $stats);
        $this->assertArrayHasKey('max_cached_messages', $stats);
        $this->assertArrayHasKey('oldest_message_age', $stats);

        $this->assertEquals(1, $stats['cached_messages']);
        $this->assertEquals(100, $stats['max_cached_messages']);
        $this->assertIsInt($stats['oldest_message_age']);
    }

    /**
     * Test message limit enforcement
     *
     * @return void
     */
    public function testMessageLimitEnforcement(): void
    {
        $this->channel->setMaxCachedMessages(3);

        for ($i = 1; $i <= 5; $i++) {
            $this->channel->broadcast(['event' => "event-{$i}"]);
        }

        $cachedMessages = $this->channel->getCachedMessages();
        $this->assertCount(3, $cachedMessages);
        $this->assertEquals('event-3', $cachedMessages[0]['event']);
        $this->assertEquals('event-4', $cachedMessages[1]['event']);
        $this->assertEquals('event-5', $cachedMessages[2]['event']);
    }

    /**
     * Test getOldestMessageAge method with no messages
     *
     * @return void
     */
    public function testGetOldestMessageAgeWithNoMessages(): void
    {
        $stats = $this->channel->getCacheStats();
        $this->assertNull($stats['oldest_message_age']);
    }

    /**
     * Test getOldestMessageAge method with messages
     *
     * @return void
     */
    public function testGetOldestMessageAgeWithMessages(): void
    {
        $this->channel->broadcast(['event' => 'test-event']);

        $stats = $this->channel->getCacheStats();
        $this->assertIsInt($stats['oldest_message_age']);
        $this->assertGreaterThanOrEqual(0, $stats['oldest_message_age']);
    }

    /**
     * Test cached message structure
     *
     * @return void
     */
    public function testCachedMessageStructure(): void
    {
        $payload = [
            'event' => 'test-event',
            'data' => 'test-data',
            'channel' => 'test-channel',
        ];

        $this->channel->broadcast($payload);

        $cachedMessages = $this->channel->getCachedMessages();
        $cachedMessage = $cachedMessages[0];

        $this->assertEquals('test-event', $cachedMessage['event']);
        $this->assertEquals('test-data', $cachedMessage['data']);
        $this->assertEquals('test-channel', $cachedMessage['channel']);
        $this->assertArrayHasKey('cached_at', $cachedMessage);
        $this->assertIsInt($cachedMessage['cached_at']);
    }

    /**
     * Test sendCachedMessages removes cached_at field
     *
     * @return void
     */
    public function testSendCachedMessagesRemovesCachedAtField(): void
    {
        $payload = [
            'event' => 'test-event',
            'data' => 'test-data',
        ];

        $this->channel->broadcast($payload);

        $this->connection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($message) {
                $decoded = json_decode($message, true);

                return !isset($decoded['cached_at']);
            }));

        $this->connection->expects($this->any())
            ->method('getId')
            ->willReturn('test-connection-1');

        $this->channel->subscribe($this->connection);
    }

    /**
     * Test multiple broadcasts maintain order
     *
     * @return void
     */
    public function testMultipleBroadcastsMaintainOrder(): void
    {
        $events = ['event-1', 'event-2', 'event-3', 'event-4', 'event-5'];

        foreach ($events as $event) {
            $this->channel->broadcast(['event' => $event]);
        }

        $cachedMessages = $this->channel->getCachedMessages();
        $this->assertCount(5, $cachedMessages);

        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals($events[$i], $cachedMessages[$i]['event']);
        }
    }

    /**
     * Test cache overflow behavior
     *
     * @return void
     */
    public function testCacheOverflowBehavior(): void
    {
        $this->channel->setMaxCachedMessages(2);

        $this->channel->broadcast(['event' => 'event-1']);
        $this->channel->broadcast(['event' => 'event-2']);
        $this->channel->broadcast(['event' => 'event-3']);

        $cachedMessages = $this->channel->getCachedMessages();
        $this->assertCount(2, $cachedMessages);
        $this->assertEquals('event-2', $cachedMessages[0]['event']);
        $this->assertEquals('event-3', $cachedMessages[1]['event']);
    }
}
