<?php

namespace Subhamchbty\Orchestral\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    protected $signature = 'orchestral:install 
                            {--config : Only publish config file}
                            {--migrations : Only publish migrations}';

    protected $description = '🎼 Install Orchestral - publish config and migrations with proper timestamps';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $onlyConfig = $this->option('config');
        $onlyMigrations = $this->option('migrations');

        $this->info('🎼 Installing Orchestral...');
        $this->line('');

        if ($onlyConfig) {
            $this->publishConfig();
        } elseif ($onlyMigrations) {
            $this->publishMigrations();
        } else {
            // Full installation - ask about storage preference
            $this->publishConfig();

            $this->line('');
            $this->info('🗄️ Storage Configuration:');
            $this->line('Orchestral can store process information using:');
            $this->line('• Database (MySQL, PostgreSQL, SQLite, etc.) - requires migration');
            $this->line('• Redis - no migration needed, faster performance');
            $this->line('');

            $useRedis = $this->choice(
                'Which storage driver would you like to use?',
                ['database', 'redis'],
                'redis'
            );

            $this->updateStorageConfig($useRedis);
            $this->updateEnvironmentFiles($useRedis);

            if ($useRedis === 'database') {
                $this->publishMigrations();
            } else {
                $this->info('📦 Using Redis storage - no migration needed');
            }
        }

        $this->line('');
        $this->info('✨ Orchestral installation complete!');
        $this->line('');

        $this->displayNextSteps($useRedis ?? 'redis');

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $configPath = config_path('orchestral.php');
        $stubPath = __DIR__.'/../../config/orchestral.php';

        if ($this->files->exists($configPath)) {
            if (! $this->confirm('Config file already exists. Overwrite?')) {
                $this->warn('⏭️ Skipped config publishing');

                return;
            }
        }

        $this->files->copy($stubPath, $configPath);
        $this->info('📄 Published config file: config/orchestral.php');
    }

    protected function publishMigrations(): void
    {
        $timestamp = date('Y_m_d_His');
        $migrationName = "{$timestamp}_create_orchestral_performances_table.php";
        $migrationPath = database_path("migrations/{$migrationName}");
        $stubPath = __DIR__.'/../../database/migrations/create_orchestral_performances_table.php.stub';

        // Check if migration already exists
        $existingMigrations = $this->files->glob(database_path('migrations/*_create_orchestral_performances_table.php'));
        if (! empty($existingMigrations)) {
            if (! $this->confirm('Orchestral migration already exists. Create another one?')) {
                $this->warn('⏭️ Skipped migration publishing');

                return;
            }
        }

        $this->files->copy($stubPath, $migrationPath);
        $this->info("📊 Published migration: database/migrations/{$migrationName}");
    }

    protected function updateStorageConfig(string $driver): void
    {
        $configPath = config_path('orchestral.php');

        if (! $this->files->exists($configPath)) {
            $this->error('Config file not found. Please run the command again.');

            return;
        }

        $content = $this->files->get($configPath);

        // Update the storage driver in the config file
        $content = preg_replace(
            "/'driver' => env\('ORCHESTRAL_STORAGE_DRIVER', '[^']+'\)/",
            "'driver' => env('ORCHESTRAL_STORAGE_DRIVER', '{$driver}')",
            $content
        );

        $this->files->put($configPath, $content);
        $this->info("⚙️ Updated storage driver to: {$driver}");
    }

    protected function updateEnvironmentFiles(string $storageDriver): void
    {
        $envVars = [
            'ORCHESTRAL_STORAGE_DRIVER' => $storageDriver,
            'ORCHESTRAL_NOTIFICATIONS_ENABLED' => 'false',
        ];

        $this->updateEnvironmentFile('.env', $envVars);
        $this->updateEnvironmentFile('.env.example', $envVars);
    }

    protected function updateEnvironmentFile(string $filename, array $envVars): void
    {
        $envPath = base_path($filename);

        if (! $this->files->exists($envPath)) {
            $this->warn("⚠️ {$filename} not found, skipping environment variable updates");

            return;
        }

        $content = $this->files->get($envPath);
        $updated = false;

        foreach ($envVars as $key => $value) {
            // Check if the variable already exists
            if (preg_match("/^{$key}=/m", $content)) {
                // Update existing variable
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            } else {
                // Add new variable at the end
                $content .= "\n# Orchestral Configuration\n{$key}={$value}\n";
                $updated = true;
            }
        }

        $this->files->put($envPath, $content);

        if ($updated || $filename === '.env') {
            $this->info("🔧 Updated {$filename} with Orchestral environment variables");
        }
    }

    protected function displayNextSteps(string $storageDriver): void
    {
        $this->info('📋 Next steps:');

        if ($storageDriver === 'database') {
            $this->line('1. Run: php artisan migrate');
            $this->line('2. Configure your performances in config/orchestral.php');
            $this->line('3. Start conducting: php artisan orchestral:conduct');
        } else {
            $this->line('1. Ensure Redis is running');
            $this->line('2. Configure your performances in config/orchestral.php');
            $this->line('3. Start conducting: php artisan orchestral:conduct');
        }

        $this->line('');
        $this->info('💡 Tip: You can always publish migrations later with:');
        $this->line('   php artisan orchestral:install --migrations');

        $this->line('');
        $this->info('🎼 Available commands:');
        $this->line('• orchestral:conduct    - Start all processes');
        $this->line('• orchestral:status     - Check process status');
        $this->line('• orchestral:pause      - Stop all processes');
        $this->line('• orchestral:instruments - List configurations');
    }
}
