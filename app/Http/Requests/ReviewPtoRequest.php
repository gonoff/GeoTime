<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPtoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()->role;
        return in_array($role, ['admin', 'super_admin', 'manager', 'team_lead']);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approve,deny'],
            'reason' => ['nullable', 'string', 'required_if:action,deny'],
        ];
    }
}
