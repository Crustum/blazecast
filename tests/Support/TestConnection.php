<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\Support;

use Evenement\EventEmitterTrait;
use React\Socket\ConnectionInterface;
use React\Stream\WritableStreamInterface;

/**
 * TestConnection class for testing
 *
 * Implements ConnectionInterface for use in tests
 */
class TestConnection implements ConnectionInterface
{
    use EventEmitterTrait;

    /**
     * @var mixed
     */
    public $stream = 123;

    /**
     * @var string|null
     */
    private ?string $remoteAddress;

    /**
     * @var string|null
     */
    private ?string $localAddress;

    /**
     * @var bool
     */
    private bool $readable = true;

    /**
     * @var bool
     */
    private bool $writable = true;

    /**
     * @var array<string, mixed>
     */
    private array $sentMessages = [];

    /**
     * @var string
     */
    private string $id;

    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Constructor
     *
     * @param string|null $remoteAddress
     * @param string|null $localAddress
     * @param string|null $id
     */
    public function __construct(
        ?string $remoteAddress = '127.0.0.1:8000',
        ?string $localAddress = '127.0.0.1:1234',
        ?string $id = null,
    ) {
        $this->remoteAddress = $remoteAddress;
        $this->localAddress = $localAddress;
        $this->id = $id ?? uniqid('test_');
    }

    /**
     * @inheritDoc
     */
    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }

    /**
     * @inheritDoc
     */
    public function getLocalAddress()
    {
        return $this->localAddress;
    }

    /**
     * @inheritDoc
     */
    public function write($data)
    {
        $this->sentMessages[] = $data;

        return true;
    }

    /**
     * Send a message
     *
     * @param string $message
     * @return void
     */
    public function send(string $message): void
    {
        $this->sentMessages[] = $message;
    }

    /**
     * Get all sent messages
     *
     * @return array<string, mixed>
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * Get connection ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set an attribute
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return void
     */
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get an attribute
     *
     * @param string $key Attribute key
     * @param mixed $default Default value if attribute doesn't exist
     * @return mixed
     */
    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Remove an attribute
     *
     * @param string $key Attribute key
     * @return void
     */
    public function removeAttribute(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Check if attribute exists
     *
     * @param string $key Attribute key
     * @return bool
     */
    public function hasAttribute(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * @inheritDoc
     */
    public function end($data = null): void
    {
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        $this->readable = false;
        $this->writable = false;
        $this->emit('close');
    }

    /**
     * @inheritDoc
     */
    public function pause(): void
    {
    }

    /**
     * @inheritDoc
     */
    public function resume(): void
    {
    }

    /**
     * @inheritDoc
     */
    public function isReadable()
    {
        return $this->readable;
    }

    /**
     * @inheritDoc
     */
    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * Pipe data to a writable stream
     *
     * @param WritableStreamInterface $dest
     * @param array<string, mixed> $options
     * @return WritableStreamInterface
     */
    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        return $dest;
    }
}
