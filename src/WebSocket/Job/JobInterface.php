<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Job;

/**
 * Interface for all background jobs
 */
interface JobInterface
{
    /**
     * Start the job.
     *
     * @return void
     */
    public function start(): void;

    /**
     * Stop the job.
     *
     * @return void
     */
    public function stop(): void;

    /**
     * Execute the job's task.
     *
     * @return void
     */
    public function run(): void;
}
