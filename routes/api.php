<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\TeamController;
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

        // Billing
        Route::prefix('billing')->group(function () {
            Route::get('/status', [\App\Http\Controllers\Billing\SubscriptionController::class, 'status']);
            Route::post('/checkout', [\App\Http\Controllers\Billing\SubscriptionController::class, 'createCheckoutSession']);
        });
    });
});
