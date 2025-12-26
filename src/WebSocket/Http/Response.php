<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Http;

/**
 * HTTP Response
 *
 * Simple PSR-7 inspired HTTP response object
 *
 * @phpstan-type ResponseHeaders array<string, string>
 */
class Response
{
    /**
     * Response body
     *
     * @var mixed
     */
    protected mixed $body;

    /**
     * Response status code
     *
     * @var int
     */
    protected int $statusCode;

    /**
     * Response headers
     *
     * @var ResponseHeaders
     */
    protected array $headers = [];

    /**
     * Response content
     *
     * @var string
     */
    protected string $content = '';

    /**
     * Create a new Http response instance.
     *
     * @param mixed $data Data
     * @param int $status Status code
     * @param ResponseHeaders $headers Headers
     * @param bool $json JSON flag
     */
    public function __construct(mixed $data = null, int $status = 200, array $headers = [], bool $json = false)
    {
        $this->body = $data;
        $this->statusCode = $status;
        $this->headers = $headers;

        if ($json || is_array($data) || is_object($data)) {
            $this->content = json_encode($data);
            if (!isset($headers['Content-Type'])) {
                $this->headers['Content-Type'] = 'application/json';
            }
        } else {
            $this->content = (string)$data;
        }

        $this->headers['Content-Length'] = (string)strlen($this->content);
    }

    /**
     * Get response body
     *
     * @return mixed
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Get response status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get response headers
     *
     * @return ResponseHeaders
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get response content
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set header
     *
     * @param string $name Header name
     * @param string $value Header value
     * @return self
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Convert response to string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->content;
    }
}
