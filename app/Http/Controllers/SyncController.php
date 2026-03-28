<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\GeofenceResource;
use App\Http\Resources\JobResource;
use App\Http\Resources\TeamResource;
use App\Models\BreakEntry;
use App\Models\Employee;
use App\Models\Geofence;
use App\Models\Job;
use App\Models\Team;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * Pull: GET /api/v1/sync?last_synced_at=<timestamp>
     * Returns all entities updated since last_synced_at.
     */
    public function pull(Request $request): JsonResponse
    {
        $rawTimestamp = $request->query('last_synced_at');
        if ($rawTimestamp) {
            // Handle URL-decoded '+' (becomes space) in timezone offset like '+00:00'
            $rawTimestamp = preg_replace('/\s(\d{2}:\d{2})$/', '+$1', $rawTimestamp);
            $lastSyncedAt = Carbon::parse($rawTimestamp);
        } else {
            $lastSyncedAt = Carbon::createFromTimestamp(0);
        }

        $geofences = Geofence::where('updated_at', '>', $lastSyncedAt)
            ->where('is_active', true)
            ->get();

        $teams = Team::where('updated_at', '>', $lastSyncedAt)
            ->where('status', 'ACTIVE')
            ->get();

        $jobs = Job::where('updated_at', '>', $lastSyncedAt)
            ->where('status', 'ACTIVE')
            ->get();

        $employees = Employee::where('updated_at', '>', $lastSyncedAt)
            ->where('status', 'ACTIVE')
            ->get();

        return response()->json([
            'data' => [
                'geofences' => GeofenceResource::collection($geofences),
                'teams' => TeamResource::collection($teams),
                'jobs' => JobResource::collection($jobs),
                'employees' => EmployeeResource::collection($employees),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Push: POST /api/v1/sync
     * Receives pending time entries and breaks from mobile device.
     */
    public function push(SyncRequest $request): JsonResponse
    {
        $syncedEntries = [];
        $conflicts = [];

        DB::transaction(function () use ($request, &$syncedEntries, &$conflicts) {
            // Process time entries
            foreach ($request->input('time_entries', []) as $entryData) {
                $employee = Employee::find($entryData['employee_id']);
                if (! $employee) {
                    continue;
                }

                // Check for conflicts — overlapping entry for same employee
                $conflict = TimeEntry::where('employee_id', $entryData['employee_id'])
                    ->where('job_id', $entryData['job_id'])
                    ->where(function ($q) use ($entryData) {
                        $q->where('clock_in', '<=', $entryData['clock_out'] ?? $entryData['clock_in'])
                          ->where(function ($q2) use ($entryData) {
                              $q2->whereNull('clock_out')
                                 ->orWhere('clock_out', '>=', $entryData['clock_in']);
                          });
                    })
                    ->first();

                if ($conflict) {
                    $conflicts[] = [
                        'client_id' => $entryData['client_id'],
                        'server_entry_id' => $conflict->id,
                        'reason' => 'Overlapping time entry exists on server',
                    ];
                    continue;
                }

                $clockIn = Carbon::parse($entryData['clock_in']);
                $clockOut = isset($entryData['clock_out']) ? Carbon::parse($entryData['clock_out']) : null;

                $totalHours = null;
                if ($clockOut) {
                    $totalHours = round($clockIn->diffInMinutes($clockOut) / 60, 2);
                }

                $entry = TimeEntry::create([
                    'employee_id' => $entryData['employee_id'],
                    'job_id' => $entryData['job_id'],
                    'team_id' => $employee->current_team_id,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'clock_in_lat' => $entryData['clock_in_lat'] ?? null,
                    'clock_in_lng' => $entryData['clock_in_lng'] ?? null,
                    'clock_out_lat' => $entryData['clock_out_lat'] ?? null,
                    'clock_out_lng' => $entryData['clock_out_lng'] ?? null,
                    'clock_method' => $entryData['clock_method'],
                    'device_id' => $entryData['device_id'] ?? null,
                    'notes' => $entryData['notes'] ?? null,
                    'total_hours' => $totalHours,
                    'status' => 'ACTIVE',
                    'sync_status' => 'SYNCED',
                ]);

                $syncedEntries[] = [
                    'client_id' => $entryData['client_id'],
                    'server_id' => $entry->id,
                ];
            }

            // Process breaks
            foreach ($request->input('breaks', []) as $breakData) {
                $startTime = Carbon::parse($breakData['start_time']);
                $endTime = isset($breakData['end_time']) ? Carbon::parse($breakData['end_time']) : null;

                $durationMinutes = null;
                if ($endTime) {
                    $durationMinutes = (int) $startTime->diffInMinutes($endTime);
                }

                BreakEntry::create([
                    'time_entry_id' => $breakData['time_entry_id'],
                    'type' => $breakData['type'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_minutes' => $durationMinutes,
                ]);
            }
        });

        return response()->json([
            'data' => [
                'synced_entries' => $syncedEntries,
                'conflicts' => $conflicts,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
