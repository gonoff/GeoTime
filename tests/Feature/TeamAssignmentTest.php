<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Team;
use App\Models\TeamAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamAssignmentTest extends TestCase
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

        app()->instance('current_tenant', $tenant);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $team = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Alpha',
            'status' => 'ACTIVE',
        ]);

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        return [$tenant, $user, $team, $employee];
    }

    public function test_team_assignment_can_be_created(): void
    {
        [$tenant, $user, $team, $employee] = $this->createSetup();

        $assignment = TeamAssignment::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'team_id' => $team->id,
            'assigned_at' => now(),
            'assigned_by' => $user->id,
        ]);

        $this->assertNotNull($assignment->id);
        $this->assertEquals($team->id, $assignment->team_id);
        $this->assertEquals($employee->id, $assignment->employee_id);
    }

    public function test_ending_assignment_sets_ended_at(): void
    {
        [$tenant, $user, $team, $employee] = $this->createSetup();

        $assignment = TeamAssignment::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'team_id' => $team->id,
            'assigned_at' => now()->subDays(30),
            'assigned_by' => $user->id,
        ]);

        $assignment->update(['ended_at' => now()]);

        $this->assertNotNull($assignment->ended_at);
    }

    public function test_employee_can_have_multiple_historical_assignments(): void
    {
        [$tenant, $user, $team, $employee] = $this->createSetup();

        $teamB = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Bravo',
            'status' => 'ACTIVE',
        ]);

        TeamAssignment::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'team_id' => $team->id,
            'assigned_at' => now()->subDays(60),
            'ended_at' => now()->subDays(30),
            'assigned_by' => $user->id,
        ]);

        TeamAssignment::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'team_id' => $teamB->id,
            'assigned_at' => now()->subDays(30),
            'assigned_by' => $user->id,
        ]);

        $this->assertEquals(2, $employee->teamAssignments()->count());
    }
}
