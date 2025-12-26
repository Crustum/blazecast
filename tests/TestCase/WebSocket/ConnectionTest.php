<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket;

use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Exception;
use React\Socket\ConnectionInterface as ReactConnectionInterface;
use ReflectionClass;

/**
 * Connection Test Case
 */
class ConnectionTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Crustum\BlazeCast\WebSocket\Connection
     */
    protected Connection $connection;

    /**
     * Mock React connection
     *
     * @var \React\Socket\ConnectionInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    protected ReactConnectionInterface $mockReactConnection;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockReactConnection = $this->createMock(ReactConnectionInterface::class);
        $this->connection = new Connection($this->mockReactConnection, 'test-connection-id');
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->connection);
        unset($this->mockReactConnection);

        parent::tearDown();
    }

    /**
     * Test constructor with custom ID
     *
     * @return void
     */
    public function testConstructorWithCustomId(): void
    {
        $connection = new Connection($this->mockReactConnection, 'custom-id');
        $this->assertEquals('custom-id', $connection->getId());
    }

    /**
     * Test constructor with generated ID
     *
     * @return void
     */
    public function testConstructorWithGeneratedId(): void
    {
        $connection = new Connection($this->mockReactConnection);
        $this->assertNotEmpty($connection->getId());
        $this->assertIsString($connection->getId());
    }

    /**
     * Test getId method
     *
     * @return void
     */
    public function testGetId(): void
    {
        $this->assertEquals('test-connection-id', $this->connection->getId());
    }

    /**
     * Test getId method with socket ID
     *
     * @return void
     */
    public function testGetIdWithSocketId(): void
    {
        $this->connection->setSocketId('123.456');
        $this->assertEquals('123.456', $this->connection->getId());
    }

    /**
     * Test send method with connected state
     *
     * @return void
     */
    public function testSendWithConnectedState(): void
    {
        $this->connection->markAsConnected();

        $this->mockReactConnection->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($data) {
                // Check if it's a WebSocket frame
                return strlen($data) > 0;
            }));

        $this->connection->send('test data');
    }

    /**
     * Test send method with non-connected state
     *
     * @return void
     */
    public function testSendWithNonConnectedState(): void
    {
        $this->mockReactConnection->expects($this->once())
            ->method('write')
            ->with('test data');

        $this->connection->send('test data');
    }

    /**
     * Test send method with exception
     *
     * @return void
     */
    public function testSendWithException(): void
    {
        $this->connection->markAsConnected();

        $this->mockReactConnection->expects($this->once())
            ->method('write')
            ->willThrowException(new Exception('Write failed'));

        $this->connection->send('test data');
        $this->assertTrue($this->connection->isConnected());
    }

    /**
     * Test close method
     *
     * @return void
     */
    public function testClose(): void
    {
        $this->mockReactConnection->expects($this->once())
            ->method('end');

        $this->connection->close();
        $this->assertFalse($this->connection->isConnected());
    }

    /**
     * Test close method with exception
     *
     * @return void
     */
    public function testCloseWithException(): void
    {
        $this->mockReactConnection->expects($this->once())
            ->method('end')
            ->willThrowException(new Exception('Close failed'));

        $this->connection->close();
        $this->assertFalse($this->connection->isConnected());
    }

    /**
     * Test isActive method
     *
     * @return void
     */
    public function testIsActive(): void
    {
        $this->connection->markAsConnected();

        $this->mockReactConnection->expects($this->once())
            ->method('isWritable')
            ->willReturn(true);

        $this->assertTrue($this->connection->isActive());
    }

    /**
     * Test isActive method with non-writable connection
     *
     * @return void
     */
    public function testIsActiveWithNonWritableConnection(): void
    {
        $this->connection->markAsConnected();

        $this->mockReactConnection->expects($this->once())
            ->method('isWritable')
            ->willReturn(false);

        $this->assertFalse($this->connection->isActive());
    }

    /**
     * Test isActive method with non-connected state
     *
     * @return void
     */
    public function testIsActiveWithNonConnectedState(): void
    {
        $this->assertFalse($this->connection->isActive());
    }

    /**
     * Test isStale method
     *
     * @return void
     */
    public function testIsStale(): void
    {
        // Simulate old activity
        $reflection = new ReflectionClass($this->connection);
        $property = $reflection->getProperty('lastActivity');
        $property->setValue($this->connection, microtime(true) - 200);

        $this->assertTrue($this->connection->isStale(120));
    }

    /**
     * Test isStale method with recent activity
     *
     * @return void
     */
    public function testIsStaleWithRecentActivity(): void
    {
        $this->connection->updateActivity();
        $this->assertFalse($this->connection->isStale(120));
    }

    /**
     * Test isStale method with custom threshold
     *
     * @return void
     */
    public function testIsStaleWithCustomThreshold(): void
    {
        $this->connection->updateActivity();
        $this->assertFalse($this->connection->isStale(1));
    }

    /**
     * Test ping method
     *
     * @return void
     */
    public function testPing(): void
    {
        $this->connection->markAsConnected();

        $this->mockReactConnection->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($data) {
                // Check if it's a WebSocket ping frame
                return strlen($data) > 0;
            }));

        $this->connection->ping('websocket');
    }

    /**
     * Test ping method with pusher type
     *
     * @return void
     */
    public function testPingWithPusherType(): void
    {
        $this->mockReactConnection->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($data) {
                $decoded = json_decode($data, true);

                return $decoded['event'] === 'pusher:ping';
            }));

        $this->connection->ping('pusher');
    }

    /**
     * Test pong method
     *
     * @return void
     */
    public function testPong(): void
    {
        $this->connection->pong('websocket');

        $pingState = $this->connection->getPingState();
        $this->assertNotNull($pingState['last_pong_time']);
    }

    /**
     * Test getPingState method
     *
     * @return void
     */
    public function testGetPingState(): void
    {
        $pingState = $this->connection->getPingState();

        $this->assertArrayHasKey('last_ping_time', $pingState);
        $this->assertArrayHasKey('last_pong_time', $pingState);
        $this->assertArrayHasKey('pending_pings', $pingState);
        $this->assertArrayHasKey('ping_count', $pingState);
    }

    /**
     * Test resetPingState method
     *
     * @return void
     */
    public function testResetPingState(): void
    {
        $this->connection->ping('websocket');
        $this->connection->pong('websocket');

        $this->connection->resetPingState();

        $pingState = $this->connection->getPingState();
        $this->assertNull($pingState['last_ping_time']);
        $this->assertNull($pingState['last_pong_time']);
        $this->assertEquals(0, $pingState['pending_pings']);
        $this->assertEquals(0, $pingState['ping_count']);
    }

    /**
     * Test updateActivity method
     *
     * @return void
     */
    public function testUpdateActivity(): void
    {
        $oldActivity = $this->connection->getLastActivity();
        sleep(1);
        $this->connection->updateActivity();
        $newActivity = $this->connection->getLastActivity();

        $this->assertGreaterThan($oldActivity, $newActivity);
    }

    /**
     * Test setAttribute method
     *
     * @return void
     */
    public function testSetAttribute(): void
    {
        $this->connection->setAttribute('test_key', 'test_value');
        $this->assertEquals('test_value', $this->connection->getAttribute('test_key'));
    }

    /**
     * Test getAttribute method with default value
     *
     * @return void
     */
    public function testGetAttributeWithDefaultValue(): void
    {
        $value = $this->connection->getAttribute('non_existent', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    /**
     * Test getAttribute method without default value
     *
     * @return void
     */
    public function testGetAttributeWithoutDefaultValue(): void
    {
        $value = $this->connection->getAttribute('non_existent');
        $this->assertNull($value);
    }

    /**
     * Test hasAttribute method
     *
     * @return void
     */
    public function testHasAttribute(): void
    {
        $this->assertFalse($this->connection->hasAttribute('test_key'));

        $this->connection->setAttribute('test_key', 'test_value');
        $this->assertTrue($this->connection->hasAttribute('test_key'));
    }

    /**
     * Test removeAttribute method
     *
     * @return void
     */
    public function testRemoveAttribute(): void
    {
        $this->connection->setAttribute('test_key', 'test_value');
        $this->assertTrue($this->connection->hasAttribute('test_key'));

        $this->connection->removeAttribute('test_key');
        $this->assertFalse($this->connection->hasAttribute('test_key'));
    }

    /**
     * Test getAttributes method
     *
     * @return void
     */
    public function testGetAttributes(): void
    {
        $this->connection->setAttribute('key1', 'value1');
        $this->connection->setAttribute('key2', 'value2');

        $attributes = $this->connection->getAttributes();
        $this->assertEquals('value1', $attributes['key1']);
        $this->assertEquals('value2', $attributes['key2']);
    }

    /**
     * Test control method
     *
     * @return void
     */
    public function testControl(): void
    {
        $this->connection->markAsConnected();

        $this->mockReactConnection->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($data) {
                // Check if it's a WebSocket control frame
                return strlen($data) > 0;
            }));

        $this->connection->control(0x8, 'close payload');
    }

    /**
     * Test control method with exception
     *
     * @return void
     */
    public function testControlWithException(): void
    {
        $this->connection->markAsConnected();

        $this->mockReactConnection->expects($this->once())
            ->method('write')
            ->willThrowException(new Exception('Control failed'));

        $this->connection->control(0x8);
        $this->assertTrue($this->connection->isConnected());
    }

    /**
     * Test sendMessage method
     *
     * @return void
     */
    public function testSendMessage(): void
    {
        $this->connection->markAsConnected();

        $this->mockReactConnection->expects($this->once())
            ->method('write');

        $this->connection->sendMessage('test message');
    }

    /**
     * Test isConnected method
     *
     * @return void
     */
    public function testIsConnected(): void
    {
        $this->assertFalse($this->connection->isConnected());

        $this->connection->markAsConnected();
        $this->assertTrue($this->connection->isConnected());
    }

    /**
     * Test getBuffer method
     *
     * @return void
     */
    public function testGetBuffer(): void
    {
        $this->assertEquals('', $this->connection->getBuffer());
    }

    /**
     * Test hasBuffer method
     *
     * @return void
     */
    public function testHasBuffer(): void
    {
        $this->assertFalse($this->connection->hasBuffer());

        $this->connection->appendToBuffer('test data');
        $this->assertTrue($this->connection->hasBuffer());
    }

    /**
     * Test appendToBuffer method
     *
     * @return void
     */
    public function testAppendToBuffer(): void
    {
        $this->connection->appendToBuffer('part1');
        $this->connection->appendToBuffer('part2');

        $this->assertEquals('part1part2', $this->connection->getBuffer());
    }

    /**
     * Test clearBuffer method
     *
     * @return void
     */
    public function testClearBuffer(): void
    {
        $this->connection->appendToBuffer('test data');
        $this->assertTrue($this->connection->hasBuffer());

        $this->connection->clearBuffer();
        $this->assertFalse($this->connection->hasBuffer());
    }

    /**
     * Test getSocketId method
     *
     * @return void
     */
    public function testGetSocketId(): void
    {
        $this->assertNull($this->connection->getSocketId());

        $this->connection->setSocketId('123.456');
        $this->assertEquals('123.456', $this->connection->getSocketId());
    }

    /**
     * Test setSocketId method
     *
     * @return void
     */
    public function testSetSocketId(): void
    {
        $this->connection->setSocketId('123.456');
        $this->assertEquals('123.456', $this->connection->getSocketId());
    }

    /**
     * Test markAsConnected method
     *
     * @return void
     */
    public function testMarkAsConnected(): void
    {
        $this->assertFalse($this->connection->isConnected());

        $this->connection->markAsConnected();
        $this->assertTrue($this->connection->isConnected());
    }

    /**
     * Test getBufferLength method
     *
     * @return void
     */
    public function testGetBufferLength(): void
    {
        $this->assertEquals(0, $this->connection->getBufferLength());

        $this->connection->appendToBuffer('test data');
        $this->assertEquals(9, $this->connection->getBufferLength());
    }

    /**
     * Test getReactConnection method
     *
     * @return void
     */
    public function testGetReactConnection(): void
    {
        $reactConnection = $this->connection->getReactConnection();
        $this->assertSame($this->mockReactConnection, $reactConnection);
    }

    /**
     * Test getEventManager method
     *
     * @return void
     */
    public function testGetEventManager(): void
    {
        $reflection = new ReflectionClass($this->connection);
        $method = $reflection->getMethod('getEventManager');
        $eventManager = $method->invoke($this->connection);
        $this->assertInstanceOf(EventManager::class, $eventManager);
    }

    /**
     * Test encodeWebSocketFrame method
     *
     * @return void
     */
    public function testEncodeWebSocketFrame(): void
    {
        $reflection = new ReflectionClass($this->connection);
        $method = $reflection->getMethod('encodeWebSocketFrame');

        $frame = $method->invoke($this->connection, 'test data');
        $this->assertIsString($frame);
        $this->assertGreaterThan(0, strlen($frame));
    }

    /**
     * Test encodeWebSocketFrame method with control frame
     *
     * @return void
     */
    public function testEncodeWebSocketFrameWithControlFrame(): void
    {
        $reflection = new ReflectionClass($this->connection);
        $method = $reflection->getMethod('encodeWebSocketFrame');

        $frame = $method->invoke($this->connection, 'close payload', 0x8);
        $this->assertIsString($frame);
        $this->assertGreaterThan(0, strlen($frame));
    }
}
