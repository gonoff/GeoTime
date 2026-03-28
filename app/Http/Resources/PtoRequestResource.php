<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PtoRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'type' => $this->type,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'hours' => (float) $this->hours,
            'notes' => $this->notes,
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'review_reason' => $this->review_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
