<?php

use Illuminate\Support\Facades\Cache;
use Subhamchbty\Orchestral\Conductor\Conductor;
use Subhamchbty\Orchestral\Conductor\Performer;
use Subhamchbty\Orchestral\Conductor\ProcessRegistry;
use Subhamchbty\Orchestral\Conductor\Score;

beforeEach(function () {
    Cache::flush();

    $this->config = [
        'performances' => [
            'testing' => [
                'test-worker' => [
                    'command' => 'sleep 1', // Simple command for testing
                    'performers' => 1,
                    'memory' => 256,
                    'timeout' => 30,
                    'options' => [],
                ],
                'multi-worker' => [
                    'command' => 'sleep 2',
                    'performers' => 2,
                    'memory' => 128,
                    'timeout' => 60,
                    'options' => [],
                ],
            ],
        ],
        'management' => [
            'restart_on_failure' => true,
            'restart_delay' => 1,
            'max_restart_attempts' => 3,
        ],
        'storage' => [
            'driver' => 'redis',
        ],
    ];

    $this->score = new Score($this->config);
    $this->registry = new ProcessRegistry;
    $this->conductor = new Conductor($this->score, $this->config, $this->registry);
});

it('manages full process lifecycle from start to stop', function () {
    // Initially no performers
    expect($this->conductor->isConducting())->toBeFalse();
    expect($this->conductor->getPerformers())->toHaveCount(0);

    // Start performance
    $this->conductor->conduct('test-worker');

    expect($this->conductor->isConducting())->toBeTrue();

    // Give processes time to start
    usleep(600000); // 0.6 seconds

    $status = $this->conductor->getStatus();
    expect($status['conducting'])->toBeTrue();
    expect($status['total_performers'])->toBeGreaterThanOrEqual(1);

    // Stop performance
    $this->conductor->pause();

    expect($this->conductor->isConducting())->toBeFalse();

    // Give processes time to stop
    sleep(1);

    $finalStatus = $this->conductor->getStatus();
    expect($finalStatus['conducting'])->toBeFalse();
})->skip('Process management requires actual process spawning which is environment-dependent');

it('handles multiple performers correctly', function () {
    $this->conductor->conduct('multi-worker');

    expect($this->conductor->isConducting())->toBeTrue();

    // Give processes time to start
    usleep(600000);

    $status = $this->conductor->getStatus();
    expect($status['conducting'])->toBeTrue();

    // Should have 2 performers for multi-worker
    $instruments = $this->conductor->getInstruments();
    expect($instruments['multi-worker']['performers'])->toBe(2);

    $this->conductor->pause();

    // Give processes time to stop
    sleep(1);
});

it('persists process state across conductor instances', function () {
    // Start with first conductor
    $this->conductor->conduct('test-worker');
    expect($this->conductor->isConducting())->toBeTrue();

    // Give processes time to start
    usleep(600000);

    // Create new conductor instance (simulating new request/restart)
    $newConductor = new Conductor($this->score, $this->config, $this->registry);

    // Should remember conducting state
    expect($newConductor->isConducting())->toBeTrue();

    $status = $newConductor->getStatus();
    expect($status['conducting'])->toBeTrue();

    // Clean up
    $newConductor->pause();
    sleep(1);
});

it('handles process monitoring and health checks', function () {
    $this->conductor->conduct('test-worker');

    // Give processes time to start
    usleep(600000);

    // Monitor performers
    $this->conductor->monitorPerformers();

    // Health check should return data
    $health = $this->conductor->healthCheck();
    expect($health)->toBeArray();

    // Get detailed status
    $status = $this->conductor->getStatus();
    expect($status)->toHaveKeys(['conducting', 'environment', 'performers', 'total_performers', 'running_performers']);

    $this->conductor->pause();
    sleep(1);
});

it('manages performer restart lifecycle', function () {
    // Create a simple performer that will exit quickly
    $performer = new Performer('test-performer', 'echo "test"', [
        'memory' => 256,
        'timeout' => 30,
        'options' => [],
    ]);

    // Initially not running
    expect($performer->isRunning())->toBeFalse();
    expect($performer->getRestartAttempts())->toBe(0);

    // Start process
    $performer->start();

    // Give it time to complete (echo exits immediately)
    sleep(1);

    // Process should have completed
    expect($performer->getPid())->toBeNull(); // echo command doesn't output PID in our implementation
})->skip('Process spawning is environment-dependent');

it('handles concurrent process management', function () {
    // Start multiple performances
    $this->conductor->conduct('test-worker');
    usleep(300000);

    $this->conductor->conduct('multi-worker');
    usleep(300000);

    expect($this->conductor->isConducting())->toBeTrue();

    $status = $this->conductor->getStatus();
    expect($status['conducting'])->toBeTrue();

    // Should handle multiple performance types
    $instruments = $this->conductor->getInstruments();
    expect($instruments)->toHaveKeys(['test-worker', 'multi-worker']);

    $this->conductor->pause();
    sleep(2);
});

it('handles graceful shutdown sequences', function () {
    $this->conductor->conduct('test-worker');
    expect($this->conductor->isConducting())->toBeTrue();

    usleep(600000);

    // Graceful pause
    $this->conductor->pause();

    expect($this->conductor->isConducting())->toBeFalse();

    // Should clear process registry
    sleep(1);
    $performers = $this->registry->loadPerformers();
    expect($performers)->toBeEmpty();
});

it('handles process failure and cleanup', function () {
    // Create performer with command that will fail
    $performer = new Performer('failing-performer', 'nonexistent-command', [
        'memory' => 256,
        'timeout' => 5,
        'options' => [],
    ]);

    // Start should not throw exception even if command fails
    $performer->start();

    // Process should not be running due to command failure
    expect($performer->isRunning())->toBeFalse();

    // Should be able to get status even for failed process
    $status = $performer->getStatus();
    expect($status)->toHaveKeys(['name', 'pid', 'running']);
    expect($status['running'])->toBeFalse();
})->skip('Process spawning is environment-dependent');

it('manages encore (restart) functionality', function () {
    $this->conductor->conduct('test-worker');
    expect($this->conductor->isConducting())->toBeTrue();

    usleep(600000);

    // Encore should pause and then restart
    $this->conductor->encore();

    expect($this->conductor->isConducting())->toBeTrue();

    // Clean up
    $this->conductor->pause();
    sleep(1);
});

it('handles process registry data persistence', function () {
    // Save some performer data
    $performerData = [
        [
            'name' => 'persistent-worker',
            'pid' => 12345,
            'command' => 'php test',
            'started_at' => now()->toIso8601String(),
        ],
    ];

    $this->registry->savePerformers($performerData);
    $this->registry->setConducting(true);

    // Create new registry instance
    $newRegistry = new ProcessRegistry;

    // Should persist state
    expect($newRegistry->isConducting())->toBeTrue();

    // Clean up
    $this->registry->setConducting(false);
    $this->registry->clearPerformers();
});

it('validates process configuration before starting', function () {
    $instruments = $this->conductor->getInstruments();

    expect($instruments)->toBeArray();
    expect($instruments)->toHaveKeys(['test-worker', 'multi-worker']);

    foreach ($instruments as $name => $config) {
        expect($config)->toHaveKeys(['command', 'performers', 'memory']);
        expect($config['command'])->toBeString();
        expect($config['performers'])->toBeInt();
        expect($config['performers'])->toBeGreaterThan(0);
        expect($config['memory'])->toBeInt();
        expect($config['memory'])->toBeGreaterThan(0);
    }
});

it('handles environment-specific process management', function () {
    expect($this->score->getEnvironment())->toBe('testing');

    $performances = $this->score->getPerformances();
    expect($performances)->toHaveKeys(['test-worker', 'multi-worker']);

    // Should only get performances for current environment
    expect($performances)->not->toBeEmpty();
});

it('manages process cleanup on application shutdown', function () {
    $this->conductor->conduct('test-worker');
    usleep(600000);

    expect($this->conductor->isConducting())->toBeTrue();

    // Simulate application shutdown
    $this->conductor->pause();

    expect($this->conductor->isConducting())->toBeFalse();

    // Registry should be cleared
    $performers = $this->registry->loadPerformers();
    expect($performers)->toBeEmpty();

    sleep(1);
});
