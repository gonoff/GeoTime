<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'hourly_rate' => $this->hourly_rate,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'hire_date' => $this->hire_date?->toDateString(),
            'address' => $this->address,
            'device_id' => $this->device_id,
            'status' => $this->status,
            'current_team_id' => $this->current_team_id,
            'qbo_employee_id' => $this->qbo_employee_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
