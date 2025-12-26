<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\Support;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventList;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use Crustum\BlazeCast\WebSocket\Pusher\ServerFactory;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Socket\SocketServer;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Test infrastructure helper for Server
 *
 * Provides proper server lifecycle management for integration tests
 */
class TestServer
{
    private ?Server $server = null;
    private ?LoopInterface $loop = null;
    private string $host;
    private int $port;
    /**
     * @var array<string, mixed>
     */
    private array $config;
    private bool $running = false;
    private bool $useSharedLoop;

    /**
     * Constructor
     *
     * @param array<string, mixed> $config Server configuration
     * @param bool $useSharedLoop Whether to use shared event loop
     */
    public function __construct(array $config = [], bool $useSharedLoop = false)
    {
        $this->host = $config['host'] ?? '127.0.0.1';
        $this->port = $config['port'] ?? $this->getAvailablePort();
        $this->useSharedLoop = $useSharedLoop;

        $this->config = array_merge([
            'app_id' => 'test-app-id',
            'app_key' => 'test-app-key',
            'app_secret' => 'test-app-secret',
            'allowed_origins' => ['*'],
            'ping_interval' => 30,
            'max_message_size' => 10000,
            'test_mode' => false,
            'debug' => false,
        ], $config);

        $this->setupEventLoop();
        $this->setupConfiguration();
        $this->createServer();
    }

    /**
     * Setup the event loop
     */
    private function setupEventLoop(): void
    {
        if ($this->useSharedLoop) {
            $this->loop = Loop::get();
        } else {
            $this->loop = new StreamSelectLoop();
        }
    }

    /**
     * Setup CakePHP configuration for BlazeCast
     */
    private function setupConfiguration(): void
    {
        $existingApps = Configure::read('BlazeCast.apps', []);
        $appConfig = [
            'id' => $this->config['app_id'],
            'key' => $this->config['app_key'],
            'secret' => $this->config['app_secret'],
            'allowed_origins' => $this->config['allowed_origins'],
            'ping_interval' => $this->config['ping_interval'],
            'max_message_size' => $this->config['max_message_size'],
        ];

        $existingAppIndex = null;
        if (is_array($existingApps)) {
            foreach ($existingApps as $index => $existingApp) {
                if (isset($existingApp['id']) && $existingApp['id'] === $appConfig['id']) {
                    $existingAppIndex = $index;
                    $appConfig = array_merge($existingApp, $appConfig);
                    break;
                }
            }
        }

        if ($existingAppIndex !== null && is_array($existingApps)) {
            $existingApps[$existingAppIndex] = $appConfig;
        } else {
            $existingApps[] = $appConfig;
        }

        Configure::write('BlazeCast.apps', $existingApps);
    }

    /**
     * Create the Server instance
     */
    private function createServer(): void
    {
        $applicationManager = new ApplicationManager($this->config);

        $this->server = ServerFactory::create(
            $this->host,
            $this->port,
            $this->config,
            $this->loop,
            $applicationManager,
        );

        // Enable event tracking for tests
        $this->server->getEventManager()->setEventList(new EventList());
    }

    /**
     * Start the server
     */
    public function start(): void
    {
        if ($this->running || !$this->server) {
            return;
        }

        $this->running = true;

        $socket = $this->getServerSocket();
        if (!$socket && !($this->config['test_mode'] ?? false)) {
            throw new RuntimeException('Server socket not initialized - check test_mode configuration');
        }

        if (!$socket && ($this->config['test_mode'] ?? false)) {
            $this->createTestSocket();
        }

        // Start jobs manually (normally done in server->start())
        $jobManager = $this->getJobManager();
        if ($jobManager) {
            $jobManager->startAll();
        }

        // Dispatch server started event
        $this->server->getEventManager()->dispatch(new Event('Server.started', $this->server));

        // Give the server a moment to bind to the port
        usleep(50000); // 50ms
    }

    /**
     * Create a test socket for integration tests
     */
    private function createTestSocket(): void
    {
        if (!$this->server || !$this->loop) {
            return;
        }

        $reflection = new ReflectionClass($this->server);
        $socketProperty = $reflection->getProperty('socket');

        $loopProperty = $reflection->getProperty('loop');
        $serverLoop = $loopProperty->getValue($this->server);

        $uri = "{$this->host}:{$this->port}";
        $socket = new SocketServer($uri, [], $serverLoop);
        $socket->on('connection', [$this->server, 'handleIncomingConnection']);

        $socketProperty->setValue($this->server, $socket);
    }

    /**
     * Stop the server and clean up resources
     */
    public function stop(): void
    {
        if (!$this->running || !$this->server) {
            return;
        }

        $this->running = false;

        try {
            $this->server->stop();

            // Allow some time for cleanup
            if (!$this->useSharedLoop && $this->loop) {
                // Stop the loop if we're managing our own
                $this->loop->futureTick(function () {
                    if ($this->loop && !$this->useSharedLoop) {
                        $this->loop->stop();
                    }
                });
            }
        } catch (Throwable $e) {
            // Ignore cleanup errors in tests
        }

        // Small delay to ensure cleanup
        usleep(10000); // 10ms
    }

    /**
     * Run the event loop for a specific amount of time
     */
    public function runFor(float $seconds): void
    {
        if (!$this->loop) {
            return;
        }

        $timer = $this->loop->addTimer($seconds, function () {
            if ($this->loop && !$this->useSharedLoop) {
                $this->loop->stop();
            }
        });

        try {
            if (!$this->useSharedLoop) {
                $this->loop->run();
            }
        } finally {
            $this->loop->cancelTimer($timer);
        }
    }

    /**
     * Run the event loop until manually stopped
     */
    public function run(): void
    {
        if ($this->loop && !$this->useSharedLoop) {
            $this->loop->run();
        }
    }

    /**
     * Wait for the server to be ready to accept connections
     */
    public function waitForReady(float $timeout = 5.0): bool
    {
        $startTime = microtime(true);

        while (microtime(true) - $startTime < $timeout) {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket && socket_connect($socket, $this->host, $this->port)) {
                socket_close($socket);

                return true;
            }
            if ($socket) {
                socket_close($socket);
            }
            usleep(50000); // 50ms
        }

        return false;
    }

    /**
     * Get the server instance
     */
    public function getServer(): ?Server
    {
        return $this->server;
    }

    /**
     * Get the event loop
     */
    public function getLoop(): ?LoopInterface
    {
        return $this->loop;
    }

    /**
     * Get the server host
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Get the server port
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Get the WebSocket URI for clients
     */
    public function getWebSocketUri(string $path = ''): string
    {
        $basePath = "/app/{$this->config['app_key']}";
        $fullPath = $basePath . $path;

        return "ws://{$this->host}:{$this->port}{$fullPath}";
    }

    /**
     * Check if server is running
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Get server configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get an available port for testing
     */
    private function getAvailablePort(): int
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new RuntimeException('Could not create socket');
        }

        socket_bind($socket, '127.0.0.1', 0);
        socket_getsockname($socket, $addr, $port);
        socket_close($socket);

        return $port;
    }

    /**
     * Create a test client connected to this server
     */
    public function createClient(): WebSocketTestClient
    {
        if (!$this->loop) {
            throw new RuntimeException('No event loop available');
        }

        return new WebSocketTestClient($this->loop);
    }

    /**
     * Cleanup method for tests
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Get the server socket for testing
     */
    public function getSocket(): mixed
    {
        return $this->getServerSocket();
    }

    /**
     * Access the server's socket using reflection
     */
    private function getServerSocket(): mixed
    {
        if (!$this->server) {
            return null;
        }

        $reflection = new ReflectionClass($this->server);
        $socketProperty = $reflection->getProperty('socket');

        return $socketProperty->getValue($this->server);
    }

    /**
     * Access the server's job manager using reflection
     */
    private function getJobManager(): mixed
    {
        if (!$this->server) {
            return null;
        }

        $reflection = new ReflectionClass($this->server);
        $jobManagerProperty = $reflection->getProperty('jobManager');

        return $jobManagerProperty->getValue($this->server);
    }
}
