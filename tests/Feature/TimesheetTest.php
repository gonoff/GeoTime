<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Job;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetTest extends TestCase
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

        return [$tenant, $adminUser, $team, $employee, $job];
    }

    private function createWeeklyEntries(string $tenantId, string $employeeId, string $jobId, string $teamId): void
    {
        for ($i = 0; $i < 5; $i++) {
            TimeEntry::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'job_id' => $jobId,
                'team_id' => $teamId,
                'clock_in' => now()->startOfWeek()->addDays($i)->setHour(8),
                'clock_out' => now()->startOfWeek()->addDays($i)->setHour(16),
                'clock_in_lat' => 39.78,
                'clock_in_lng' => -89.65,
                'clock_method' => 'MANUAL',
                'total_hours' => 8.00,
                'status' => 'ACTIVE',
                'sync_status' => 'SYNCED',
            ]);
        }
    }

    public function test_can_submit_timesheet(): void
    {
        [$tenant, $admin, $team, $employee, $job] = $this->createSetup();
        $this->createWeeklyEntries($tenant->id, $employee->id, $job->id, $team->id);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/timesheets/submit', [
                'employee_id' => $employee->id,
                'week_start' => now()->startOfWeek()->toDateString(),
                'week_end' => now()->endOfWeek()->toDateString(),
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Timesheet submitted for review']);

        // All entries for that week should be SUBMITTED
        $submittedCount = TimeEntry::where('employee_id', $employee->id)
            ->where('status', 'SUBMITTED')
            ->count();
        $this->assertEquals(5, $submittedCount);
    }

    public function test_team_lead_can_review_timesheet(): void
    {
        [$tenant, $admin, $team, $employee, $job] = $this->createSetup();
        $this->createWeeklyEntries($tenant->id, $employee->id, $job->id, $team->id);

        // Submit first
        TimeEntry::where('employee_id', $employee->id)->update(['status' => 'SUBMITTED']);

        $teamLeadUser = User::withoutGlobalScopes()->create([
            'name' => 'Lead',
            'email' => 'lead@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'team_lead',
        ]);

        $response = $this->actingAs($teamLeadUser, 'sanctum')
            ->postJson('/api/v1/timesheets/review', [
                'employee_id' => $employee->id,
                'week_start' => now()->startOfWeek()->toDateString(),
                'week_end' => now()->endOfWeek()->toDateString(),
                'action' => 'approve',
            ]);

        $response->assertStatus(200);

        $approvedCount = TimeEntry::where('employee_id', $employee->id)
            ->where('status', 'APPROVED')
            ->count();
        $this->assertEquals(5, $approvedCount);
    }

    public function test_admin_can_reject_timesheet(): void
    {
        [$tenant, $admin, $team, $employee, $job] = $this->createSetup();
        $this->createWeeklyEntries($tenant->id, $employee->id, $job->id, $team->id);

        TimeEntry::where('employee_id', $employee->id)->update(['status' => 'SUBMITTED']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/timesheets/review', [
                'employee_id' => $employee->id,
                'week_start' => now()->startOfWeek()->toDateString(),
                'week_end' => now()->endOfWeek()->toDateString(),
                'action' => 'reject',
                'reason' => 'Missing entries for Tuesday',
            ]);

        $response->assertStatus(200);

        $rejectedCount = TimeEntry::where('employee_id', $employee->id)
            ->where('status', 'REJECTED')
            ->count();
        $this->assertEquals(5, $rejectedCount);
    }

    public function test_admin_can_mark_payroll_processed(): void
    {
        [$tenant, $admin, $team, $employee, $job] = $this->createSetup();
        $this->createWeeklyEntries($tenant->id, $employee->id, $job->id, $team->id);

        TimeEntry::where('employee_id', $employee->id)->update(['status' => 'APPROVED']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/timesheets/process-payroll', [
                'employee_id' => $employee->id,
                'week_start' => now()->startOfWeek()->toDateString(),
                'week_end' => now()->endOfWeek()->toDateString(),
            ]);

        $response->assertStatus(200);

        $processedCount = TimeEntry::where('employee_id', $employee->id)
            ->where('status', 'PAYROLL_PROCESSED')
            ->count();
        $this->assertEquals(5, $processedCount);
    }
}
