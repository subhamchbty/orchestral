<?php

use Subhamchbty\Orchestral\Conductor\Conductor;

beforeEach(function () {
    // Mock the Conductor
    $this->conductor = Mockery::mock(Conductor::class);
    $this->app->instance(Conductor::class, $this->conductor);
});

it('displays status of running performers', function () {
    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'queue-worker',
                    'pid' => 12345,
                    'running' => true,
                    'uptime' => '2 hours',
                    'memory_mb' => 256,
                    'cpu_percent' => 5.5,
                ],
                [
                    'name' => 'scheduler',
                    'pid' => 12346,
                    'running' => true,
                    'uptime' => '1 hour',
                    'memory_mb' => 128,
                    'cpu_percent' => 2.0,
                ],
            ],
            'total_performers' => 2,
            'running_performers' => 2,
        ]);

    $this->artisan('orchestral:status')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory', 'CPU'],
            [
                ['queue-worker', '12345', 'Running', '2 hours', '256 MB', '5.5%'],
                ['scheduler', '12346', 'Running', '1 hour', '128 MB', '2%'],
            ]
        )
        ->expectsOutput('Total: 2 | Running: 2')
        ->assertSuccessful();
});

it('displays message when no performers are running', function () {
    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [],
            'total_performers' => 0,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:status')
        ->expectsOutput('No performers running')
        ->assertSuccessful();
});

it('displays status with stopped performers', function () {
    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'worker-1',
                    'pid' => 12345,
                    'running' => true,
                    'uptime' => '30 minutes',
                    'memory_mb' => 512,
                    'cpu_percent' => 10.5,
                ],
                [
                    'name' => 'worker-2',
                    'pid' => null,
                    'running' => false,
                    'uptime' => null,
                    'memory_mb' => null,
                    'cpu_percent' => null,
                ],
            ],
            'total_performers' => 2,
            'running_performers' => 1,
        ]);

    $this->artisan('orchestral:status')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory', 'CPU'],
            [
                ['worker-1', '12345', 'Running', '30 minutes', '512 MB', '10.5%'],
                ['worker-2', '-', 'Stopped', '-', '-', '-'],
            ]
        )
        ->expectsOutput('Total: 2 | Running: 1')
        ->assertSuccessful();
});

it('formats memory in GB when over 1024 MB', function () {
    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'memory-intensive',
                    'pid' => 12345,
                    'running' => true,
                    'uptime' => '5 hours',
                    'memory_mb' => 2048,
                    'cpu_percent' => 15.0,
                ],
                [
                    'name' => 'small-worker',
                    'pid' => 12346,
                    'running' => true,
                    'uptime' => '5 hours',
                    'memory_mb' => 512,
                    'cpu_percent' => 3.0,
                ],
            ],
            'total_performers' => 2,
            'running_performers' => 2,
        ]);

    $this->artisan('orchestral:status')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory', 'CPU'],
            [
                ['memory-intensive', '12345', 'Running', '5 hours', '2 GB', '15%'],
                ['small-worker', '12346', 'Running', '5 hours', '512 MB', '3%'],
            ]
        )
        ->assertSuccessful();
});

it('displays status with health check information', function () {
    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'worker',
                    'pid' => 12345,
                    'running' => true,
                    'uptime' => '1 hour',
                    'memory_mb' => 256,
                    'cpu_percent' => 5.0,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 1,
        ]);

    $this->conductor->shouldReceive('healthCheck')
        ->once()
        ->andReturn([
            'worker' => [
                'healthy' => true,
                'issues' => [],
            ],
        ]);

    $this->artisan('orchestral:status', ['--health' => true])
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory', 'CPU'],
            [
                ['worker', '12345', 'Running', '1 hour', '256 MB', '5%'],
            ]
        )
        ->expectsOutput('Total: 1 | Running: 1')
        ->expectsOutput('Health Status:')
        ->expectsOutput('worker: Healthy')
        ->assertSuccessful();
});

it('displays health issues when performers are unhealthy', function () {
    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'problematic-worker',
                    'pid' => 12345,
                    'running' => true,
                    'uptime' => '10 minutes',
                    'memory_mb' => 900,
                    'cpu_percent' => 95.0,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 1,
        ]);

    $this->conductor->shouldReceive('healthCheck')
        ->once()
        ->andReturn([
            'problematic-worker' => [
                'healthy' => false,
                'issues' => [
                    'Memory usage exceeds 90% of limit',
                    'CPU usage is critically high',
                    'Process not responding to health checks',
                ],
            ],
        ]);

    $this->artisan('orchestral:status', ['--health' => true])
        ->expectsOutput('Health Status:')
        ->expectsOutput('problematic-worker: Issues')
        ->expectsOutput('  • Memory usage exceeds 90% of limit')
        ->expectsOutput('  • CPU usage is critically high')
        ->expectsOutput('  • Process not responding to health checks')
        ->assertSuccessful();
});

it('outputs status as JSON when --json option is used', function () {
    $status = [
        'performers' => [
            [
                'name' => 'worker',
                'pid' => 12345,
                'running' => true,
                'uptime' => '1 hour',
                'memory_mb' => 256,
                'cpu_percent' => 5.0,
            ],
        ],
        'total_performers' => 1,
        'running_performers' => 1,
    ];

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn($status);

    $expectedJson = json_encode($status, JSON_PRETTY_PRINT);

    $this->artisan('orchestral:status', ['--json' => true])
        ->expectsOutput($expectedJson)
        ->assertSuccessful();
});

it('outputs JSON with health information when both options are used', function () {
    $status = [
        'performers' => [
            [
                'name' => 'worker',
                'pid' => 12345,
                'running' => true,
                'uptime' => '1 hour',
                'memory_mb' => 256,
                'cpu_percent' => 5.0,
            ],
        ],
        'total_performers' => 1,
        'running_performers' => 1,
    ];

    $health = [
        'worker' => [
            'healthy' => true,
            'issues' => [],
        ],
    ];

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn($status);

    $this->conductor->shouldReceive('healthCheck')
        ->once()
        ->andReturn($health);

    $expectedOutput = array_merge($status, ['health' => $health]);
    $expectedJson = json_encode($expectedOutput, JSON_PRETTY_PRINT);

    $this->artisan('orchestral:status', ['--json' => true, '--health' => true])
        ->expectsOutput($expectedJson)
        ->assertSuccessful();
});

it('handles mixed health statuses correctly', function () {
    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'healthy-worker',
                    'pid' => 12345,
                    'running' => true,
                    'uptime' => '2 hours',
                    'memory_mb' => 128,
                    'cpu_percent' => 3.0,
                ],
                [
                    'name' => 'unhealthy-worker',
                    'pid' => 12346,
                    'running' => true,
                    'uptime' => '30 minutes',
                    'memory_mb' => 450,
                    'cpu_percent' => 85.0,
                ],
            ],
            'total_performers' => 2,
            'running_performers' => 2,
        ]);

    $this->conductor->shouldReceive('healthCheck')
        ->once()
        ->andReturn([
            'healthy-worker' => [
                'healthy' => true,
                'issues' => [],
            ],
            'unhealthy-worker' => [
                'healthy' => false,
                'issues' => [
                    'High CPU usage detected',
                ],
            ],
        ]);

    $this->artisan('orchestral:status', ['--health' => true])
        ->expectsOutput('Health Status:')
        ->expectsOutput('healthy-worker: Healthy')
        ->expectsOutput('unhealthy-worker: Issues')
        ->expectsOutput('  • High CPU usage detected')
        ->assertSuccessful();
});

it('formats large memory values correctly', function () {
    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'small',
                    'pid' => 1,
                    'running' => true,
                    'uptime' => '1h',
                    'memory_mb' => 64,
                    'cpu_percent' => 1.0,
                ],
                [
                    'name' => 'medium',
                    'pid' => 2,
                    'running' => true,
                    'uptime' => '1h',
                    'memory_mb' => 1024,
                    'cpu_percent' => 2.0,
                ],
                [
                    'name' => 'large',
                    'pid' => 3,
                    'running' => true,
                    'uptime' => '1h',
                    'memory_mb' => 1536,
                    'cpu_percent' => 3.0,
                ],
                [
                    'name' => 'huge',
                    'pid' => 4,
                    'running' => true,
                    'uptime' => '1h',
                    'memory_mb' => 4096,
                    'cpu_percent' => 4.0,
                ],
            ],
            'total_performers' => 4,
            'running_performers' => 4,
        ]);

    $this->artisan('orchestral:status')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory', 'CPU'],
            [
                ['small', '1', 'Running', '1h', '64 MB', '1%'],
                ['medium', '2', 'Running', '1h', '1024 MB', '2%'],
                ['large', '3', 'Running', '1h', '1.5 GB', '3%'],
                ['huge', '4', 'Running', '1h', '4 GB', '4%'],
            ]
        )
        ->assertSuccessful();
});

it('handles decimal CPU percentages correctly', function () {
    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'worker-1',
                    'pid' => 12345,
                    'running' => true,
                    'uptime' => '1 hour',
                    'memory_mb' => 256,
                    'cpu_percent' => 0.5,
                ],
                [
                    'name' => 'worker-2',
                    'pid' => 12346,
                    'running' => true,
                    'uptime' => '1 hour',
                    'memory_mb' => 256,
                    'cpu_percent' => 10.75,
                ],
                [
                    'name' => 'worker-3',
                    'pid' => 12347,
                    'running' => true,
                    'uptime' => '1 hour',
                    'memory_mb' => 256,
                    'cpu_percent' => 99.99,
                ],
            ],
            'total_performers' => 3,
            'running_performers' => 3,
        ]);

    $this->artisan('orchestral:status')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory', 'CPU'],
            [
                ['worker-1', '12345', 'Running', '1 hour', '256 MB', '0.5%'],
                ['worker-2', '12346', 'Running', '1 hour', '256 MB', '10.75%'],
                ['worker-3', '12347', 'Running', '1 hour', '256 MB', '99.99%'],
            ]
        )
        ->assertSuccessful();
});

it('does not display total line when no performers exist', function () {
    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [],
            'total_performers' => 0,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:status')
        ->expectsOutput('No performers running')
        ->doesntExpectOutput('Total: 0 | Running: 0')
        ->assertSuccessful();
});
