<?php

use App\Http\Controllers\Auth\WebLoginController;
use App\Http\Controllers\DashboardController;
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
});
