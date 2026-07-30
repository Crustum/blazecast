<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Log\Log;

$loggingConfig = Configure::read('BlazeCast.logging', []);
if (($loggingConfig['enabled'] ?? true) === true) {
    $logFile = $loggingConfig['log_file'] ?? 'blazecast';
    $logPath = $loggingConfig['log_path'] ?? LOGS;
    $allScopes = array_keys(array_filter($loggingConfig['scopes'] ?? [], fn($enabled) => $enabled === true));

    if (Log::getConfig($logFile) === null) {
        Log::setConfig($logFile, [
            'className' => 'Cake\Log\Engine\FileLog',
            'path' => $logPath,
            'file' => $logFile,
            'levels' => ['debug', 'info', 'warning', 'error'],
            'scopes' => $allScopes,
        ]);
    }
}
