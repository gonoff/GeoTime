<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
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
            'address' => $job->address,
            'status' => $job->status,
            'start_date' => $job->start_date?->format('Y-m-d'),
            'end_date' => $job->end_date?->format('Y-m-d'),
            'geofences_count' => $job->geofences_count,
        ]);

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'filters' => [
                'status' => $request->input('status'),
            ],
        ]);
    }

    public function store(StoreJobRequest $request)
    {
        Job::create($request->validated());

        return back()->with('success', 'Job site created successfully.');
    }

    public function update(UpdateJobRequest $request, Job $job)
    {
        $job->update($request->validated());

        return back()->with('success', 'Job site updated successfully.');
    }

    public function complete(Request $request, Job $job)
    {
        if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
            abort(403);
        }

        $job->update(['status' => 'COMPLETED']);

        return back()->with('success', 'Job marked as completed.');
    }

    public function destroy(Request $request, Job $job)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        $job->delete();

        return back()->with('success', 'Job site deleted.');
    }
}
