<?php

namespace Tests\Unit;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Acme Construction',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->assertNotNull($tenant->id);
        $this->assertEquals('Acme Construction', $tenant->name);
        $this->assertEquals('trial', $tenant->status);
    }

    public function test_tenant_has_default_overtime_rule(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/Chicago',
            'workweek_start_day' => 0,
            'plan' => 'starter',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->assertEquals(40, $tenant->overtime_rule['weekly_threshold']);
        $this->assertEquals(1.5, $tenant->overtime_rule['multiplier']);
    }

    public function test_tenant_trial_check(): void
    {
        $tenant = Tenant::create([
            'name' => 'Trial Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->assertTrue($tenant->onTrial());

        $tenant->trial_ends_at = now()->subDay();
        $this->assertFalse($tenant->onTrial());
    }
}
