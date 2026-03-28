<?php

namespace App\Listeners;

use App\Events\EmployeeCountChanged;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncEmployeeCount implements ShouldQueue
{
    public function handle(EmployeeCountChanged $event): void
    {
        $tenant = $event->tenant;

        if (! $tenant->subscribed('default')) {
            return;
        }

        $count = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();

        $tenant->subscription('default')->updateQuantity(max(1, $count));
    }
}
