<?php

use Subhamchbty\Orchestral\Conductor\Score;

beforeEach(function () {
    $this->config = [
        'performances' => [
            'local' => [
                'queue-worker' => [
                    'command' => 'php artisan queue:work',
                    'performers' => 2,
                    'memory' => 256,
                    'timeout' => 60,
                    'options' => ['--queue' => 'default'],
                ],
                'scheduler' => [
                    'command' => 'php artisan schedule:work',
                    'performers' => 1,
                    'memory' => 128,
                    'timeout' => null,
                    'options' => [],
                ],
            ],
            'production' => [
                'queue-worker' => [
                    'command' => 'php artisan queue:work',
                    'performers' => 5,
                    'memory' => 512,
                    'timeout' => 120,
                    'options' => ['--queue' => 'high,default,low'],
                ],
                'horizon' => [
                    'command' => 'php artisan horizon',
                    'performers' => 1,
                    'memory' => 1024,
                    'timeout' => null,
                    'options' => [],
                ],
            ],
        ],
        'management' => [
            'restart_on_failure' => true,
            'restart_delay' => 5,
            'max_restart_attempts' => 3,
            'graceful_shutdown_timeout' => 30,
        ],
        'monitoring' => [
            'health_check_interval' => 60,
            'track_memory' => true,
            'track_cpu' => true,
            'memory_alert_threshold' => 90,
        ],
        'storage' => [
            'driver' => 'redis',
            'database' => [
                'connection' => 'mysql',
                'table' => 'orchestral_performances',
            ],
            'redis' => [
                'connection' => 'default',
                'prefix' => 'orchestral',
            ],
        ],
    ];
});

it('gets performances for current environment', function () {
    // Mock the environment
    app()->detectEnvironment(fn () => 'local');

    $score = new Score($this->config);
    $performances = $score->getPerformances();

    expect($performances)->toBeArray();
    expect($performances)->toHaveCount(2);
    expect($performances)->toHaveKeys(['queue-worker', 'scheduler']);
    expect($performances['queue-worker']['command'])->toBe('php artisan queue:work');
});

it('gets specific performance by name', function () {
    app()->detectEnvironment(fn () => 'local');

    $score = new Score($this->config);

    $performance = $score->getPerformance('queue-worker');
    expect($performance)->toBeArray();
    expect($performance['command'])->toBe('php artisan queue:work');
    expect($performance['performers'])->toBe(2);

    $nonExistent = $score->getPerformance('non-existent');
    expect($nonExistent)->toBeNull();
});

it('returns empty array when environment has no performances', function () {
    app()->detectEnvironment(fn () => 'testing');

    $score = new Score($this->config);
    $performances = $score->getPerformances();

    expect($performances)->toBeArray();
    expect($performances)->toBeEmpty();
});

it('gets management configuration', function () {
    $score = new Score($this->config);
    $management = $score->getManagementConfig();

    expect($management)->toBeArray();
    expect($management['restart_on_failure'])->toBeTrue();
    expect($management['restart_delay'])->toBe(5);
    expect($management['max_restart_attempts'])->toBe(3);
});

it('gets monitoring configuration', function () {
    $score = new Score($this->config);
    $monitoring = $score->getMonitoringConfig();

    expect($monitoring)->toBeArray();
    expect($monitoring['health_check_interval'])->toBe(60);
    expect($monitoring['track_memory'])->toBeTrue();
    expect($monitoring['track_cpu'])->toBeTrue();
});

it('gets storage configuration', function () {
    $score = new Score($this->config);
    $storage = $score->getStorageConfig();

    expect($storage)->toBeArray();
    expect($storage['driver'])->toBe('redis');
    expect($storage['database']['connection'])->toBe('mysql');
    expect($storage['redis']['prefix'])->toBe('orchestral');
});

it('gets individual management settings', function () {
    $score = new Score($this->config);

    expect($score->shouldRestartOnFailure())->toBeTrue();
    expect($score->getRestartDelay())->toBe(5);
    expect($score->getMaxRestartAttempts())->toBe(3);
    expect($score->getGracefulShutdownTimeout())->toBe(30);
});

it('gets individual monitoring settings', function () {
    $score = new Score($this->config);

    expect($score->getHealthCheckInterval())->toBe(60);
    expect($score->shouldTrackMemory())->toBeTrue();
    expect($score->shouldTrackCpu())->toBeTrue();
    expect($score->getMemoryAlertThreshold())->toBe(90);
});

it('returns default values when config missing', function () {
    $minimalConfig = ['performances' => []];
    $score = new Score($minimalConfig);

    expect($score->shouldRestartOnFailure())->toBeTrue(); // default
    expect($score->getRestartDelay())->toBe(5); // default
    expect($score->getMaxRestartAttempts())->toBe(10); // default is 10, not 3
    expect($score->getGracefulShutdownTimeout())->toBe(30); // default
    expect($score->getHealthCheckInterval())->toBe(60); // default
    expect($score->shouldTrackMemory())->toBeTrue(); // default
    expect($score->shouldTrackCpu())->toBeTrue(); // default
    expect($score->getMemoryAlertThreshold())->toBe(90); // default
});

it('gets current environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $score = new Score($this->config);

    expect($score->getEnvironment())->toBe('production');
});

it('gets all performance names for environment', function () {
    app()->detectEnvironment(fn () => 'local');

    $score = new Score($this->config);
    $names = $score->getAllPerformanceNames();

    expect($names)->toBeArray();
    expect($names)->toEqual(['queue-worker', 'scheduler']);

    app()->detectEnvironment(fn () => 'production');
    $score = new Score($this->config);
    $names = $score->getAllPerformanceNames();

    expect($names)->toEqual(['queue-worker', 'horizon']);
});

it('builds command with options', function () {
    $score = new Score($this->config);

    $performance = [
        'command' => 'queue:work',
        'options' => [
            '--queue' => 'high,default',
            '--sleep' => '3',
            'verbose',
        ],
    ];

    $command = $score->buildCommand($performance);

    expect($command)->toBe('php artisan queue:work --queue=high,default --sleep=3 verbose');
});

it('builds command without options', function () {
    $score = new Score($this->config);

    $performance = [
        'command' => 'horizon',
        'options' => [],
    ];

    $command = $score->buildCommand($performance);

    expect($command)->toBe('php artisan horizon');
});

it('builds command with missing options key', function () {
    $score = new Score($this->config);

    $performance = [
        'command' => 'test',
    ];

    $command = $score->buildCommand($performance);

    expect($command)->toBe('php artisan test');
});

it('handles boolean values in options', function () {
    $score = new Score($this->config);

    $performance = [
        'command' => 'migrate',
        'options' => [
            '--force' => true,
            '--seed' => false,
            '--pretend' => '1',
        ],
    ];

    $command = $score->buildCommand($performance);

    expect($command)->toContain('--force=1');
    expect($command)->toContain('--seed='); // false becomes empty value
    expect($command)->toContain('--pretend=1');
});

it('handles numeric options', function () {
    $score = new Score($this->config);

    $performance = [
        'command' => 'php worker.php',
        'options' => [
            '--tries' => 3,
            '--timeout' => 60,
            '--workers' => 10,
        ],
    ];

    $command = $score->buildCommand($performance);

    expect($command)->toContain('--tries=3');
    expect($command)->toContain('--timeout=60');
    expect($command)->toContain('--workers=10');
});

it('handles config with different environment structures', function () {
    $config = [
        'performances' => [
            'staging' => [
                'worker' => ['command' => 'php staging.php'],
            ],
            'development' => [],
        ],
    ];

    app()->detectEnvironment(fn () => 'staging');
    $score = new Score($config);
    expect($score->getPerformances())->toHaveCount(1);

    app()->detectEnvironment(fn () => 'development');
    $score = new Score($config);
    expect($score->getPerformances())->toBeEmpty();

    app()->detectEnvironment(fn () => 'testing');
    $score = new Score($config);
    expect($score->getPerformances())->toBeEmpty(); // Returns empty array for undefined environments
});

it('handles deeply nested config values', function () {
    $config = [
        'performances' => [],
        'storage' => [
            'redis' => [
                'connection' => [
                    'host' => 'localhost',
                    'port' => 6379,
                ],
            ],
        ],
    ];

    $score = new Score($config);
    $storage = $score->getStorageConfig();

    expect($storage['redis']['connection']['host'])->toBe('localhost');
    expect($storage['redis']['connection']['port'])->toBe(6379);
});
