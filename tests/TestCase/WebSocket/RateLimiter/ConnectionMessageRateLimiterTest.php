<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\RateLimiter;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\RateLimiter\ConnectionMessageRateLimiter;

/**
 * ConnectionMessageRateLimiterTest
 */
class ConnectionMessageRateLimiterTest extends TestCase
{
    /**
     * @return void
     */
    public function testAllowsMessagesUpToLimit(): void
    {
        $limiter = new ConnectionMessageRateLimiter(3, false);

        $this->assertFalse($limiter->consume('conn-1')->isExceeded());
        $this->assertFalse($limiter->consume('conn-1')->isExceeded());
        $this->assertFalse($limiter->consume('conn-1')->isExceeded());
        $this->assertTrue($limiter->consume('conn-1')->isExceeded());
    }

    /**
     * @return void
     */
    public function testLimitsArePerConnection(): void
    {
        $limiter = new ConnectionMessageRateLimiter(1, false);

        $this->assertFalse($limiter->consume('conn-a')->isExceeded());
        $this->assertTrue($limiter->consume('conn-a')->isExceeded());
        $this->assertFalse($limiter->consume('conn-b')->isExceeded());
    }

    /**
     * @return void
     */
    public function testAppOverrideChangesMaxMessages(): void
    {
        $limiter = new ConnectionMessageRateLimiter(10, false, [
            'app-1' => ['max_messages_per_second' => 1],
        ]);

        $this->assertFalse($limiter->consume('conn-1', 'app-1')->isExceeded());
        $this->assertTrue($limiter->consume('conn-1', 'app-1')->isExceeded());
    }

    /**
     * @return void
     */
    public function testShouldTerminateOnLimitUsesDefaultsAndOverrides(): void
    {
        $limiter = new ConnectionMessageRateLimiter(60, true, [
            'app-soft' => ['terminate_on_limit' => false],
        ]);

        $this->assertTrue($limiter->shouldTerminateOnLimit(null));
        $this->assertFalse($limiter->shouldTerminateOnLimit('app-soft'));
    }

    /**
     * @return void
     */
    public function testForgetRemovesConnectionState(): void
    {
        $limiter = new ConnectionMessageRateLimiter(1, false);

        $this->assertFalse($limiter->consume('conn-1')->isExceeded());
        $this->assertTrue($limiter->consume('conn-1')->isExceeded());

        $limiter->forget('conn-1');

        $this->assertFalse($limiter->consume('conn-1')->isExceeded());
    }
}
