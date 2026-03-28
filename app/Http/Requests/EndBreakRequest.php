<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EndBreakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'was_interrupted' => ['sometimes', 'boolean'],
            'end_time' => ['nullable', 'date'], // default to now()
        ];
    }
}
