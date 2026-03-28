<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewTimesheetRequest;
use App\Http\Requests\SubmitTimesheetRequest;
use App\Models\TimeEntry;
use App\Services\OvertimeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimesheetController extends Controller
{
    public function submit(SubmitTimesheetRequest $request): JsonResponse
    {
        $updated = TimeEntry::where('employee_id', $request->employee_id)
            ->where('status', 'ACTIVE')
            ->whereDate('clock_in', '>=', $request->week_start)
            ->whereDate('clock_in', '<=', $request->week_end)
            ->update(['status' => 'SUBMITTED']);

        return response()->json([
            'message' => 'Timesheet submitted for review',
            'entries_submitted' => $updated,
        ]);
    }

    public function review(ReviewTimesheetRequest $request): JsonResponse
    {
        $newStatus = $request->action === 'approve' ? 'APPROVED' : 'REJECTED';

        $updated = TimeEntry::where('employee_id', $request->employee_id)
            ->where('status', 'SUBMITTED')
            ->whereDate('clock_in', '>=', $request->week_start)
            ->whereDate('clock_in', '<=', $request->week_end)
            ->update(['status' => $newStatus]);

        $action = $request->action === 'approve' ? 'approved' : 'rejected';

        return response()->json([
            'message' => "Timesheet {$action}",
            'entries_updated' => $updated,
        ]);
    }

    public function processPayroll(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
            'week_end' => ['required', 'date'],
        ]);

        // Calculate overtime for the week
        $entries = TimeEntry::where('employee_id', $request->employee_id)
            ->where('status', 'APPROVED')
            ->whereDate('clock_in', '>=', $request->week_start)
            ->whereDate('clock_in', '<=', $request->week_end)
            ->get();

        $tenant = app('current_tenant');
        $calculator = OvertimeCalculator::fromConfig($tenant->overtime_rule);

        $weeklyHours = (float) $entries->sum('total_hours');
        $maxDailyHours = (float) $entries->max('total_hours');

        $overtime = $calculator->calculate($weeklyHours, $maxDailyHours);

        // Mark all entries as payroll processed
        $updated = TimeEntry::where('employee_id', $request->employee_id)
            ->where('status', 'APPROVED')
            ->whereDate('clock_in', '>=', $request->week_start)
            ->whereDate('clock_in', '<=', $request->week_end)
            ->update(['status' => 'PAYROLL_PROCESSED']);

        return response()->json([
            'message' => 'Payroll processed',
            'entries_processed' => $updated,
            'weekly_summary' => [
                'total_hours' => $weeklyHours,
                'regular_hours' => $overtime['regular_hours'],
                'overtime_hours' => $overtime['overtime_hours'],
                'overtime_multiplier' => $overtime['multiplier'],
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
            'week_end' => ['required', 'date'],
        ]);

        $entries = TimeEntry::where('employee_id', $request->employee_id)
            ->whereDate('clock_in', '>=', $request->week_start)
            ->whereDate('clock_in', '<=', $request->week_end)
            ->get();

        $tenant = app('current_tenant');
        $calculator = OvertimeCalculator::fromConfig($tenant->overtime_rule);

        $weeklyHours = (float) $entries->sum('total_hours');
        $maxDailyHours = (float) $entries->max('total_hours');
        $overtime = $calculator->calculate($weeklyHours, $maxDailyHours);

        return response()->json([
            'data' => [
                'employee_id' => $request->employee_id,
                'week_start' => $request->week_start,
                'week_end' => $request->week_end,
                'total_entries' => $entries->count(),
                'total_hours' => $weeklyHours,
                'regular_hours' => $overtime['regular_hours'],
                'overtime_hours' => $overtime['overtime_hours'],
                'status_breakdown' => $entries->groupBy('status')->map->count(),
            ],
        ]);
    }
}
