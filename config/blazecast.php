<?php
declare(strict_types=1);

return [
    'BlazeCast' => [
        /**
          * Default BlazeCast Server
          *
          * This option controls the default server used by BlazeCast to handle
          * incoming messages as well as broadcasting message to all your
          * connected clients. At this time only "blazecast" is supported.
         */
        'default' => env('BLAZECAST_SERVER', 'blazecast'),

        /**
         * BlazeCast Servers
         *
         * Here you may define details for each of the supported BlazeCast servers.
         * Each server has its own configuration options that are defined in
         * the array below. You should ensure all the options are present.
         */
        'servers' => [
            'blazecast' => [
                'host' => env('BLAZECAST_SERVER_HOST', '0.0.0.0'),
                'port' => env('BLAZECAST_SERVER_PORT', 8080),
                'path' => env('BLAZECAST_SERVER_PATH', ''),
                'hostname' => env('BLAZECAST_HOST'),
                'protocol_version' => env('BLAZECAST_PROTOCOL_VERSION', '7'),
                'options' => [
                    'tls' => [],
                ],
                'max_request_size' => env('BLAZECAST_MAX_REQUEST_SIZE', 10_000),
                'scaling' => [
                    'enabled' => env('BLAZECAST_SCALING_ENABLED', true),
                    'channel' => env('BLAZECAST_SCALING_CHANNEL', 'blazecast:broadcast'),
                    'server' => [
                        'url' => env('REDIS_URL'),
                        'host' => env('REDIS_HOST', '127.0.0.1'),
                        'port' => env('REDIS_PORT', '6379'),
                        'username' => env('REDIS_USERNAME'),
                        'password' => env('REDIS_PASSWORD'),
                        'database' => env('REDIS_DB', '0'),
                        'timeout' => env('REDIS_TIMEOUT', 60),
                    ],
                ],
                'ping_interval' => env('BLAZECAST_PING_INTERVAL', 30),
                'activity_timeout' => env('BLAZECAST_ACTIVITY_TIMEOUT', 120),
                'speculum_ingest_interval' => (int)env('BLAZECAST_SPECULUM_INGEST_INTERVAL', 15),
            ],
        ],

        /**
         * BlazeCast Applications (Preserved from existing structure)
         *
         * Multiple applications support - preserved existing key structure
         *
         */
        'applications' => [
            [
                'id' => env('BLAZECAST_APP_ID', 'app-id'),
                'key' => env('BLAZECAST_APP_KEY', 'app-key'),
                'secret' => env('BLAZECAST_APP_SECRET', 'app-secret'),
                'name' => env('BLAZECAST_APP_NAME', 'Default BlazeCast App'),
                'max_connections' => env('BLAZECAST_APP_MAX_CONNECTIONS', 100),
                'enable_client_messages' => env('BLAZECAST_APP_ENABLE_CLIENT_MESSAGES', true),
                'accept_client_events_from' => env('BLAZECAST_APP_ACCEPT_CLIENT_EVENTS_FROM', 'all'),
                'enable_statistics' => env('BLAZECAST_APP_ENABLE_STATISTICS', true),
                'enable_debug' => env('BLAZECAST_APP_ENABLE_DEBUG', false),
                'allowed_origins' => ['*'],
                'ping_interval' => env('BLAZECAST_APP_PING_INTERVAL', 60),
                'activity_timeout' => env('BLAZECAST_APP_ACTIVITY_TIMEOUT', 30),
                'max_message_size' => env('BLAZECAST_APP_MAX_MESSAGE_SIZE', 10_000),
            ],
        ],

        /**
         * Event Handlers (Preserved from existing structure)
         *
         * Event handlers for processing WebSocket events.
         */
        'handlers' => [
            'BlazeCast\WebSocket\Pusher\Handler\PusherEventHandler' => 100,
        ],

        /**
         * Redis Configuration (Preserved from existing structure)
         *
         * Redis configuration for PubSub and cross-platform communication.
         */
        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
            'password' => env('REDIS_PASSWORD'),
        ],

        'redis_test' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB_TEST', 1),
            'password' => env('REDIS_PASSWORD'),
        ],



        /**
         * Cross-Platform Bridge Configuration
         *
         * Configuration for external server communication via Redis PubSub
         */
        'bridge' => [
            'enabled' => env('BLAZECAST_EXTERNAL_BRIDGE_ENABLED', true),
            'channels' => [
                'chat' => env('BLAZECAST_CHAT_CHANNEL', 'blazecast:chat'),
                'notifications' => env('BLAZECAST_NOTIFICATIONS_CHANNEL', 'blazecast:notifications'),
                'ai' => env('BLAZECAST_AI_CHANNEL', 'blazecast:ai'),
            ],
        ],

        /**
         * Rate Limiting Configuration
         *
         * Controls rate limiting for HTTP API endpoints and WebSocket messages.
         * Prevents abuse and ensures fair resource allocation across applications.
         */
        'rate_limiter' => [
            'enabled' => env('BLAZECAST_RATE_LIMITER_ENABLED', false),
            'driver' => env('BLAZECAST_RATE_LIMITER_DRIVER', 'local'),
            'default_limits' => [
                'max_backend_events_per_second' => env('BLAZECAST_RATE_LIMITER_BACKEND_EVENTS', 100),
                'max_frontend_events_per_second' => env('BLAZECAST_RATE_LIMITER_FRONTEND_EVENTS', 10),
                'max_read_requests_per_second' => env('BLAZECAST_RATE_LIMITER_READ_REQUESTS', 50),
            ],
            'connection' => [
                'enabled' => env('BLAZECAST_CONNECTION_RATE_LIMIT_ENABLED', false),
                'max_messages_per_second' => env('BLAZECAST_CONNECTION_RATE_LIMIT_MAX', 60),
                'terminate_on_limit' => env('BLAZECAST_CONNECTION_RATE_LIMIT_TERMINATE', false),
            ],
            'redis' => [
                'host' => env('REDIS_HOST', 'localhost'),
                'port' => (int)env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD', null),
                'database' => (int)env('REDIS_DATABASE', 0),
                'cluster_mode' => env('REDIS_CLUSTER_MODE', false),
            ],
        ],

        /**
         * Logging Configuration
         */
        'logging' => [
            'enabled' => env('BLAZECAST_LOGGING_ENABLED', true),
            'debug_enabled' => env('BLAZECAST_LOGGING_DEBUG_ENABLED', false),
            'log_file' => env('BLAZECAST_LOGGING_FILE', 'blazecast'),
            'log_path' => env('BLAZECAST_LOGGING_PATH', LOGS),
            'scopes' => [
                'command.server' => true,
                'command.server.start' => true,
                'rhythm.recorder' => true,
                'rhythm.recorder.messages' => true,
                'rhythm.recorder.connections' => true,
                'socket.server' => true,
                'socket.server.redis' => true,
                'socket.server.rhythm' => true,
                'socket.server.restart' => true,
                'socket.server.disconnect' => true,
                'socket.connection' => true,
                'socket.connection.ping' => true,
                'socket.connection.pong' => true,
                'socket.connection.control' => true,
                'socket.connection.events' => true,
                'socket.controller' => true,
                'socket.controller.auth' => true,
                'socket.controller.events' => true,
                'socket.controller.factory' => true,
                'socket.controller.response' => true,
                'socket.controller.users' => true,
                'socket.channel' => true,
                'socket.channel.pusher' => true,
                'socket.channel.factory' => true,
                'socket.channel.serialization' => true,
                'socket.channel.cache' => true,
                'socket.channel.private' => true,
                'socket.manager' => true,
                'socket.manager.channel' => true,
                'socket.manager.connection' => true,
                'socket.manager.application' => true,
                'socket.manager.operations' => true,
                'socket.registry' => true,
                'socket.registry.connection' => true,
                'socket.registry.context' => true,
                'socket.handler' => true,
                'socket.handler.pusher' => true,
                'socket.handler.dispatcher' => true,
                'socket.router' => true,
                'socket.router.loader' => true,
                'socket.http' => true,
                'socket.http.processor' => true,
                'socket.metrics' => true,
                'socket.job' => true,
                'socket.job.manager' => true,
            ],
            'disabled_scopes' => [
            ],
        ],
    ],
];
