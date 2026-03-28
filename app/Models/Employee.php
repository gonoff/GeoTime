<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'current_team_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'hourly_rate',
        'ssn_encrypted',
        'date_of_birth',
        'address',
        'hire_date',
        'device_id',
        'status',
        'qbo_employee_id',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function teamAssignments(): HasMany
    {
        return $this->hasMany(TeamAssignment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(Transfer::class, 'employee_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
