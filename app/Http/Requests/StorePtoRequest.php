<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePtoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'type' => ['required', 'string', 'in:VACATION,SICK,PERSONAL,UNPAID'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'hours' => ['required', 'numeric', 'min:0.5'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
