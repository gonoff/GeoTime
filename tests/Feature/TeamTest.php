<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
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

    public function test_admin_can_create_team(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/teams', [
                'name' => 'Alpha Team',
                'description' => 'The alpha team',
                'color_tag' => '#FF5733',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description', 'color_tag', 'status', 'created_at'],
            ]);

        $this->assertDatabaseHas('teams', [
            'name' => 'Alpha Team',
            'tenant_id' => $tenant->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_admin_can_list_teams(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Team A',
            'status' => 'ACTIVE',
        ]);

        Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Team B',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/teams');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_assign_team_lead(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Lead',
            'last_name' => 'Person',
            'email' => 'lead@test.com',
            'role' => 'TEAM_LEAD',
            'hourly_rate' => 25.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $team = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Team A',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/teams/{$team->id}", [
                'lead_employee_id' => $employee->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'lead_employee_id' => $employee->id,
        ]);
    }

    public function test_admin_can_archive_team(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $team = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Old Team',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/teams/{$team->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'status' => 'ARCHIVED',
        ]);
    }

    public function test_teams_are_tenant_scoped(): void
    {
        [$tenantA, $userA] = $this->createAuthenticatedAdmin();

        Team::create([
            'tenant_id' => $tenantA->id,
            'name' => 'A Team',
            'status' => 'ACTIVE',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Other Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        Team::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'B Team',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/teams');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
