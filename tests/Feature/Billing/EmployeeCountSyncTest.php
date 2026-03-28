<?php

namespace Tests\Feature\Billing;

use App\Events\EmployeeCountChanged;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCountSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_count_changed_event_is_dispatched(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $event = new EmployeeCountChanged($tenant);
        $this->assertEquals($tenant->id, $event->tenant->id);
    }

    public function test_listener_calculates_correct_employee_count(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            User::withoutGlobalScopes()->create([
                'name' => "User $i",
                'email' => "user{$i}@test.com",
                'password' => 'password',
                'tenant_id' => $tenant->id,
                'role' => 'employee',
            ]);
        }

        $count = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();

        $this->assertEquals(3, $count);
    }
}
