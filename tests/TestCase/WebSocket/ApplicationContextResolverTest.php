<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\ApplicationContextResolver;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

/**
 * ApplicationContextResolverTest
 */
class ApplicationContextResolverTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @var \Crustum\BlazeCast\WebSocket\ApplicationContextResolver
     */
    protected ApplicationContextResolver $resolver;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager&\JMac\Testing\DoubleInterface
     */
    protected ApplicationManager $applicationManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\JMac\Testing\DoubleInterface
     */
    protected ChannelManager $defaultChannelManager;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationManager = Double::for(ApplicationManager::class);
        $this->defaultChannelManager = Double::for(ChannelManager::class);

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
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('conn-123');

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
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('conn-123');
        $connection->expects('getAttribute')
            ->with('app_id')
            ->returns('app-789');

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
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('conn-123');
        $connection->expects('getAttribute')
            ->times(2)
            ->resolves(function (mixed ...$args): mixed {
                $attribute = $args[0] ?? null;
                if ($attribute === 'app_id') {
                    return null;
                }

                if ($attribute === 'app_key') {
                    return 'test-key';
                }

                return null;
            });

        $application = ['id' => 'app-from-key'];
        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplicationByKey')
            ->with('test-key')
            ->returns($application);

        $resolver = new ApplicationContextResolver(
            $applicationManager,
            $this->defaultChannelManager,
        );

        $activeConnections = [];

        $result = $resolver->getAppIdForConnection($connection, $activeConnections);

        $this->assertEquals('app-from-key', $result);
    }

    /**
     * Test getAppIdForConnection with single application
     *
     * @return void
     */
    public function testGetAppIdForConnectionWithSingleApplication(): void
    {
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('conn-123');
        $connection->allows('getAttribute')->returns(null);

        $this->applicationManager
            ->allows('getApplicationByKey')
            ->returns(null);

        $this->applicationManager
            ->allows('getApplications')
            ->returns(['app-single' => ['id' => 'app-single']]);

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
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('conn-123');

        $appChannelManager = Double::for(ChannelManager::class);
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

        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplication')
            ->with('app-123')
            ->returns($application);

        $resolver = new ApplicationContextResolver(
            $applicationManager,
            $this->defaultChannelManager,
        );

        $result = $resolver->getChannelManagerForConnection($connection, $activeConnections);

        $this->assertSame($appChannelManager, $result);
    }

    /**
     * Test getChannelManagerForConnection with default manager
     *
     * @return void
     */
    public function testGetChannelManagerForConnectionWithDefaultManager(): void
    {
        $connection = Double::for(Connection::class);
        $connection->allows('getId')->returns('conn-123');
        $connection->allows('getAttribute')->returns(null);

        $this->applicationManager
            ->allows('getApplications')
            ->returns([]);

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

        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplication')
            ->with('app-123')
            ->returns($application);

        $resolver = new ApplicationContextResolver(
            $applicationManager,
            $this->defaultChannelManager,
        );

        $result = $resolver->getApplication('app-123');

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

        $applicationManager = Double::for(ApplicationManager::class);
        $applicationManager->expects('getApplicationByKey')
            ->with('test-key')
            ->returns($application);

        $resolver = new ApplicationContextResolver(
            $applicationManager,
            $this->defaultChannelManager,
        );

        $result = $resolver->getApplicationByKey('test-key');

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
            ->allows('getApplications')
            ->returns($applications);

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
