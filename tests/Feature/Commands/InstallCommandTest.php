<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Clean up any existing test files
    cleanupTestFiles();
});

afterEach(function () {
    // Clean up after tests
    cleanupTestFiles();
});

function cleanupTestFiles(): void
{
    // Remove test config
    if (File::exists(config_path('orchestral.php'))) {
        File::delete(config_path('orchestral.php'));
    }

    // Remove test migrations
    $migrations = File::glob(database_path('migrations/*_create_orchestral_performances_table.php'));
    foreach ($migrations as $migration) {
        File::delete($migration);
    }

    // Clean up .env files if they exist
    if (File::exists(base_path('.env'))) {
        File::delete(base_path('.env'));
    }
    if (File::exists(base_path('.env.example'))) {
        File::delete(base_path('.env.example'));
    }
}

it('performs full installation with database storage', function () {
    // Create .env files for testing
    File::put(base_path('.env'), "APP_NAME=Laravel\n");
    File::put(base_path('.env.example'), "APP_NAME=Laravel\n");

    $this->artisan('orchestral:install')
        ->expectsQuestion('Which storage driver would you like to use?', 'database')
        ->expectsOutput('🎼 Installing Orchestral...')
        ->expectsOutput('📄 Published config file: config/orchestral.php')
        ->expectsOutput('⚙️ Updated storage driver to: database')
        ->expectsOutputToContain('Published migration: database/migrations/')
        ->expectsOutput('✨ Orchestral installation complete!')
        ->assertSuccessful();

    expect(config_path('orchestral.php'))->toBeFile();

    $migrations = File::glob(database_path('migrations/*_create_orchestral_performances_table.php'));
    expect($migrations)->not->toBeEmpty();

    $envContent = File::get(base_path('.env'));
    expect($envContent)
        ->toContain('ORCHESTRAL_STORAGE_DRIVER=database')
        ->toContain('ORCHESTRAL_NOTIFICATIONS_ENABLED=false');
});

it('performs full installation with redis storage', function () {
    File::put(base_path('.env'), "APP_NAME=Laravel\n");

    $this->artisan('orchestral:install')
        ->expectsQuestion('Which storage driver would you like to use?', 'redis')
        ->expectsOutput('📦 Using Redis storage - no migration needed')
        ->assertSuccessful();

    expect(config_path('orchestral.php'))->toBeFile();

    $migrations = File::glob(database_path('migrations/*_create_orchestral_performances_table.php'));
    expect($migrations)->toBeEmpty();

    $envContent = File::get(base_path('.env'));
    expect($envContent)->toContain('ORCHESTRAL_STORAGE_DRIVER=redis');
});

it('installs only config with --config flag', function () {
    $this->artisan('orchestral:install', ['--config' => true])
        ->expectsOutput('📄 Published config file: config/orchestral.php')
        ->assertSuccessful();

    expect(config_path('orchestral.php'))->toBeFile();

    $migrations = File::glob(database_path('migrations/*_create_orchestral_performances_table.php'));
    expect($migrations)->toBeEmpty();
});

it('installs only migrations with --migrations flag', function () {
    $this->artisan('orchestral:install', ['--migrations' => true])
        ->expectsOutputToContain('Published migration: database/migrations/')
        ->assertSuccessful();

    $migrations = File::glob(database_path('migrations/*_create_orchestral_performances_table.php'));
    expect($migrations)->not->toBeEmpty();

    expect(config_path('orchestral.php'))->not->toBeFile();
});

it('asks for confirmation when config file exists', function () {
    File::put(config_path('orchestral.php'), '<?php return [];');

    $this->artisan('orchestral:install', ['--config' => true])
        ->expectsConfirmation('Config file already exists. Overwrite?', 'yes')
        ->expectsOutput('📄 Published config file: config/orchestral.php')
        ->assertSuccessful();
});

it('skips config overwrite when user declines', function () {
    $originalContent = '<?php return ["test" => true];';
    File::put(config_path('orchestral.php'), $originalContent);

    $this->artisan('orchestral:install', ['--config' => true])
        ->expectsConfirmation('Config file already exists. Overwrite?', 'no')
        ->expectsOutput('⏭️ Skipped config publishing')
        ->assertSuccessful();

    expect(File::get(config_path('orchestral.php')))->toBe($originalContent);
});

it('asks for confirmation when migration exists', function () {
    $existingMigration = database_path('migrations/2024_01_01_000000_create_orchestral_performances_table.php');
    File::put($existingMigration, '<?php');

    $this->artisan('orchestral:install', ['--migrations' => true])
        ->expectsConfirmation('Orchestral migration already exists. Create another one?', 'yes')
        ->assertSuccessful();

    $migrations = File::glob(database_path('migrations/*_create_orchestral_performances_table.php'));
    expect($migrations)->toHaveCount(2);
});

it('handles missing .env files gracefully', function () {
    $this->artisan('orchestral:install')
        ->expectsQuestion('Which storage driver would you like to use?', 'redis')
        ->expectsOutput('⚠️ .env not found, skipping environment variable updates')
        ->expectsOutput('⚠️ .env.example not found, skipping environment variable updates')
        ->assertSuccessful();
});

it('updates existing environment variables', function () {
    File::put(base_path('.env'), "APP_NAME=Laravel\nORCHESTRAL_STORAGE_DRIVER=cache\n");

    $this->artisan('orchestral:install')
        ->expectsQuestion('Which storage driver would you like to use?', 'database')
        ->assertSuccessful();

    $envContent = File::get(base_path('.env'));
    expect($envContent)
        ->toContain('ORCHESTRAL_STORAGE_DRIVER=database')
        ->and(substr_count($envContent, 'ORCHESTRAL_STORAGE_DRIVER'))
        ->toBe(1);
});

it('updates config file with selected storage driver', function () {
    $this->artisan('orchestral:install')
        ->expectsQuestion('Which storage driver would you like to use?', 'redis')
        ->assertSuccessful();

    $configContent = File::get(config_path('orchestral.php'));
    expect($configContent)->toContain("'driver' => env('ORCHESTRAL_STORAGE_DRIVER', 'redis')");
});

it('shows database-specific next steps', function () {
    $this->artisan('orchestral:install')
        ->expectsQuestion('Which storage driver would you like to use?', 'database')
        ->expectsOutput('1. Run: php artisan migrate')
        ->assertSuccessful();
});

it('shows redis-specific next steps', function () {
    $this->artisan('orchestral:install')
        ->expectsQuestion('Which storage driver would you like to use?', 'redis')
        ->expectsOutput('1. Ensure Redis is running')
        ->assertSuccessful();
});

it('creates migrations with unique timestamps', function () {
    $this->artisan('orchestral:install', ['--migrations' => true])
        ->assertSuccessful();

    sleep(1); // Ensure different timestamp

    $this->artisan('orchestral:install', ['--migrations' => true])
        ->expectsConfirmation('Orchestral migration already exists. Create another one?', 'yes')
        ->assertSuccessful();

    $migrations = File::glob(database_path('migrations/*_create_orchestral_performances_table.php'));

    $timestamps = array_map(fn ($path) => basename($path, '_create_orchestral_performances_table.php'), $migrations);

    expect($migrations)->toHaveCount(2)
        ->and(array_unique($timestamps))->toHaveCount(2);
});

it('displays available commands in next steps', function () {
    $this->artisan('orchestral:install')
        ->expectsQuestion('Which storage driver would you like to use?', 'redis')
        ->expectsOutput('🎼 Available commands:')
        ->expectsOutput('• orchestral:conduct    - Start all processes')
        ->expectsOutput('• orchestral:status     - Check process status')
        ->expectsOutput('• orchestral:pause      - Stop all processes')
        ->expectsOutput('• orchestral:instruments - List configurations')
        ->assertSuccessful();
});
