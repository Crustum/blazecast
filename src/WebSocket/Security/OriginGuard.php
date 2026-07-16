<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Security;

/**
 * OriginGuard
 *
 * Validates WebSocket Origin headers and resolves CORS Allow-Origin values
 * using wildcard host patterns (e.g. `*.example.com`).
 */
class OriginGuard
{
    /**
     * Determine whether the given Origin header is allowed.
     *
     * @param array<string> $allowedOrigins Allowed origin patterns
     * @param string|null $originHeader Full Origin header value (e.g. https://example.com)
     * @return bool
     */
    public static function isAllowed(array $allowedOrigins, ?string $originHeader): bool
    {
        if ($allowedOrigins === [] || in_array('*', $allowedOrigins, true)) {
            return true;
        }

        $originHost = self::extractHost($originHeader);
        if ($originHost === null) {
            return false;
        }

        foreach ($allowedOrigins as $allowedOrigin) {
            if (self::matchesPattern((string)$allowedOrigin, $originHost)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the Access-Control-Allow-Origin response header value.
     *
     * @param array<string> $allowedOrigins Allowed origin patterns
     * @param string|null $originHeader Full Origin header value
     * @return string|null Header value, or null when the origin is not allowed
     */
    public static function resolveCorsOrigin(array $allowedOrigins, ?string $originHeader): ?string
    {
        if ($allowedOrigins === [] || in_array('*', $allowedOrigins, true)) {
            return '*';
        }

        if ($originHeader === null || $originHeader === '') {
            return null;
        }

        if (!self::isAllowed($allowedOrigins, $originHeader)) {
            return null;
        }

        return $originHeader;
    }

    /**
     * Extract the host from an Origin URL or bare hostname.
     *
     * @param string|null $origin Origin header or hostname
     * @return string|null
     */
    public static function extractHost(?string $origin): ?string
    {
        if ($origin === null || $origin === '') {
            return null;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        if (!str_contains($origin, '://') && !str_contains($origin, '/')) {
            return $origin;
        }

        return null;
    }

    /**
     * Match a host against a wildcard pattern (`*`, `*.example.com`, exact host).
     *
     * @param string $pattern Allowed pattern (`example.com`, `*.example.com`, `*`)
     * @param string $value Host to match
     * @return bool
     */
    public static function matchesPattern(string $pattern, string $value): bool
    {
        if ($pattern === '*' || $pattern === $value) {
            return true;
        }

        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';

        return (bool)preg_match($regex, $value);
    }
}
