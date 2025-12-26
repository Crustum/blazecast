<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket;

/**
 * Unified WebSocket Connection Interface
 *
 * Consolidates all connection functionality from the over-engineered connection layer
 * into a single, clean interface that handles all connection types.
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
interface ConnectionInterface
{
    /**
     * Get the unique connection identifier
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Send data to the connection
     *
     * @param string $data Data to send
     * @return void
     */
    public function send(string $data): void;

    /**
     * Close the connection
     *
     * @return void
     */
    public function close(): void;

    /**
     * Check if the connection is currently active/connected
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Check if the connection is stale (inactive for too long)
     *
     * @param int $threshold Stale threshold in seconds (optional)
     * @return bool
     */
    public function isStale(int $threshold = 120): bool;

    /**
     * Send a ping to the connection
     *
     * @param string $type Ping type ('websocket' or 'pusher')
     * @return void
     */
    public function ping(string $type = 'websocket'): void;

    /**
     * Mark that a pong was received from the connection
     *
     * @param string $type Pong type ('websocket' or 'pusher')
     * @return void
     */
    public function pong(string $type = 'websocket'): void;

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
    public function getPingState(): array;

    /**
     * Reset the ping/pong state
     *
     * @return void
     */
    public function resetPingState(): void;

    /**
     * Get the last activity timestamp
     *
     * @return float
     */
    public function getLastActivity(): float;

    /**
     * Update the last activity timestamp
     *
     * @return void
     */
    public function updateActivity(): void;

    /**
     * Set a connection attribute
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void;

    /**
     * Get a connection attribute
     *
     * @param string $key Attribute key
     * @param mixed $default Default value if attribute doesn't exist
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed;

    /**
     * Check if a connection attribute exists
     *
     * @param string $key Attribute key
     * @return bool
     */
    public function hasAttribute(string $key): bool;

    /**
     * Remove a connection attribute
     *
     * @param string $key Attribute key
     * @return void
     */
    public function removeAttribute(string $key): void;

    /**
     * Get all connection attributes
     *
     * @return ConnectionAttributes
     */
    public function getAttributes(): array;

    /**
     * Send a control frame to the connection (for WebSocket protocol)
     *
     * @param int $opcode Control frame opcode (0x8=close, 0x9=ping, 0xA=pong)
     * @param string $payload Optional payload for the control frame
     * @return void
     */
    public function control(int $opcode, string $payload = ''): void;

    /**
     * Send a message with event dispatching
     *
     * @param string $message Message to send
     * @return void
     */
    public function sendMessage(string $message): void;

    /**
     * Check if the connection is in connected state
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Get the HTTP message buffer (for HTTP upgrade connections)
     *
     * @return string
     */
    public function getBuffer(): string;

    /**
     * Check if the connection has an HTTP message buffer
     *
     * @return bool
     */
    public function hasBuffer(): bool;

    /**
     * Append data to the HTTP message buffer
     *
     * @param string $data Data to append
     * @return void
     */
    public function appendToBuffer(string $data): void;

    /**
     * Clear the HTTP message buffer
     *
     * @return void
     */
    public function clearBuffer(): void;

    /**
     * Get the Pusher-compliant socket ID
     *
     * @return string|null
     */
    public function getSocketId(): ?string;

    /**
     * Set the Pusher-compliant socket ID
     *
     * @param string $socketId Socket ID
     * @return void
     */
    public function setSocketId(string $socketId): void;

    /**
     * Mark the connection as connected/upgraded to WebSocket
     *
     * @return void
     */
    public function markAsConnected(): void;
}
