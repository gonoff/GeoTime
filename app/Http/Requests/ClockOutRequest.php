<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clock_out_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'clock_out_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string'],
            'clock_out' => ['nullable', 'date'], // for sync/admin override
        ];
    }
}
