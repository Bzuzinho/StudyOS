<?php

return [
    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [
        'moodle' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'jobs',
            'queue' => 'moodle',
            'retry_after' => 1860,
            'after_commit' => false,
        ],
        'sync' => [
            'driver' => 'sync',
        ],
        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 240,
            'after_commit' => false,
        ],
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],
];
