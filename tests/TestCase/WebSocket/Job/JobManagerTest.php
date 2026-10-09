<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket\Job;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Job\JobInterface;
use Crustum\BlazeCast\WebSocket\Job\JobManager;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

class JobManagerTest extends TestCase
{
    use VerifiesDoubles;

    public function testRegisterAndGetJob(): void
    {
        $jobManager = new JobManager();
        $job = Double::for(JobInterface::class);

        $jobManager->register('test_job', $job);

        $this->assertSame($job, $jobManager->getJob('test_job'));
        $this->assertCount(1, $jobManager->getAllJobs());
    }

    public function testStartAllJobs(): void
    {
        $jobManager = new JobManager();
        $job1 = Double::for(JobInterface::class);
        $job2 = Double::for(JobInterface::class);

        $job1->expects('start');
        $job2->expects('start');

        $jobManager->register('job1', $job1);
        $jobManager->register('job2', $job2);

        $jobManager->startAll();
    }

    public function testStopAllJobs(): void
    {
        $jobManager = new JobManager();
        $job1 = Double::for(JobInterface::class);
        $job2 = Double::for(JobInterface::class);

        $job1->expects('stop');
        $job2->expects('stop');

        $jobManager->register('job1', $job1);
        $jobManager->register('job2', $job2);

        $jobManager->stopAll();
    }
}
