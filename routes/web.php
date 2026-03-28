<?php

use App\Http\Controllers\Auth\WebLoginController;
use App\Http\Controllers\DashboardController;
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
    Route::get('/billing', [BillingPageController::class, 'index'])->name('billing.index');
    Route::get('/settings', [SettingsPageController::class, 'index'])->name('settings.index');
    Route::get('/time-entries', [TimeEntryPageController::class, 'index'])->name('time-entries.index');
    Route::get('/timesheets', [TimesheetPageController::class, 'index'])->name('timesheets.index');

    Route::get('/employees', [EmployeePageController::class, 'index'])->name('employees.index');
    Route::get('/employees/{employee}', [EmployeePageController::class, 'show'])->name('employees.show');

    Route::get('/teams', [TeamPageController::class, 'index'])->name('teams.index');
});
