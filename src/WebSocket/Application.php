<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket;

/**
 * WebSocket Application class
 *
 * @phpstan-type ApplicationConfig array{
 *   app_id: string,
 *   key: string,
 *   secret: string,
 *   ping_interval: int,
 *   activity_timeout: int,
 *   allowed_origins: array<string>,
 *   max_message_size: int,
 *   options?: array<string, mixed>
 * }
 */
class Application
{
    /**
     * Create a new application instance.
     *
     * @param string $id Application ID
     * @param string $key Application key
     * @param string $secret Application secret
     * @param int $pingInterval Ping interval in seconds
     * @param int $activityTimeout Activity timeout in seconds
     * @param array<string> $allowedOrigins Array of allowed origins
     * @param int $maxMessageSize Maximum message size
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        protected string $id,
        protected string $key,
        protected string $secret,
        protected int $pingInterval,
        protected int $activityTimeout,
        protected array $allowedOrigins,
        protected int $maxMessageSize,
        protected array $options = [],
    ) {
    }

    /**
     * Get the application ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the application key.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the application secret.
     *
     * @return string
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * Get the allowed origins.
     *
     * @return array<string>
     */
    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**
     * Get the client ping interval in seconds.
     *
     * @return int
     */
    public function getPingInterval(): int
    {
        return $this->pingInterval;
    }

    /**
     * Get the activity timeout in seconds.
     *
     * @return int
     */
    public function getActivityTimeout(): int
    {
        return $this->activityTimeout;
    }

    /**
     * Get the maximum message size allowed from the client.
     *
     * @return int
     */
    public function getMaxMessageSize(): int
    {
        return $this->maxMessageSize;
    }

    /**
     * Get the application options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Convert the application to an array.
     *
     * @return ApplicationConfig
     */
    public function toArray(): array
    {
        return [
            'app_id' => $this->id,
            'key' => $this->key,
            'secret' => $this->secret,
            'ping_interval' => $this->pingInterval,
            'activity_timeout' => $this->activityTimeout,
            'allowed_origins' => $this->allowedOrigins,
            'max_message_size' => $this->maxMessageSize,
            'options' => $this->options,
        ];
    }
}
