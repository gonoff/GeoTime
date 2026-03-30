<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimesheetPageController extends Controller
{
    public function index(Request $request)
    {
        // Determine the week (Monday-Sunday)
        $weekStart = $request->input('week_start')
            ? Carbon::parse($request->input('week_start'))->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        // Get all time entries for the week grouped by employee
        $entries = TimeEntry::with('employee')
            ->whereDate('clock_in', '>=', $weekStart)
            ->whereDate('clock_in', '<=', $weekEnd)
            ->orderBy('employee_id')
            ->get();

        // Group by employee and build daily totals
        $grouped = $entries->groupBy('employee_id');

        $timesheets = $grouped->map(function ($employeeEntries) use ($weekStart) {
            $employee = $employeeEntries->first()->employee;
            if (!$employee) return null;

            $dailyHours = [];
            $totalHours = 0;

            for ($i = 0; $i < 7; $i++) {
                $day = $weekStart->copy()->addDays($i);
                $dayKey = $day->format('Y-m-d');

                $hours = $employeeEntries
                    ->filter(fn ($e) => $e->clock_in->format('Y-m-d') === $dayKey)
                    ->sum('total_hours');

                $dailyHours[] = round((float) $hours, 2);
                $totalHours += (float) $hours;
            }

            // Determine the dominant status for the week
            $statuses = $employeeEntries->pluck('status')->unique();
            if ($statuses->contains('PAYROLL_PROCESSED')) {
                $status = 'PAYROLL_PROCESSED';
            } elseif ($statuses->contains('APPROVED')) {
                $status = 'APPROVED';
            } elseif ($statuses->contains('SUBMITTED')) {
                $status = 'SUBMITTED';
            } elseif ($statuses->contains('REJECTED')) {
                $status = 'REJECTED';
            } else {
                $status = 'ACTIVE';
            }

            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'daily_hours' => $dailyHours,
                'total_hours' => round($totalHours, 2),
                'status' => $status,
            ];
        })->filter()->values()->all();

        return Inertia::render('Timesheets/Index', [
            'timesheets' => $timesheets,
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'week_label' => $weekStart->format('M j') . ' - ' . $weekEnd->format('M j'),
        ]);
    }

    public function approve(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
            abort(403);
        }

        $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
        ]);

        $weekStart = Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        TimeEntry::where('employee_id', $request->employee_id)
            ->whereDate('clock_in', '>=', $weekStart)
            ->whereDate('clock_in', '<=', $weekEnd)
            ->where('status', 'SUBMITTED')
            ->update(['status' => 'APPROVED']);

        return back()->with('success', 'Timesheet approved.');
    }

    public function reject(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
            abort(403);
        }

        $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
        ]);

        $weekStart = Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        TimeEntry::where('employee_id', $request->employee_id)
            ->whereDate('clock_in', '>=', $weekStart)
            ->whereDate('clock_in', '<=', $weekEnd)
            ->where('status', 'SUBMITTED')
            ->update(['status' => 'REJECTED']);

        return back()->with('success', 'Timesheet rejected.');
    }

    public function processPayroll(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
        ]);

        $weekStart = Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        TimeEntry::where('employee_id', $request->employee_id)
            ->whereDate('clock_in', '>=', $weekStart)
            ->whereDate('clock_in', '<=', $weekEnd)
            ->where('status', 'APPROVED')
            ->update(['status' => 'PAYROLL_PROCESSED']);

        return back()->with('success', 'Timesheet processed for payroll.');
    }

    public function bulkApprove(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
            abort(403);
        }

        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'items.*.week_start' => ['required', 'date'],
        ]);

        foreach ($request->items as $item) {
            $weekStart = Carbon::parse($item['week_start'])->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->addDays(6);

            TimeEntry::where('employee_id', $item['employee_id'])
                ->whereDate('clock_in', '>=', $weekStart)
                ->whereDate('clock_in', '<=', $weekEnd)
                ->where('status', 'SUBMITTED')
                ->update(['status' => 'APPROVED']);
        }

        return back()->with('success', count($request->items) . ' timesheets approved.');
    }

    public function bulkReject(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
            abort(403);
        }

        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'items.*.week_start' => ['required', 'date'],
        ]);

        foreach ($request->items as $item) {
            $weekStart = Carbon::parse($item['week_start'])->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->addDays(6);

            TimeEntry::where('employee_id', $item['employee_id'])
                ->whereDate('clock_in', '>=', $weekStart)
                ->whereDate('clock_in', '<=', $weekEnd)
                ->where('status', 'SUBMITTED')
                ->update(['status' => 'REJECTED']);
        }

        return back()->with('success', count($request->items) . ' timesheets rejected.');
    }
}
