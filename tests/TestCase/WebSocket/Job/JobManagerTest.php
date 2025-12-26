<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket\Job;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Job\JobInterface;
use Crustum\BlazeCast\WebSocket\Job\JobManager;

class JobManagerTest extends TestCase
{
    public function testRegisterAndGetJob(): void
    {
        $jobManager = new JobManager();
        $job = $this->createMock(JobInterface::class);

        $jobManager->register('test_job', $job);

        $this->assertSame($job, $jobManager->getJob('test_job'));
        $this->assertCount(1, $jobManager->getAllJobs());
    }

    public function testStartAllJobs(): void
    {
        $jobManager = new JobManager();
        $job1 = $this->createMock(JobInterface::class);
        $job2 = $this->createMock(JobInterface::class);

        $job1->expects($this->once())->method('start');
        $job2->expects($this->once())->method('start');

        $jobManager->register('job1', $job1);
        $jobManager->register('job2', $job2);

        $jobManager->startAll();
    }

    public function testStopAllJobs(): void
    {
        $jobManager = new JobManager();
        $job1 = $this->createMock(JobInterface::class);
        $job2 = $this->createMock(JobInterface::class);

        $job1->expects($this->once())->method('stop');
        $job2->expects($this->once())->method('stop');

        $jobManager->register('job1', $job1);
        $jobManager->register('job2', $job2);

        $jobManager->stopAll();
    }
}
