<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher;

use Cake\Core\Configure;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use InvalidArgumentException;

/**
 * Pusher Application Manager
 *
 * Manages multiple Pusher applications with key/secret authentication.
 *
 * @phpstan-type ApplicationConfig array{
 *   id: string,
 *   key: string,
 *   secret: string,
 *   name?: string,
 *   max_connections?: int,
 *   enable_client_messages?: bool,
 *   enable_statistics?: bool,
 *   enable_debug?: bool,
 *   ping_interval?: int,
 *   activity_timeout?: int,
 *   cluster?: string,
 *   enabled?: bool,
 *   created_at?: int,
 *   channel_manager?: \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager,
 *   max_backend_events_per_second?: int,
 *   max_frontend_events_per_second?: int,
 *   max_read_requests_per_second?: int,
 *   rate_limiter_enabled?: bool
 * }
 *
 * @phpstan-type ApplicationManagerConfig array{
 *   applications?: array<ApplicationConfig>,
 *   app_id?: string,
 *   app_key?: string,
 *   app_secret?: string,
 *   app_name?: string,
 *   max_connections?: int,
 *   enable_client_messages?: bool,
 *   enable_statistics?: bool,
 *   enable_debug?: bool
 * }
 *
 * @phpstan-type ApplicationStats array{
 *   id: string,
 *   name: string,
 *   max_connections: int,
 *   enable_client_messages: bool,
 *   enable_statistics: bool,
 *   created_at: int
 * }
 *
 * @phpstan-type AppContext array{
 *   app_id: string,
 *   app_key?: string,
 *   app_secret?: string
 * }
 */
class ApplicationManager
{
    /**
     * Registered applications
     *
     * @var array<string, ApplicationConfig>
     */
    protected array $applications = [];

    /**
     * Default application configuration
     *
     * @var array<string, mixed>
     */
    protected array $defaultConfig = [
        'max_connections' => null,
        'enable_client_messages' => true,
        'enable_statistics' => true,
        'enable_debug' => false,
        'max_backend_events_per_second' => 100,
        'max_frontend_events_per_second' => 10,
        'max_read_requests_per_second' => 50,
        'rate_limiter_enabled' => true,
    ];

    /**
     * Constructor
     *
     * @param ApplicationManagerConfig $config Configuration array
     */
    public function __construct(array $config = [])
    {
        $this->loadApplicationsFromConfig($config);
    }

    /**
     * Load applications from configuration
     *
     * @param ApplicationManagerConfig|array<array-key, mixed> $config Configuration array
     * @return void
     */
    protected function loadApplicationsFromConfig(array $config): void
    {
        if (isset($config['applications']) && is_array($config['applications'])) {
            foreach ($config['applications'] as $appConfig) {
                if (isset($appConfig['id'], $appConfig['key'], $appConfig['secret'])) {
                    $this->registerApplication($appConfig);
                }
            }
        } elseif (isset($config['app_id'], $config['app_key'], $config['app_secret'])) {
            $this->registerApplication([
                'id' => $config['app_id'],
                'key' => $config['app_key'],
                'secret' => $config['app_secret'],
                'name' => $config['app_name'] ?? 'Default App',
                'max_backend_events_per_second' => $config['max_backend_events_per_second'] ?? null,
                'max_frontend_events_per_second' => $config['max_frontend_events_per_second'] ?? null,
                'max_read_requests_per_second' => $config['max_read_requests_per_second'] ?? null,
                'enable_client_messages' => $config['enable_client_messages'] ?? null,
            ]);
        }

        $blazeCastApps = Configure::read('BlazeCast.apps');
        $configApps = is_array($blazeCastApps) && !empty($blazeCastApps) ? $blazeCastApps : Configure::read('Pusher.applications');
        if (is_array($configApps)) {
            foreach ($configApps as $appConfig) {
                if (isset($appConfig['id'], $appConfig['key'], $appConfig['secret'])) {
                    $this->registerApplication($appConfig);
                }
            }
        }

        // Log::info('Loaded ' . count($this->applications) . ' Pusher applications', [
        //     'scope' => ['websocket', 'pusher', 'applications'],
        // ]);
    }

    /**
     * Register a new application
     *
     * @param ApplicationConfig|array<array-key, mixed> $config Application configuration
     * @return void
     * @throws \InvalidArgumentException If required fields are missing
     */
    public function registerApplication(array $config): void
    {
        if (!isset($config['id'], $config['key'], $config['secret'])) {
            throw new InvalidArgumentException('Application must have id, key, and secret');
        }

        $appId = $config['id'];
        $appConfig = array_merge($this->defaultConfig, $config);

        $channelManager = $this->applications[$appId]['channel_manager'] ?? new ChannelManager();

        $existingApp = $this->applications[$appId] ?? [];
        $this->applications[$config['id']] = array_merge([
            'id' => $config['id'],
            'key' => $config['key'],
            'secret' => $config['secret'],
            'name' => $config['name'] ?? 'App ' . $config['id'],
            'max_connections' => $appConfig['max_connections'],
            'enable_client_messages' => $appConfig['enable_client_messages'],
            'enable_statistics' => $appConfig['enable_statistics'],
            'enable_debug' => $appConfig['enable_debug'],
            'created_at' => $existingApp['created_at'] ?? time(),
            'channel_manager' => $channelManager,
        ], $appConfig);

        // Log::info('Registered Pusher application', [
        //     'scope' => ['websocket', 'pusher', 'applications'],
        //     'app_id' => $config['id'],
        //     'app_name' => $appConfig['name'] ?? 'Unnamed',
        // ]);
    }

    /**
     * Get application by ID
     *
     * @param string $appId Application ID
     * @return ApplicationConfig|null Application configuration or null if not found
     */
    public function getApplication(string $appId): ?array
    {
        return $this->applications[$appId] ?? null;
    }

    /**
     * Get application by key
     *
     * @param string $appKey Application key
     * @return ApplicationConfig|null Application configuration or null if not found
     */
    public function getApplicationByKey(string $appKey): ?array
    {
        foreach ($this->applications as $app) {
            if ($app['key'] === $appKey) {
                return $app;
            }
        }

        return null;
    }

    /**
     * Validate application credentials
     *
     * @param string $appId Application ID
     * @param string $appKey Application key
     * @param string $appSecret Application secret
     * @return bool True if credentials are valid
     */
    public function validateCredentials(string $appId, string $appKey, string $appSecret): bool
    {
        $app = $this->getApplication($appId);

        if (!$app) {
            return false;
        }

        return $app['key'] === $appKey && $app['secret'] === $appSecret;
    }

    /**
     * Validate HMAC signature for HTTP requests
     *
     * @param string $appId Application ID
     * @param string $method HTTP method
     * @param string $path Request path
     * @param string $body Request body
     * @param string $signature Provided signature
     * @return bool True if signature is valid
     */
    public function validateSignature(string $appId, string $method, string $path, string $body, string $signature): bool
    {
        $app = $this->getApplication($appId);

        if (!$app) {
            return false;
        }

        $stringToSign = implode("\n", [
            strtoupper($method),
            $path,
            $body,
        ]);

        $expectedSignature = hash_hmac('sha256', $stringToSign, $app['secret']);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get all registered applications
     *
     * @return array<string, ApplicationConfig> Array of applications keyed by ID
     */
    public function getApplications(): array
    {
        return $this->applications;
    }

    /**
     * Get application count
     *
     * @return int Number of registered applications
     */
    public function getApplicationCount(): int
    {
        return count($this->applications);
    }

    /**
     * Check if application exists
     *
     * @param string $appId Application ID
     * @return bool True if application exists
     */
    public function hasApplication(string $appId): bool
    {
        return isset($this->applications[$appId]);
    }

    /**
     * Remove application
     *
     * @param string $appId Application ID
     * @return bool True if application was removed
     */
    public function removeApplication(string $appId): bool
    {
        if (isset($this->applications[$appId])) {
            unset($this->applications[$appId]);
                BlazeCastLogger::info('Removed Pusher application', [
                'scope' => ['socket.manager', 'socket.manager.application'],
                'app_id' => $appId,
                ]);

            return true;
        }

        return false;
    }

    /**
     * Get application statistics
     *
     * @param string $appId Application ID
     * @return ApplicationStats|array{} Application statistics or empty array if not found
     */
    public function getApplicationStats(string $appId): array
    {
        $app = $this->getApplication($appId);

        if (!$app) {
            return [];
        }

        return [
            'id' => $app['id'],
            'name' => $app['name'],
            'max_connections' => $app['max_connections'],
            'enable_client_messages' => $app['enable_client_messages'],
            'enable_statistics' => $app['enable_statistics'],
            'created_at' => $app['created_at'],
        ];
    }

    /**
     * Update application configuration
     *
     * @param string $appId Application ID
     * @param array<string, mixed> $config New configuration values
     * @return bool True if application was updated
     */
    public function updateApplication(string $appId, array $config): bool
    {
        if (!isset($this->applications[$appId])) {
            return false;
        }

        $protectedFields = ['id', 'key', 'secret', 'created_at'];
        $safeConfig = [];

        foreach ($config as $key => $value) {
            if (!in_array($key, $protectedFields)) {
                $safeConfig[$key] = $value;
            }
        }

        $this->applications[$appId] = array_merge(
            $this->applications[$appId],
            $safeConfig,
        );

                BlazeCastLogger::info('Updated Pusher application', [
            'scope' => ['socket.manager', 'socket.manager.application'],
            'app_id' => $appId,
            'updated_fields' => array_keys($safeConfig),
                ]);

        return true;
    }

    /**
     * Extract application context from WebSocket path
     *
     * @param string $path WebSocket connection path
     * @return AppContext|null App context with app_id and app_key, or null if invalid
     */
    public function extractAppContextFromPath(string $path): ?array
    {
        if (!preg_match('#^/app/([^/?]+)#', $path, $matches)) {
            return null;
        }

        $keyOrId = $matches[1];

        $application = $this->getApplicationByKey($keyOrId);
        if ($application) {
            return [
                'app_id' => $application['id'],
                'app_key' => $application['key'],
                'application' => $application,
            ];
        }

        $application = $this->getApplication($keyOrId);
        if ($application) {
            return [
                'app_id' => $application['id'],
                'app_key' => $application['key'],
                'application' => $application,
            ];
        }

        $applications = $this->getApplications();
        if (count($applications) === 1) {
            $application = array_values($applications)[0];
                BlazeCastLogger::info('Single-app setup: accepting any key/id for default application', [
                'scope' => ['socket.manager', 'socket.manager.application'],
                'provided_key_or_id' => $keyOrId,
                'using_app_id' => $application['id'],
                ]);

            return [
                'app_id' => $application['id'],
                'app_key' => $application['key'],
                'application' => $application,
            ];
        }

        $availableApps = implode(', ', array_keys($applications));
        BlazeCastLogger::warning(sprintf('No application found for WebSocket path. path=%s, key_or_id=%s, available_apps=[%s]', $path, $keyOrId, $availableApps), [
            'scope' => ['socket.manager', 'socket.manager.application'],
        ]);

        return null;
    }
}
