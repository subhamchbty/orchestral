<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Subhamchbty\Orchestral\Models\Performance;

uses(RefreshDatabase::class);

it('creates a performance record', function () {
    $performance = Performance::create([
        'event' => 'performance_started',
        'performer_name' => 'queue-worker',
        'environment' => 'testing',
        'data' => ['pid' => 12345, 'command' => 'php artisan queue:work'],
        'occurred_at' => now(),
    ]);

    expect($performance)->toBeInstanceOf(Performance::class);
    expect($performance->event)->toBe('performance_started');
    expect($performance->performer_name)->toBe('queue-worker');
    expect($performance->environment)->toBe('testing');
    expect($performance->data)->toBeArray();
    expect($performance->data['pid'])->toBe(12345);
});

it('casts data field to array', function () {
    $data = ['key' => 'value', 'nested' => ['item' => 'test']];

    $performance = Performance::create([
        'event' => 'test_event',
        'environment' => 'testing',
        'data' => $data,
        'occurred_at' => now(),
    ]);

    $retrieved = Performance::find($performance->id);

    expect($retrieved->data)->toBeArray();
    expect($retrieved->data)->toEqual($data);
});

it('casts occurred_at to datetime', function () {
    $time = now();

    $performance = Performance::create([
        'event' => 'test_event',
        'environment' => 'testing',
        'occurred_at' => $time,
    ]);

    expect($performance->occurred_at)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($performance->occurred_at->format('Y-m-d H:i:s'))->toBe($time->format('Y-m-d H:i:s'));
});

it('scopes recent performances', function () {
    // Create old performance
    Performance::create([
        'event' => 'old_event',
        'environment' => 'testing',
        'occurred_at' => now()->subDays(10),
    ]);

    // Create recent performances
    Performance::create([
        'event' => 'recent_event_1',
        'environment' => 'testing',
        'occurred_at' => now()->subDays(3),
    ]);

    Performance::create([
        'event' => 'recent_event_2',
        'environment' => 'testing',
        'occurred_at' => now()->subDay(),
    ]);

    $recent = Performance::recent(7)->get();

    expect($recent)->toHaveCount(2);
    expect($recent->pluck('event')->toArray())->toContain('recent_event_1', 'recent_event_2');
    expect($recent->pluck('event')->toArray())->not->toContain('old_event');
});

it('scopes by environment', function () {
    Performance::create([
        'event' => 'prod_event',
        'environment' => 'production',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'local_event',
        'environment' => 'local',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'test_event',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    $production = Performance::byEnvironment('production')->get();

    expect($production)->toHaveCount(1);
    expect($production->first()->event)->toBe('prod_event');
});

it('scopes by performer name', function () {
    Performance::create([
        'event' => 'event_1',
        'performer_name' => 'queue-worker',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'event_2',
        'performer_name' => 'scheduler',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'event_3',
        'performer_name' => 'queue-worker',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    $queueWorker = Performance::byPerformer('queue-worker')->get();

    expect($queueWorker)->toHaveCount(2);
    expect($queueWorker->pluck('event')->toArray())->toContain('event_1', 'event_3');
});

it('scopes by event type', function () {
    Performance::create([
        'event' => 'performance_started',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'performer_failed',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'performance_started',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    $started = Performance::byEvent('performance_started')->get();

    expect($started)->toHaveCount(2);
    expect($started->pluck('event')->unique()->toArray())->toEqual(['performance_started']);
});

it('scopes failures', function () {
    Performance::create([
        'event' => 'performer_failed',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'memory_exceeded',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'performance_started',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'performer_restarted',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    $failures = Performance::failures()->get();

    expect($failures)->toHaveCount(2);
    expect($failures->pluck('event')->toArray())->toContain('performer_failed', 'memory_exceeded');
});

it('scopes restarts', function () {
    Performance::create([
        'event' => 'performer_restarted',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'performance_started',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    Performance::create([
        'event' => 'performer_restarted',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    $restarts = Performance::restarts()->get();

    expect($restarts)->toHaveCount(2);
    expect($restarts->pluck('event')->unique()->toArray())->toEqual(['performer_restarted']);
});

it('allows nullable performer name', function () {
    $performance = Performance::create([
        'event' => 'system_event',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    expect($performance->performer_name)->toBeNull();
});

it('allows nullable data field', function () {
    $performance = Performance::create([
        'event' => 'simple_event',
        'environment' => 'testing',
        'occurred_at' => now(),
    ]);

    expect($performance->data)->toBeNull();
});

it('combines multiple scopes', function () {
    // Create various performances
    Performance::create([
        'event' => 'performer_failed',
        'performer_name' => 'queue-worker',
        'environment' => 'production',
        'occurred_at' => now()->subDays(2),
    ]);

    Performance::create([
        'event' => 'performer_failed',
        'performer_name' => 'scheduler',
        'environment' => 'production',
        'occurred_at' => now()->subDays(10),
    ]);

    Performance::create([
        'event' => 'performer_failed',
        'performer_name' => 'queue-worker',
        'environment' => 'local',
        'occurred_at' => now()->subDay(),
    ]);

    // Query with multiple scopes
    $results = Performance::recent(7)
        ->byEnvironment('production')
        ->failures()
        ->byPerformer('queue-worker')
        ->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->performer_name)->toBe('queue-worker');
    expect($results->first()->environment)->toBe('production');
});

it('handles large data payloads', function () {
    $largeData = [
        'metrics' => array_fill(0, 100, ['value' => rand(1, 1000)]),
        'logs' => str_repeat('Log entry. ', 1000),
        'nested' => [
            'deep' => [
                'structure' => [
                    'with' => ['many' => 'levels'],
                ],
            ],
        ],
    ];

    $performance = Performance::create([
        'event' => 'large_data_event',
        'environment' => 'testing',
        'data' => $largeData,
        'occurred_at' => now(),
    ]);

    $retrieved = Performance::find($performance->id);

    expect($retrieved->data)->toEqual($largeData);
});
