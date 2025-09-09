<?php

use Subhamchbty\Orchestral\Conductor\Conductor;

beforeEach(function () {
    // Mock the Conductor
    $this->conductor = Mockery::mock(Conductor::class);
    $this->app->instance(Conductor::class, $this->conductor);
});

it('pauses all performances', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'queue-worker',
                    'pid' => null,
                    'running' => false,
                ],
                [
                    'name' => 'scheduler',
                    'pid' => null,
                    'running' => false,
                ],
            ],
            'total_performers' => 2,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:pause')
        ->expectsOutput('queue-worker: Stopped')
        ->expectsOutput('scheduler: Stopped')
        ->expectsOutput('Stopped: 2 performers')
        ->assertSuccessful();
});

it('pauses a specific performance', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with('queue-worker');

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'queue-worker',
                    'pid' => null,
                    'running' => false,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:pause', ['performance' => 'queue-worker'])
        ->expectsOutput('queue-worker: Stopped')
        ->expectsOutput('Stopped: 1 performers')
        ->assertSuccessful();
});

it('shows still running performers after pause attempt', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'worker-1',
                    'pid' => null,
                    'running' => false,
                ],
                [
                    'name' => 'worker-2',
                    'pid' => 12345,
                    'running' => true,
                ],
                [
                    'name' => 'worker-3',
                    'pid' => null,
                    'running' => false,
                ],
            ],
            'total_performers' => 3,
            'running_performers' => 1,
        ]);

    $this->artisan('orchestral:pause')
        ->expectsOutput('worker-1: Stopped')
        ->expectsOutput('worker-2: Still Running')
        ->expectsOutput('worker-3: Stopped')
        ->expectsOutput('Stopped: 3 performers')
        ->assertSuccessful();
});

it('handles exceptions gracefully', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Unable to connect to process manager'));

    $this->artisan('orchestral:pause')
        ->expectsOutput('❌ Error: Unable to connect to process manager')
        ->assertFailed();
});

it('handles exception for specific performance', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with('invalid-worker')
        ->andThrow(new Exception('Performance not found: invalid-worker'));

    $this->artisan('orchestral:pause', ['performance' => 'invalid-worker'])
        ->expectsOutput('❌ Error: Performance not found: invalid-worker')
        ->assertFailed();
});

it('handles empty performer list', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [],
            'total_performers' => 0,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:pause')
        ->doesntExpectOutput('Stopped: 0 performers')
        ->assertSuccessful();
});

it('accepts graceful option', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'worker',
                    'pid' => null,
                    'running' => false,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:pause', ['--graceful' => true])
        ->expectsOutput('worker: Stopped')
        ->expectsOutput('Stopped: 1 performers')
        ->assertSuccessful();
});

it('pauses specific performance with graceful option', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with('scheduler');

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'scheduler',
                    'pid' => null,
                    'running' => false,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:pause', [
        'performance' => 'scheduler',
        '--graceful' => true,
    ])
        ->expectsOutput('scheduler: Stopped')
        ->expectsOutput('Stopped: 1 performers')
        ->assertSuccessful();
});

it('displays all stopped performers correctly', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'high-priority-worker',
                    'pid' => null,
                    'running' => false,
                ],
                [
                    'name' => 'medium-priority-worker',
                    'pid' => null,
                    'running' => false,
                ],
                [
                    'name' => 'low-priority-worker',
                    'pid' => null,
                    'running' => false,
                ],
                [
                    'name' => 'scheduler',
                    'pid' => null,
                    'running' => false,
                ],
                [
                    'name' => 'horizon',
                    'pid' => null,
                    'running' => false,
                ],
            ],
            'total_performers' => 5,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:pause')
        ->expectsOutput('high-priority-worker: Stopped')
        ->expectsOutput('medium-priority-worker: Stopped')
        ->expectsOutput('low-priority-worker: Stopped')
        ->expectsOutput('scheduler: Stopped')
        ->expectsOutput('horizon: Stopped')
        ->expectsOutput('Stopped: 5 performers')
        ->assertSuccessful();
});

it('displays mixed status when some performers fail to stop', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'stubborn-worker-1',
                    'pid' => 12345,
                    'running' => true,
                ],
                [
                    'name' => 'compliant-worker',
                    'pid' => null,
                    'running' => false,
                ],
                [
                    'name' => 'stubborn-worker-2',
                    'pid' => 12346,
                    'running' => true,
                ],
            ],
            'total_performers' => 3,
            'running_performers' => 2,
        ]);

    $this->artisan('orchestral:pause')
        ->expectsOutput('stubborn-worker-1: Still Running')
        ->expectsOutput('compliant-worker: Stopped')
        ->expectsOutput('stubborn-worker-2: Still Running')
        ->expectsOutput('Stopped: 3 performers')
        ->assertSuccessful();
});

it('handles process not found exception', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Process not found'));

    $this->artisan('orchestral:pause')
        ->expectsOutput('❌ Error: Process not found')
        ->assertFailed();
});

it('handles permission denied exception', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Permission denied'));

    $this->artisan('orchestral:pause')
        ->expectsOutput('❌ Error: Permission denied')
        ->assertFailed();
});

it('handles storage backend unavailable exception', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null)
        ->andThrow(new Exception('Storage backend unavailable'));

    $this->artisan('orchestral:pause')
        ->expectsOutput('❌ Error: Storage backend unavailable')
        ->assertFailed();
});

it('pauses queue-worker performance', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with('queue-worker');

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'queue-worker',
                    'pid' => null,
                    'running' => false,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:pause', ['performance' => 'queue-worker'])
        ->expectsOutput('queue-worker: Stopped')
        ->expectsOutput('Stopped: 1 performers')
        ->assertSuccessful();
});

it('pauses scheduler performance', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with('scheduler');

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'scheduler',
                    'pid' => null,
                    'running' => false,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:pause', ['performance' => 'scheduler'])
        ->expectsOutput('scheduler: Stopped')
        ->expectsOutput('Stopped: 1 performers')
        ->assertSuccessful();
});

it('shows correct count when single performer stops', function () {
    $this->conductor->shouldReceive('pause')
        ->once()
        ->with(null);

    $this->conductor->shouldReceive('getStatus')
        ->once()
        ->andReturn([
            'performers' => [
                [
                    'name' => 'single-worker',
                    'pid' => null,
                    'running' => false,
                ],
            ],
            'total_performers' => 1,
            'running_performers' => 0,
        ]);

    $this->artisan('orchestral:pause')
        ->expectsOutput('single-worker: Stopped')
        ->expectsOutput('Stopped: 1 performers')
        ->assertSuccessful();
});
