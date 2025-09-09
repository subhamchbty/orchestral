<?php

namespace Subhamchbty\Orchestral;

use Illuminate\Support\ServiceProvider;
use Subhamchbty\Orchestral\Commands\ConductCommand;
use Subhamchbty\Orchestral\Commands\EncoreCommand;
use Subhamchbty\Orchestral\Commands\InstallCommand;
use Subhamchbty\Orchestral\Commands\InstrumentsCommand;
use Subhamchbty\Orchestral\Commands\PauseCommand;
use Subhamchbty\Orchestral\Commands\StatusCommand;
use Subhamchbty\Orchestral\Conductor\Conductor;
use Subhamchbty\Orchestral\Conductor\ProcessRegistry;
use Subhamchbty\Orchestral\Conductor\Score;

class OrchestralServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/orchestral.php',
            'orchestral'
        );

        $this->app->singleton(Score::class, function ($app) {
            return new Score($app['config']['orchestral'] ?? []);
        });

        $this->app->singleton(ProcessRegistry::class, function ($app) {
            return new ProcessRegistry;
        });

        $this->app->singleton(Conductor::class, function ($app) {
            return new Conductor(
                $app->make(Score::class),
                $app['config']['orchestral'] ?? [],
                $app->make(ProcessRegistry::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration file
            $this->publishes([
                __DIR__.'/../config/orchestral.php' => config_path('orchestral.php'),
            ], 'orchestral-config');

            // Publish migrations with automatic timestamping
            $this->publishesMigrations([
                __DIR__.'/../database/migrations/create_orchestral_performances_table.php.stub' => 'create_orchestral_performances_table.php',
            ], 'orchestral-migrations');

            // Publish both config and migrations together
            $this->publishes([
                __DIR__.'/../config/orchestral.php' => config_path('orchestral.php'),
            ], 'orchestral');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations/create_orchestral_performances_table.php.stub' => 'create_orchestral_performances_table.php',
            ], 'orchestral');

            // Don't auto-load migrations - let users publish and run them manually
            // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->commands([
                InstallCommand::class,
                ConductCommand::class,
                PauseCommand::class,
                EncoreCommand::class,
                StatusCommand::class,
                InstrumentsCommand::class,
            ]);
        }
    }
}
