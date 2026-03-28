<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color_tag' => $this->color_tag,
            'lead_employee_id' => $this->lead_employee_id,
            'lead' => $this->whenLoaded('lead', fn () => new EmployeeResource($this->lead)),
            'status' => $this->status,
            'member_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
