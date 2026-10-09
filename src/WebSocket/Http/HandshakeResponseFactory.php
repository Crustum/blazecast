<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Http;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Response factory for the WebSocket handshake.
 *
 * The Ratchet handshake negotiator sets the "Sec-WebSocket-Version" header as an
 * integer, which "guzzlehttp/psr7" 3.x no longer casts to a string.
 * Ported from upstream `laravel/reverb` `HandshakeResponseFactory` (#410).
 */
class HandshakeResponseFactory implements ResponseFactoryInterface
{
    /**
     * Create a new response for the WebSocket handshake.
     *
     * @param int $code HTTP status code
     * @param string $reasonPhrase Reason phrase
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new class ($code, [], null, '1.1', $reasonPhrase) extends Response {
            /**
             * Cast integer header values (e.g. Sec-WebSocket-Version) to string.
             *
             * @param mixed $header Header name.
             * @param mixed $value Header value.
             * @return \Psr\Http\Message\MessageInterface
             */
            public function withHeader(mixed $header, mixed $value): MessageInterface
            {
                return parent::withHeader($header, is_int($value) ? (string)$value : $value);
            }
        };
    }
}
