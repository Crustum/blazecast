<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket\Http;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\HttpRequestProcessor;
use Crustum\BlazeCast\WebSocket\Http\PusherRouter;
use Crustum\BlazeCast\WebSocket\Http\Response;
use GuzzleHttp\Psr7\ServerRequest;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use ReflectionClass;

/**
 * HttpRequestProcessorTest
 */
class HttpRequestProcessorTest extends TestCase
{
    use VerifiesDoubles;

    protected HttpRequestProcessor $processor;

    protected PusherRouter $router;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Double::for(PusherRouter::class);
        $this->processor = new HttpRequestProcessor($this->router, 1000);
    }

    /**
     * Test isCompleteHttpRequest with complete request
     *
     * @return void
     */
    public function testIsCompleteHttpRequestWithCompleteRequest(): void
    {
        $buffer = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";

        $result = $this->processor->isCompleteHttpRequest($buffer);

        $this->assertTrue($result);
    }

    /**
     * Test isCompleteHttpRequest with incomplete request
     *
     * @return void
     */
    public function testIsCompleteHttpRequestWithIncompleteRequest(): void
    {
        $buffer = "GET / HTTP/1.1\r\nHost: localhost\r\n";

        $result = $this->processor->isCompleteHttpRequest($buffer);

        $this->assertFalse($result);
    }

    /**
     * Test exceedsMaxSize
     *
     * @return void
     */
    public function testExceedsMaxSize(): void
    {
        $this->assertFalse($this->processor->exceedsMaxSize(500));
        $this->assertFalse($this->processor->exceedsMaxSize(1000));
        $this->assertTrue($this->processor->exceedsMaxSize(1001));
    }

    /**
     * Test parseHttpRequest
     *
     * @return void
     */
    public function testParseHttpRequest(): void
    {
        $buffer = "GET /test HTTP/1.1\r\nHost: localhost\r\nContent-Length: 0\r\n\r\n";

        $request = $this->processor->parseHttpRequest($buffer);

        $this->assertInstanceOf(ServerRequest::class, $request);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/test', $request->getUri()->getPath());
        $this->assertEquals('localhost', $request->getHeaderLine('Host'));
    }

    /**
     * Test handleHttpRequest with OPTIONS request
     *
     * @return void
     */
    public function testHandleHttpRequestWithOptionsRequest(): void
    {
        $request = new ServerRequest('OPTIONS', '/test');
        $connection = Double::for(Connection::class);

        $connection->expects('send')
            ->with(Argument::satisfies(fn($response): bool => str_contains((string)$response, 'HTTP/1.1 200 OK') &&
                   str_contains((string)$response, 'Access-Control-Allow-Origin: *')));

        $connection->expects('close');

        $this->processor->handleHttpRequest($request, $connection);
    }

    /**
     * Test handleHttpRequest with regular request
     *
     * @return void
     */
    public function testHandleHttpRequestWithRegularRequest(): void
    {
        $request = new ServerRequest('GET', '/test');
        $connection = Double::for(Connection::class);
        $routerResponse = new Response('test content', 200);

        $router = Double::for(PusherRouter::class);
        $router
            ->expects('dispatch')
            ->with($request, $connection)
            ->returns($routerResponse);

        $this->processor->setRouter($router);

        $connection->expects('send')
            ->with(Argument::satisfies(fn($response): bool => str_contains((string)$response, 'HTTP/1.1 200 OK') &&
                   str_contains((string)$response, 'Access-Control-Allow-Origin: *') &&
                   str_contains((string)$response, 'test content')));

        $connection->expects('close');

        $this->processor->handleHttpRequest($request, $connection);
    }

    /**
     * Test processHttpRequest with OPTIONS request
     *
     * @return void
     */
    public function testProcessHttpRequestWithOptionsRequest(): void
    {
        $request = new ServerRequest('OPTIONS', '/test');
        $connection = Double::for(Connection::class);

        $response = $this->processor->processHttpRequest($request, $connection);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertEquals('*', $headers['Access-Control-Allow-Origin']);
        $this->assertEquals('0', $headers['Content-Length']);
    }

    /**
     * Test processHttpRequest with regular request
     *
     * @return void
     */
    public function testProcessHttpRequestWithRegularRequest(): void
    {
        $request = new ServerRequest('GET', '/test');
        $connection = Double::for(Connection::class);
        $routerResponse = new Response('test content', 200);

        $router = Double::for(PusherRouter::class);
        $router
            ->expects('dispatch')
            ->with($request, $connection)
            ->returns($routerResponse);

        $this->processor->setRouter($router);

        $response = $this->processor->processHttpRequest($request, $connection);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('test content', $response->getContent());
        $headers = $response->getHeaders();
        $this->assertEquals('*', $headers['Access-Control-Allow-Origin']);
    }

    /**
     * Test createCorsPreflightResponse using reflection
     *
     * @return void
     */
    public function testCreateCorsPreflightResponse(): void
    {
        $reflection = new ReflectionClass($this->processor);
        $method = $reflection->getMethod('createCorsPreflightResponse');

        $response = $method->invoke($this->processor);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertEquals('*', $headers['Access-Control-Allow-Origin']);
        $this->assertEquals('GET, POST, PUT, DELETE, OPTIONS', $headers['Access-Control-Allow-Methods']);
        $this->assertEquals('Content-Type, Authorization, X-Pusher-Key, X-Requested-With', $headers['Access-Control-Allow-Headers']);
        $this->assertEquals('86400', $headers['Access-Control-Max-Age']);
        $this->assertEquals('0', $headers['Content-Length']);
    }

    /**
     * Test addCorsHeaders using reflection
     *
     * @return void
     */
    public function testAddCorsHeaders(): void
    {
        $originalResponse = new Response('test', 200);

        $reflection = new ReflectionClass($this->processor);
        $method = $reflection->getMethod('addCorsHeaders');

        $response = $method->invoke($this->processor, $originalResponse);

        $headers = $response->getHeaders();
        $this->assertEquals('*', $headers['Access-Control-Allow-Origin']);
        $this->assertEquals('GET, POST, PUT, DELETE, OPTIONS', $headers['Access-Control-Allow-Methods']);
        $this->assertEquals('Content-Type, Authorization, X-Pusher-Key, X-Requested-With', $headers['Access-Control-Allow-Headers']);
    }

    /**
     * Test formatHttpResponse
     *
     * @return void
     */
    public function testFormatHttpResponse(): void
    {
        $response = new Response('test content', 200, [
            'Content-Type' => 'application/json',
        ]);

        $formatted = $this->processor->formatHttpResponse($response);

        $this->assertStringContainsString('HTTP/1.1 200 OK', $formatted);
        $this->assertStringContainsString('Content-Type: application/json', $formatted);
        $this->assertStringContainsString('test content', $formatted);
    }

    /**
     * Test router getter and setter
     *
     * @return void
     */
    public function testRouterGetterAndSetter(): void
    {
        $newRouter = Double::for(PusherRouter::class);

        $this->assertSame($this->router, $this->processor->getRouter());

        $this->processor->setRouter($newRouter);
        $this->assertSame($newRouter, $this->processor->getRouter());
    }
}
