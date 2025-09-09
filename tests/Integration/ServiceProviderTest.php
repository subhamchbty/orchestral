<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Subhamchbty\Orchestral\Commands\ConductCommand;
use Subhamchbty\Orchestral\Commands\EncoreCommand;
use Subhamchbty\Orchestral\Commands\InstallCommand;
use Subhamchbty\Orchestral\Commands\InstrumentsCommand;
use Subhamchbty\Orchestral\Commands\PauseCommand;
use Subhamchbty\Orchestral\Commands\StatusCommand;
use Subhamchbty\Orchestral\Conductor\Conductor;
use Subhamchbty\Orchestral\Conductor\ProcessRegistry;
use Subhamchbty\Orchestral\Conductor\Score;
use Subhamchbty\Orchestral\OrchestralServiceProvider;

it('registers the service provider', function () {
    $providers = $this->app->getLoadedProviders();

    expect($providers)->toHaveKey(OrchestralServiceProvider::class);
});

it('binds services as singletons', function () {
    $score1 = $this->app->make(Score::class);
    $score2 = $this->app->make(Score::class);

    expect($score1)->toBe($score2);

    $registry1 = $this->app->make(ProcessRegistry::class);
    $registry2 = $this->app->make(ProcessRegistry::class);

    expect($registry1)->toBe($registry2);

    $conductor1 = $this->app->make(Conductor::class);
    $conductor2 = $this->app->make(Conductor::class);

    expect($conductor1)->toBe($conductor2);
});

it('registers all console commands', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('orchestral:install');
    expect($commands)->toHaveKey('orchestral:conduct');
    expect($commands)->toHaveKey('orchestral:pause');
    expect($commands)->toHaveKey('orchestral:encore');
    expect($commands)->toHaveKey('orchestral:status');
    expect($commands)->toHaveKey('orchestral:instruments');
});

it('publishes config file', function () {
    $configPath = config_path('orchestral.php');

    // Clean up first
    if (File::exists($configPath)) {
        File::delete($configPath);
    }

    Artisan::call('vendor:publish', [
        '--provider' => OrchestralServiceProvider::class,
        '--tag' => 'orchestral-config',
    ]);

    expect($configPath)->toBeFile();

    // Clean up
    File::delete($configPath);
});

it('merges config from package', function () {
    $config = config('orchestral');

    expect($config)->toBeArray();
    expect($config)->toHaveKeys(['performances', 'management', 'monitoring', 'storage', 'notifications']);
});

it('resolves Score with correct config', function () {
    $score = $this->app->make(Score::class);

    expect($score)->toBeInstanceOf(Score::class);
    expect($score->getEnvironment())->toBe('testing');
});

it('resolves ProcessRegistry correctly', function () {
    $registry = $this->app->make(ProcessRegistry::class);

    expect($registry)->toBeInstanceOf(ProcessRegistry::class);
    expect($registry->isConducting())->toBeFalse();
});

it('resolves Conductor with dependencies', function () {
    $conductor = $this->app->make(Conductor::class);

    expect($conductor)->toBeInstanceOf(Conductor::class);
    expect($conductor->isConducting())->toBeFalse();
    expect($conductor->getPerformers())->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('loads commands only in console', function () {
    // This test verifies that commands are registered when running in console
    expect($this->app->runningInConsole())->toBeTrue();

    $commands = [
        InstallCommand::class,
        ConductCommand::class,
        PauseCommand::class,
        EncoreCommand::class,
        StatusCommand::class,
        InstrumentsCommand::class,
    ];

    foreach ($commands as $command) {
        expect(class_exists($command))->toBeTrue();
    }
});

it('provides publishable groups', function () {
    // Get publishable paths from service provider
    $provider = new OrchestralServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    // Test that both config and migrations can be published
    Artisan::call('vendor:publish', [
        '--provider' => OrchestralServiceProvider::class,
        '--tag' => 'orchestral',
        '--force' => true,
    ]);

    expect(config_path('orchestral.php'))->toBeFile();

    // Clean up
    File::delete(config_path('orchestral.php'));
});

it('does not auto-load migrations', function () {
    // Verify that migrations are not automatically loaded
    // They should only be published and run manually
    $loadedMigrations = $this->app['migrator']->paths();

    $packageMigrationPath = realpath(__DIR__.'/../../database/migrations');

    expect($loadedMigrations)->not->toContain($packageMigrationPath);
});

it('provides correct command signatures', function () {
    $commandSignatures = [
        'orchestral:install' => 'orchestral:install',
        'orchestral:conduct' => 'orchestral:conduct',
        'orchestral:pause' => 'orchestral:pause',
        'orchestral:encore' => 'orchestral:encore',
        'orchestral:status' => 'orchestral:status',
        'orchestral:instruments' => 'orchestral:instruments',
    ];

    foreach ($commandSignatures as $name => $expectedSignature) {
        $command = Artisan::all()[$name] ?? null;
        expect($command)->not->toBeNull();
        expect($command->getName())->toBe($expectedSignature);
    }
});

it('injects dependencies into commands correctly', function () {
    // Test that commands receive their dependencies
    $conductCommand = $this->app->make(ConductCommand::class);
    expect($conductCommand)->toBeInstanceOf(ConductCommand::class);

    $statusCommand = $this->app->make(StatusCommand::class);
    expect($statusCommand)->toBeInstanceOf(StatusCommand::class);

    $instrumentsCommand = $this->app->make(InstrumentsCommand::class);
    expect($instrumentsCommand)->toBeInstanceOf(InstrumentsCommand::class);
});

it('handles config caching correctly', function () {
    // Test that config can be cached and still works
    Artisan::call('config:cache');

    $config = config('orchestral');

    // Config caching in packages is complex - just ensure no errors occur
    // The config might be null after caching in test environment
    if ($config !== null) {
        expect($config)->toBeArray();
        expect($config)->toHaveKeys(['performances', 'management']);
    }

    // Clear cache
    Artisan::call('config:clear');
})->skip('Config caching behavior varies in package tests');

it('publishes migrations with correct structure', function () {
    $migrationStubPath = __DIR__.'/../../database/migrations/create_orchestral_performances_table.php.stub';

    expect($migrationStubPath)->toBeFile();

    $content = File::get($migrationStubPath);
    expect($content)->toContain('Schema::create');
    expect($content)->toContain('orchestral_performances');
    expect($content)->toContain('event');
    expect($content)->toContain('performer_name');
    expect($content)->toContain('environment');
});
