<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
                'workweek_start_day' => $tenant?->workweek_start_day ?? 1,
                'overtime_rule' => $tenant?->overtime_rule ?? ['weekly_threshold' => 40, 'daily_threshold' => null, 'multiplier' => 1.5],
                'rounding_rule' => $tenant?->rounding_rule ?? 'EXACT',
                'clock_verification_mode' => $tenant?->clock_verification_mode ?? 'AUTO_ONLY',
            ],
        ]);
    }

    public function update(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'timezone' => ['required', 'string', 'timezone'],
            'workweek_start_day' => ['required', 'integer', 'between:0,6'],
            'clock_verification_mode' => ['required', 'in:AUTO_ONLY,AUTO_PHOTO'],
            'overtime_weekly_threshold' => ['required', 'numeric', 'min:0'],
            'overtime_daily_threshold' => ['nullable', 'numeric', 'min:0'],
            'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:3'],
            'rounding_rule' => ['required', 'in:EXACT,ROUND_UP,ROUND_DOWN,QUARTER,HALF'],
        ]);

        $tenant = app('current_tenant');
        $tenant->update([
            'timezone' => $validated['timezone'],
            'workweek_start_day' => $validated['workweek_start_day'],
            'clock_verification_mode' => $validated['clock_verification_mode'],
            'overtime_rule' => [
                'weekly_threshold' => $validated['overtime_weekly_threshold'],
                'daily_threshold' => $validated['overtime_daily_threshold'],
                'multiplier' => $validated['overtime_multiplier'],
            ],
            'rounding_rule' => $validated['rounding_rule'],
        ]);

        return back()->with('success', 'Settings updated successfully.');
    }
}
