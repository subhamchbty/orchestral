<?php

namespace Subhamchbty\Orchestral\Models;

use Illuminate\Database\Eloquent\Model;

class Performance extends Model
{
    protected $table = 'orchestral_performances';

    protected $fillable = [
        'event',
        'performer_name',
        'environment',
        'data',
        'occurred_at',
    ];

    protected $casts = [
        'data' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }

    public function scopeByEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    public function scopeByPerformer($query, string $performerName)
    {
        return $query->where('performer_name', $performerName);
    }

    public function scopeByEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeFailures($query)
    {
        return $query->whereIn('event', ['performer_failed', 'memory_exceeded']);
    }

    public function scopeRestarts($query)
    {
        return $query->where('event', 'performer_restarted');
    }
}