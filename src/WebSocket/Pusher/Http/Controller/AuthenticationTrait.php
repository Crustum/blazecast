<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Psr\Http\Message\RequestInterface;

/**
 * AuthenticationTrait
 *
 * Provides authentication methods for Pusher controllers
 *
 * @phpstan-import-type QueryParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 */
trait AuthenticationTrait
{
    /**
     * Parse query parameters from request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @return QueryParams
     */
    protected function parseQueryParams(RequestInterface $request): array
    {
        $query = [];
        $queryString = $request->getUri()->getQuery();
        if (!empty($queryString)) {
            parse_str($queryString, $query);
        }

        return $query;
    }

    /**
     * Parse request body
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @return string|null
     */
    protected function parseRequestBody(RequestInterface $request): ?string
    {
        return (string)$request->getBody() ?: null;
    }

    /**
     * Verify signature of request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param QueryParams $params Parameters to include in signature
     * @param string $secret Application secret key
     * @return bool
     */
    protected function verifySignature(RequestInterface $request, array $params, string $secret): bool
    {
        $authSignature = $params['auth_signature'] ?? null;
        if (!$authSignature) {
            return false;
        }

        unset($params['auth_signature']);
        ksort($params);
        $queryString = http_build_query($params);
        $path = $request->getUri()->getPath();
        $signatureString = "{$request->getMethod()}\n{$path}\n{$queryString}";
        $expectedSignature = hash_hmac('sha256', $signatureString, $secret);

        BlazeCastLogger::info(sprintf('Verifying signature. path=%s, expected=%s, received=%s', $path, $expectedSignature, $authSignature), [
            'scope' => ['socket.controller', 'socket.controller.auth'],
        ]);

        return hash_equals($expectedSignature, $authSignature);
    }
}
