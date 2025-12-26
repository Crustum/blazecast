<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket;

use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;

/**
 * ApplicationContextResolver
 *
 * Resolves application context for connections.
 *
 * @phpstan-import-type ApplicationConfig from \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
 * @phpstan-type ConnectionInfo array{
 *   connection_id: string,
 *   app_id?: string,
 *   user_id?: string,
 *   user_info?: array<string, mixed>,
 *   channels: array<string>,
 *   last_activity: float,
 *   app_context?: array{
 *     app_id?: string,
 *     app_key?: string
 *   },
 *   connection?: \Crustum\BlazeCast\WebSocket\Connection,
 *   registered_at?: int
 * }
 */
class ApplicationContextResolver
{
    protected ApplicationManager $applicationManager;
    protected ChannelManager $defaultChannelManager;

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager $defaultChannelManager Default channel manager
     */
    public function __construct(ApplicationManager $applicationManager, ChannelManager $defaultChannelManager)
    {
        $this->applicationManager = $applicationManager;
        $this->defaultChannelManager = $defaultChannelManager;
    }

    /**
     * Get application ID for a connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param array<string, ConnectionInfo> $activeConnections Active connections array
     * @return string|null Application ID or null if not found
     */
    public function getAppIdForConnection(Connection $connection, array $activeConnections): ?string
    {
        $connectionId = $connection->getId();

        $appId = $connection->getAttribute('app_id');
        if ($appId) {
            return $appId;
        }

        $appKey = $connection->getAttribute('app_key');
        if ($appKey) {
            $application = $this->applicationManager->getApplicationByKey($appKey);
            if ($application) {
                return $application['id'];
            }
        }

        $connectionInfo = $activeConnections[$connectionId] ?? null;
        if ($connectionInfo) {
            if (isset($connectionInfo['app_context']['app_id'])) {
                return $connectionInfo['app_context']['app_id'];
            }

            if (isset($connectionInfo['app_id'])) {
                return $connectionInfo['app_id'];
            }
        }

        $applications = $this->applicationManager->getApplications();
        if (count($applications) === 1) {
            $firstApp = array_values($applications)[0];

            return $firstApp['id'];
        }

        $hasConnectionInfo = isset($activeConnections[$connectionId]) ? 'true' : 'false';
        $availableApps = implode(', ', array_keys($applications));
        BlazeCastLogger::warning(sprintf('No application ID found for connection. connection_id=%s, has_connection_info=%s, available_apps=[%s]', $connectionId, $hasConnectionInfo, $availableApps), [
            'scope' => ['socket.registry', 'socket.registry.context'],
        ]);

        return null;
    }

    /**
     * Get application-specific ChannelManager for a connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param array<string, ConnectionInfo> $activeConnections Active connections array
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager|null
     */
    public function getChannelManagerForConnection(Connection $connection, array $activeConnections): ?ChannelManager
    {
        $appId = $this->getAppIdForConnection($connection, $activeConnections);
        if (!$appId) {
            return $this->defaultChannelManager;
        }

        $application = $this->applicationManager->getApplication($appId);
        if (!$application) {
            BlazeCastLogger::warning(sprintf('Application not found for connection. connection_id=%s, app_id=%s', $connection->getId(), $appId), [
                'scope' => ['socket.registry', 'socket.registry.context'],
            ]);

            return $this->defaultChannelManager;
        }

        if (isset($application['channel_manager'])) {
            return $application['channel_manager'];
        }

        BlazeCastLogger::warning(sprintf('No ChannelManager found in application, using default. connection_id=%s, app_id=%s', $connection->getId(), $appId), [
            'scope' => ['socket.registry', 'socket.registry.context'],
        ]);

        return $this->defaultChannelManager;
    }

    /**
     * Get application by ID
     *
     * @param string $appId Application ID
     * @return ApplicationConfig|null Application data or null if not found
     */
    public function getApplication(string $appId): ?array
    {
        return $this->applicationManager->getApplication($appId);
    }

    /**
     * Get application by key
     *
     * @param string $appKey Application key
     * @return ApplicationConfig|null Application data or null if not found
     */
    public function getApplicationByKey(string $appKey): ?array
    {
        return $this->applicationManager->getApplicationByKey($appKey);
    }

    /**
     * Get all applications
     *
     * @return array<string, ApplicationConfig> Applications array
     */
    public function getApplications(): array
    {
        return $this->applicationManager->getApplications();
    }

    /**
     * Get application manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    public function getApplicationManager(): ApplicationManager
    {
        return $this->applicationManager;
    }
}
