<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Redis;

use Cake\Core\Configure;
use Crustum\BlazeCast\WebSocket\Redis\PubSub;
use Crustum\BlazeCast\WebSocket\Redis\PubSubFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for PubSubFactory
 */
class PubSubFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new ReflectionClass(PubSubFactory::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setValue(null, null);

        Configure::delete('BlazeCast.redis');
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(PubSubFactory::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setValue(null, null);

        Configure::delete('BlazeCast.redis');
    }

    #[Test]
    public function getInstanceReturnsSameInstanceOnMultipleCalls(): void
    {
        $instance1 = PubSubFactory::getInstance();
        $instance2 = PubSubFactory::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(PubSub::class, $instance1);
    }

    #[Test]
    public function getInstanceCreatesInstanceWithDefaultConfiguration(): void
    {
        $instance = PubSubFactory::getInstance();

        $this->assertInstanceOf(PubSub::class, $instance);
    }

    #[Test]
    public function getInstanceUsesConfiguredRedisSettings(): void
    {
        $redisConfig = [
            'host' => '192.168.1.100',
            'port' => 6380,
            'database' => 2,
            'password' => 'secret',
        ];

        Configure::write('BlazeCast.redis', $redisConfig);

        $instance = PubSubFactory::getInstance();

        $this->assertInstanceOf(PubSub::class, $instance);
    }

    #[Test]
    public function setInstanceStoresProvidedInstance(): void
    {
        /** @var PubSub&\PHPUnit\Framework\MockObject\Stub $mockPubSub */
        $mockPubSub = $this->createStub(PubSub::class);

        PubSubFactory::setInstance($mockPubSub);

        $retrievedInstance = PubSubFactory::getInstance();

        $this->assertSame($mockPubSub, $retrievedInstance);
    }

    #[Test]
    public function setInstanceOverridesPreviousInstance(): void
    {
        $firstInstance = PubSubFactory::getInstance();

        /** @var PubSub&\PHPUnit\Framework\MockObject\Stub $mockPubSub */
        $mockPubSub = $this->createStub(PubSub::class);
        PubSubFactory::setInstance($mockPubSub);

        // Get instance again
        $secondInstance = PubSubFactory::getInstance();

        $this->assertNotSame($firstInstance, $secondInstance);
        $this->assertSame($mockPubSub, $secondInstance);
    }

    #[Test]
    public function createInstanceUsesDefaultConfigurationWhenNoneProvided(): void
    {
        Configure::delete('BlazeCast.redis');

        $instance = PubSubFactory::getInstance();

        $this->assertInstanceOf(PubSub::class, $instance);
    }

    #[Test]
    public function createInstanceHandlesEmptyConfiguration(): void
    {
        Configure::write('BlazeCast.redis', []);

        $instance = PubSubFactory::getInstance();

        $this->assertInstanceOf(PubSub::class, $instance);
    }

    #[Test]
    public function createInstanceHandlesNullConfiguration(): void
    {
        Configure::write('BlazeCast.redis', null);

        $instance = PubSubFactory::getInstance();

        $this->assertInstanceOf(PubSub::class, $instance);
    }

    #[Test]
    public function factoryCreatesInstanceWithProperDependencies(): void
    {
        $instance = PubSubFactory::getInstance();

        $reflection = new ReflectionClass($instance);

        $this->assertTrue($reflection->hasProperty('loop'));
        $this->assertTrue($reflection->hasProperty('server'));
        $this->assertTrue($reflection->hasProperty('client'));
    }

    #[Test]
    public function multipleCallsToGetInstanceReturnSameObject(): void
    {
        $instances = [];

        for ($i = 0; $i < 5; $i++) {
            $instances[] = PubSubFactory::getInstance();
        }

        $firstInstance = $instances[0];
        foreach ($instances as $instance) {
            $this->assertSame($firstInstance, $instance);
        }
    }

    #[Test]
    public function factoryHandlesComplexRedisConfiguration(): void
    {
        $complexConfig = [
            'uri' => 'redis://user:password@redis.example.com:6379/0',
            'options' => [
                'database' => 1,
                'timeout' => 30,
                'read_timeout' => 60,
                'persistent' => true,
            ],
            'parameters' => [
                'password' => 'complex-password',
                'database' => 2,
            ],
        ];

        Configure::write('BlazeCast.redis', $complexConfig);

        $instance = PubSubFactory::getInstance();

        $this->assertInstanceOf(PubSub::class, $instance);
    }
}
