<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Support;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Support\ServerPath;

/**
 * ServerPathTest
 */
class ServerPathTest extends TestCase
{
    /**
     * Test stripping a configured path prefix for signature verification.
     *
     * @return void
     */
    public function testStripPrefix(): void
    {
        $config = ['path' => '/ws'];

        $this->assertSame('/apps/1/events', ServerPath::strip('/ws/apps/1/events', $config));
        $this->assertSame('/apps/1/events', ServerPath::strip('/apps/1/events', $config));
        $this->assertSame('/', ServerPath::strip('/ws', $config));
    }

    /**
     * Test empty path leaves URI unchanged.
     *
     * @return void
     */
    public function testEmptyPrefix(): void
    {
        $this->assertSame('/apps/1/events', ServerPath::strip('/apps/1/events', ['path' => '']));
        $this->assertSame('', ServerPath::prefix(['path' => '']));
        $this->assertSame('/ws', ServerPath::prefix(['path' => 'ws']));
    }
}
