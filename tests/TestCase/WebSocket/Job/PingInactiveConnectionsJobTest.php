<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase\WebSocket\Job;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Job\PingInactiveConnectionsJob;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use React\EventLoop\LoopInterface;

class PingInactiveConnectionsJobTest extends TestCase
{
    use VerifiesDoubles;

    public function testRunPingsInactiveConnection(): void
    {
        $connection = Double::for(Connection::class);
        $server = Double::for(Server::class);
        $appManager = Double::for(ApplicationManager::class);
        $loop = Double::for(LoopInterface::class);

        $app = [
            'id' => 'app-id',
            'ping_interval' => 30,
        ];

        $server->allows('getConnections')->returns([$connection]);
        $server->expects('getAppIdForConnection')
            ->with($connection)
            ->returns('app-id');
        $server->allows('getApplicationManager')->returns($appManager);
        $appManager->expects('getApplication')
            ->with('app-id')
            ->returns($app);

        $connection->allows('getPingState')->returns([
            'last_ping_time' => null,
            'last_pong_time' => null,
            'pending_pings' => 0,
            'ping_count' => 0,
        ]);
        $connection->allows('getLastActivity')->returns(microtime(true) - 40);

        $connection->expects('ping')->times(2);

        $job = new PingInactiveConnectionsJob($loop, $server);
        $job->run();
    }

    public function testRunDoesNotPingActiveConnection(): void
    {
        $connection = Double::for(Connection::class);
        $server = Double::for(Server::class);
        $appManager = Double::for(ApplicationManager::class);
        $loop = Double::for(LoopInterface::class);

        $app = [
            'id' => 'app-id',
            'ping_interval' => 30,
        ];

        $server->allows('getConnections')->returns([$connection]);
        $server->expects('getAppIdForConnection')
            ->with($connection)
            ->returns('app-id');
        $server->allows('getApplicationManager')->returns($appManager);
        $appManager->expects('getApplication')
            ->with('app-id')
            ->returns($app);

        $connection->allows('getPingState')->returns([
            'last_ping_time' => null,
            'last_pong_time' => null,
            'pending_pings' => 0,
            'ping_count' => 0,
        ]);
        $connection->allows('getLastActivity')->returns(microtime(true) - 10);

        $connection->expects('ping')->never();

        $job = new PingInactiveConnectionsJob($loop, $server);
        $job->run();
    }

    public function testRunDoesNotPingWhileWaitingForPong(): void
    {
        $connection = Double::for(Connection::class);
        $server = Double::for(Server::class);
        $loop = Double::for(LoopInterface::class);

        $server->expects('getConnections')
            ->returns([$connection]);
        $connection->allows('getPingState')->returns([
            'last_ping_time' => microtime(true) - 10,
            'last_pong_time' => null,
            'pending_pings' => 2,
            'ping_count' => 2,
        ]);

        $connection->expects('ping')->never();

        $job = new PingInactiveConnectionsJob($loop, $server);
        $job->run();
    }
}
