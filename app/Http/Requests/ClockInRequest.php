<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by sanctum middleware
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'job_id' => ['required', 'uuid', 'exists:job_sites,id'],
            'clock_in_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'clock_in_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'clock_method' => ['required', 'string', 'in:GEOFENCE,MANUAL,KIOSK,ADMIN_OVERRIDE'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'clock_in' => ['nullable', 'date'], // for sync/admin override
        ];
    }
}
