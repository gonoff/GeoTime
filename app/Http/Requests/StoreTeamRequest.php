<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
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
