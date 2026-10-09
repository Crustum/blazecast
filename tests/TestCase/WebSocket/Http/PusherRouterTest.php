<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Http;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\PusherRouter;
use Crustum\BlazeCast\WebSocket\Http\Response;
use GuzzleHttp\Psr7\Request;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use ReflectionClass;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * PusherRouterTest
 */
class PusherRouterTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * URL matcher
     *
     * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface&\JMac\Testing\DoubleInterface
     */
    protected $matcher;

    /**
     * Route collection
     *
     * @var \Symfony\Component\Routing\RouteCollection
     */
    protected $routes;

    /**
     * Connection
     *
     * @var \Crustum\BlazeCast\WebSocket\Connection
     */
    protected $connection;

    /**
     * Router
     *
     * @var \Crustum\BlazeCast\WebSocket\Http\PusherRouter
     */
    protected $router;

    /**
     * Setup test
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->routes = new RouteCollection();

        $this->matcher = Double::for(UrlMatcherInterface::class);

        $this->connection = Double::for(Connection::class);

        $this->router = new PusherRouter($this->routes);

        $reflection = new ReflectionClass($this->router);
        $matcherProperty = $reflection->getProperty('matcher');
        $matcherProperty->setValue($this->router, $this->matcher);
    }

    /**
     * Test dispatch with valid route
     *
     * @return void
     */
    public function testDispatchWithValidRoute(): void
    {
        $request = new Request('GET', '/pusher/health');
        $response = new Response(['health' => 'OK']);
        $connection = Double::for(Connection::class);

        $controller = function ($req, $conn, $params) use ($request, $response, $connection): Response {
            $this->assertSame($request, $req);
            $this->assertSame($connection, $conn);
            $this->assertEquals([], $params);

            return $response;
        };

        $this->matcher->expects('match')
            ->with('/pusher/health')
            ->returns([
                '_controller' => $controller,
                '_route' => 'health_check',
            ]);

        $result = $this->router->dispatch($request, $connection);
        $this->assertSame($response, $result);
    }

    /**
     * Test dispatch with route parameters
     *
     * @return void
     */
    public function testDispatchWithRouteParameters(): void
    {
        $request = new Request('GET', '/apps/app-id/channels');
        $response = new Response(['channels' => []]);
        $connection = Double::for(Connection::class);

        $controller = function ($req, $conn, $params) use ($request, $response, $connection): Response {
            $this->assertSame($request, $req);
            $this->assertSame($connection, $conn);
            $this->assertEquals(['appId' => 'app-id'], $params);

            return $response;
        };

        $this->matcher->expects('match')
            ->with('/apps/app-id/channels')
            ->returns([
                '_controller' => $controller,
                '_route' => 'channels',
                'appId' => 'app-id',
            ]);

        $result = $this->router->dispatch($request, $connection);
        $this->assertSame($response, $result);
    }

    /**
     * Test dispatch with not found route
     *
     * @return void
     */
    public function testDispatchWithNotFoundRoute(): void
    {
        $request = new Request('GET', '/not-found');

        $this->matcher->expects('match')
            ->with('/not-found')
            ->throws(new ResourceNotFoundException());

        $connection = Double::for(Connection::class);
        $connection->expects('close')->never();

        $result = $this->router->dispatch($request, $connection);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(404, $result->getStatusCode());
        $this->assertArrayHasKey('error', $result->getBody());
    }

    /**
     * Test dispatch with method not allowed
     *
     * @return void
     */
    public function testDispatchWithMethodNotAllowed(): void
    {
        $request = new Request('POST', '/pusher/health');

        $exception = new MethodNotAllowedException(['GET', 'OPTIONS']);

        $this->matcher->expects('match')
            ->with('/pusher/health')
            ->throws($exception);

        $connection = Double::for(Connection::class);
        $connection->expects('close')->never();

        $result = $this->router->dispatch($request, $connection);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(405, $result->getStatusCode());
        $this->assertArrayHasKey('error', $result->getBody());
    }

    /**
     * Test dispatch with invalid controller
     *
     * @return void
     */
    public function testDispatchWithInvalidController(): void
    {
        $request = new Request('GET', '/pusher/health');

        $this->matcher->expects('match')
            ->with('/pusher/health')
            ->returns([
                '_route' => 'health_check',
            ]);

        $connection = Double::for(Connection::class);
        $connection->expects('close')->never();

        $result = $this->router->dispatch($request, $connection);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(500, $result->getStatusCode());
        $this->assertArrayHasKey('error', $result->getBody());
    }
}
