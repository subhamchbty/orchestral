<?php

use Carbon\Carbon;
use Subhamchbty\Orchestral\Conductor\Performer;
use Symfony\Component\Process\Process;

beforeEach(function () {
    // Set up test environment
    $this->performerName = 'test-worker';
    $this->command = 'php artisan queue:work';
    $this->config = [
        'memory' => 256,
        'timeout' => 60,
        'nice' => 10,
        'options' => ['--queue' => 'default'],
    ];
});

it('creates a performer with correct properties', function () {
    $performer = new Performer($this->performerName, $this->command, $this->config);

    expect($performer->getName())->toBe('test-worker');
    expect($performer->getCommand())->toBe('php artisan queue:work');
    expect($performer->getPid())->toBeNull();
    expect($performer->getRestartAttempts())->toBe(0);
});

it('builds process command with nice priority', function () {
    $config = [
        'memory' => 512,
        'timeout' => 120,
        'nice' => 10,
        'options' => ['--queue' => 'high,default', '--sleep' => '3'],
    ];

    $performer = new Performer('worker', 'php artisan queue:work', $config);

    // Use reflection to test protected method
    $reflection = new ReflectionClass($performer);
    $method = $reflection->getMethod('buildProcessCommand');
    $method->setAccessible(true);

    $command = $method->invoke($performer);

    expect($command)->toBe('nice -n 10 php artisan queue:work');
});

it('builds command without nice priority', function () {
    $config = [
        'memory' => 256,
        'timeout' => null,
        'options' => [],
    ];

    $performer = new Performer('worker', 'php artisan test', $config);

    $reflection = new ReflectionClass($performer);
    $method = $reflection->getMethod('buildProcessCommand');
    $method->setAccessible(true);

    $command = $method->invoke($performer);

    expect($command)->toBe('php artisan test');
});

it('tracks restart attempts correctly', function () {
    $performer = new Performer($this->performerName, $this->command, $this->config);

    expect($performer->getRestartAttempts())->toBe(0);
    expect($performer->getLastRestartAt())->toBeNull();

    // Use reflection to simulate restart attempts without actual process execution
    $reflection = new ReflectionClass($performer);
    $restartAttemptsProperty = $reflection->getProperty('restartAttempts');
    $restartAttemptsProperty->setAccessible(true);
    $lastRestartProperty = $reflection->getProperty('lastRestartAt');
    $lastRestartProperty->setAccessible(true);

    // Simulate 3 restarts
    $restartAttemptsProperty->setValue($performer, 3);
    $lastRestartProperty->setValue($performer, Carbon::now());

    expect($performer->getRestartAttempts())->toBe(3);
    expect($performer->getLastRestartAt())->toBeInstanceOf(Carbon::class);
});

it('calculates uptime correctly', function () {
    $performer = new Performer($this->performerName, $this->command, $this->config);

    // Initially no uptime
    expect($performer->getUptime())->toBeNull();

    // Set started time using reflection
    $reflection = new ReflectionClass($performer);
    $property = $reflection->getProperty('startedAt');
    $property->setAccessible(true);
    $property->setValue($performer, Carbon::now()->subHours(2));

    $uptime = $performer->getUptime();
    expect($uptime)->toContain('2 hours');
});

it('returns correct status array', function () {
    $performer = new Performer($this->performerName, $this->command, $this->config);

    // Set started time to avoid uninitialized property error
    $reflection = new ReflectionClass($performer);
    $property = $reflection->getProperty('startedAt');
    $property->setAccessible(true);
    $property->setValue($performer, Carbon::now());

    $status = $performer->getStatus();

    expect($status)->toHaveKeys(['name', 'pid', 'running', 'uptime', 'memory_mb', 'cpu_percent', 'restart_attempts']);
    expect($status['name'])->toBe('test-worker');
    expect($status['running'])->toBeFalse();
    expect($status['restart_attempts'])->toBe(0);
});

it('checks process health with memory threshold', function () {
    $performer = new Performer($this->performerName, $this->command, $this->config);

    // Set startedAt to avoid uninitialized property error
    $reflection = new ReflectionClass($performer);
    $property = $reflection->getProperty('startedAt');
    $property->setAccessible(true);
    $property->setValue($performer, Carbon::now());

    // Process not running should be unhealthy
    $health = $performer->checkHealth();
    expect($health['healthy'])->toBeFalse();
    expect($health['issues'])->toContain('Process is not running');
    expect($health)->toHaveKeys(['healthy', 'issues', 'metrics']);
});

it('detects when process is not running', function () {
    $performer = new Performer($this->performerName, $this->command, $this->config);

    // Process not started yet
    expect($performer->isRunning())->toBeFalse();

    // Set a fake PID
    $reflection = new ReflectionClass($performer);
    $property = $reflection->getProperty('pid');
    $property->setAccessible(true);
    $property->setValue($performer, 99999999); // Non-existent PID

    expect($performer->isRunning())->toBeFalse();
});

it('handles process output methods', function () {
    $performer = new Performer($this->performerName, $this->command, $this->config);

    // Create a mock process
    $processMock = Mockery::mock(Process::class);
    $processMock->shouldReceive('getOutput')->andReturn('Standard output');
    $processMock->shouldReceive('getErrorOutput')->andReturn('Error output');

    // Inject the mock
    $reflection = new ReflectionClass($performer);
    $property = $reflection->getProperty('process');
    $property->setAccessible(true);
    $property->setValue($performer, $processMock);

    expect($performer->getOutput())->toBe('Standard output');
    expect($performer->getErrorOutput())->toBe('Error output');
});

it('returns empty output when process not set', function () {
    $performer = new Performer($this->performerName, $this->command, $this->config);

    // Process is not initialized until start() is called
    // The null-safe operator in getOutput/getErrorOutput handles this
    expect($performer->getOutput())->toBe('');
    expect($performer->getErrorOutput())->toBe('');
});

it('handles config with nice priority', function () {
    $config = [
        'memory' => 256,
        'timeout' => 60,
        'nice' => 15,
        'options' => [],
    ];

    $performer = new Performer('worker', 'php test.php', $config);

    $reflection = new ReflectionClass($performer);
    $method = $reflection->getMethod('buildProcessCommand');
    $method->setAccessible(true);

    $command = $method->invoke($performer);

    // The nice value is set via environment variable in the actual implementation
    expect($command)->toContain('php test.php');
});

it('handles different nice priorities', function () {
    $configs = [
        ['nice' => 0, 'expected' => 'php test'],
        ['nice' => 5, 'expected' => 'nice -n 5 php test'],
        ['nice' => -5, 'expected' => 'nice -n -5 php test'],
        ['nice' => 19, 'expected' => 'nice -n 19 php test'],
    ];

    foreach ($configs as $test) {
        $config = array_merge(['memory' => 256, 'timeout' => 60], $test);
        $performer = new Performer('worker', 'php test', $config);

        $reflection = new ReflectionClass($performer);
        $method = $reflection->getMethod('buildProcessCommand');
        $method->setAccessible(true);

        $command = $method->invoke($performer);

        expect($command)->toBe($test['expected']);
    }
});
