<?php

namespace Tests\Feature\Billing;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionGatingTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantWithUser(string $status, ?string $trialEnds = null): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => $status,
            'trial_ends_at' => $trialEnds ? now()->parse($trialEnds) : null,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        return [$tenant, $user];
    }

    public function test_active_tenant_can_access_protected_routes(): void
    {
        [$tenant, $user] = $this->createTenantWithUser('active');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
    }

    public function test_trial_tenant_can_access_protected_routes(): void
    {
        [$tenant, $user] = $this->createTenantWithUser('trial', '+14 days');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
    }

    public function test_expired_trial_allows_reads_but_blocks_writes(): void
    {
        [$tenant, $user] = $this->createTenantWithUser('trial', '-1 day');

        // GET (read) should work
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');
        $response->assertStatus(200);

        // POST (write) should return 402
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/billing/checkout', ['plan' => 'starter']);
        $response->assertStatus(402)
            ->assertJson(['read_only' => true]);
    }

    public function test_suspended_tenant_cannot_access_anything(): void
    {
        [$tenant, $user] = $this->createTenantWithUser('suspended');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Account suspended. Please contact support.']);
    }
}
