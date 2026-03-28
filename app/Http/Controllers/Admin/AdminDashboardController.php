<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $totalTenants = Tenant::count();
        $totalUsers = User::withoutGlobalScopes()->count();
        $totalEmployees = Employee::withoutGlobalScopes()->count();

        $tenantsByPlan = Tenant::query()
            ->selectRaw('plan, count(*) as count')
            ->groupBy('plan')
            ->pluck('count', 'plan');

        $tenantsByStatus = Tenant::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $activeSubscriptions = Tenant::where('status', 'active')->count();

        $recentTenants = Tenant::query()
            ->withCount([
                'users' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $tenant->plan,
                'status' => $tenant->status,
                'users_count' => $tenant->users_count,
                'created_at' => $tenant->created_at->format('M d, Y'),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalTenants' => $totalTenants,
                'totalUsers' => $totalUsers,
                'totalEmployees' => $totalEmployees,
                'activeSubscriptions' => $activeSubscriptions,
            ],
            'tenantsByPlan' => $tenantsByPlan,
            'tenantsByStatus' => $tenantsByStatus,
            'recentTenants' => $recentTenants,
        ]);
    }
}
