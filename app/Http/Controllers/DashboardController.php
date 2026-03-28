<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        // Stats
        $totalEmployees = $tenant ? Employee::count() : 0;

        $clockedIn = $tenant ? TimeEntry::whereNull('clock_out')
            ->where('status', 'ACTIVE')
            ->count() : 0;

        $overtimeAlerts = 0; // TODO: Calculate from weekly hours > 35
        $pendingApprovals = $tenant ? TimeEntry::where('status', 'SUBMITTED')->count() : 0;
        $unverifiedEntries = $tenant ? TimeEntry::where('verification_status', 'UNVERIFIED')->count() : 0;

        // Recent activity (today's clock events)
        $activity = $tenant ? TimeEntry::with('employee')
            ->whereDate('clock_in', today())
            ->orderByDesc('clock_in')
            ->limit(10)
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'employee' => $entry->employee?->full_name ?? 'Unknown',
                'action' => $entry->clock_out ? 'clocked out' : 'clocked in',
                'type' => $entry->clock_out ? 'clock_out' : 'clock_in',
                'time' => $entry->clock_in->format('g:i A'),
            ]) : collect();

        // Alerts
        $alerts = collect();

        if ($tenant) {
            // Pending transfers
            $pendingTransfers = Transfer::where('status', 'PENDING')->count();
            if ($pendingTransfers > 0) {
                $alerts->push([
                    'id' => 'pending-transfers',
                    'severity' => 'info',
                    'message' => "{$pendingTransfers} transfer(s) awaiting approval",
                    'meta' => 'Review in Transfers',
                ]);
            }

            // Unverified entries
            if ($unverifiedEntries > 0) {
                $alerts->push([
                    'id' => 'unverified',
                    'severity' => 'warning',
                    'message' => "{$unverifiedEntries} clock entries missing photo verification",
                    'meta' => 'Review in Time Entries',
                ]);
            }

            // Missing clock-outs (clocked in for > 12 hours)
            $staleEntries = TimeEntry::whereNull('clock_out')
                ->where('clock_in', '<', now()->subHours(12))
                ->count();
            if ($staleEntries > 0) {
                $alerts->push([
                    'id' => 'stale-entries',
                    'severity' => 'critical',
                    'message' => "{$staleEntries} employee(s) clocked in for 12+ hours",
                    'meta' => 'Possible missed clock-out',
                ]);
            }
        }

        // Team status
        $teams = $tenant ? Team::where('status', 'ACTIVE')
            ->withCount('members')
            ->get()
            ->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'color' => $team->color_tag,
                'clockedIn' => TimeEntry::where('team_id', $team->id)
                    ->whereNull('clock_out')
                    ->where('status', 'ACTIVE')
                    ->count(),
                'onBreak' => 0, // TODO: Calculate from active breaks
                'absent' => $team->members_count,
            ]) : collect();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalEmployees' => $totalEmployees,
                'clockedIn' => $clockedIn,
                'overtimeAlerts' => $overtimeAlerts,
                'pendingApprovals' => $pendingApprovals,
                'unverifiedEntries' => $unverifiedEntries,
            ],
            'activity' => $activity,
            'alerts' => $alerts,
            'teams' => $teams,
        ]);
    }
}
