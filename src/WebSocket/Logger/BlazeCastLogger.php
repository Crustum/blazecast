<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Logger;

use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * BlazeCast Logger
 *
 * Custom logger for BlazeCast plugin with scope-based filtering
 * and dedicated log file support.
 */
class BlazeCastLogger
{
    /**
     * @var array<string|int, string>|null Cached enabled scopes (flipped for fast isset lookup)
     */
    protected static ?array $enabledScopes = null;

    /**
     * @var array<string|int, string>|null Cached disabled scopes (flipped for fast isset lookup)
     */
    protected static ?array $disabledScopes = null;

    /**
     * @var bool|null Cached logging enabled state
     */
    protected static ?bool $loggingEnabled = null;

    /**
     * @var bool|null Cached debug enabled state
     */
    protected static ?bool $debugEnabled = null;

    /**
     * Initialize logger configuration
     *
     * @return void
     */
    protected static function initialize(): void
    {
        if (self::$enabledScopes !== null) {
            return;
        }

        $config = Configure::read('BlazeCast.logging', []);
        self::$loggingEnabled = $config['enabled'] ?? true;

        self::$debugEnabled = $config['debug_enabled'] ?? false;

        $allScopes = $config['scopes'] ?? [];
        $disabledScopesList = $config['disabled_scopes'] ?? [];

        $enabledScopeKeys = array_keys(array_filter($allScopes, fn($enabled) => $enabled === true));
        /** @var array<string|int, string> $enabledScopesFlipped */
        $enabledScopesFlipped = array_flip($enabledScopeKeys);
        self::$enabledScopes = $enabledScopesFlipped;

        /** @var array<string|int, string> $disabledScopesFlipped */
        $disabledScopesFlipped = array_flip($disabledScopesList);
        self::$disabledScopes = $disabledScopesFlipped;
    }

    /**
     * Check if scope is enabled
     *
     * @param array<string> $scopes Scope array
     * @return bool True if at least one scope is enabled
     */
    protected static function isScopeEnabled(array $scopes): bool
    {
        self::initialize();

        if (!self::$loggingEnabled) {
            return false;
        }

        foreach ($scopes as $scope) {
            if (isset(self::$disabledScopes[$scope])) {
                continue;
            }

            if (isset(self::$enabledScopes[$scope])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract scopes from context array
     *
     * @param array<string, mixed>|array<string> $context Context array
     * @return array<string> Scope array
     */
    protected static function extractScopes(array $context): array
    {
        if (isset($context['scope'])) {
            return (array)$context['scope'];
        }

        if (isset($context[0]) && is_string($context[0])) {
            return $context;
        }

        return ['socket.server'];
    }

    /**
     * Write log message
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed>|array<string> $context Context array (same format as Log::info)
     * @return void
     */
    public static function write(string $level, string $message, array $context = []): void
    {
        if ($level === 'debug' && !self::$debugEnabled) {
            return;
        }

        $scopes = self::extractScopes($context);

        if (!self::isScopeEnabled($scopes)) {
            return;
        }

        $logContext = $context;
        if (!isset($logContext['scope'])) {
            $logContext['scope'] = $scopes;
        }

        Log::write($level, $message, $logContext);
    }

    /**
     * Log debug message
     *
     * @param string $message Log message
     * @param array<string, mixed>|array<string> $context Context array (same format as Log::debug)
     * @return void
     */
    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    /**
     * Log info message
     *
     * @param string $message Log message
     * @param array<string, mixed>|array<string> $context Context array (same format as Log::info)
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array<string, mixed>|array<string> $context Context array (same format as Log::warning)
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array<string, mixed>|array<string> $context Context array (same format as Log::error)
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * Reset cached configuration
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$enabledScopes = null;
        self::$disabledScopes = null;
        self::$loggingEnabled = null;
        self::$debugEnabled = null;
    }
}
