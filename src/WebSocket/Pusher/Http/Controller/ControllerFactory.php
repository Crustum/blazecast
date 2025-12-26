<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Cake\Event\EventManager;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface;
use Exception;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

/**
 * ControllerFactory
 *
 * Factory for creating controller instances
 */
class ControllerFactory
{
    /**
     * Application manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    protected ApplicationManager $applicationManager;

    /**
     * Channel manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager
     */
    protected ChannelManager $channelManager;

    /**
     * Channel connection manager
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager
     */
    protected ChannelConnectionManager $connectionManager;

    /**
     * Event manager
     *
     * @var \Cake\Event\EventManager
     */
    protected EventManager $eventManager;

    /**
     * Container for dependency injection
     *
     * @var \Psr\Container\ContainerInterface|null
     */
    protected ?ContainerInterface $container = null;

    /**
     * Rate limiter
     *
     * @var \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|null
     */
    protected ?RateLimiterInterface $rateLimiter = null;

    /**
     * Controller instances cache
     *
     * @var array<string, \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface>
     */
    protected array $instances = [];

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager $channelManager Channel manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager $connectionManager Connection manager
     * @param \Cake\Event\EventManager|null $eventManager Event manager
     * @param \Psr\Container\ContainerInterface|null $container Container for dependency injection
     * @param \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|null $rateLimiter Rate limiter
     */
    public function __construct(
        ApplicationManager $applicationManager,
        ChannelManager $channelManager,
        ChannelConnectionManager $connectionManager,
        ?EventManager $eventManager = null,
        ?ContainerInterface $container = null,
        ?RateLimiterInterface $rateLimiter = null,
    ) {
        $this->applicationManager = $applicationManager;
        $this->channelManager = $channelManager;
        $this->connectionManager = $connectionManager;
        $this->eventManager = $eventManager ?: EventManager::instance();
        $this->container = $container;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Create a controller instance
     *
     * @param string $controllerClass Controller class name
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
     * @throws \InvalidArgumentException If controller class is invalid
     */
    public function create(string $controllerClass): PusherControllerInterface
    {
        if (isset($this->instances[$controllerClass])) {
            return $this->instances[$controllerClass];
        }

        if (!class_exists($controllerClass)) {
            throw new InvalidArgumentException("Controller class not found: {$controllerClass}");
        }

        try {
            if ($this->container !== null && $this->container->has($controllerClass)) {
                $controller = $this->container->get($controllerClass);
            } else {
                $controller = new $controllerClass(
                    $this->applicationManager,
                    $this->channelManager,
                    $this->connectionManager,
                    $this->eventManager,
                    $this->rateLimiter,
                );
            }

            if (!$controller instanceof PusherControllerInterface) {
                throw new InvalidArgumentException(
                    "Controller {$controllerClass} must implement PusherControllerInterface",
                );
            }

            $this->instances[$controllerClass] = $controller;

            return $controller;
        } catch (Exception $e) {
            $errorMsg = sprintf(
                'Error creating controller %s - %s in %s:%d - Trace: %s',
                $controllerClass,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString(),
            );
            BlazeCastLogger::error($errorMsg, [
                'scope' => ['socket.controller', 'socket.controller.factory'],
            ]);
            throw $e;
        }
    }

    /**
     * Get or create a controller instance
     *
     * @param string $controllerClass Controller class name
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
     */
    public function get(string $controllerClass): PusherControllerInterface
    {
        return $this->create($controllerClass);
    }

    /**
     * Register a controller instance
     *
     * @param string $controllerClass Controller class name
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface $controller Controller instance
     * @return void
     */
    public function register(string $controllerClass, PusherControllerInterface $controller): void
    {
        $this->instances[$controllerClass] = $controller;
    }

    /**
     * Clear controller instances cache
     *
     * @return void
     */
    public function clear(): void
    {
        $this->instances = [];
    }

    /**
     * Get the application manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    public function getApplicationManager(): ApplicationManager
    {
        return $this->applicationManager;
    }

    /**
     * Get the channel manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager
     */
    public function getChannelManager(): ChannelManager
    {
        return $this->channelManager;
    }

    /**
     * Get the connection manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager
     */
    public function getConnectionManager(): ChannelConnectionManager
    {
        return $this->connectionManager;
    }

    /**
     * Get the rate limiter
     *
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|null
     */
    public function getRateLimiter(): ?RateLimiterInterface
    {
        return $this->rateLimiter;
    }
}
