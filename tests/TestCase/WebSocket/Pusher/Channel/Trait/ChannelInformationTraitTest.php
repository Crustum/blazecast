<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Channel\Trait;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Pusher\Trait\ChannelInformationTrait;
use ReflectionClass;

/**
 * Unit tests for ChannelInformationTrait
 */
class ChannelInformationTraitTest extends TestCase
{
    /**
     * @var object
     */
    private object $traitUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->traitUser = new class {
            use ChannelInformationTrait;

            public mixed $channelManager = null;
            public mixed $connectionManager = null;

            /**
             * @param mixed $channelManager
             * @param mixed $connectionManager
             * @return void
             */
            public function setManagers(mixed $channelManager, mixed $connectionManager): void
            {
                $this->channelManager = $channelManager;
                $this->connectionManager = $connectionManager;
            }

            /**
             * @param array<string, mixed> $application
             * @param string $channelName
             * @param string $info
             * @return array<string, mixed>
             */
            public function callInfo(array $application, string $channelName, string $info = ''): array
            {
                return $this->info($application, $channelName, $info);
            }

            /**
             * @param array<string, mixed> $application
             * @param array<string> $channels
             * @param string $info
             * @return array<string, mixed>
             */
            public function callInfoForChannels(array $application, array $channels, string $info = ''): array
            {
                return $this->infoForChannels($application, $channels, $info);
            }
        };
    }

    public function testInfoReturnsUnoccupiedIfNoManagers(): void
    {
        $result = $this->traitUser->callInfo([], 'test-channel', 'user_count,subscription_count');
        $this->assertEquals([
            'occupied' => false,
            'user_count' => 0,
            'subscription_count' => 0,
        ], $result);
    }

    public function testInfoReturnsOccupied(): void
    {
        $channelManager = new class {
            public function getChannel(string $name): string
            {
                return 'presence-test';
            }
        };
        $connectionManager = new class {
            /**
             * @return array<mixed>
             */
            public function getConnectionsForChannel(string $name): array
            {
                return [
                    (object)['user_id' => '1'],
                    (object)['user_id' => '2'],
                    (object)['user_id' => '1'],
                ];
            }
        };
        $this->traitUser->setManagers($channelManager, $connectionManager);
        $result = $this->traitUser->callInfo([], 'presence-test', 'user_count,subscription_count,member_count');
        $this->assertEquals([
            'occupied' => true,
            'subscription_count' => 3,
            'user_count' => 2,
            'member_count' => 2,
        ], $result);
    }

    public function testInfoForChannels(): void
    {
        $trait = $this->traitUser;
        $trait->setManagers(
            new class {
                public function getChannel(string $name): mixed
                {
                    return $name;
                }
            },
            new class {
                /**
                 * @return array<mixed>
                 */
                public function getConnectionsForChannel(string $name): array
                {
                    return [ (object)['user_id' => $name] ];
                }
            },
        );
        $channels = ['presence-1', 'presence-2'];
        $result = $trait->callInfoForChannels([], $channels, 'user_count');
        $this->assertEquals([
            'presence-1' => ['occupied' => true, 'user_count' => 1],
            'presence-2' => ['occupied' => true, 'user_count' => 1],
        ], $result);
    }

    public function testBuildOccupiedInfo(): void
    {
        $reflection = new ReflectionClass($this->traitUser);
        $method = $reflection->getMethod('buildOccupiedInfo');
        $connections = [ (object)['user_id' => '1'], (object)['user_id' => '2'] ];
        $result = $method->invoke($this->traitUser, 'presence-test', $connections, ['user_count', 'subscription_count', 'member_count']);
        $this->assertEquals([
            'occupied' => true,
            'subscription_count' => 2,
            'user_count' => 2,
            'member_count' => 2,
        ], $result);
    }

    public function testBuildUnoccupiedInfo(): void
    {
        $reflection = new ReflectionClass($this->traitUser);
        $method = $reflection->getMethod('buildUnoccupiedInfo');
        $result = $method->invoke($this->traitUser, ['user_count', 'subscription_count', 'member_count']);
        $this->assertEquals([
            'occupied' => false,
            'user_count' => 0,
            'subscription_count' => 0,
            'member_count' => 0,
        ], $result);
    }

    public function testExtractUniqueUsers(): void
    {
        $reflection = new ReflectionClass($this->traitUser);
        $method = $reflection->getMethod('extractUniqueUsers');
        $connections = [ (object)['user_id' => '1'], (object)['user_id' => '2'], (object)['user_id' => '1'] ];
        $result = $method->invoke($this->traitUser, $connections);
        $this->assertEquals(['1', '2'], $result);
    }

    public function testExtractUserId(): void
    {
        $reflection = new ReflectionClass($this->traitUser);
        $method = $reflection->getMethod('extractUserId');
        $obj = (object)['user_id' => '42'];
        $this->assertEquals('42', $method->invoke($this->traitUser, $obj));
        $arr = ['user_id' => '99'];
        $this->assertEquals('99', $method->invoke($this->traitUser, $arr));
        $mock = new class {
            public function getAttribute(string $key): mixed
            {
                return $key === 'user_id' ? '77' : null;
            }
        };
        $this->assertEquals('77', $method->invoke($this->traitUser, $mock));
    }

    public function testParseInfoFields(): void
    {
        $reflection = new ReflectionClass($this->traitUser);
        $method = $reflection->getMethod('parseInfoFields');
        $this->assertEquals(['user_count', 'subscription_count'], $method->invoke($this->traitUser, 'user_count,subscription_count,foo'));
        $this->assertEquals([], $method->invoke($this->traitUser, ''));
    }

    public function testIsPresenceChannel(): void
    {
        $reflection = new ReflectionClass($this->traitUser);
        $method = $reflection->getMethod('isPresenceChannel');
        $this->assertTrue($method->invoke($this->traitUser, 'presence-abc'));
        $this->assertFalse($method->invoke($this->traitUser, 'private-abc'));
    }

    public function testIsPrivateChannel(): void
    {
        $reflection = new ReflectionClass($this->traitUser);
        $method = $reflection->getMethod('isPrivateChannel');
        $this->assertTrue($method->invoke($this->traitUser, 'private-abc'));
        $this->assertFalse($method->invoke($this->traitUser, 'presence-abc'));
    }

    public function testIsCacheChannel(): void
    {
        $reflection = new ReflectionClass($this->traitUser);
        $method = $reflection->getMethod('isCacheChannel');
        $this->assertTrue($method->invoke($this->traitUser, 'cache-abc'));
        $this->assertFalse($method->invoke($this->traitUser, 'private-abc'));
    }
}
