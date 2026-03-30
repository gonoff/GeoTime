<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'super_admin', 'manager', 'team_lead']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'color_tag' => ['nullable', 'string', 'max:7'],
            'lead_employee_id' => ['nullable', 'uuid', 'exists:employees,id'],
        ];
    }
}
