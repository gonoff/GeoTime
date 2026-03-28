<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'job_id' => $this->job_id,
            'job' => $this->whenLoaded('job', fn () => new JobResource($this->job)),
            'team_id' => $this->team_id,
            'clock_in' => $this->clock_in?->toIso8601String(),
            'clock_out' => $this->clock_out?->toIso8601String(),
            'clock_in_lat' => $this->clock_in_lat ? (float) $this->clock_in_lat : null,
            'clock_in_lng' => $this->clock_in_lng ? (float) $this->clock_in_lng : null,
            'clock_out_lat' => $this->clock_out_lat ? (float) $this->clock_out_lat : null,
            'clock_out_lng' => $this->clock_out_lng ? (float) $this->clock_out_lng : null,
            'clock_method' => $this->clock_method,
            'total_hours' => $this->total_hours ? (float) $this->total_hours : null,
            'overtime_hours' => $this->overtime_hours ? (float) $this->overtime_hours : null,
            'status' => $this->status,
            'sync_status' => $this->sync_status,
            'device_id' => $this->device_id,
            'notes' => $this->notes,
            'breaks' => BreakEntryResource::collection($this->whenLoaded('breaks')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
