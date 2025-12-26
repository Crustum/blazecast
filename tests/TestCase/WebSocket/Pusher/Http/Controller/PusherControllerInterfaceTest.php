<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Psr\Http\Message\RequestInterface;

class PusherControllerInterfaceTest extends TestCase
{
    /**
     * Test that a controller implementing the interface can be invoked
     *
     * @return void
     */
    public function testControllerCanBeInvoked(): void
    {
        $controller = new TestController();
        $request = $this->getMockBuilder(RequestInterface::class)->getMock();
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $response = $controller($request, $connection, ['param1' => 'value1']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('Test response with value1', $response->getBody());
    }

    /**
     * Test that a controller implementing the interface is stateless
     *
     * @return void
     */
    public function testControllerIsStateless(): void
    {
        $controller = new TestController();
        $request = $this->getMockBuilder(RequestInterface::class)->getMock();
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $response1 = $controller($request, $connection, ['param1' => 'value1']);
        $this->assertEquals('Test response with value1', $response1->getBody());

        $response2 = $controller($request, $connection, ['param1' => 'value2']);
        $this->assertEquals('Test response with value2', $response2->getBody());

        $this->assertNotEquals($response1->getBody(), $response2->getBody());
    }
}
