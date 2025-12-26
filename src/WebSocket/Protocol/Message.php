<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Protocol;

use InvalidArgumentException;

/**
 * WebSocket message implementation
 *
 * @phpstan-consistent-constructor
 */
class Message implements MessageInterface
{
    /**
     * Constructor
     *
     * @param string $event Event type
     * @param mixed $data Message data
     * @param string|null $channel Channel name
     */
    public function __construct(
        protected string $event,
        protected mixed $data = null,
        protected ?string $channel = null,
    ) {
    }

    /**
     * Get the message event type
     *
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * Get the message data
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get the channel if applicable
     *
     * @return string|null
     */
    public function getChannel(): ?string
    {
        return $this->channel;
    }

    /**
     * Set the message data
     *
     * @param mixed $data Message data
     * @return $this
     */
    public function setData(mixed $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Set the channel
     *
     * @param string|null $channel Channel name
     * @return $this
     */
    public function setChannel(?string $channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Convert the message to JSON
     *
     * @return string
     */
    public function toJson(): string
    {
        $data = [
            'event' => $this->event,
            'data' => $this->data,
        ];

        if ($this->channel !== null) {
            $data['channel'] = $this->channel;
        }

        return json_encode($data);
    }

    /**
     * Create message from JSON
     *
     * @param string $json JSON string
     * @return static
     * @throws \InvalidArgumentException When JSON is invalid
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        if (!isset($data['event'])) {
            throw new InvalidArgumentException('Message must have an event property');
        }

        $messageData = $data['data'] ?? null;
        if (is_string($messageData)) {
            $decoded = json_decode($messageData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $messageData = $decoded;
            }
        }

        $message = new static(
            $data['event'],
            $messageData,
            $data['channel'] ?? null,
        );

        return $message;
    }

    /**
     * Create an authentication message
     *
     * @param string $token Authentication token
     * @return static
     */
    public static function auth(string $token): static
    {
        return new static('authenticate', [
            'token' => $token,
        ]);
    }

    /**
     * Create a channel subscription message
     *
     * @param string $channel Channel to subscribe to
     * @return static
     */
    public static function subscribe(string $channel): static
    {
        return new static('subscribe', null, $channel);
    }

    /**
     * Create a channel unsubscribe message
     *
     * @param string $channel Channel to unsubscribe from
     * @return static
     */
    public static function unsubscribe(string $channel): static
    {
        return new static('unsubscribe', null, $channel);
    }
}
