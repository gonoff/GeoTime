<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGeofenceRequest;
use App\Http\Requests\UpdateGeofenceRequest;
use App\Http\Resources\GeofenceResource;
use App\Models\Geofence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GeofenceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $geofences = Geofence::query()
            ->when($request->query('job_id'), fn ($q, $jobId) => $q->where('job_id', $jobId))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->with('job')
            ->orderBy('name')
            ->paginate($request->query('per_page', 25));

        return GeofenceResource::collection($geofences);
    }

    public function store(StoreGeofenceRequest $request): JsonResponse
    {
        $geofence = Geofence::create($request->validated());

        return (new GeofenceResource($geofence))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Geofence $geofence): GeofenceResource
    {
        $geofence->load('job');

        return new GeofenceResource($geofence);
    }

    public function update(UpdateGeofenceRequest $request, Geofence $geofence): GeofenceResource
    {
        $geofence->update($request->validated());

        return new GeofenceResource($geofence->fresh());
    }

    public function destroy(Request $request, Geofence $geofence): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $geofence->update(['is_active' => false]);

        return response()->json(['message' => 'Geofence deactivated']);
    }
}
