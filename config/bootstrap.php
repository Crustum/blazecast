<?php
/**
 * Reverb Plugin bootstrap file.
 *
 * This file contains initialization code for the Reverb WebSocket plugin.
 */

use Crustum\BlazeCast\WebSocket\Pusher\Handler\PusherEventHandler;
use Cake\Core\Configure;
use Cake\Log\Log;

if (Configure::read('BlazeCast', null) === null) {
    Configure::write('BlazeCast', [
        'applications' => [
            [
                'id' => 'app-id',
                'key' => 'app-key',
                'secret' => 'app-secret',
                'name' => 'Default BlazeCast App',
                'max_connections' => 100,
                'enable_client_messages' => true,
                'enable_statistics' => true,
                'enable_debug' => false,
            ],
        ],

        'ping_interval' => 30,
        'activity_timeout' => 120,
        'allowed_origins' => ['*'],
        'max_message_size' => 10000,

        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
            'password' => null,
        ],
        'redis_test' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 1,
            'password' => null,
        ],

        'handlers' => [
            PusherEventHandler::class => 100,
        ],
    ]);
}

Configure::load('Crustum/BlazeCast.logging', 'default');

$loggingConfig = Configure::read('BlazeCast.logging', []);
if (($loggingConfig['enabled'] ?? true) === true) {
    $logFile = $loggingConfig['log_file'] ?? 'blazecast';
    $logPath = $loggingConfig['log_path'] ?? LOGS;
    $allScopes = array_keys(array_filter($loggingConfig['scopes'] ?? [], fn($enabled) => $enabled === true));

    Log::setConfig($logFile, [
        'className' => 'Cake\Log\Engine\FileLog',
        'path' => $logPath,
        'file' => $logFile,
        'levels' => ['debug', 'info', 'warning', 'error'],
        'scopes' => $allScopes,
    ]);
}
