<?php

namespace Subhamchbty\Orchestral\Conductor;

use Symfony\Component\Process\Process;
use Carbon\Carbon;

class Performer
{
    protected Process $process;
    protected string $name;
    protected string $command;
    protected array $config;
    protected ?int $pid = null;
    protected Carbon $startedAt;
    protected int $restartAttempts = 0;
    protected ?Carbon $lastRestartAt = null;
    protected array $performanceMetrics = [];

    public function __construct(string $name, string $command, array $config)
    {
        $this->name = $name;
        $this->command = $command;
        $this->config = $config;
    }

    public function start(): void
    {
        $processCommand = $this->buildProcessCommand();
        
        // Use nohup to detach the process from the parent
        $detachedCommand = "nohup {$processCommand} > /dev/null 2>&1 & echo $!";
        
        $this->process = Process::fromShellCommandline(
            $detachedCommand,
            base_path(),
            null,
            null,
            10 // Short timeout since we just need to get the PID
        );

        if (isset($this->config['nice'])) {
            $this->process->setEnv(['NICE' => $this->config['nice']]);
        }

        $this->process->run();
        
        // Get the PID from the output
        $output = trim($this->process->getOutput());
        $this->pid = $output ? (int) $output : null;
        $this->startedAt = Carbon::now();
    }

    public function stop(): void
    {
        if ($this->isRunning()) {
            exec("kill -TERM {$this->pid}");
        }
    }

    public function restart(): void
    {
        $this->stop();
        sleep(2);
        $this->start();
        $this->restartAttempts++;
        $this->lastRestartAt = Carbon::now();
    }

    public function isRunning(): bool
    {
        return $this->pid && file_exists("/proc/{$this->pid}");
    }

    public function getPid(): ?int
    {
        return $this->pid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getUptime(): ?string
    {
        if (!isset($this->startedAt)) {
            return null;
        }

        return $this->startedAt->diffForHumans(null, true);
    }

    public function getMemoryUsage(): ?float
    {
        if (!$this->isRunning() || !$this->pid) {
            return null;
        }

        $statusFile = "/proc/{$this->pid}/status";
        if (!file_exists($statusFile)) {
            return null;
        }

        $status = file_get_contents($statusFile);
        if (preg_match('/VmRSS:\s+(\d+)\s+kB/', $status, $matches)) {
            return round($matches[1] / 1024, 2); // Convert to MB
        }

        return null;
    }

    public function getCpuUsage(): ?float
    {
        if (!$this->isRunning() || !$this->pid) {
            return null;
        }

        exec("ps -p {$this->pid} -o %cpu --no-headers", $output);
        if (!empty($output[0])) {
            return (float) trim($output[0]);
        }

        return null;
    }

    public function getRestartAttempts(): int
    {
        return $this->restartAttempts;
    }

    public function getLastRestartAt(): ?Carbon
    {
        return $this->lastRestartAt;
    }

    public function getStatus(): array
    {
        return [
            'name' => $this->name,
            'command' => $this->command,
            'pid' => $this->pid,
            'running' => $this->isRunning(),
            'uptime' => $this->getUptime(),
            'memory_mb' => $this->getMemoryUsage(),
            'cpu_percent' => $this->getCpuUsage(),
            'restart_attempts' => $this->restartAttempts,
            'last_restart_at' => $this->lastRestartAt?->toIso8601String(),
            'started_at' => $this->startedAt?->toIso8601String(),
        ];
    }

    public function checkHealth(): array
    {
        $isHealthy = true;
        $issues = [];

        if (!$this->isRunning()) {
            $isHealthy = false;
            $issues[] = 'Process is not running';
        }

        $memoryUsage = $this->getMemoryUsage();
        $memoryLimit = $this->config['memory'] ?? 512;
        
        if ($memoryUsage && $memoryUsage > $memoryLimit) {
            $isHealthy = false;
            $issues[] = "Memory usage ({$memoryUsage}MB) exceeds limit ({$memoryLimit}MB)";
        }

        return [
            'healthy' => $isHealthy,
            'issues' => $issues,
            'metrics' => [
                'memory_mb' => $memoryUsage,
                'cpu_percent' => $this->getCpuUsage(),
                'uptime_seconds' => $this->startedAt ? $this->startedAt->diffInSeconds() : 0,
            ],
        ];
    }

    protected function buildProcessCommand(): string
    {
        $memoryLimit = $this->config['memory'] ?? 512;
        $nice = $this->config['nice'] ?? 0;
        
        $prefix = '';
        if ($nice != 0) {
            $prefix = "nice -n {$nice} ";
        }
        
        return $prefix . $this->command;
    }

    public function getOutput(): string
    {
        return $this->process?->getOutput() ?? '';
    }

    public function getErrorOutput(): string
    {
        return $this->process?->getErrorOutput() ?? '';
    }
}