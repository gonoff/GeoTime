<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasUuids;

    protected $attributes = [
        'overtime_rule' => '{"weekly_threshold": 40, "daily_threshold": null, "multiplier": 1.5}',
    ];

    protected $fillable = [
        'name',
        'timezone',
        'workweek_start_day',
        'overtime_rule',
        'rounding_rule',
        'clock_verification_mode',
        'plan',
        'status',
        'trial_ends_at',
        'stripe_id',
    ];

    protected function casts(): array
    {
        return [
            'overtime_rule' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function onTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
