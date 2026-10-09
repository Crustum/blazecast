<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use Psr\Http\Message\RequestInterface;

class PusherControllerInterfaceTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * Test that a controller implementing the interface can be invoked
     *
     * @return void
     */
    public function testControllerCanBeInvoked(): void
    {
        $controller = new TestController();
        $request = Double::for(RequestInterface::class);
        $connection = Double::for(Connection::class);

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
        $request = Double::for(RequestInterface::class);
        $connection = Double::for(Connection::class);

        $response1 = $controller($request, $connection, ['param1' => 'value1']);
        $this->assertEquals('Test response with value1', $response1->getBody());

        $response2 = $controller($request, $connection, ['param1' => 'value2']);
        $this->assertEquals('Test response with value2', $response2->getBody());

        $this->assertNotEquals($response1->getBody(), $response2->getBody());
    }
}
