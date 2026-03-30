<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePtoRequest;
use App\Models\Employee;
use App\Models\PtoBalance;
use App\Models\PtoRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PtoPageController extends Controller
{
    public function index()
    {
        $requests = PtoRequest::with('employee')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (PtoRequest $pto) => [
                'id' => $pto->id,
                'employee_name' => $pto->employee?->full_name,
                'type' => $pto->type,
                'start_date' => $pto->start_date?->format('Y-m-d'),
                'end_date' => $pto->end_date?->format('Y-m-d'),
                'hours' => $pto->hours,
                'status' => $pto->status,
                'notes' => $pto->notes,
            ]);

        $employees = Employee::orderBy('full_name')
            ->get(['id', 'full_name']);

        return Inertia::render('Pto/Index', [
            'requests' => $requests,
            'employees' => $employees,
        ]);
    }

    public function store(StorePtoRequest $request)
    {
        PtoRequest::create([
            ...$request->validated(),
            'status' => 'PENDING',
        ]);

        return back()->with('success', 'PTO request submitted.');
    }

    public function approve(Request $request, PtoRequest $ptoRequest)
    {
        if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
            abort(403);
        }

        $ptoRequest->update([
            'status' => 'APPROVED',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'PTO request approved.');
    }

    public function deny(Request $request, PtoRequest $ptoRequest)
    {
        if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
            abort(403);
        }

        $ptoRequest->update([
            'status' => 'DENIED',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'PTO request denied.');
    }

    public function balance(Employee $employee)
    {
        $balances = PtoBalance::where('employee_id', $employee->id)
            ->where('year', now()->year)
            ->get()
            ->mapWithKeys(fn ($b) => [$b->type => [
                'accrued' => $b->accrued_hours,
                'used' => $b->used_hours,
                'remaining' => $b->balance_hours,
            ]]);

        return response()->json($balances);
    }
}
