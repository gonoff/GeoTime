<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\TenantManagementController;
use App\Http\Controllers\Auth\WebLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Controllers\Web\BillingPageController;
use App\Http\Controllers\Web\GeofencePageController;
use App\Http\Controllers\Web\JobPageController;
use App\Http\Controllers\Web\PtoPageController;
use App\Http\Controllers\Web\SettingsPageController;
use App\Http\Controllers\Web\TimeEntryPageController;
use App\Http\Controllers\Web\TimesheetPageController;
use App\Http\Controllers\Web\EmployeePageController;
use App\Http\Controllers\Web\TeamPageController;
use App\Http\Controllers\Web\TransferPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebLoginController::class, 'show'])->name('login');
    Route::post('/login', [WebLoginController::class, 'store']);
});

Route::post('/logout', [WebLoginController::class, 'destroy'])->name('logout')->middleware('auth');

// App
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/jobs', [JobPageController::class, 'index'])->name('jobs.index');
    Route::post('/jobs', [JobPageController::class, 'store'])->name('jobs.store');
    Route::put('/jobs/{job}', [JobPageController::class, 'update'])->name('jobs.update');
    Route::post('/jobs/{job}/complete', [JobPageController::class, 'complete'])->name('jobs.complete');
    Route::delete('/jobs/{job}', [JobPageController::class, 'destroy'])->name('jobs.destroy');
    Route::get('/geofences', [GeofencePageController::class, 'index'])->name('geofences.index');
    Route::post('/geofences', [GeofencePageController::class, 'store'])->name('geofences.store');
    Route::put('/geofences/{geofence}', [GeofencePageController::class, 'update'])->name('geofences.update');
    Route::post('/geofences/{geofence}/deactivate', [GeofencePageController::class, 'deactivate'])->name('geofences.deactivate');
    Route::post('/geofences/{geofence}/activate', [GeofencePageController::class, 'activate'])->name('geofences.activate');
    Route::delete('/geofences/{geofence}', [GeofencePageController::class, 'destroy'])->name('geofences.destroy');
    Route::get('/transfers', [TransferPageController::class, 'index'])->name('transfers.index');
    Route::post('/transfers', [TransferPageController::class, 'store'])->name('transfers.store');
    Route::post('/transfers/{transfer}/approve', [TransferPageController::class, 'approve'])->name('transfers.approve');
    Route::post('/transfers/{transfer}/reject', [TransferPageController::class, 'reject'])->name('transfers.reject');
    Route::get('/pto', [PtoPageController::class, 'index'])->name('pto.index');
    Route::post('/pto', [PtoPageController::class, 'store'])->name('pto.store');
    Route::get('/pto/balance/{employee}', [PtoPageController::class, 'balance'])->name('pto.balance');
    Route::post('/pto/{ptoRequest}/approve', [PtoPageController::class, 'approve'])->name('pto.approve');
    Route::post('/pto/{ptoRequest}/deny', [PtoPageController::class, 'deny'])->name('pto.deny');
    Route::get('/billing', [BillingPageController::class, 'index'])->name('billing.index');
    Route::get('/settings', [SettingsPageController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsPageController::class, 'update'])->name('settings.update');
    Route::get('/time-entries', [TimeEntryPageController::class, 'index'])->name('time-entries.index');
    Route::get('/timesheets', [TimesheetPageController::class, 'index'])->name('timesheets.index');
    Route::post('/timesheets/approve', [TimesheetPageController::class, 'approve'])->name('timesheets.approve');
    Route::post('/timesheets/reject', [TimesheetPageController::class, 'reject'])->name('timesheets.reject');
    Route::post('/timesheets/process-payroll', [TimesheetPageController::class, 'processPayroll'])->name('timesheets.process-payroll');
    Route::post('/timesheets/bulk-approve', [TimesheetPageController::class, 'bulkApprove'])->name('timesheets.bulk-approve');
    Route::post('/timesheets/bulk-reject', [TimesheetPageController::class, 'bulkReject'])->name('timesheets.bulk-reject');

    Route::get('/employees', [EmployeePageController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeePageController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}', [EmployeePageController::class, 'show'])->name('employees.show');
    Route::put('/employees/{employee}', [EmployeePageController::class, 'update'])->name('employees.update');
    Route::post('/employees/{employee}/terminate', [EmployeePageController::class, 'terminate'])->name('employees.terminate');
    Route::delete('/employees/{employee}', [EmployeePageController::class, 'destroy'])->name('employees.destroy');

    Route::get('/teams', [TeamPageController::class, 'index'])->name('teams.index');
    Route::post('/teams', [TeamPageController::class, 'store'])->name('teams.store');
    Route::put('/teams/{team}', [TeamPageController::class, 'update'])->name('teams.update');
    Route::post('/teams/{team}/archive', [TeamPageController::class, 'archive'])->name('teams.archive');
    Route::delete('/teams/{team}', [TeamPageController::class, 'destroy'])->name('teams.destroy');
});

// Platform Admin
Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminLoginController::class, 'show'])->name('admin.login');
        Route::post('/login', [AdminLoginController::class, 'store']);
    });

    Route::middleware(['auth', EnsurePlatformAdmin::class])->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('admin.dashboard');
        Route::get('/tenants', [TenantManagementController::class, 'index'])->name('admin.tenants.index');
        Route::get('/tenants/{tenant}', [TenantManagementController::class, 'show'])->name('admin.tenants.show');
        Route::post('/tenants/{tenant}/suspend', [TenantManagementController::class, 'suspend'])->name('admin.tenants.suspend');
        Route::post('/tenants/{tenant}/activate', [TenantManagementController::class, 'activate'])->name('admin.tenants.activate');
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('admin.logout');
    });
});
