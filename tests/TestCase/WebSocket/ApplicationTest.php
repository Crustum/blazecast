<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket;

use Crustum\BlazeCast\WebSocket\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WebSocket Application
 */
class ApplicationTest extends TestCase
{
    #[Test]
    public function applicationCanBeCreatedWithBasicConfig(): void
    {
        $app = new Application(
            'test_app',
            'test_key',
            'test_secret',
            30,
            120,
            ['*'],
            10000,
        );

        $this->assertInstanceOf(Application::class, $app);
        $this->assertEquals('test_app', $app->getId());
        $this->assertEquals('test_key', $app->getKey());
        $this->assertEquals('test_secret', $app->getSecret());
        $this->assertEquals(30, $app->getPingInterval());
        $this->assertEquals(120, $app->getActivityTimeout());
        $this->assertEquals(['*'], $app->getAllowedOrigins());
        $this->assertEquals(10000, $app->getMaxMessageSize());
    }

    #[Test]
    public function applicationCanBeConvertedToArray(): void
    {
        $app = new Application(
            'test_app',
            'test_key',
            'test_secret',
            30,
            120,
            ['localhost', '127.0.0.1'],
            5000,
        );

        $expected = [
            'app_id' => 'test_app',
            'key' => 'test_key',
            'secret' => 'test_secret',
            'ping_interval' => 30,
            'activity_timeout' => 120,
            'allowed_origins' => ['localhost', '127.0.0.1'],
            'max_message_size' => 5000,
            'options' => [],
        ];

        $this->assertEquals($expected, $app->toArray());
    }

    #[Test]
    public function applicationHandlesOptionsCorrectly(): void
    {
        $options = ['debug' => true, 'custom_setting' => 'value'];

        $app = new Application(
            'test_app',
            'test_key',
            'test_secret',
            30,
            120,
            ['*'],
            10000,
            $options,
        );

        $this->assertEquals($options, $app->getOptions());

        $array = $app->toArray();
        $this->assertEquals($options, $array['options']);
    }

    #[Test]
    public function applicationValidatesBasicRequirements(): void
    {
        $configs = [
            ['app1', 'key1', 'secret1', 10, 60, ['*'], 1000],
            ['app2', 'key2', 'secret2', 60, 300, ['localhost'], 50000],
            ['app3', 'key3', 'secret3', 5, 30, ['*'], 100000],
        ];

        foreach ($configs as $config) {
            $app = new Application(...$config);
            $this->assertInstanceOf(Application::class, $app);
        }
    }
}
