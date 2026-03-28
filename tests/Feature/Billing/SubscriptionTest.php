<?php

namespace Tests\Feature\Billing;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        return [$tenant, $user];
    }

    public function test_billing_status_endpoint_returns_tenant_billing_info(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/billing/status');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'plan' => 'business',
                    'status' => 'trial',
                    'on_trial' => true,
                    'has_subscription' => false,
                ],
            ]);
    }

    public function test_only_admins_can_access_billing(): void
    {
        [$tenant, $admin] = $this->createAuthenticatedAdmin();

        $employee = User::withoutGlobalScopes()->create([
            'name' => 'Employee',
            'email' => 'emp@test.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/billing/status');

        $response->assertStatus(403);
    }
}
