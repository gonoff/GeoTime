<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
                'color_tag' => $team->color_tag,
                'status' => $team->status,
                'members_count' => $team->members_count,
                'lead_name' => $team->lead?->full_name,
            ]);

        return Inertia::render('Teams/Index', [
            'teams' => $teams,
        ]);
    }
}
