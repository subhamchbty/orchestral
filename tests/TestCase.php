<?php

namespace Subhamchbty\Orchestral\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Subhamchbty\Orchestral\OrchestralServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations for tests that need the database
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            OrchestralServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Load the orchestral config
        $config = require __DIR__.'/../config/orchestral.php';
        $app['config']->set('orchestral', $config);
    }
}
