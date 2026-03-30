<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Team;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeePageController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with('currentTeam');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $employees = $query->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'full_name' => $employee->full_name,
                'email' => $employee->email,
                'role' => $employee->role,
                'hourly_rate' => $employee->hourly_rate,
                'status' => $employee->status,
                'hire_date' => $employee->hire_date?->format('M d, Y'),
                'team_name' => $employee->currentTeam?->name,
            ]);

        $teams = Team::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'teams' => $teams,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
            ],
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();
        if (isset($data['current_team_id'])) {
            // Will be set separately
        }
        $employee = Employee::create($data);

        return back()->with('success', 'Employee created successfully.');
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        return back()->with('success', 'Employee updated successfully.');
    }

    public function terminate(Request $request, Employee $employee)
    {
        if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager'])) {
            abort(403);
        }

        $employee->update([
            'status' => 'TERMINATED',
            'terminated_at' => now(),
        ]);

        return back()->with('success', 'Employee terminated.');
    }

    public function destroy(Request $request, Employee $employee)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        $employee->delete();

        return redirect('/employees')->with('success', 'Employee deleted.');
    }

    public function show(Employee $employee)
    {
        $employee->load('currentTeam');

        $recentTimeEntries = TimeEntry::where('employee_id', $employee->id)
            ->with('job')
            ->orderByDesc('clock_in')
            ->limit(10)
            ->get()
            ->map(fn (TimeEntry $entry) => [
                'id' => $entry->id,
                'clock_in' => $entry->clock_in?->format('M d, Y g:i A'),
                'clock_out' => $entry->clock_out?->format('M d, Y g:i A'),
                'total_hours' => $entry->total_hours,
                'status' => $entry->status,
                'job_name' => $entry->job?->name,
            ]);

        $teams = Team::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Employees/Show', [
            'teams' => $teams,
            'employee' => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'full_name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'role' => $employee->role,
                'hourly_rate' => $employee->hourly_rate,
                'status' => $employee->status,
                'hire_date' => $employee->hire_date?->format('M d, Y'),
                'hire_date_raw' => $employee->hire_date?->format('Y-m-d'),
                'date_of_birth' => $employee->date_of_birth?->format('M d, Y'),
                'date_of_birth_raw' => $employee->date_of_birth?->format('Y-m-d'),
                'address' => $employee->address,
                'team' => $employee->currentTeam ? [
                    'id' => $employee->currentTeam->id,
                    'name' => $employee->currentTeam->name,
                    'color_tag' => $employee->currentTeam->color_tag,
                ] : null,
            ],
            'recentTimeEntries' => $recentTimeEntries,
        ]);
    }
}
