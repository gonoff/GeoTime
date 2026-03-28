<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BreakEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'time_entry_id' => $this->time_entry_id,
            'type' => $this->type,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'was_interrupted' => $this->was_interrupted,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
