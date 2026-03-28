<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakEntry extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'time_entry_id',
        'type',
        'start_time',
        'end_time',
        'duration_minutes',
        'was_interrupted',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration_minutes' => 'integer',
            'was_interrupted' => 'boolean',
        ];
    }

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class);
    }

    /**
     * Calculate duration in minutes from start to end.
     */
    public function calculateDuration(): ?int
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }

        return (int) $this->start_time->diffInMinutes($this->end_time);
    }
}
