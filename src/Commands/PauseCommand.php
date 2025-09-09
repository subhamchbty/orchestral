<?php

namespace Subhamchbty\Orchestral\Commands;

use Illuminate\Console\Command;
use Subhamchbty\Orchestral\Conductor\Conductor;

class PauseCommand extends Command
{
    protected $signature = 'orchestral:pause 
                            {performance? : The specific performance to pause}
                            {--graceful : Wait for current operations to complete}';

    protected $description = '⏸️ Pause the orchestra - stop all running performances';

    public function __construct(protected Conductor $conductor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $performance = $this->argument('performance');
        $graceful = $this->option('graceful');

        try {
            $this->conductor->pause($performance);

            $this->displayFinalStatus();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function displayFinalStatus(): void
    {
        $status = $this->conductor->getStatus();

        foreach ($status['performers'] as $performer) {
            $statusIcon = $performer['running'] ? '⚠️' : '✅';
            $statusText = $performer['running'] ? 'Still Running' : 'Stopped';

            $this->line("{$performer['name']}: {$statusText}");
        }

        if ($status['total_performers'] > 0) {
            $this->info("Stopped: {$status['total_performers']} performers");
        }
    }
}
