<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
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
                'job_name' => $geofence->job?->name,
                'latitude' => $geofence->latitude,
                'longitude' => $geofence->longitude,
                'radius_meters' => $geofence->radius_meters,
                'is_active' => $geofence->is_active,
            ]);

        return Inertia::render('Geofences/Index', [
            'geofences' => $geofences,
        ]);
    }
}
