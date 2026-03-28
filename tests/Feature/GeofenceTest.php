<?php

namespace Tests\Feature;

use App\Models\Geofence;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofenceTest extends TestCase
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

        $job = Job::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Site',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-01-01',
        ]);

        return [$tenant, $user, $job];
    }

    public function test_admin_can_create_geofence(): void
    {
        [$tenant, $user, $job] = $this->createSetup();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/geofences', [
                'job_id' => $job->id,
                'name' => 'Main Entrance',
                'latitude' => 39.7817213,
                'longitude' => -89.6501481,
                'radius_meters' => 150,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'job_id', 'name', 'latitude', 'longitude',
                    'radius_meters', 'is_active',
                ],
            ]);

        $this->assertDatabaseHas('geofences', [
            'name' => 'Main Entrance',
            'tenant_id' => $tenant->id,
            'job_id' => $job->id,
        ]);
    }

    public function test_admin_can_list_geofences(): void
    {
        [$tenant, $user, $job] = $this->createSetup();

        Geofence::create([
            'tenant_id' => $tenant->id,
            'job_id' => $job->id,
            'name' => 'Gate A',
            'latitude' => 39.7817213,
            'longitude' => -89.6501481,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        Geofence::create([
            'tenant_id' => $tenant->id,
            'job_id' => $job->id,
            'name' => 'Gate B',
            'latitude' => 39.7827213,
            'longitude' => -89.6511481,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/geofences');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_update_geofence(): void
    {
        [$tenant, $user, $job] = $this->createSetup();

        $geofence = Geofence::create([
            'tenant_id' => $tenant->id,
            'job_id' => $job->id,
            'name' => 'Old Name',
            'latitude' => 39.7817213,
            'longitude' => -89.6501481,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/geofences/{$geofence->id}", [
                'name' => 'New Name',
                'radius_meters' => 200,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('geofences', [
            'id' => $geofence->id,
            'name' => 'New Name',
            'radius_meters' => 200,
        ]);
    }

    public function test_admin_can_deactivate_geofence(): void
    {
        [$tenant, $user, $job] = $this->createSetup();

        $geofence = Geofence::create([
            'tenant_id' => $tenant->id,
            'job_id' => $job->id,
            'name' => 'Active Fence',
            'latitude' => 39.7817213,
            'longitude' => -89.6501481,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/geofences/{$geofence->id}", [
                'is_active' => false,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('geofences', [
            'id' => $geofence->id,
            'is_active' => false,
        ]);
    }

    public function test_radius_must_be_between_50_and_500(): void
    {
        [$tenant, $user, $job] = $this->createSetup();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/geofences', [
                'job_id' => $job->id,
                'name' => 'Too Small',
                'latitude' => 39.7817213,
                'longitude' => -89.6501481,
                'radius_meters' => 10,
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['radius_meters']);
    }

    public function test_geofences_are_tenant_scoped(): void
    {
        [$tenantA, $userA, $jobA] = $this->createSetup();

        Geofence::create([
            'tenant_id' => $tenantA->id,
            'job_id' => $jobA->id,
            'name' => 'A Fence',
            'latitude' => 39.78,
            'longitude' => -89.65,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $tenantB = Tenant::create([
            'name' => 'Other Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $jobB = Job::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'B Site',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-01-01',
        ]);

        Geofence::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'job_id' => $jobB->id,
            'name' => 'B Fence',
            'latitude' => 40.00,
            'longitude' => -90.00,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/geofences');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
