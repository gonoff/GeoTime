<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtoBalance extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'type',
        'balance_hours',
        'accrued_hours',
        'used_hours',
        'year',
    ];

    protected function casts(): array
    {
        return [
            'balance_hours' => 'decimal:2',
            'accrued_hours' => 'decimal:2',
            'used_hours' => 'decimal:2',
            'year' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
