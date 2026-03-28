<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_are_scoped_to_tenant(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Company A',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Company B',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        // Use withoutGlobalScopes to bypass tenant scoping during setup
        User::withoutGlobalScopes()->create([
            'name' => 'Alice',
            'email' => 'alice@a.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenantA->id,
            'role' => 'admin',
        ]);

        User::withoutGlobalScopes()->create([
            'name' => 'Bob',
            'email' => 'bob@b.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenantB->id,
            'role' => 'admin',
        ]);

        // Set current tenant to A
        app()->instance('current_tenant', $tenantA);

        // Should only see Alice
        $users = User::all();
        $this->assertCount(1, $users);
        $this->assertEquals('Alice', $users->first()->name);
    }

    public function test_user_cannot_access_other_tenant_data(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Company A',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Company B',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $bobUser = User::withoutGlobalScopes()->create([
            'name' => 'Bob',
            'email' => 'bob@b.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenantB->id,
            'role' => 'admin',
        ]);

        // Set current tenant to A
        app()->instance('current_tenant', $tenantA);

        // Should not find Bob
        $found = User::find($bobUser->id);
        $this->assertNull($found);
    }
}
