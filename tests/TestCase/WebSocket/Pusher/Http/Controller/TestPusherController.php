<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection as WebSocketConnection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherController;
use Psr\Http\Message\RequestInterface;

/**
 * Test controller for exposing protected methods
 */
class TestPusherController extends PusherController
{
    /**
     * Flag for testing initialization
     *
     * @var bool
     */
    protected bool $initialized = false;

    /**
     * Last query parameters for testing statelessness
     *
     * @var array<string, mixed>
     */
    protected array $lastQuery = [];

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        $this->initialized = true;
    }

    /**
     * Check if controller was initialized
     *
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Expose parseQueryParams for testing
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return array<string, mixed>
     */
    public function callParseQueryParams(RequestInterface $request): array
    {
        return $this->parseQueryParams($request);
    }

    /**
     * Expose successResponse for testing
     *
     * @param array<string, mixed>|object|null $data
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    public function callSuccessResponse($data = null): Response
    {
        return $this->successResponse($data);
    }

    /**
     * Expose errorResponse for testing
     *
     * @param string $message
     * @param int $statusCode
     * @param string|null $code
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    public function callErrorResponse(string $message, int $statusCode = 400, ?string $code = null): Response
    {
        return $this->errorResponse($message, $statusCode, $code);
    }

    /**
     * Expose verifySignature for testing
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @param array<string, mixed> $params
     * @param string $secret
     * @return bool
     */
    public function callVerifySignature(RequestInterface $request, array $params, string $secret): bool
    {
        return $this->verifySignature($request, $params, $secret);
    }

    /**
     * Get last parsed query params
     *
     * @return array<string, mixed>
     */
    public function getLastQuery(): array
    {
        return $this->query;
    }

    /**
     * @inheritDoc
     */
    public function handle(RequestInterface $request, WebSocketConnection $connection, array $params): Response
    {
        $this->lastQuery = $this->parseQueryParams($request);

        return $this->successResponse(['message' => 'Test response']);
    }
}
