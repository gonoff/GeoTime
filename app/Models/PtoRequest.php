<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtoRequest extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'hours',
        'notes',
        'status',
        'reviewed_by',
        'review_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'hours' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
