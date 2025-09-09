<?php

use Subhamchbty\Orchestral\Conductor\Conductor;

beforeEach(function () {
    // Mock the Conductor
    $this->conductor = Mockery::mock(Conductor::class);
    $this->app->instance(Conductor::class, $this->conductor);
});

it('restarts all performances with default delay', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

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
                    'restart_attempts' => 1,
                ],
                [
                    'name' => 'scheduler',
                    'pid' => 12346,
                    'running' => true,
                    'restart_attempts' => 1,
                ],
            ],
            'total_performers' => 2,
            'running_performers' => 2,
        ]);

    $this->artisan('orchestral:encore')
        ->expectsOutput('🎭 The audience demands an encore!')
        ->expectsOutput('⏸️ First, the orchestra takes a bow...')
        ->expectsOutput('⏳ Intermission for 2 seconds...')
        ->expectsOutput('🎼 The encore begins!')
        ->expectsOutput('✨ The orchestra plays once more!')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Restarts'],
            [
                ['queue-worker', '12345', '✅ Running', '1'],
                ['scheduler', '12346', '✅ Running', '1'],
            ]
        )
        ->expectsOutput('Total performers: 2 | Running: 2')
        ->assertSuccessful();
});

it('restarts a specific performance', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with('queue-worker');

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
                    'restart_attempts' => 2,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 1,
        ]);

    $this->artisan('orchestral:encore', ['performance' => 'queue-worker'])
        ->expectsOutput('🔄 Preparing encore for: queue-worker')
        ->expectsOutput('⏸️ First, the orchestra takes a bow...')
        ->expectsOutput('⏳ Intermission for 2 seconds...')
        ->expectsOutput('🎼 The encore begins!')
        ->expectsOutput('✨ The orchestra plays once more!')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Restarts'],
            [
                ['queue-worker', '12345', '✅ Running', '2'],
            ]
        )
        ->expectsOutput('Total performers: 1 | Running: 1')
        ->assertSuccessful();
});

it('restarts with custom delay', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'worker',
                    'pid' => 12345,
                    'running' => true,
                    'restart_attempts' => 1,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 1,
        ]);

    $this->artisan('orchestral:encore', ['--delay' => 5])
        ->expectsOutput('🎭 The audience demands an encore!')
        ->expectsOutput('⏸️ First, the orchestra takes a bow...')
        ->expectsOutput('⏳ Intermission for 5 seconds...')
        ->expectsOutput('🎼 The encore begins!')
        ->expectsOutput('✨ The orchestra plays once more!')
        ->assertSuccessful();
});

it('restarts immediately with zero delay', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'worker',
                    'pid' => 12345,
                    'running' => true,
                    'restart_attempts' => 1,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 1,
        ]);

    $this->artisan('orchestral:encore', ['--delay' => 0])
        ->expectsOutput('🎭 The audience demands an encore!')
        ->expectsOutput('⏸️ First, the orchestra takes a bow...')
        ->doesntExpectOutput('⏳ Intermission for 0 seconds...')
        ->expectsOutput('🎼 The encore begins!')
        ->expectsOutput('✨ The orchestra plays once more!')
        ->assertSuccessful();
});

it('handles exceptions during pause phase', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Unable to pause: Process not found'));

    $this->artisan('orchestral:encore')
        ->expectsOutput('🎭 The audience demands an encore!')
        ->expectsOutput('⏸️ First, the orchestra takes a bow...')
        ->expectsOutput('❌ The encore could not be performed: Unable to pause: Process not found')
        ->assertFailed();
});

it('handles exceptions during conduct phase', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Unable to start: Configuration missing'));

    $this->artisan('orchestral:encore')
        ->expectsOutput('🎭 The audience demands an encore!')
        ->expectsOutput('⏸️ First, the orchestra takes a bow...')
        ->expectsOutput('⏳ Intermission for 2 seconds...')
        ->expectsOutput('🎼 The encore begins!')
        ->expectsOutput('❌ The encore could not be performed: Unable to start: Configuration missing')
        ->assertFailed();
});

it('displays status with stopped performers after restart', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

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
                    'restart_attempts' => 3,
                ],
                [
                    'name' => 'worker-2',
                    'pid' => null,
                    'running' => false,
                    'restart_attempts' => 3,
                ],
            ],
            'total_performers' => 2,
            'running_performers' => 1,
        ]);

    $this->artisan('orchestral:encore')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Restarts'],
            [
                ['worker-1', '12345', '✅ Running', '3'],
                ['worker-2', 'N/A', '❌ Stopped', '3'],
            ]
        )
        ->expectsOutput('Total performers: 2 | Running: 1')
        ->assertSuccessful();
});

it('handles performers with no restart attempts', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'new-worker',
                    'pid' => 12345,
                    'running' => true,
                    'restart_attempts' => null,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 1,
        ]);

    $this->artisan('orchestral:encore')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Restarts'],
            [
                ['new-worker', '12345', '✅ Running', '0'],
            ]
        )
        ->assertSuccessful();
});

it('restarts specific performance with custom delay', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with('scheduler');

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with('scheduler');

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'scheduler',
                    'pid' => 12346,
                    'running' => true,
                    'restart_attempts' => 5,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 1,
        ]);

    $this->artisan('orchestral:encore', [
        'performance' => 'scheduler',
        '--delay' => 3,
    ])
        ->expectsOutput('🔄 Preparing encore for: scheduler')
        ->expectsOutput('⏸️ First, the orchestra takes a bow...')
        ->expectsOutput('⏳ Intermission for 3 seconds...')
        ->expectsOutput('🎼 The encore begins!')
        ->expectsOutput('✨ The orchestra plays once more!')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Restarts'],
            [
                ['scheduler', '12346', '✅ Running', '5'],
            ]
        )
        ->assertSuccessful();
});

it('handles empty performer list', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

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

    $this->artisan('orchestral:encore')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Restarts'],
            []
        )
        ->expectsOutput('Total performers: 0 | Running: 0')
        ->assertSuccessful();
});

it('displays correct message for queue-worker performance', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with('queue-worker');

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with('queue-worker');

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [],
            'total_performers' => 0,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:encore', ['performance' => 'queue-worker', '--delay' => 0])
        ->expectsOutput('🔄 Preparing encore for: queue-worker')
        ->assertSuccessful();
});

it('displays correct message for scheduler performance', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with('scheduler');

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with('scheduler');

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [],
            'total_performers' => 0,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:encore', ['performance' => 'scheduler', '--delay' => 0])
        ->expectsOutput('🔄 Preparing encore for: scheduler')
        ->assertSuccessful();
});

it('handles high restart attempt counts', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('conduct')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'unstable-worker',
                    'pid' => 12345,
                    'running' => true,
                    'restart_attempts' => 999,
                ],
                [
                    'name' => 'stable-worker',
                    'pid' => 12346,
                    'running' => true,
                    'restart_attempts' => 0,
                ],
            ],
            'total_performers' => 2,
            'running_performers' => 2,
        ]);

    $this->artisan('orchestral:encore')
        ->expectsTable(
            ['Performer', 'PID', 'Status', 'Restarts'],
            [
                ['unstable-worker', '12345', '✅ Running', '999'],
                ['stable-worker', '12346', '✅ Running', '0'],
            ]
        )
        ->expectsOutput('Total performers: 2 | Running: 2')
        ->assertSuccessful();
});
