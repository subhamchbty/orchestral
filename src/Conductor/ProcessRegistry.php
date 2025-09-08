<?php

namespace Subhamchbty\Orchestral\Conductor;

use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ProcessRegistry
{
    protected const CACHE_KEY = 'orchestral:processes';
    protected const CONDUCTING_KEY = 'orchestral:conducting';

    public function savePerformers($performers): void
    {
        $processData = [];
        
        foreach ($performers as $performer) {
            // Handle both Performer objects and arrays
            if (is_object($performer)) {
                $pid = $performer->getPid();
                
                $processData[] = [
                    'name' => $performer->getName(),
                    'command' => $performer->getCommand(),
                    'pid' => $pid,
                    'started_at' => now()->toIso8601String(),
                ];
            } else {
                $processData[] = $performer;
            }
        }
        
        Cache::put(self::CACHE_KEY, $processData, now()->addDays(7));
    }

    public function loadPerformers(): array
    {
        $processData = Cache::get(self::CACHE_KEY, []);
        $activeProcesses = [];
        
        foreach ($processData as $data) {
            if ($this->isProcessRunning($data['pid'])) {
                $activeProcesses[] = $data;
            }
        }
        
        // Clean up if processes have changed or refresh TTL
        if (count($activeProcesses) !== count($processData)) {
            Cache::put(self::CACHE_KEY, $activeProcesses, now()->addDays(7));
        } else {
            // Refresh TTL to keep active processes in cache
            Cache::put(self::CACHE_KEY, $activeProcesses, now()->addDays(7));
        }
        
        return $activeProcesses;
    }

    public function clearPerformers(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function setConducting(bool $conducting): void
    {
        if ($conducting) {
            Cache::put(self::CONDUCTING_KEY, true, now()->addDays(7));
        } else {
            Cache::forget(self::CONDUCTING_KEY);
        }
    }

    public function isConducting(): bool
    {
        return Cache::has(self::CONDUCTING_KEY);
    }

    protected function isProcessRunning(?int $pid): bool
    {
        if (!$pid) {
            return false;
        }
        
        return file_exists("/proc/{$pid}");
    }

    public function getProcessInfo(int $pid): ?array
    {
        if (!$this->isProcessRunning($pid)) {
            return null;
        }

        $info = [
            'pid' => $pid,
            'running' => true,
            'memory_mb' => null,
            'cpu_percent' => null,
        ];

        // Get memory usage
        $statusFile = "/proc/{$pid}/status";
        if (file_exists($statusFile)) {
            $status = file_get_contents($statusFile);
            if (preg_match('/VmRSS:\s+(\d+)\s+kB/', $status, $matches)) {
                $info['memory_mb'] = round($matches[1] / 1024, 2);
            }
        }

        // Get CPU usage
        exec("ps -p {$pid} -o %cpu --no-headers 2>/dev/null", $output);
        if (!empty($output[0])) {
            $info['cpu_percent'] = (float) trim($output[0]);
        }

        return $info;
    }
}