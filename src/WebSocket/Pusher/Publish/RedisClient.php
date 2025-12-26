<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Publish;

use Cake\Log\Log;
use Clue\React\Redis\Client;
use Exception;
use React\EventLoop\LoopInterface;
use Throwable;

/**
 * Base Redis client with connection management
 *
 * @phpstan-import-type RedisConfig from \Crustum\BlazeCast\WebSocket\Redis\ClientFactory
 */
class RedisClient
{
    /**
     * Redis connection client
     *
     * @var \Clue\React\Redis\Client|null
     */
    protected ?Client $client = null;

    /**
     * The name of the Redis connection
     *
     * @var string
     */
    protected string $name = 'redis';

    /**
     * Determine if the client should attempt to reconnect when disconnected
     *
     * @var bool
     */
    protected bool $shouldRetry = true;

    /**
     * Number of seconds elapsed since attempting to reconnect
     *
     * @var int
     */
    protected int $retryTimer = 0;

    /**
     * Create a new instance of the Redis client
     *
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @param string $channel Channel name
     * @param RedisConfig $server Server configuration
     * @param callable|null $onConnect Connection callback
     */
    public function __construct(
        protected LoopInterface $loop,
        protected string $channel,
        protected array $server = [],
        protected $onConnect = null,
    ) {
    }

    /**
     * Create a new connection to the Redis server
     *
     * @return void
     */
    public function connect(): void
    {
        $factory = new RedisClientFactory();
        $factory->make($this->loop, $this->redisUrl())->then(
            fn(Client $client) => $this->onConnection($client),
            fn(Throwable $exception) => $this->onFailedConnection($exception),
        );
    }

    /**
     * Attempt to reconnect to the Redis server
     *
     * @return void
     */
    public function reconnect(): void
    {
        if (!$this->shouldRetry) {
            return;
        }

        $this->loop->addTimer(1, fn() => $this->attemptReconnection());
    }

    /**
     * Disconnect from the Redis server
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->shouldRetry = false;

        $this->client?->close();
    }

    /**
     * Listen for a given event
     *
     * @param string $event Event name
     * @param callable $callback Event callback
     * @return void
     */
    public function on(string $event, callable $callback): void
    {
        if ($this->isConnected()) {
            $this->client->on($event, $callback);
        } else {
            Log::error('RedisClient is not connected to Redis');
        }
    }

    /**
     * Determine if the client is currently connected to the server
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->client instanceof Client;
    }

    /**
     * Handle a connection failure to the Redis server
     *
     * @return void
     */
    protected function configureClientErrorHandler(): void
    {
        $this->client->on('close', function (): void {
            $this->client = null;

            Log::info('Disconnected from Redis', "<fg=red>{$this->name}</>");

            $this->reconnect();
        });
    }

    /**
     * Handle a successful connection to the Redis server
     *
     * @param \Clue\React\Redis\Client $client Redis client
     * @return void
     */
    protected function onConnection(Client $client): void
    {
        $this->client = $client;

        $this->resetRetryTimer();
        $this->configureClientErrorHandler();

        if ($this->onConnect) {
            call_user_func($this->onConnect, $client);
        }

        Log::info('Redis connection established', "<fg=green>{$this->name}</>");
    }

    /**
     * Handle a failed connection to the Redis server
     *
     * @param \Throwable $exception Connection exception
     * @return void
     */
    protected function onFailedConnection(Throwable $exception): void
    {
        $this->client = null;

        Log::error($exception->getMessage());

        $this->reconnect();
    }

    /**
     * Attempt to reconnect to the Redis server until timeout is reached
     *
     * @return void
     */
    protected function attemptReconnection(): void
    {
        $this->retryTimer++;

        if ($this->retryTimer >= $this->retryTimeout()) {
            $message = "Failed to connect to Redis after {$this->retryTimeout()} seconds";
            Log::error($message);

            throw new Exception($message);
        }

        Log::info('Attempting reconnection to Redis', "<fg=yellow>{$this->name}</>");

        $this->connect();
    }

    /**
     * Determine the configured reconnection timeout
     *
     * @return int Timeout in seconds
     */
    protected function retryTimeout(): int
    {
        return (int)($this->server['timeout'] ?? 60);
    }

    /**
     * Reset the retry connection timer
     *
     * @return void
     */
    protected function resetRetryTimer(): void
    {
        $this->retryTimer = 0;
    }

    /**
     * Get the connection URL for Redis
     *
     * @return string Redis connection URL
     */
    protected function redisUrl(): string
    {
        $config = empty($this->server) ? $this->getDefaultConfig() : $this->server;

        $parsed = $this->parseConfiguration($config);

        $driver = strtolower($parsed['driver'] ?? '');

        if (in_array($driver, ['tcp', 'tls'])) {
            $parsed['scheme'] = $driver;
        }

        [$host, $port, $protocol, $query] = [
            $parsed['host'],
            $parsed['port'] ?: 6379,
            ($parsed['scheme'] ?? '') === 'tls' ? 's' : '',
            [],
        ];

        if ($parsed['username'] ?? false) {
            $query['username'] = $parsed['username'];
        }

        if ($parsed['password'] ?? false) {
            $query['password'] = $parsed['password'];
        }

        if ($parsed['database'] ?? false) {
            $query['db'] = $parsed['database'];
        }

        $query = http_build_query($query);

        return "redis{$protocol}://{$host}:{$port}" . ($query ? "?{$query}" : '');
    }

    /**
     * Get default Redis configuration
     *
     * @return RedisConfig Default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
        ];
    }

    /**
     * Parse configuration array or URL
     *
     * @param RedisConfig|string $config Configuration
     * @return RedisConfig Parsed configuration
     */
    protected function parseConfiguration(array|string $config): array
    {
        if (is_string($config)) {
            return $this->parseUrl($config);
        }

        return $config;
    }

    /**
     * Parse Redis URL
     *
     * @param string $url Redis URL
     * @return RedisConfig Parsed configuration
     */
    protected function parseUrl(string $url): array
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            throw new Exception("Invalid Redis URL: {$url}");
        }

        $config = [
            'host' => $parsed['host'] ?? '127.0.0.1',
            'port' => $parsed['port'] ?? 6379,
        ];

        if (isset($parsed['user'])) {
            $config['username'] = $parsed['user'];
        }

        if (isset($parsed['pass'])) {
            $config['password'] = $parsed['pass'];
        }

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
            if (isset($query['db'])) {
                $config['database'] = (int)$query['db'];
            }
        }

        return $config;
    }
}
