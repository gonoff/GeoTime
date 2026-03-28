<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'in:EMPLOYEE,TEAM_LEAD,MANAGER,ADMIN,SUPER_ADMIN'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'ssn_encrypted' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string'],
            'address.city' => ['nullable', 'string'],
            'address.state' => ['nullable', 'string'],
            'address.zip' => ['nullable', 'string'],
            'hire_date' => ['required', 'date'],
            'device_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
