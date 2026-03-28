<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClockInRequest;
use App\Http\Requests\ClockOutRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TimeEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = TimeEntry::query()
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('job_id'), fn ($q, $id) => $q->where('job_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('date_from'), fn ($q, $date) => $q->whereDate('clock_in', '>=', $date))
            ->when($request->query('date_to'), fn ($q, $date) => $q->whereDate('clock_in', '<=', $date))
            ->with(['employee', 'job', 'breaks'])
            ->orderByDesc('clock_in')
            ->paginate($request->query('per_page', 25));

        return TimeEntryResource::collection($entries);
    }

    public function show(TimeEntry $timeEntry): TimeEntryResource
    {
        $timeEntry->load(['employee', 'job', 'breaks']);

        return new TimeEntryResource($timeEntry);
    }

    public function clockIn(ClockInRequest $request): JsonResponse
    {
        $employee = Employee::findOrFail($request->employee_id);

        // Check if already clocked in
        $activeEntry = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->where('status', 'ACTIVE')
            ->first();

        if ($activeEntry) {
            return response()->json([
                'message' => 'Employee is already clocked in',
            ], 422);
        }

        $tenant = Tenant::find($employee->tenant_id);

        $entry = TimeEntry::create([
            'employee_id' => $employee->id,
            'job_id' => $request->job_id,
            'team_id' => $employee->current_team_id,
            'clock_in' => $request->clock_in ?? now(),
            'clock_in_lat' => $request->clock_in_lat,
            'clock_in_lng' => $request->clock_in_lng,
            'clock_method' => $request->clock_method,
            'device_id' => $request->device_id,
            'notes' => $request->notes,
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
            'verification_status' => $tenant->clock_verification_mode === 'AUTO_PHOTO' ? 'UNVERIFIED' : 'NOT_REQUIRED',
        ]);

        return (new TimeEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function clockOut(ClockOutRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        if ($timeEntry->clock_out) {
            return response()->json([
                'message' => 'Already clocked out',
            ], 422);
        }

        $clockOut = $request->clock_out ?? now();

        $timeEntry->update([
            'clock_out' => $clockOut,
            'clock_out_lat' => $request->clock_out_lat,
            'clock_out_lng' => $request->clock_out_lng,
        ]);

        // Calculate total hours
        $totalHours = $timeEntry->calculateTotalHours();
        $timeEntry->update(['total_hours' => $totalHours]);

        if ($request->notes) {
            $timeEntry->update(['notes' => $timeEntry->notes . "\n" . $request->notes]);
        }

        return response()->json([
            'data' => new TimeEntryResource($timeEntry->fresh()->load('breaks')),
        ]);
    }

    public function update(Request $request, TimeEntry $timeEntry): TimeEntryResource
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            abort(403, 'Forbidden');
        }

        $validated = $request->validate([
            'clock_in' => ['sometimes', 'date'],
            'clock_out' => ['sometimes', 'date', 'nullable'],
            'job_id' => ['sometimes', 'uuid', 'exists:job_sites,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,SUBMITTED,APPROVED,REJECTED,PAYROLL_PROCESSED'],
        ]);

        $timeEntry->update($validated);

        // Recalculate total hours if clock times changed
        if (isset($validated['clock_in']) || isset($validated['clock_out'])) {
            $timeEntry->update(['total_hours' => $timeEntry->calculateTotalHours()]);
        }

        return new TimeEntryResource($timeEntry->fresh());
    }

    public function verify(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        $request->validate([
            'selfie' => ['required', 'image', 'max:5120'], // 5MB max
        ]);

        $path = $request->file('selfie')->store(
            "tenants/{$timeEntry->tenant_id}/selfies",
            's3'
        );

        $timeEntry->update([
            'selfie_url' => $path,
            'verification_status' => 'VERIFIED',
        ]);

        return response()->json(['data' => $timeEntry->fresh()]);
    }
}
