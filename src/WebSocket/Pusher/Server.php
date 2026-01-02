<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Crustum\BlazeCast\WebSocket\ApplicationContextResolver;
use Crustum\BlazeCast\WebSocket\ChannelOperationsManager;
use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\ConnectionRegistry;
use Crustum\BlazeCast\WebSocket\Event\ConnectionEstablishedEvent;
use Crustum\BlazeCast\WebSocket\Event\MessageReceivedEvent;
use Crustum\BlazeCast\WebSocket\Event\MessageSentEvent;
use Crustum\BlazeCast\WebSocket\Handler\DefaultHandler;
use Crustum\BlazeCast\WebSocket\Handler\HandlerRegistry;
use Crustum\BlazeCast\WebSocket\Handler\PingHandler;
use Crustum\BlazeCast\WebSocket\Http\HttpRequestProcessor;
use Crustum\BlazeCast\WebSocket\Http\PusherRouter;
use Crustum\BlazeCast\WebSocket\Job\JobManager;
use Crustum\BlazeCast\WebSocket\Job\PingInactiveConnectionsJob;
use Crustum\BlazeCast\WebSocket\Job\PruneStaleConnectionsJob;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Protocol\Message as WebSocketMessage;
use Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionLimitExceeded;
use Crustum\BlazeCast\WebSocket\Pusher\Handler\PusherEventHandler;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use Crustum\BlazeCast\WebSocket\Pusher\Publish\RedisPubSubProvider;
use Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface;
use Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface;
use Crustum\BlazeCast\WebSocket\WebSocketServerInterface;
use Crustum\Rhythm\Event\SharedBeat;
use Crustum\Rhythm\Rhythm;
use Exception;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use React\Socket\SocketServer;
use RuntimeException;
use SignalHandler\Command\Trait\SignalHandlerTrait;
use Throwable;

/**
 * WebSocket Server for Pusher Protocol
 *
 * Handles WebSocket connections, message processing, and channel management.
 *
 * @phpstan-import-type ConnectionInfo from \Crustum\BlazeCast\WebSocket\ApplicationContextResolver
 * @phpstan-import-type ApplicationConfig from \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
 * @phpstan-type ServerConfig array{
 *   max_request_size?: int,
 *   test_mode?: bool
 * }
 * @phpstan-type LogOptions array<string, mixed>
 */
class Server implements WebSocketServerInterface
{
    use SignalHandlerTrait;

    /**
     * Event loop
     *
     * @var LoopInterface
     */
    protected LoopInterface $loop;

    /**
     * Socket server
     *
     * @var ServerInterface|null
     */
    protected ?ServerInterface $socket = null;

    /**
     * HTTP router
     *
     * @var PusherRouter
     */
    protected PusherRouter $httpRouter;

    /**
     * Channel manager
     *
     * @var ChannelManager
     */
    protected ChannelManager $channelManager;

    /**
     * Connection manager
     *
     * @var ChannelConnectionManager
     */
    /**
     * @var ChannelConnectionManager
     */
    protected ChannelConnectionManager $connectionManager;

    /**
     * Application manager
     *
     * @var ApplicationManager
     */
    protected ApplicationManager $applicationManager;

    /**
     * Negotiator
     *
     * @var ServerNegotiator
     */
    protected ServerNegotiator $negotiator;

    /**
     * Max request size
     *
     * @var int
     */
    protected int $maxRequestSize = 10000;

    /**
     * Active connections
     *
     * @var array<string, ConnectionInfo>
     */
    protected array $activeConnections = [];

    /**
     * Handler registry
     *
     * @var HandlerRegistry
     */
    protected HandlerRegistry $handlerRegistry;

    /**
     * Job manager
     *
     * @var JobManager
     */
    protected JobManager $jobManager;

    /**
     * Event manager
     *
     * @var EventManager
     */
    protected EventManager $eventManager;

    /**
     * HTTP request processor
     *
     * @var HttpRequestProcessor
     */
    protected HttpRequestProcessor $httpRequestProcessor;

    /**
     * Connection registry
     *
     * @var ConnectionRegistry
     */
    protected ConnectionRegistry $connectionRegistry;

    /**
     * Application context resolver
     *
     * @var ApplicationContextResolver
     */
    protected ApplicationContextResolver $applicationContextResolver;

    /**
     * Channel operations manager
     *
     * @var ChannelOperationsManager
     */
    protected ChannelOperationsManager $channelOperationsManager;

    /**
     * Container
     *
     * @var ContainerInterface|null
     */
    protected ?ContainerInterface $container;

    /**
     * Is running
     *
     * @var bool
     */

    protected bool $isRunning = true;

    /**
     * Rhythm
     *
     * @var \Crustum\Rhythm\Rhythm|null
     */
    protected ?Rhythm $rhythm = null;

    /**
     * Verbose
     *
     * @var bool
     */
    protected bool $verbose = false;

    /**
     * Server configuration
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * Rate limiter
     *
     * @var \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|\Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface|null
     */
    protected RateLimiterInterface|AsyncRateLimiterInterface|null $rateLimiter = null;

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Http\PusherRouter $httpRouter HTTP router
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager $channelManager Channel manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager $connectionManager Connection manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param string $host Server host
     * @param int $port Server port
     * @param ServerConfig $config Server configuration
     * @param \React\EventLoop\LoopInterface|null $loop Event loop
     * @param \Cake\Core\ContainerInterface|null $container Container for Rhythm integration
     * @param \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|\Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface|null $rateLimiter Rate limiter
     */
    public function __construct(
        PusherRouter $httpRouter,
        ChannelManager $channelManager,
        ChannelConnectionManager $connectionManager,
        ApplicationManager $applicationManager,
        string $host = '0.0.0.0',
        int $port = 8080,
        array $config = [],
        ?LoopInterface $loop = null,
        ?ContainerInterface $container = null,
        RateLimiterInterface|AsyncRateLimiterInterface|null $rateLimiter = null,
    ) {
        Configure::load('Crustum/BlazeCast.rhythm');
        $this->config = $config;

        $this->httpRouter = $httpRouter;
        $this->channelManager = $channelManager;
        $this->connectionManager = $connectionManager;
        $this->applicationManager = $applicationManager;
        $this->loop = $loop ?: Loop::get();
        $this->maxRequestSize = $config['max_request_size'] ?? 10000;
        $this->container = $container;
        $this->rateLimiter = $rateLimiter;

        $uri = "{$host}:{$port}";
        if (!($config['test_mode'] ?? false)) {
            if (!$this->isPortAvailable($host, $port)) {
                throw new RuntimeException("Port {$port} is already in use on {$host}. Cannot start WebSocket server.");
            }

            try {
                $this->socket = new SocketServer($uri, [], $this->loop);
                $this->socket->on('connection', [$this, 'handleIncomingConnection']);
            } catch (RuntimeException $e) {
                if (strpos($e->getMessage(), 'Address already in use') !== false) {
                    throw new RuntimeException("Port {$port} is already in use on {$host}. Cannot start WebSocket server.", 0, $e);
                }
                throw $e;
            }
        }

        $this->negotiator = new ServerNegotiator(
            new RequestVerifier(),
            new HttpFactory(),
        );

        $this->negotiator->setSupportedSubProtocols([
            'pusher-protocol-7',
            'pusher-protocol-6',
            'pusher-protocol-5',
        ]);

        $this->eventManager = EventManager::instance();

        $this->httpRequestProcessor = new HttpRequestProcessor($this->httpRouter, $this->maxRequestSize);
        $this->connectionRegistry = new ConnectionRegistry($this->connectionManager, $this->eventManager);
        $this->applicationContextResolver = new ApplicationContextResolver($this->applicationManager, $this->channelManager);
        $this->channelOperationsManager = new ChannelOperationsManager(
            $this->applicationManager,
            $this->connectionRegistry,
            $this->connectionManager,
            $this->eventManager,
            $this->applicationContextResolver,
        );

        $this->initializeHandlerRegistry();
        $this->initializeJobManager();

        BlazeCastLogger::info("BlazeCast WebSocket Server created on {$uri}", [
            'scope' => ['socket.server'],
        ]);
    }

    /**
     * Initialize handler registry with PusherEventHandler
     *
     * @return void
     */
    protected function initializeHandlerRegistry(): void
    {
        $this->handlerRegistry = new HandlerRegistry();
        $this->handlerRegistry->setServer($this);

        $pusherHandler = new PusherEventHandler();
        $pusherHandler->setServer($this);
        $this->handlerRegistry->register($pusherHandler);

        $pingHandler = new PingHandler();
        $pingHandler->setServer($this);
        $this->handlerRegistry->register($pingHandler);

        $defaultHandler = new DefaultHandler();
        $defaultHandler->setServer($this);
        $this->handlerRegistry->register($defaultHandler);

        $this->log('info', sprintf('Handler registry initialized with PusherEventHandler priority. Handlers: %s', implode(', ', ['PusherEventHandler', 'PingHandler', 'DefaultHandler'])), [
            'scope' => ['socket.server'],
        ]);
    }

    /**
     * Initialize job manager and register jobs
     *
     * @return void
     */
    protected function initializeJobManager(): void
    {
        $this->jobManager = new JobManager();

        $jobIntervals = $this->config['job_intervals'] ?? [
            'ping' => 30,
            'prune' => 120,
        ];

        $pingJob = new PingInactiveConnectionsJob($this->loop, $this, $jobIntervals['ping']);
        $this->jobManager->register('ping', $pingJob);

        $pruneJob = new PruneStaleConnectionsJob($this->loop, $this, $jobIntervals['prune']);
        $this->jobManager->register('prune', $pruneJob);

        $this->log('info', __('Job manager initialized with configured jobs. Jobs: {0}, Intervals: {1}', implode(', ', ['ping', 'prune']), json_encode($jobIntervals)), [
            'scope' => ['socket.server'],
        ]);
    }

    /**
     * Get channel manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager
     */
    public function getChannelManager(): ChannelManager
    {
        return $this->channelManager;
    }

    /**
     * Get connection manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager
     */
    public function getConnectionManager(): ChannelConnectionManager
    {
        return $this->connectionManager;
    }

    /**
     * Get application manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    public function getApplicationManager(): ApplicationManager
    {
        return $this->applicationManager;
    }

    /**
     * Get rate limiter
     *
     * @return \Crustum\BlazeCast\WebSocket\RateLimiter\RateLimiterInterface|\Crustum\BlazeCast\WebSocket\RateLimiter\AsyncRateLimiterInterface|null
     */
    public function getRateLimiter(): RateLimiterInterface|AsyncRateLimiterInterface|null
    {
        return $this->rateLimiter;
    }

    /**
     * Get application-specific ChannelManager for a connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager|null
     */
    protected function getChannelManagerForConnection(Connection $connection): ?ChannelManager
    {
        return $this->applicationContextResolver->getChannelManagerForConnection($connection, $this->activeConnections);
    }

    /**
     * Get application ID for a connection
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @return string|null Application ID or null if not found
     */
    public function getAppIdForConnection(Connection $connection): ?string
    {
        return $this->applicationContextResolver->getAppIdForConnection($connection, $this->activeConnections);
    }

    /**
     * Start the unified server
     *
     * @return void
     */
    public function start(): void
    {
        if (!$this->socket) {
            BlazeCastLogger::warning(__('Server: Cannot start server - socket not initialized (test mode?)'), [
                'scope' => ['socket.server'],
            ]);

            return;
        }

        $this->initializePubSub();
        $this->jobManager->startAll();
        $this->ensureRhythmEventsAreCollected();
        $this->setupPrometheusMetricsListeners();

        $this->bindGracefulTermination(function (): void {
            $this->isRunning = false;
        });

        $this->loop->addPeriodicTimer(1, function (): void {
            if (!$this->isRunning) {
                $this->log('info', __('Server: Graceful termination...'), [
                    'scope' => ['socket.server'],
                ]);

                $this->stop();
            }
        });

        $this->ensureRestartCommandIsRespected();

        $this->loop->addTimer(0, function (): void {
            $this->getEventManager()->dispatch(new Event('Server.started', $this));

            $this->log('info', __('Server: Websocket Server started'), [
                'scope' => ['socket.server'],
            ]);
        });

        $this->loop->run();
    }

    /**
     * Initialize Redis PubSub if enabled.
     *
     * @return void
     */
    protected function initializePubSub(): void
    {
        $serverConfig = $this->config['servers']['blazecast'] ?? [];
        $scalingConfig = $serverConfig['scaling'] ?? [];
        $redisEnabled = $scalingConfig['enabled'] ?? false;

        if (!$redisEnabled) {
            $this->log('info', __('Server: Redis PubSub scaling disabled'), [
                'scope' => ['socket.server', 'socket.server.redis'],
            ]);

            return;
        }

        try {
            $pubSubProvider = new RedisPubSubProvider(
                $scalingConfig['channel'] ?? 'blazecast:broadcast',
                $scalingConfig['server'] ?? [],
                null,
            );

            $pubSubProvider->connect($this->loop);

            $this->log('info', __('Server: Redis PubSub initialized for horizontal scaling on channel {0}', $scalingConfig['channel'] ?? 'blazecast:broadcast'), [
                'scope' => ['socket.server', 'socket.server.redis'],
            ]);
        } catch (Throwable $e) {
            $this->log('error', __('Server: Failed to initialize Redis PubSub: {0}', $e->getMessage()), [
                'scope' => ['socket.server', 'socket.server.redis'],
            ]);
        }
    }

    /**
     * Schedule Rhythm to ingest events if enabled.
     *
     * @return void
     */
    protected function ensureRhythmEventsAreCollected(): void
    {
        $this->initializeRhythm();
        if (!$this->rhythm) {
            $this->log('info', __('Server: Rhythm not available, skipping ingestion scheduling'), [
                'scope' => ['socket.server', 'socket.server.rhythm'],
            ]);

            return;
        }

        $interval = 1;
        $this->loop->addPeriodicTimer($interval, function (): void {
            try {
                $this->eventManager->dispatch(new SharedBeat(DateTime::now(), gethostname()));
                $this->ingestRhythmMetrics();
            } catch (Exception $e) {
                debug($e);
            }
        });

        $this->log('info', __('Server: Rhythm ingestion scheduled (interval: {0}s)', $interval), [
            'scope' => ['socket.server', 'socket.server.rhythm'],
        ]);
    }

    /**
     * Initialize Rhythm for metrics collection
     *
     * @return void
     */
    protected function initializeRhythm(): void
    {
        if (!$this->container || !$this->container->has(Rhythm::class)) {
            $this->log('info', __('Server: Rhythm not available in container, skipping initialization'), [
                'scope' => ['socket.server', 'socket.server.rhythm'],
            ]);

            return;
        }

        $this->rhythm = $this->container->get(Rhythm::class);
        $this->rhythm->clearRecorders();
        $recordersToInit = Configure::read('Rhythm');
        $recordersToInit = [
            'recorders' => [
                'Blazecast.messages' => [
                    'className' => 'Crustum\BlazeCast\Recorder\BlazeCastMessagesRecorder',
                    'enabled' => true,
                    'sample_rate' => 1,
                  ],
                  'Blazecast.connections' => [
                    'className' => 'Crustum\BlazeCast\Recorder\BlazeCastConnectionsRecorder',
                    'enabled' => true,
                    'sample_rate' => 1,
                    'throttle_seconds' => 15,
                  ],
            ],
        ];

        // @phpstan-ignore-next-line
        if (!is_array($recordersToInit)) {
            BlazeCastLogger::error(__('Server: Rhythm configuration is not an array. Type: {0}, Value: {1}', gettype($recordersToInit), (string)json_encode($recordersToInit)), [
                'scope' => ['socket.server', 'socket.server.rhythm'],
            ]);

            return;
        }

        $recorders = $this->rhythm->getRecorders();
        // @phpstan-ignore-next-line
        if (empty($recorders) && !empty($recordersToInit['recorders'])) {
            $recorders = array_filter($recordersToInit['recorders'], function ($recorder) {
                return str_starts_with($recorder['className'], 'BlazeCast');
            });
            $this->rhythm->register($recorders);
        }

        $this->log('info', 'Rhythm initialized for metrics collection', [
            'scope' => ['socket.server', 'socket.server.rhythm'],
        ]);
    }

    /**
     * Check to see whether the restart signal has been sent.
     *
     * @return void
     */
    protected function ensureRestartCommandIsRespected(): void
    {
        $cacheKey = 'blazecast:server:restart';
        $lastRestart = Cache::read($cacheKey);

        $this->loop->addPeriodicTimer(5, function () use ($cacheKey, $lastRestart): void {
            if ($lastRestart === Cache::read($cacheKey)) {
                return;
            }

            $this->log('info', __('Server: Restart signal received, gracefully disconnecting...'), [
                'scope' => ['socket.server', 'socket.server.restart'],
            ]);

            $this->stop();

            $this->log('info', __('Server: Restart signal processed, server stopping'), [
                'scope' => ['socket.server', 'socket.server.restart'],
            ]);
        });
    }

    /**
     * Ingest Rhythm metrics
     *
     * @return int Number of ingested entries
     */
    protected function ingestRhythmMetrics(): int
    {
        if (!$this->rhythm) {
            return 0;
        }

        try {
            $recorders = $this->rhythm->getRecorders();
            if (empty($recorders)) {
                $this->log('info', __('Server: No recorders registered in Rhythm, skipping ingestion'), [
                    'scope' => ['socket.server.rhythm'],
                ]);

                return 0;
            }

            $ingestedCount = $this->rhythm->ingest();

            if ($ingestedCount > 0) {
                $this->log('info', __('Server: Rhythm ingested {0} entries', $ingestedCount), [
                    'scope' => ['socket.server.rhythm'],
                ]);
            }

            return $ingestedCount;
        } catch (Exception $e) {
            BlazeCastLogger::error(__('Server: Failed to ingest Rhythm events: {0}', $e->getMessage()), [
                'scope' => ['socket.server', 'socket.server.rhythm'],
            ]);

            return 0;
        }
    }

    /**
     * Stop the unified server
     *
     * @return void
     */
    public function stop(): void
    {
        $this->jobManager->stopAll();

        $connections = $this->connectionRegistry->getConnections();
        foreach ($connections as $connection) {
            if ($connection->isConnected()) {
                $connection->close();
            }
        }

        $this->connectionRegistry->clear();
        BlazeCastLogger::debug(__('Server: All connections gracefully disconnected'), [
            'scope' => ['socket.server', 'socket.server.disconnect'],
        ]);

        if ($this->socket) {
            $this->socket->close();
            $this->socket = null;
        }

        $this->loop->stop();

        $this->ingestRhythmMetrics();

        BlazeCastLogger::debug(__('Server: Pusher Unified Server stopped'), [
            'scope' => ['socket.server'],
        ]);
    }

    /**
     * Handle incoming TCP connection
     *
     * @param \React\Socket\ConnectionInterface $tcpConnection Raw TCP connection
     * @return void
     */
    public function handleIncomingConnection(ConnectionInterface $tcpConnection): void
    {
        $connection = new Connection($tcpConnection);

        BlazeCastLogger::debug(__('Server: New TCP connection received, connection_id: {0}', $connection->getId()), [
            'scope' => ['socket.server'],
        ]);

        $tcpConnection->on('data', function (string $data) use ($connection): void {
            $this->handleIncomingData($data, $connection);
        });

        $tcpConnection->on('close', function () use ($connection): void {
            $connectionId = $connection->getId();

            if (isset($this->activeConnections[$connectionId])) {
                $this->handleConnectionDisconnect($connection);
            }

            if (isset($this->connections[$connectionId])) {
                unset($this->connections[$connectionId]);
            }

            if (isset($this->activeConnections[$connectionId])) {
                unset($this->activeConnections[$connectionId]);
            }

            BlazeCastLogger::debug(__('Server: TCP connection closed and cleaned up, connectionId: {0}', $connectionId), [
                'scope' => ['socket.server'],
            ]);
        });
    }

    /**
     * Handle WebSocket connection disconnect
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection that disconnected
     * @return void
     */
    protected function handleConnectionDisconnect(Connection $connection): void
    {
        $this->connectionRegistry->handleConnectionDisconnect($connection, function ($conn, $channelName): void {
            $this->unsubscribeFromChannel($conn, $channelName);
        });
    }

    /**
     * Handle incoming HTTP/WebSocket data
     *
     * @param string $data Raw TCP data
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Unified connection
     * @return void
     */
    protected function handleIncomingData(string $data, Connection $connection): void
    {
        $connection->updateActivity();

        if ($connection->isConnected()) {
            $this->handleWebSocketData($data, $connection);

            return;
        }

        $connection->appendToBuffer($data);

        if ($connection->getBufferLength() > $this->maxRequestSize) {
            $this->closeConnection($connection, 413, 'Request Entity Too Large');

            return;
        }

        if (!$this->httpRequestProcessor->isCompleteHttpRequest($connection->getBuffer())) {
            return;
        }

        try {
            $request = $this->httpRequestProcessor->parseHttpRequest($connection->getBuffer());
            $connection->clearBuffer();

            if ($this->isWebSocketUpgradeRequest($request)) {
                $this->handleWebSocketUpgrade($request, $connection);
            } else {
                $this->httpRequestProcessor->handleHttpRequest($request, $connection);
            }
        } catch (Throwable $e) {
            BlazeCastLogger::error(__('Server: Failed to parse HTTP request on connection {0}: {1}', $connection->getId(), $e->getMessage()), [
                'scope' => ['socket.server'],
            ]);
            $this->closeConnection($connection, 400, 'Bad Request');
        }
    }

    /**
     * Check if request is WebSocket upgrade
     *
     * @param \Psr\Http\Message\RequestInterface $request PSR-7 request
     * @return bool
     */
    protected function isWebSocketUpgradeRequest(RequestInterface $request): bool
    {
        $upgrade = $request->getHeaderLine('Upgrade');

        return strtolower($upgrade) === 'websocket';
    }

    /**
     * Handle WebSocket upgrade
     *
     * @param \Psr\Http\Message\RequestInterface $request PSR-7 request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Unified connection
     * @return void
     */
    protected function handleWebSocketUpgrade(RequestInterface $request, Connection $connection): void
    {
        $path = $request->getUri()->getPath();

        BlazeCastLogger::debug(__('Server: WebSocket upgrade requested on path {0} for connection {1}', $path, $connection->getId()), [
            'scope' => ['socket.server'],
        ]);

        try {
            $appContext = $this->extractAppContextFromPath($path);
            if (!$appContext) {
                throw new InvalidArgumentException('Invalid WebSocket path format, expected /app/{appKey}');
            }

            if (isset($appContext['app_id'])) {
                $connection->setAttribute('app_id', $appContext['app_id']);
                $this->ensureWithinConnectionLimit($appContext['app_id']);
            }

            $response = $this->negotiator->handshake($request)
                ->withHeader('X-Powered-By', 'CakePHP BlazeCast');

            $connection->send(Message::toString($response));

            $connection->markAsConnected();

            $this->connectionRegistry->register($connection, [
                'request' => $request,
                'app_context' => $appContext,
                'upgraded_at' => time(),
            ]);

            $this->activeConnections[$connection->getId()] = [
                'connection' => $connection,
                'request' => $request,
                'app_context' => $appContext,
                'upgraded_at' => time(),
            ];

            BlazeCastLogger::debug(__('Server: WebSocket connection established with app context {0} for connection {1}', json_encode($appContext), $connection->getId()), [
                'scope' => ['socket.server'],
            ]);

            $this->createWebSocketConnection($connection);

            $this->sendPusherConnectionEstablished($connection);

            $event = new ConnectionEstablishedEvent($connection);
            $this->getEventManager()->dispatch($event);

            $appId = $connection->getAttribute('app_id');
            if ($appId) {
                $this->connectionManager->recordNewConnection($appId);
            }
        } catch (ConnectionLimitExceeded $e) {
            BlazeCastLogger::warning(__('Server: Connection limit exceeded for app {0} on connection {1}', $appContext['app_id'] ?? 'unknown', $connection->getId()), [
                'scope' => ['socket.server'],
            ]);
            $errorMessage = json_encode([
                'event' => 'pusher:error',
                'data' => json_encode([
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ]),
            ]);
            $connection->send($errorMessage);
            $this->closeConnection($connection, 1008, 'Connection Limit Exceeded');
        } catch (Throwable $e) {
            BlazeCastLogger::error(__('Server: WebSocket upgrade failed on connection {0}: {1}', $connection->getId(), $e->getMessage()), [
                'scope' => ['socket.server'],
            ]);
            $this->closeConnection($connection, 400, 'WebSocket Upgrade Failed');
        }
    }

    /**
     * Ensure the server is within the connection limit for the application
     *
     * @param string $appId Application ID
     * @return void
     * @throws \Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionLimitExceeded When connection limit is exceeded
     */
    protected function ensureWithinConnectionLimit(string $appId): void
    {
        $application = $this->applicationManager->getApplication($appId);
        if (!$application) {
            return;
        }

        $maxConnections = $application['max_connections'] ?? null;
        if ($maxConnections === null) {
            return;
        }

        $currentConnections = $this->connectionManager->getConnectionsForApp($appId);
        $connectionCount = count($currentConnections);

        if ($connectionCount >= $maxConnections) {
            throw new ConnectionLimitExceeded();
        }
    }

    /**
     * Extract application context from WebSocket path
     *
     * @param string $path WebSocket connection path
     * @return array{app_id?: string, app_key?: string}|null App context with app_id and app_key, or null if invalid
     */
    protected function extractAppContextFromPath(string $path): ?array
    {
        return $this->applicationManager->extractAppContextFromPath($path);
    }

    /**
     * Send Pusher connection established message
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Unified connection
     * @return void
     */
    protected function sendPusherConnectionEstablished(Connection $connection): void
    {
        try {
            $baseId = $connection->getId();
            $socketId = $baseId . '.' . random_int(100000, 999999);

            $this->connectionRegistry->updateConnectionId($baseId, $socketId);

            if (isset($this->activeConnections[$baseId])) {
                $connectionInfo = $this->activeConnections[$baseId];
                unset($this->activeConnections[$baseId]);
                $this->activeConnections[$socketId] = $connectionInfo;
            }

            $connection->setSocketId($socketId);
            $activityTimeout = 120;

            $welcomeData = [
                'event' => 'pusher:connection_established',
                'data' => json_encode([
                    'socket_id' => $socketId,
                    'activity_timeout' => $activityTimeout,
                ]),
            ];

            $message = json_encode($welcomeData);

            // $this->log('info', __('Server: Sending Pusher connection established message for connection {0}, socketId: {1}, message: {2}', $baseId, $socketId, $message), [
            //     'scope' => ['socket.server'],
            // ]);

            $connection->send($message);

            // $this->log('info', __('Server: Pusher connection established message sent for connection {0}, socketId: {1}, event: {2}', $baseId, $socketId, 'pusher:connection_established'), [
            //     'scope' => ['socket.server'],
            // ]);
        } catch (Exception $e) {
            BlazeCastLogger::error(__('Server: Error sending Pusher connection established message for connection {0}: {1} {2}', $connection->getId(), $e->getMessage(), $e->getTraceAsString()), [
                'scope' => ['socket.server'],
            ]);
        }
    }

    /**
     * Handle WebSocket frame data
     *
     * @param string $data WebSocket frame data
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Unified connection
     * @return void
     */
    protected function handleWebSocketData(string $data, Connection $connection): void
    {
        BlazeCastLogger::debug(__('Server: WebSocket data received for connection {0}, data length: {1}', $connection->getId(), strlen($data)), [
            'scope' => ['socket.server'],
        ]);

        $decodedData = $this->decodeWebSocketFrame($data);
        $this->getEventManager()->dispatch(new MessageReceivedEvent($connection, $decodedData ?? $data));

        $appId = $connection->getAttribute('app_id');
        if ($appId) {
            $bytes = strlen($decodedData ?? $data);
            $this->connectionManager->recordWsMessageReceived($appId, $bytes);
        }

        if ($decodedData === null) {
            if (strlen($data) >= 2 && (ord($data[0]) & 0x0F) === 0x9) {
                $connection->control(0xA);
                BlazeCastLogger::debug(__('Server: Sent pong response to ping for connection {0}', $connection->getId()), [
                    'scope' => ['socket.server'],
                ]);
            }

            return;
        }

        if (trim($decodedData) === '') {
            BlazeCastLogger::warning(__('Server: Empty WebSocket message received for connection {0}', $connection->getId()), [
                'scope' => ['socket.server'],
            ]);

            return;
        }

        try {
            $message = WebSocketMessage::fromJson($decodedData);

            // $this->log('info', __('Server: WebSocket message received for connection {0}, event: {1}, channel: {2}', $connection->getId(), $message->getEvent(), $message->getChannel()), [
            //     'scope' => ['socket.server'],
            // ]);

            $handled = $this->handlerRegistry->handle($connection, $message);
            if (!$handled) {
                $this->log('warning', sprintf('Message not handled by registry, using default handling. connection_id: %s, event: %s', $connection->getId(), $message->getEvent()), [
                    'scope' => ['socket.server'],
                ]);
                $this->handleDefaultMessage($connection, $message);
            }
        } catch (Exception $e) {
            BlazeCastLogger::error(__('Server: Error handling WebSocket on connection {0} message: {1}', $connection->getId(), $e->getMessage()), [
                'scope' => ['socket.server'],
            ]);

            $errorMessage = new WebSocketMessage('error', [
                'message' => 'Invalid message format: ' . $e->getMessage(),
                'error_type' => 'message_format_error',
            ]);

            $connection->send($errorMessage->toJson());
        }
    }

    /**
     * Create WebSocket Connection adapter for handler compatibility
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Unified connection
     * @return \Crustum\BlazeCast\WebSocket\Connection
     */
    protected function createWebSocketConnection(Connection $connection): Connection
    {
        $socketId = $connection->getSocketId() ?? $connection->getId();

        $connectionId = $connection->getId();
        if (isset($this->activeConnections[$connectionId])) {
            $this->activeConnections[$connectionId]['connection'] = $connection;
        } else {
            $this->activeConnections[$connectionId] = [
                'connection' => $connection,
                'app_context' => null,
                'upgraded_at' => time(),
            ];
        }

        BlazeCastLogger::debug(sprintf('WebSocket connection registered for broadcasting. connection_id: %s, pusher_socket_id: %s', $connectionId, $socketId), [
            'scope' => ['socket.server'],
        ]);

        return $connection;
    }

    /**
     * Decode WebSocket frame (copied from base Server class)
     *
     * @param string $data Raw WebSocket frame data
     * @return string|null Decoded payload or null for control frames
     */
    protected function decodeWebSocketFrame(string $data): ?string
    {
        if (strlen($data) < 2) {
            return null;
        }

        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);

        $opcode = $firstByte & 0x0F;
        $isMasked = (bool)($secondByte & 0x80);
        $payloadLength = $secondByte & 0x7F;

        if ($opcode === 0x8 || $opcode === 0x9 || $opcode === 0xA) {
            return null;
        }

        if ($opcode !== 0x1 && $opcode !== 0x0) {
            return null;
        }

        $offset = 2;
        if ($payloadLength === 126) {
            if (strlen($data) < 4) {
                return null;
            }
            $payloadLength = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($payloadLength === 127) {
            if (strlen($data) < 10) {
                return null;
            }
            $payloadLength = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }

        $maskingKey = '';
        if ($isMasked) {
            if (strlen($data) < $offset + 4) {
                return null;
            }
            $maskingKey = substr($data, $offset, 4);
            $offset += 4;
        }

        if (strlen($data) < $offset + $payloadLength) {
            return null;
        }

        $payload = substr($data, $offset, $payloadLength);

        if ($isMasked) {
            $unmaskedPayload = '';
            for ($i = 0; $i < $payloadLength; $i++) {
                $unmaskedPayload .= $payload[$i] ^ $maskingKey[$i % 4];
            }
            $payload = $unmaskedPayload;
        }

        return $payload;
    }

    /**
     * Handle default message types (delegated from base Server)
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message WebSocket message
     * @return void
     */
    protected function handleDefaultMessage(Connection $connection, WebSocketMessage $message): void
    {
        $eventType = $message->getEvent();

        if ($this->isProtocolEvent($eventType)) {
            return;
        }

        $response = new WebSocketMessage('message_received', [
            'original_event' => $eventType,
            'timestamp' => time(),
        ]);

        $connection->send($response->toJson());

        $this->log('info', sprintf('Default message handled. connection_id: %s, event: %s', $connection->getId(), $eventType), [
            'scope' => ['socket.server'],
        ]);
    }

    /**
     * Check if event is a protocol event that should not be echoed
     *
     * @param string $eventType Event type to check
     * @return bool True if this is a protocol event
     */
    protected function isProtocolEvent(string $eventType): bool
    {
        return str_starts_with($eventType, 'pusher:') || str_starts_with($eventType, 'pusher_internal:');
    }

    /**
     * Close connection with error response
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection to close
     * @param int $statusCode HTTP status code
     * @param string $message Error message
     * @return void
     */
    protected function closeConnection(Connection $connection, int $statusCode, string $message): void
    {
        // Record Prometheus metrics for disconnection
        $appId = $connection->getAttribute('app_id');
        if ($appId) {
            $this->connectionManager->recordDisconnection($appId);
        }

        $response = new GuzzleResponse($statusCode, [], $message);
        $connection->send(Message::toString($response));
        $connection->close();
    }

    /**
     * Get the HTTP router
     *
     * @return \Crustum\BlazeCast\WebSocket\Http\PusherRouter
     */
    public function getHttpRouter(): PusherRouter
    {
        return $this->httpRouter;
    }

    /**
     * Set the HTTP router
     *
     * @param \Crustum\BlazeCast\WebSocket\Http\PusherRouter $router HTTP router
     * @return void
     */
    public function setHttpRouter(PusherRouter $router): void
    {
        $this->httpRouter = $router;
    }

    /**
     * Subscribe connection to Pusher channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param string $channelName Channel name
     * @return void
     */
    public function subscribeToPusherChannel(Connection $connection, string $channelName): void
    {
        $this->log('info', __('Server: Subscribing connection to Pusher channel {0}', $channelName), [
            'scope' => ['socket.server'],
        ]);

        $this->subscribeToChannel($connection, $channelName);

        $connectionId = $connection->getId();

        $this->log('info', __('Server: Connection {0} subscribed to Pusher channel {1}', $connectionId, $channelName), [
            'scope' => ['socket.server'],
        ]);
    }

    /**
     * Get a connection by ID
     *
     * @param string $connectionId Connection ID
     * @return \Crustum\BlazeCast\WebSocket\Connection|null
     */
    public function getConnection(string $connectionId): ?Connection
    {
        return $this->connectionRegistry->getConnection($connectionId);
    }

    /**
     * Get all active connections
     *
     * @return array<string, \Crustum\BlazeCast\WebSocket\Connection>
     */
    public function getConnections(): array
    {
        return $this->connectionRegistry->getConnections();
    }

    /**
     * Subscribe connection to channel using application-specific ChannelManager
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $channelName Channel name
     * @return void
     */
    public function subscribeToChannel(Connection $connection, string $channelName): void
    {
        $this->channelOperationsManager->subscribeToChannel($connection, $channelName);
    }

    /**
     * Subscribe connection to channel with authentication data
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $channelName Channel name
     * @param string|null $auth Authentication token
     * @param string|null $channelData Channel data
     * @return void
     * @throws \Exception If authentication fails
     */
    public function subscribeToChannelWithAuth(Connection $connection, string $channelName, ?string $auth = null, ?string $channelData = null): void
    {
        $this->channelOperationsManager->subscribeToChannelWithAuth($connection, $channelName, $auth, $channelData);
    }

    /**
     * Unsubscribe connection from channel using application-specific ChannelManager
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string $channelName Channel name
     * @return void
     */
    public function unsubscribeFromChannel(Connection $connection, string $channelName): void
    {
        $this->channelOperationsManager->unsubscribeFromChannel($connection, $channelName);
    }

    /**
     * Get all connections subscribed to a channel across all applications
     *
     * @param string $channelName Channel name
     * @return array<\Crustum\BlazeCast\WebSocket\Connection>
     */
    public function getChannelConnections(string $channelName): array
    {
        return $this->channelOperationsManager->getChannelConnections($channelName);
    }

    /**
     * Broadcast a message to all active connections
     *
     * @param string $message Message to broadcast
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     */
    public function broadcast(string $message, ?string $exceptConnectionId = null): void
    {
        $this->channelOperationsManager->broadcast($message, $exceptConnectionId);
    }

    /**
     * Broadcast a message to all connections in a channel (DEPRECATED - use broadcastToChannelForApp)
     *
     * @param string $channelName Channel name
     * @param string $message Message to broadcast
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     * @deprecated Use broadcastToChannelForApp() for proper multi-app isolation
     */
    public function broadcastToChannel(string $channelName, string $message, ?string $exceptConnectionId = null): void
    {
        $this->channelOperationsManager->broadcastToChannel($channelName, $message, $exceptConnectionId);
    }

    /**
     * Broadcast a message to connections in a channel for a specific application
     *
     * @param string $appId Application ID
     * @param string $channelName Channel name
     * @param string $message Message to broadcast
     * @param string|null $exceptConnectionId Connection ID to exclude
     * @return void
     */
    public function broadcastToChannelForApp(string $appId, string $channelName, string $message, ?string $exceptConnectionId = null): void
    {
        $this->channelOperationsManager->broadcastToChannelForApp($appId, $channelName, $message, $exceptConnectionId);
    }

    /**
     * Get the event manager instance.
     *
     * @return \Cake\Event\EventManager
     */
    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    /**
     * Set the event manager instance.
     *
     * @param \Cake\Event\EventManager $eventManager Event manager instance
     * @return void
     */
    public function setEventManager(EventManager $eventManager): void
    {
        // $this->eventManager = $eventManager;
    }

    /**
     * Setup Prometheus metrics event listeners
     *
     * @return void
     */
    protected function setupPrometheusMetricsListeners(): void
    {
        $this->eventManager->on('BlazeCast.MessageSent', function ($event): void {
            if ($event instanceof MessageSentEvent) {
                $connection = $event->getConnection();
                $appId = $connection->getAttribute('app_id');
                if ($appId) {
                    $bytes = strlen($event->getData());
                    $this->connectionManager->recordWsMessageSent($appId, $bytes);
                }
            }
        });
    }

    /**
     * Get application context resolver
     *
     * @return \Crustum\BlazeCast\WebSocket\ApplicationContextResolver
     */
    public function getApplicationContextResolver(): ApplicationContextResolver
    {
        return $this->applicationContextResolver;
    }

    /**
     * Get channel operations manager
     *
     * @return \Crustum\BlazeCast\WebSocket\ChannelOperationsManager
     */
    public function getChannelOperationsManager(): ChannelOperationsManager
    {
        return $this->channelOperationsManager;
    }

    /**
     * Get connection registry
     *
     * @return \Crustum\BlazeCast\WebSocket\ConnectionRegistry
     */
    public function getConnectionRegistry(): ConnectionRegistry
    {
        return $this->connectionRegistry;
    }

    /**
     * Log a message
     *
     * @param string $type Log type
     * @param string $message Log message
     * @param LogOptions $options Log options
     * @return void
     */
    public function log(string $type, string $message, array $options): void
    {
        // if ($this->verbose) {
            BlazeCastLogger::write($type, $message, $options);
        // }
    }

    /**
     * Check if a port is available for binding
     *
     * @param string $host Host to check
     * @param int $port Port to check
     * @param float $timeout Timeout in seconds (default: 0.1)
     * @return bool True if port is available, false if in use
     */
    protected function isPortAvailable(string $host, int $port, float $timeout = 0.1): bool
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            return false;
        }

        $result = socket_bind($socket, $host, $port);
        socket_close($socket);

        return $result !== false;
    }
}
