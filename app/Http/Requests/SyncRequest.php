<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time_entries' => ['present', 'array'],
            'time_entries.*.client_id' => ['required', 'string'],
            'time_entries.*.employee_id' => ['required', 'uuid'],
            'time_entries.*.job_id' => ['required', 'uuid'],
            'time_entries.*.clock_in' => ['required', 'date'],
            'time_entries.*.clock_out' => ['nullable', 'date'],
            'time_entries.*.clock_in_lat' => ['nullable', 'numeric'],
            'time_entries.*.clock_in_lng' => ['nullable', 'numeric'],
            'time_entries.*.clock_out_lat' => ['nullable', 'numeric'],
            'time_entries.*.clock_out_lng' => ['nullable', 'numeric'],
            'time_entries.*.clock_method' => ['required', 'string', 'in:GEOFENCE,MANUAL,KIOSK,ADMIN_OVERRIDE'],
            'time_entries.*.device_id' => ['nullable', 'string'],
            'time_entries.*.notes' => ['nullable', 'string'],

            'breaks' => ['present', 'array'],
            'breaks.*.client_id' => ['required', 'string'],
            'breaks.*.time_entry_id' => ['required', 'uuid'],
            'breaks.*.type' => ['required', 'string', 'in:PAID_REST,UNPAID_MEAL'],
            'breaks.*.start_time' => ['required', 'date'],
            'breaks.*.end_time' => ['nullable', 'date'],
        ];
    }
}
