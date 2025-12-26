<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Protocol;

/**
 * Interface for WebSocket messages
 */
interface MessageInterface
{
    /**
     * Get the message event type
     *
     * @return string
     */
    public function getEvent(): string;

    /**
     * Get the message data
     *
     * @return mixed
     */
    public function getData(): mixed;

    /**
     * Get the channel if applicable
     *
     * @return string|null
     */
    public function getChannel(): ?string;

    /**
     * Convert the message to JSON
     *
     * @return string
     */
    public function toJson(): string;

    /**
     * Create message from JSON
     *
     * @param string $json JSON string
     * @return static
     */
    public static function fromJson(string $json): static;
}
