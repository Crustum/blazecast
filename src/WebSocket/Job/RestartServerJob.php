<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Job;

use Cake\Log\Log;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use Exception;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * Job to restart the WebSocket server periodically
 */
class RestartServerJob implements JobInterface
{
    /**
     * Timer identifier
     *
     * @var \React\EventLoop\TimerInterface|null
     */
    protected ?TimerInterface $timerId = null;

    /**
     * Constructor
     *
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Server $server WebSocket server
     * @param int $restartAfter Time in seconds after which to restart the server
     */
    public function __construct(
        protected LoopInterface $loop,
        protected Server $server,
        protected int $restartAfter = 86400,
    ) {
    }

    /**
     * Start the job
     *
     * @return void
     */
    public function start(): void
    {
        $this->timerId = $this->loop->addTimer($this->restartAfter, function (): void {
            $this->run();
        });

        Log::info('RestartServerJob scheduled (restart after: ' . $this->formatDuration($this->restartAfter) . ')');
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

            Log::info('RestartServerJob stopped');
        }
    }

    /**
     * Execute the job
     *
     * @return void
     */
    public function run(): void
    {
        Log::info('Restarting WebSocket server...');

        try {
            $this->server->stop();

            $this->loop->addTimer(1, function (): void {
                $this->server->start();

                $this->start();

                Log::info('WebSocket server restarted successfully');
            });
        } catch (Exception $e) {
            Log::error('Error restarting WebSocket server: ' . $e->getMessage());
        }
    }

    /**
     * Format a duration in seconds to a human-readable string
     *
     * @param int $seconds Duration in seconds
     * @return string
     */
    protected function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
        }

        if ($minutes > 0) {
            $parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }

        if ($secs > 0 && count($parts) < 2) {
            $parts[] = $secs . ' second' . ($secs > 1 ? 's' : '');
        }

        return implode(', ', $parts);
    }
}
