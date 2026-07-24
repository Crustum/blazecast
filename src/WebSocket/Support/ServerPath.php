<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Support;

use Cake\Core\Configure;

/**
 * ServerPath
 *
 * Helpers for the optional BlazeCast server path prefix (e.g. `/ws`).
 */
class ServerPath
{
    /**
     * Get the configured server path prefix.
     *
     * @param array<string, mixed>|null $serverConfig Optional server config override
     * @return string Normalized prefix without trailing slash (empty when unset)
     */
    public static function prefix(?array $serverConfig = null): string
    {
        $path = $serverConfig['path']
            ?? Configure::read('BlazeCast.servers.blazecast.path')
            ?? '';

        $path = trim((string)$path);
        if ($path === '' || $path === '/') {
            return '';
        }

        return '/' . trim($path, '/');
    }

    /**
     * Strip the configured server path prefix from a request URI path.
     *
     * @param string $path Request path
     * @param array<string, mixed>|null $serverConfig Optional server config override
     * @return string Path without prefix (always starts with `/` when non-empty prefix matched)
     */
    public static function strip(string $path, ?array $serverConfig = null): string
    {
        $prefix = self::prefix($serverConfig);
        if ($prefix === '') {
            return $path;
        }

        $normalizedPath = '/' . ltrim($path, '/');
        $normalizedPrefix = rtrim($prefix, '/');

        if ($normalizedPath === $normalizedPrefix) {
            return '/';
        }

        if (str_starts_with($normalizedPath, $normalizedPrefix . '/')) {
            $stripped = substr($normalizedPath, strlen($normalizedPrefix));

            return $stripped === '' ? '/' : $stripped;
        }

        return $path;
    }
}
