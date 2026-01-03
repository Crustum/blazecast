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
use Psr\Container\ContainerInterface;
use TypeError;

/**
 * ControllerFactory Test Case
 */
class ControllerFactoryTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\ControllerFactory
     */
    protected ControllerFactory $controllerFactory;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager&\PHPUnit\Framework\MockObject\MockObject
     */
    protected $applicationManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager&\PHPUnit\Framework\MockObject\MockObject
     */
    protected $channelManager;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager&\PHPUnit\Framework\MockObject\MockObject
     */
    protected $connectionManager;

    /**
     * @var \Psr\Container\ContainerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    protected $container;

    /**
     * @var \Cake\Event\EventManager&\PHPUnit\Framework\MockObject\MockObject
     */
    protected $eventManager;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationManager = $this->createStub(ApplicationManager::class);
        $this->channelManager = $this->createStub(ChannelManager::class);
        $this->connectionManager = $this->createStub(ChannelConnectionManager::class);
        $this->container = $this->createMock(ContainerInterface::class);

        $this->eventManager = $this->createStub(EventManager::class);

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
        $mockController = $this->createMock(PusherControllerInterface::class);

        $this->container->expects($this->once())
            ->method('has')
            ->with(FactoryTestController::class)
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with(FactoryTestController::class)
            ->willReturn($mockController);

        $result = $this->controllerFactory->create(FactoryTestController::class);

        $this->assertSame($mockController, $result);
    }

    /**
     * Test create with no-arg constructor
     *
     * @return void
     */
    public function testCreateWithNoArgConstructor(): void
    {
        $this->container->expects($this->once())
            ->method('has')
            ->with(FactoryTestController::class)
            ->willReturn(false);

        $result = $this->controllerFactory->create(FactoryTestController::class);

        $this->assertInstanceOf(FactoryTestController::class, $result);
    }

    /**
     * Test create with constructor parameters
     *
     * @return void
     */
    public function testCreateWithConstructorParameters(): void
    {
        $this->container->expects($this->once())
            ->method('has')
            ->with(FactoryTestControllerWithParams::class)
            ->willReturn(false);

        $result = $this->controllerFactory->create(FactoryTestControllerWithParams::class);

        $this->assertInstanceOf(FactoryTestControllerWithParams::class, $result);
    }

    /**
     * Test create with cached instance
     *
     * @return void
     */
    public function testCreateWithCachedInstance(): void
    {
        $mockController = $this->createMock(PusherControllerInterface::class);

        $this->container->expects($this->once())
            ->method('has')
            ->with(FactoryTestController::class)
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with(FactoryTestController::class)
            ->willReturn($mockController);

        // First call should create the instance
        $result1 = $this->controllerFactory->create(FactoryTestController::class);
        $this->assertSame($mockController, $result1);

        // Second call should return cached instance
        $result2 = $this->controllerFactory->create(FactoryTestController::class);
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
        $mockController = $this->createMock(PusherControllerInterface::class);

        $this->container->expects($this->once())
            ->method('has')
            ->with(FactoryTestController::class)
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with(FactoryTestController::class)
            ->willReturn($mockController);

        $result = $this->controllerFactory->get(FactoryTestController::class);

        $this->assertSame($mockController, $result);
    }

    /**
     * Test register method
     *
     * @return void
     */
    public function testRegister(): void
    {
        $mockController = $this->createMock(PusherControllerInterface::class);

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
        $mockController = $this->createMock(PusherControllerInterface::class);

        // Register a controller
        $this->controllerFactory->register(FactoryTestController::class, $mockController);

        // Verify it's cached
        $result1 = $this->controllerFactory->create(FactoryTestController::class);
        $this->assertSame($mockController, $result1);

        // Clear cache
        $this->controllerFactory->clear();

        // Should create new instance
        $this->container->expects($this->once())
            ->method('has')
            ->with(FactoryTestController::class)
            ->willReturn(false);

        $result2 = $this->controllerFactory->create(FactoryTestController::class);
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
        $this->container->expects($this->once())
            ->method('has')
            ->with(FactoryTestController::class)
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with(FactoryTestController::class)
            ->willThrowException(new Exception('Container error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Container error');

        $this->controllerFactory->create(FactoryTestController::class);
    }
}
