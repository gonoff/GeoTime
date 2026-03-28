<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
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

    public function test_admin_can_create_employee(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@test.com',
                'phone' => '555-1234',
                'role' => 'EMPLOYEE',
                'hourly_rate' => 25.00,
                'date_of_birth' => '1990-05-15',
                'hire_date' => '2026-01-15',
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'Springfield',
                    'state' => 'IL',
                    'zip' => '62704',
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'first_name', 'last_name', 'email', 'phone',
                    'role', 'hourly_rate', 'hire_date', 'status', 'created_at',
                ],
            ]);

        $this->assertDatabaseHas('employees', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_admin_can_list_employees(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/employees');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_update_employee(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 18.00,
            'hire_date' => '2026-02-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/employees/{$employee->id}", [
                'hourly_rate' => 22.00,
                'role' => 'TEAM_LEAD',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'hourly_rate' => 22.00,
            'role' => 'TEAM_LEAD',
        ]);
    }

    public function test_admin_can_show_single_employee(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Carol',
            'last_name' => 'White',
            'email' => 'carol@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 30.00,
            'hire_date' => '2026-03-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'first_name' => 'Carol',
                    'last_name' => 'White',
                ],
            ]);
    }

    public function test_employees_are_tenant_scoped(): void
    {
        [$tenantA, $userA] = $this->createAuthenticatedAdmin();

        Employee::create([
            'tenant_id' => $tenantA->id,
            'first_name' => 'TenantA',
            'last_name' => 'Employee',
            'email' => 'a@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Other Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'first_name' => 'TenantB',
            'last_name' => 'Employee',
            'email' => 'b@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/employees');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_non_admin_cannot_create_employee(): void
    {
        [$tenant, $admin] = $this->createAuthenticatedAdmin();

        $employee = User::withoutGlobalScopes()->create([
            'name' => 'Employee',
            'email' => 'emp@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/employees', [
                'first_name' => 'New',
                'last_name' => 'Person',
                'email' => 'new@test.com',
                'role' => 'EMPLOYEE',
                'hourly_rate' => 15.00,
                'hire_date' => '2026-01-01',
            ]);

        $response->assertStatus(403);
    }
}
