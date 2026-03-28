<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class SettingsPageController extends Controller
{
    public function index()
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return Inertia::render('Settings/Index', [
            'settings' => [
                'company_name' => $tenant?->name ?? '',
                'timezone' => $tenant?->timezone ?? 'America/New_York',
                'workweek_start_day' => $tenant?->workweek_start_day ?? 'monday',
                'overtime_rule' => $tenant?->overtime_rule ?? ['weekly_threshold' => 40, 'daily_threshold' => null, 'multiplier' => 1.5],
                'rounding_rule' => $tenant?->rounding_rule ?? 'none',
                'clock_verification_mode' => $tenant?->clock_verification_mode ?? 'none',
            ],
        ]);
    }
}
