<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Http;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\PusherRouter;
use Crustum\BlazeCast\WebSocket\Http\Response;
use GuzzleHttp\Psr7\Request;
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
    /**
     * URL matcher
     *
     * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface|\PHPUnit\Framework\MockObject\MockObject
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
     * @var \Crustum\BlazeCast\WebSocket\Connection|\PHPUnit\Framework\MockObject\MockObject
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
    public function setUp(): void
    {
        parent::setUp();

        $this->routes = new RouteCollection();

        $this->matcher = $this->getMockBuilder(UrlMatcherInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

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
        $connection = $this->createStub(Connection::class);

        $controller = function ($req, $conn, $params) use ($request, $response, $connection) {
            $this->assertSame($request, $req);
            $this->assertSame($connection, $conn);
            $this->assertEquals([], $params);

            return $response;
        };

        $this->matcher->expects($this->once())
            ->method('match')
            ->with('/pusher/health')
            ->willReturn([
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
        $connection = $this->createStub(Connection::class);

        $controller = function ($req, $conn, $params) use ($request, $response, $connection) {
            $this->assertSame($request, $req);
            $this->assertSame($connection, $conn);
            $this->assertEquals(['appId' => 'app-id'], $params);

            return $response;
        };

        $this->matcher->expects($this->once())
            ->method('match')
            ->with('/apps/app-id/channels')
            ->willReturn([
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

        $this->matcher->expects($this->once())
            ->method('match')
            ->with('/not-found')
            ->willThrowException(new ResourceNotFoundException());

        $this->connection->expects($this->never())
            ->method('close');

        $result = $this->router->dispatch($request, $this->connection);
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

        $this->matcher->expects($this->once())
            ->method('match')
            ->with('/pusher/health')
            ->willThrowException($exception);

        $this->connection->expects($this->never())
            ->method('close');

        $result = $this->router->dispatch($request, $this->connection);
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

        $this->matcher->expects($this->once())
            ->method('match')
            ->with('/pusher/health')
            ->willReturn([
                '_route' => 'health_check',
            ]);

        $this->connection->expects($this->never())
            ->method('close');

        $result = $this->router->dispatch($request, $this->connection);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(500, $result->getStatusCode());
        $this->assertArrayHasKey('error', $result->getBody());
    }
}
