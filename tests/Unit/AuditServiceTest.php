<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createSetup(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
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

        return [$tenant, $user];
    }

    public function test_can_log_create_action(): void
    {
        [$tenant, $user] = $this->createSetup();

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

        $service = new AuditService();
        $service->log(
            entityType: 'employee',
            entityId: $employee->id,
            action: 'CREATE',
            changedBy: $user->id,
            newValue: $employee->toArray(),
        );

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'employee',
            'entity_id' => $employee->id,
            'action' => 'CREATE',
            'changed_by' => $user->id,
        ]);
    }

    public function test_can_log_update_action_with_old_and_new_values(): void
    {
        [$tenant, $user] = $this->createSetup();

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

        $oldValues = ['hourly_rate' => 25.00];
        $newValues = ['hourly_rate' => 30.00];

        $service = new AuditService();
        $service->log(
            entityType: 'employee',
            entityId: $employee->id,
            action: 'UPDATE',
            changedBy: $user->id,
            oldValue: $oldValues,
            newValue: $newValues,
        );

        $log = AuditLog::where('entity_id', $employee->id)
            ->where('action', 'UPDATE')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(25.00, $log->old_value['hourly_rate']);
        $this->assertEquals(30.00, $log->new_value['hourly_rate']);
    }
}
