<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket;

use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\ConnectionRegistry;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

/**
 * ConnectionRegistryTest
 */
class ConnectionRegistryTest extends TestCase
{
    use VerifiesDoubles;

    protected ConnectionRegistry $registry;

    /**
     * @var ChannelConnectionManager
     */
    protected ChannelConnectionManager $connectionManager;

    /**
     * @var EventManager
     */
    protected EventManager $eventManager;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionManager = Double::for(ChannelConnectionManager::class);
        // Double::for() cannot double EventManager (its `instance` method
        // collides with Double internals); the stub configured no methods,
        // so a real instance is behaviorally identical here.
        $this->eventManager = new EventManager();

        $this->registry = new ConnectionRegistry($this->connectionManager, $this->eventManager);
    }

    /**
     * Test register connection
     *
     * @return void
     */
    public function testRegisterConnection(): void
    {
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('conn-123');

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
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('conn-123');

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
        $connection1 = Double::for(Connection::class);
        $connection1->allows('getId')->returns('conn-1');

        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('conn-2');

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

        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('conn-123');

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
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('conn-123');

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
        $connection1 = Double::for(Connection::class);
        $connection1->allows('getId')->returns('conn-1');
        $connection1->allows('isConnected')->returns(true);

        $connection2 = Double::for(Connection::class);
        $connection2->allows('getId')->returns('conn-2');
        $connection2->allows('isConnected')->returns(true);

        $this->registry->register($connection1);
        $this->registry->register($connection2);

        $this->assertEquals(2, $this->registry->getConnectionCount());

        $this->registry->clear();

        $this->assertEquals(0, $this->registry->getConnectionCount());
    }
}
