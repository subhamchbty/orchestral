<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Orchestral Performances Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the various "performances" (process groups) that
    | Orchestral will conduct. Each environment can have its own set of
    | performers with different configurations.
    |
    */

    'performances' => [
        'local' => [
            'queue-worker' => [
                'command' => 'queue:work',
                'performers' => 2,  // Number of processes
                'memory' => 128,    // Memory limit in MB
                'timeout' => 3600,  // Timeout in seconds
                'retry_after' => 60, // Seconds before retry on failure
                'nice' => 0,        // Process priority (-20 to 19)
                'options' => [      // Additional command options
                    '--tries' => 3,
                    '--sleep' => 3,
                ],
            ],
            'scheduler' => [
                'command' => 'schedule:work',
                'performers' => 1,
                'memory' => 64,
                'options' => [],
            ],
        ],

        'staging' => [
            'queue-worker' => [
                'command' => 'queue:work',
                'performers' => 3,
                'memory' => 256,
                'timeout' => 3600,
                'retry_after' => 60,
                'options' => [
                    '--tries' => 3,
                    '--sleep' => 3,
                    '--queue' => 'default,high,low',
                ],
            ],
            'scheduler' => [
                'command' => 'schedule:work',
                'performers' => 1,
                'memory' => 128,
            ],
        ],

        'production' => [
            'queue-worker' => [
                'command' => 'queue:work',
                'performers' => 5,
                'memory' => 512,
                'timeout' => 3600,
                'retry_after' => 60,
                'nice' => 10,
                'options' => [
                    '--tries' => 3,
                    '--sleep' => 3,
                    '--queue' => 'default,high,low',
                    '--max-jobs' => 1000,
                    '--max-time' => 3600,
                ],
            ],
            'scheduler' => [
                'command' => 'schedule:work',
                'performers' => 1,
                'memory' => 256,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Process Management Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how Orchestral manages processes globally.
    |
    */

    'management' => [
        'restart_on_failure' => true,       // Auto-restart failed processes
        'restart_delay' => 5,                // Seconds to wait before restart
        'max_restart_attempts' => 10,        // Max restart attempts before giving up
        'restart_window' => 3600,            // Time window for restart attempts (seconds)
        'graceful_shutdown_timeout' => 30,   // Seconds to wait for graceful shutdown
        'health_check_interval' => 60,       // Seconds between health checks
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Settings
    |--------------------------------------------------------------------------
    |
    | Configure how Orchestral monitors and reports on process health.
    |
    */

    'monitoring' => [
        'track_memory' => true,              // Track memory usage
        'track_cpu' => true,                 // Track CPU usage
        'alert_on_high_memory' => true,      // Alert when memory threshold exceeded
        'memory_alert_threshold' => 90,       // Memory usage percentage to trigger alert
        'log_performance' => true,           // Log performance metrics
        'performance_log_interval' => 300,    // Seconds between performance logs
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    |
    | Configure where Orchestral stores process information and logs.
    |
    */

    'storage' => [
        'driver' => env('ORCHESTRAL_STORAGE_DRIVER', 'redis'), // 'database' or 'redis'
        'redis_connection' => 'default',
        'table_name' => 'orchestral_performances',
        'cleanup_after_days' => 7,           // Days to keep old process records
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure notifications for process events.
    |
    */

    'notifications' => [
        'enabled' => env('ORCHESTRAL_NOTIFICATIONS_ENABLED', false),
        'channels' => ['log'],               // 'log', 'mail', 'slack', etc.
        'notify_on' => [
            'process_failed' => true,
            'high_memory' => true,
            'all_stopped' => true,
        ],
    ],
];
