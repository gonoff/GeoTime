<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Tenant::query()
            ->withCount([
                'users' => fn ($q) => $q->withoutGlobalScopes(),
            ]);

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($plan = $request->input('plan')) {
            $query->where('plan', $plan);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $tenants = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $tenant->plan,
                'status' => $tenant->status,
                'users_count' => $tenant->users_count,
                'employee_count' => Employee::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
                'created_at' => $tenant->created_at->format('M d, Y'),
            ]);

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'plan', 'status']),
        ]);
    }

    public function show(Tenant $tenant)
    {
        $users = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]);

        $employeeCount = Employee::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $tenant->plan,
                'status' => $tenant->status,
                'timezone' => $tenant->timezone,
                'workweek_start_day' => $tenant->workweek_start_day,
                'overtime_rule' => $tenant->overtime_rule,
                'trial_ends_at' => $tenant->trial_ends_at?->format('M d, Y'),
                'created_at' => $tenant->created_at->format('M d, Y'),
                'updated_at' => $tenant->updated_at->format('M d, Y'),
            ],
            'users' => $users,
            'employeeCount' => $employeeCount,
        ]);
    }

    public function suspend(Tenant $tenant)
    {
        $tenant->update(['status' => 'suspended']);

        return back()->with('success', "Tenant \"{$tenant->name}\" has been suspended.");
    }

    public function activate(Tenant $tenant)
    {
        $tenant->update(['status' => 'active']);

        return back()->with('success', "Tenant \"{$tenant->name}\" has been activated.");
    }
}
