<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use Crustum\BlazeCast\WebSocket\Pusher\ServerFactory;
use Exception;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * ServerStartCommand
 *
 * Command to start the unified Pusher server that handles both HTTP and WebSocket
 */
class ServerStartCommand extends Command
{
    /**
     * Server host from command line
     *
     * @var string
     */
    protected ?string $serverHost = '0.0.0.0';

    /**
     * Server port from command line
     *
     * @var int|null
     */
    protected ?int $serverPort = null;

    /**
     * Application manager (optional, injected via DI)
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager|null
     */
    protected ?ApplicationManager $applicationManager = null;

    /**
     * Channel connection manager (optional, injected via DI)
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager|null
     */
    protected ?ChannelConnectionManager $channelConnectionManager = null;

    /**
     * Container instance (optional, injected via DI)
     *
     * @var \Cake\Core\ContainerInterface|null
     */
    protected ?ContainerInterface $container = null;

    /**
     * Constructor - dependencies are optionally injected
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager|null $applicationManager Application manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager|null $channelConnectionManager Connection manager
     * @param \Cake\Core\ContainerInterface|null $container Container instance
     */
    public function __construct(
        ?ApplicationManager $applicationManager = null,
        ?ChannelConnectionManager $channelConnectionManager = null,
        ?ContainerInterface $container = null,
    ) {
        parent::__construct();
        $this->applicationManager = $applicationManager;
        $this->channelConnectionManager = $channelConnectionManager;
        $this->container = $container;
    }

    /**
     * Build the option parser
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to update
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Start the unified Pusher server that handles both HTTP API and WebSocket connections')
            ->addOption('host', [
                'short' => 'H',
                'default' => '0.0.0.0',
                'help' => 'The host/IP address to bind the server to',
            ])
            ->addOption('port', [
                'short' => 'p',
                'help' => 'The port to listen on',
            ])
            ->addOption('debug', [
                'short' => 'd',
                'boolean' => true,
                'help' => 'Enable debug mode with detailed logging',
            ])

            ->addOption('max-request-size', [
                'default' => 10000,
                'help' => 'Maximum HTTP request size in bytes',
            ])
            ->addOption('app-id', [
                'help' => 'Pusher application ID',
                'default' => 'app-id',
            ])
            ->addOption('app-key', [
                'help' => 'Pusher application key',
                'default' => 'app-key',
            ])
            ->addOption('app-secret', [
                'help' => 'Pusher application secret',
                'default' => 'app-secret',
            ]);

        return $parser;
    }

    /**
     * Execute the command
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $this->setServerHost($args);
        $this->setServerPort($args);

        $debug = $args->getOption('debug');
        $maxRequestSize = (int)$args->getOption('max-request-size');

        $io->out('<info>Starting Pusher Unified Server</info>');
        $io->out(sprintf('   Host: %s', $this->getServerHost()));
        $io->out(sprintf('   Port: %d', $this->getServerPort()));
        $io->out(sprintf('   Debug: %s', $debug ? 'enabled' : 'disabled'));
        $io->out(sprintf('   Max Request Size: %d bytes', $maxRequestSize));
        $io->out('');

        $blazecastConfig = Configure::read('BlazeCast', []);

        $config = array_merge($blazecastConfig, [
            'max_request_size' => $maxRequestSize,
            'debug' => $debug,
            'log_level' => $debug ? 'debug' : 'info',
            'app_id' => (string)$args->getOption('app-id'),
            'app_key' => (string)$args->getOption('app-key'),
            'app_secret' => (string)$args->getOption('app-secret'),
        ]);

        try {
            if ($this->applicationManager) {
                $io->out('<info>Using ApplicationManager from DI container</info>');
            }
            if ($this->channelConnectionManager) {
                $io->out('<info>Using ChannelConnectionManager from DI container</info>');
            }

            $server = ServerFactory::create(
                $this->getServerHost(),
                $this->getServerPort(),
                $config,
                null,
                $this->applicationManager,
                $this->channelConnectionManager,
                $this->container,
            );
            $io->out('<success>Server created</success>');

            $io->out('');
            $io->out('<info>Server Capabilities:</info>');
            $io->out('   HTTP API endpoints (Pusher REST API)');
            $io->out('   WebSocket connections (Real-time)');
            $io->out('   Automatic protocol detection');
            $io->out('   HMAC authentication');
            $io->out('   CORS support');
            $io->out('');

            $this->displayRegisteredApplications($io, $server);

            $io->out('<info>Available Endpoints:</info>');
            $io->out(sprintf('   HTTP API: http://%s:%d/', $this->getServerHost(), $this->getServerPort()));
            $io->out(sprintf('   WebSocket: ws://%s:%d/app/{APP_KEY}', $this->getServerHost(), $this->getServerPort()));
            $io->out('');

            $this->displayRegisteredRoutes($io, $server);
            $io->out('');

            $io->out('Press Ctrl+C to stop the server');
            $io->out('');

            $this->logServerStart($this->getServerHost(), $this->getServerPort());
            $server->start();
        } catch (RuntimeException $e) {
            $io->error('Failed to start server: ' . $e->getMessage());

            if ($debug) {
                $io->out('');
                $io->out('<error>Debug trace:</error>');
                $io->out($e->getTraceAsString());
            }

            return static::CODE_ERROR;
        } catch (Throwable $e) {
            $io->error('Failed to start server: ' . $e->getMessage());

            if ($debug) {
                $io->out('');
                $io->out('<error>Debug trace:</error>');
                $io->out($e->getTraceAsString());
            }

            BlazeCastLogger::error(sprintf('Failed to start Blazecast Server %s\n%s', $e->getMessage(), $e->getTraceAsString()), [
                'scope' => ['command.server', 'command.server.start'],
            ]);

            return static::CODE_ERROR;
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Log server start
     *
     * @param string $host Host
     * @param int $port Port
     * @return void
     */
    protected function logServerStart(string $host, int $port): void
    {
        $timestamp = date('Y-m-d H:i:s');
        BlazeCastLogger::info(sprintf('Starting Websocket Server. host=%s, port=%d, pid=%d, timestamp=%s', $host, $port, getmypid(), $timestamp), [
            'scope' => ['command.server', 'command.server.start'],
        ]);
    }

    /**
     * Get the server host with command-line priority over config
     *
     * @return string
     */
    protected function getServerHost(): string
    {
        $config = Configure::read('BlazeCast.servers.blazecast', []);

        return $this->serverHost ?: ($config['host'] ?? '0.0.0.0');
    }

    /**
     * Set the server host with command-line priority over config
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @return void
     */
    protected function setServerHost(Arguments $args): void
    {
        $config = Configure::read('BlazeCast.servers.blazecast', []);
        $host = $args->getOption('host') ?: null;

        $this->serverHost = $host ?: ($config['host'] ?? '0.0.0.0');
    }

    /**
     * Get the server port with command-line priority over config
     *
     * @return int
     */
    protected function getServerPort(): int
    {
        return $this->serverPort;
    }

    /**
     * Set the server port with command-line priority over config
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @return void
     */
    protected function setServerPort(Arguments $args): void
    {
        $config = Configure::read('BlazeCast.servers.blazecast', []);
        $port = (int)$args->getOption('port') ?: null;

        $this->serverPort = $port ?: ($config['port'] ?? 8080);
    }

    /**
     * Display registered applications
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Server $server Unified server
     * @return void
     */
    protected function displayRegisteredApplications(ConsoleIo $io, Server $server): void
    {
        $io->out('<info>Registered Applications:</info>');

        try {
            $router = $server->getHttpRouter();

            $reflection = new ReflectionClass($router);
            $factoryProperty = $reflection->getProperty('controllerFactory');
            $factoryProperty->setAccessible(true);
            $controllerFactory = $factoryProperty->getValue($router);

            if ($controllerFactory) {
                $applicationManager = $controllerFactory->getApplicationManager();
                $applications = $applicationManager->getApplications();

                if (empty($applications)) {
                    $io->out('   No applications registered!');
                    $io->out(sprintf(
                        '   ApplicationManager has %d apps',
                        $applicationManager->getApplicationCount(),
                    ));
                } else {
                    foreach ($applications as $app) {
                        $io->out(sprintf('   App ID: %s', $app['id']));
                        $io->out(sprintf('   Key: %s', $app['key']));
                        $io->out(sprintf('   Secret: %s...', substr($app['secret'], 0, 8)));
                        $io->out(sprintf('   Name: %s', $app['name']));
                        $io->out(sprintf('   WebSocket: ws://%s:%d/app/%s', $this->getServerHost(), $this->getServerPort(), $app['key']));
                        $io->out(sprintf('   HTTP API: http://%s:%d/apps/%s/', $this->getServerHost(), $this->getServerPort(), $app['id']));
                        $io->out('');
                    }
                }
            } else {
                $io->out('   No controller factory found!');
            }
        } catch (Exception $e) {
            $io->out(sprintf('   Could not retrieve applications: %s', $e->getMessage()));
        }

        $io->out('');
    }

    /**
     * Display registered routes
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Server $server Unified server
     * @return void
     */
    protected function displayRegisteredRoutes(ConsoleIo $io, Server $server): void
    {
        $io->out('<info>HTTP API Routes:</info>');

        try {
            $router = $server->getHttpRouter();

            $reflection = new ReflectionClass($router);
            $routesMethod = $reflection->getMethod('getAvailableRoutes');
            $routesMethod->setAccessible(true);
            $routes = $routesMethod->invoke($router);

            if (empty($routes)) {
                $io->out('   No routes registered!');
            } else {
                foreach ($routes as $route) {
                    $method = isset($route['methods']) ? implode('|', $route['methods']) : 'GET';
                    $path = $route['path'] ?? 'unknown';
                    $name = $route['name'] ?? '';

                    if ($name) {
                        $io->out(sprintf('   %s %s (%s)', $method, $path, $name));
                    } else {
                        $io->out(sprintf('   %s %s', $method, $path));
                    }
                }
            }
        } catch (Exception $e) {
            print_r($e->getMessage());
            print_r($e->getTraceAsString());
            $io->out(sprintf('   Could not retrieve routes: %s', $e->getMessage()));
        }
    }
}
