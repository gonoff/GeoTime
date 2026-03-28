<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TeamAssignment;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;

class TransferService
{
    /**
     * Execute an approved transfer — move the employee to the new team
     * and update team assignment history.
     */
    public function executeTransfer(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            // End current team assignment
            TeamAssignment::where('employee_id', $transfer->employee_id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Create new team assignment
            TeamAssignment::create([
                'tenant_id' => $transfer->tenant_id,
                'employee_id' => $transfer->employee_id,
                'team_id' => $transfer->to_team_id,
                'assigned_at' => now(),
                'assigned_by' => $transfer->approved_by ?? $transfer->initiated_by,
            ]);

            // Update employee's current team
            Employee::withoutGlobalScopes()
                ->where('id', $transfer->employee_id)
                ->update(['current_team_id' => $transfer->to_team_id]);

            // Mark transfer as completed
            $transfer->update(['status' => 'COMPLETED']);
        });
    }

    /**
     * Revert a temporary transfer — move the employee back to the original team.
     */
    public function revertTransfer(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            // End current team assignment
            TeamAssignment::where('employee_id', $transfer->employee_id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Create assignment back to original team
            TeamAssignment::create([
                'tenant_id' => $transfer->tenant_id,
                'employee_id' => $transfer->employee_id,
                'team_id' => $transfer->from_team_id,
                'assigned_at' => now(),
                'assigned_by' => $transfer->initiated_by,
            ]);

            // Update employee's current team
            Employee::withoutGlobalScopes()
                ->where('id', $transfer->employee_id)
                ->update(['current_team_id' => $transfer->from_team_id]);

            // Mark transfer as reverted
            $transfer->update(['status' => 'REVERTED']);
        });
    }
}
