<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket\Job;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\ApplicationContextResolver;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\ConnectionRegistry;
use Crustum\BlazeCast\WebSocket\Job\PruneStaleConnectionsJob;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use React\EventLoop\LoopInterface;

class PruneStaleConnectionsJobTest extends TestCase
{
    public function testRunPrunesStaleConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $server = $this->createStub(Server::class);
        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $applicationContextResolver = $this->createStub(ApplicationContextResolver::class);
        $appManager = $this->createStub(ApplicationManager::class);
        $loop = $this->createStub(LoopInterface::class);

        $app = [
            'id' => 'app-id',
            'activity_timeout' => 60,
        ];

        $server->method('getConnectionRegistry')->willReturn($connectionRegistry);
        $server->method('getApplicationContextResolver')->willReturn($applicationContextResolver);
        $server->method('getApplicationManager')->willReturn($appManager);

        $connectionRegistry->method('getConnections')->willReturn([$connection]);
        $applicationContextResolver->method('getAppIdForConnection')->with($connection, [])->willReturn('app-id');
        $appManager->method('getApplication')->with('app-id')->willReturn($app);

        $connection->expects($this->once())
            ->method('isStale')
            ->with(60)
            ->willReturn(true);

        $connection->expects($this->once())->method('close');

        $job = new PruneStaleConnectionsJob($loop, $server);
        $job->run();
    }

    public function testRunDoesNotPruneActiveConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $server = $this->createStub(Server::class);
        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $applicationContextResolver = $this->createStub(ApplicationContextResolver::class);
        $appManager = $this->createStub(ApplicationManager::class);
        $loop = $this->createStub(LoopInterface::class);

        $app = [
            'id' => 'app-id',
            'activity_timeout' => 60,
        ];

        $server->method('getConnectionRegistry')->willReturn($connectionRegistry);
        $server->method('getApplicationContextResolver')->willReturn($applicationContextResolver);
        $server->method('getApplicationManager')->willReturn($appManager);

        $connectionRegistry->method('getConnections')->willReturn([$connection]);
        $applicationContextResolver->method('getAppIdForConnection')->with($connection, [])->willReturn('app-id');
        $appManager->method('getApplication')->with('app-id')->willReturn($app);

        $connection->expects($this->once())
            ->method('isStale')
            ->with(60)
            ->willReturn(false);

        $connection->expects($this->never())->method('close');

        $job = new PruneStaleConnectionsJob($loop, $server);
        $job->run();
    }
}
