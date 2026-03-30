<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeofenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'super_admin', 'manager', 'team_lead']);
    }

    public function rules(): array
    {
        return [
            'job_id' => ['sometimes', 'uuid', 'exists:job_sites,id'],
            'name' => ['sometimes', 'string', 'max:100'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'radius_meters' => ['sometimes', 'integer', 'between:50,500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
