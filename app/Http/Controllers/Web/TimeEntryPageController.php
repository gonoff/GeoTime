<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimeEntryPageController extends Controller
{
    public function index(Request $request)
    {
        $query = TimeEntry::with(['employee', 'job']);

        // Search by employee name
        if ($search = $request->input('search')) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Date range
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('clock_in', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('clock_in', '<=', $dateTo);
        }

        $entries = $query->orderByDesc('clock_in')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (TimeEntry $entry) => [
                'id' => $entry->id,
                'employee_name' => $entry->employee?->full_name ?? 'Unknown',
                'job_name' => $entry->job?->name ?? '-',
                'clock_in' => $entry->clock_in?->format('M j, g:i A'),
                'clock_out' => $entry->clock_out?->format('M j, g:i A'),
                'total_hours' => $entry->total_hours,
                'clock_method' => $entry->clock_method,
                'status' => $entry->status,
                'verification_status' => $entry->verification_status,
            ]);

        return Inertia::render('TimeEntries/Index', [
            'entries' => $entries,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
        ]);
    }
}
