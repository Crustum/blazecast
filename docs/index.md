# CakePHP BlazeCast Plugin

- [Introduction](#introduction)
- [Quickstart](#quickstart)
- [Installation](#installation)
    - [Server Configuration](#server-configuration)
    - [Application Configuration](#application-configuration)
    - [Redis Configuration](#redis-configuration)
- [Running the Server](#running-the-server)
    - [Starting the Server](#starting-the-server)
    - [Restarting the Server](#restarting-the-server)
- [Channels](#channels)
    - [Public Channels](#public-channels)
    - [Private Channels](#private-channels)
    - [Presence Channels](#presence-channels)
    - [Cache Channels](#cache-channels)
- [HTTP API](#http-api)
    - [Triggering Events](#triggering-events)
    - [Querying Channels](#querying-channels)
    - [Querying Channel Users](#querying-channel-users)
    - [Terminating User Connections](#terminating-user-connections)
    - [Health Checks](#health-checks)
    - [Metrics](#metrics)
- [Client Integration](#client-integration)
    - [Pusher JavaScript Client](#pusher-javascript-client)
    - [Laravel Echo](#laravel-echo)
- [Scaling](#scaling)
    - [Redis PubSub](#redis-pubsub)
    - [Horizontal Scaling](#horizontal-scaling)
- [Rate Limiting](#rate-limiting)
    - [Configuration](#rate-limiting-configuration)
    - [Rate Limit Drivers](#rate-limit-drivers)
- [Logging](#logging)
    - [Log Scopes](#log-scopes)
    - [Configuring Logging](#configuring-logging)
- [Events](#events)
    - [WebSocket Events](#websocket-events)
    - [HTTP API Events](#http-api-events)
- [Testing](#testing)

<a name="introduction"></a>
## Introduction

BlazeCast is a self-hosted WebSocket server for CakePHP applications that implements the Pusher protocol. It enables real-time bidirectional communication between your server and connected clients through WebSocket connections.

BlazeCast provides a complete WebSocket solution with support for public channels, private channels, and presence channels. The plugin includes a built-in HTTP API compatible with Pusher's REST API, Redis PubSub for horizontal scaling, rate limiting, comprehensive logging, and seamless integration with CakePHP's event system.

The plugin is fully compatible with Pusher JavaScript libraries and Laravel Echo, making it easy to integrate into existing applications that use Pusher or to build new real-time features.

<a name="quickstart"></a>
## Quickstart

### Installing the Plugin

Install via Composer:

```bash
composer require crustum/blazecast
```

> [!NOTE]
> This plugin should be registered in your `config/plugins.php` file.

```bash
bin/cake plugin load Crustum/BlazeCast
```

> [!TIP]
> **After the plugin registers itself**, it's recommended to install the configuration with the manifest system:

```bash
bin/cake manifest install --plugin Crustum/BlazeCast
```

The BlazeCast plugin will create the `config/blazecast.php` configuration file. Additionally, it will append the loading of the `config/blazecast.php` file to the `config/bootstrap.php` file.

### Basic Configuration

All of your application's BlazeCast configuration is stored in the `config/blazecast.php` configuration file:

```php
return [
    'BlazeCast' => [
        'default' => env('BLAZECAST_SERVER', 'blazecast'),
        'servers' => [
            'blazecast' => [
                'host' => env('BLAZECAST_SERVER_HOST', '0.0.0.0'),
                'port' => env('BLAZECAST_SERVER_PORT', 8080),
            ],
        ],
        'applications' => [
            [
                'id' => env('BLAZECAST_APP_ID', 'app-id'),
                'key' => env('BLAZECAST_APP_KEY', 'app-key'),
                'secret' => env('BLAZECAST_APP_SECRET', 'app-secret'),
                'name' => env('BLAZECAST_APP_NAME', 'Default BlazeCast App'),
            ],
        ],
    ],
];
```

### Starting the Server

Once configured, you can start the WebSocket server:

```bash
bin/cake blazecast server
```

The server will start listening on the configured host and port (default: `0.0.0.0:8080`).

<a name="installation"></a>
## Installation

<a name="server-configuration"></a>
### Server Configuration

The server configuration defines how the WebSocket server operates:

```php
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
        'ping_interval' => env('BLAZECAST_PING_INTERVAL', 30),
        'activity_timeout' => env('BLAZECAST_ACTIVITY_TIMEOUT', 120),
    ],
],
```

**Configuration Options:**

- `host`: The host address to bind to (default: `0.0.0.0` for all interfaces)
- `port`: The port number to listen on (default: `8080`)
- `path`: Optional path prefix for WebSocket connections
- `hostname`: Optional hostname for the server
- `protocol_version`: Pusher protocol version (default: `7`)
- `options.tls`: TLS configuration for secure connections
- `max_request_size`: Maximum size of HTTP requests in bytes (default: `10000`)
- `ping_interval`: Interval in seconds for sending ping messages (default: `30`)
- `activity_timeout`: Timeout in seconds for inactive connections (default: `120`)

<a name="application-configuration"></a>
### Application Configuration

Applications define the authentication credentials and limits for your WebSocket connections:

```php
'applications' => [
    [
        'id' => env('BLAZECAST_APP_ID', 'app-id'),
        'key' => env('BLAZECAST_APP_KEY', 'app-key'),
        'secret' => env('BLAZECAST_APP_SECRET', 'app-secret'),
        'name' => env('BLAZECAST_APP_NAME', 'Default BlazeCast App'),
        'max_connections' => env('BLAZECAST_APP_MAX_CONNECTIONS', 100),
        'enable_client_messages' => env('BLAZECAST_APP_ENABLE_CLIENT_MESSAGES', true),
        'enable_statistics' => env('BLAZECAST_APP_ENABLE_STATISTICS', true),
        'enable_debug' => env('BLAZECAST_APP_ENABLE_DEBUG', false),
        'allowed_origins' => ['*'],
        'ping_interval' => env('BLAZECAST_APP_PING_INTERVAL', 60),
        'activity_timeout' => env('BLAZECAST_APP_ACTIVITY_TIMEOUT', 30),
        'max_message_size' => env('BLAZECAST_APP_MAX_MESSAGE_SIZE', 10_000),
    ],
],
```

**Configuration Options:**

- `id`: Unique application identifier
- `key`: Public application key (used by clients)
- `secret`: Private application secret (used for authentication)
- `name`: Human-readable application name
- `max_connections`: Maximum number of concurrent connections (default: `100`)
- `enable_client_messages`: Allow clients to send messages (default: `true`)
- `enable_statistics`: Enable connection and channel statistics (default: `true`)
- `enable_debug`: Enable debug logging (default: `false`)
- `allowed_origins`: Array of allowed CORS origins (default: `['*']`)
- `ping_interval`: Interval in seconds for ping messages (default: `60`)
- `activity_timeout`: Timeout in seconds for inactive connections (default: `30`)
- `max_message_size`: Maximum message size in bytes (default: `10000`)

<a name="redis-configuration"></a>
### Redis Configuration

Redis is used for PubSub communication between multiple server instances when scaling horizontally:

```php
'redis' => [
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'port' => env('REDIS_PORT', 6379),
    'database' => env('REDIS_DB', 0),
    'password' => env('REDIS_PASSWORD'),
],
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
```

<a name="running-the-server"></a>
## Running the Server

<a name="starting-the-server"></a>
### Starting the Server

To start the WebSocket server, use the `blazecast server` command:

```bash
bin/cake blazecast server
```

You can override the host and port from the command line:

```bash
bin/cake blazecast server --host 127.0.0.1 --port 8080
```

The server will start and begin accepting WebSocket connections on the specified address and port.

<a name="restarting-the-server"></a>
### Restarting the Server

To restart the server, use the `blazecast restart_server` command:

```bash
bin/cake blazecast restart_server
```

This command will gracefully restart the server, closing existing connections and starting fresh.

<a name="channels"></a>
## Channels

BlazeCast supports three types of channels: public, private, and presence channels.

<a name="public-channels"></a>
### Public Channels

Public channels are open to all clients. Any client can subscribe to a public channel without authentication:

```javascript
const channel = pusher.subscribe('public-channel-name');
channel.bind('event-name', function(data) {
    console.log(data);
});
```

<a name="private-channels"></a>
### Private Channels

Private channels require authentication. Clients must authenticate before subscribing:

```javascript
const channel = pusher.subscribe('private-channel-name');
channel.bind('event-name', function(data) {
    console.log(data);
});
```

The authentication is handled automatically by the Broadcasting plugin. When using Laravel Echo, it makes a request to `/broadcasting/auth` endpoint which is provided by the Broadcasting plugin.

<a name="presence-channels"></a>
### Presence Channels

Presence channels extend private channels by providing information about who is subscribed to the channel:

```javascript
const channel = pusher.subscribe('presence-channel-name');
channel.bind('pusher:subscription_succeeded', function(members) {
    console.log('Current members:', members);
});
channel.bind('pusher:member_added', function(member) {
    console.log('Member joined:', member);
});
channel.bind('pusher:member_removed', function(member) {
    console.log('Member left:', member);
});
```

When subscribing to a presence channel using Laravel Echo with the Broadcasting plugin, the authentication is handled automatically through the `/broadcasting/auth` endpoint. The Broadcasting plugin will authorize the channel subscription based on your channel authorization rules defined in `config/channels.php`.

<a name="http-api"></a>
## HTTP API

BlazeCast provides a RESTful HTTP API compatible with Pusher's API for triggering events, querying channels, and managing connections.

<a name="triggering-events"></a>
### Triggering Events

To trigger an event on one or more channels, make a POST request to `/apps/{appId}/events`:

```php
$response = $http->post('http://localhost:8080/apps/app-id/events', [
    'headers' => [
        'Authorization' => 'Bearer ' . $appSecret,
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'name' => 'event-name',
        'data' => ['message' => 'Hello, World!'],
        'channels' => ['channel-name'],
    ],
]);
```

You can also trigger events to a single channel:

```php
$response = $http->post('http://localhost:8080/apps/app-id/events', [
    'headers' => [
        'Authorization' => 'Bearer ' . $appSecret,
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'name' => 'event-name',
        'data' => ['message' => 'Hello, World!'],
        'channel' => 'channel-name',
    ],
]);
```

To exclude a specific socket from receiving the event:

```php
$response = $http->post('http://localhost:8080/apps/app-id/events', [
    'headers' => [
        'Authorization' => 'Bearer ' . $appSecret,
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'name' => 'event-name',
        'data' => ['message' => 'Hello, World!'],
        'channel' => 'channel-name',
        'socket_id' => 'socket-id-to-exclude',
    ],
]);
```

<a name="querying-channels"></a>
### Querying Channels

To get information about all channels:

```php
$response = $http->get('http://localhost:8080/apps/app-id/channels', [
    'headers' => [
        'Authorization' => 'Bearer ' . $appSecret,
    ],
]);
```

To get information about a specific channel:

```php
$response = $http->get('http://localhost:8080/apps/app-id/channels/channel-name', [
    'headers' => [
        'Authorization' => 'Bearer ' . $appSecret,
    ],
]);
```

<a name="querying-channel-users"></a>
### Querying Channel Users

To get the list of users in a presence channel:

```php
$response = $http->get('http://localhost:8080/apps/app-id/channels/presence-channel-name/users', [
    'headers' => [
        'Authorization' => 'Bearer ' . $appSecret,
    ],
]);
```

<a name="terminating-user-connections"></a>
### Terminating User Connections

To terminate all connections for a specific user:

```php
$response = $http->post('http://localhost:8080/apps/app-id/users/user-id/terminate_connections', [
    'headers' => [
        'Authorization' => 'Bearer ' . $appSecret,
        'Content-Type' => 'application/json',
    ],
]);
```

<a name="health-checks"></a>
### Health Checks

To check if the server is running:

```bash
curl http://localhost:8080/up
```

Or using the Pusher-compatible endpoint:

```bash
curl http://localhost:8080/pusher/health
```

<a name="metrics"></a>
### Metrics

To get Prometheus-compatible metrics:

```bash
curl http://localhost:8080/metrics
```

<a name="client-integration"></a>
## Client Integration

BlazeCast integrates with the [CakePHP Broadcasting plugin](https://github.com/Crustum/Broadcasting) to provide real-time event broadcasting. To use BlazeCast with Broadcasting, you need to configure the Broadcasting plugin to use BlazeCast as the Pusher driver.

<a name="broadcasting-configuration"></a>
### Broadcasting Plugin Configuration

First, configure the Broadcasting plugin to use BlazeCast. In your `config/broadcasting.php` file:

```php
return [
    'Broadcasting' => [
        'default' => [
            'className' => 'Crustum/Broadcasting.Pusher',
            'key' => env('BLAZECAST_APP_KEY'),
            'secret' => env('BLAZECAST_APP_SECRET'),
            'app_id' => env('BLAZECAST_APP_ID'),
            'options' => [
                'host' => env('BLAZECAST_SERVER_HOST', '127.0.0.1'),
                'port' => env('BLAZECAST_SERVER_PORT', 8080),
                'scheme' => env('BLAZECAST_SCHEME', 'http'),
                'useTLS' => false,
            ],
        ],
    ],
];
```

Make sure the `key`, `secret`, and `app_id` match the values configured in your `config/blazecast.php` file.

<a name="laravel-echo"></a>
### Laravel Echo Configuration

Configure Laravel Echo to connect to your BlazeCast server:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    wsHost: process.env.MIX_PUSHER_HOST || '127.0.0.1',
    wsPort: process.env.MIX_PUSHER_PORT || 8080,
    wssPort: process.env.MIX_PUSHER_PORT || 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    }
});
```

Now you can listen for events broadcast by your CakePHP application:

```javascript
Echo.channel('orders')
    .listen('OrderShipmentStatusUpdated', (e) => {
        console.log(e.order);
    });

Echo.private('orders.' + orderId)
    .listen('OrderShipmentStatusUpdated', (e) => {
        console.log(e.order);
    });

Echo.join('presence-chat.' + roomId)
    .here((users) => {
        console.log('Current users:', users);
    })
    .joining((user) => {
        console.log('User joined:', user);
    })
    .leaving((user) => {
        console.log('User left:', user);
    });
```

<a name="scaling"></a>
## Scaling

<a name="redis-pubsub"></a>
### Redis PubSub

BlazeCast uses Redis PubSub to enable horizontal scaling. When multiple server instances are running, they communicate through Redis to broadcast events across all instances:

```php
'scaling' => [
    'enabled' => env('BLAZECAST_SCALING_ENABLED', true),
    'channel' => env('BLAZECAST_SCALING_CHANNEL', 'blazecast:broadcast'),
    'server' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', '6379'),
        'password' => env('REDIS_PASSWORD'),
        'database' => env('REDIS_DB', '0'),
    ],
],
```

<a name="horizontal-scaling"></a>
### Horizontal Scaling

To scale horizontally, run multiple BlazeCast server instances behind a load balancer. Each instance should:

1. Connect to the same Redis server
2. Use the same application configuration
3. Share the same scaling channel name

Events triggered on one instance will be broadcast to all connected clients across all instances via Redis PubSub.

<a name="rate-limiting"></a>
## Rate Limiting

BlazeCast includes built-in rate limiting to prevent abuse and ensure fair resource allocation.

<a name="rate-limiting-configuration"></a>
### Configuration

Rate limiting can be configured in `config/blazecast.php`:

```php
'rate_limiter' => [
    'enabled' => env('BLAZECAST_RATE_LIMITER_ENABLED', false),
    'driver' => env('BLAZECAST_RATE_LIMITER_DRIVER', 'local'),
    'default_limits' => [
        'max_backend_events_per_second' => env('BLAZECAST_RATE_LIMITER_BACKEND_EVENTS', 100),
        'max_frontend_events_per_second' => env('BLAZECAST_RATE_LIMITER_FRONTEND_EVENTS', 10),
        'max_read_requests_per_second' => env('BLAZECAST_RATE_LIMITER_READ_REQUESTS', 50),
    ],
    'redis' => [
        'host' => env('REDIS_HOST', 'localhost'),
        'port' => (int)env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int)env('REDIS_DATABASE', 0),
        'cluster_mode' => env('REDIS_CLUSTER_MODE', false),
    ],
],
```

**Rate Limit Types:**

- `max_backend_events_per_second`: Maximum events per second via HTTP API (default: `100`)
- `max_frontend_events_per_second`: Maximum events per second from WebSocket clients (default: `10`)
- `max_read_requests_per_second`: Maximum read requests per second (default: `50`)

<a name="rate-limit-drivers"></a>
### Rate Limit Drivers

BlazeCast supports two rate limit drivers:

- `local`: In-memory rate limiting (single server only)
- `redis`: Redis-based rate limiting (works across multiple servers)

<a name="logging"></a>
## Logging

BlazeCast provides comprehensive logging with configurable scopes for different components.

<a name="log-scopes"></a>
### Log Scopes

Logging is organized into scopes that can be enabled or disabled individually:

```php
'logging' => [
    'enabled' => env('BLAZECAST_LOGGING_ENABLED', true),
    'debug_enabled' => env('BLAZECAST_LOGGING_DEBUG_ENABLED', false),
    'log_file' => env('BLAZECAST_LOGGING_FILE', 'blazecast'),
    'log_path' => env('BLAZECAST_LOGGING_PATH', LOGS),
    'scopes' => [
        'command.server' => true,
        'command.server.start' => true,
        'socket.server' => true,
        'socket.connection' => false,
        'socket.channel' => false,
        // ... more scopes
    ],
],
```

**Available Scopes:**

- `command.server`: Server command execution
- `command.server.start`: Server startup
- `socket.server`: Server-level events
- `socket.connection`: Connection-level events
- `socket.channel`: Channel-level events
- `socket.controller`: HTTP controller events
- `socket.handler`: Event handler execution
- And many more...

<a name="configuring-logging"></a>
### Configuring Logging

To enable or disable specific log scopes, modify the `scopes` array in your configuration. Set a scope to `true` to enable logging for that component, or `false` to disable it.

<a name="events"></a>
## Events

BlazeCast dispatches various events that you can listen to in your application.

<a name="websocket-events"></a>
### WebSocket Events

- `BlazeCast.WebSocket.connectionEstablished`: Fired when a new connection is established
- `BlazeCast.WebSocket.connectionClosed`: Fired when a connection is closed
- `BlazeCast.WebSocket.channelCreated`: Fired when a channel is created
- `BlazeCast.WebSocket.channelRemoved`: Fired when a channel is removed
- `BlazeCast.WebSocket.messageReceived`: Fired when a message is received from a client
- `BlazeCast.WebSocket.messageSent`: Fired when a message is sent to a client

<a name="http-api-events"></a>
### HTTP API Events

- `BlazeCast.WebSocket.HttpApiEvent`: Fired when an HTTP API request is processed

You can listen to these events in your application:

```php
use Cake\Event\EventManager;

EventManager::instance()->on('BlazeCast.WebSocket.connectionEstablished', function ($event) {
    $connection = $event->getData('connection');
    // Handle connection established
});
```
