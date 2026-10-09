<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Http;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Http\HandshakeResponseFactory;
use GuzzleHttp\Psr7\Request;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;

/**
 * HandshakeResponseFactoryTest
 *
 * Ported from upstream `laravel/reverb` `HandshakeResponseFactoryTest` (#410).
 */
class HandshakeResponseFactoryTest extends TestCase
{
    /**
     * Test factory creates a response with the given status.
     *
     * @return void
     */
    public function testCreatesResponseWithGivenStatus(): void
    {
        $response = (new HandshakeResponseFactory())->createResponse(101, 'Switching Protocols');

        $this->assertSame(101, $response->getStatusCode());
        $this->assertSame('Switching Protocols', $response->getReasonPhrase());
    }

    /**
     * Test the negotiator can set the version header through the factory.
     *
     * Ratchet's `ServerNegotiator` passes the version as an integer, which
     * `guzzlehttp/psr7` 3.x rejects without the casting factory. The integer
     * originates from Ratchet here, so no interface violation in test code.
     *
     * @return void
     */
    public function testNegotiatorHandshakeSetsVersionHeader(): void
    {
        $negotiator = new ServerNegotiator(new RequestVerifier(), new HandshakeResponseFactory());
        $request = new Request('GET', 'ws://localhost:8080/app/key', [
            'Connection' => 'Upgrade',
        ]);

        $response = $negotiator->handshake($request);

        $this->assertSame(426, $response->getStatusCode());
        $this->assertSame(['13'], $response->getHeader('Sec-WebSocket-Version'));
    }

    /**
     * Test header casting survives derived responses.
     *
     * @return void
     */
    public function testKeepsHeaderCastingOnDerivedResponses(): void
    {
        $negotiator = new ServerNegotiator(new RequestVerifier(), new HandshakeResponseFactory());
        $request = new Request('GET', 'ws://localhost:8080/app/key', [
            'Connection' => 'Upgrade',
        ]);

        $response = $negotiator->handshake($request)
            ->withHeader('Upgrade', 'websocket');

        $this->assertSame(426, $response->getStatusCode());
        $this->assertSame(['13'], $response->getHeader('Sec-WebSocket-Version'));
    }
}
