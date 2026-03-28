<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'color_tag',
        'lead_employee_id',
        'status',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'lead_employee_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Employee::class, 'current_team_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeamAssignment::class);
    }
}
