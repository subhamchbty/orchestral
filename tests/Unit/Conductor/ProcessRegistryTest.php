<?php

use Illuminate\Support\Facades\Cache;
use Subhamchbty\Orchestral\Conductor\Performer;
use Subhamchbty\Orchestral\Conductor\ProcessRegistry;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();

    $this->registry = new ProcessRegistry;
});

it('saves performers to cache', function () {
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

    $cached = Cache::get('orchestral:processes');
    expect($cached)->toBeArray();
    expect($cached)->toHaveCount(2);
    expect($cached[0]['name'])->toBe('worker-1');
    expect($cached[1]['name'])->toBe('worker-2');
});

it('saves performer objects to cache', function () {
    $performer1 = Mockery::mock(Performer::class);
    $performer1->shouldReceive('getPid')->andReturn(12345);
    $performer1->shouldReceive('getName')->andReturn('worker-1');
    $performer1->shouldReceive('getCommand')->andReturn('php artisan queue:work');

    $performer2 = Mockery::mock(Performer::class);
    $performer2->shouldReceive('getPid')->andReturn(12346);
    $performer2->shouldReceive('getName')->andReturn('worker-2');
    $performer2->shouldReceive('getCommand')->andReturn('php artisan schedule:work');

    $this->registry->savePerformers([$performer1, $performer2]);

    $cached = Cache::get('orchestral:processes');
    expect($cached)->toBeArray();
    expect($cached)->toHaveCount(2);
    expect($cached[0]['pid'])->toBe(12345);
    expect($cached[1]['pid'])->toBe(12346);
});

it('loads active performers from cache', function () {
    // Save some performers first
    $performers = [
        [
            'name' => 'worker-1',
            'command' => 'php artisan queue:work',
            'pid' => getmypid(), // Use current process PID (which exists)
            'started_at' => now()->toIso8601String(),
        ],
        [
            'name' => 'worker-2',
            'command' => 'php artisan schedule:work',
            'pid' => 99999999, // Non-existent PID
            'started_at' => now()->toIso8601String(),
        ],
    ];

    Cache::put('orchestral:processes', $performers, now()->addDays(7));

    $loaded = $this->registry->loadPerformers();

    // Only the process with existing PID should be returned
    expect($loaded)->toBeArray();
    expect($loaded)->toHaveCount(1);
    expect($loaded[0]['name'])->toBe('worker-1');
});

it('cleans up dead processes when loading', function () {
    $performers = [
        [
            'name' => 'active-worker',
            'command' => 'php artisan queue:work',
            'pid' => getmypid(),
            'started_at' => now()->toIso8601String(),
        ],
        [
            'name' => 'dead-worker-1',
            'command' => 'php artisan test',
            'pid' => 99999998,
            'started_at' => now()->toIso8601String(),
        ],
        [
            'name' => 'dead-worker-2',
            'command' => 'php artisan test2',
            'pid' => 99999999,
            'started_at' => now()->toIso8601String(),
        ],
    ];

    Cache::put('orchestral:processes', $performers, now()->addDays(7));

    $loaded = $this->registry->loadPerformers();

    // Should only return active process
    expect($loaded)->toHaveCount(1);

    // Cache should be updated with only active processes
    $cached = Cache::get('orchestral:processes');
    expect($cached)->toHaveCount(1);
    expect($cached[0]['name'])->toBe('active-worker');
});

it('returns empty array when no performers in cache', function () {
    $loaded = $this->registry->loadPerformers();

    expect($loaded)->toBeArray();
    expect($loaded)->toBeEmpty();
});

it('clears all performers from cache', function () {
    $performers = [
        ['name' => 'worker-1', 'pid' => 12345],
        ['name' => 'worker-2', 'pid' => 12346],
    ];

    Cache::put('orchestral:processes', $performers, now()->addDays(7));

    expect(Cache::has('orchestral:processes'))->toBeTrue();

    $this->registry->clearPerformers();

    expect(Cache::has('orchestral:processes'))->toBeFalse();
});

it('manages conducting state', function () {
    expect($this->registry->isConducting())->toBeFalse();

    $this->registry->setConducting(true);
    expect($this->registry->isConducting())->toBeTrue();
    expect(Cache::has('orchestral:conducting'))->toBeTrue();

    $this->registry->setConducting(false);
    expect($this->registry->isConducting())->toBeFalse();
    expect(Cache::has('orchestral:conducting'))->toBeFalse();
});

it('gets process info for running process', function () {
    $pid = getmypid();
    $info = $this->registry->getProcessInfo($pid);

    expect($info)->toBeArray();
    expect($info['pid'])->toBe($pid);
    expect($info['running'])->toBeTrue();
    expect($info)->toHaveKeys(['memory_mb', 'cpu_percent']);
});

it('returns null for non-existent process', function () {
    $info = $this->registry->getProcessInfo(99999999);

    expect($info)->toBeNull();
});

it('returns null for zero pid in process info', function () {
    $info = $this->registry->getProcessInfo(0);

    expect($info)->toBeNull();
});

it('refreshes cache TTL when loading performers', function () {
    $performers = [
        [
            'name' => 'worker',
            'command' => 'php test',
            'pid' => getmypid(),
            'started_at' => now()->toIso8601String(),
        ],
    ];

    // Put with short TTL
    Cache::put('orchestral:processes', $performers, now()->addMinutes(1));

    // Load performers (should refresh TTL)
    $this->registry->loadPerformers();

    // Cache should still exist with refreshed TTL
    expect(Cache::has('orchestral:processes'))->toBeTrue();
});

it('handles mixed performer data types when saving', function () {
    $performerObject = Mockery::mock(Performer::class);
    $performerObject->shouldReceive('getPid')->andReturn(12345);
    $performerObject->shouldReceive('getName')->andReturn('object-worker');
    $performerObject->shouldReceive('getCommand')->andReturn('php object');

    $performerArray = [
        'name' => 'array-worker',
        'command' => 'php array',
        'pid' => 12346,
        'started_at' => now()->toIso8601String(),
    ];

    $this->registry->savePerformers([$performerObject, $performerArray]);

    $cached = Cache::get('orchestral:processes');
    expect($cached)->toHaveCount(2);
    expect($cached[0]['name'])->toBe('object-worker');
    expect($cached[1]['name'])->toBe('array-worker');
});

it('correctly identifies process running status', function () {
    // Use reflection to test protected method
    $reflection = new ReflectionClass($this->registry);
    $method = $reflection->getMethod('isProcessRunning');
    $method->setAccessible(true);

    // Current process should be running
    expect($method->invoke($this->registry, getmypid()))->toBeTrue();

    // Non-existent process should not be running
    expect($method->invoke($this->registry, 99999999))->toBeFalse();

    // Null PID should not be running
    expect($method->invoke($this->registry, null))->toBeFalse();

    // Zero PID should not be running
    expect($method->invoke($this->registry, 0))->toBeFalse();
});

it('handles cache expiration correctly', function () {
    $performers = [
        ['name' => 'worker', 'pid' => 12345],
    ];

    // Put with very short TTL
    Cache::put('orchestral:processes', $performers, now()->subSecond());

    // Wait for expiration
    sleep(1);

    $loaded = $this->registry->loadPerformers();
    expect($loaded)->toBeEmpty();
});

it('maintains data integrity when multiple processes are saved', function () {
    // First save
    $this->registry->savePerformers([
        ['name' => 'worker-1', 'pid' => 12345],
    ]);

    // Second save (should replace, not append)
    $this->registry->savePerformers([
        ['name' => 'worker-2', 'pid' => 12346],
        ['name' => 'worker-3', 'pid' => 12347],
    ]);

    $cached = Cache::get('orchestral:processes');
    expect($cached)->toHaveCount(2);
    expect($cached[0]['name'])->toBe('worker-2');
    expect($cached[1]['name'])->toBe('worker-3');
});
