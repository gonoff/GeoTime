<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['sometimes', 'string', 'in:EMPLOYEE,TEAM_LEAD,MANAGER,ADMIN,SUPER_ADMIN'],
            'hourly_rate' => ['sometimes', 'numeric', 'min:0'],
            'ssn_encrypted' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'array'],
            'hire_date' => ['sometimes', 'date'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE,TERMINATED'],
        ];
    }
}
