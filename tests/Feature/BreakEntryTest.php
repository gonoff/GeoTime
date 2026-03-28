<?php

namespace Tests\Feature;

use App\Models\BreakEntry;
use App\Models\Employee;
use App\Models\Job;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreakEntryTest extends TestCase
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

        $team = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Alpha',
            'status' => 'ACTIVE',
        ]);

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'current_team_id' => $team->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 25.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $job = Job::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Site',
            'status' => 'ACTIVE',
            'budget_hours' => 500,
            'hourly_rate' => 45,
            'start_date' => '2026-01-01',
        ]);

        $timeEntry = TimeEntry::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'job_id' => $job->id,
            'team_id' => $team->id,
            'clock_in' => now()->subHours(4),
            'clock_in_lat' => 39.78,
            'clock_in_lng' => -89.65,
            'clock_method' => 'MANUAL',
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
        ]);

        return [$tenant, $adminUser, $employee, $timeEntry];
    }

    public function test_can_start_break(): void
    {
        [$tenant, $admin, $employee, $timeEntry] = $this->createSetup();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/breaks', [
                'time_entry_id' => $timeEntry->id,
                'type' => 'PAID_REST',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'time_entry_id', 'type', 'start_time'],
            ]);

        $this->assertDatabaseHas('break_entries', [
            'time_entry_id' => $timeEntry->id,
            'type' => 'PAID_REST',
        ]);
    }

    public function test_can_end_break(): void
    {
        [$tenant, $admin, $employee, $timeEntry] = $this->createSetup();

        $breakEntry = BreakEntry::create([
            'tenant_id' => $tenant->id,
            'time_entry_id' => $timeEntry->id,
            'type' => 'UNPAID_MEAL',
            'start_time' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/breaks/{$breakEntry->id}/end");

        $response->assertStatus(200);
        $breakEntry->refresh();
        $this->assertNotNull($breakEntry->end_time);
        $this->assertNotNull($breakEntry->duration_minutes);
        $this->assertGreaterThanOrEqual(30, $breakEntry->duration_minutes);
    }

    public function test_break_interruption_marks_interrupted(): void
    {
        [$tenant, $admin, $employee, $timeEntry] = $this->createSetup();

        $breakEntry = BreakEntry::create([
            'tenant_id' => $tenant->id,
            'time_entry_id' => $timeEntry->id,
            'type' => 'UNPAID_MEAL',
            'start_time' => now()->subMinutes(15),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/breaks/{$breakEntry->id}/end", [
                'was_interrupted' => true,
            ]);

        $response->assertStatus(200);
        $breakEntry->refresh();
        $this->assertTrue($breakEntry->was_interrupted);
    }

    public function test_cannot_start_break_on_completed_time_entry(): void
    {
        [$tenant, $admin, $employee, $timeEntry] = $this->createSetup();

        $timeEntry->update([
            'clock_out' => now(),
            'total_hours' => 4,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/breaks', [
                'time_entry_id' => $timeEntry->id,
                'type' => 'PAID_REST',
            ]);

        $response->assertStatus(422);
    }
}
