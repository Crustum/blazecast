<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Channel;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Pusher\Channel\PusherPresenceChannel;

/**
 * Testable version of PusherPresenceChannel that overrides problematic methods
 */
class TestablePresenceChannel extends PusherPresenceChannel
{
    /**
     * Override to prevent broadcasting which causes issues in tests
     *
     * @param array<string, mixed> $userData User data
     * @param \Crustum\BlazeCast\WebSocket\Connection $except Connection to exclude
     * @return void
     */
    protected function broadcastMemberAdded(array $userData, Connection $except): void
    {
    }

    /**
     * Override to prevent broadcasting which causes issues in tests
     *
     * @param string $userId User ID
     * @return void
     */
    protected function broadcastMemberRemoved(string $userId): void
    {
    }
}
