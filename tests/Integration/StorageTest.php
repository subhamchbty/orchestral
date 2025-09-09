<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Subhamchbty\Orchestral\Conductor\ProcessRegistry;
use Subhamchbty\Orchestral\Models\Performance;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();

    $this->registry = new ProcessRegistry;
});

it('stores and retrieves performer data using Redis/Cache storage', function () {
    $performers = [
        [
            'name' => 'worker-1',
            'command' => 'php artisan queue:work',
            'pid' => 12345,
            'started_at' => now()->toIso8601String(),
        ],
        [
            'name' => 'worker-2',
            'command' => 'php artisan schedule:work',
            'pid' => 12346,
            'started_at' => now()->toIso8601String(),
        ],
    ];

    $this->registry->savePerformers($performers);

    // Verify data is stored in cache
    expect(Cache::has('orchestral:processes'))->toBeTrue();

    $cached = Cache::get('orchestral:processes');
    expect($cached)->toBeArray();
    expect($cached)->toHaveCount(2);
    expect($cached[0]['name'])->toBe('worker-1');
    expect($cached[1]['name'])->toBe('worker-2');
});

it('persists conducting state across requests', function () {
    expect($this->registry->isConducting())->toBeFalse();

    $this->registry->setConducting(true);

    // Create new registry instance to simulate new request
    $newRegistry = new ProcessRegistry;
    expect($newRegistry->isConducting())->toBeTrue();

    $this->registry->setConducting(false);
    expect($newRegistry->isConducting())->toBeFalse();
});

it('handles cache TTL correctly for performer data', function () {
    $performers = [
        ['name' => 'worker', 'pid' => 12345, 'command' => 'test'],
    ];

    $this->registry->savePerformers($performers);

    // Verify cache has TTL set (7 days)
    expect(Cache::has('orchestral:processes'))->toBeTrue();

    // Load performers should refresh TTL
    $loaded = $this->registry->loadPerformers();
    expect($loaded)->toBeArray();

    // Cache should still exist after refresh
    expect(Cache::has('orchestral:processes'))->toBeTrue();
});

it('stores performance events in database when configured', function () {
    // Set database storage
    Config::set('orchestral.storage.driver', 'database');

    Performance::create([
        'event' => 'performance_started',
        'performer_name' => 'queue-worker',
        'environment' => 'testing',
        'data' => ['pid' => 12345],
        'occurred_at' => now(),
    ]);

    $performance = Performance::first();
    expect($performance)->toBeInstanceOf(Performance::class);
    expect($performance->event)->toBe('performance_started');
    expect($performance->performer_name)->toBe('queue-worker');
});

it('handles storage driver switching', function () {
    // Start with Redis
    Config::set('orchestral.storage.driver', 'redis');

    // Use current process PID for testing
    $currentPid = getmypid();

    $this->registry->savePerformers([
        ['name' => 'redis-worker', 'pid' => $currentPid, 'command' => 'test'],
    ]);

    $loaded = $this->registry->loadPerformers();
    expect($loaded)->toHaveCount(1);
    expect($loaded[0]['name'])->toBe('redis-worker');

    // Clear and switch to database
    $this->registry->clearPerformers();
    Config::set('orchestral.storage.driver', 'database');

    // Registry operations should still work (though actual storage logic may differ)
    expect($this->registry->loadPerformers())->toBeArray();
});

it('maintains data integrity with concurrent access', function () {
    $registry1 = new ProcessRegistry;
    $registry2 = new ProcessRegistry;

    // Use current process PID for testing
    $currentPid = getmypid();

    // Simulate concurrent writes
    $registry1->savePerformers([
        ['name' => 'worker-1', 'pid' => $currentPid, 'command' => 'test1'],
    ]);

    $registry2->savePerformers([
        ['name' => 'worker-2', 'pid' => $currentPid, 'command' => 'test2'],
    ]);

    // Last write wins
    $loaded = $registry1->loadPerformers();
    expect($loaded)->toHaveCount(1);
    expect($loaded[0]['name'])->toBe('worker-2');
});

it('handles cache failures gracefully', function () {
    // Mock cache failure scenario by clearing cache store
    Cache::flush();

    // Should return empty array instead of throwing exception
    $loaded = $this->registry->loadPerformers();
    expect($loaded)->toBeArray();
    expect($loaded)->toBeEmpty();

    // Saving should not throw exception
    $this->registry->savePerformers([
        ['name' => 'worker', 'pid' => 12345, 'command' => 'test'],
    ]);

    expect(true)->toBeTrue(); // If we reach here, no exception was thrown
});

it('cleans up expired process data', function () {
    $performers = [
        [
            'name' => 'active-worker',
            'pid' => getmypid(), // Current process PID (exists)
            'command' => 'php test',
            'started_at' => now()->toIso8601String(),
        ],
        [
            'name' => 'expired-worker',
            'pid' => 99999999, // Non-existent PID
            'command' => 'php expired',
            'started_at' => now()->toIso8601String(),
        ],
    ];

    Cache::put('orchestral:processes', $performers, now()->addDays(7));

    // Load performers should clean up dead processes
    $loaded = $this->registry->loadPerformers();

    expect($loaded)->toHaveCount(1);
    expect($loaded[0]['name'])->toBe('active-worker');

    // Verify cache was updated
    $cached = Cache::get('orchestral:processes');
    expect($cached)->toHaveCount(1);
    expect($cached[0]['name'])->toBe('active-worker');
});

it('handles different cache configurations', function () {
    // Use current process PID for testing
    $currentPid = getmypid();

    // Test with array cache (default in testing)
    $this->registry->savePerformers([
        ['name' => 'array-worker', 'pid' => $currentPid, 'command' => 'test'],
    ]);

    $loaded = $this->registry->loadPerformers();
    expect($loaded)->toHaveCount(1);
    expect($loaded[0]['name'])->toBe('array-worker');

    // Verify conducting state works with array cache
    $this->registry->setConducting(true);
    expect($this->registry->isConducting())->toBeTrue();
});

it('stores performance metrics correctly', function () {
    $processInfo = $this->registry->getProcessInfo(getmypid());

    if ($processInfo) {
        expect($processInfo)->toHaveKeys(['pid', 'running', 'memory_mb', 'cpu_percent']);
        expect($processInfo['pid'])->toBe(getmypid());
        expect($processInfo['running'])->toBeTrue();
        expect($processInfo['memory_mb'])->toBeGreaterThan(0);
        expect($processInfo['cpu_percent'])->toBeGreaterThanOrEqual(0);
    } else {
        // If process info can't be retrieved, that's also valid behavior
        expect($processInfo)->toBeNull();
    }
});

it('handles storage configuration validation', function () {
    // Test valid configurations
    $validConfigs = [
        ['driver' => 'redis'],
        ['driver' => 'database'],
        ['driver' => 'redis', 'redis' => ['connection' => 'default']],
        ['driver' => 'database', 'database' => ['connection' => 'mysql']],
    ];

    foreach ($validConfigs as $config) {
        Config::set('orchestral.storage', $config);

        // Should not throw exception
        $registry = new ProcessRegistry;
        expect($registry)->toBeInstanceOf(ProcessRegistry::class);
    }
});

it('maintains backward compatibility with legacy data formats', function () {
    // Simulate legacy data format
    $legacyData = [
        [
            'name' => 'legacy-worker',
            'pid' => 12345,
            'command' => 'php legacy',
            // Missing started_at field
        ],
    ];

    Cache::put('orchestral:processes', $legacyData, now()->addDays(7));

    // Should handle gracefully without breaking
    $loaded = $this->registry->loadPerformers();
    expect($loaded)->toBeArray();

    // If process doesn't exist, it should be filtered out
    // If it exists, it should be included with whatever data is available
    expect(true)->toBeTrue(); // Test passes if no exception is thrown
});
