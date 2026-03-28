<?php

namespace App\Http\Requests;

use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admins, managers, and team leads (team leads create pending transfers)
        $role = $this->user()->role;
        return in_array($role, ['admin', 'super_admin', 'manager', 'team_lead']);
    }

    public function rules(): array
    {
        $reasonCodes = implode(',', Transfer::REASON_CODES);
        $reasonCategories = implode(',', Transfer::REASON_CATEGORIES);

        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'from_team_id' => ['required', 'uuid', 'exists:teams,id'],
            'to_team_id' => ['required', 'uuid', 'exists:teams,id', 'different:from_team_id'],
            'reason_category' => ['required', 'string', "in:{$reasonCategories}"],
            'reason_code' => ['required', 'string', "in:{$reasonCodes}"],
            'notes' => ['nullable', 'string', 'required_if:reason_code,OTHER'],
            'transfer_type' => ['required', 'string', 'in:PERMANENT,TEMPORARY'],
            'effective_date' => ['required', 'date'],
            'expected_return_date' => ['nullable', 'date', 'after:effective_date', 'required_if:transfer_type,TEMPORARY'],
        ];
    }
}
