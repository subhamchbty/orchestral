<?php

namespace Subhamchbty\Orchestral\Commands;

use Illuminate\Console\Command;
use Subhamchbty\Orchestral\Conductor\Conductor;

class ConductCommand extends Command
{
    protected $signature = 'orchestral:conduct 
                            {performance? : The specific performance to conduct}
                            {--daemon : Run in daemon mode with monitoring}';

    protected $description = '🎼 Start conducting the orchestra - begin all configured performances';

    public function __construct(protected Conductor $conductor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $performance = $this->argument('performance');
        $isDaemon = $this->option('daemon');

        try {
            $this->conductor->conduct($performance);

            $this->displayStatus();

            if ($isDaemon) {
                $this->runInDaemonMode();
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function runInDaemonMode(): void
    {
        $this->info('👁️ Running in daemon mode. Press Ctrl+C to stop...');

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        pcntl_signal(SIGTERM, [$this, 'handleShutdown']);

        while ($this->conductor->isConducting()) {
            $this->conductor->monitorPerformers();
            sleep(10);
        }
    }

    protected function handleShutdown(): void
    {
        $this->conductor->pause();
        exit(0);
    }

    protected function displayStatus(): void
    {
        $status = $this->conductor->getStatus();

        $this->table(
            ['Performer', 'PID', 'Status', 'Uptime', 'Memory (MB)', 'CPU (%)'],
            collect($status['performers'])->map(function ($performer) {
                return [
                    $performer['name'],
                    $performer['pid'] ?? 'N/A',
                    $performer['running'] ? '✅ Running' : '❌ Stopped',
                    $performer['uptime'] ?? 'N/A',
                    $performer['memory_mb'] ?? 'N/A',
                    $performer['cpu_percent'] ?? 'N/A',
                ];
            })->toArray()
        );

        $this->info("Total performers: {$status['total_performers']} | Running: {$status['running_performers']}");
    }
}
