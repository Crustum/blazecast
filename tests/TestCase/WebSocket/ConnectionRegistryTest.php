<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket;

use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\ConnectionRegistry;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;

/**
 * ConnectionRegistryTest
 */
class ConnectionRegistryTest extends TestCase
{
    protected ConnectionRegistry $registry;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&ChannelConnectionManager
     */
    protected ChannelConnectionManager $connectionManager;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&EventManager
     */
    protected EventManager $eventManager;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->connectionManager = $this->createStub(ChannelConnectionManager::class);
        $this->eventManager = $this->createStub(EventManager::class);

        $this->registry = new ConnectionRegistry($this->connectionManager, $this->eventManager);
    }

    /**
     * Test register connection
     *
     * @return void
     */
    public function testRegisterConnection(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getId')->willReturn('conn-123');

        $this->registry->register($connection, ['app_id' => 'data']);

        $retrievedConnection = $this->registry->getConnection('conn-123');
        $this->assertSame($connection, $retrievedConnection);

        $connectionInfo = $this->registry->getConnectionInfo('conn-123');
        $this->assertEquals('data', $connectionInfo['app_id'] ?? null);
        $this->assertArrayHasKey('registered_at', $connectionInfo);
    }

    /**
     * Test update connection ID
     *
     * @return void
     */
    public function testUpdateConnectionId(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getId')->willReturn('conn-123');

        $this->registry->register($connection);

        $this->registry->updateConnectionId('conn-123', 'conn-456');

        $this->assertNull($this->registry->getConnection('conn-123'));
        $this->assertSame($connection, $this->registry->getConnection('conn-456'));
    }

    /**
     * Test get connections
     *
     * @return void
     */
    public function testGetConnections(): void
    {
        $connection1 = $this->createStub(Connection::class);
        $connection1->method('getId')->willReturn('conn-1');

        $connection2 = $this->createStub(Connection::class);
        $connection2->method('getId')->willReturn('conn-2');

        $this->registry->register($connection1);
        $this->registry->register($connection2);

        $connections = $this->registry->getConnections();

        $this->assertCount(2, $connections);
        $this->assertSame($connection1, $connections['conn-1']);
        $this->assertSame($connection2, $connections['conn-2']);
    }

    /**
     * Test connection count
     *
     * @return void
     */
    public function testGetConnectionCount(): void
    {
        $this->assertEquals(0, $this->registry->getConnectionCount());

        $connection = $this->createStub(Connection::class);
        $connection->method('getId')->willReturn('conn-123');

        $this->registry->register($connection);

        $this->assertEquals(1, $this->registry->getConnectionCount());
    }

    /**
     * Test unregister connection
     *
     * @return void
     */
    public function testUnregisterConnection(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getId')->willReturn('conn-123');

        $this->registry->register($connection);
        $this->assertEquals(1, $this->registry->getConnectionCount());

        $this->registry->unregister('conn-123');

        $this->assertEquals(0, $this->registry->getConnectionCount());
        $this->assertNull($this->registry->getConnection('conn-123'));
    }

    /**
     * Test clear all connections
     *
     * @return void
     */
    public function testClearConnections(): void
    {
        $connection1 = $this->createStub(Connection::class);
        $connection1->method('getId')->willReturn('conn-1');
        $connection1->method('isConnected')->willReturn(true);

        $connection2 = $this->createStub(Connection::class);
        $connection2->method('getId')->willReturn('conn-2');
        $connection2->method('isConnected')->willReturn(true);

        $this->registry->register($connection1);
        $this->registry->register($connection2);

        $this->assertEquals(2, $this->registry->getConnectionCount());

        $this->registry->clear();

        $this->assertEquals(0, $this->registry->getConnectionCount());
    }
}
