<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;

/**
 * ResponseTrait
 *
 * Provides response handling methods for Pusher controllers
 *
 * @phpstan-import-type ResponseData from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 * @phpstan-import-type ResponseHeaders from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 */
trait ResponseTrait
{
    /**
     * Create a JSON response
     *
     * @param ResponseData $data Response data
     * @param int $statusCode HTTP status code
     * @param ResponseHeaders $headers Additional headers
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    protected function jsonResponse(array|object|null $data = null, int $statusCode = 200, array $headers = []): Response
    {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
        ];

        return new Response($data, $statusCode, array_merge($defaultHeaders, $headers));
    }

    /**
     * Create a success response
     *
     * @param ResponseData $data Optional data
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    protected function successResponse(array|object|null $data = null): Response
    {
        return $this->jsonResponse($data ?: (object)[]);
    }

    /**
     * Create an error response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param string|null $code Error code
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    protected function errorResponse(string $message, int $statusCode = 400, ?string $code = null): Response
    {
        $data = [
            'error' => $message,
        ];

        if ($code !== null) {
            $data['code'] = $code;
        }

        $errorCode = $code ?? 'null';
        BlazeCastLogger::warning(sprintf('Pusher API error. message=%s, status_code=%d, error_code=%s', $message, $statusCode, $errorCode), [
            'scope' => ['socket.controller', 'socket.controller.response'],
        ]);

        return $this->jsonResponse($data, $statusCode);
    }
}
