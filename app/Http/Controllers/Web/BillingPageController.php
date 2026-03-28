<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Inertia\Inertia;

class BillingPageController extends Controller
{
    public function index()
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return Inertia::render('Billing/Index', [
            'billing' => [
                'plan' => $tenant?->plan ?? 'starter',
                'status' => $tenant?->status ?? 'trial',
                'trial_ends_at' => $tenant?->trial_ends_at?->format('Y-m-d'),
                'on_trial' => $tenant?->onTrial() ?? false,
                'employee_count' => $tenant ? Employee::count() : 0,
            ],
        ]);
    }
}
