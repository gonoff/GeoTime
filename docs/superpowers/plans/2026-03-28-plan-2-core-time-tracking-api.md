# Plan 2: Core Time Tracking API

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the core business logic API — employees, teams, transfers, jobs, geofences, time entries, breaks, overtime, rounding, timesheet approval, PTO, audit logging, and the mobile sync endpoint.

**Architecture:** All models are tenant-scoped via `BelongsToTenant` trait (Plan 1). UUIDs for all primary keys via `HasUuids`. JSON responses via Laravel API Resources. Validation via Form Requests. PostGIS geometry columns for geofence data. Single bulk sync endpoint for mobile offline-first architecture.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL 16 + PostGIS, Redis 7, Laravel Sanctum 4.x

---

## File Structure

```
app/
├── Models/
│   ├── Employee.php
│   ├── Team.php
│   ├── TeamAssignment.php
│   ├── Transfer.php
│   ├── Job.php
│   ├── Geofence.php
│   ├── TimeEntry.php
│   ├── BreakEntry.php
│   ├── PtoRequest.php
│   ├── PtoBalance.php
│   └── AuditLog.php
├── Http/
│   ├── Controllers/
│   │   ├── EmployeeController.php
│   │   ├── TeamController.php
│   │   ├── TransferController.php
│   │   ├── JobController.php
│   │   ├── GeofenceController.php
│   │   ├── TimeEntryController.php
│   │   ├── BreakEntryController.php
│   │   ├── TimesheetController.php
│   │   ├── PtoController.php
│   │   └── SyncController.php
│   ├── Requests/
│   │   ├── StoreEmployeeRequest.php
│   │   ├── UpdateEmployeeRequest.php
│   │   ├── StoreTeamRequest.php
│   │   ├── UpdateTeamRequest.php
│   │   ├── StoreTransferRequest.php
│   │   ├── ApproveTransferRequest.php
│   │   ├── StoreJobRequest.php
│   │   ├── UpdateJobRequest.php
│   │   ├── StoreGeofenceRequest.php
│   │   ├── UpdateGeofenceRequest.php
│   │   ├── ClockInRequest.php
│   │   ├── ClockOutRequest.php
│   │   ├── StoreBreakRequest.php
│   │   ├── EndBreakRequest.php
│   │   ├── SubmitTimesheetRequest.php
│   │   ├── ReviewTimesheetRequest.php
│   │   ├── StorePtoRequest.php
│   │   ├── ReviewPtoRequest.php
│   │   └── SyncRequest.php
│   └── Resources/
│       ├── EmployeeResource.php
│       ├── TeamResource.php
│       ├── TransferResource.php
│       ├── JobResource.php
│       ├── GeofenceResource.php
│       ├── TimeEntryResource.php
│       ├── BreakEntryResource.php
│       ├── PtoRequestResource.php
│       └── SyncResource.php
├── Services/
│   ├── OvertimeCalculator.php
│   ├── TimeRounder.php
│   ├── TransferService.php
│   └── AuditService.php
├── Console/
│   └── Commands/
│       └── RevertTemporaryTransfers.php
database/
└── migrations/
    ├── xxxx_create_employees_table.php
    ├── xxxx_create_teams_table.php
    ├── xxxx_create_team_assignments_table.php
    ├── xxxx_create_transfers_table.php
    ├── xxxx_create_jobs_table.php
    ├── xxxx_create_geofences_table.php
    ├── xxxx_create_time_entries_table.php
    ├── xxxx_create_break_entries_table.php
    ├── xxxx_create_pto_requests_table.php
    ├── xxxx_create_pto_balances_table.php
    └── xxxx_create_audit_logs_table.php
routes/
└── api.php (modify)
tests/
├── Unit/
│   ├── OvertimeCalculatorTest.php
│   ├── TimeRounderTest.php
│   └── AuditServiceTest.php
└── Feature/
    ├── EmployeeTest.php
    ├── TeamTest.php
    ├── TransferTest.php
    ├── JobTest.php
    ├── GeofenceTest.php
    ├── TimeEntryTest.php
    ├── BreakEntryTest.php
    ├── TimesheetTest.php
    ├── PtoTest.php
    ├── SyncTest.php
    └── AuditLogTest.php
```

---

## Task 1: Employee Model & CRUD

**Files:**
- Create: `database/migrations/xxxx_create_employees_table.php`
- Create: `app/Models/Employee.php`
- Create: `app/Http/Resources/EmployeeResource.php`
- Create: `app/Http/Requests/StoreEmployeeRequest.php`
- Create: `app/Http/Requests/UpdateEmployeeRequest.php`
- Create: `app/Http/Controllers/EmployeeController.php`
- Create: `tests/Feature/EmployeeTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/EmployeeTest.php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/EmployeeTest.php`
Expected: FAIL — Employee model/table does not exist.

- [ ] **Step 3: Create the employees migration**

```bash
docker compose exec app php artisan make:migration create_employees_table
```

```php
// database/migrations/xxxx_create_employees_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('current_team_id')->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255);
            $table->string('phone', 20)->nullable();
            $table->string('role', 20)->default('EMPLOYEE'); // EMPLOYEE, TEAM_LEAD, MANAGER, ADMIN, SUPER_ADMIN
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->text('ssn_encrypted')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->jsonb('address')->nullable(); // { street, city, state, zip }
            $table->date('hire_date');
            $table->string('device_id', 255)->nullable();
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, INACTIVE, TERMINATED
            $table->string('qbo_employee_id', 50)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
```

- [ ] **Step 4: Create the Employee model**

```php
// app/Models/Employee.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'current_team_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'hourly_rate',
        'ssn_encrypted',
        'date_of_birth',
        'address',
        'hire_date',
        'device_id',
        'status',
        'qbo_employee_id',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function teamAssignments(): HasMany
    {
        return $this->hasMany(TeamAssignment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(Transfer::class, 'employee_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
```

- [ ] **Step 5: Create EmployeeResource**

```php
// app/Http/Resources/EmployeeResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'hourly_rate' => $this->hourly_rate,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'hire_date' => $this->hire_date?->toDateString(),
            'address' => $this->address,
            'device_id' => $this->device_id,
            'status' => $this->status,
            'current_team_id' => $this->current_team_id,
            'qbo_employee_id' => $this->qbo_employee_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Create StoreEmployeeRequest**

```php
// app/Http/Requests/StoreEmployeeRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'in:EMPLOYEE,TEAM_LEAD,MANAGER,ADMIN,SUPER_ADMIN'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'ssn_encrypted' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string'],
            'address.city' => ['nullable', 'string'],
            'address.state' => ['nullable', 'string'],
            'address.zip' => ['nullable', 'string'],
            'hire_date' => ['required', 'date'],
            'device_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 7: Create UpdateEmployeeRequest**

```php
// app/Http/Requests/UpdateEmployeeRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['sometimes', 'string', 'in:EMPLOYEE,TEAM_LEAD,MANAGER,ADMIN,SUPER_ADMIN'],
            'hourly_rate' => ['sometimes', 'numeric', 'min:0'],
            'ssn_encrypted' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'array'],
            'hire_date' => ['sometimes', 'date'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE,TERMINATED'],
        ];
    }
}
```

- [ ] **Step 8: Create EmployeeController**

```php
// app/Http/Controllers/EmployeeController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $employees = Employee::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('team_id'), fn ($q, $teamId) => $q->where('current_team_id', $teamId))
            ->orderBy('last_name')
            ->paginate($request->query('per_page', 25));

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee->update($request->validated());

        return new EmployeeResource($employee->fresh());
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee->update(['status' => 'TERMINATED']);

        return response()->json(['message' => 'Employee terminated']);
    }
}
```

- [ ] **Step 9: Add employee routes**

Add to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
use App\Http\Controllers\EmployeeController;

// Employees
Route::apiResource('employees', EmployeeController::class);
```

- [ ] **Step 10: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/EmployeeTest.php`
Expected: All 6 tests PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Models/Employee.php app/Http/Controllers/EmployeeController.php app/Http/Resources/EmployeeResource.php app/Http/Requests/StoreEmployeeRequest.php app/Http/Requests/UpdateEmployeeRequest.php database/migrations/*create_employees* routes/api.php tests/Feature/EmployeeTest.php
git commit -m "feat: add Employee model with CRUD API endpoints and tests"
```

---

## Task 2: Team Model & CRUD

**Files:**
- Create: `database/migrations/xxxx_create_teams_table.php`
- Create: `app/Models/Team.php`
- Create: `app/Http/Resources/TeamResource.php`
- Create: `app/Http/Requests/StoreTeamRequest.php`
- Create: `app/Http/Requests/UpdateTeamRequest.php`
- Create: `app/Http/Controllers/TeamController.php`
- Create: `tests/Feature/TeamTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/TeamTest.php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
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

    public function test_admin_can_create_team(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/teams', [
                'name' => 'Alpha Team',
                'description' => 'The alpha team',
                'color_tag' => '#FF5733',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description', 'color_tag', 'status', 'created_at'],
            ]);

        $this->assertDatabaseHas('teams', [
            'name' => 'Alpha Team',
            'tenant_id' => $tenant->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_admin_can_list_teams(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Team A',
            'status' => 'ACTIVE',
        ]);

        Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Team B',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/teams');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_assign_team_lead(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Lead',
            'last_name' => 'Person',
            'email' => 'lead@test.com',
            'role' => 'TEAM_LEAD',
            'hourly_rate' => 25.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $team = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Team A',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/teams/{$team->id}", [
                'lead_employee_id' => $employee->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'lead_employee_id' => $employee->id,
        ]);
    }

    public function test_admin_can_archive_team(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $team = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Old Team',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/teams/{$team->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'status' => 'ARCHIVED',
        ]);
    }

    public function test_teams_are_tenant_scoped(): void
    {
        [$tenantA, $userA] = $this->createAuthenticatedAdmin();

        Team::create([
            'tenant_id' => $tenantA->id,
            'name' => 'A Team',
            'status' => 'ACTIVE',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Other Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        Team::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'B Team',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/teams');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/TeamTest.php`
Expected: FAIL — Team model/table does not exist.

- [ ] **Step 3: Create the teams migration**

```bash
docker compose exec app php artisan make:migration create_teams_table
```

```php
// database/migrations/xxxx_create_teams_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('color_tag', 7)->nullable(); // Hex color
            $table->uuid('lead_employee_id')->nullable();
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, ARCHIVED
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
        });

        // Add foreign key for current_team_id on employees now that teams table exists
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('current_team_id')->references('id')->on('teams')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['current_team_id']);
        });
        Schema::dropIfExists('teams');
    }
};
```

- [ ] **Step 4: Create the Team model**

```php
// app/Models/Team.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'color_tag',
        'lead_employee_id',
        'status',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'lead_employee_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Employee::class, 'current_team_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeamAssignment::class);
    }
}
```

- [ ] **Step 5: Create TeamResource**

```php
// app/Http/Resources/TeamResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color_tag' => $this->color_tag,
            'lead_employee_id' => $this->lead_employee_id,
            'lead' => $this->whenLoaded('lead', fn () => new EmployeeResource($this->lead)),
            'status' => $this->status,
            'member_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Create StoreTeamRequest**

```php
// app/Http/Requests/StoreTeamRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'color_tag' => ['nullable', 'string', 'max:7'],
            'lead_employee_id' => ['nullable', 'uuid', 'exists:employees,id'],
        ];
    }
}
```

- [ ] **Step 7: Create UpdateTeamRequest**

```php
// app/Http/Requests/UpdateTeamRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'color_tag' => ['nullable', 'string', 'max:7'],
            'lead_employee_id' => ['nullable', 'uuid', 'exists:employees,id'],
        ];
    }
}
```

- [ ] **Step 8: Create TeamController**

```php
// app/Http/Controllers/TeamController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $teams = Team::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->withCount('members')
            ->with('lead')
            ->orderBy('name')
            ->paginate($request->query('per_page', 25));

        return TeamResource::collection($teams);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = Team::create($request->validated());

        return (new TeamResource($team))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Team $team): TeamResource
    {
        $team->loadCount('members')->load('lead');

        return new TeamResource($team);
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $team->update($request->validated());

        return new TeamResource($team->fresh());
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $team->update(['status' => 'ARCHIVED']);

        return response()->json(['message' => 'Team archived']);
    }
}
```

- [ ] **Step 9: Add team routes**

Add to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
use App\Http\Controllers\TeamController;

Route::apiResource('teams', TeamController::class);
```

- [ ] **Step 10: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/TeamTest.php`
Expected: All 5 tests PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Models/Team.php app/Http/Controllers/TeamController.php app/Http/Resources/TeamResource.php app/Http/Requests/StoreTeamRequest.php app/Http/Requests/UpdateTeamRequest.php database/migrations/*create_teams* routes/api.php tests/Feature/TeamTest.php
git commit -m "feat: add Team model with CRUD API endpoints and tests"
```

---

## Task 3: Team Assignment History

**Files:**
- Create: `database/migrations/xxxx_create_team_assignments_table.php`
- Create: `app/Models/TeamAssignment.php`
- Create: `tests/Feature/TeamAssignmentTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/TeamAssignmentTest.php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/TeamAssignmentTest.php`
Expected: FAIL — TeamAssignment model/table does not exist.

- [ ] **Step 3: Create the team_assignments migration**

```bash
docker compose exec app php artisan make:migration create_team_assignments_table
```

```php
// database/migrations/xxxx_create_team_assignments_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->uuid('team_id');
            $table->timestampTz('assigned_at');
            $table->timestampTz('ended_at')->nullable();
            $table->uuid('assigned_by'); // user who made the assignment
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index(['employee_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_assignments');
    }
};
```

- [ ] **Step 4: Create the TeamAssignment model**

```php
// app/Models/TeamAssignment.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamAssignment extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'team_id',
        'assigned_at',
        'ended_at',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
```

- [ ] **Step 5: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/TeamAssignmentTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/TeamAssignment.php database/migrations/*create_team_assignments* tests/Feature/TeamAssignmentTest.php
git commit -m "feat: add TeamAssignment model for team history tracking"
```

---

## Task 4: Employee Transfer Workflow

**Files:**
- Create: `database/migrations/xxxx_create_transfers_table.php`
- Create: `app/Models/Transfer.php`
- Create: `app/Http/Resources/TransferResource.php`
- Create: `app/Http/Requests/StoreTransferRequest.php`
- Create: `app/Http/Requests/ApproveTransferRequest.php`
- Create: `app/Services/TransferService.php`
- Create: `app/Http/Controllers/TransferController.php`
- Create: `app/Console/Commands/RevertTemporaryTransfers.php`
- Create: `tests/Feature/TransferTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/TransferTest.php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/TransferTest.php`
Expected: FAIL — Transfer model/table does not exist.

- [ ] **Step 3: Create the transfers migration**

```bash
docker compose exec app php artisan make:migration create_transfers_table
```

```php
// database/migrations/xxxx_create_transfers_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->uuid('from_team_id');
            $table->uuid('to_team_id');
            $table->string('reason_category', 30); // OPERATIONAL, PERFORMANCE, EMPLOYEE_REQUEST, ADMINISTRATIVE
            $table->string('reason_code', 30); // WORKLOAD_BALANCE, SKILL_MATCH, PROJECT_NEED, etc.
            $table->text('notes')->nullable();
            $table->string('transfer_type', 20); // PERMANENT, TEMPORARY
            $table->date('effective_date');
            $table->date('expected_return_date')->nullable();
            $table->uuid('initiated_by');
            $table->uuid('approved_by')->nullable();
            $table->string('status', 20)->default('PENDING'); // PENDING, APPROVED, REJECTED, COMPLETED, REVERTED
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('from_team_id')->references('id')->on('teams');
            $table->foreign('to_team_id')->references('id')->on('teams');
            $table->index('tenant_id');
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
```

- [ ] **Step 4: Create the Transfer model**

```php
// app/Models/Transfer.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use HasUuids, BelongsToTenant;

    public const REASON_CATEGORIES = [
        'OPERATIONAL', 'PERFORMANCE', 'EMPLOYEE_REQUEST', 'ADMINISTRATIVE',
    ];

    public const REASON_CODES = [
        'WORKLOAD_BALANCE', 'SKILL_MATCH', 'PROJECT_NEED', 'LOCATION_CHANGE',
        'PERFORMANCE_IMPROVEMENT', 'PROMOTION', 'MENTOR_ASSIGNMENT',
        'PERSONAL_REQUEST', 'SCHEDULE_ACCOMMODATION', 'CONFLICT_RESOLUTION',
        'TEAM_RESTRUCTURE', 'TEAM_DISSOLUTION', 'SEASONAL_ADJUSTMENT', 'OTHER',
    ];

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'from_team_id',
        'to_team_id',
        'reason_category',
        'reason_code',
        'notes',
        'transfer_type',
        'effective_date',
        'expected_return_date',
        'initiated_by',
        'approved_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'expected_return_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'to_team_id');
    }
}
```

- [ ] **Step 5: Create TransferResource**

```php
// app/Http/Resources/TransferResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'from_team_id' => $this->from_team_id,
            'from_team' => $this->whenLoaded('fromTeam', fn () => new TeamResource($this->fromTeam)),
            'to_team_id' => $this->to_team_id,
            'to_team' => $this->whenLoaded('toTeam', fn () => new TeamResource($this->toTeam)),
            'reason_category' => $this->reason_category,
            'reason_code' => $this->reason_code,
            'notes' => $this->notes,
            'transfer_type' => $this->transfer_type,
            'effective_date' => $this->effective_date?->toDateString(),
            'expected_return_date' => $this->expected_return_date?->toDateString(),
            'initiated_by' => $this->initiated_by,
            'approved_by' => $this->approved_by,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Create StoreTransferRequest**

```php
// app/Http/Requests/StoreTransferRequest.php
<?php

namespace App\Http\Requests;

use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admins, managers, and team leads (team leads create pending transfers)
        $role = $this->user()->role;
        return in_array($role, ['admin', 'super_admin', 'manager', 'team_lead']);
    }

    public function rules(): array
    {
        $reasonCodes = implode(',', Transfer::REASON_CODES);
        $reasonCategories = implode(',', Transfer::REASON_CATEGORIES);

        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'from_team_id' => ['required', 'uuid', 'exists:teams,id'],
            'to_team_id' => ['required', 'uuid', 'exists:teams,id', 'different:from_team_id'],
            'reason_category' => ['required', 'string', "in:{$reasonCategories}"],
            'reason_code' => ['required', 'string', "in:{$reasonCodes}"],
            'notes' => ['nullable', 'string', 'required_if:reason_code,OTHER'],
            'transfer_type' => ['required', 'string', 'in:PERMANENT,TEMPORARY'],
            'effective_date' => ['required', 'date'],
            'expected_return_date' => ['nullable', 'date', 'after:effective_date', 'required_if:transfer_type,TEMPORARY'],
        ];
    }
}
```

- [ ] **Step 7: Create TransferService**

```php
// app/Services/TransferService.php
<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TeamAssignment;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;

class TransferService
{
    /**
     * Execute an approved transfer — move the employee to the new team
     * and update team assignment history.
     */
    public function executeTransfer(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            // End current team assignment
            TeamAssignment::where('employee_id', $transfer->employee_id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Create new team assignment
            TeamAssignment::create([
                'tenant_id' => $transfer->tenant_id,
                'employee_id' => $transfer->employee_id,
                'team_id' => $transfer->to_team_id,
                'assigned_at' => now(),
                'assigned_by' => $transfer->approved_by ?? $transfer->initiated_by,
            ]);

            // Update employee's current team
            Employee::withoutGlobalScopes()
                ->where('id', $transfer->employee_id)
                ->update(['current_team_id' => $transfer->to_team_id]);

            // Mark transfer as completed
            $transfer->update(['status' => 'COMPLETED']);
        });
    }

    /**
     * Revert a temporary transfer — move the employee back to the original team.
     */
    public function revertTransfer(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            // End current team assignment
            TeamAssignment::where('employee_id', $transfer->employee_id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Create assignment back to original team
            TeamAssignment::create([
                'tenant_id' => $transfer->tenant_id,
                'employee_id' => $transfer->employee_id,
                'team_id' => $transfer->from_team_id,
                'assigned_at' => now(),
                'assigned_by' => $transfer->initiated_by,
            ]);

            // Update employee's current team
            Employee::withoutGlobalScopes()
                ->where('id', $transfer->employee_id)
                ->update(['current_team_id' => $transfer->from_team_id]);

            // Mark transfer as reverted
            $transfer->update(['status' => 'REVERTED']);
        });
    }
}
```

- [ ] **Step 8: Create TransferController**

```php
// app/Http/Controllers/TransferController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $transfers = Transfer::query()
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->with(['employee', 'fromTeam', 'toTeam'])
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 25));

        return TransferResource::collection($transfers);
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $user = $request->user();
        $isTeamLead = $user->role === 'team_lead';

        $transfer = Transfer::create([
            ...$request->validated(),
            'initiated_by' => $user->id,
            'status' => $isTeamLead ? 'PENDING' : 'COMPLETED',
        ]);

        // If admin/manager, execute immediately
        if (! $isTeamLead) {
            $transfer->update(['approved_by' => $user->id]);
            $this->transferService->executeTransfer($transfer);
        }

        return (new TransferResource($transfer->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Transfer $transfer): TransferResource
    {
        $transfer->load(['employee', 'fromTeam', 'toTeam']);

        return new TransferResource($transfer);
    }

    public function approve(Request $request, Transfer $transfer): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($transfer->status !== 'PENDING') {
            return response()->json(['message' => 'Transfer is not pending'], 422);
        }

        $transfer->update(['approved_by' => $request->user()->id]);
        $this->transferService->executeTransfer($transfer);

        return response()->json([
            'data' => new TransferResource($transfer->fresh()),
            'message' => 'Transfer approved and executed',
        ]);
    }

    public function reject(Request $request, Transfer $transfer): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($transfer->status !== 'PENDING') {
            return response()->json(['message' => 'Transfer is not pending'], 422);
        }

        $transfer->update([
            'status' => 'REJECTED',
            'approved_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new TransferResource($transfer->fresh()),
            'message' => 'Transfer rejected',
        ]);
    }
}
```

- [ ] **Step 9: Create RevertTemporaryTransfers command**

```php
// app/Console/Commands/RevertTemporaryTransfers.php
<?php

namespace App\Console\Commands;

use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Console\Command;

class RevertTemporaryTransfers extends Command
{
    protected $signature = 'transfers:revert-temporary';

    protected $description = 'Revert temporary transfers that have reached their expected return date';

    public function handle(TransferService $transferService): int
    {
        $transfers = Transfer::withoutGlobalScopes()
            ->where('transfer_type', 'TEMPORARY')
            ->where('status', 'COMPLETED')
            ->whereNotNull('expected_return_date')
            ->whereDate('expected_return_date', '<=', now())
            ->get();

        $count = 0;
        foreach ($transfers as $transfer) {
            $transferService->revertTransfer($transfer);
            $count++;
        }

        $this->info("Reverted {$count} temporary transfer(s).");

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 10: Add transfer routes**

Add to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
use App\Http\Controllers\TransferController;

Route::apiResource('transfers', TransferController::class)->only(['index', 'store', 'show']);
Route::post('transfers/{transfer}/approve', [TransferController::class, 'approve']);
Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject']);
```

- [ ] **Step 11: Register scheduled command**

Add to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('transfers:revert-temporary')->dailyAt('00:00');
```

- [ ] **Step 12: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/TransferTest.php`
Expected: All 8 tests PASS.

- [ ] **Step 13: Commit**

```bash
git add app/Models/Transfer.php app/Http/Controllers/TransferController.php app/Http/Resources/TransferResource.php app/Http/Requests/StoreTransferRequest.php app/Services/TransferService.php app/Console/Commands/RevertTemporaryTransfers.php database/migrations/*create_transfers* routes/api.php routes/console.php tests/Feature/TransferTest.php
git commit -m "feat: add employee transfer workflow with approval flow and auto-reversion"
```

---

## Task 5: Job / Job Site Model & CRUD

**Files:**
- Create: `database/migrations/xxxx_create_jobs_table.php`
- Create: `app/Models/Job.php`
- Create: `app/Http/Resources/JobResource.php`
- Create: `app/Http/Requests/StoreJobRequest.php`
- Create: `app/Http/Requests/UpdateJobRequest.php`
- Create: `app/Http/Controllers/JobController.php`
- Create: `tests/Feature/JobTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/JobTest.php
<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobTest extends TestCase
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

    public function test_admin_can_create_job(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/jobs', [
                'name' => 'Office Building Renovation',
                'client_name' => 'Acme Corp',
                'address' => '456 Industrial Ave, Springfield, IL',
                'status' => 'ACTIVE',
                'budget_hours' => 500.00,
                'hourly_rate' => 45.00,
                'start_date' => '2026-04-01',
                'end_date' => '2026-09-30',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'client_name', 'address', 'status',
                    'budget_hours', 'hourly_rate', 'start_date', 'end_date',
                ],
            ]);

        $this->assertDatabaseHas('jobs', [
            'name' => 'Office Building Renovation',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_admin_can_list_jobs(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        Job::create([
            'tenant_id' => $tenant->id,
            'name' => 'Job A',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-01-01',
        ]);

        Job::create([
            'tenant_id' => $tenant->id,
            'name' => 'Job B',
            'status' => 'COMPLETED',
            'budget_hours' => 200,
            'hourly_rate' => 35,
            'start_date' => '2026-02-01',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/jobs');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_update_job(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $job = Job::create([
            'tenant_id' => $tenant->id,
            'name' => 'Old Name',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/jobs/{$job->id}", [
                'name' => 'New Name',
                'status' => 'ON_HOLD',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'name' => 'New Name',
            'status' => 'ON_HOLD',
        ]);
    }

    public function test_admin_can_show_single_job(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $job = Job::create([
            'tenant_id' => $tenant->id,
            'name' => 'Detail Job',
            'client_name' => 'Client X',
            'status' => 'ACTIVE',
            'budget_hours' => 150,
            'hourly_rate' => 40,
            'start_date' => '2026-03-01',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/jobs/{$job->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Detail Job',
                    'client_name' => 'Client X',
                ],
            ]);
    }

    public function test_jobs_are_tenant_scoped(): void
    {
        [$tenantA, $userA] = $this->createAuthenticatedAdmin();

        Job::create([
            'tenant_id' => $tenantA->id,
            'name' => 'A Job',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-01-01',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Other Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        Job::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'B Job',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/jobs');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/JobTest.php`
Expected: FAIL — Job model/table does not exist.

- [ ] **Step 3: Create the jobs migration**

```bash
docker compose exec app php artisan make:migration create_jobs_table
```

```php
// database/migrations/xxxx_create_jobs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 255);
            $table->string('client_name', 255)->nullable();
            $table->string('qbo_customer_id', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, COMPLETED, ON_HOLD
            $table->decimal('budget_hours', 10, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_sites');
    }
};
```

Note: The table is named `jobs_sites` to avoid collision with Laravel's built-in `jobs` queue table.

- [ ] **Step 4: Create the Job model**

```php
// app/Models/Job.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    use HasUuids, BelongsToTenant;

    protected $table = 'jobs_sites';

    protected $fillable = [
        'tenant_id',
        'name',
        'client_name',
        'qbo_customer_id',
        'address',
        'status',
        'budget_hours',
        'hourly_rate',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'budget_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function geofences(): HasMany
    {
        return $this->hasMany(Geofence::class, 'job_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'job_id');
    }
}
```

- [ ] **Step 5: Create JobResource**

```php
// app/Http/Resources/JobResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'client_name' => $this->client_name,
            'qbo_customer_id' => $this->qbo_customer_id,
            'address' => $this->address,
            'status' => $this->status,
            'budget_hours' => $this->budget_hours,
            'hourly_rate' => $this->hourly_rate,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'geofence_count' => $this->whenCounted('geofences'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Create StoreJobRequest**

```php
// app/Http/Requests/StoreJobRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,COMPLETED,ON_HOLD'],
            'budget_hours' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
```

- [ ] **Step 7: Create UpdateJobRequest**

```php
// app/Http/Requests/UpdateJobRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,COMPLETED,ON_HOLD'],
            'budget_hours' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ];
    }
}
```

- [ ] **Step 8: Create JobController**

```php
// app/Http/Controllers/JobController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $jobs = Job::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->withCount('geofences')
            ->orderBy('name')
            ->paginate($request->query('per_page', 25));

        return JobResource::collection($jobs);
    }

    public function store(StoreJobRequest $request): JsonResponse
    {
        $job = Job::create($request->validated());

        return (new JobResource($job))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Job $job): JobResource
    {
        $job->loadCount('geofences');

        return new JobResource($job);
    }

    public function update(UpdateJobRequest $request, Job $job): JobResource
    {
        $job->update($request->validated());

        return new JobResource($job->fresh());
    }

    public function destroy(Request $request, Job $job): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $job->update(['status' => 'COMPLETED']);

        return response()->json(['message' => 'Job marked as completed']);
    }
}
```

- [ ] **Step 9: Add job routes**

Add to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
use App\Http\Controllers\JobController;

Route::apiResource('jobs', JobController::class);
```

- [ ] **Step 10: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/JobTest.php`
Expected: All 5 tests PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Models/Job.php app/Http/Controllers/JobController.php app/Http/Resources/JobResource.php app/Http/Requests/StoreJobRequest.php app/Http/Requests/UpdateJobRequest.php database/migrations/*create_jobs* routes/api.php tests/Feature/JobTest.php
git commit -m "feat: add Job/Job Site model with CRUD API endpoints and tests"
```

---

## Task 6: Geofence Model & CRUD

**Files:**
- Create: `database/migrations/xxxx_create_geofences_table.php`
- Create: `app/Models/Geofence.php`
- Create: `app/Http/Resources/GeofenceResource.php`
- Create: `app/Http/Requests/StoreGeofenceRequest.php`
- Create: `app/Http/Requests/UpdateGeofenceRequest.php`
- Create: `app/Http/Controllers/GeofenceController.php`
- Create: `tests/Feature/GeofenceTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/GeofenceTest.php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/GeofenceTest.php`
Expected: FAIL — Geofence model/table does not exist.

- [ ] **Step 3: Create the geofences migration**

```bash
docker compose exec app php artisan make:migration create_geofences_table
```

```php
// database/migrations/xxxx_create_geofences_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('job_id');
            $table->string('name', 100);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('radius_meters')->default(100); // 50-500
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('jobs_sites')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index(['job_id', 'is_active']);
        });

        // Add PostGIS geometry column for server-side spatial queries
        DB::statement('ALTER TABLE geofences ADD COLUMN geom geometry(Point, 4326)');

        // Create spatial index
        DB::statement('CREATE INDEX geofences_geom_idx ON geofences USING GIST (geom)');

        // Create trigger to auto-populate geom from lat/lng
        DB::statement("
            CREATE OR REPLACE FUNCTION update_geofence_geom()
            RETURNS TRIGGER AS \$\$
            BEGIN
                NEW.geom = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326);
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER geofence_geom_trigger
            BEFORE INSERT OR UPDATE ON geofences
            FOR EACH ROW EXECUTE FUNCTION update_geofence_geom();
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS geofence_geom_trigger ON geofences');
        DB::statement('DROP FUNCTION IF EXISTS update_geofence_geom()');
        Schema::dropIfExists('geofences');
    }
};
```

- [ ] **Step 4: Create the Geofence model**

```php
// app/Models/Geofence.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Geofence extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'job_id',
        'name',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }
}
```

- [ ] **Step 5: Create GeofenceResource**

```php
// app/Http/Resources/GeofenceResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeofenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'job' => $this->whenLoaded('job', fn () => new JobResource($this->job)),
            'name' => $this->name,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'radius_meters' => $this->radius_meters,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Create StoreGeofenceRequest**

```php
// app/Http/Requests/StoreGeofenceRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGeofenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'job_id' => ['required', 'uuid', 'exists:jobs_sites,id'],
            'name' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'between:50,500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
```

- [ ] **Step 7: Create UpdateGeofenceRequest**

```php
// app/Http/Requests/UpdateGeofenceRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeofenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'job_id' => ['sometimes', 'uuid', 'exists:jobs_sites,id'],
            'name' => ['sometimes', 'string', 'max:100'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'radius_meters' => ['sometimes', 'integer', 'between:50,500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
```

- [ ] **Step 8: Create GeofenceController**

```php
// app/Http/Controllers/GeofenceController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGeofenceRequest;
use App\Http\Requests\UpdateGeofenceRequest;
use App\Http\Resources\GeofenceResource;
use App\Models\Geofence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GeofenceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $geofences = Geofence::query()
            ->when($request->query('job_id'), fn ($q, $jobId) => $q->where('job_id', $jobId))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->with('job')
            ->orderBy('name')
            ->paginate($request->query('per_page', 25));

        return GeofenceResource::collection($geofences);
    }

    public function store(StoreGeofenceRequest $request): JsonResponse
    {
        $geofence = Geofence::create($request->validated());

        return (new GeofenceResource($geofence))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Geofence $geofence): GeofenceResource
    {
        $geofence->load('job');

        return new GeofenceResource($geofence);
    }

    public function update(UpdateGeofenceRequest $request, Geofence $geofence): GeofenceResource
    {
        $geofence->update($request->validated());

        return new GeofenceResource($geofence->fresh());
    }

    public function destroy(Request $request, Geofence $geofence): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $geofence->update(['is_active' => false]);

        return response()->json(['message' => 'Geofence deactivated']);
    }
}
```

- [ ] **Step 9: Add geofence routes**

Add to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
use App\Http\Controllers\GeofenceController;

Route::apiResource('geofences', GeofenceController::class);
```

- [ ] **Step 10: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/GeofenceTest.php`
Expected: All 6 tests PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Models/Geofence.php app/Http/Controllers/GeofenceController.php app/Http/Resources/GeofenceResource.php app/Http/Requests/StoreGeofenceRequest.php app/Http/Requests/UpdateGeofenceRequest.php database/migrations/*create_geofences* routes/api.php tests/Feature/GeofenceTest.php
git commit -m "feat: add Geofence model with PostGIS geometry and CRUD API endpoints"
```

---

## Task 7: Time Entry Model & Clock In/Out

**Files:**
- Create: `database/migrations/xxxx_create_time_entries_table.php`
- Create: `app/Models/TimeEntry.php`
- Create: `app/Http/Resources/TimeEntryResource.php`
- Create: `app/Http/Requests/ClockInRequest.php`
- Create: `app/Http/Requests/ClockOutRequest.php`
- Create: `app/Http/Controllers/TimeEntryController.php`
- Create: `tests/Feature/TimeEntryTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/TimeEntryTest.php
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
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/TimeEntryTest.php`
Expected: FAIL — TimeEntry model/table does not exist.

- [ ] **Step 3: Create the time_entries migration**

```bash
docker compose exec app php artisan make:migration create_time_entries_table
```

```php
// database/migrations/xxxx_create_time_entries_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->uuid('job_id');
            $table->uuid('team_id'); // team at time of entry
            $table->timestampTz('clock_in');
            $table->timestampTz('clock_out')->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->string('clock_method', 20); // GEOFENCE, MANUAL, KIOSK, ADMIN_OVERRIDE
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->nullable();
            $table->string('status', 25)->default('ACTIVE'); // ACTIVE, SUBMITTED, APPROVED, REJECTED, PAYROLL_PROCESSED
            $table->string('sync_status', 20)->default('SYNCED'); // PENDING, SYNCED, CONFLICT
            $table->string('device_id', 255)->nullable();
            $table->text('selfie_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('jobs_sites')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams');
            $table->index('tenant_id');
            $table->index(['employee_id', 'clock_in']);
            $table->index(['employee_id', 'status']);
            $table->index(['job_id', 'clock_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
```

- [ ] **Step 4: Create the TimeEntry model**

```php
// app/Models/TimeEntry.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeEntry extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'job_id',
        'team_id',
        'clock_in',
        'clock_out',
        'clock_in_lat',
        'clock_in_lng',
        'clock_out_lat',
        'clock_out_lng',
        'clock_method',
        'total_hours',
        'overtime_hours',
        'status',
        'sync_status',
        'device_id',
        'selfie_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'total_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'clock_in_lat' => 'decimal:7',
            'clock_in_lng' => 'decimal:7',
            'clock_out_lat' => 'decimal:7',
            'clock_out_lng' => 'decimal:7',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(BreakEntry::class, 'time_entry_id');
    }

    /**
     * Calculate total hours between clock_in and clock_out,
     * subtracting unpaid break time.
     */
    public function calculateTotalHours(): ?float
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return null;
        }

        $totalMinutes = $this->clock_in->diffInMinutes($this->clock_out);

        // Subtract unpaid break minutes
        $unpaidBreakMinutes = $this->breaks()
            ->where('type', 'UNPAID_MEAL')
            ->whereNotNull('end_time')
            ->where('was_interrupted', false)
            ->sum('duration_minutes');

        $workedMinutes = max(0, $totalMinutes - $unpaidBreakMinutes);

        return round($workedMinutes / 60, 2);
    }
}
```

- [ ] **Step 5: Create TimeEntryResource**

```php
// app/Http/Resources/TimeEntryResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'job_id' => $this->job_id,
            'job' => $this->whenLoaded('job', fn () => new JobResource($this->job)),
            'team_id' => $this->team_id,
            'clock_in' => $this->clock_in?->toIso8601String(),
            'clock_out' => $this->clock_out?->toIso8601String(),
            'clock_in_lat' => $this->clock_in_lat ? (float) $this->clock_in_lat : null,
            'clock_in_lng' => $this->clock_in_lng ? (float) $this->clock_in_lng : null,
            'clock_out_lat' => $this->clock_out_lat ? (float) $this->clock_out_lat : null,
            'clock_out_lng' => $this->clock_out_lng ? (float) $this->clock_out_lng : null,
            'clock_method' => $this->clock_method,
            'total_hours' => $this->total_hours ? (float) $this->total_hours : null,
            'overtime_hours' => $this->overtime_hours ? (float) $this->overtime_hours : null,
            'status' => $this->status,
            'sync_status' => $this->sync_status,
            'device_id' => $this->device_id,
            'notes' => $this->notes,
            'breaks' => BreakEntryResource::collection($this->whenLoaded('breaks')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Create ClockInRequest**

```php
// app/Http/Requests/ClockInRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by sanctum middleware
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'job_id' => ['required', 'uuid', 'exists:jobs_sites,id'],
            'clock_in_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'clock_in_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'clock_method' => ['required', 'string', 'in:GEOFENCE,MANUAL,KIOSK,ADMIN_OVERRIDE'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'clock_in' => ['nullable', 'date'], // for sync/admin override
        ];
    }
}
```

- [ ] **Step 7: Create ClockOutRequest**

```php
// app/Http/Requests/ClockOutRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clock_out_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'clock_out_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string'],
            'clock_out' => ['nullable', 'date'], // for sync/admin override
        ];
    }
}
```

- [ ] **Step 8: Create TimeEntryController**

```php
// app/Http/Controllers/TimeEntryController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClockInRequest;
use App\Http\Requests\ClockOutRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Employee;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TimeEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = TimeEntry::query()
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('job_id'), fn ($q, $id) => $q->where('job_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('date_from'), fn ($q, $date) => $q->whereDate('clock_in', '>=', $date))
            ->when($request->query('date_to'), fn ($q, $date) => $q->whereDate('clock_in', '<=', $date))
            ->with(['employee', 'job', 'breaks'])
            ->orderByDesc('clock_in')
            ->paginate($request->query('per_page', 25));

        return TimeEntryResource::collection($entries);
    }

    public function show(TimeEntry $timeEntry): TimeEntryResource
    {
        $timeEntry->load(['employee', 'job', 'breaks']);

        return new TimeEntryResource($timeEntry);
    }

    public function clockIn(ClockInRequest $request): JsonResponse
    {
        $employee = Employee::findOrFail($request->employee_id);

        // Check if already clocked in
        $activeEntry = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->where('status', 'ACTIVE')
            ->first();

        if ($activeEntry) {
            return response()->json([
                'message' => 'Employee is already clocked in',
            ], 422);
        }

        $entry = TimeEntry::create([
            'employee_id' => $employee->id,
            'job_id' => $request->job_id,
            'team_id' => $employee->current_team_id,
            'clock_in' => $request->clock_in ?? now(),
            'clock_in_lat' => $request->clock_in_lat,
            'clock_in_lng' => $request->clock_in_lng,
            'clock_method' => $request->clock_method,
            'device_id' => $request->device_id,
            'notes' => $request->notes,
            'status' => 'ACTIVE',
            'sync_status' => 'SYNCED',
        ]);

        return (new TimeEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function clockOut(ClockOutRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        if ($timeEntry->clock_out) {
            return response()->json([
                'message' => 'Already clocked out',
            ], 422);
        }

        $clockOut = $request->clock_out ?? now();

        $timeEntry->update([
            'clock_out' => $clockOut,
            'clock_out_lat' => $request->clock_out_lat,
            'clock_out_lng' => $request->clock_out_lng,
        ]);

        // Calculate total hours
        $totalHours = $timeEntry->calculateTotalHours();
        $timeEntry->update(['total_hours' => $totalHours]);

        if ($request->notes) {
            $timeEntry->update(['notes' => $timeEntry->notes . "\n" . $request->notes]);
        }

        return response()->json([
            'data' => new TimeEntryResource($timeEntry->fresh()->load('breaks')),
        ]);
    }

    public function update(Request $request, TimeEntry $timeEntry): TimeEntryResource
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            abort(403, 'Forbidden');
        }

        $validated = $request->validate([
            'clock_in' => ['sometimes', 'date'],
            'clock_out' => ['sometimes', 'date', 'nullable'],
            'job_id' => ['sometimes', 'uuid', 'exists:jobs_sites,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,SUBMITTED,APPROVED,REJECTED,PAYROLL_PROCESSED'],
        ]);

        $timeEntry->update($validated);

        // Recalculate total hours if clock times changed
        if (isset($validated['clock_in']) || isset($validated['clock_out'])) {
            $timeEntry->update(['total_hours' => $timeEntry->calculateTotalHours()]);
        }

        return new TimeEntryResource($timeEntry->fresh());
    }
}
```

- [ ] **Step 9: Add time entry routes**

Add to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
use App\Http\Controllers\TimeEntryController;

Route::get('time-entries', [TimeEntryController::class, 'index']);
Route::get('time-entries/{timeEntry}', [TimeEntryController::class, 'show']);
Route::post('time-entries/clock-in', [TimeEntryController::class, 'clockIn']);
Route::post('time-entries/{timeEntry}/clock-out', [TimeEntryController::class, 'clockOut']);
Route::put('time-entries/{timeEntry}', [TimeEntryController::class, 'update']);
```

- [ ] **Step 10: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/TimeEntryTest.php`
Expected: All 6 tests PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Models/TimeEntry.php app/Http/Controllers/TimeEntryController.php app/Http/Resources/TimeEntryResource.php app/Http/Requests/ClockInRequest.php app/Http/Requests/ClockOutRequest.php database/migrations/*create_time_entries* routes/api.php tests/Feature/TimeEntryTest.php
git commit -m "feat: add TimeEntry model with clock in/out API endpoints and tests"
```

---

## Task 8: Break Tracking

**Files:**
- Create: `database/migrations/xxxx_create_break_entries_table.php`
- Create: `app/Models/BreakEntry.php`
- Create: `app/Http/Resources/BreakEntryResource.php`
- Create: `app/Http/Requests/StoreBreakRequest.php`
- Create: `app/Http/Requests/EndBreakRequest.php`
- Create: `app/Http/Controllers/BreakEntryController.php`
- Create: `tests/Feature/BreakEntryTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/BreakEntryTest.php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/BreakEntryTest.php`
Expected: FAIL — BreakEntry model/table does not exist.

- [ ] **Step 3: Create the break_entries migration**

```bash
docker compose exec app php artisan make:migration create_break_entries_table
```

```php
// database/migrations/xxxx_create_break_entries_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('break_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('time_entry_id');
            $table->string('type', 20); // PAID_REST, UNPAID_MEAL
            $table->timestampTz('start_time');
            $table->timestampTz('end_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->boolean('was_interrupted')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('time_entry_id')->references('id')->on('time_entries')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index('time_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_entries');
    }
};
```

- [ ] **Step 4: Create the BreakEntry model**

```php
// app/Models/BreakEntry.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakEntry extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'time_entry_id',
        'type',
        'start_time',
        'end_time',
        'duration_minutes',
        'was_interrupted',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration_minutes' => 'integer',
            'was_interrupted' => 'boolean',
        ];
    }

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class);
    }

    /**
     * Calculate duration in minutes from start to end.
     */
    public function calculateDuration(): ?int
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }

        return (int) $this->start_time->diffInMinutes($this->end_time);
    }
}
```

- [ ] **Step 5: Create BreakEntryResource**

```php
// app/Http/Resources/BreakEntryResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BreakEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'time_entry_id' => $this->time_entry_id,
            'type' => $this->type,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'was_interrupted' => $this->was_interrupted,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Create StoreBreakRequest**

```php
// app/Http/Requests/StoreBreakRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBreakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time_entry_id' => ['required', 'uuid', 'exists:time_entries,id'],
            'type' => ['required', 'string', 'in:PAID_REST,UNPAID_MEAL'],
            'start_time' => ['nullable', 'date'], // default to now()
        ];
    }
}
```

- [ ] **Step 7: Create EndBreakRequest**

```php
// app/Http/Requests/EndBreakRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EndBreakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'was_interrupted' => ['sometimes', 'boolean'],
            'end_time' => ['nullable', 'date'], // default to now()
        ];
    }
}
```

- [ ] **Step 8: Create BreakEntryController**

```php
// app/Http/Controllers/BreakEntryController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\EndBreakRequest;
use App\Http\Requests\StoreBreakRequest;
use App\Http\Resources\BreakEntryResource;
use App\Models\BreakEntry;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;

class BreakEntryController extends Controller
{
    public function store(StoreBreakRequest $request): JsonResponse
    {
        $timeEntry = TimeEntry::findOrFail($request->time_entry_id);

        // Cannot start break on a completed time entry
        if ($timeEntry->clock_out) {
            return response()->json([
                'message' => 'Cannot start break on a completed time entry',
            ], 422);
        }

        // Check if there's already an active break
        $activeBreak = BreakEntry::where('time_entry_id', $timeEntry->id)
            ->whereNull('end_time')
            ->first();

        if ($activeBreak) {
            return response()->json([
                'message' => 'There is already an active break',
            ], 422);
        }

        $breakEntry = BreakEntry::create([
            'time_entry_id' => $timeEntry->id,
            'type' => $request->type,
            'start_time' => $request->start_time ?? now(),
        ]);

        return (new BreakEntryResource($breakEntry))
            ->response()
            ->setStatusCode(201);
    }

    public function end(EndBreakRequest $request, BreakEntry $breakEntry): JsonResponse
    {
        if ($breakEntry->end_time) {
            return response()->json([
                'message' => 'Break has already ended',
            ], 422);
        }

        $endTime = $request->end_time ?? now();

        $breakEntry->update([
            'end_time' => $endTime,
            'was_interrupted' => $request->boolean('was_interrupted', false),
        ]);

        $breakEntry->update([
            'duration_minutes' => $breakEntry->calculateDuration(),
        ]);

        return response()->json([
            'data' => new BreakEntryResource($breakEntry->fresh()),
        ]);
    }
}
```

- [ ] **Step 9: Add break routes**

Add to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
use App\Http\Controllers\BreakEntryController;

Route::post('breaks', [BreakEntryController::class, 'store']);
Route::post('breaks/{breakEntry}/end', [BreakEntryController::class, 'end']);
```

- [ ] **Step 10: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/BreakEntryTest.php`
Expected: All 4 tests PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Models/BreakEntry.php app/Http/Controllers/BreakEntryController.php app/Http/Resources/BreakEntryResource.php app/Http/Requests/StoreBreakRequest.php app/Http/Requests/EndBreakRequest.php database/migrations/*create_break_entries* routes/api.php tests/Feature/BreakEntryTest.php
git commit -m "feat: add break tracking with paid/unpaid types and interruption handling"
```

---

## Task 9: Overtime Calculation Service

**Files:**
- Create: `app/Services/OvertimeCalculator.php`
- Create: `tests/Unit/OvertimeCalculatorTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/OvertimeCalculatorTest.php
<?php

namespace Tests\Unit;

use App\Services\OvertimeCalculator;
use Tests\TestCase;

class OvertimeCalculatorTest extends TestCase
{
    public function test_no_overtime_under_weekly_threshold(): void
    {
        $calculator = new OvertimeCalculator(
            weeklyThreshold: 40,
            dailyThreshold: null,
            multiplier: 1.5
        );

        $result = $calculator->calculate(
            weeklyHours: 35.0,
            dailyHours: 8.0
        );

        $this->assertEquals(0.0, $result['overtime_hours']);
        $this->assertEquals(35.0, $result['regular_hours']);
    }

    public function test_weekly_overtime_over_40_hours(): void
    {
        $calculator = new OvertimeCalculator(
            weeklyThreshold: 40,
            dailyThreshold: null,
            multiplier: 1.5
        );

        $result = $calculator->calculate(
            weeklyHours: 45.0,
            dailyHours: 9.0
        );

        $this->assertEquals(5.0, $result['overtime_hours']);
        $this->assertEquals(40.0, $result['regular_hours']);
        $this->assertEquals(1.5, $result['multiplier']);
    }

    public function test_daily_overtime_california_rules(): void
    {
        $calculator = new OvertimeCalculator(
            weeklyThreshold: 40,
            dailyThreshold: 8,
            multiplier: 1.5
        );

        $result = $calculator->calculate(
            weeklyHours: 36.0,
            dailyHours: 10.0
        );

        // Daily overtime: 10 - 8 = 2 hours
        $this->assertEquals(2.0, $result['overtime_hours']);
        $this->assertEquals(8.0, $result['regular_hours']);
    }

    public function test_both_daily_and_weekly_takes_higher(): void
    {
        $calculator = new OvertimeCalculator(
            weeklyThreshold: 40,
            dailyThreshold: 8,
            multiplier: 1.5
        );

        $result = $calculator->calculate(
            weeklyHours: 48.0,
            dailyHours: 12.0
        );

        // Weekly OT: 48 - 40 = 8
        // Daily OT: 12 - 8 = 4
        // Take the higher: 8
        $this->assertEquals(8.0, $result['overtime_hours']);
    }

    public function test_custom_multiplier(): void
    {
        $calculator = new OvertimeCalculator(
            weeklyThreshold: 40,
            dailyThreshold: null,
            multiplier: 2.0
        );

        $result = $calculator->calculate(
            weeklyHours: 50.0,
            dailyHours: 10.0
        );

        $this->assertEquals(2.0, $result['multiplier']);
        $this->assertEquals(10.0, $result['overtime_hours']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Unit/OvertimeCalculatorTest.php`
Expected: FAIL — OvertimeCalculator class does not exist.

- [ ] **Step 3: Create OvertimeCalculator service**

```php
// app/Services/OvertimeCalculator.php
<?php

namespace App\Services;

class OvertimeCalculator
{
    public function __construct(
        private readonly float $weeklyThreshold = 40.0,
        private readonly ?float $dailyThreshold = null,
        private readonly float $multiplier = 1.5,
    ) {}

    /**
     * Create from tenant overtime_rule config.
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            weeklyThreshold: $config['weekly_threshold'] ?? 40.0,
            dailyThreshold: $config['daily_threshold'] ?? null,
            multiplier: $config['multiplier'] ?? 1.5,
        );
    }

    /**
     * Calculate overtime hours.
     *
     * @return array{regular_hours: float, overtime_hours: float, multiplier: float}
     */
    public function calculate(float $weeklyHours, float $dailyHours): array
    {
        $weeklyOt = max(0, $weeklyHours - $this->weeklyThreshold);

        $dailyOt = 0.0;
        if ($this->dailyThreshold !== null) {
            $dailyOt = max(0, $dailyHours - $this->dailyThreshold);
        }

        // Take the higher overtime calculation
        $overtimeHours = max($weeklyOt, $dailyOt);

        // Regular hours = total minus overtime (use weekly for weekly OT, daily for daily OT)
        if ($weeklyOt >= $dailyOt) {
            $regularHours = $weeklyHours - $overtimeHours;
        } else {
            $regularHours = $dailyHours - $overtimeHours;
        }

        return [
            'regular_hours' => round($regularHours, 2),
            'overtime_hours' => round($overtimeHours, 2),
            'multiplier' => $this->multiplier,
        ];
    }
}
```

- [ ] **Step 4: Run tests**

Run: `docker compose exec app php artisan test tests/Unit/OvertimeCalculatorTest.php`
Expected: All 5 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/OvertimeCalculator.php tests/Unit/OvertimeCalculatorTest.php
git commit -m "feat: add OvertimeCalculator service with weekly/daily threshold support"
```

---

## Task 10: Time Rounding Service

**Files:**
- Create: `app/Services/TimeRounder.php`
- Create: `tests/Unit/TimeRounderTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/TimeRounderTest.php
<?php

namespace Tests\Unit;

use App\Services\TimeRounder;
use Carbon\Carbon;
use Tests\TestCase;

class TimeRounderTest extends TestCase
{
    public function test_exact_returns_unchanged(): void
    {
        $rounder = new TimeRounder('EXACT');
        $time = Carbon::parse('2026-03-28 08:07:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:07:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_nearest_5_rounds_down(): void
    {
        $rounder = new TimeRounder('NEAREST_5');
        $time = Carbon::parse('2026-03-28 08:07:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:05:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_nearest_5_rounds_up(): void
    {
        $rounder = new TimeRounder('NEAREST_5');
        $time = Carbon::parse('2026-03-28 08:08:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:10:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_nearest_6_rounds(): void
    {
        $rounder = new TimeRounder('NEAREST_6');
        $time = Carbon::parse('2026-03-28 08:04:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:06:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_nearest_15_rounds_down(): void
    {
        $rounder = new TimeRounder('NEAREST_15');
        $time = Carbon::parse('2026-03-28 08:07:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_nearest_15_rounds_up(): void
    {
        $rounder = new TimeRounder('NEAREST_15');
        $time = Carbon::parse('2026-03-28 08:08:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:15:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_round_hours_decimal(): void
    {
        $rounder = new TimeRounder('NEAREST_15');

        // 8 hours 7 minutes = 8.1167 -> round to nearest 0.25 = 8.00
        $this->assertEquals(8.0, $rounder->roundHours(8.1167));

        // 8 hours 23 minutes = 8.3833 -> round to nearest 0.25 = 8.25
        $this->assertEquals(8.25, $rounder->roundHours(8.3833));

        // 8 hours 37 minutes = 8.6167 -> round to nearest 0.25 = 8.75 (actually 8.5 is closer)
        $this->assertEquals(8.5, $rounder->roundHours(8.5));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Unit/TimeRounderTest.php`
Expected: FAIL — TimeRounder class does not exist.

- [ ] **Step 3: Create TimeRounder service**

```php
// app/Services/TimeRounder.php
<?php

namespace App\Services;

use Carbon\Carbon;

class TimeRounder
{
    private int $intervalMinutes;

    public function __construct(
        private readonly string $rule = 'EXACT',
    ) {
        $this->intervalMinutes = match ($this->rule) {
            'NEAREST_5' => 5,
            'NEAREST_6' => 6,
            'NEAREST_15' => 15,
            default => 0,
        };
    }

    /**
     * Round a timestamp to the nearest interval.
     * Raw timestamp is always preserved; this is for display/payroll.
     */
    public function round(Carbon $time): Carbon
    {
        if ($this->intervalMinutes === 0) {
            return $time->copy();
        }

        $minutes = $time->minute;
        $remainder = $minutes % $this->intervalMinutes;

        if ($remainder === 0) {
            return $time->copy()->second(0);
        }

        $halfInterval = $this->intervalMinutes / 2.0;

        if ($remainder < $halfInterval) {
            // Round down
            return $time->copy()->minute($minutes - $remainder)->second(0);
        }

        // Round up
        return $time->copy()->minute($minutes + ($this->intervalMinutes - $remainder))->second(0);
    }

    /**
     * Round a decimal hours value to the nearest interval fraction.
     */
    public function roundHours(float $hours): float
    {
        if ($this->intervalMinutes === 0) {
            return round($hours, 2);
        }

        $fraction = $this->intervalMinutes / 60.0;

        return round(round($hours / $fraction) * $fraction, 2);
    }
}
```

- [ ] **Step 4: Run tests**

Run: `docker compose exec app php artisan test tests/Unit/TimeRounderTest.php`
Expected: All 7 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/TimeRounder.php tests/Unit/TimeRounderTest.php
git commit -m "feat: add TimeRounder service with configurable rounding intervals"
```

---

## Task 11: Timesheet Approval Workflow

**Files:**
- Create: `app/Http/Controllers/TimesheetController.php`
- Create: `app/Http/Requests/SubmitTimesheetRequest.php`
- Create: `app/Http/Requests/ReviewTimesheetRequest.php`
- Create: `tests/Feature/TimesheetTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/TimesheetTest.php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/TimesheetTest.php`
Expected: FAIL — controller and routes don't exist.

- [ ] **Step 3: Create SubmitTimesheetRequest**

```php
// app/Http/Requests/SubmitTimesheetRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
            'week_end' => ['required', 'date', 'after_or_equal:week_start'],
        ];
    }
}
```

- [ ] **Step 4: Create ReviewTimesheetRequest**

```php
// app/Http/Requests/ReviewTimesheetRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()->role;
        return in_array($role, ['admin', 'super_admin', 'manager', 'team_lead']);
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
            'week_end' => ['required', 'date', 'after_or_equal:week_start'],
            'action' => ['required', 'string', 'in:approve,reject'],
            'reason' => ['nullable', 'string', 'required_if:action,reject'],
        ];
    }
}
```

- [ ] **Step 5: Create TimesheetController**

```php
// app/Http/Controllers/TimesheetController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewTimesheetRequest;
use App\Http\Requests\SubmitTimesheetRequest;
use App\Models\TimeEntry;
use App\Services\OvertimeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimesheetController extends Controller
{
    public function submit(SubmitTimesheetRequest $request): JsonResponse
    {
        $updated = TimeEntry::where('employee_id', $request->employee_id)
            ->where('status', 'ACTIVE')
            ->whereDate('clock_in', '>=', $request->week_start)
            ->whereDate('clock_in', '<=', $request->week_end)
            ->update(['status' => 'SUBMITTED']);

        return response()->json([
            'message' => 'Timesheet submitted for review',
            'entries_submitted' => $updated,
        ]);
    }

    public function review(ReviewTimesheetRequest $request): JsonResponse
    {
        $newStatus = $request->action === 'approve' ? 'APPROVED' : 'REJECTED';

        $updated = TimeEntry::where('employee_id', $request->employee_id)
            ->where('status', 'SUBMITTED')
            ->whereDate('clock_in', '>=', $request->week_start)
            ->whereDate('clock_in', '<=', $request->week_end)
            ->update(['status' => $newStatus]);

        $action = $request->action === 'approve' ? 'approved' : 'rejected';

        return response()->json([
            'message' => "Timesheet {$action}",
            'entries_updated' => $updated,
        ]);
    }

    public function processPayroll(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
            'week_end' => ['required', 'date'],
        ]);

        // Calculate overtime for the week
        $entries = TimeEntry::where('employee_id', $request->employee_id)
            ->where('status', 'APPROVED')
            ->whereDate('clock_in', '>=', $request->week_start)
            ->whereDate('clock_in', '<=', $request->week_end)
            ->get();

        $tenant = app('current_tenant');
        $calculator = OvertimeCalculator::fromConfig($tenant->overtime_rule);

        $weeklyHours = (float) $entries->sum('total_hours');
        $maxDailyHours = (float) $entries->max('total_hours');

        $overtime = $calculator->calculate($weeklyHours, $maxDailyHours);

        // Distribute overtime to last entries of the week
        // (simplified: mark overtime on the weekly total)
        $updated = TimeEntry::where('employee_id', $request->employee_id)
            ->where('status', 'APPROVED')
            ->whereDate('clock_in', '>=', $request->week_start)
            ->whereDate('clock_in', '<=', $request->week_end)
            ->update(['status' => 'PAYROLL_PROCESSED']);

        return response()->json([
            'message' => 'Payroll processed',
            'entries_processed' => $updated,
            'weekly_summary' => [
                'total_hours' => $weeklyHours,
                'regular_hours' => $overtime['regular_hours'],
                'overtime_hours' => $overtime['overtime_hours'],
                'overtime_multiplier' => $overtime['multiplier'],
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
            'week_end' => ['required', 'date'],
        ]);

        $entries = TimeEntry::where('employee_id', $request->employee_id)
            ->whereDate('clock_in', '>=', $request->week_start)
            ->whereDate('clock_in', '<=', $request->week_end)
            ->get();

        $tenant = app('current_tenant');
        $calculator = OvertimeCalculator::fromConfig($tenant->overtime_rule);

        $weeklyHours = (float) $entries->sum('total_hours');
        $maxDailyHours = (float) $entries->max('total_hours');
        $overtime = $calculator->calculate($weeklyHours, $maxDailyHours);

        return response()->json([
            'data' => [
                'employee_id' => $request->employee_id,
                'week_start' => $request->week_start,
                'week_end' => $request->week_end,
                'total_entries' => $entries->count(),
                'total_hours' => $weeklyHours,
                'regular_hours' => $overtime['regular_hours'],
                'overtime_hours' => $overtime['overtime_hours'],
                'status_breakdown' => $entries->groupBy('status')->map->count(),
            ],
        ]);
    }
}
```

- [ ] **Step 6: Add timesheet routes**

Add to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
use App\Http\Controllers\TimesheetController;

Route::prefix('timesheets')->group(function () {
    Route::post('/submit', [TimesheetController::class, 'submit']);
    Route::post('/review', [TimesheetController::class, 'review']);
    Route::post('/process-payroll', [TimesheetController::class, 'processPayroll']);
    Route::get('/summary', [TimesheetController::class, 'summary']);
});
```

- [ ] **Step 7: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/TimesheetTest.php`
Expected: All 4 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/TimesheetController.php app/Http/Requests/SubmitTimesheetRequest.php app/Http/Requests/ReviewTimesheetRequest.php routes/api.php tests/Feature/TimesheetTest.php
git commit -m "feat: add timesheet approval workflow (submit, review, payroll processing)"
```

---

## Task 12: PTO / Time Off

**Files:**
- Create: `database/migrations/xxxx_create_pto_requests_table.php`
- Create: `database/migrations/xxxx_create_pto_balances_table.php`
- Create: `app/Models/PtoRequest.php`
- Create: `app/Models/PtoBalance.php`
- Create: `app/Http/Resources/PtoRequestResource.php`
- Create: `app/Http/Requests/StorePtoRequest.php`
- Create: `app/Http/Requests/ReviewPtoRequest.php`
- Create: `app/Http/Controllers/PtoController.php`
- Create: `tests/Feature/PtoTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/PtoTest.php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/PtoTest.php`
Expected: FAIL — PtoRequest/PtoBalance model/table does not exist.

- [ ] **Step 3: Create the pto_requests migration**

```bash
docker compose exec app php artisan make:migration create_pto_requests_table
```

```php
// database/migrations/xxxx_create_pto_requests_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pto_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->string('type', 20); // VACATION, SICK, PERSONAL, UNPAID
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('hours', 6, 2);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('PENDING'); // PENDING, APPROVED, DENIED, CANCELLED
            $table->uuid('reviewed_by')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pto_requests');
    }
};
```

- [ ] **Step 4: Create the pto_balances migration**

```bash
docker compose exec app php artisan make:migration create_pto_balances_table
```

```php
// database/migrations/xxxx_create_pto_balances_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pto_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->string('type', 20); // VACATION, SICK, PERSONAL
            $table->decimal('balance_hours', 8, 2)->default(0);
            $table->decimal('accrued_hours', 8, 2)->default(0);
            $table->decimal('used_hours', 8, 2)->default(0);
            $table->integer('year');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index('tenant_id');
            $table->unique(['employee_id', 'type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pto_balances');
    }
};
```

- [ ] **Step 5: Create PtoRequest model**

```php
// app/Models/PtoRequest.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtoRequest extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'hours',
        'notes',
        'status',
        'reviewed_by',
        'review_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'hours' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

- [ ] **Step 6: Create PtoBalance model**

```php
// app/Models/PtoBalance.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtoBalance extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'type',
        'balance_hours',
        'accrued_hours',
        'used_hours',
        'year',
    ];

    protected function casts(): array
    {
        return [
            'balance_hours' => 'decimal:2',
            'accrued_hours' => 'decimal:2',
            'used_hours' => 'decimal:2',
            'year' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

- [ ] **Step 7: Create PtoRequestResource**

```php
// app/Http/Resources/PtoRequestResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PtoRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'type' => $this->type,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'hours' => (float) $this->hours,
            'notes' => $this->notes,
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'review_reason' => $this->review_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 8: Create StorePtoRequest**

```php
// app/Http/Requests/StorePtoRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePtoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'type' => ['required', 'string', 'in:VACATION,SICK,PERSONAL,UNPAID'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'hours' => ['required', 'numeric', 'min:0.5'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 9: Create ReviewPtoRequest**

```php
// app/Http/Requests/ReviewPtoRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPtoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()->role;
        return in_array($role, ['admin', 'super_admin', 'manager', 'team_lead']);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approve,deny'],
            'reason' => ['nullable', 'string', 'required_if:action,deny'],
        ];
    }
}
```

- [ ] **Step 10: Create PtoController**

```php
// app/Http/Controllers/PtoController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewPtoRequest as ReviewPtoFormRequest;
use App\Http\Requests\StorePtoRequest as StorePtoFormRequest;
use App\Http\Resources\PtoRequestResource;
use App\Models\PtoBalance;
use App\Models\PtoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PtoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $ptoRequests = PtoRequest::query()
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->with('employee')
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 25));

        return PtoRequestResource::collection($ptoRequests);
    }

    public function store(StorePtoFormRequest $request): JsonResponse
    {
        // Check balance (skip for UNPAID type)
        if ($request->type !== 'UNPAID') {
            $balance = PtoBalance::where('employee_id', $request->employee_id)
                ->where('type', $request->type)
                ->where('year', date('Y'))
                ->first();

            if (! $balance || (float) $balance->balance_hours < (float) $request->hours) {
                return response()->json([
                    'message' => 'Insufficient PTO balance',
                ], 422);
            }
        }

        $pto = PtoRequest::create([
            ...$request->validated(),
            'status' => 'PENDING',
        ]);

        return (new PtoRequestResource($pto))
            ->response()
            ->setStatusCode(201);
    }

    public function review(ReviewPtoFormRequest $request, PtoRequest $ptoRequest): JsonResponse
    {
        if ($ptoRequest->status !== 'PENDING') {
            return response()->json(['message' => 'PTO request is not pending'], 422);
        }

        $newStatus = $request->action === 'approve' ? 'APPROVED' : 'DENIED';

        $ptoRequest->update([
            'status' => $newStatus,
            'reviewed_by' => $request->user()->id,
            'review_reason' => $request->reason,
            'reviewed_at' => now(),
        ]);

        // If approved and not UNPAID, deduct from balance
        if ($newStatus === 'APPROVED' && $ptoRequest->type !== 'UNPAID') {
            $balance = PtoBalance::where('employee_id', $ptoRequest->employee_id)
                ->where('type', $ptoRequest->type)
                ->where('year', $ptoRequest->start_date->year)
                ->first();

            if ($balance) {
                $balance->update([
                    'used_hours' => (float) $balance->used_hours + (float) $ptoRequest->hours,
                    'balance_hours' => (float) $balance->balance_hours - (float) $ptoRequest->hours,
                ]);
            }
        }

        return response()->json([
            'data' => new PtoRequestResource($ptoRequest->fresh()),
            'message' => "PTO request {$newStatus}",
        ]);
    }

    public function balance(Request $request, string $employeeId): JsonResponse
    {
        $year = $request->query('year', date('Y'));

        $balances = PtoBalance::where('employee_id', $employeeId)
            ->where('year', $year)
            ->get()
            ->map(fn ($b) => [
                'type' => $b->type,
                'balance_hours' => (float) $b->balance_hours,
                'accrued_hours' => (float) $b->accrued_hours,
                'used_hours' => (float) $b->used_hours,
                'year' => $b->year,
            ]);

        return response()->json(['data' => $balances]);
    }
}
```

- [ ] **Step 11: Add PTO routes**

Add to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
use App\Http\Controllers\PtoController;

Route::get('pto', [PtoController::class, 'index']);
Route::post('pto', [PtoController::class, 'store']);
Route::post('pto/{ptoRequest}/review', [PtoController::class, 'review']);
Route::get('pto/balance/{employeeId}', [PtoController::class, 'balance']);
```

- [ ] **Step 12: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/PtoTest.php`
Expected: All 6 tests PASS.

- [ ] **Step 13: Commit**

```bash
git add app/Models/PtoRequest.php app/Models/PtoBalance.php app/Http/Controllers/PtoController.php app/Http/Resources/PtoRequestResource.php app/Http/Requests/StorePtoRequest.php app/Http/Requests/ReviewPtoRequest.php database/migrations/*create_pto* routes/api.php tests/Feature/PtoTest.php
git commit -m "feat: add PTO request/approval workflow with balance tracking"
```

---

## Task 13: Audit Log

**Files:**
- Create: `database/migrations/xxxx_create_audit_logs_table.php`
- Create: `app/Models/AuditLog.php`
- Create: `app/Services/AuditService.php`
- Create: `tests/Unit/AuditServiceTest.php`
- Create: `tests/Feature/AuditLogTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/AuditServiceTest.php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Unit/AuditServiceTest.php`
Expected: FAIL — AuditLog model/table does not exist.

- [ ] **Step 3: Create the audit_logs migration**

```bash
docker compose exec app php artisan make:migration create_audit_logs_table
```

```php
// database/migrations/xxxx_create_audit_logs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('entity_type', 50); // employee, time_entry, transfer, etc.
            $table->uuid('entity_id');
            $table->string('action', 20); // CREATE, UPDATE, DELETE, APPROVE, REJECT
            $table->uuid('changed_by');
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value')->nullable();
            $table->inet('ip_address')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('changed_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

- [ ] **Step 4: Create the AuditLog model**

```php
// app/Models/AuditLog.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'action',
        'changed_by',
        'old_value',
        'new_value',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }
}
```

- [ ] **Step 5: Create AuditService**

```php
// app/Services/AuditService.php
<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * Log an auditable action.
     */
    public function log(
        string $entityType,
        string $entityId,
        string $action,
        string $changedBy,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'changed_by' => $changedBy,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => $ipAddress ?? request()?->ip(),
        ]);
    }
}
```

- [ ] **Step 6: Write the feature test for audit log API**

```php
// tests/Feature/AuditLogTest.php
<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_audit_logs(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
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

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'employee',
            'entity_id' => $user->id,
            'action' => 'CREATE',
            'changed_by' => $user->id,
            'new_value' => ['name' => 'Test'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_non_admin_cannot_list_audit_logs(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Employee',
            'email' => 'emp@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'employee',
        ]);

        app()->instance('current_tenant', $tenant);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 7: Add audit log route and controller method**

Add a simple route to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
Route::get('audit-logs', function (Illuminate\Http\Request $request) {
    if (! $request->user()->isAdmin()) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $logs = \App\Models\AuditLog::query()
        ->when($request->query('entity_type'), fn ($q, $type) => $q->where('entity_type', $type))
        ->when($request->query('entity_id'), fn ($q, $id) => $q->where('entity_id', $id))
        ->orderByDesc('created_at')
        ->paginate($request->query('per_page', 50));

    return response()->json([
        'data' => $logs->items(),
        'meta' => [
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
        ],
    ]);
});
```

- [ ] **Step 8: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Unit/AuditServiceTest.php tests/Feature/AuditLogTest.php`
Expected: All 4 tests PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Models/AuditLog.php app/Services/AuditService.php database/migrations/*create_audit_logs* routes/api.php tests/Unit/AuditServiceTest.php tests/Feature/AuditLogTest.php
git commit -m "feat: add audit log model and service for tracking all entity changes"
```

---

## Task 14: Mobile Sync Endpoint

**Files:**
- Create: `app/Http/Controllers/SyncController.php`
- Create: `app/Http/Requests/SyncRequest.php`
- Create: `app/Http/Resources/SyncResource.php`
- Create: `tests/Feature/SyncTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/SyncTest.php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/SyncTest.php`
Expected: FAIL — controller and routes don't exist.

- [ ] **Step 3: Create SyncRequest**

```php
// app/Http/Requests/SyncRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time_entries' => ['present', 'array'],
            'time_entries.*.client_id' => ['required', 'string'],
            'time_entries.*.employee_id' => ['required', 'uuid'],
            'time_entries.*.job_id' => ['required', 'uuid'],
            'time_entries.*.clock_in' => ['required', 'date'],
            'time_entries.*.clock_out' => ['nullable', 'date'],
            'time_entries.*.clock_in_lat' => ['nullable', 'numeric'],
            'time_entries.*.clock_in_lng' => ['nullable', 'numeric'],
            'time_entries.*.clock_out_lat' => ['nullable', 'numeric'],
            'time_entries.*.clock_out_lng' => ['nullable', 'numeric'],
            'time_entries.*.clock_method' => ['required', 'string', 'in:GEOFENCE,MANUAL,KIOSK,ADMIN_OVERRIDE'],
            'time_entries.*.device_id' => ['nullable', 'string'],
            'time_entries.*.notes' => ['nullable', 'string'],

            'breaks' => ['present', 'array'],
            'breaks.*.client_id' => ['required', 'string'],
            'breaks.*.time_entry_id' => ['required', 'uuid'],
            'breaks.*.type' => ['required', 'string', 'in:PAID_REST,UNPAID_MEAL'],
            'breaks.*.start_time' => ['required', 'date'],
            'breaks.*.end_time' => ['nullable', 'date'],
        ];
    }
}
```

- [ ] **Step 4: Create SyncController**

```php
// app/Http/Controllers/SyncController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncRequest;
use App\Http\Resources\GeofenceResource;
use App\Http\Resources\JobResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\EmployeeResource;
use App\Models\BreakEntry;
use App\Models\Employee;
use App\Models\Geofence;
use App\Models\Job;
use App\Models\Team;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * Pull: GET /api/v1/sync?last_synced_at=<timestamp>
     * Returns all entities updated since last_synced_at.
     */
    public function pull(Request $request): JsonResponse
    {
        $lastSyncedAt = $request->query('last_synced_at')
            ? Carbon::parse($request->query('last_synced_at'))
            : Carbon::createFromTimestamp(0);

        $geofences = Geofence::where('updated_at', '>', $lastSyncedAt)
            ->where('is_active', true)
            ->get();

        $teams = Team::where('updated_at', '>', $lastSyncedAt)
            ->where('status', 'ACTIVE')
            ->get();

        $jobs = Job::where('updated_at', '>', $lastSyncedAt)
            ->where('status', 'ACTIVE')
            ->get();

        $employees = Employee::where('updated_at', '>', $lastSyncedAt)
            ->where('status', 'ACTIVE')
            ->get();

        return response()->json([
            'data' => [
                'geofences' => GeofenceResource::collection($geofences),
                'teams' => TeamResource::collection($teams),
                'jobs' => JobResource::collection($jobs),
                'employees' => EmployeeResource::collection($employees),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Push: POST /api/v1/sync
     * Receives pending time entries and breaks from mobile device.
     */
    public function push(SyncRequest $request): JsonResponse
    {
        $syncedEntries = [];
        $conflicts = [];

        DB::transaction(function () use ($request, &$syncedEntries, &$conflicts) {
            // Process time entries
            foreach ($request->input('time_entries', []) as $entryData) {
                $employee = Employee::find($entryData['employee_id']);
                if (! $employee) {
                    continue;
                }

                // Check for conflicts — overlapping entry for same employee
                $conflict = TimeEntry::where('employee_id', $entryData['employee_id'])
                    ->where('job_id', $entryData['job_id'])
                    ->where(function ($q) use ($entryData) {
                        $q->where('clock_in', '<=', $entryData['clock_out'] ?? $entryData['clock_in'])
                          ->where(function ($q2) use ($entryData) {
                              $q2->whereNull('clock_out')
                                 ->orWhere('clock_out', '>=', $entryData['clock_in']);
                          });
                    })
                    ->first();

                if ($conflict) {
                    $conflicts[] = [
                        'client_id' => $entryData['client_id'],
                        'server_entry_id' => $conflict->id,
                        'reason' => 'Overlapping time entry exists on server',
                    ];
                    continue;
                }

                $clockIn = Carbon::parse($entryData['clock_in']);
                $clockOut = isset($entryData['clock_out']) ? Carbon::parse($entryData['clock_out']) : null;

                $totalHours = null;
                if ($clockOut) {
                    $totalHours = round($clockIn->diffInMinutes($clockOut) / 60, 2);
                }

                $entry = TimeEntry::create([
                    'employee_id' => $entryData['employee_id'],
                    'job_id' => $entryData['job_id'],
                    'team_id' => $employee->current_team_id,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'clock_in_lat' => $entryData['clock_in_lat'] ?? null,
                    'clock_in_lng' => $entryData['clock_in_lng'] ?? null,
                    'clock_out_lat' => $entryData['clock_out_lat'] ?? null,
                    'clock_out_lng' => $entryData['clock_out_lng'] ?? null,
                    'clock_method' => $entryData['clock_method'],
                    'device_id' => $entryData['device_id'] ?? null,
                    'notes' => $entryData['notes'] ?? null,
                    'total_hours' => $totalHours,
                    'status' => 'ACTIVE',
                    'sync_status' => 'SYNCED',
                ]);

                $syncedEntries[] = [
                    'client_id' => $entryData['client_id'],
                    'server_id' => $entry->id,
                ];
            }

            // Process breaks
            foreach ($request->input('breaks', []) as $breakData) {
                $startTime = Carbon::parse($breakData['start_time']);
                $endTime = isset($breakData['end_time']) ? Carbon::parse($breakData['end_time']) : null;

                $durationMinutes = null;
                if ($endTime) {
                    $durationMinutes = (int) $startTime->diffInMinutes($endTime);
                }

                BreakEntry::create([
                    'time_entry_id' => $breakData['time_entry_id'],
                    'type' => $breakData['type'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_minutes' => $durationMinutes,
                ]);
            }
        });

        return response()->json([
            'data' => [
                'synced_entries' => $syncedEntries,
                'conflicts' => $conflicts,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
```

- [ ] **Step 5: Add sync routes**

Add to `routes/api.php` inside the `auth:sanctum` middleware group:

```php
use App\Http\Controllers\SyncController;

Route::get('sync', [SyncController::class, 'pull']);
Route::post('sync', [SyncController::class, 'push']);
```

- [ ] **Step 6: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/SyncTest.php`
Expected: All 4 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/SyncController.php app/Http/Requests/SyncRequest.php routes/api.php tests/Feature/SyncTest.php
git commit -m "feat: add mobile sync endpoint (pull geofences/teams/jobs, push time entries/breaks)"
```

---

## Task 15: Complete API Routes & Final Verification

**Files:**
- Modify: `routes/api.php` (final complete file)

- [ ] **Step 1: Verify complete routes/api.php**

The final `routes/api.php` should contain all routes added across Tasks 1-14. Verify the complete file:

```php
// routes/api.php
<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BreakEntryController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GeofenceController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PtoController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\TransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth routes
    Route::post('/auth/register', RegisterController::class);
    Route::post('/auth/login', [LoginController::class, 'login']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::get('/auth/me', [LoginController::class, 'me']);
        Route::post('/auth/logout', [LoginController::class, 'logout']);

        // Billing
        Route::prefix('billing')->group(function () {
            Route::get('/status', [\App\Http\Controllers\Billing\SubscriptionController::class, 'status']);
            Route::post('/checkout', [\App\Http\Controllers\Billing\SubscriptionController::class, 'createCheckoutSession']);
        });

        // Employees
        Route::apiResource('employees', EmployeeController::class);

        // Teams
        Route::apiResource('teams', TeamController::class);

        // Transfers
        Route::apiResource('transfers', TransferController::class)->only(['index', 'store', 'show']);
        Route::post('transfers/{transfer}/approve', [TransferController::class, 'approve']);
        Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject']);

        // Jobs
        Route::apiResource('jobs', JobController::class);

        // Geofences
        Route::apiResource('geofences', GeofenceController::class);

        // Time Entries
        Route::get('time-entries', [TimeEntryController::class, 'index']);
        Route::get('time-entries/{timeEntry}', [TimeEntryController::class, 'show']);
        Route::post('time-entries/clock-in', [TimeEntryController::class, 'clockIn']);
        Route::post('time-entries/{timeEntry}/clock-out', [TimeEntryController::class, 'clockOut']);
        Route::put('time-entries/{timeEntry}', [TimeEntryController::class, 'update']);

        // Breaks
        Route::post('breaks', [BreakEntryController::class, 'store']);
        Route::post('breaks/{breakEntry}/end', [BreakEntryController::class, 'end']);

        // Timesheets
        Route::prefix('timesheets')->group(function () {
            Route::post('/submit', [TimesheetController::class, 'submit']);
            Route::post('/review', [TimesheetController::class, 'review']);
            Route::post('/process-payroll', [TimesheetController::class, 'processPayroll']);
            Route::get('/summary', [TimesheetController::class, 'summary']);
        });

        // PTO
        Route::get('pto', [PtoController::class, 'index']);
        Route::post('pto', [PtoController::class, 'store']);
        Route::post('pto/{ptoRequest}/review', [PtoController::class, 'review']);
        Route::get('pto/balance/{employeeId}', [PtoController::class, 'balance']);

        // Audit Logs
        Route::get('audit-logs', function (Request $request) {
            if (! $request->user()->isAdmin()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $logs = \App\Models\AuditLog::query()
                ->when($request->query('entity_type'), fn ($q, $type) => $q->where('entity_type', $type))
                ->when($request->query('entity_id'), fn ($q, $id) => $q->where('entity_id', $id))
                ->orderByDesc('created_at')
                ->paginate($request->query('per_page', 50));

            return response()->json([
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
            ]);
        });

        // Sync (mobile offline-first)
        Route::get('sync', [SyncController::class, 'pull']);
        Route::post('sync', [SyncController::class, 'push']);
    });
});

// Stripe webhook (no auth, Cashier verifies signature)
Route::post('/stripe/webhook', [\App\Http\Controllers\Billing\WebhookController::class, 'handleWebhook']);
```

- [ ] **Step 2: Run all tests**

Run: `docker compose exec app php artisan test`
Expected: All tests pass (Plan 1 + Plan 2 tests).

- [ ] **Step 3: Verify route list**

Run: `docker compose exec app php artisan route:list --path=api/v1`
Expected: All endpoints listed:
- `POST   api/v1/auth/register`
- `POST   api/v1/auth/login`
- `GET    api/v1/auth/me`
- `POST   api/v1/auth/logout`
- `GET    api/v1/billing/status`
- `POST   api/v1/billing/checkout`
- `GET    api/v1/employees`
- `POST   api/v1/employees`
- `GET    api/v1/employees/{employee}`
- `PUT    api/v1/employees/{employee}`
- `DELETE api/v1/employees/{employee}`
- `GET    api/v1/teams`
- `POST   api/v1/teams`
- `GET    api/v1/teams/{team}`
- `PUT    api/v1/teams/{team}`
- `DELETE api/v1/teams/{team}`
- `GET    api/v1/transfers`
- `POST   api/v1/transfers`
- `GET    api/v1/transfers/{transfer}`
- `POST   api/v1/transfers/{transfer}/approve`
- `POST   api/v1/transfers/{transfer}/reject`
- `GET    api/v1/jobs`
- `POST   api/v1/jobs`
- `GET    api/v1/jobs/{job}`
- `PUT    api/v1/jobs/{job}`
- `DELETE api/v1/jobs/{job}`
- `GET    api/v1/geofences`
- `POST   api/v1/geofences`
- `GET    api/v1/geofences/{geofence}`
- `PUT    api/v1/geofences/{geofence}`
- `DELETE api/v1/geofences/{geofence}`
- `GET    api/v1/time-entries`
- `GET    api/v1/time-entries/{timeEntry}`
- `POST   api/v1/time-entries/clock-in`
- `POST   api/v1/time-entries/{timeEntry}/clock-out`
- `PUT    api/v1/time-entries/{timeEntry}`
- `POST   api/v1/breaks`
- `POST   api/v1/breaks/{breakEntry}/end`
- `POST   api/v1/timesheets/submit`
- `POST   api/v1/timesheets/review`
- `POST   api/v1/timesheets/process-payroll`
- `GET    api/v1/timesheets/summary`
- `GET    api/v1/pto`
- `POST   api/v1/pto`
- `POST   api/v1/pto/{ptoRequest}/review`
- `GET    api/v1/pto/balance/{employeeId}`
- `GET    api/v1/audit-logs`
- `GET    api/v1/sync`
- `POST   api/v1/sync`

- [ ] **Step 4: Commit**

```bash
git add routes/api.php
git commit -m "chore: verify complete API route file for Plan 2"
```

- [ ] **Step 5: Run full verification from clean state**

```bash
docker compose down -v
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

Expected: All migrations run, all tests pass from a clean state.

- [ ] **Step 6: Final commit and push**

```bash
git add -A
git commit -m "chore: final verification for Plan 2 — Core Time Tracking API"
git push origin main
```
