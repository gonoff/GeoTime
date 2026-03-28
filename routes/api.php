<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\GeofenceController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\BreakEntryController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\PtoController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/stripe/webhook', [\App\Http\Controllers\Billing\WebhookController::class, 'handleWebhook']);

Route::prefix('v1')->group(function () {
    // Public auth routes
    Route::post('/auth/register', RegisterController::class);
    Route::post('/auth/login', [LoginController::class, 'login']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [LoginController::class, 'me']);
        Route::post('/auth/logout', [LoginController::class, 'logout']);

        // Employees
        Route::apiResource('employees', EmployeeController::class);

        // Teams
        Route::apiResource('teams', TeamController::class);

        // Transfers
        Route::apiResource('transfers', TransferController::class)->only(['index', 'store', 'show']);
        Route::post('transfers/{transfer}/approve', [TransferController::class, 'approve']);
        Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject']);

        // Jobs / Job Sites
        Route::apiResource('jobs', JobController::class);

        // Geofences
        Route::apiResource('geofences', GeofenceController::class);

        // Time Entries
        Route::get('time-entries', [TimeEntryController::class, 'index']);
        Route::get('time-entries/{timeEntry}', [TimeEntryController::class, 'show']);
        Route::post('time-entries/clock-in', [TimeEntryController::class, 'clockIn']);
        Route::post('time-entries/{timeEntry}/clock-out', [TimeEntryController::class, 'clockOut']);
        Route::put('time-entries/{timeEntry}', [TimeEntryController::class, 'update']);
        Route::post('time-entries/{timeEntry}/verify', [TimeEntryController::class, 'verify']);

        // Breaks
        Route::post('breaks', [BreakEntryController::class, 'store']);
        Route::post('breaks/{breakEntry}/end', [BreakEntryController::class, 'end']);

        // Timesheets
        Route::prefix('timesheets')->group(function () {
            Route::post('/submit', [TimesheetController::class, 'submit']);
            Route::post('/review', [TimesheetController::class, 'review']);
            Route::post('/process-payroll', [TimesheetController::class, 'processPayroll']);
            Route::get('/summary', [TimesheetController::class, 'summary']);
        });

        // PTO
        Route::get('pto', [PtoController::class, 'index']);
        Route::post('pto', [PtoController::class, 'store']);
        Route::post('pto/{ptoRequest}/review', [PtoController::class, 'review']);
        Route::get('pto/balance/{employeeId}', [PtoController::class, 'balance']);

        // Audit Logs
        Route::get('audit-logs', function (\Illuminate\Http\Request $request) {
            if (! $request->user()->isAdmin()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $logs = \App\Models\AuditLog::query()
                ->when($request->query('entity_type'), fn ($q, $type) => $q->where('entity_type', $type))
                ->when($request->query('entity_id'), fn ($q, $id) => $q->where('entity_id', $id))
                ->orderByDesc('created_at')
                ->paginate($request->query('per_page', 50));

            return response()->json([
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
            ]);
        });

        // Mobile Sync
        Route::get('sync', [SyncController::class, 'pull']);
        Route::post('sync', [SyncController::class, 'push']);

        // Billing
        Route::prefix('billing')->group(function () {
            Route::get('/status', [\App\Http\Controllers\Billing\SubscriptionController::class, 'status']);
            Route::post('/checkout', [\App\Http\Controllers\Billing\SubscriptionController::class, 'createCheckoutSession']);
        });
    });
});
