<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http;

use Crustum\BlazeCast\WebSocket\Http\PusherRouteBuilder;
use Crustum\BlazeCast\WebSocket\Pusher\Http\PusherRouteLoaderInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Test route loader implementation
 */
class TestRouteLoader implements PusherRouteLoaderInterface
{
    /**
     * @var \Symfony\Component\Routing\RouteCollection
     */
    protected RouteCollection $routes;

    /**
     * Constructor
     *
     * @param \Symfony\Component\Routing\RouteCollection $routes Route collection to use
     */
    public function __construct(?RouteCollection $routes = null)
    {
        $this->routes = $routes ?? new RouteCollection();
    }

    /**
     * @inheritDoc
     */
    public function registerRoutes(PusherRouteBuilder $builder): void
    {
        $builder->get('/test/route', TestController::class, 'test.route');
        $builder->post('/test/route/two', new TestController(), 'test.route.two');
    }

    /**
     * @inheritDoc
     */
    public function getRouteCollection(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Get controller map
     *
     * @return array<string, mixed>
     */
    public function getControllerMap(): array
    {
        return [
            'test.route' => TestController::class,
            'test.route.two' => new TestController(),
        ];
    }
}
