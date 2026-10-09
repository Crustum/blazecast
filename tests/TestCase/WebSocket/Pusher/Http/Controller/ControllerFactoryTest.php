<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ControllerFactory;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Exception;
use InvalidArgumentException;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use Psr\Container\ContainerInterface;
use TypeError;

/**
 * ControllerFactory Test Case
 */
class ControllerFactoryTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * Test subject
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ControllerFactory
     */
    protected ControllerFactory $controllerFactory;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    protected ApplicationManager $applicationManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager
     */
    protected ChannelManager $channelManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager
     */
    protected ChannelConnectionManager $connectionManager;

    /**
     * @var \Psr\Container\ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var \Cake\Event\EventManager
     */
    protected EventManager $eventManager;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationManager = Double::for(ApplicationManager::class);
        $this->channelManager = Double::for(ChannelManager::class);
        $this->connectionManager = Double::for(ChannelConnectionManager::class);
        $this->container = Double::for(ContainerInterface::class);

        $this->eventManager = Double::for(EventManager::class, override: true)->instance();

        $this->controllerFactory = new ControllerFactory(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
            $this->eventManager,
            $this->container,
        );
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->controllerFactory);
        unset($this->applicationManager);
        unset($this->channelManager);
        unset($this->connectionManager);
        unset($this->container);

        parent::tearDown();
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $factory = new ControllerFactory(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
        );

        $this->assertInstanceOf(ControllerFactory::class, $factory);
        $this->assertSame($this->applicationManager, $factory->getApplicationManager());
        $this->assertSame($this->channelManager, $factory->getChannelManager());
        $this->assertSame($this->connectionManager, $factory->getConnectionManager());
    }

    /**
     * Test constructor with event manager
     *
     * @return void
     */
    public function testConstructorWithEventManager(): void
    {
        $factory = new ControllerFactory(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
            $this->eventManager,
        );

        $this->assertInstanceOf(ControllerFactory::class, $factory);
    }

    /**
     * Test create with container
     *
     * @return void
     */
    public function testCreateWithContainer(): void
    {
        $mockController = Double::for(PusherControllerInterface::class);

        $container = Double::for(ContainerInterface::class);
        $container->expects('has')
            ->with(FactoryTestController::class)
            ->returns(true);

        $container->expects('get')
            ->with(FactoryTestController::class)
            ->returns($mockController);

        $factory = new ControllerFactory(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
            $this->eventManager,
            $container,
        );
        $result = $factory->create(FactoryTestController::class);

        $this->assertSame($mockController, $result);
    }

    /**
     * Test create with no-arg constructor
     *
     * @return void
     */
    public function testCreateWithNoArgConstructor(): void
    {
        $container = Double::for(ContainerInterface::class);
        $container->expects('has')
            ->with(FactoryTestController::class)
            ->returns(false);

        $factory = new ControllerFactory(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
            $this->eventManager,
            $container,
        );
        $result = $factory->create(FactoryTestController::class);

        $this->assertInstanceOf(FactoryTestController::class, $result);
    }

    /**
     * Test create with constructor parameters
     *
     * @return void
     */
    public function testCreateWithConstructorParameters(): void
    {
        $container = Double::for(ContainerInterface::class);
        $container->expects('has')
            ->with(FactoryTestControllerWithParams::class)
            ->returns(false);

        $factory = new ControllerFactory(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
            $this->eventManager,
            $container,
        );
        $result = $factory->create(FactoryTestControllerWithParams::class);

        $this->assertInstanceOf(FactoryTestControllerWithParams::class, $result);
    }

    /**
     * Test create with cached instance
     *
     * @return void
     */
    public function testCreateWithCachedInstance(): void
    {
        $mockController = Double::for(PusherControllerInterface::class);

        $container = Double::for(ContainerInterface::class);
        $container->expects('has')
            ->with(FactoryTestController::class)
            ->returns(true);

        $container->expects('get')
            ->with(FactoryTestController::class)
            ->returns($mockController);

        $factory = new ControllerFactory(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
            $this->eventManager,
            $container,
        );
        // First call should create the instance
        $result1 = $factory->create(FactoryTestController::class);
        $this->assertSame($mockController, $result1);

        // Second call should return cached instance
        $result2 = $factory->create(FactoryTestController::class);
        $this->assertSame($mockController, $result2);
    }

    /**
     * Test create with invalid class
     *
     * @return void
     */
    public function testCreateWithInvalidClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Controller class not found: NonExistentController');

        $this->controllerFactory->create('NonExistentController');
    }

    /**
     * Test create with non-interface implementation
     *
     * @return void
     */
    public function testCreateWithNonInterfaceImplementation(): void
    {
        $fqcn = FactoryTestNonInterfaceController::class;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Controller {$fqcn} must implement PusherControllerInterface");

        $this->controllerFactory->create($fqcn);
    }

    /**
     * Test create with unresolvable parameters
     *
     * @return void
     */
    public function testCreateWithUnresolvableParameters(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($unresolvableParam) must be of type string');

        $this->controllerFactory->create(FactoryTestControllerUnresolvable::class);
    }

    /**
     * Test get method
     *
     * @return void
     */
    public function testGet(): void
    {
        $mockController = Double::for(PusherControllerInterface::class);

        $container = Double::for(ContainerInterface::class);
        $container->expects('has')
            ->with(FactoryTestController::class)
            ->returns(true);

        $container->expects('get')
            ->with(FactoryTestController::class)
            ->returns($mockController);

        $factory = new ControllerFactory(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
            $this->eventManager,
            $container,
        );
        $result = $factory->get(FactoryTestController::class);

        $this->assertSame($mockController, $result);
    }

    /**
     * Test register method
     *
     * @return void
     */
    public function testRegister(): void
    {
        $mockController = Double::for(PusherControllerInterface::class);

        $this->controllerFactory->register(FactoryTestController::class, $mockController);

        $result = $this->controllerFactory->create(FactoryTestController::class);
        $this->assertSame($mockController, $result);
    }

    /**
     * Test clear method
     *
     * @return void
     */
    public function testClear(): void
    {
        $mockController = Double::for(PusherControllerInterface::class);

        // Register a controller
        $this->controllerFactory->register(FactoryTestController::class, $mockController);

        // Verify it's cached
        $result1 = $this->controllerFactory->create(FactoryTestController::class);
        $this->assertSame($mockController, $result1);

        // Clear cache
        $this->controllerFactory->clear();

        // Should create new instance
        $container = Double::for(ContainerInterface::class);
        $container->expects('has')
            ->with(FactoryTestController::class)
            ->returns(false);

        $factory = new ControllerFactory(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
            $this->eventManager,
            $container,
        );
        $result2 = $factory->create(FactoryTestController::class);
        $this->assertInstanceOf(FactoryTestController::class, $result2);
    }

    /**
     * Test getApplicationManager
     *
     * @return void
     */
    public function testGetApplicationManager(): void
    {
        $result = $this->controllerFactory->getApplicationManager();
        $this->assertSame($this->applicationManager, $result);
    }

    /**
     * Test getChannelManager
     *
     * @return void
     */
    public function testGetChannelManager(): void
    {
        $result = $this->controllerFactory->getChannelManager();
        $this->assertSame($this->channelManager, $result);
    }

    /**
     * Test getConnectionManager
     *
     * @return void
     */
    public function testGetConnectionManager(): void
    {
        $result = $this->controllerFactory->getConnectionManager();
        $this->assertSame($this->connectionManager, $result);
    }

    /**
     * Test create with exception handling
     *
     * @return void
     */
    public function testCreateWithExceptionHandling(): void
    {
        $container = Double::for(ContainerInterface::class);
        $container->expects('has')
            ->with(FactoryTestController::class)
            ->returns(true);

        $container->expects('get')
            ->with(FactoryTestController::class)
            ->throws(new Exception('Container error'));

        $factory = new ControllerFactory(
            $this->applicationManager,
            $this->channelManager,
            $this->connectionManager,
            $this->eventManager,
            $container,
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Container error');

        $factory->create(FactoryTestController::class);
    }
}
