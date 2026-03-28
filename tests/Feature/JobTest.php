<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $tenant);

        return [$tenant, $user];
    }

    public function test_admin_can_create_job(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/jobs', [
                'name' => 'Office Building Renovation',
                'client_name' => 'Acme Corp',
                'address' => '456 Industrial Ave, Springfield, IL',
                'status' => 'ACTIVE',
                'budget_hours' => 500.00,
                'hourly_rate' => 45.00,
                'start_date' => '2026-04-01',
                'end_date' => '2026-09-30',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'client_name', 'address', 'status',
                    'budget_hours', 'hourly_rate', 'start_date', 'end_date',
                ],
            ]);

        $this->assertDatabaseHas('job_sites', [
            'name' => 'Office Building Renovation',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_admin_can_list_jobs(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        Job::create([
            'tenant_id' => $tenant->id,
            'name' => 'Job A',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-01-01',
        ]);

        Job::create([
            'tenant_id' => $tenant->id,
            'name' => 'Job B',
            'status' => 'COMPLETED',
            'budget_hours' => 200,
            'hourly_rate' => 35,
            'start_date' => '2026-02-01',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/jobs');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_update_job(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $job = Job::create([
            'tenant_id' => $tenant->id,
            'name' => 'Old Name',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/jobs/{$job->id}", [
                'name' => 'New Name',
                'status' => 'ON_HOLD',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('job_sites', [
            'id' => $job->id,
            'name' => 'New Name',
            'status' => 'ON_HOLD',
        ]);
    }

    public function test_admin_can_show_single_job(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $job = Job::create([
            'tenant_id' => $tenant->id,
            'name' => 'Detail Job',
            'client_name' => 'Client X',
            'status' => 'ACTIVE',
            'budget_hours' => 150,
            'hourly_rate' => 40,
            'start_date' => '2026-03-01',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/jobs/{$job->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Detail Job',
                    'client_name' => 'Client X',
                ],
            ]);
    }

    public function test_jobs_are_tenant_scoped(): void
    {
        [$tenantA, $userA] = $this->createAuthenticatedAdmin();

        Job::create([
            'tenant_id' => $tenantA->id,
            'name' => 'A Job',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-01-01',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Other Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        Job::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'B Job',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/jobs');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
