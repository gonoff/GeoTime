<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBreakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time_entry_id' => ['required', 'uuid', 'exists:time_entries,id'],
            'type' => ['required', 'string', 'in:PAID_REST,UNPAID_MEAL'],
            'start_time' => ['nullable', 'date'], // default to now()
        ];
    }
}
