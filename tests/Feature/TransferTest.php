<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Team;
use App\Models\TeamAssignment;
use App\Models\Tenant;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private function createSetup(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $adminUser = User::withoutGlobalScopes()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $tenant);

        $teamA = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Alpha',
            'status' => 'ACTIVE',
        ]);

        $teamB = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Bravo',
            'status' => 'ACTIVE',
        ]);

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'current_team_id' => $teamA->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        // Create initial team assignment
        TeamAssignment::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'team_id' => $teamA->id,
            'assigned_at' => now()->subDays(30),
            'assigned_by' => $adminUser->id,
        ]);

        return [$tenant, $adminUser, $teamA, $teamB, $employee];
    }

    public function test_admin_can_create_permanent_transfer(): void
    {
        [$tenant, $admin, $teamA, $teamB, $employee] = $this->createSetup();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/transfers', [
                'employee_id' => $employee->id,
                'from_team_id' => $teamA->id,
                'to_team_id' => $teamB->id,
                'reason_category' => 'OPERATIONAL',
                'reason_code' => 'WORKLOAD_BALANCE',
                'transfer_type' => 'PERMANENT',
                'effective_date' => now()->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'employee_id', 'from_team_id', 'to_team_id',
                    'reason_category', 'reason_code', 'transfer_type',
                    'status', 'effective_date',
                ],
            ]);

        // Employee's current_team_id should be updated
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'current_team_id' => $teamB->id,
        ]);
    }

    public function test_transfer_creates_team_assignment_history(): void
    {
        [$tenant, $admin, $teamA, $teamB, $employee] = $this->createSetup();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/transfers', [
                'employee_id' => $employee->id,
                'from_team_id' => $teamA->id,
                'to_team_id' => $teamB->id,
                'reason_category' => 'OPERATIONAL',
                'reason_code' => 'SKILL_MATCH',
                'transfer_type' => 'PERMANENT',
                'effective_date' => now()->toDateString(),
            ]);

        // Old assignment should be ended
        $oldAssignment = TeamAssignment::where('employee_id', $employee->id)
            ->where('team_id', $teamA->id)
            ->first();
        $this->assertNotNull($oldAssignment->ended_at);

        // New assignment should exist
        $newAssignment = TeamAssignment::where('employee_id', $employee->id)
            ->where('team_id', $teamB->id)
            ->whereNull('ended_at')
            ->first();
        $this->assertNotNull($newAssignment);
    }

    public function test_temporary_transfer_includes_return_date(): void
    {
        [$tenant, $admin, $teamA, $teamB, $employee] = $this->createSetup();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/transfers', [
                'employee_id' => $employee->id,
                'from_team_id' => $teamA->id,
                'to_team_id' => $teamB->id,
                'reason_category' => 'OPERATIONAL',
                'reason_code' => 'PROJECT_NEED',
                'transfer_type' => 'TEMPORARY',
                'effective_date' => now()->toDateString(),
                'expected_return_date' => now()->addDays(14)->toDateString(),
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transfers', [
            'transfer_type' => 'TEMPORARY',
            'employee_id' => $employee->id,
        ]);
    }

    public function test_other_reason_requires_notes(): void
    {
        [$tenant, $admin, $teamA, $teamB, $employee] = $this->createSetup();

        // Without notes should fail
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/transfers', [
                'employee_id' => $employee->id,
                'from_team_id' => $teamA->id,
                'to_team_id' => $teamB->id,
                'reason_category' => 'ADMINISTRATIVE',
                'reason_code' => 'OTHER',
                'transfer_type' => 'PERMANENT',
                'effective_date' => now()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);
    }

    public function test_team_lead_transfer_requires_approval(): void
    {
        [$tenant, $admin, $teamA, $teamB, $employee] = $this->createSetup();

        $teamLeadUser = User::withoutGlobalScopes()->create([
            'name' => 'Lead',
            'email' => 'lead@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'team_lead',
        ]);

        $response = $this->actingAs($teamLeadUser, 'sanctum')
            ->postJson('/api/v1/transfers', [
                'employee_id' => $employee->id,
                'from_team_id' => $teamA->id,
                'to_team_id' => $teamB->id,
                'reason_category' => 'OPERATIONAL',
                'reason_code' => 'WORKLOAD_BALANCE',
                'transfer_type' => 'PERMANENT',
                'effective_date' => now()->toDateString(),
            ]);

        $response->assertStatus(201);
        // Transfer should be pending approval
        $this->assertDatabaseHas('transfers', [
            'employee_id' => $employee->id,
            'status' => 'PENDING',
        ]);

        // Employee should NOT have moved yet
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'current_team_id' => $teamA->id,
        ]);
    }

    public function test_admin_can_approve_pending_transfer(): void
    {
        [$tenant, $admin, $teamA, $teamB, $employee] = $this->createSetup();

        $transfer = Transfer::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'from_team_id' => $teamA->id,
            'to_team_id' => $teamB->id,
            'reason_category' => 'OPERATIONAL',
            'reason_code' => 'WORKLOAD_BALANCE',
            'transfer_type' => 'PERMANENT',
            'effective_date' => now()->toDateString(),
            'initiated_by' => $admin->id,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/transfers/{$transfer->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('transfers', [
            'id' => $transfer->id,
            'status' => 'COMPLETED',
        ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'current_team_id' => $teamB->id,
        ]);
    }

    public function test_admin_can_reject_transfer(): void
    {
        [$tenant, $admin, $teamA, $teamB, $employee] = $this->createSetup();

        $transfer = Transfer::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'from_team_id' => $teamA->id,
            'to_team_id' => $teamB->id,
            'reason_category' => 'OPERATIONAL',
            'reason_code' => 'WORKLOAD_BALANCE',
            'transfer_type' => 'PERMANENT',
            'effective_date' => now()->toDateString(),
            'initiated_by' => $admin->id,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/transfers/{$transfer->id}/reject");

        $response->assertStatus(200);
        $this->assertDatabaseHas('transfers', [
            'id' => $transfer->id,
            'status' => 'REJECTED',
        ]);
    }

    public function test_admin_can_list_transfers(): void
    {
        [$tenant, $admin, $teamA, $teamB, $employee] = $this->createSetup();

        Transfer::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'from_team_id' => $teamA->id,
            'to_team_id' => $teamB->id,
            'reason_category' => 'OPERATIONAL',
            'reason_code' => 'WORKLOAD_BALANCE',
            'transfer_type' => 'PERMANENT',
            'effective_date' => now()->toDateString(),
            'initiated_by' => $admin->id,
            'status' => 'COMPLETED',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/transfers');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
