<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Employee;
use App\Models\Team;
use App\Models\Transfer;
use App\Services\TransferService;
use Inertia\Inertia;

class TransferPageController extends Controller
{
    public function index()
    {
        $transfers = Transfer::with(['employee', 'fromTeam', 'toTeam'])
            ->orderByDesc('effective_date')
            ->get()
            ->map(fn (Transfer $transfer) => [
                'id' => $transfer->id,
                'employee_name' => $transfer->employee?->full_name,
                'from_team' => $transfer->fromTeam?->name,
                'to_team' => $transfer->toTeam?->name,
                'reason_category' => $transfer->reason_category,
                'transfer_type' => $transfer->transfer_type,
                'status' => $transfer->status,
                'effective_date' => $transfer->effective_date?->format('Y-m-d'),
            ]);

        $employees = Employee::with('currentTeam')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'current_team_id' => $e->current_team_id,
                'current_team_name' => $e->currentTeam?->name,
            ]);

        $teams = Team::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
            'employees' => $employees,
            'teams' => $teams,
            'reason_categories' => Transfer::REASON_CATEGORIES,
            'reason_codes' => Transfer::REASON_CODES,
        ]);
    }

    public function store(StoreTransferRequest $request)
    {
        Transfer::create([
            ...$request->validated(),
            'initiated_by' => auth()->id(),
            'status' => 'PENDING',
        ]);

        return back()->with('success', 'Transfer request created successfully.');
    }

    public function approve(Transfer $transfer, TransferService $transferService)
    {
        $user = request()->user();
        if (!$user->isAdmin() && !$user->isManager()) {
            abort(403);
        }

        $transfer->update([
            'approved_by' => auth()->id(),
            'status' => 'APPROVED',
        ]);

        $transferService->executeTransfer($transfer);

        return back()->with('success', 'Transfer approved and executed.');
    }

    public function reject(Transfer $transfer)
    {
        $user = request()->user();
        if (!$user->isAdmin() && !$user->isManager()) {
            abort(403);
        }

        $transfer->update(['status' => 'REJECTED']);

        return back()->with('success', 'Transfer rejected.');
    }
}
