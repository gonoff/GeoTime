<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PtoBalance;
use App\Models\PtoRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PtoTest extends TestCase
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

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 25.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        // Create PTO balance
        PtoBalance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'type' => 'VACATION',
            'balance_hours' => 80.0, // 10 days
            'accrued_hours' => 80.0,
            'used_hours' => 0.0,
            'year' => 2026,
        ]);

        return [$tenant, $adminUser, $employee];
    }

    public function test_employee_can_request_pto(): void
    {
        [$tenant, $admin, $employee] = $this->createSetup();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/pto', [
                'employee_id' => $employee->id,
                'type' => 'VACATION',
                'start_date' => '2026-04-06',
                'end_date' => '2026-04-10',
                'hours' => 40.0,
                'notes' => 'Family vacation',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'employee_id', 'type', 'start_date', 'end_date', 'hours', 'status'],
            ]);

        $this->assertDatabaseHas('pto_requests', [
            'employee_id' => $employee->id,
            'type' => 'VACATION',
            'status' => 'PENDING',
        ]);
    }

    public function test_manager_can_approve_pto(): void
    {
        [$tenant, $admin, $employee] = $this->createSetup();

        $pto = PtoRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'type' => 'VACATION',
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-10',
            'hours' => 40.0,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/pto/{$pto->id}/review", [
                'action' => 'approve',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('pto_requests', [
            'id' => $pto->id,
            'status' => 'APPROVED',
        ]);

        // Check balance was deducted
        $balance = PtoBalance::where('employee_id', $employee->id)
            ->where('type', 'VACATION')
            ->first();
        $this->assertEquals(40.0, (float) $balance->used_hours);
        $this->assertEquals(40.0, (float) $balance->balance_hours);
    }

    public function test_manager_can_deny_pto(): void
    {
        [$tenant, $admin, $employee] = $this->createSetup();

        $pto = PtoRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'type' => 'SICK',
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-06',
            'hours' => 8.0,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/pto/{$pto->id}/review", [
                'action' => 'deny',
                'reason' => 'Insufficient coverage',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('pto_requests', [
            'id' => $pto->id,
            'status' => 'DENIED',
        ]);
    }

    public function test_pto_request_rejected_if_insufficient_balance(): void
    {
        [$tenant, $admin, $employee] = $this->createSetup();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/pto', [
                'employee_id' => $employee->id,
                'type' => 'VACATION',
                'start_date' => '2026-04-01',
                'end_date' => '2026-05-01',
                'hours' => 200.0, // More than 80 balance
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Insufficient PTO balance']);
    }

    public function test_can_list_pto_requests(): void
    {
        [$tenant, $admin, $employee] = $this->createSetup();

        PtoRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'type' => 'VACATION',
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-10',
            'hours' => 40.0,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/pto');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_pto_balance(): void
    {
        [$tenant, $admin, $employee] = $this->createSetup();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/pto/balance/{$employee->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['type', 'balance_hours', 'accrued_hours', 'used_hours', 'year'],
                ],
            ]);
    }
}
