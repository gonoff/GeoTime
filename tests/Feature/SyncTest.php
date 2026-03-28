<?php

namespace Tests\Feature;

use App\Models\BreakEntry;
use App\Models\Employee;
use App\Models\Geofence;
use App\Models\Job;
use App\Models\Team;
use App\Models\TeamAssignment;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncTest extends TestCase
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

        $user = User::withoutGlobalScopes()->create([
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

        $geofence = Geofence::create([
            'tenant_id' => $tenant->id,
            'job_id' => $job->id,
            'name' => 'Main Entrance',
            'latitude' => 39.7817213,
            'longitude' => -89.6501481,
            'radius_meters' => 150,
            'is_active' => true,
        ]);

        return [$tenant, $user, $team, $employee, $job, $geofence];
    }

    public function test_pull_sync_returns_updated_entities(): void
    {
        [$tenant, $user, $team, $employee, $job, $geofence] = $this->createSetup();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/sync?last_synced_at=' . now()->subDay()->toIso8601String());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'geofences',
                    'teams',
                    'jobs',
                    'employees',
                    'server_time',
                ],
            ]);

        $this->assertCount(1, $response->json('data.geofences'));
        $this->assertCount(1, $response->json('data.teams'));
        $this->assertCount(1, $response->json('data.jobs'));
    }

    public function test_push_sync_creates_time_entries(): void
    {
        [$tenant, $user, $team, $employee, $job, $geofence] = $this->createSetup();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sync', [
                'time_entries' => [
                    [
                        'client_id' => 'local-uuid-1',
                        'employee_id' => $employee->id,
                        'job_id' => $job->id,
                        'clock_in' => now()->subHours(8)->toIso8601String(),
                        'clock_out' => now()->toIso8601String(),
                        'clock_in_lat' => 39.7817213,
                        'clock_in_lng' => -89.6501481,
                        'clock_out_lat' => 39.7817213,
                        'clock_out_lng' => -89.6501481,
                        'clock_method' => 'GEOFENCE',
                        'device_id' => 'device-123',
                    ],
                ],
                'breaks' => [],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'synced_entries',
                    'conflicts',
                    'server_time',
                ],
            ]);

        $this->assertDatabaseHas('time_entries', [
            'employee_id' => $employee->id,
            'job_id' => $job->id,
            'sync_status' => 'SYNCED',
        ]);

        $this->assertCount(1, $response->json('data.synced_entries'));
    }

    public function test_push_sync_creates_breaks(): void
    {
        [$tenant, $user, $team, $employee, $job, $geofence] = $this->createSetup();

        $timeEntry = TimeEntry::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'job_id' => $job->id,
            'team_id' => $team->id,
            'clock_in' => now()->subHours(8),
            'clock_in_lat' => 39.78,
            'clock_in_lng' => -89.65,
            'clock_method' => 'MANUAL',
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sync', [
                'time_entries' => [],
                'breaks' => [
                    [
                        'client_id' => 'break-local-1',
                        'time_entry_id' => $timeEntry->id,
                        'type' => 'PAID_REST',
                        'start_time' => now()->subHours(4)->toIso8601String(),
                        'end_time' => now()->subHours(4)->addMinutes(15)->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('break_entries', [
            'time_entry_id' => $timeEntry->id,
            'type' => 'PAID_REST',
        ]);
    }

    public function test_pull_sync_respects_last_synced_at(): void
    {
        [$tenant, $user, $team, $employee, $job, $geofence] = $this->createSetup();

        // Request with a future timestamp — should get nothing
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/sync?last_synced_at=' . now()->addDay()->toIso8601String());

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.geofences'));
        $this->assertCount(0, $response->json('data.teams'));
    }
}
