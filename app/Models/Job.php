<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    use HasUuids, BelongsToTenant;

    protected $table = 'job_sites';

    protected $fillable = [
        'tenant_id',
        'name',
        'client_name',
        'qbo_customer_id',
        'address',
        'status',
        'lunch_duration_minutes',
        'lunch_after_hours',
        'budget_hours',
        'hourly_rate',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'budget_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function geofences(): HasMany
    {
        return $this->hasMany(Geofence::class, 'job_id');
    }
}
