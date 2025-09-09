<?php

use Subhamchbty\Orchestral\Conductor\Conductor;

beforeEach(function () {
    // Mock the Conductor
    $this->conductor = Mockery::mock(Conductor::class);
    $this->app->instance(Conductor::class, $this->conductor);
});

it('starts all performances when no specific performance is given', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'queue-worker',
                    'pid' => 12345,
                    'running' => true,
                    'uptime' => '2 hours',
                    'memory_mb' => 128,
                    'cpu_percent' => 2.5,
                ],
                [
                    'name' => 'scheduler',
                    'pid' => 12346,
                    'running' => true,
                    'uptime' => '2 hours',
                    'memory_mb' => 64,
                    'cpu_percent' => 0.5,
                ],
            ],
            'total_performers' => 2,
            'running_performers' => 2,
        ]);

    $this->artisan('orchestral:conduct')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory (MB)', 'CPU (%)'],
            [
                ['queue-worker', '12345', '✅ Running', '2 hours', '128', '2.5'],
                ['scheduler', '12346', '✅ Running', '2 hours', '64', '0.5'],
            ]
        )
        ->expectsOutput('Total performers: 2 | Running: 2')
        ->assertSuccessful();
});

it('starts a specific performance when name is provided', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with('queue-worker');

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'queue-worker',
                    'pid' => 12345,
                    'running' => true,
                    'uptime' => '0 seconds',
                    'memory_mb' => 128,
                    'cpu_percent' => 1.0,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 1,
        ]);

    $this->artisan('orchestral:conduct', ['performance' => 'queue-worker'])
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory (MB)', 'CPU (%)'],
            [
                ['queue-worker', '12345', '✅ Running', '0 seconds', '128', '1'],
            ]
        )
        ->expectsOutput('Total performers: 1 | Running: 1')
        ->assertSuccessful();
});

it('handles conductor exceptions gracefully', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Unable to start performances: Redis connection failed'));

    $this->artisan('orchestral:conduct')
        ->expectsOutput('❌ Error: Unable to start performances: Redis connection failed')
        ->assertFailed();
});

it('displays status with stopped performers', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

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
                    'cpu_percent' => 5.5,
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

    $this->artisan('orchestral:conduct')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory (MB)', 'CPU (%)'],
            [
                ['worker-1', '12345', '✅ Running', '1 hour', '256', '5.5'],
                ['worker-2', 'N/A', '❌ Stopped', 'N/A', 'N/A', 'N/A'],
            ]
        )
        ->expectsOutput('Total performers: 2 | Running: 1')
        ->assertSuccessful();
});

it('displays empty status when no performers exist', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [],
            'total_performers' => 0,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:conduct')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory (MB)', 'CPU (%)'],
            []
        )
        ->expectsOutput('Total performers: 0 | Running: 0')
        ->assertSuccessful();
});

it('handles daemon mode option', function () {
    // Skip this test if pcntl extension is not available
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped('PCNTL extension not available');
    }

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [],
            'total_performers' => 0,
            'running_performers' => 0,
        ]);

    // For daemon mode, we'll mock it to immediately return false to prevent infinite loop
    $this->conductor->shouldReceive('isConducting')
        ->once()
        ->andReturn(false);

    $this->artisan('orchestral:conduct', ['--daemon' => true])
        ->expectsOutput('👁️ Running in daemon mode. Press Ctrl+C to stop...')
        ->assertSuccessful();
});

it('monitors performers in daemon mode when conducting', function () {
    // Skip this test if pcntl extension is not available
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped('PCNTL extension not available');
    }

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [],
            'total_performers' => 0,
            'running_performers' => 0,
        ]);

    // Simulate conducting state
    $this->conductor->shouldReceive('isConducting')
        ->twice()
        ->andReturn(true, false);

    $this->conductor->shouldReceive('monitorPerformers')
        ->once();

    $this->artisan('orchestral:conduct', ['--daemon' => true])
        ->expectsOutput('👁️ Running in daemon mode. Press Ctrl+C to stop...')
        ->assertSuccessful();
});

it('starts specific performance with daemon mode', function () {
    // Skip this test if pcntl extension is not available
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped('PCNTL extension not available');
    }

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with('scheduler');

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'scheduler',
                    'pid' => 9999,
                    'running' => true,
                    'uptime' => '0 seconds',
                    'memory_mb' => 64,
                    'cpu_percent' => 0.1,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 1,
        ]);

    $this->conductor->shouldReceive('isConducting')
        ->once()
        ->andReturn(false);

    $this->artisan('orchestral:conduct', [
        'performance' => 'scheduler',
        '--daemon' => true,
    ])
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory (MB)', 'CPU (%)'],
            [
                ['scheduler', '9999', '✅ Running', '0 seconds', '64', '0.1'],
            ]
        )
        ->expectsOutput('Total performers: 1 | Running: 1')
        ->expectsOutput('👁️ Running in daemon mode. Press Ctrl+C to stop...')
        ->assertSuccessful();
});

it('displays status with multiple performers having various states', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'high-priority-worker',
                    'pid' => 10001,
                    'running' => true,
                    'uptime' => '3 days, 2 hours',
                    'memory_mb' => 512,
                    'cpu_percent' => 15.5,
                ],
                [
                    'name' => 'low-priority-worker',
                    'pid' => 10002,
                    'running' => true,
                    'uptime' => '1 day, 5 hours',
                    'memory_mb' => 128,
                    'cpu_percent' => 3.2,
                ],
                [
                    'name' => 'failed-worker',
                    'pid' => null,
                    'running' => false,
                    'uptime' => null,
                    'memory_mb' => null,
                    'cpu_percent' => null,
                ],
                [
                    'name' => 'scheduler',
                    'pid' => 10003,
                    'running' => true,
                    'uptime' => '3 days, 2 hours',
                    'memory_mb' => 64,
                    'cpu_percent' => 0.1,
                ],
            ],
            'total_performers' => 4,
            'running_performers' => 3,
        ]);

    $this->artisan('orchestral:conduct')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory (MB)', 'CPU (%)'],
            [
                ['high-priority-worker', '10001', '✅ Running', '3 days, 2 hours', '512', '15.5'],
                ['low-priority-worker', '10002', '✅ Running', '1 day, 5 hours', '128', '3.2'],
                ['failed-worker', 'N/A', '❌ Stopped', 'N/A', 'N/A', 'N/A'],
                ['scheduler', '10003', '✅ Running', '3 days, 2 hours', '64', '0.1'],
            ]
        )
        ->expectsOutput('Total performers: 4 | Running: 3')
        ->assertSuccessful();
});

it('handles configuration not found exception', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Configuration not found for performance: invalid-name'));

    $this->artisan('orchestral:conduct')
        ->expectsOutput('❌ Error: Configuration not found for performance: invalid-name')
        ->assertFailed();
});

it('handles storage connection exception', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Failed to connect to storage backend'));

    $this->artisan('orchestral:conduct')
        ->expectsOutput('❌ Error: Failed to connect to storage backend')
        ->assertFailed();
});

it('handles performance already running exception', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Performance already running'));

    $this->artisan('orchestral:conduct')
        ->expectsOutput('❌ Error: Performance already running')
        ->assertFailed();
});

it('handles insufficient permissions exception', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Insufficient permissions to start process'));

    $this->artisan('orchestral:conduct')
        ->expectsOutput('❌ Error: Insufficient permissions to start process')
        ->assertFailed();
});

it('displays correct status format for high resource usage', function () {
    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'memory-intensive-worker',
                    'pid' => 20001,
                    'running' => true,
                    'uptime' => '5 hours',
                    'memory_mb' => 2048,
                    'cpu_percent' => 85.5,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 1,
        ]);

    $this->artisan('orchestral:conduct')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory (MB)', 'CPU (%)'],
            [
                ['memory-intensive-worker', '20001', '✅ Running', '5 hours', '2048', '85.5'],
            ]
        )
        ->expectsOutput('Total performers: 1 | Running: 1')
        ->assertSuccessful();
});
