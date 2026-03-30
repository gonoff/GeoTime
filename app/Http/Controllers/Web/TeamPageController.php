<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Employee;
use App\Models\Team;
use Inertia\Inertia;

class TeamPageController extends Controller
{
    public function index()
    {
        $teams = Team::withCount('members')
            ->with('lead')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'color_tag' => $team->color_tag,
                'status' => $team->status,
                'members_count' => $team->members_count,
                'lead_name' => $team->lead?->full_name,
                'lead_employee_id' => $team->lead_employee_id,
            ]);

        $employees = Employee::orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->first_name . ' ' . $e->last_name,
            ]);

        return Inertia::render('Teams/Index', [
            'teams' => $teams,
            'employees' => $employees,
        ]);
    }

    public function store(StoreTeamRequest $request)
    {
        Team::create($request->validated());

        return back()->with('success', 'Team created successfully.');
    }

    public function update(UpdateTeamRequest $request, Team $team)
    {
        $team->update($request->validated());

        return back()->with('success', 'Team updated successfully.');
    }

    public function archive(Team $team)
    {
        $user = request()->user();
        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
            abort(403);
        }

        $team->update(['status' => 'ARCHIVED']);

        return back()->with('success', 'Team archived successfully.');
    }

    public function destroy(Team $team)
    {
        if (!request()->user()->isAdmin()) {
            abort(403);
        }

        $team->delete();

        return back()->with('success', 'Team deleted successfully.');
    }
}
