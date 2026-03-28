<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobPageController extends Controller
{
    public function index(Request $request)
    {
        $query = Job::withCount('geofences')->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $jobs = $query->get()->map(fn (Job $job) => [
            'id' => $job->id,
            'name' => $job->name,
            'client_name' => $job->client_name,
            'status' => $job->status,
            'budget_hours' => $job->budget_hours,
            'hourly_rate' => $job->hourly_rate,
            'geofences_count' => $job->geofences_count,
        ]);

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'filters' => [
                'status' => $request->input('status'),
            ],
        ]);
    }
}
