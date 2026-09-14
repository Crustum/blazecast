<?php
declare(strict_types=1);

/**
 * Boots the REAL BlazeCast WebSocket server for conformance testing.
 *
 * Unlike a generic echo server, this exercises the production code path:
 * Pusher handshake on /app/{key}, pusher:connection_established welcome,
 * channel handling, rate limiting (disabled here), and the RFC6455
 * frame layer in Crustum\BlazeCast\WebSocket\Pusher\Server.
 *
 * Usage:
 *   php spec-server.php                      # 0.0.0.0:8080, app key spec-key
 *   HOST=127.0.0.1 PORT=8091 php spec-server.php
 *
 * Env overrides: HOST, PORT, SPEC_APP_ID, SPEC_APP_KEY, SPEC_APP_SECRET.
 */

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Crustum\BlazeCast\BlazeCastPlugin;
use Crustum\BlazeCast\WebSocket\Pusher\ServerFactory;

require __DIR__ . '/../../tests/bootstrap.php';

$host = getenv('HOST') ?: '0.0.0.0';
$port = (int)(getenv('PORT') ?: 8080);
$appId = getenv('SPEC_APP_ID') ?: 'spec-app-id';
$appKey = getenv('SPEC_APP_KEY') ?: 'spec-key';
$appSecret = getenv('SPEC_APP_SECRET') ?: 'spec-secret';

// Lets Configure::load('Crustum/BlazeCast.rhythm') inside Server resolve.
Plugin::getCollection()->add(new BlazeCastPlugin());

// Keep conformance runs deterministic: no throttling of probe traffic.
Configure::write('BlazeCast.rate_limiter', ['enabled' => false]);

$config = [
    'app_id' => $appId,
    'app_key' => $appKey,
    'app_secret' => $appSecret,
    'app_name' => 'Spec Conformance App',
    'allowed_origins' => ['*'],
    'max_message_size' => 10000,
    'debug' => false,
    // Raw RFC6455 echo on "/" for Autobahn fuzzingclient. Pusher paths
    // keep the full production code path (welcome, channels, limits).
    'echo_mode' => true,
    'echo_path' => '/',
];

$server = ServerFactory::create($host, $port, $config);

echo "BlazeCast spec server running at {$host}:{$port} (app key: {$appKey})\n";

$server->start();
