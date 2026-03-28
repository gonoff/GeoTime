<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'from_team_id' => $this->from_team_id,
            'from_team' => $this->whenLoaded('fromTeam', fn () => new TeamResource($this->fromTeam)),
            'to_team_id' => $this->to_team_id,
            'to_team' => $this->whenLoaded('toTeam', fn () => new TeamResource($this->toTeam)),
            'reason_category' => $this->reason_category,
            'reason_code' => $this->reason_code,
            'notes' => $this->notes,
            'transfer_type' => $this->transfer_type,
            'effective_date' => $this->effective_date?->toDateString(),
            'expected_return_date' => $this->expected_return_date?->toDateString(),
            'initiated_by' => $this->initiated_by,
            'approved_by' => $this->approved_by,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
