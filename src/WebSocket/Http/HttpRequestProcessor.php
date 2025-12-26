<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Http;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * HttpRequestProcessor
 *
 * Handles HTTP request processing including CORS, parsing, and routing.
 */
class HttpRequestProcessor
{
    protected PusherRouter $router;
    protected int $maxRequestSize;

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Http\PusherRouter $router HTTP router
     * @param int $maxRequestSize Maximum request size in bytes
     */
    public function __construct(PusherRouter $router, int $maxRequestSize = 10000)
    {
        $this->router = $router;
        $this->maxRequestSize = $maxRequestSize;
    }

    /**
     * Check if HTTP request is complete
     *
     * @param string $buffer Request buffer
     * @return bool
     */
    public function isCompleteHttpRequest(string $buffer): bool
    {
        return strpos($buffer, "\r\n\r\n") !== false;
    }

    /**
     * Check if request exceeds maximum size
     *
     * @param int $bufferLength Current buffer length
     * @return bool
     */
    public function exceedsMaxSize(int $bufferLength): bool
    {
        return $bufferLength > $this->maxRequestSize;
    }

    /**
     * Parse HTTP request from buffer
     *
     * @param string $buffer Request buffer
     * @return \Psr\Http\Message\RequestInterface
     * @throws \Throwable
     */
    public function parseHttpRequest(string $buffer): RequestInterface
    {
        $request = Message::parseRequest($buffer);

        if ($request instanceof Request) {
            return new ServerRequest(
                $request->getMethod(),
                $request->getUri(),
                $request->getHeaders(),
                $request->getBody(),
                $request->getProtocolVersion(),
            );
        }

        return $request;
    }

    /**
     * Handle complete HTTP request flow including connection management
     *
     * @param \Psr\Http\Message\RequestInterface $request PSR-7 request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return void
     */
    public function handleHttpRequest(RequestInterface $request, Connection $connection): void
    {
            BlazeCastLogger::info('HTTP request received', [
            'scope' => ['socket.http', 'socket.http.processor'],
            'connection_id' => $connection->getId(),
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            ]);

        try {
            $response = $this->processHttpRequest($request, $connection);

            if ($response) {
                $httpResponse = $this->formatHttpResponse($response);
                $connection->send($httpResponse);
            }
            $connection->close();
        } catch (Throwable $e) {
                BlazeCastLogger::error('HTTP request failed: ' . $e->getMessage(), [
                'scope' => ['socket.http', 'socket.http.processor'],
                'connection_id' => $connection->getId(),
                'exception' => $e,
                ]);
            $this->closeConnection($connection, 500, 'Internal Server Error');
        }
    }

    /**
     * Process HTTP request and return response
     *
     * @param \Psr\Http\Message\RequestInterface $request PSR-7 request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return \Crustum\BlazeCast\WebSocket\Http\Response|null
     * @throws \Throwable
     */
    public function processHttpRequest(RequestInterface $request, Connection $connection): ?Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $this->createCorsPreflightResponse();
        }

        $response = $this->router->dispatch($request, $connection);

        return $this->addCorsHeaders($response);
    }

    /**
     * Create CORS preflight response
     *
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    protected function createCorsPreflightResponse(): Response
    {
        return new Response('', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Pusher-Key, X-Requested-With',
            'Access-Control-Max-Age' => '86400',
            'Content-Length' => '0',
        ]);
    }

    /**
     * Add CORS headers to response
     *
     * @param \Crustum\BlazeCast\WebSocket\Http\Response $response Original response
     * @return \Crustum\BlazeCast\WebSocket\Http\Response Response with CORS headers
     */
    protected function addCorsHeaders(Response $response): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Pusher-Key, X-Requested-With');
    }

    /**
     * Format HTTP response from Response object
     *
     * @param \Crustum\BlazeCast\WebSocket\Http\Response $response Response object
     * @return string Formatted HTTP response
     */
    public function formatHttpResponse(Response $response): string
    {
        $httpResponse = "HTTP/1.1 {$response->getStatusCode()} OK\r\n";

        foreach ($response->getHeaders() as $name => $value) {
            $httpResponse .= "{$name}: {$value}\r\n";
        }

        $httpResponse .= "\r\n";
        $httpResponse .= $response->getContent();

        return $httpResponse;
    }

    /**
     * Close connection with error response
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection to close
     * @param int $statusCode HTTP status code
     * @param string $message Error message
     * @return void
     */
    protected function closeConnection(Connection $connection, int $statusCode, string $message): void
    {
        $response = new GuzzleResponse($statusCode, ['Content-Type' => 'text/plain'], $message);
        $connection->send(Message::toString($response));
        $connection->close();
    }

    /**
     * Get the HTTP router
     *
     * @return \Crustum\BlazeCast\WebSocket\Http\PusherRouter
     */
    public function getRouter(): PusherRouter
    {
        return $this->router;
    }

    /**
     * Set the HTTP router
     *
     * @param \Crustum\BlazeCast\WebSocket\Http\PusherRouter $router HTTP router
     * @return void
     */
    public function setRouter(PusherRouter $router): void
    {
        $this->router = $router;
    }
}
