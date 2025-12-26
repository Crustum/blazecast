<?php
declare(strict_types=1);

/**
 * BlazeCast Rhythm Recorders Configuration
 *
 * This file contains configuration for BlazeCast Rhythm recorders.
 * Copy this configuration to your app's config/rhythm.php file.
 */

return [
    'Rhythm' => [
        'recorders' => [
            'messages' => [
                'className' => \Crustum\BlazeCast\Recorder\BlazeCastMessagesRecorder::class,
                'enabled' => true,
                'sample_rate' => 1.0,
            ],
            'connections' => [
                'className' => \Crustum\BlazeCast\Recorder\BlazeCastConnectionsRecorder::class,
                'enabled' => true,
                'sample_rate' => 1.0,
                'throttle_seconds' => 15,
            ],
        ],
    ]
];
