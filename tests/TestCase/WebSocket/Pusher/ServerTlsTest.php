<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Http\PusherRouter;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use React\EventLoop\Loop;
use React\Socket\SecureServer;
use React\Socket\ServerInterface;
use React\Socket\TcpServer;
use ReflectionClass;
use ReflectionMethod;

/**
 * Server TLS options tests
 *
 * Covers user-provided certificate path options and plain TCP when TLS is unset.
 * Uses a placeholder cert path (file need not exist for bind/context assertions).
 */
class ServerTlsTest extends TestCase
{
    protected PusherRouter $router;

    protected ChannelManager $channelManager;

    protected ChannelConnectionManager $connectionManager;

    protected ApplicationManager $applicationManager;

    /**
     * @var array<\Crustum\BlazeCast\WebSocket\Pusher\Server>
     */
    protected array $servers = [];

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->router = $this->createStub(PusherRouter::class);
        $this->channelManager = new ChannelManager();
        $this->connectionManager = new ChannelConnectionManager();
        $this->applicationManager = new ApplicationManager([
            'applications' => [
                [
                    'id' => '1',
                    'key' => 'test-key',
                    'secret' => 'test-secret',
                    'name' => 'Test App',
                ],
            ],
        ]);
    }

    /**
     * Tear down and close any bound sockets
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->servers as $server) {
            $reflection = new ReflectionClass($server);
            if ($reflection->hasProperty('socket')) {
                $socketProperty = $reflection->getProperty('socket');
                $socket = $socketProperty->getValue($server);
                if ($socket instanceof ServerInterface) {
                    $socket->close();
                }
            }
        }

        $this->servers = [];

        parent::tearDown();
    }

    /**
     * Test buildSocketOptions keeps user-provided TLS context.
     *
     * @return void
     */
    public function testBuildSocketOptionsIncludesUserCertificate(): void
    {
        $server = $this->createServerInTestMode();
        $method = $this->accessibleMethod($server, 'buildSocketOptions');

        $options = $method->invoke($server, [
            'servers' => [
                'blazecast' => [
                    'options' => [
                        'tls' => [
                            'local_cert' => '/path/to/cert.pem',
                            'verify_peer' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('/path/to/cert.pem', $options['tls']['local_cert']);
        $this->assertFalse($options['tls']['verify_peer']);
    }

    /**
     * Test null TLS context values are filtered out.
     *
     * @return void
     */
    public function testBuildSocketOptionsFiltersNullTlsValues(): void
    {
        $server = $this->createServerInTestMode();
        $method = $this->accessibleMethod($server, 'buildSocketOptions');

        $options = $method->invoke($server, [
            'servers' => [
                'blazecast' => [
                    'options' => [
                        'tls' => [
                            'local_cert' => null,
                            'verify_peer' => null,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertArrayNotHasKey('tls', $options);
    }

    /**
     * Test usesTls detects local_cert.
     *
     * @return void
     */
    public function testUsesTlsWhenLocalCertProvided(): void
    {
        $server = $this->createServerInTestMode();
        $method = $this->accessibleMethod($server, 'usesTls');

        $this->assertTrue($method->invoke($server, [
            'tls' => ['local_cert' => '/path/to/cert.pem'],
        ]));
        $this->assertFalse($method->invoke($server, [
            'tls' => [],
        ]));
    }

    /**
     * Test creating a TLS server with a user-provided certificate path.
     *
     * @return void
     */
    public function testCanCreateTlsServerUsingUserProvidedCertificate(): void
    {
        $port = $this->findAvailablePort();
        $server = $this->createBoundServer($port, [
            'servers' => [
                'blazecast' => [
                    'options' => [
                        'tls' => [
                            'local_cert' => '/path/to/cert.pem',
                            'verify_peer' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $socketServer = $this->resolveInnerSocketServer($server);
        $context = $this->resolveSocketContext($socketServer);

        $this->assertInstanceOf(SecureServer::class, $socketServer);
        $this->assertSame('/path/to/cert.pem', $context['local_cert']);
        $this->assertFalse($context['verify_peer']);
    }

    /**
     * Test TLS server binds as tls://host:port.
     *
     * @return void
     */
    public function testCanCreateTlsServerOnGivenHostAndPort(): void
    {
        $port = $this->findAvailablePort();
        $server = $this->createBoundServer($port, [
            'servers' => [
                'blazecast' => [
                    'options' => [
                        'tls' => [
                            'local_cert' => '/path/to/cert.pem',
                            'verify_peer' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $socketServer = $this->resolveInnerSocketServer($server);

        $this->assertInstanceOf(SecureServer::class, $socketServer);
        $this->assertSame("tls://127.0.0.1:{$port}", $socketServer->getAddress());
    }

    /**
     * Test server stays plain TCP when TLS context values are null.
     *
     * @return void
     */
    public function testCanCreateServerWithoutTlsWhenContextValuesAreNull(): void
    {
        $port = $this->findAvailablePort();
        $server = $this->createBoundServer($port, [
            'servers' => [
                'blazecast' => [
                    'options' => [
                        'tls' => [
                            'local_cert' => null,
                            'verify_peer' => null,
                        ],
                    ],
                ],
            ],
        ]);

        $socketServer = $this->resolveInnerSocketServer($server);

        $this->assertInstanceOf(TcpServer::class, $socketServer);
        $this->assertSame("tcp://127.0.0.1:{$port}", $socketServer->getAddress());
    }

    /**
     * Create a server in test mode (no bind).
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Server
     */
    protected function createServerInTestMode(): Server
    {
        return new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            38090,
            ['test_mode' => true],
            Loop::get(),
        );
    }

    /**
     * Create a bound server and track it for tearDown.
     *
     * @param int $port Port
     * @param array<string, mixed> $config Server config
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Server
     */
    protected function createBoundServer(int $port, array $config): Server
    {
        $server = new Server(
            $this->router,
            $this->channelManager,
            $this->connectionManager,
            $this->applicationManager,
            '127.0.0.1',
            $port,
            $config,
            Loop::get(),
        );
        $this->servers[] = $server;

        return $server;
    }

    /**
     * Make a protected method accessible.
     *
     * @param object $object Object
     * @param string $methodName Method name
     * @return \ReflectionMethod
     */
    protected function accessibleMethod(object $object, string $methodName): ReflectionMethod
    {
        return new ReflectionMethod($object, $methodName);
    }

    /**
     * Resolve the inner React socket server (TcpServer or SecureServer).
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Server $server Server
     * @return \React\Socket\ServerInterface
     */
    protected function resolveInnerSocketServer(Server $server): ServerInterface
    {
        $reflection = new ReflectionClass($server);
        $socketProperty = $reflection->getProperty('socket');
        $socket = $socketProperty->getValue($server);
        $this->assertInstanceOf(ServerInterface::class, $socket);

        $socketReflection = new ReflectionClass($socket);
        if ($socketReflection->hasProperty('server')) {
            $inner = $socketReflection->getProperty('server')->getValue($socket);
            $this->assertInstanceOf(ServerInterface::class, $inner);

            return $inner;
        }

        return $socket;
    }

    /**
     * Resolve TLS context from SecureServer / TcpServer.
     *
     * @param \React\Socket\ServerInterface $socketServer Socket server
     * @return array<string, mixed>
     */
    protected function resolveSocketContext(ServerInterface $socketServer): array
    {
        $reflection = new ReflectionClass($socketServer);
        if ($reflection->hasProperty('context')) {
            $context = $reflection->getProperty('context')->getValue($socketServer);
            $this->assertIsArray($context);

            return $context;
        }

        if ($reflection->hasProperty('server')) {
            $inner = $reflection->getProperty('server')->getValue($socketServer);
            $innerReflection = new ReflectionClass($inner);
            if ($innerReflection->hasProperty('context')) {
                $context = $innerReflection->getProperty('context')->getValue($inner);
                $this->assertIsArray($context);

                return $context;
            }
        }

        $this->fail('Unable to resolve socket TLS context');
    }

    /**
     * Find an available local TCP port.
     *
     * @return int
     */
    protected function findAvailablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket);
        $name = stream_socket_get_name($socket, false);
        $this->assertNotFalse($name);
        fclose($socket);
        $parts = explode(':', $name);

        return (int)end($parts);
    }
}
