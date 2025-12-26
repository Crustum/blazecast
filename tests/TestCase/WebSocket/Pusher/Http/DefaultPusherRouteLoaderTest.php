<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Http\PusherRouteBuilder;
use Crustum\BlazeCast\WebSocket\Pusher\Http\DefaultPusherRouteLoader;
use Symfony\Component\Routing\RouteCollection;

class DefaultPusherRouteLoaderTest extends TestCase
{
    /**
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Http\DefaultPusherRouteLoader
     */
    protected $loader;

    /**
     * @var \Symfony\Component\Routing\RouteCollection
     */
    protected $routes;

    /**
     * @var \Crustum\BlazeCast\WebSocket\Http\PusherRouteBuilder
     */
    protected $builder;

    /**
     * Setup the test case
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->routes = new RouteCollection();
        $this->builder = new PusherRouteBuilder($this->routes);
        $this->loader = new DefaultPusherRouteLoader($this->routes);
    }

    /**
     * Test that constructor properly sets the route collection
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $routes = new RouteCollection();
        $loader = new DefaultPusherRouteLoader($routes);
        $this->assertSame($routes, $loader->getRouteCollection());

        $loader = new DefaultPusherRouteLoader();
        $this->assertInstanceOf(RouteCollection::class, $loader->getRouteCollection());
    }

    /**
     * Test that registerRoutes adds the expected routes
     *
     * @return void
     */
    public function testRegisterRoutes(): void
    {
        $this->loader->registerRoutes($this->builder);

        $routes = $this->loader->getRouteCollection();

        $this->assertNotNull($routes->get('pusher.health'));
        $this->assertNotNull($routes->get('pusher.events.trigger'));
        $this->assertNotNull($routes->get('pusher.channels.index'));
        $this->assertNotNull($routes->get('pusher.channels.show'));
        $this->assertNotNull($routes->get('pusher.channels.users'));
        $this->assertNotNull($routes->get('pusher.auth'));

        $this->assertEquals('/pusher/health', $routes->get('pusher.health')->getPath());
        $this->assertEquals('/apps/{appId}/events', $routes->get('pusher.events.trigger')->getPath());
        $this->assertEquals('/apps/{appId}/channels', $routes->get('pusher.channels.index')->getPath());
    }
}
