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
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use React\EventLoop\LoopInterface;

class PruneStaleConnectionsJobTest extends TestCase
{
    use VerifiesDoubles;

    public function testRunPrunesConnectionWaitingTooLongForPong(): void
    {
        $connection = Double::for(Connection::class);
        $server = Double::for(Server::class);
        $connectionRegistry = Double::for(ConnectionRegistry::class);
        $applicationContextResolver = Double::for(ApplicationContextResolver::class);
        $appManager = Double::for(ApplicationManager::class);
        $loop = Double::for(LoopInterface::class);

        $app = [
            'id' => 'app-id',
            'activity_timeout' => 60,
        ];

        $server->allows('getConnectionRegistry')->returns($connectionRegistry);
        $server->allows('getApplicationContextResolver')->returns($applicationContextResolver);
        $server->allows('getApplicationManager')->returns($appManager);

        $connectionRegistry->allows('getConnections')->returns([$connection]);
        $applicationContextResolver->expects('getAppIdForConnection')
            ->with($connection, [])
            ->returns('app-id');
        $appManager->expects('getApplication')
            ->with('app-id')
            ->returns($app);

        $connection->allows('getPingState')->returns([
            'last_ping_time' => microtime(true) - 90,
            'last_pong_time' => microtime(true) - 200,
            'pending_pings' => 1,
            'ping_count' => 1,
        ]);
        $connection->expects('send');
        $connection->expects('close');

        $job = new PruneStaleConnectionsJob($loop, $server);
        $job->run();
    }

    public function testRunDoesNotPruneWhenPongAlreadyReceived(): void
    {
        $connection = Double::for(Connection::class);
        $server = Double::for(Server::class);
        $connectionRegistry = Double::for(ConnectionRegistry::class);
        $applicationContextResolver = Double::for(ApplicationContextResolver::class);
        $appManager = Double::for(ApplicationManager::class);
        $loop = Double::for(LoopInterface::class);

        $app = [
            'id' => 'app-id',
            'activity_timeout' => 60,
        ];

        $server->allows('getConnectionRegistry')->returns($connectionRegistry);
        $server->allows('getApplicationContextResolver')->returns($applicationContextResolver);
        $server->allows('getApplicationManager')->returns($appManager);

        $connectionRegistry->allows('getConnections')->returns([$connection]);
        $applicationContextResolver->allows('getAppIdForConnection')
            ->with($connection, [])
            ->returns('app-id');
        $appManager->allows('getApplication')
            ->with('app-id')
            ->returns($app);

        $connection->allows('getPingState')->returns([
            'last_ping_time' => microtime(true) - 90,
            'last_pong_time' => microtime(true) - 10,
            'pending_pings' => 0,
            'ping_count' => 1,
        ]);
        $connection->expects('close')->never();

        $job = new PruneStaleConnectionsJob($loop, $server);
        $job->run();
    }
}
