<?php

namespace Subhamchbty\Orchestral\Commands;

use Illuminate\Console\Command;
use Subhamchbty\Orchestral\Conductor\Conductor;

class EncoreCommand extends Command
{
    protected $signature = 'orchestral:encore 
                            {performance? : The specific performance to restart}
                            {--delay=2 : Seconds to wait before restarting}';

    protected $description = '🔄 Encore! Restart the orchestra performances';

    public function __construct(protected Conductor $conductor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $performance = $this->argument('performance');
        $delay = (int) $this->option('delay');

        if ($performance) {
            $this->info("🔄 Preparing encore for: {$performance}");
        } else {
            $this->info('🎭 The audience demands an encore!');
        }

        try {
            $this->info('⏸️ First, the orchestra takes a bow...');
            $this->conductor->pause($performance);

            if ($delay > 0) {
                $this->info("⏳ Intermission for {$delay} seconds...");
                $this->output->write('');

                $bar = $this->output->createProgressBar($delay);
                $bar->start();

                for ($i = 0; $i < $delay; $i++) {
                    sleep(1);
                    $bar->advance();
                }

                $bar->finish();
                $this->line('');
            }

            $this->info('🎼 The encore begins!');
            $this->conductor->conduct($performance);

            $this->info('✨ The orchestra plays once more!');
            $this->displayStatus();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ The encore could not be performed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function displayStatus(): void
    {
        $status = $this->conductor->getStatus();

        $this->table(
            ['Performer', 'PID', 'Status', 'Restarts'],
            collect($status['performers'])->map(function ($performer) {
                return [
                    $performer['name'],
                    $performer['pid'] ?? 'N/A',
                    $performer['running'] ? '✅ Running' : '❌ Stopped',
                    $performer['restart_attempts'] ?? 0,
                ];
            })->toArray()
        );

        $this->info("Total performers: {$status['total_performers']} | Running: {$status['running_performers']}");
    }
}
