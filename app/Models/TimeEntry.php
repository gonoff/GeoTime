<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeEntry extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'job_id',
        'team_id',
        'clock_in',
        'clock_out',
        'clock_in_lat',
        'clock_in_lng',
        'clock_out_lat',
        'clock_out_lng',
        'clock_method',
        'total_hours',
        'overtime_hours',
        'status',
        'sync_status',
        'device_id',
        'selfie_url',
        'verification_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'total_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'clock_in_lat' => 'decimal:7',
            'clock_in_lng' => 'decimal:7',
            'clock_out_lat' => 'decimal:7',
            'clock_out_lng' => 'decimal:7',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(BreakEntry::class, 'time_entry_id');
    }

    /**
     * Calculate total hours between clock_in and clock_out,
     * subtracting unpaid break time.
     */
    public function calculateTotalHours(): ?float
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return null;
        }

        $totalMinutes = $this->clock_in->diffInMinutes($this->clock_out);

        // Subtract unpaid break minutes
        $unpaidBreakMinutes = $this->breaks()
            ->where('type', 'UNPAID_MEAL')
            ->whereNotNull('end_time')
            ->where('was_interrupted', false)
            ->sum('duration_minutes');

        $workedMinutes = max(0, $totalMinutes - $unpaidBreakMinutes);

        // Auto-deduct lunch based on job site settings
        if ($this->job) {
            $job = $this->job;
            $lunchDuration = $job->lunch_duration_minutes;
            $lunchAfterHours = $job->lunch_after_hours;

            if ($lunchDuration && $lunchAfterHours) {
                $hoursWorked = $workedMinutes / 60;
                if ($hoursWorked >= $lunchAfterHours) {
                    $workedMinutes = max(0, $workedMinutes - $lunchDuration);
                }
            }
        }

        return round($workedMinutes / 60, 2);
    }
}
