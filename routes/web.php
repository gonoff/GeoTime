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
    Route::get('/geofences', [GeofencePageController::class, 'index'])->name('geofences.index');
    Route::get('/transfers', [TransferPageController::class, 'index'])->name('transfers.index');
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

    Route::get('/employees', [EmployeePageController::class, 'index'])->name('employees.index');
    Route::get('/employees/{employee}', [EmployeePageController::class, 'show'])->name('employees.show');

    Route::get('/teams', [TeamPageController::class, 'index'])->name('teams.index');
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
