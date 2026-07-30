<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket\Job;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Job\PingInactiveConnectionsJob;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use React\EventLoop\LoopInterface;

class PingInactiveConnectionsJobTest extends TestCase
{
    public function testRunPingsInactiveConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $server = $this->createMock(Server::class);
        $appManager = $this->createMock(ApplicationManager::class);
        $loop = $this->createStub(LoopInterface::class);

        $app = [
            'id' => 'app-id',
            'ping_interval' => 30,
        ];

        $server->method('getConnections')->willReturn([$connection]);
        $server->method('getAppIdForConnection')->with($connection)->willReturn('app-id');
        $server->method('getApplicationManager')->willReturn($appManager);
        $appManager->method('getApplication')->with('app-id')->willReturn($app);

        $connection->method('getPingState')->willReturn([
            'last_ping_time' => null,
            'last_pong_time' => null,
            'pending_pings' => 0,
            'ping_count' => 0,
        ]);
        $connection->method('getLastActivity')->willReturn(microtime(true) - 40);

        $connection->expects($this->exactly(2))->method('ping');

        $job = new PingInactiveConnectionsJob($loop, $server);
        $job->run();
    }

    public function testRunDoesNotPingActiveConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $server = $this->createMock(Server::class);
        $appManager = $this->createMock(ApplicationManager::class);
        $loop = $this->createStub(LoopInterface::class);

        $app = [
            'id' => 'app-id',
            'ping_interval' => 30,
        ];

        $server->method('getConnections')->willReturn([$connection]);
        $server->method('getAppIdForConnection')->with($connection)->willReturn('app-id');
        $server->method('getApplicationManager')->willReturn($appManager);
        $appManager->method('getApplication')->with('app-id')->willReturn($app);

        $connection->method('getPingState')->willReturn([
            'last_ping_time' => null,
            'last_pong_time' => null,
            'pending_pings' => 0,
            'ping_count' => 0,
        ]);
        $connection->method('getLastActivity')->willReturn(microtime(true) - 10);

        $connection->expects($this->never())->method('ping');

        $job = new PingInactiveConnectionsJob($loop, $server);
        $job->run();
    }

    public function testRunDoesNotPingWhileWaitingForPong(): void
    {
        $connection = $this->createMock(Connection::class);
        $server = $this->createMock(Server::class);
        $loop = $this->createStub(LoopInterface::class);

        $server->method('getConnections')->willReturn([$connection]);
        $connection->method('getPingState')->willReturn([
            'last_ping_time' => microtime(true) - 10,
            'last_pong_time' => null,
            'pending_pings' => 2,
            'ping_count' => 2,
        ]);

        $connection->expects($this->never())->method('ping');

        $job = new PingInactiveConnectionsJob($loop, $server);
        $job->run();
    }
}
