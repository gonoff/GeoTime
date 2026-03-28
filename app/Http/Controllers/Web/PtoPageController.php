<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PtoRequest;
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
                'status' => $pto->status,
                'notes' => $pto->notes,
            ]);

        return Inertia::render('Pto/Index', [
            'requests' => $requests,
        ]);
    }
}
