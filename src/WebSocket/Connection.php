<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket;

use Cake\Event\EventManager;
use Crustum\BlazeCast\WebSocket\Event\MessageSentEvent;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Exception;
use React\Socket\ConnectionInterface as ReactConnectionInterface;

/**
 * Unified WebSocket Connection
 *
 * Single connection class that handles all connection types:
 * - Raw TCP connections
 * - HTTP upgrade connections
 * - WebSocket connections
 * - Pusher protocol connections
 *
 * Consolidates functionality from the over-engineered connection layer.
 *
 * @phpstan-type ConnectionAttributes array<string, mixed>
 *
 * @phpstan-type PingState array{
 *   last_ping_time: float|null,
 *   last_pong_time: float|null,
 *   pending_pings: int,
 *   ping_count: int,
 *   websocket_ping_sent: bool,
 *   pusher_ping_sent: bool
 * }
 */
class Connection implements ConnectionInterface
{
    /**
     * Connection ID (base connection ID)
     *
     * @var string
     */
    protected string $id;

    /**
     * Pusher-compliant socket ID (e.g., "12345.67890")
     *
     * @var string|null
     */
    protected ?string $socketId = null;

    /**
     * Connection attributes
     *
     * @var ConnectionAttributes
     */
    protected array $attributes = [];

    /**
     * Last activity timestamp
     *
     * @var float
     */
    protected float $lastActivity;

    /**
     * Connection state
     *
     * @var bool
     */
    protected bool $connected = false;

    /**
     * HTTP message buffer for upgrade connections
     *
     * @var string
     */
    protected string $buffer = '';

    /**
     * Ping/pong state tracking
     *
     * @var PingState
     */
    protected array $pingState = [
        'last_ping_time' => null,
        'last_pong_time' => null,
        'pending_pings' => 0,
        'ping_count' => 0,
        'websocket_ping_sent' => false,
        'pusher_ping_sent' => false,
    ];

    /**
     * Event manager instance
     *
     * @var \Cake\Event\EventManager|null
     */
    protected ?EventManager $eventManager = null;

    /**
     * Constructor
     *
     * @param \React\Socket\ConnectionInterface $reactConnection Raw React socket connection
     * @param string|null $id Optional connection ID (generated if not provided)
     */
    public function __construct(
        protected ReactConnectionInterface $reactConnection,
        ?string $id = null,
    ) {
        $this->id = $id ?? $this->generateId();
        $this->lastActivity = microtime(true);
        $this->resetPingState();

        BlazeCastLogger::debug(sprintf('Unified connection created, connection_id: %s', $this->id), [
            'scope' => ['socket.connection'],
        ]);
    }

    /**
     * Get the connection ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->socketId ?? $this->id;
    }

    /**
     * Send data to the connection
     *
     * @param string $data Data to send
     * @return void
     */
    public function send(string $data): void
    {
        try {
            if ($this->connected) {
                $frame = $this->encodeWebSocketFrame($data);
                $this->reactConnection->write($frame);
            } else {
                $this->reactConnection->write($data);
            }

            $this->updateActivity();

            $this->dispatchMessageSentEvent($data);

            BlazeCastLogger::debug(__('Data sent via unified connection {0}. Data length: {1}, is websocket: {2}', $this->getId(), strlen($data), $this->connected), [
                'scope' => ['socket.connection'],
            ]);
        } catch (Exception $e) {
            BlazeCastLogger::error(__('Error sending data to connection {0}: {1}', $this->getId(), $e->getMessage()), [
                'scope' => ['socket.connection'],
            ]);
        }
    }

    /**
     * Dispatch MessageSentEvent for Rhythm recording
     *
     * @param string $data Data that was sent
     * @return void
     */
    protected function dispatchMessageSentEvent(string $data): void
    {
        try {
            $event = new MessageSentEvent($this, $data);
            $eventManager = EventManager::instance();
            $eventManager->dispatch($event);
        } catch (Exception $e) {
            BlazeCastLogger::warning(__('Failed to dispatch MessageSentEvent: {0}', $e->getMessage()), [
                'scope' => ['socket.connection', 'socket.connection.events'],
            ]);
        }
    }

    /**
     * Close the connection
     *
     * @return void
     */
    public function close(): void
    {
        try {
            $this->reactConnection->end();
            $this->connected = false;

            BlazeCastLogger::info(__('Connection closed {0}', $this->getId()), [
                'scope' => ['socket.connection'],
            ]);
        } catch (Exception $e) {
            BlazeCastLogger::error(__('Error closing connection {0}: {1}', $this->getId(), $e->getMessage()), [
                'scope' => ['socket.connection'],
            ]);
        }
    }

    /**
     * Check if the connection is currently active/connected
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->connected && $this->reactConnection->isWritable();
    }

    /**
     * Check if the connection is stale (inactive for too long)
     *
     * @param int $threshold Stale threshold in seconds (optional)
     * @return bool
     */
    public function isStale(int $threshold = 120): bool
    {
        $timeSinceActivity = microtime(true) - $this->lastActivity;

        return $timeSinceActivity > $threshold;
    }

    /**
     * Send a ping to the connection
     *
     * @param string $type Ping type ('websocket' or 'pusher')
     * @return void
     */
    public function ping(string $type = 'websocket'): void
    {
        try {
            if ($type === 'websocket') {
                $this->control(0x9);
                $this->pingState['websocket_ping_sent'] = true;
            } elseif ($type === 'pusher') {
                $pusherPing = json_encode(['event' => 'pusher:ping', 'data' => []]);
                $this->send((string)$pusherPing);
                $this->pingState['pusher_ping_sent'] = true;
            }

            $this->pingState['last_ping_time'] = microtime(true);
            $this->pingState['pending_pings']++;
            $this->pingState['ping_count']++;

            BlazeCastLogger::debug(__('Ping sent to connection {0} ({1}), pending pings: {2}', $this->getId(), $type, $this->pingState['pending_pings']), [
                'scope' => ['socket.connection', 'socket.connection.ping'],
            ]);
        } catch (Exception $e) {
            BlazeCastLogger::error(__('Error sending {0} ping to connection {1}: {2}', $type, $this->getId(), $e->getMessage()), [
                'scope' => ['socket.connection', 'socket.connection.ping'],
            ]);
        }
    }

    /**
     * Mark that a pong was received from the connection
     *
     * @param string $type Pong type ('websocket' or 'pusher')
     * @return void
     */
    public function pong(string $type = 'websocket'): void
    {
        $this->pingState['last_pong_time'] = microtime(true);

        if ($this->pingState['pending_pings'] > 0) {
            $this->pingState['pending_pings']--;
        }

        if ($type === 'websocket') {
            $this->pingState['websocket_ping_sent'] = false;
        } elseif ($type === 'pusher') {
            $this->pingState['pusher_ping_sent'] = false;
        }

        $this->updateActivity();

        BlazeCastLogger::debug(__('Pong received from connection {0} ({1}), pending pings: {2}', $this->getId(), $type, $this->pingState['pending_pings']), [
            'scope' => ['socket.connection', 'socket.connection.pong'],
        ]);
    }

    /**
     * Get the ping/pong state of the connection
     *
     * @return array{
     *     last_ping_time: float|null,
     *     last_pong_time: float|null,
     *     pending_pings: int,
     *     ping_count: int
     * }
     */
    public function getPingState(): array
    {
        return [
            'last_ping_time' => $this->pingState['last_ping_time'],
            'last_pong_time' => $this->pingState['last_pong_time'],
            'pending_pings' => $this->pingState['pending_pings'],
            'ping_count' => $this->pingState['ping_count'],
        ];
    }

    /**
     * Reset the ping/pong state
     *
     * @return void
     */
    public function resetPingState(): void
    {
        $this->pingState = [
            'last_ping_time' => null,
            'last_pong_time' => null,
            'pending_pings' => 0,
            'ping_count' => 0,
            'websocket_ping_sent' => false,
            'pusher_ping_sent' => false,
        ];

        BlazeCastLogger::debug(__('Ping state reset for connection {0}', $this->getId()), [
            'scope' => ['socket.connection', 'socket.connection.ping'],
        ]);
    }

    /**
     * Get the last activity timestamp
     *
     * @return float
     */
    public function getLastActivity(): float
    {
        return $this->lastActivity;
    }

    /**
     * Update the last activity timestamp
     *
     * @return void
     */
    public function updateActivity(): void
    {
        $this->lastActivity = microtime(true);
    }

    /**
     * Set a connection attribute
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get a connection attribute
     *
     * @param string $key Attribute key
     * @param mixed $default Default value if attribute doesn't exist
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check if a connection attribute exists
     *
     * @param string $key Attribute key
     * @return bool
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Remove a connection attribute
     *
     * @param string $key Attribute key
     * @return void
     */
    public function removeAttribute(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Get all connection attributes
     *
     * @return ConnectionAttributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Send a control frame to the connection (for WebSocket protocol)
     *
     * @param int $opcode Control frame opcode (0x8=close, 0x9=ping, 0xA=pong)
     * @param string $payload Optional payload for the control frame
     * @return void
     */
    public function control(int $opcode, string $payload = ''): void
    {
        try {
            $frame = $this->encodeWebSocketFrame($payload, $opcode);
            $this->reactConnection->write($frame);
            $this->updateActivity();

            BlazeCastLogger::debug(__('Control frame sent to connection {0} ({1}), payload length: {2}', $this->getId(), $opcode, strlen($payload)), [
                'scope' => ['socket.connection', 'socket.connection.control'],
            ]);
        } catch (Exception $e) {
            BlazeCastLogger::error(__('Error sending control frame to connection {0}: {1}', $this->getId(), $e->getMessage()), [
                'scope' => ['socket.connection'],
            ]);
        }
    }

    /**
     * Send a message with event dispatching
     *
     * @param string $message Message to send
     * @return void
     */
    public function sendMessage(string $message): void
    {
        $this->send($message);

        BlazeCastLogger::debug(sprintf('Message sent with event dispatch. connection_id: %s, message_length: %d', $this->getId(), strlen($message)), [
            'scope' => ['socket.connection'],
        ]);
    }

    /**
     * Check if the connection is in connected state
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Get the HTTP message buffer (for HTTP upgrade connections)
     *
     * @return string
     */
    public function getBuffer(): string
    {
        return $this->buffer;
    }

    /**
     * Check if the connection has an HTTP message buffer
     *
     * @return bool
     */
    public function hasBuffer(): bool
    {
        return $this->buffer !== '';
    }

    /**
     * Append data to the HTTP message buffer
     *
     * @param string $data Data to append
     * @return void
     */
    public function appendToBuffer(string $data): void
    {
        $this->buffer .= $data;
    }

    /**
     * Clear the HTTP message buffer
     *
     * @return void
     */
    public function clearBuffer(): void
    {
        $this->buffer = '';
    }

    /**
     * Get the Pusher-compliant socket ID
     *
     * @return string|null
     */
    public function getSocketId(): ?string
    {
        return $this->socketId;
    }

    /**
     * Set the Pusher-compliant socket ID
     *
     * @param string $socketId Socket ID
     * @return void
     */
    public function setSocketId(string $socketId): void
    {
        $this->socketId = $socketId;

        BlazeCastLogger::debug(sprintf('Pusher socket ID set. connection_id: %s, socket_id: %s', $this->id, $socketId), [
            'scope' => ['socket.connection'],
        ]);
    }

    /**
     * Mark the connection as connected/upgraded to WebSocket
     *
     * @return void
     */
    public function markAsConnected(): void
    {
        $this->connected = true;
        $this->updateActivity();

        BlazeCastLogger::info(__('Connection marked as WebSocket connected {0}', $this->getId()), [
            'scope' => ['socket.connection'],
        ]);
    }

    /**
     * Get the buffer length (for HTTP connections)
     *
     * @return int
     */
    public function getBufferLength(): int
    {
        return strlen($this->buffer);
    }

    /**
     * Get the underlying React connection
     *
     * @return \React\Socket\ConnectionInterface
     */
    public function getReactConnection(): ReactConnectionInterface
    {
        return $this->reactConnection;
    }

    /**
     * Generate a unique ID for the connection
     *
     * @return string
     */
    protected function generateId(): string
    {
        return (string)random_int(1000000, 9999999);
    }

    /**
     * Get the event manager instance
     *
     * @return \Cake\Event\EventManager
     */
    protected function getEventManager(): EventManager
    {
        if ($this->eventManager === null) {
            $this->eventManager = EventManager::instance();
        }

        return $this->eventManager;
    }

    /**
     * Encode data into a WebSocket frame
     *
     * @param string $payload Message payload
     * @param int $opcode Frame opcode (0x1 for text, 0xA for pong, 0x9 for ping)
     * @return string Encoded WebSocket frame
     */
    protected function encodeWebSocketFrame(string $payload, int $opcode = 0x1): string
    {
        $payloadLength = strlen($payload);
        $frame = chr(0x80 | $opcode);

        if ($payloadLength < 126) {
            $frame .= chr($payloadLength);
        } elseif ($payloadLength < 65536) {
            $frame .= chr(126) . pack('n', $payloadLength);
        } else {
            $frame .= chr(127) . pack('J', $payloadLength);
        }

        $frame .= $payload;

        return $frame;
    }
}
