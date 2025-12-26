<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\EventsController;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use InvalidArgumentException;

/**
 * EventsControllerTest
 *
 * Tests the EventsController using direct method calls rather than going through __invoke
 */
class EventsControllerTest extends TestCase
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
     * @var Connection
     */
    protected Connection $httpConnection;

    /**
     * @var array<string, mixed>
     */
    protected array $testApp = [
        'id' => 'test-app',
        'key' => 'test-key',
        'secret' => 'test-secret',
    ];

    /**
     * @var PusherControllerTestHelper
     */
    protected PusherControllerTestHelper $helper;

    /**
     * Setup method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new PusherControllerTestHelper($this);
        $this->controller = $this->helper->createController(EventsController::class);
        $this->helper->configureController($this->controller);
    }

    /**
     * Test validateEventPayload with missing event name
     *
     * @return void
     */
    public function testMissingEventNameValidation(): void
    {
        $payload = [
            'channels' => ['test-channel'],
            'data' => '{"message":"Hello World"}',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name is required');

        $this->helper->callProtectedMethod($this->controller, 'validateEventPayload', [$payload]);
    }

    /**
     * Test validateEventPayload with missing data
     *
     * @return void
     */
    public function testMissingDataValidation(): void
    {
        $payload = [
            'name' => 'test-event',
            'channels' => ['test-channel'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event data is required');

        $this->helper->callProtectedMethod($this->controller, 'validateEventPayload', [$payload]);
    }

    /**
     * Test validateEventPayload with missing channels
     *
     * @return void
     */
    public function testMissingChannelsValidation(): void
    {
        $payload = [
            'name' => 'test-event',
            'data' => '{"message":"Hello World"}',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Channel or channels is required');

        $this->helper->callProtectedMethod($this->controller, 'validateEventPayload', [$payload]);
    }

    /**
     * Test validateEventPayload with invalid channels format
     *
     * @return void
     */
    public function testInvalidChannelsFormatValidation(): void
    {
        $payload = [
            'name' => 'test-event',
            'channels' => 'not-an-array',
            'data' => '{"message":"Hello World"}',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Channels must be an array');

        $this->helper->callProtectedMethod($this->controller, 'validateEventPayload', [$payload]);
    }

    /**
     * Test validateEventPayload with valid payload
     *
     * @return void
     */
    public function testValidPayloadValidation(): void
    {
        $payload1 = [
            'name' => 'test-event',
            'channel' => 'test-channel',
            'data' => '{"message":"Hello World"}',
        ];

        $this->helper->callProtectedMethod($this->controller, 'validateEventPayload', [$payload1]);

        $payload2 = [
            'name' => 'test-event',
            'channels' => ['test-channel'],
            'data' => '{"message":"Hello World"}',
        ];

        $this->helper->callProtectedMethod($this->controller, 'validateEventPayload', [$payload2]);

        $payload3 = [
            'batch' => [
                [
                    'name' => 'test-event',
                    'channel' => 'test-channel',
                    'data' => '{"message":"Hello World"}',
                ],
            ],
        ];

        $this->helper->callProtectedMethod($this->controller, 'validateEventPayload', [$payload3]);

        $this->assertTrue(true); // No exception was thrown
    }

    /**
     * Test validateEventPayload with invalid batch data
     *
     * @return void
     */
    public function testInvalidBatchDataValidation(): void
    {
        $payload = [
            'batch' => 'not-an-array',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch must be an array');

        $this->helper->callProtectedMethod($this->controller, 'validateEventPayload', [$payload]);
    }

    /**
     * Test handleSingleEvent method
     *
     * @return void
     */
    public function testHandleSingleEvent(): void
    {
        $expectedEventName = 'test-event';
        $expectedChannel = 'test-channel';
        $expectedData = json_encode(['message' => 'Hello World']);
        $expectedMessage = [
            'event' => $expectedEventName,
            'channel' => $expectedChannel,
            'data' => $expectedData,
        ];

        $testChannel = $this->helper->createChannelMock('test-channel', 5);

        $testChannel->expects($this->once())
            ->method('broadcast')
            ->with($this->equalTo($expectedMessage), $this->anything());

        $this->helper->getChannelManager()
            ->method('getChannel')
            ->with($this->equalTo('test-channel'))
            ->willReturn($testChannel);

        $eventData = [
            'name' => $expectedEventName,
            'channel' => $expectedChannel,
            'data' => $expectedData,
        ];

        $response = $this->helper->callProtectedMethod($this->controller, 'handleSingleEvent', [$eventData]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test handleBatchEvents method
     *
     * @return void
     */
    public function testHandleBatchEvents(): void
    {
        $expectedEventName1 = 'test-event-1';
        $expectedChannel1 = 'test-channel-1';
        $expectedData1 = json_encode(['message' => 'Hello World 1']);
        $expectedMessage1 = [
            'event' => $expectedEventName1,
            'channel' => $expectedChannel1,
            'data' => $expectedData1,
        ];

        $expectedEventName2 = 'test-event-2';
        $expectedChannel2 = 'test-channel-2';
        $expectedData2 = json_encode(['message' => 'Hello World 2']);
        $expectedMessage2 = [
            'event' => $expectedEventName2,
            'channel' => $expectedChannel2,
            'data' => $expectedData2,
        ];

        $testChannel1 = $this->helper->createChannelMock('test-channel-1', 5);
        $testChannel2 = $this->helper->createChannelMock('test-channel-2', 10);

        $testChannel1->expects($this->once())
            ->method('broadcast')
            ->with($this->equalTo($expectedMessage1), $this->anything());

        $testChannel2->expects($this->once())
            ->method('broadcast')
            ->with($this->equalTo($expectedMessage2), $this->anything());

        $channelManager = $this->helper->getChannelManager();
        $channelManager->method('getChannel')
            ->willReturnCallback(function ($name) use ($testChannel1, $testChannel2) {
                if ($name === 'test-channel-1') {
                    return $testChannel1;
                } elseif ($name === 'test-channel-2') {
                    return $testChannel2;
                }

                return null;
            });

        $batchData = [
            'batch' => [
                [
                    'name' => $expectedEventName1,
                    'channel' => $expectedChannel1,
                    'data' => $expectedData1,
                ],
                [
                    'name' => $expectedEventName2,
                    'channel' => $expectedChannel2,
                    'data' => $expectedData2,
                ],
            ],
        ];

        $response = $this->helper->callProtectedMethod($this->controller, 'handleBatchEvents', [$batchData]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test getChannelInfo method
     *
     * @return void
     */
    public function testGetChannelInfo(): void
    {
        $testChannel = $this->helper->createChannelMock('test-channel', 5);

        $channelManager = $this->helper->getChannelManager();
        $channelManager->method('getChannel')
            ->with('test-channel')
            ->willReturn($testChannel);

        $result = $this->helper->callProtectedMethod(
            $this->controller,
            'getChannelInfo',
            ['test-channel', 'user_count'],
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('connection_count', $result);
        $this->assertEquals(5, $result['connection_count']);
    }

    /**
     * Test getChannelsInfo method
     *
     * @return void
     */
    public function testGetChannelsInfo(): void
    {
        $channel1 = $this->helper->createChannelMock('test-channel-1', 5);
        $channel2 = $this->helper->createChannelMock('test-channel-2', 10);

        $this->helper->setupChannels([$channel1, $channel2]);

        $result = $this->helper->callProtectedMethod(
            $this->controller,
            'getChannelsInfo',
            [['test-channel-1', 'test-channel-2'], 'user_count'],
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('test-channel-1', $result);
        $this->assertArrayHasKey('test-channel-2', $result);
        $this->assertEquals(5, $result['test-channel-1']['connection_count']);
        $this->assertEquals(10, $result['test-channel-2']['connection_count']);
    }
}
