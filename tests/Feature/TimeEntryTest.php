<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Job;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TimeEntryTest extends TestCase
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

    public function test_can_clock_in(): void
    {
        [$tenant, $admin, $team, $employee, $job] = $this->createSetup();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/time-entries/clock-in', [
                'employee_id' => $employee->id,
                'job_id' => $job->id,
                'clock_in_lat' => 39.7817213,
                'clock_in_lng' => -89.6501481,
                'clock_method' => 'GEOFENCE',
                'device_id' => 'device-abc-123',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'employee_id', 'job_id', 'team_id',
                    'clock_in', 'clock_out', 'clock_method', 'status',
                ],
            ]);

        $this->assertDatabaseHas('time_entries', [
            'employee_id' => $employee->id,
            'job_id' => $job->id,
            'team_id' => $team->id,
            'clock_method' => 'GEOFENCE',
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
        ]);
    }

    public function test_can_clock_out(): void
    {
        [$tenant, $admin, $team, $employee, $job] = $this->createSetup();

        $entry = TimeEntry::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'job_id' => $job->id,
            'team_id' => $team->id,
            'clock_in' => now()->subHours(8),
            'clock_in_lat' => 39.7817213,
            'clock_in_lng' => -89.6501481,
            'clock_method' => 'GEOFENCE',
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/time-entries/{$entry->id}/clock-out", [
                'clock_out_lat' => 39.7817213,
                'clock_out_lng' => -89.6501481,
            ]);

        $response->assertStatus(200);
        $entry->refresh();
        $this->assertNotNull($entry->clock_out);
        $this->assertNotNull($entry->total_hours);
    }

    public function test_cannot_clock_in_when_already_clocked_in(): void
    {
        [$tenant, $admin, $team, $employee, $job] = $this->createSetup();

        TimeEntry::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'job_id' => $job->id,
            'team_id' => $team->id,
            'clock_in' => now()->subHour(),
            'clock_in_lat' => 39.78,
            'clock_in_lng' => -89.65,
            'clock_method' => 'GEOFENCE',
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/time-entries/clock-in', [
                'employee_id' => $employee->id,
                'job_id' => $job->id,
                'clock_in_lat' => 39.78,
                'clock_in_lng' => -89.65,
                'clock_method' => 'MANUAL',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Employee is already clocked in']);
    }

    public function test_admin_can_list_time_entries(): void
    {
        [$tenant, $admin, $team, $employee, $job] = $this->createSetup();

        TimeEntry::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'job_id' => $job->id,
            'team_id' => $team->id,
            'clock_in' => now()->subHours(8),
            'clock_out' => now(),
            'clock_in_lat' => 39.78,
            'clock_in_lng' => -89.65,
            'clock_method' => 'MANUAL',
            'total_hours' => 8.00,
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/time-entries');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_clock_out_calculates_total_hours(): void
    {
        [$tenant, $admin, $team, $employee, $job] = $this->createSetup();

        $clockIn = now()->subHours(8)->subMinutes(30);
        $entry = TimeEntry::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'job_id' => $job->id,
            'team_id' => $team->id,
            'clock_in' => $clockIn,
            'clock_in_lat' => 39.78,
            'clock_in_lng' => -89.65,
            'clock_method' => 'MANUAL',
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/time-entries/{$entry->id}/clock-out", [
                'clock_out_lat' => 39.78,
                'clock_out_lng' => -89.65,
            ]);

        $entry->refresh();
        $this->assertGreaterThanOrEqual(8.0, (float) $entry->total_hours);
    }

    public function test_time_entries_are_tenant_scoped(): void
    {
        [$tenantA, $adminA, $teamA, $employeeA, $jobA] = $this->createSetup();

        TimeEntry::create([
            'tenant_id' => $tenantA->id,
            'employee_id' => $employeeA->id,
            'job_id' => $jobA->id,
            'team_id' => $teamA->id,
            'clock_in' => now()->subHour(),
            'clock_in_lat' => 39.78,
            'clock_in_lng' => -89.65,
            'clock_method' => 'MANUAL',
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Other Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $response = $this->actingAs($adminA, 'sanctum')
            ->getJson('/api/v1/time-entries');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_verify_time_entry_with_selfie(): void
    {
        // Create tenant with AUTO_PHOTO mode
        [$tenant, $admin, $team, $employee, $job] = $this->createSetup();
        $tenant->update(['clock_verification_mode' => 'AUTO_PHOTO']);

        // Create employee and time entry with UNVERIFIED status
        $entry = TimeEntry::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'job_id' => $job->id,
            'team_id' => $team->id,
            'clock_in' => now()->subHour(),
            'clock_in_lat' => 39.78,
            'clock_in_lng' => -89.65,
            'clock_method' => 'GEOFENCE',
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
            'verification_status' => 'UNVERIFIED',
        ]);

        // POST selfie to verify endpoint
        Storage::fake('s3');
        $selfie = UploadedFile::fake()->image('selfie.jpg', 640, 480);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/time-entries/{$entry->id}/verify", [
                'selfie' => $selfie,
            ]);

        $response->assertStatus(200);

        // Assert verification_status is now VERIFIED
        $entry->refresh();
        $this->assertEquals('VERIFIED', $entry->verification_status);
        $this->assertNotNull($entry->selfie_url);
    }
}
