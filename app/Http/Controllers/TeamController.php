<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $teams = Team::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->withCount('members')
            ->with('lead')
            ->orderBy('name')
            ->paginate($request->query('per_page', 25));

        return TeamResource::collection($teams);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = Team::create($request->validated());

        return (new TeamResource($team))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Team $team): TeamResource
    {
        $team->loadCount('members')->load('lead');

        return new TeamResource($team);
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $team->update($request->validated());

        return new TeamResource($team->fresh());
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $team->update(['status' => 'ARCHIVED']);

        return response()->json(['message' => 'Team archived']);
    }
}
