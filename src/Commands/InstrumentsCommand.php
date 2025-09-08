<?php

namespace Subhamchbty\Orchestral\Commands;

use Illuminate\Console\Command;
use Subhamchbty\Orchestral\Conductor\Conductor;
use Subhamchbty\Orchestral\Conductor\Score;

class InstrumentsCommand extends Command
{
    protected $signature = 'orchestral:instruments 
                            {--environment : Show which environment is active}';

    protected $description = '🎻 List all configured instruments (processes) in the orchestra';

    public function __construct(
        protected Conductor $conductor,
        protected Score $score
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $showEnvironment = $this->option('environment');
        $instruments = $this->conductor->getInstruments();
        
        $this->displayHeader($showEnvironment);
        
        if (empty($instruments)) {
            $this->warn('No instruments configured for the current environment.');
            return self::SUCCESS;
        }

        $this->displayInstruments($instruments);
        $this->displayLegend();

        return self::SUCCESS;
    }

    protected function displayHeader(bool $showEnvironment): void
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════╗');
        $this->line('║        🎻 ORCHESTRAL INSTRUMENTS 🎻              ║');
        $this->line('╚══════════════════════════════════════════════════╝');
        $this->line('');
        
        if ($showEnvironment) {
            $env = $this->score->getEnvironment();
            $this->info("🌍 Environment: {$env}");
            $this->line('');
        }
    }

    protected function displayInstruments(array $instruments): void
    {
        $headers = ['🎵 Instrument', '🎯 Command', '👥 Performers', '💾 Memory', '⏱️ Timeout', '⚙️ Options'];
        $rows = [];

        foreach ($instruments as $name => $config) {
            $options = $this->formatOptions($config['options']);
            $timeout = $config['timeout'] ? "{$config['timeout']}s" : 'unlimited';
            
            $rows[] = [
                $name,
                $config['command'],
                $config['performers'],
                "{$config['memory']} MB",
                $timeout,
                $options,
            ];
        }

        $this->table($headers, $rows);
    }

    protected function displayLegend(): void
    {
        $this->line('');
        $this->info('📖 Legend:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('• Performers: Number of parallel processes');
        $this->line('• Memory: Maximum memory limit per process');
        $this->line('• Timeout: Maximum execution time (0 = unlimited)');
        $this->line('• Options: Additional command-line arguments');
        $this->line('');
        
        $this->info('🎼 Available Commands:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('• orchestral:conduct [name]  - Start specific or all performances');
        $this->line('• orchestral:pause [name]    - Stop specific or all performances');
        $this->line('• orchestral:encore [name]   - Restart performances');
        $this->line('• orchestral:status          - Show current status');
    }

    protected function formatOptions(array $options): string
    {
        if (empty($options)) {
            return 'none';
        }

        $formatted = [];
        foreach ($options as $key => $value) {
            if (is_numeric($key)) {
                $formatted[] = $value;
            } else {
                $formatted[] = "{$key}={$value}";
            }
        }

        return implode(' ', $formatted);
    }
}