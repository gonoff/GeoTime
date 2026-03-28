<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use HasUuids, BelongsToTenant;

    public const REASON_CATEGORIES = [
        'OPERATIONAL', 'PERFORMANCE', 'EMPLOYEE_REQUEST', 'ADMINISTRATIVE',
    ];

    public const REASON_CODES = [
        'WORKLOAD_BALANCE', 'SKILL_MATCH', 'PROJECT_NEED', 'LOCATION_CHANGE',
        'PERFORMANCE_IMPROVEMENT', 'PROMOTION', 'MENTOR_ASSIGNMENT',
        'PERSONAL_REQUEST', 'SCHEDULE_ACCOMMODATION', 'CONFLICT_RESOLUTION',
        'TEAM_RESTRUCTURE', 'TEAM_DISSOLUTION', 'SEASONAL_ADJUSTMENT', 'OTHER',
    ];

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'from_team_id',
        'to_team_id',
        'reason_category',
        'reason_code',
        'notes',
        'transfer_type',
        'effective_date',
        'expected_return_date',
        'initiated_by',
        'approved_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'expected_return_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'to_team_id');
    }
}
