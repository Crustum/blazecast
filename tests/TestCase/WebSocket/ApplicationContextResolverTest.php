<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\ApplicationContextResolver;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;

/**
 * ApplicationContextResolverTest
 */
class ApplicationContextResolverTest extends TestCase
{
    /**
     * @var ApplicationContextResolver&\PHPUnit\Framework\MockObject\MockObject
     */
    protected ApplicationContextResolver $resolver;

    /**
     * @var ApplicationManager&\PHPUnit\Framework\MockObject\MockObject
     */
    protected ApplicationManager $applicationManager;

    /**
     * @var ChannelManager&\PHPUnit\Framework\MockObject\MockObject
     */
    protected ChannelManager $defaultChannelManager;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->applicationManager = $this->createStub(ApplicationManager::class);
        $this->defaultChannelManager = $this->createStub(ChannelManager::class);

        $this->resolver = new ApplicationContextResolver(
            $this->applicationManager,
            $this->defaultChannelManager,
        );
    }

    /**
     * Test getAppIdForConnection with app context
     *
     * @return void
     */
    public function testGetAppIdForConnectionWithAppContext(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getId')->willReturn('conn-123');

        $activeConnections = [
            'conn-123' => [
                'app_context' => ['app_id' => 'app-456'],
                'connection_id' => 'conn-123',
                'channels' => [],
                'last_activity' => time(),

            ],
        ];

        $result = $this->resolver->getAppIdForConnection($connection, $activeConnections);

        $this->assertEquals('app-456', $result);
    }

    /**
     * Test getAppIdForConnection with connection attribute
     *
     * @return void
     */
    public function testGetAppIdForConnectionWithAttribute(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getId')->willReturn('conn-123');
        $connection->method('getAttribute')->with('app_id')->willReturn('app-789');

        $activeConnections = [];

        $result = $this->resolver->getAppIdForConnection($connection, $activeConnections);

        $this->assertEquals('app-789', $result);
    }

    /**
     * Test getAppIdForConnection with app key
     *
     * @return void
     */
    public function testGetAppIdForConnectionWithAppKey(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getId')->willReturn('conn-123');
        $connection->expects($this->exactly(2))
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) {
                if ($attribute === 'app_id') {
                    return null;
                }
                if ($attribute === 'app_key') {
                    return 'test-key';
                }

                return null;
            });

        $application = ['id' => 'app-from-key'];
        $this->applicationManager
            ->method('getApplicationByKey')
            ->with('test-key')
            ->willReturn($application);

        $activeConnections = [];

        $result = $this->resolver->getAppIdForConnection($connection, $activeConnections);

        $this->assertEquals('app-from-key', $result);
    }

    /**
     * Test getAppIdForConnection with single application
     *
     * @return void
     */
    public function testGetAppIdForConnectionWithSingleApplication(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getId')->willReturn('conn-123');
        $connection->method('getAttribute')->willReturn(null);

        $this->applicationManager
            ->method('getApplicationByKey')
            ->willReturn(null);

        $this->applicationManager
            ->method('getApplications')
            ->willReturn(['app-single' => ['id' => 'app-single']]);

        $activeConnections = [];

        $result = $this->resolver->getAppIdForConnection($connection, $activeConnections);

        $this->assertEquals('app-single', $result);
    }

    /**
     * Test getChannelManagerForConnection with app-specific manager
     *
     * @return void
     */
    public function testGetChannelManagerForConnectionWithAppSpecificManager(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getId')->willReturn('conn-123');

        $appChannelManager = $this->createStub(ChannelManager::class);
        $application = [
            'id' => 'app-123',
            'channel_manager' => $appChannelManager,
        ];

        $activeConnections = [
            'conn-123' => [
                'app_context' => ['app_id' => 'app-123'],
                'connection_id' => 'conn-123',
                'channels' => [],
                'last_activity' => time(),
            ],
        ];

        $this->applicationManager
            ->method('getApplication')
            ->with('app-123')
            ->willReturn($application);

        $result = $this->resolver->getChannelManagerForConnection($connection, $activeConnections);

        $this->assertSame($appChannelManager, $result);
    }

    /**
     * Test getChannelManagerForConnection with default manager
     *
     * @return void
     */
    public function testGetChannelManagerForConnectionWithDefaultManager(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getId')->willReturn('conn-123');
        $connection->method('getAttribute')->willReturn(null);

        $this->applicationManager
            ->method('getApplications')
            ->willReturn([]);

        $activeConnections = [];

        $result = $this->resolver->getChannelManagerForConnection($connection, $activeConnections);

        $this->assertSame($this->defaultChannelManager, $result);
    }

    /**
     * Test getApplication
     *
     * @return void
     */
    public function testGetApplication(): void
    {
        $application = ['id' => 'app-123', 'name' => 'Test App'];

        $this->applicationManager
            ->method('getApplication')
            ->with('app-123')
            ->willReturn($application);

        $result = $this->resolver->getApplication('app-123');

        $this->assertEquals($application, $result);
    }

    /**
     * Test getApplicationByKey
     *
     * @return void
     */
    public function testGetApplicationByKey(): void
    {
        $application = ['id' => 'app-123', 'key' => 'test-key'];

        $this->applicationManager
            ->method('getApplicationByKey')
            ->with('test-key')
            ->willReturn($application);

        $result = $this->resolver->getApplicationByKey('test-key');

        $this->assertEquals($application, $result);
    }

    /**
     * Test getApplications
     *
     * @return void
     */
    public function testGetApplications(): void
    {
        $applications = [
            'app-1' => ['id' => 'app-1'],
            'app-2' => ['id' => 'app-2'],
        ];

        $this->applicationManager
            ->method('getApplications')
            ->willReturn($applications);

        $result = $this->resolver->getApplications();

        $this->assertEquals($applications, $result);
    }

    /**
     * Test getApplicationManager
     *
     * @return void
     */
    public function testGetApplicationManager(): void
    {
        $result = $this->resolver->getApplicationManager();

        $this->assertSame($this->applicationManager, $result);
    }
}
