<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\Test\Support\PusherControllerTestHelper;
use Crustum\BlazeCast\WebSocket\Http\Response;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Tests for the base PusherController
 */
class PusherControllerTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @var \Crustum\BlazeCast\Test\Support\PusherControllerTestHelper
     */
    protected PusherControllerTestHelper $helper;

    /**
     * @var \Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller\TestPusherController
     */
    protected TestPusherController $controller;

    /**
     * Setup method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new PusherControllerTestHelper($this);
        $this->controller = new TestPusherController(
            $this->helper->getApplicationManager(),
            $this->helper->getChannelManager(),
            $this->helper->getConnectionManager(),
        );
    }

    /**
     * Test controller constructor and initialization
     *
     * @return void
     */
    public function testConstructorAndInitialization(): void
    {
        $controller = $this->controller;

        $this->assertSame($this->helper->getApplicationManager(), $controller->getApplicationManager());
        $this->assertSame($this->helper->getChannelManager(), $controller->getChannelManager());
        $this->assertSame($this->helper->getConnectionManager(), $controller->getConnectionManager());
        $this->assertTrue($controller->isInitialized(), 'Controller should be initialized after construction');
    }

    /**
     * Test parsing query parameters
     *
     * @return void
     */
    public function testParseQueryParams(): void
    {
        $uri = Double::for(UriInterface::class);
        $uri->allows('getQuery')->returns('foo=bar&test=123');

        $request = Double::for(RequestInterface::class);
        $request->allows('getUri')->returns($uri);

        $params = $this->controller->callParseQueryParams($request);

        $this->assertEquals([
            'foo' => 'bar',
            'test' => '123',
        ], $params);
    }

    /**
     * Test controller responses
     *
     * @return void
     */
    public function testResponseMethods(): void
    {
        $success = $this->controller->callSuccessResponse(['data' => 'test']);
        $this->helper->assertResponse($success, 200, ['data' => 'test']);

        $error = $this->controller->callErrorResponse('Test error', 404, 'ERROR_CODE');
        $this->helper->assertResponse($error, 404, ['error' => 'Test error', 'code' => 'ERROR_CODE']);

        $emptySuccess = $this->controller->callSuccessResponse();
        $this->assertInstanceOf(Response::class, $emptySuccess);
    }

    /**
     * Test that controller remains stateless between requests
     *
     * @return void
     */
    public function testControllerStatelessness(): void
    {
        $uri1 = Double::for(UriInterface::class);
        $uri1->allows('getQuery')->returns('param=value1');
        $uri1->allows('getPath')->returns('/test/path');

        $request1 = Double::for(RequestInterface::class);
        $request1->allows('getUri')->returns($uri1);
        $request1->allows('getMethod')->returns('GET');
        $request1->allows('getBody')->returns('');

        $this->controller->__invoke($request1, $this->helper->getConnection(), ['param' => 'value1']);

        $this->assertEquals(['param' => 'value1'], $this->controller->getLastQuery());

        $uri2 = Double::for(UriInterface::class);
        $uri2->allows('getQuery')->returns('param=value2');
        $uri2->allows('getPath')->returns('/test/path');

        $request2 = Double::for(RequestInterface::class);
        $request2->allows('getUri')->returns($uri2);
        $request2->allows('getMethod')->returns('GET');
        $request2->allows('getBody')->returns('');

        $this->controller->__invoke($request2, $this->helper->getConnection(), ['param' => 'value2']);

        $this->assertEquals(['param' => 'value2'], $this->controller->getLastQuery());
    }

    /**
     * Test handling OPTIONS requests
     *
     * @return void
     */
    public function testOptionsRequestHandling(): void
    {
        $request = Double::for(RequestInterface::class);
        $request->allows('getMethod')->returns('OPTIONS');
        $uri = Double::for(UriInterface::class);
        $uri->allows('getPath')->returns('/test');
        $request->allows('getUri')->returns($uri);
        $request->allows('getBody')->returns('');

        $response = $this->controller->__invoke($request, $this->helper->getConnection());

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $response->getHeaders());
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $response->getHeaders());
    }

    /**
     * Test signature verification
     *
     * @return void
     */
    public function testSignatureVerification(): void
    {
        $params = [
            'auth_key' => 'test-key',
            'auth_timestamp' => '1234567890',
            'auth_signature' => 'invalid-signature',
        ];

        $uri = Double::for(UriInterface::class);
        $uri->allows('getPath')->returns('/test/path');

        $request = Double::for(RequestInterface::class);
        $request->allows('getUri')->returns($uri);
        $request->allows('getMethod')->returns('GET');

        $result = $this->controller->callVerifySignature($request, $params, 'test-secret');

        $this->assertFalse($result, 'Should return false for invalid signature');
    }
}
