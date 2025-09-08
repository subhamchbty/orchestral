<?php

namespace Subhamchbty\Orchestral\Commands;

use Illuminate\Console\Command;
use Subhamchbty\Orchestral\Conductor\Conductor;

class StatusCommand extends Command
{
    protected $signature = 'orchestral:status 
                            {--health : Include health check information}
                            {--json : Output as JSON}';

    protected $description = '📊 Show the current status of all orchestra performers';

    public function __construct(protected Conductor $conductor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $status = $this->conductor->getStatus();
        $includeHealth = $this->option('health');
        $asJson = $this->option('json');

        if ($asJson) {
            $output = $status;
            if ($includeHealth) {
                $output['health'] = $this->conductor->healthCheck();
            }
            $this->line(json_encode($output, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->displayPerformers($status);
        
        if ($includeHealth) {
            $this->displayHealthStatus();
        }

        return self::SUCCESS;
    }


    protected function displayPerformers(array $status): void
    {
        if (empty($status['performers'])) {
            $this->info('No performers running');
            return;
        }

        $headers = ['Performer', 'PID', 'Status', 'Uptime', 'Memory', 'CPU'];
        $rows = [];

        foreach ($status['performers'] as $performer) {
            $statusText = $performer['running'] ? 'Running' : 'Stopped';
            
            $rows[] = [
                $performer['name'],
                $performer['pid'] ?? '-',
                $statusText,
                $performer['uptime'] ?? '-',
                $this->formatMemory($performer['memory_mb']),
                $this->formatCpu($performer['cpu_percent']),
            ];
        }

        $this->table($headers, $rows);
        
        if ($status['total_performers'] > 0) {
            $this->info("Total: {$status['total_performers']} | Running: {$status['running_performers']}");
        }
    }

    protected function displayHealthStatus(): void
    {
        $health = $this->conductor->healthCheck();
        
        $this->line('');
        $this->info('Health Status:');
        
        foreach ($health as $performerName => $status) {
            $healthText = $status['healthy'] ? 'Healthy' : 'Issues';
            $this->line("{$performerName}: {$healthText}");
            
            if (!$status['healthy'] && !empty($status['issues'])) {
                foreach ($status['issues'] as $issue) {
                    $this->line("  • {$issue}");
                }
            }
        }
    }

    protected function formatMemory($memory): string
    {
        if ($memory === null) {
            return '-';
        }
        
        if ($memory > 1024) {
            return round($memory / 1024, 2) . ' GB';
        }
        
        return $memory . ' MB';
    }

    protected function formatCpu($cpu): string
    {
        if ($cpu === null) {
            return '-';
        }
        
        return $cpu . '%';
    }
}