<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Schema;

class JobController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Job::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name');

        if (Schema::hasTable('geofences')) {
            $query->withCount('geofences');
        }

        $jobs = $query->paginate($request->query('per_page', 25));

        return JobResource::collection($jobs);
    }

    public function store(StoreJobRequest $request): JsonResponse
    {
        $job = Job::create($request->validated());

        return (new JobResource($job))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Job $job): JobResource
    {
        if (Schema::hasTable('geofences')) {
            $job->loadCount('geofences');
        }

        return new JobResource($job);
    }

    public function update(UpdateJobRequest $request, Job $job): JobResource
    {
        $job->update($request->validated());

        return new JobResource($job->fresh());
    }

    public function destroy(Request $request, Job $job): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $job->update(['status' => 'COMPLETED']);

        return response()->json(['message' => 'Job marked as completed']);
    }
}
