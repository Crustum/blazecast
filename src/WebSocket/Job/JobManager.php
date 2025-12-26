<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Job;

use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Throwable;

/**
 * Manages background jobs for the WebSocket server.
 */
class JobManager
{
    /**
     * @var array<string, \Crustum\BlazeCast\WebSocket\Job\JobInterface>
     */
    protected array $jobs = [];

    /**
     * Register a job.
     *
     * @param string $name The name of the job
     * @param \Crustum\BlazeCast\WebSocket\Job\JobInterface $job The job instance
     * @return void
     */
    public function register(string $name, JobInterface $job): void
    {
        $this->jobs[$name] = $job;
        BlazeCastLogger::info("Job '{$name}' registered.", ['scope' => ['socket.job', 'socket.job.manager']]);
    }

    /**
     * Start all registered jobs.
     *
     * @return void
     */
    public function startAll(): void
    {
        if (empty($this->jobs)) {
            BlazeCastLogger::info('No jobs to start.', ['scope' => ['socket.job', 'socket.job.manager']]);

            return;
        }

        BlazeCastLogger::info('Starting all registered jobs...', ['scope' => ['socket.job', 'socket.job.manager']]);
        foreach ($this->jobs as $name => $job) {
            try {
                $job->start();
            } catch (Throwable $e) {
                BlazeCastLogger::error("Failed to start job '{$name}': " . $e->getMessage(), [
                    'scope' => ['socket.job', 'socket.job.manager'],
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Stop all registered jobs.
     *
     * @return void
     */
    public function stopAll(): void
    {
        if (empty($this->jobs)) {
            return;
        }

        BlazeCastLogger::info('Stopping all registered jobs...', ['scope' => ['socket.job', 'socket.job.manager']]);
        foreach ($this->jobs as $name => $job) {
            try {
                $job->stop();
            } catch (Throwable $e) {
                BlazeCastLogger::error(sprintf("Failed to stop job '%s': %s", $name, $e->getMessage()), [
                    'scope' => ['socket.job', 'socket.job.manager'],
                ]);
            }
        }
    }

    /**
     * Get a specific job by name.
     *
     * @param string $name The name of the job
     * @return \Crustum\BlazeCast\WebSocket\Job\JobInterface|null
     */
    public function getJob(string $name): ?JobInterface
    {
        return $this->jobs[$name] ?? null;
    }

    /**
     * Get all registered jobs.
     *
     * @return array<string, \Crustum\BlazeCast\WebSocket\Job\JobInterface>
     */
    public function getAllJobs(): array
    {
        return $this->jobs;
    }
}
