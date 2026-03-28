<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_audit_logs(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $tenant);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'employee',
            'entity_id' => $user->id,
            'action' => 'CREATE',
            'changed_by' => $user->id,
            'new_value' => ['name' => 'Test'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_non_admin_cannot_list_audit_logs(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Employee',
            'email' => 'emp@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'employee',
        ]);

        app()->instance('current_tenant', $tenant);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(403);
    }
}
