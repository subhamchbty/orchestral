<?php

use Illuminate\Support\Collection;
use Subhamchbty\Orchestral\Conductor\Conductor;
use Subhamchbty\Orchestral\Conductor\ProcessRegistry;
use Subhamchbty\Orchestral\Conductor\Score;

beforeEach(function () {
    $this->score = Mockery::mock(Score::class);
    $this->registry = Mockery::mock(ProcessRegistry::class);
    $this->config = [
        'performances' => [
            'local' => [
                'worker' => [
                    'command' => 'php artisan queue:work',
                    'performers' => 2,
                    'memory' => 256,
                ],
            ],
        ],
        'management' => [
            'restart_on_failure' => true,
            'restart_delay' => 5,
        ],
        'storage' => [
            'driver' => 'redis',
        ],
    ];

    // Setup default expectations for constructor
    $this->registry->shouldReceive('loadPerformers')
        ->andReturn([]);
    $this->registry->shouldReceive('isConducting')
        ->andReturn(false);

    $this->conductor = new Conductor($this->score, $this->config, $this->registry);
});

it('conducts all performances when no specific name given', function () {
    $performances = [
        'worker-1' => ['command' => 'php test1', 'performers' => 1],
        'worker-2' => ['command' => 'php test2', 'performers' => 2],
    ];

    $this->score->shouldReceive('getPerformances')
        ->once()
        ->andReturn($performances);

    $this->score->shouldReceive('buildCommand')
        ->with(['command' => 'php test1', 'performers' => 1])
        ->andReturn('php test1');
    $this->score->shouldReceive('buildCommand')
        ->with(['command' => 'php test2', 'performers' => 2])
        ->andReturn('php test2');

    $this->registry->shouldReceive('setConducting')
        ->once()
        ->with(true);

    $this->registry->shouldReceive('savePerformers')
        ->once();

    $this->conductor->conduct();

    expect($this->conductor->isConducting())->toBeTrue();
});

it('conducts specific performance when name provided', function () {
    $performance = ['command' => 'php test', 'performers' => 1];

    $this->score->shouldReceive('getPerformance')
        ->once()
        ->with('worker')
        ->andReturn($performance);

    $this->score->shouldReceive('buildCommand')
        ->with($performance)
        ->andReturn('php test');

    $this->registry->shouldReceive('setConducting')
        ->once()
        ->with(true);

    $this->registry->shouldReceive('savePerformers')
        ->once();

    $this->conductor->conduct('worker');

    expect($this->conductor->isConducting())->toBeTrue();
});

it('pauses all performances', function () {
    $this->registry->shouldReceive('setConducting')
        ->once()
        ->with(false);

    $this->registry->shouldReceive('clearPerformers')
        ->once();

    $this->conductor->pause();

    expect($this->conductor->isConducting())->toBeFalse();
});

it('gets status of performers', function () {
    $performerData = [
        [
            'name' => 'worker',
            'pid' => 12345,
            'command' => 'php test',
            'started_at' => now()->toIso8601String(),
        ],
    ];

    // Reset expectations to avoid conflicts with beforeEach
    $registry = Mockery::mock(ProcessRegistry::class);
    $registry->shouldReceive('loadPerformers')
        ->andReturn($performerData);
    $registry->shouldReceive('isConducting')
        ->andReturn(false);
    $registry->shouldReceive('getProcessInfo')
        ->with(12345)
        ->andReturn([
            'pid' => 12345,
            'running' => true,
            'memory_mb' => 128,
            'cpu_percent' => 5.5,
        ]);

    $score = Mockery::mock(Score::class);
    $score->shouldReceive('getEnvironment')
        ->andReturn('testing');

    $conductor = new Conductor($score, $this->config, $registry);
    $status = $conductor->getStatus();

    expect($status)->toHaveKeys(['environment', 'conducting', 'performers', 'total_performers', 'running_performers']);
    expect($status['environment'])->toBe('testing');
    expect($status['conducting'])->toBeFalse();
    expect($status['performers'])->toHaveCount(1);
    expect($status['total_performers'])->toBe(1);
    expect($status['running_performers'])->toBe(1);
});

it('gets instruments configuration', function () {
    $performances = [
        'worker' => [
            'command' => 'php artisan queue:work',
            'performers' => 2,
            'memory' => 256,
            'timeout' => 60,
            'options' => ['--queue' => 'default'],
        ],
    ];

    $this->score->shouldReceive('getPerformances')
        ->once()
        ->andReturn($performances);

    $instruments = $this->conductor->getInstruments();

    expect($instruments)->toHaveCount(1);
    expect($instruments['worker'])->toHaveKeys(['command', 'performers', 'memory', 'timeout', 'options']);
    expect($instruments['worker']['command'])->toBe('php artisan queue:work');
    expect($instruments['worker']['performers'])->toBe(2);
});

it('performs health check on performers', function () {
    // Health check works on the performers collection which is empty by default
    $health = $this->conductor->healthCheck();

    expect($health)->toBeArray();
    expect($health)->toBeEmpty();
});

it('monitors performers and handles unhealthy ones', function () {
    $performerData = [
        [
            'name' => 'worker',
            'pid' => 12345,
            'command' => 'php test',
            'started_at' => now()->subMinutes(10)->toIso8601String(),
        ],
    ];

    // Reset expectations to avoid conflicts with beforeEach
    $registry = Mockery::mock(ProcessRegistry::class);
    $registry->shouldReceive('loadPerformers')
        ->once() // Constructor
        ->andReturn([]);
    $registry->shouldReceive('loadPerformers')
        ->once() // monitorPerformers
        ->andReturn($performerData);
    $registry->shouldReceive('isConducting')
        ->andReturn(false);
    $registry->shouldReceive('getProcessInfo')
        ->once()
        ->with(12345)
        ->andReturn(null); // Process not running

    $score = Mockery::mock(Score::class);
    $score->shouldReceive('shouldRestartOnFailure')
        ->once()
        ->andReturn(false); // Don't restart to avoid savePerformers call

    $conductor = new Conductor($score, $this->config, $registry);
    $conductor->monitorPerformers();

    // Assert that the monitoring happened without errors
    expect(true)->toBeTrue();
});

it('returns conducting state', function () {
    expect($this->conductor->isConducting())->toBeFalse();

    $this->registry->shouldReceive('setConducting')
        ->with(true);

    $this->registry->shouldReceive('savePerformers');

    $this->score->shouldReceive('getPerformances')
        ->andReturn([]);

    $this->conductor->conduct();

    expect($this->conductor->isConducting())->toBeTrue();
});

it('gets performers collection', function () {
    $performers = $this->conductor->getPerformers();

    expect($performers)->toBeInstanceOf(Collection::class);
});

it('handles empty performances gracefully', function () {
    $this->score->shouldReceive('getPerformances')
        ->once()
        ->andReturn([]);

    $this->registry->shouldReceive('setConducting')
        ->once()
        ->with(true);

    $this->registry->shouldReceive('savePerformers')
        ->once()
        ->with([]);

    $this->conductor->conduct();

    expect($this->conductor->isConducting())->toBeTrue();
});

it('handles null performance config', function () {
    $this->score->shouldReceive('getPerformance')
        ->once()
        ->with('non-existent')
        ->andReturn(null);

    $this->registry->shouldReceive('setConducting')
        ->once()
        ->with(true);

    $this->registry->shouldReceive('savePerformers')
        ->once()
        ->with([]);

    $this->conductor->conduct('non-existent');

    expect($this->conductor->isConducting())->toBeTrue();
});

it('handles encore functionality', function () {
    // Setup for pause
    $this->registry->shouldReceive('setConducting')
        ->with(false);

    $this->registry->shouldReceive('clearPerformers');

    // Setup for conduct
    $this->score->shouldReceive('getPerformances')
        ->andReturn([]);

    $this->registry->shouldReceive('setConducting')
        ->with(true);

    $this->registry->shouldReceive('savePerformers');

    $this->conductor->encore();

    expect($this->conductor->isConducting())->toBeTrue();
});

it('calculates uptime correctly', function () {
    $reflection = new ReflectionClass($this->conductor);
    $method = $reflection->getMethod('calculateUptime');
    $method->setAccessible(true);

    $startedAt = now()->subHours(2)->toIso8601String();
    $uptime = $method->invoke($this->conductor, $startedAt);

    expect($uptime)->toContain('2 hours');
});

it('checks if should use database storage', function () {
    $reflection = new ReflectionClass($this->conductor);
    $method = $reflection->getMethod('shouldUseDatabase');
    $method->setAccessible(true);

    // Default config has redis driver
    expect($method->invoke($this->conductor))->toBeFalse();

    // Test with database driver
    $conductor = new Conductor($this->score, [
        'storage' => ['driver' => 'database'],
    ], $this->registry);

    expect($method->invoke($conductor))->toBeTrue();
});
