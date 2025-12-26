<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Http\PusherRouteBuilder;
use Symfony\Component\Routing\RouteCollection;

class PusherRouteLoaderInterfaceTest extends TestCase
{
    /**
     * Test that a route loader can register routes
     *
     * @return void
     */
    public function testRouteLoaderCanRegisterRoutes(): void
    {
        $collection = new RouteCollection();
        $builder = new PusherRouteBuilder($collection);
        $loader = new TestRouteLoader($collection);

        $loader->registerRoutes($builder);
        $routes = $loader->getRouteCollection();

        $this->assertCount(2, $routes);
        $this->assertTrue($routes->get('test.route') !== null);
        $this->assertTrue($routes->get('test.route.two') !== null);
    }
}
