<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Command;

use Cake\Command\Command;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleOptionParser;
use Cake\Container\ContainerInterface;
use Cake\Core\Configure;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Server;
use Crustum\BlazeCast\WebSocket\Pusher\ServerFactory;
use Exception;
use Override;
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
     * Get container instance from factory
     *
     * @return \Cake\Container\ContainerInterface|null
     */
    protected function getContainer(): ?ContainerInterface
    {
        if ($this->factory instanceof CommandFactoryInterface) {
            $reflection = new ReflectionClass($this->factory);
            if ($reflection->hasProperty('container')) {
                $property = $reflection->getProperty('container');

                return $property->getValue($this->factory);
            }
        }

        return null;
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
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Start the unified Pusher server that handles both HTTP API and WebSocket connections';
    }

    /**
     * Execute the command
     *
     * @return int|null
     */
    public function execute(): ?int
    {
        $this->setServerHost();
        $this->setServerPort();
        $debug = $this->args->getOption('debug');
        $maxRequestSize = (int)$this->args->getOption('max-request-size');
        $this->io->out('<info>Starting Pusher Unified Server</info>');
        $this->io->out(sprintf('   Host: %s', $this->getServerHost()));
        $this->io->out(sprintf('   Port: %d', $this->getServerPort()));
        $this->io->out(sprintf('   Debug: %s', $debug ? 'enabled' : 'disabled'));
        $this->io->out(sprintf('   Max Request Size: %d bytes', $maxRequestSize));
        $this->io->out('');

        $blazecastConfig = Configure::read('BlazeCast', []);
        $config = array_merge($blazecastConfig, [
            'max_request_size' => $maxRequestSize,
            'debug' => $debug,
            'log_level' => $debug ? 'debug' : 'info',
            'app_id' => (string)$this->args->getOption('app-id'),
            'app_key' => (string)$this->args->getOption('app-key'),
            'app_secret' => (string)$this->args->getOption('app-secret'),
        ]);
        try {
            $applicationManager = null;
            $connectionManager = null;

            $container = $this->getContainer();
            if ($container instanceof ContainerInterface) {
                if ($container->has(ApplicationManager::class)) {
                    $applicationManager = $container->get(ApplicationManager::class);
                    $this->io->out('<info>Using ApplicationManager from container</info>');
                } else {
                    $this->io->out('<error>ApplicationManager not found in container</error>');
                }

                if ($container->has(ChannelConnectionManager::class)) {
                    $connectionManager = $container->get(ChannelConnectionManager::class);
                    $this->io->out('<info>Using ChannelConnectionManager from container</info>');
                } else {
                    $this->io->out('<error>ChannelConnectionManager not found in container</error>');
                }
            }

            $server = ServerFactory::create(
                $this->getServerHost(),
                $this->getServerPort(),
                $config,
                null,
                $applicationManager,
                $connectionManager,
                $container,
            );
            $this->io->out('<success>Server created</success>');

            $this->io->out('');
            $this->io->out('<info>Server Capabilities:</info>');
            $this->io->out('   HTTP API endpoints (Pusher REST API)');
            $this->io->out('   WebSocket connections (Real-time)');
            $this->io->out('   Automatic protocol detection');
            $this->io->out('   HMAC authentication');
            $this->io->out('   CORS support');
            $this->io->out('');

            $this->displayRegisteredApplications($server);

            $this->io->out('<info>Available Endpoints:</info>');
            $this->io->out(sprintf('   HTTP API: http://%s:%d/', $this->getServerHost(), $this->getServerPort()));
            $this->io->out(sprintf('   WebSocket: ws://%s:%d/app/{APP_KEY}', $this->getServerHost(), $this->getServerPort()));
            $this->io->out('');

            $this->displayRegisteredRoutes($server);
            $this->io->out('');

            $this->io->out('Press Ctrl+C to stop the server');
            $this->io->out('');

            $this->logServerStart($this->getServerHost(), $this->getServerPort());
            $server->start();
        } catch (RuntimeException $e) {
            $this->io->error('Failed to start server: ' . $e->getMessage());

            if ($debug) {
                $this->io->out('');
                $this->io->out('<error>Debug trace:</error>');
                $this->io->out($e->getTraceAsString());
            }

            return static::CODE_ERROR;
        } catch (Throwable $e) {
            $this->io->error('Failed to start server: ' . $e->getMessage());

            if ($debug) {
                $this->io->out('');
                $this->io->out('<error>Debug trace:</error>');
                $this->io->out($e->getTraceAsString());
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
     * @return void
     */
    protected function setServerHost(): void
    {
        $config = Configure::read('BlazeCast.servers.blazecast', []);
        $host = $this->args->getOption('host') ?: null;
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
     * @return void
     */
    protected function setServerPort(): void
    {
        $config = Configure::read('BlazeCast.servers.blazecast', []);
        $portOption = $this->args->getOption('port');
        $port = is_numeric($portOption) ? (int)$portOption : null;
        $configPort = isset($config['port']) ? (int)$config['port'] : 8080;
        $this->serverPort = $port ?? $configPort;
    }

    /**
     * Display registered applications
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Server $server Unified server
     * @return void
     */
    protected function displayRegisteredApplications(Server $server): void
    {
        $this->io->out('<info>Registered Applications:</info>');

        try {
            $router = $server->getHttpRouter();

            $reflection = new ReflectionClass($router);
            $factoryProperty = $reflection->getProperty('controllerFactory');
            $controllerFactory = $factoryProperty->getValue($router);

            if ($controllerFactory) {
                $applicationManager = $controllerFactory->getApplicationManager();
                $applications = $applicationManager->getApplications();

                if (empty($applications)) {
                    $this->io->out('   No applications registered!');
                    $this->io->out(sprintf(
                        '   ApplicationManager has %d apps',
                        $applicationManager->getApplicationCount(),
                    ));
                } else {
                    foreach ($applications as $app) {
                        $this->io->out(sprintf('   App ID: %s', $app['id']));
                        $this->io->out(sprintf('   Key: %s', $app['key']));
                        $this->io->out(sprintf('   Secret: %s...', substr($app['secret'], 0, 8)));
                        $this->io->out(sprintf('   Name: %s', $app['name']));
                        $this->io->out(sprintf('   WebSocket: ws://%s:%d/app/%s', $this->getServerHost(), $this->getServerPort(), $app['key']));
                        $this->io->out(sprintf('   HTTP API: http://%s:%d/apps/%s/', $this->getServerHost(), $this->getServerPort(), $app['id']));
                        $this->io->out('');
                    }
                }
            } else {
                $this->io->out('   No controller factory found!');
            }
        } catch (Exception $exception) {
            $this->io->out(sprintf('   Could not retrieve applications: %s', $exception->getMessage()));
        }

        $this->io->out('');
    }

    /**
     * Display registered routes
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Server $server Unified server
     * @return void
     */
    protected function displayRegisteredRoutes(Server $server): void
    {
        $this->io->out('<info>HTTP API Routes:</info>');

        try {
            $router = $server->getHttpRouter();

            $reflection = new ReflectionClass($router);
            $routesMethod = $reflection->getMethod('getAvailableRoutes');
            $routes = $routesMethod->invoke($router);

            if (empty($routes)) {
                $this->io->out('   No routes registered!');
            } else {
                foreach ($routes as $route) {
                    $method = isset($route['methods']) ? implode('|', $route['methods']) : 'GET';
                    $path = $route['path'] ?? 'unknown';
                    $name = $route['name'] ?? '';

                    if ($name) {
                        $this->io->out(sprintf('   %s %s (%s)', $method, $path, $name));
                    } else {
                        $this->io->out(sprintf('   %s %s', $method, $path));
                    }
                }
            }
        } catch (Exception $exception) {
            $this->io->out(sprintf('   Could not retrieve routes: %s', $exception->getMessage()));
        }
    }
}
