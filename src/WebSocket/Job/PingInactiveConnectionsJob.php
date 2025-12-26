<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Job;

use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * Job to ping inactive connections to keep them alive
 */
class PingInactiveConnectionsJob implements JobInterface
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
     * @param int $interval Interval in seconds between pings
     */
    public function __construct(
        protected LoopInterface $loop,
        protected Server $server,
        int $interval = 30,
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

        Log::info("PingInactiveConnectionsJob started (interval: {$this->interval}s)");
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

            Log::info('PingInactiveConnectionsJob stopped');
        }
    }

    /**
     * Execute the job
     *
     * @return void
     */
    public function run(): void
    {
        $pingCount = 0;

        foreach ($this->server->getConnections() as $connection) {
            if ($this->shouldPing($connection)) {
                $this->ping($connection);
                $pingCount++;
            }
        }

        if ($pingCount > 0) {
            Log::info("Sent ping to {$pingCount} connections");
        }
    }

    /**
     * Determine if a connection should be pinged
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The connection to check
     * @return bool
     */
    protected function shouldPing(Connection $connection): bool
    {
        $appId = $this->server->getAppIdForConnection($connection);
        if (!$appId) {
            return true;
        }

        $app = $this->server->getApplicationManager()->getApplication($appId);
        if (!$app) {
            return true;
        }

        $pingInterval = $app['ping_interval'] ?? $this->interval;
        $timeSinceActivity = microtime(true) - $connection->getLastActivity();

        return $timeSinceActivity >= $pingInterval;
    }

    /**
     * Send a dual-protocol ping to the connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The connection to ping
     * @return void
     */
    protected function ping(Connection $connection): void
    {
        $connection->ping('websocket');

        $connection->ping('pusher');
    }
}
