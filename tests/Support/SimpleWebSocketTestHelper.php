<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\Support;

use Exception;
use React\Async;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\Connector;
use RuntimeException;

/**
 * Simple WebSocket test helper
 *
 * Provides utilities for testing WebSocket connections and messages.
 */
class SimpleWebSocketTestHelper
{
    private LoopInterface $loop;
    private ?string $host = null;
    private ?int $port = null;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function setServerAddress(string $host, int $port): void
    {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Run a callback with timeout
     *
     * @param float $timeout Timeout in seconds
     * @param callable $callback Callback to execute
     * @return mixed
     */
    public function runWithTimeout(float $timeout, callable $callback)
    {
        $deferred = new Deferred();

        $timer = $this->loop->addTimer($timeout, function () use ($deferred, $timeout) {
            $deferred->reject(new RuntimeException("Test timeout after {$timeout} seconds"));
        });

        try {
            $result = $callback();
            if ($result instanceof PromiseInterface) {
                $result->then(
                    function ($value) use ($deferred, $timer) {
                        $this->loop->cancelTimer($timer);
                        $deferred->resolve($value);
                    },
                    function ($error) use ($deferred, $timer) {
                        $this->loop->cancelTimer($timer);
                        $deferred->reject($error);
                    },
                );
            } else {
                $this->loop->cancelTimer($timer);
                $deferred->resolve($result);
            }
        } catch (Exception $e) {
            $this->loop->cancelTimer($timer);
            $deferred->reject($e);
        }

        return Async\await($deferred->promise());
    }

    /**
     * Connect to the WebSocket server
     *
     * @return PromiseInterface<mixed>
     */
    public function connectToServer(): PromiseInterface
    {
        if (!$this->host || !$this->port) {
            throw new RuntimeException('Server address not set');
        }

        $connector = new Connector($this->loop);

        return $connector->connect("{$this->host}:{$this->port}");
    }

    /**
     * Send HTTP upgrade request to establish WebSocket connection
     *
     * @param mixed $connection Connection object
     * @param string $path Request path
     * @return PromiseInterface<mixed>
     */
    public function sendHttpUpgradeRequest($connection, string $path = '/'): PromiseInterface
    {
        $deferred = new Deferred();

        $key = base64_encode(random_bytes(16));
        $request = "GET {$path} HTTP/1.1\r\n" .
                   "Host: {$this->host}:{$this->port}\r\n" .
                   "Upgrade: websocket\r\n" .
                   "Connection: Upgrade\r\n" .
                   "Sec-WebSocket-Key: {$key}\r\n" .
                   "Sec-WebSocket-Version: 13\r\n" .
                   "\r\n";

        $responseData = '';
        $connection->on('data', function ($data) use (&$responseData, $deferred, $connection) {
            $responseData .= $data;

            if (strpos($responseData, "\r\n\r\n") !== false) {
                if (strpos($responseData, 'HTTP/1.1 101') === 0) {
                    $deferred->resolve($connection);
                } else {
                    $deferred->reject(new RuntimeException('WebSocket upgrade failed'));
                }
            }
        });

        $connection->write($request);

        return $deferred->promise();
    }

    /**
     * Create a simple WebSocket message
     *
     * @param string $event Event name
     * @param array<string, mixed> $data Event data
     * @param string|null $channel Channel name
     * @return string JSON encoded message
     */
    public function createSimpleMessage(string $event, array $data = [], ?string $channel = null): string
    {
        $message = [
            'event' => $event,
            'data' => $data,
        ];

        if ($channel !== null) {
            $message['channel'] = $channel;
        }

        return json_encode($message);
    }

    /**
     * Parse a WebSocket message
     *
     * @param string $rawMessage Raw message string
     * @return array<string, mixed> Parsed message data
     */
    public function parseMessage(string $rawMessage): array
    {
        return json_decode($rawMessage, true) ?: [];
    }

    /**
     * Wait for server to start
     *
     * @param string $host Server host
     * @param int $port Server port
     * @param float $timeout Timeout in seconds
     * @return bool True if server started, false otherwise
     */
    public function waitForServerToStart(string $host, int $port, float $timeout = 5.0): bool
    {
        $startTime = microtime(true);

        while (microtime(true) - $startTime < $timeout) {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket && socket_connect($socket, $host, $port)) {
                socket_close($socket);

                return true;
            }
            if ($socket) {
                socket_close($socket);
            }
            usleep(100000);
        }

        return false;
    }

    /**
     * Get an available port
     *
     * @return int Available port number
     */
    public function getAvailablePort(): int
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new RuntimeException('Could not create socket');
        }

        socket_bind($socket, '127.0.0.1', 0);
        socket_getsockname($socket, $addr, $port);
        socket_close($socket);

        return $port;
    }
}
