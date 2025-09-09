<?php

use Subhamchbty\Orchestral\Conductor\Conductor;
use Subhamchbty\Orchestral\Conductor\Score;

beforeEach(function () {
    // Mock the Conductor
    $this->conductor = Mockery::mock(Conductor::class);
    $this->app->instance(Conductor::class, $this->conductor);

    // Mock the Score
    $this->score = Mockery::mock(Score::class);
    $this->app->instance(Score::class, $this->score);
});

it('displays instruments with all details', function () {
    $instruments = [
        'queue-worker' => [
            'command' => 'php artisan queue:work',
            'performers' => 3,
            'memory' => 256,
            'timeout' => 60,
            'options' => ['--queue' => 'default', '--sleep' => '3'],
        ],
        'scheduler' => [
            'command' => 'php artisan schedule:work',
            'performers' => 1,
            'memory' => 128,
            'timeout' => null,
            'options' => [],
        ],
    ];

    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn($instruments);

    $this->artisan('orchestral:instruments')
        ->expectsOutput('║        🎻 ORCHESTRAL INSTRUMENTS 🎻              ║')
        ->expectsTable(
            ['🎵 Instrument', '🎯 Command', '👥 Performers', '💾 Memory', '⏱️ Timeout', '⚙️ Options'],
            [
                ['queue-worker', 'php artisan queue:work', 3, '256 MB', '60s', '--queue=default --sleep=3'],
                ['scheduler', 'php artisan schedule:work', 1, '128 MB', 'unlimited', 'none'],
            ]
        )
        ->expectsOutput('📖 Legend:')
        ->expectsOutput('• Performers: Number of parallel processes')
        ->expectsOutput('🎼 Available Commands:')
        ->expectsOutput('• orchestral:conduct [name]  - Start specific or all performances')
        ->assertSuccessful();
});

it('shows environment when --environment option is used', function () {
    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn([
            'test-worker' => [
                'command' => 'php artisan test:work',
                'performers' => 1,
                'memory' => 128,
                'timeout' => 30,
                'options' => [],
            ],
        ]);

    $this->score->shouldReceive('getEnvironment')
        ->once()
        ->andReturn('production');

    $this->artisan('orchestral:instruments', ['--environment' => true])
        ->expectsOutput('🌍 Environment: production')
        ->expectsTable(
            ['🎵 Instrument', '🎯 Command', '👥 Performers', '💾 Memory', '⏱️ Timeout', '⚙️ Options'],
            [
                ['test-worker', 'php artisan test:work', 1, '128 MB', '30s', 'none'],
            ]
        )
        ->assertSuccessful();
});

it('displays warning when no instruments are configured', function () {
    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn([]);

    $this->artisan('orchestral:instruments')
        ->expectsOutput('║        🎻 ORCHESTRAL INSTRUMENTS 🎻              ║')
        ->expectsOutput('No instruments configured for the current environment.')
        ->assertSuccessful();
});

it('formats options correctly with numeric and associative arrays', function () {
    $instruments = [
        'complex-worker' => [
            'command' => 'php artisan complex:work',
            'performers' => 2,
            'memory' => 512,
            'timeout' => 0,
            'options' => [
                '--queue' => 'high,default',
                '--tries' => '3',
                'verbose',
                '--delay' => '10',
            ],
        ],
    ];

    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn($instruments);

    $this->artisan('orchestral:instruments')
        ->expectsTable(
            ['🎵 Instrument', '🎯 Command', '👥 Performers', '💾 Memory', '⏱️ Timeout', '⚙️ Options'],
            [
                ['complex-worker', 'php artisan complex:work', 2, '512 MB', 'unlimited', '--queue=high,default --tries=3 verbose --delay=10'],
            ]
        )
        ->assertSuccessful();
});

it('handles instruments with minimal configuration', function () {
    $instruments = [
        'minimal-worker' => [
            'command' => 'php artisan minimal:work',
            'performers' => 1,
            'memory' => 512,
            'timeout' => null,
            'options' => [],
        ],
    ];

    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn($instruments);

    $this->artisan('orchestral:instruments')
        ->expectsTable(
            ['🎵 Instrument', '🎯 Command', '👥 Performers', '💾 Memory', '⏱️ Timeout', '⚙️ Options'],
            [
                ['minimal-worker', 'php artisan minimal:work', 1, '512 MB', 'unlimited', 'none'],
            ]
        )
        ->assertSuccessful();
});

it('displays multiple instruments sorted by name', function () {
    $instruments = [
        'worker-c' => [
            'command' => 'php artisan worker:c',
            'performers' => 1,
            'memory' => 128,
            'timeout' => 30,
            'options' => [],
        ],
        'worker-a' => [
            'command' => 'php artisan worker:a',
            'performers' => 2,
            'memory' => 256,
            'timeout' => 60,
            'options' => [],
        ],
        'worker-b' => [
            'command' => 'php artisan worker:b',
            'performers' => 3,
            'memory' => 512,
            'timeout' => null,
            'options' => [],
        ],
    ];

    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn($instruments);

    $this->artisan('orchestral:instruments')
        ->expectsTable(
            ['🎵 Instrument', '🎯 Command', '👥 Performers', '💾 Memory', '⏱️ Timeout', '⚙️ Options'],
            [
                ['worker-c', 'php artisan worker:c', 1, '128 MB', '30s', 'none'],
                ['worker-a', 'php artisan worker:a', 2, '256 MB', '60s', 'none'],
                ['worker-b', 'php artisan worker:b', 3, '512 MB', 'unlimited', 'none'],
            ]
        )
        ->assertSuccessful();
});

it('displays the complete legend with all available commands', function () {
    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn([
            'test' => [
                'command' => 'test',
                'performers' => 1,
                'memory' => 128,
                'timeout' => 30,
                'options' => [],
            ],
        ]);

    $this->artisan('orchestral:instruments')
        ->expectsOutput('📖 Legend:')
        ->expectsOutput('━━━━━━━━━━━━━━━━━━━━━━━━━')
        ->expectsOutput('• Performers: Number of parallel processes')
        ->expectsOutput('• Memory: Maximum memory limit per process')
        ->expectsOutput('• Timeout: Maximum execution time (0 = unlimited)')
        ->expectsOutput('• Options: Additional command-line arguments')
        ->expectsOutput('🎼 Available Commands:')
        ->expectsOutput('━━━━━━━━━━━━━━━━━━━━━━━━━')
        ->expectsOutput('• orchestral:conduct [name]  - Start specific or all performances')
        ->expectsOutput('• orchestral:pause [name]    - Stop specific or all performances')
        ->expectsOutput('• orchestral:encore [name]   - Restart performances')
        ->expectsOutput('• orchestral:status          - Show current status')
        ->assertSuccessful();
});

it('formats timeout correctly for different values', function () {
    $instruments = [
        'timeout-test-1' => [
            'command' => 'test1',
            'performers' => 1,
            'memory' => 128,
            'timeout' => 30,
            'options' => [],
        ],
        'timeout-test-2' => [
            'command' => 'test2',
            'performers' => 1,
            'memory' => 128,
            'timeout' => 0,
            'options' => [],
        ],
        'timeout-test-3' => [
            'command' => 'test3',
            'performers' => 1,
            'memory' => 128,
            'timeout' => null,
            'options' => [],
        ],
        'timeout-test-4' => [
            'command' => 'test4',
            'performers' => 1,
            'memory' => 128,
            'timeout' => 3600,
            'options' => [],
        ],
    ];

    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn($instruments);

    $this->artisan('orchestral:instruments')
        ->expectsTable(
            ['🎵 Instrument', '🎯 Command', '👥 Performers', '💾 Memory', '⏱️ Timeout', '⚙️ Options'],
            [
                ['timeout-test-1', 'test1', 1, '128 MB', '30s', 'none'],
                ['timeout-test-2', 'test2', 1, '128 MB', 'unlimited', 'none'],
                ['timeout-test-3', 'test3', 1, '128 MB', 'unlimited', 'none'],
                ['timeout-test-4', 'test4', 1, '128 MB', '3600s', 'none'],
            ]
        )
        ->assertSuccessful();
});

it('displays environment for production', function () {
    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn([]);

    $this->score->shouldReceive('getEnvironment')
        ->once()
        ->andReturn('production');

    $this->artisan('orchestral:instruments', ['--environment' => true])
        ->expectsOutput('🌍 Environment: production')
        ->assertSuccessful();
});

it('displays environment for local', function () {
    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn([]);

    $this->score->shouldReceive('getEnvironment')
        ->once()
        ->andReturn('local');

    $this->artisan('orchestral:instruments', ['--environment' => true])
        ->expectsOutput('🌍 Environment: local')
        ->assertSuccessful();
});

it('handles options with special characters correctly', function () {
    $instruments = [
        'special-worker' => [
            'command' => 'php artisan special:work',
            'performers' => 1,
            'memory' => 256,
            'timeout' => 60,
            'options' => [
                '--queue' => 'high,medium,low',
                '--connection' => 'redis://localhost:6379',
                '--name' => 'worker-#1',
                'vvv',
            ],
        ],
    ];

    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn($instruments);

    $this->artisan('orchestral:instruments')
        ->expectsTable(
            ['🎵 Instrument', '🎯 Command', '👥 Performers', '💾 Memory', '⏱️ Timeout', '⚙️ Options'],
            [
                ['special-worker', 'php artisan special:work', 1, '256 MB', '60s', '--queue=high,medium,low --connection=redis://localhost:6379 --name=worker-#1 vvv'],
            ]
        )
        ->assertSuccessful();
});

it('displays formatted header with box drawing characters', function () {
    $this->conductor->shouldReceive('getInstruments')
        ->once()
        ->andReturn([]);

    $this->artisan('orchestral:instruments')
        ->expectsOutput('╔══════════════════════════════════════════════════╗')
        ->expectsOutput('║        🎻 ORCHESTRAL INSTRUMENTS 🎻              ║')
        ->expectsOutput('╚══════════════════════════════════════════════════╝')
        ->assertSuccessful();
});
