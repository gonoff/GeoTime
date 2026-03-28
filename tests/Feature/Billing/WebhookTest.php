<?php

namespace Tests\Feature\Billing;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantWithStripeId(string $stripeId): Tenant
    {
        return Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'stripe_id' => $stripeId,
        ]);
    }

    public function test_subscription_created_activates_tenant(): void
    {
        $tenant = $this->createTenantWithStripeId('cus_test_123');
        $this->assertEquals('trial', $tenant->status);

        $tenant->update(['status' => 'active', 'plan' => 'business']);
        $tenant->refresh();

        $this->assertEquals('active', $tenant->status);
        $this->assertEquals('business', $tenant->plan);
    }

    public function test_subscription_deleted_cancels_tenant(): void
    {
        $tenant = $this->createTenantWithStripeId('cus_test_456');
        $tenant->update(['status' => 'active']);

        $tenant->update(['status' => 'cancelled']);
        $tenant->refresh();

        $this->assertEquals('cancelled', $tenant->status);
    }

    public function test_subscription_past_due_updates_tenant(): void
    {
        $tenant = $this->createTenantWithStripeId('cus_test_789');
        $tenant->update(['status' => 'active']);

        $tenant->update(['status' => 'past_due']);
        $tenant->refresh();

        $this->assertEquals('past_due', $tenant->status);
    }
}
