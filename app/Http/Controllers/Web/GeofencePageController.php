<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGeofenceRequest;
use App\Http\Requests\UpdateGeofenceRequest;
use App\Models\Geofence;
use App\Models\Job;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GeofencePageController extends Controller
{
    public function index()
    {
        $geofences = Geofence::with('job')
            ->orderBy('name')
            ->get()
            ->map(fn (Geofence $geofence) => [
                'id' => $geofence->id,
                'name' => $geofence->name,
                'job_id' => $geofence->job_id,
                'job_name' => $geofence->job?->name,
                'latitude' => $geofence->latitude,
                'longitude' => $geofence->longitude,
                'radius_meters' => $geofence->radius_meters,
                'is_active' => $geofence->is_active,
            ]);

        $jobs = Job::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Geofences/Index', [
            'geofences' => $geofences,
            'jobs' => $jobs,
        ]);
    }

    public function store(StoreGeofenceRequest $request)
    {
        Geofence::create($request->validated());

        return back()->with('success', 'Geofence created successfully.');
    }

    public function update(UpdateGeofenceRequest $request, Geofence $geofence)
    {
        $geofence->update($request->validated());

        return back()->with('success', 'Geofence updated successfully.');
    }

    public function deactivate(Request $request, Geofence $geofence)
    {
        if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
            abort(403);
        }

        $geofence->update(['is_active' => false]);

        return back()->with('success', 'Geofence deactivated.');
    }

    public function activate(Request $request, Geofence $geofence)
    {
        if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
            abort(403);
        }

        $geofence->update(['is_active' => true]);

        return back()->with('success', 'Geofence activated.');
    }

    public function destroy(Request $request, Geofence $geofence)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        $geofence->delete();

        return back()->with('success', 'Geofence deleted.');
    }
}
