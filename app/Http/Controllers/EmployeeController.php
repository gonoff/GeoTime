<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $employees = Employee::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('team_id'), fn ($q, $teamId) => $q->where('current_team_id', $teamId))
            ->orderBy('last_name')
            ->paginate($request->query('per_page', 25));

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee->update($request->validated());

        return new EmployeeResource($employee->fresh());
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee->update(['status' => 'TERMINATED']);

        return response()->json(['message' => 'Employee terminated']);
    }
}
