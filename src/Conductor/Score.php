<?php

namespace Subhamchbty\Orchestral\Conductor;

use Illuminate\Support\Arr;

class Score
{
    protected array $config;

    protected string $environment;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->environment = app()->environment();
    }

    public function getPerformances(): array
    {
        return Arr::get($this->config, "performances.{$this->environment}", []);
    }

    public function getPerformance(string $name): ?array
    {
        return Arr::get($this->getPerformances(), $name);
    }

    public function getManagementConfig(): array
    {
        return Arr::get($this->config, 'management', []);
    }

    public function getMonitoringConfig(): array
    {
        return Arr::get($this->config, 'monitoring', []);
    }

    public function getStorageConfig(): array
    {
        return Arr::get($this->config, 'storage', []);
    }

    public function shouldRestartOnFailure(): bool
    {
        return Arr::get($this->config, 'management.restart_on_failure', true);
    }

    public function getRestartDelay(): int
    {
        return Arr::get($this->config, 'management.restart_delay', 5);
    }

    public function getMaxRestartAttempts(): int
    {
        return Arr::get($this->config, 'management.max_restart_attempts', 10);
    }

    public function getGracefulShutdownTimeout(): int
    {
        return Arr::get($this->config, 'management.graceful_shutdown_timeout', 30);
    }

    public function getHealthCheckInterval(): int
    {
        return Arr::get($this->config, 'management.health_check_interval', 60);
    }

    public function shouldTrackMemory(): bool
    {
        return Arr::get($this->config, 'monitoring.track_memory', true);
    }

    public function shouldTrackCpu(): bool
    {
        return Arr::get($this->config, 'monitoring.track_cpu', true);
    }

    public function getMemoryAlertThreshold(): int
    {
        return Arr::get($this->config, 'monitoring.memory_alert_threshold', 90);
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getAllPerformanceNames(): array
    {
        return array_keys($this->getPerformances());
    }

    public function buildCommand(array $performance): string
    {
        $command = 'php artisan '.$performance['command'];

        if (! empty($performance['options'])) {
            foreach ($performance['options'] as $key => $value) {
                if (is_numeric($key)) {
                    $command .= ' '.$value;
                } else {
                    $command .= ' '.$key.'='.$value;
                }
            }
        }

        return $command;
    }
}
