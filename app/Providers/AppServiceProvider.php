<?php

namespace App\Providers;

use App\Events\EmployeeCountChanged;
use App\Listeners\SyncEmployeeCount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);

        Event::listen(EmployeeCountChanged::class, SyncEmployeeCount::class);

        User::created(function (User $user) {
            if ($user->tenant) {
                EmployeeCountChanged::dispatch($user->tenant);
            }
        });

        User::deleted(function (User $user) {
            if ($user->tenant) {
                EmployeeCountChanged::dispatch($user->tenant);
            }
        });
    }
}
