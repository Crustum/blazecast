<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\Support;

use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RuntimeException;

/**
 * WebSocket Test Client for integration testing
 */
class WebSocketTestClient
{
    private LoopInterface $loop;
    private ?WebSocket $connection = null;
    /**
     * @var array<int, mixed>
     */
    private array $receivedMessages = [];
    /**
     * @var array<callable>
     */
    private array $listeners = [];

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Connect to WebSocket server
     *
     * @param string $uri WebSocket URI
     * @return PromiseInterface<mixed>
     */
    public function connect(string $uri): PromiseInterface
    {
        $connector = new Connector($this->loop);

        return $connector($uri)
            ->then(function (WebSocket $conn) {
                $this->connection = $conn;

                $conn->on('message', function (MessageInterface $msg) {
                    $payload = $msg->getPayload();
                    $this->receivedMessages[] = $payload;

                    foreach ($this->listeners as $listener) {
                        $listener($payload);
                    }
                });

                $conn->on('close', function ($code = null, $reason = null) {
                    $this->connection = null;
                });

                return $conn;
            });
    }

    /**
     * Send a message
     *
     * @param string $message Message to send
     */
    public function send(string $message): void
    {
        if ($this->connection) {
            $this->connection->send($message);
        }
    }

    /**
     * Close the connection
     */
    public function close(): void
    {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /**
     * Get received messages
     *
     * @return array<int, mixed>
     */
    public function getReceivedMessages(): array
    {
        return $this->receivedMessages;
    }

    /**
     * Clear received messages
     */
    public function clearMessages(): void
    {
        $this->receivedMessages = [];
    }

    /**
     * Get last received message
     */
    public function getLastMessage(): ?string
    {
        return end($this->receivedMessages) ?: null;
    }

    /**
     * Wait for a message
     *
     * @param float $timeout Timeout in seconds
     * @return PromiseInterface<mixed>
     */
    public function waitForMessage(float $timeout = 2.0): PromiseInterface
    {
        $deferred = new Deferred();
        $startCount = count($this->receivedMessages);

        $timer = $this->loop->addTimer($timeout, function () use ($deferred) {
            $deferred->reject(new RuntimeException('Timeout waiting for message'));
        });

        $checkForMessage = function () use ($deferred, $timer, $startCount) {
            if (count($this->receivedMessages) > $startCount) {
                $this->loop->cancelTimer($timer);
                $deferred->resolve($this->receivedMessages[count($this->receivedMessages) - 1]);

                return true;
            }

            return false;
        };

        $this->loop->addPeriodicTimer(0.01, function ($periodicTimer) use ($checkForMessage) {
            if ($checkForMessage()) {
                $this->loop->cancelTimer($periodicTimer);
            }
        });

        return $deferred->promise();
    }

    /**
     * Wait for multiple messages
     *
     * @param int $count Number of messages to wait for
     * @param float $timeout Timeout in seconds
     * @return PromiseInterface<mixed>
     */
    public function waitForMessages(int $count, float $timeout = 2.0): PromiseInterface
    {
        $deferred = new Deferred();
        $startCount = count($this->receivedMessages);
        $targetCount = $startCount + $count;

        $timer = $this->loop->addTimer($timeout, function () use ($deferred) {
            $deferred->reject(new RuntimeException('Timeout waiting for messages'));
        });

        $checkForMessages = function () use ($deferred, $timer, $targetCount) {
            if (count($this->receivedMessages) >= $targetCount) {
                $this->loop->cancelTimer($timer);
                $deferred->resolve(array_slice($this->receivedMessages, -$targetCount));

                return true;
            }

            return false;
        };

        $this->loop->addPeriodicTimer(0.01, function ($periodicTimer) use ($checkForMessages) {
            if ($checkForMessages()) {
                $this->loop->cancelTimer($periodicTimer);
            }
        });

        return $deferred->promise();
    }

    /**
     * Add message listener
     *
     * @param callable $callback Message callback
     */
    public function onMessage(callable $callback): void
    {
        $this->listeners[] = $callback;
    }
}
