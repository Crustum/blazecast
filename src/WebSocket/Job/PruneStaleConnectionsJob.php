<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Job;

use Cake\Event\EventManager;
use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Event\ConnectionPrunedEvent;
use Crustum\BlazeCast\WebSocket\Pusher\Pusher;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * Job to prune stale connections that haven't responded to pings
 */
class PruneStaleConnectionsJob implements JobInterface
{
    /**
     * Run interval in seconds
     *
     * @var int
     */
    protected int $interval;

    /**
     * Timer identifier
     *
     * @var \React\EventLoop\TimerInterface|string|int|null
     */
    protected TimerInterface|string|int|null $timerId = null;

    /**
     * Constructor
     *
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Server $server The Pusher server instance
     * @param int $interval Interval in seconds between pruning checks
     */
    public function __construct(
        protected LoopInterface $loop,
        protected Server $server,
        int $interval = 60,
    ) {
        $this->interval = $interval;
    }

    /**
     * Start the job
     *
     * @return void
     */
    public function start(): void
    {
        $this->timerId = $this->loop->addPeriodicTimer($this->interval, function (): void {
            $this->run();
        });

        Log::info("PruneStaleConnectionsJob started (interval: {$this->interval}s)");
    }

    /**
     * Stop the job
     *
     * @return void
     */
    public function stop(): void
    {
        if ($this->timerId !== null) {
            $this->loop->cancelTimer($this->timerId);
            $this->timerId = null;

            Log::info('PruneStaleConnectionsJob stopped');
        }
    }

    /**
     * Execute the job
     *
     * @return void
     */
    public function run(): void
    {
        $prunedCount = 0;

        $connectionRegistry = $this->server->getConnectionRegistry();
        foreach ($connectionRegistry->getConnections() as $connection) {
            if ($this->shouldPruneConnection($connection)) {
                $this->pruneConnection($connection);
                $prunedCount++;
            }
        }

        if ($prunedCount > 0) {
            Log::info("Pruned {$prunedCount} stale connections");
        }
    }

    /**
     * Determine if a connection should be pruned
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @return bool
     */
    protected function shouldPruneConnection(Connection $connection): bool
    {
        $applicationContextResolver = $this->server->getApplicationContextResolver();
        $appId = $applicationContextResolver->getAppIdForConnection($connection, []);

        if (!$appId) {
            return $connection->isStale(120);
        }

        $applicationManager = $this->server->getApplicationManager();
        $app = $applicationManager->getApplication($appId);

        if (!$app) {
            return $connection->isStale(120);
        }

        $staleThreshold = $app['activity_timeout'] ?? 120;

        return $connection->isStale((int)$staleThreshold);
    }

    /**
     * Prune a single connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The connection to prune
     * @return void
     */
    protected function pruneConnection(Connection $connection): void
    {
        $this->sendPruningMessage($connection);

        $connection->close();

        $event = new ConnectionPrunedEvent($connection, 'inactivity');
        $this->getEventManager()->dispatch($event);
    }

    /**
     * Send a Pusher error message before closing the connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The connection to notify
     * @return void
     */
    protected function sendPruningMessage(Connection $connection): void
    {
        $message = Pusher::error(
            4201,
            'Pong reply not received in time',
        );

        $connection->send($message);
    }

    /**
     * Get the event manager
     *
     * @return \Cake\Event\EventManager
     */
    protected function getEventManager(): EventManager
    {
        return EventManager::instance();
    }
}
