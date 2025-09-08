<?php

namespace Subhamchbty\Orchestral\Conductor;

use Illuminate\Support\Collection;
use Subhamchbty\Orchestral\Models\Performance;
use Carbon\Carbon;

class Conductor
{
    protected Score $score;
    protected array $config;
    protected Collection $performers;
    protected ProcessRegistry $registry;
    protected bool $conducting = false;

    public function __construct(Score $score, array $config, ProcessRegistry $registry)
    {
        $this->score = $score;
        $this->config = $config;
        $this->registry = $registry;
        $this->performers = collect();
        $this->loadExistingPerformers();
    }

    public function conduct(?string $performanceName = null): void
    {
        $performances = $performanceName 
            ? [$performanceName => $this->score->getPerformance($performanceName)]
            : $this->score->getPerformances();

        foreach ($performances as $name => $config) {
            if (!$config) {
                continue;
            }

            $this->startPerformance($name, $config);
        }

        $this->conducting = true;
        $this->registry->setConducting(true);
        
        // Give processes a moment to start and get their PIDs
        usleep(500000); // 0.5 seconds
        
        $this->registry->savePerformers($this->performers->toArray());
        $this->recordPerformanceStart();
    }

    public function pause(?string $performanceName = null): void
    {
        $performers = $performanceName 
            ? $this->performers->filter(fn($p) => str_starts_with($p->getName(), $performanceName))
            : $this->performers;

        foreach ($performers as $performer) {
            $performer->stop();
        }

        if (!$performanceName) {
            $this->conducting = false;
            $this->registry->setConducting(false);
            $this->registry->clearPerformers();
            $this->recordPerformanceStop();
        } else {
            $this->registry->savePerformers($this->performers->toArray());
        }
    }

    public function encore(?string $performanceName = null): void
    {
        $this->pause($performanceName);
        sleep(2);
        $this->conduct($performanceName);
    }

    public function getStatus(): array
    {
        $this->loadExistingPerformers();
        
        return [
            'conducting' => $this->registry->isConducting(),
            'environment' => $this->score->getEnvironment(),
            'performers' => $this->buildPerformerStatus(),
            'total_performers' => count($this->registry->loadPerformers()),
            'running_performers' => count(array_filter($this->registry->loadPerformers(), fn($p) => $this->registry->getProcessInfo($p['pid']))),
        ];
    }

    public function getInstruments(): array
    {
        $performances = $this->score->getPerformances();
        $instruments = [];

        foreach ($performances as $name => $config) {
            $instruments[$name] = [
                'command' => $config['command'],
                'performers' => $config['performers'] ?? 1,
                'memory' => $config['memory'] ?? 512,
                'timeout' => $config['timeout'] ?? null,
                'options' => $config['options'] ?? [],
            ];
        }

        return $instruments;
    }

    public function healthCheck(): array
    {
        $healthStatus = [];
        
        foreach ($this->performers as $performer) {
            $healthStatus[$performer->getName()] = $performer->checkHealth();
        }

        return $healthStatus;
    }

    public function monitorPerformers(): void
    {
        // Load current processes from registry (Redis/cache)
        $processData = $this->registry->loadPerformers();
        
        foreach ($processData as $data) {
            $processInfo = $this->registry->getProcessInfo($data['pid']);
            
            // If process is not running and auto-restart is enabled
            if (!$processInfo && $this->score->shouldRestartOnFailure()) {
                $this->restartProcessFromData($data);
            }
            
            // Check for memory issues if process is running
            if ($processInfo && isset($processInfo['memory_mb']) && $processInfo['memory_mb'] > 0) {
                $memoryLimitMb = $this->getMemoryLimitForProcess($data['name']);
                if ($memoryLimitMb && $processInfo['memory_mb'] > $memoryLimitMb) {
                    $this->restartProcessFromData($data, 'Memory limit exceeded');
                }
            }
        }
    }

    protected function startPerformance(string $name, array $config): void
    {
        $performerCount = $config['performers'] ?? 1;
        $command = $this->score->buildCommand($config);

        for ($i = 1; $i <= $performerCount; $i++) {
            $performerName = $performerCount > 1 ? "{$name}-{$i}" : $name;
            $performer = new Performer($performerName, $command, $config);
            $performer->start();
            $this->performers->push($performer);
        }
    }

    protected function attemptRestart(Performer $performer): void
    {
        $maxAttempts = $this->score->getMaxRestartAttempts();
        
        if ($performer->getRestartAttempts() < $maxAttempts) {
            $delay = $this->score->getRestartDelay();
            sleep($delay);
            $performer->restart();
            $this->recordRestart($performer);
        } else {
            $this->recordFailure($performer);
        }
    }

    protected function handleUnhealthyPerformer(Performer $performer, array $health): void
    {
        foreach ($health['issues'] as $issue) {
            if (str_contains($issue, 'Memory usage') && str_contains($issue, 'exceeds limit')) {
                $performer->restart();
                $this->recordMemoryExceeded($performer);
            }
        }
    }

    protected function recordPerformanceStart(): void
    {
        if ($this->shouldUseDatabase()) {
            Performance::create([
                'event' => 'performance_started',
                'environment' => $this->score->getEnvironment(),
                'data' => [
                    'performers' => $this->performers->map(fn($p) => $p->getName())->toArray(),
                    'total_count' => $this->performers->count(),
                ],
                'occurred_at' => Carbon::now(),
            ]);
        }
        // For Redis storage, we don't need to record individual events
        // The process state is already managed by ProcessRegistry via cache/Redis
    }

    protected function recordPerformanceStop(): void
    {
        if ($this->shouldUseDatabase()) {
            Performance::create([
                'event' => 'performance_stopped',
                'environment' => $this->score->getEnvironment(),
                'data' => [
                    'performers' => $this->performers->map(fn($p) => $p->getName())->toArray(),
                ],
                'occurred_at' => Carbon::now(),
            ]);
        }
    }

    protected function recordRestart(Performer $performer): void
    {
        if ($this->shouldUseDatabase()) {
            Performance::create([
                'event' => 'performer_restarted',
                'performer_name' => $performer->getName(),
                'environment' => $this->score->getEnvironment(),
                'data' => [
                    'restart_attempts' => $performer->getRestartAttempts(),
                    'command' => $performer->getCommand(),
                ],
                'occurred_at' => Carbon::now(),
            ]);
        }
    }

    protected function recordFailure(Performer $performer): void
    {
        if ($this->shouldUseDatabase()) {
            Performance::create([
                'event' => 'performer_failed',
                'performer_name' => $performer->getName(),
                'environment' => $this->score->getEnvironment(),
                'data' => [
                    'restart_attempts' => $performer->getRestartAttempts(),
                    'command' => $performer->getCommand(),
                    'last_output' => $performer->getErrorOutput(),
                ],
                'occurred_at' => Carbon::now(),
            ]);
        }
    }

    protected function recordMemoryExceeded(Performer $performer): void
    {
        if ($this->shouldUseDatabase()) {
            Performance::create([
                'event' => 'memory_exceeded',
                'performer_name' => $performer->getName(),
                'environment' => $this->score->getEnvironment(),
                'data' => [
                    'memory_usage' => $performer->getMemoryUsage(),
                    'command' => $performer->getCommand(),
                ],
                'occurred_at' => Carbon::now(),
            ]);
        }
    }

    public function isConducting(): bool
    {
        return $this->conducting;
    }

    public function getPerformers(): Collection
    {
        return $this->performers;
    }

    protected function loadExistingPerformers(): void
    {
        $processData = $this->registry->loadPerformers();
        $this->conducting = $this->registry->isConducting();
        
        // Note: We don't recreate Performer objects here since they contain
        // the actual Process instances. The registry just tracks PIDs and metadata.
    }

    protected function buildPerformerStatus(): array
    {
        $processData = $this->registry->loadPerformers();
        $status = [];
        
        foreach ($processData as $process) {
            $processInfo = $this->registry->getProcessInfo($process['pid']);
            
            if ($processInfo) {
                $status[] = [
                    'name' => $process['name'],
                    'command' => $process['command'],
                    'pid' => $process['pid'],
                    'running' => $processInfo['running'],
                    'uptime' => $this->calculateUptime($process['started_at']),
                    'memory_mb' => $processInfo['memory_mb'],
                    'cpu_percent' => $processInfo['cpu_percent'],
                    'restart_attempts' => 0, // TODO: Track this in registry
                    'last_restart_at' => null,
                    'started_at' => $process['started_at'],
                ];
            }
        }
        
        return $status;
    }

    protected function calculateUptime(string $startedAt): string
    {
        $started = Carbon::parse($startedAt);
        return $started->diffForHumans(null, true);
    }

    protected function restartProcessFromData(array $data, ?string $reason = null): void
    {
        // Extract performance name and config from process data
        $performanceName = $data['name'];
        $basePerformanceName = explode('-', $performanceName)[0]; // Remove -1, -2 suffix
        
        $performances = $this->score->getPerformances();
        $config = $performances[$basePerformanceName] ?? null;
        
        if (!$config) {
            return;
        }
        
        // Create a new performer and start it
        $performer = new Performer($performanceName, $this->score->buildCommand($config), $config);
        $performer->start();
        
        // Update the registry with the new process info
        $updatedProcesses = $this->registry->loadPerformers();
        foreach ($updatedProcesses as &$process) {
            if ($process['name'] === $performanceName) {
                $process['pid'] = $performer->getPid();
                $process['started_at'] = now()->toIso8601String();
                break;
            }
        }
        
        $this->registry->savePerformers($updatedProcesses);
        
        // Record the restart event
        if ($reason && $this->shouldUseDatabase()) {
            Performance::create([
                'event' => 'performer_restarted',
                'performer_name' => $performanceName,
                'environment' => $this->score->getEnvironment(),
                'data' => [
                    'reason' => $reason,
                    'old_pid' => $data['pid'],
                    'new_pid' => $performer->getPid(),
                ],
                'occurred_at' => Carbon::now(),
            ]);
        }
    }
    
    protected function getMemoryLimitForProcess(string $performerName): ?int
    {
        $basePerformanceName = explode('-', $performerName)[0]; // Remove -1, -2 suffix
        $performances = $this->score->getPerformances();
        $config = $performances[$basePerformanceName] ?? null;
        
        return $config['memory'] ?? null;
    }

    protected function shouldUseDatabase(): bool
    {
        return $this->config['storage']['driver'] === 'database';
    }
}