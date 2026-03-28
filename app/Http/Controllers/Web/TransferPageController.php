<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
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

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
        ]);
    }
}
