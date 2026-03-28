<?php

namespace App\Http\Controllers;

use App\Http\Requests\EndBreakRequest;
use App\Http\Requests\StoreBreakRequest;
use App\Http\Resources\BreakEntryResource;
use App\Models\BreakEntry;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;

class BreakEntryController extends Controller
{
    public function store(StoreBreakRequest $request): JsonResponse
    {
        $timeEntry = TimeEntry::findOrFail($request->time_entry_id);

        // Cannot start break on a completed time entry
        if ($timeEntry->clock_out) {
            return response()->json([
                'message' => 'Cannot start break on a completed time entry',
            ], 422);
        }

        // Check if there's already an active break
        $activeBreak = BreakEntry::where('time_entry_id', $timeEntry->id)
            ->whereNull('end_time')
            ->first();

        if ($activeBreak) {
            return response()->json([
                'message' => 'There is already an active break',
            ], 422);
        }

        $breakEntry = BreakEntry::create([
            'time_entry_id' => $timeEntry->id,
            'type' => $request->type,
            'start_time' => $request->start_time ?? now(),
        ]);

        return (new BreakEntryResource($breakEntry))
            ->response()
            ->setStatusCode(201);
    }

    public function end(EndBreakRequest $request, BreakEntry $breakEntry): JsonResponse
    {
        if ($breakEntry->end_time) {
            return response()->json([
                'message' => 'Break has already ended',
            ], 422);
        }

        $endTime = $request->end_time ?? now();

        $breakEntry->update([
            'end_time' => $endTime,
            'was_interrupted' => $request->boolean('was_interrupted', false),
        ]);

        $breakEntry->update([
            'duration_minutes' => $breakEntry->calculateDuration(),
        ]);

        return response()->json([
            'data' => new BreakEntryResource($breakEntry->fresh()),
        ]);
    }
}
