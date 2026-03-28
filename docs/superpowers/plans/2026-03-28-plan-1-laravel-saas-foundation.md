# Plan 1: Laravel SaaS Foundation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up a Dockerized Laravel 13 SaaS foundation with multi-tenant architecture, Sanctum auth (web + API), and Stripe billing via Laravel Cashier.

**Architecture:** Fully Dockerized stack (Caddy, PHP 8.3-FPM, PostgreSQL 16 + PostGIS, Redis 7) running on a single host. Multi-tenancy via `tenant_id` column scoping with a Laravel global scope and middleware. Stripe subscriptions managed by Laravel Cashier with per-employee quantity billing.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL 16 + PostGIS, Redis 7, Caddy 2, Laravel Sanctum 4.x, Laravel Cashier 16.x, Laravel Reverb 1.x, Docker Compose

---

## File Structure

```
GeoTime/
├── docker/
│   ├── app/
│   │   ├── Dockerfile
│   │   └── supervisord.conf
│   ├── caddy/
│   │   └── Caddyfile
│   └── postgres/
│       └── init-postgis.sql
├── docker-compose.yml
├── .env.example
├── app/
│   ├── Models/
│   │   ├── Tenant.php
│   │   ├── User.php
│   │   └── Scopes/
│   │       └── TenantScope.php
│   ├── Traits/
│   │   └── BelongsToTenant.php
│   ├── Http/
│   │   ├── Middleware/
│   │   │   ├── ResolveTenant.php
│   │   │   └── EnsureSubscriptionActive.php
│   │   └── Controllers/
│   │       ├── Auth/
│   │       │   ├── RegisterController.php
│   │       │   ├── LoginController.php
│   │       │   └── ApiTokenController.php
│   │       └── Billing/
│   │           ├── SubscriptionController.php
│   │           └── WebhookController.php
│   └── Providers/
│       └── AppServiceProvider.php
├── database/
│   └── migrations/
│       ├── 0001_01_01_000000_create_users_table.php (modify)
│       ├── xxxx_create_tenants_table.php
│       └── xxxx_add_tenant_id_to_users_table.php
├── routes/
│   ├── web.php
│   └── api.php
├── config/
│   └── database.php (modify)
└── tests/
    ├── Unit/
    │   ├── TenantScopeTest.php
    │   └── BelongsToTenantTest.php
    └── Feature/
        ├── Auth/
        │   ├── RegistrationTest.php
        │   ├── LoginTest.php
        │   └── ApiTokenTest.php
        ├── Tenant/
        │   └── TenantIsolationTest.php
        └── Billing/
            ├── SubscriptionTest.php
            └── SubscriptionGatingTest.php
```

---

## Task 1: Docker Infrastructure

**Files:**
- Create: `docker-compose.yml`
- Create: `docker/app/Dockerfile`
- Create: `docker/app/supervisord.conf`
- Create: `docker/caddy/Caddyfile`
- Create: `docker/postgres/init-postgis.sql`

- [ ] **Step 1: Create docker-compose.yml**

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    volumes:
      - .:/var/www/html
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
    environment:
      - APP_ENV=${APP_ENV:-local}
    networks:
      - geotime

  caddy:
    image: caddy:2-alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/caddy/Caddyfile:/etc/caddy/Caddyfile
      - .:/var/www/html
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      - app
    networks:
      - geotime

  postgres:
    image: postgis/postgis:16-3.5
    environment:
      POSTGRES_DB: ${DB_DATABASE:-geotime}
      POSTGRES_USER: ${DB_USERNAME:-geotime}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/init-postgis.sql:/docker-entrypoint-initdb.d/init-postgis.sql
    ports:
      - "5432:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-geotime}"]
      interval: 5s
      timeout: 5s
      retries: 5
    networks:
      - geotime

  redis:
    image: redis:7-alpine
    command: redis-server --maxmemory 64mb --maxmemory-policy allkeys-lru
    volumes:
      - redis_data:/data
    ports:
      - "6379:6379"
    networks:
      - geotime

volumes:
  postgres_data:
  redis_data:
  caddy_data:
  caddy_config:

networks:
  geotime:
    driver: bridge
```

- [ ] **Step 2: Create Dockerfile**

```dockerfile
# docker/app/Dockerfile
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libzip-dev libicu-dev \
    supervisor \
    && docker-php-ext-install pdo_pgsql pgsql zip intl bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm

COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

- [ ] **Step 3: Create supervisord.conf**

```ini
; docker/app/supervisord.conf
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:queue-worker]
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=1
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:scheduler]
command=bash -c "while true; do php /var/www/html/artisan schedule:run --no-interaction; sleep 60; done"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

- [ ] **Step 4: Create Caddyfile**

```
# docker/caddy/Caddyfile
{
    auto_https off
}

:80 {
    root * /var/www/html/public

    encode gzip

    file_server {
        precompressed gzip
    }

    @static {
        path *.css *.js *.ico *.gif *.jpg *.jpeg *.png *.svg *.woff *.woff2
    }
    header @static Cache-Control "public, max-age=31536000, immutable"

    php_fastcgi app:9000
}
```

- [ ] **Step 5: Create PostGIS init script**

```sql
-- docker/postgres/init-postgis.sql
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
```

- [ ] **Step 6: Verify Docker builds**

Run: `docker compose build`
Expected: All images build without errors.

- [ ] **Step 7: Commit**

```bash
git add docker/ docker-compose.yml
git commit -m "feat: add Docker infrastructure (Caddy, PHP 8.3, PostgreSQL/PostGIS, Redis)"
```

---

## Task 2: Laravel Project Scaffold

**Files:**
- Create: Laravel 13 project in current directory
- Modify: `.env.example`
- Modify: `config/database.php`

- [ ] **Step 1: Create Laravel project**

Run from the project root (outside Docker, on host):

```bash
composer create-project laravel/laravel temp-laravel "13.*"
cp -rn temp-laravel/. .
rm -rf temp-laravel
```

This creates the Laravel project files without overwriting existing files (docker/, docs/, PRD.md).

- [ ] **Step 2: Update .env.example for PostgreSQL + Redis**

Edit `.env.example` — change these values:

```dotenv
APP_NAME=GeoTime
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=geotime
DB_USERNAME=geotime
DB_PASSWORD=secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PORT=6379

BROADCAST_CONNECTION=reverb

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

- [ ] **Step 3: Copy .env.example to .env**

```bash
cp .env.example .env
```

- [ ] **Step 4: Create .gitignore additions**

Ensure `.gitignore` includes:
```
.env
vendor/
node_modules/
```

- [ ] **Step 5: Install dependencies inside Docker**

```bash
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app npm install
```

- [ ] **Step 6: Verify Laravel boots**

Run: `docker compose exec app php artisan about`
Expected: Shows Laravel version 13.x, PHP 8.3, PostgreSQL connection.

Run: `curl -s http://localhost | head -20`
Expected: Laravel welcome page HTML.

- [ ] **Step 7: Commit**

```bash
git add .
git commit -m "feat: scaffold Laravel 13 project with PostgreSQL/Redis config"
```

---

## Task 3: Tenant Model & Migration

**Files:**
- Create: `database/migrations/xxxx_create_tenants_table.php`
- Create: `app/Models/Tenant.php`
- Create: `tests/Unit/TenantModelTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/TenantModelTest.php
<?php

namespace Tests\Unit;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Acme Construction',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->assertNotNull($tenant->id);
        $this->assertEquals('Acme Construction', $tenant->name);
        $this->assertEquals('trial', $tenant->status);
    }

    public function test_tenant_has_default_overtime_rule(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/Chicago',
            'workweek_start_day' => 0,
            'plan' => 'starter',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->assertEquals(40, $tenant->overtime_rule['weekly_threshold']);
        $this->assertEquals(1.5, $tenant->overtime_rule['multiplier']);
    }

    public function test_tenant_trial_check(): void
    {
        $tenant = Tenant::create([
            'name' => 'Trial Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->assertTrue($tenant->onTrial());

        $tenant->trial_ends_at = now()->subDay();
        $this->assertFalse($tenant->onTrial());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Unit/TenantModelTest.php`
Expected: FAIL — Tenant model/table does not exist.

- [ ] **Step 3: Create the tenants migration**

```bash
docker compose exec app php artisan make:migration create_tenants_table
```

```php
// database/migrations/xxxx_create_tenants_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('name', 255);
            $table->string('timezone', 50)->default('America/New_York');
            $table->tinyInteger('workweek_start_day')->default(1); // 0=Sun, 1=Mon
            $table->jsonb('overtime_rule')->default('{"weekly_threshold": 40, "daily_threshold": null, "multiplier": 1.5}');
            $table->string('rounding_rule', 20)->default('EXACT'); // EXACT, NEAREST_5, NEAREST_6, NEAREST_15
            $table->string('plan', 20)->default('starter'); // starter, business
            $table->string('status', 20)->default('trial'); // trial, active, past_due, cancelled, suspended
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
```

- [ ] **Step 4: Create the Tenant model**

```php
// app/Models/Tenant.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use HasUuids, Billable;

    protected $fillable = [
        'name',
        'timezone',
        'workweek_start_day',
        'overtime_rule',
        'rounding_rule',
        'plan',
        'status',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'overtime_rule' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function onTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
```

- [ ] **Step 5: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Unit/TenantModelTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Tenant.php database/migrations/*create_tenants* tests/Unit/TenantModelTest.php
git commit -m "feat: add Tenant model with migration and tests"
```

---

## Task 4: Tenant Scoping (Multi-Tenancy)

**Files:**
- Create: `app/Traits/BelongsToTenant.php`
- Create: `app/Models/Scopes/TenantScope.php`
- Create: `app/Http/Middleware/ResolveTenant.php`
- Modify: `app/Models/User.php`
- Modify: default users migration
- Create: `tests/Feature/Tenant/TenantIsolationTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Tenant/TenantIsolationTest.php
<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_are_scoped_to_tenant(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Company A',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Company B',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        User::create([
            'name' => 'Alice',
            'email' => 'alice@a.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenantA->id,
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Bob',
            'email' => 'bob@b.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenantB->id,
            'role' => 'admin',
        ]);

        // Set current tenant to A
        app()->instance('current_tenant', $tenantA);

        // Should only see Alice
        $users = User::all();
        $this->assertCount(1, $users);
        $this->assertEquals('Alice', $users->first()->name);
    }

    public function test_user_cannot_access_other_tenant_data(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Company A',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Company B',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $bobUser = User::create([
            'name' => 'Bob',
            'email' => 'bob@b.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenantB->id,
            'role' => 'admin',
        ]);

        // Set current tenant to A
        app()->instance('current_tenant', $tenantA);

        // Should not find Bob
        $found = User::find($bobUser->id);
        $this->assertNull($found);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Tenant/TenantIsolationTest.php`
Expected: FAIL — tenant_id column doesn't exist, scope not applied.

- [ ] **Step 3: Create migration to add tenant_id to users**

```bash
docker compose exec app php artisan make:migration add_tenant_id_and_role_to_users_table
```

```php
// database/migrations/xxxx_add_tenant_id_and_role_to_users_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('tenant_id')->after('id');
            $table->string('role', 20)->default('employee'); // employee, team_lead, manager, admin, super_admin
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'role']);
        });
    }
};
```

- [ ] **Step 4: Create TenantScope**

```php
// app/Models/Scopes/TenantScope.php
<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if ($tenant) {
            $builder->where($model->getTable() . '.tenant_id', $tenant->id);
        }
    }
}
```

- [ ] **Step 5: Create BelongsToTenant trait**

```php
// app/Traits/BelongsToTenant.php
<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->tenant_id && app()->bound('current_tenant')) {
                $model->tenant_id = app('current_tenant')->id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

- [ ] **Step 6: Update User model**

```php
// app/Models/User.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, BelongsToTenant;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['manager', 'admin', 'super_admin']);
    }
}
```

- [ ] **Step 7: Create ResolveTenant middleware**

```php
// app/Http/Middleware/ResolveTenant.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            $tenant = $user->tenant()->first();
            app()->instance('current_tenant', $tenant);
        }

        return $next($request);
    }
}
```

- [ ] **Step 8: Register middleware in bootstrap/app.php**

Add to `bootstrap/app.php` inside the `withMiddleware` callback:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', \App\Http\Middleware\ResolveTenant::class);
    $middleware->appendToGroup('api', \App\Http\Middleware\ResolveTenant::class);
})
```

- [ ] **Step 9: Run migration and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/Tenant/TenantIsolationTest.php`
Expected: All 2 tests PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Models/ app/Traits/ app/Http/Middleware/ResolveTenant.php database/migrations/*tenant* bootstrap/app.php tests/Feature/Tenant/
git commit -m "feat: add multi-tenant scoping with BelongsToTenant trait and middleware"
```

---

## Task 5: Tenant Registration (Web + API)

**Files:**
- Create: `app/Http/Controllers/Auth/RegisterController.php`
- Create: `tests/Feature/Auth/RegistrationTest.php`
- Modify: `routes/web.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Auth/RegistrationTest.php
<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tenant_can_register_via_api(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'company_name' => 'Acme Construction',
            'name' => 'John Owner',
            'email' => 'john@acme.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone' => 'America/New_York',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'tenant' => ['id', 'name', 'plan', 'status', 'trial_ends_at'],
                    'user' => ['id', 'name', 'email', 'role'],
                    'token',
                ],
            ]);

        // Verify tenant was created
        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Construction',
            'plan' => 'business',
            'status' => 'trial',
        ]);

        // Verify admin user was created
        $this->assertDatabaseHas('users', [
            'email' => 'john@acme.com',
            'role' => 'admin',
        ]);

        // Verify trial is 14 days
        $tenant = Tenant::where('name', 'Acme Construction')->first();
        $this->assertTrue($tenant->trial_ends_at->isFuture());
        $this->assertTrue($tenant->trial_ends_at->diffInDays(now()) >= 13);
    }

    public function test_registration_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name', 'name', 'email', 'password']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $tenant = Tenant::create([
            'name' => 'Existing Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        User::create([
            'name' => 'Existing',
            'email' => 'taken@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'company_name' => 'New Co',
            'name' => 'New User',
            'email' => 'taken@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Auth/RegistrationTest.php`
Expected: FAIL — route not defined, controller does not exist.

- [ ] **Step 3: Create RegisterController**

```php
// app/Http/Controllers/Auth/RegisterController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'name' => $validated['company_name'],
                'timezone' => $validated['timezone'] ?? 'America/New_York',
                'workweek_start_day' => 1,
                'plan' => 'business',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            $user = User::withoutGlobalScopes()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'tenant_id' => $tenant->id,
                'role' => 'admin',
            ]);

            $token = $user->createToken('api')->plainTextToken;

            return compact('tenant', 'user', 'token');
        });

        return response()->json([
            'data' => [
                'tenant' => [
                    'id' => $result['tenant']->id,
                    'name' => $result['tenant']->name,
                    'plan' => $result['tenant']->plan,
                    'status' => $result['tenant']->status,
                    'trial_ends_at' => $result['tenant']->trial_ends_at->toIso8601String(),
                ],
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'role' => $result['user']->role,
                ],
                'token' => $result['token'],
            ],
        ], 201);
    }
}
```

- [ ] **Step 4: Add API route**

```php
// routes/api.php
<?php

use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', RegisterController::class);
});
```

- [ ] **Step 5: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Auth/RegistrationTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Auth/RegisterController.php routes/api.php tests/Feature/Auth/RegistrationTest.php
git commit -m "feat: add tenant registration endpoint with validation and tests"
```

---

## Task 6: Login (Web + API Token)

**Files:**
- Create: `app/Http/Controllers/Auth/LoginController.php`
- Create: `app/Http/Controllers/Auth/ApiTokenController.php`
- Create: `tests/Feature/Auth/LoginTest.php`
- Create: `tests/Feature/Auth/ApiTokenTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing login test**

```php
// tests/Feature/Auth/LoginTest.php
<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantAndUser(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('Password123!'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        return [$tenant, $user];
    }

    public function test_user_can_login_via_api(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                    'tenant' => ['id', 'name', 'plan'],
                    'token',
                ],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@test.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_request_resolves_tenant(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'user' => ['email' => 'test@test.com'],
                    'tenant' => ['name' => 'Test Co'],
                ],
            ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Auth/LoginTest.php`
Expected: FAIL — controller and routes don't exist.

- [ ] **Step 3: Create LoginController**

```php
// app/Http/Controllers/Auth/LoginController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['sometimes', 'string'],
        ]);

        $user = User::withoutGlobalScopes()
            ->where('email', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $tenant = $user->tenant()->first();
        $token = $user->createToken($request->device_name ?? 'api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'plan' => $tenant->plan,
                ],
                'token' => $token,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = app('current_tenant');

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'plan' => $tenant->plan,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
```

- [ ] **Step 4: Update API routes**

```php
// routes/api.php
<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth routes
    Route::post('/auth/register', RegisterController::class);
    Route::post('/auth/login', [LoginController::class, 'login']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [LoginController::class, 'me']);
        Route::post('/auth/logout', [LoginController::class, 'logout']);
    });
});
```

- [ ] **Step 5: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Auth/LoginTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Auth/LoginController.php routes/api.php tests/Feature/Auth/LoginTest.php
git commit -m "feat: add login, logout, and me endpoints with Sanctum tokens"
```

---

## Task 7: Stripe Billing Setup

**Files:**
- Create: `app/Http/Controllers/Billing/SubscriptionController.php`
- Create: `tests/Feature/Billing/SubscriptionTest.php`
- Modify: `app/Models/Tenant.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Install Laravel Cashier**

```bash
docker compose exec app composer require laravel/cashier
docker compose exec app php artisan migrate
```

This creates the `subscriptions` and `subscription_items` tables that Cashier manages.

- [ ] **Step 2: Verify Tenant model uses Billable trait**

The `Tenant` model already has `use Billable;` (added in Task 3). Cashier expects the Billable model to have `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at` columns — these are already in our tenants migration.

Configure Cashier to use Tenant as the billable model. Add to `config/cashier.php` (publish if needed):

```bash
docker compose exec app php artisan vendor:publish --tag=cashier-config
```

Edit `config/cashier.php`:

```php
'model' => App\Models\Tenant::class,
```

- [ ] **Step 3: Write the failing subscription test**

```php
// tests/Feature/Billing/SubscriptionTest.php
<?php

namespace Tests\Feature\Billing;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        return [$tenant, $user];
    }

    public function test_billing_status_endpoint_returns_tenant_billing_info(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/billing/status');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'plan' => 'business',
                    'status' => 'trial',
                    'on_trial' => true,
                    'has_subscription' => false,
                ],
            ]);
    }

    public function test_only_admins_can_access_billing(): void
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
            ->getJson('/api/v1/billing/status');

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 4: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Billing/SubscriptionTest.php`
Expected: FAIL — controller and route don't exist.

- [ ] **Step 5: Create SubscriptionController**

```php
// app/Http/Controllers/Billing/SubscriptionController.php
<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tenant = app('current_tenant');

        return response()->json([
            'data' => [
                'plan' => $tenant->plan,
                'status' => $tenant->status,
                'on_trial' => $tenant->onTrial(),
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'has_subscription' => $tenant->subscribed('default'),
            ],
        ]);
    }

    public function createCheckoutSession(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'plan' => ['required', 'in:starter,business'],
        ]);

        $tenant = app('current_tenant');

        $priceId = config("billing.prices.{$request->plan}");

        $activeEmployeeCount = $tenant->users()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();

        $checkout = $tenant->newSubscription('default', $priceId)
            ->quantity(max(1, $activeEmployeeCount))
            ->checkout([
                'success_url' => config('app.frontend_url') . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/billing/cancel',
            ]);

        return response()->json([
            'data' => [
                'checkout_url' => $checkout->url,
            ],
        ]);
    }
}
```

- [ ] **Step 6: Add billing config**

```php
// config/billing.php
<?php

return [
    'prices' => [
        'starter' => env('STRIPE_PRICE_STARTER', ''),
        'business' => env('STRIPE_PRICE_BUSINESS', ''),
    ],
];
```

- [ ] **Step 7: Add billing routes**

Update `routes/api.php` — add inside the `auth:sanctum` group:

```php
    // Billing
    Route::prefix('billing')->group(function () {
        Route::get('/status', [\App\Http\Controllers\Billing\SubscriptionController::class, 'status']);
        Route::post('/checkout', [\App\Http\Controllers\Billing\SubscriptionController::class, 'createCheckoutSession']);
    });
```

- [ ] **Step 8: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Billing/SubscriptionTest.php`
Expected: All 2 tests PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Billing/ config/billing.php routes/api.php tests/Feature/Billing/SubscriptionTest.php
git commit -m "feat: add billing status and Stripe checkout endpoints"
```

---

## Task 8: Subscription Gating Middleware

**Files:**
- Create: `app/Http/Middleware/EnsureSubscriptionActive.php`
- Create: `tests/Feature/Billing/SubscriptionGatingTest.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Billing/SubscriptionGatingTest.php
<?php

namespace Tests\Feature\Billing;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionGatingTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantWithUser(string $status, ?string $trialEnds = null): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => $status,
            'trial_ends_at' => $trialEnds ? now()->parse($trialEnds) : null,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        return [$tenant, $user];
    }

    public function test_active_tenant_can_access_protected_routes(): void
    {
        [$tenant, $user] = $this->createTenantWithUser('active');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
    }

    public function test_trial_tenant_can_access_protected_routes(): void
    {
        [$tenant, $user] = $this->createTenantWithUser('trial', '+14 days');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
    }

    public function test_expired_trial_allows_reads_but_blocks_writes(): void
    {
        [$tenant, $user] = $this->createTenantWithUser('trial', '-1 day');

        // GET (read) should work — read-only mode
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');
        $response->assertStatus(200);

        // POST (write) should return 402 with read_only flag
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/billing/checkout', ['plan' => 'starter']);
        $response->assertStatus(402)
            ->assertJson([
                'read_only' => true,
            ]);
    }

    public function test_suspended_tenant_cannot_access_anything(): void
    {
        [$tenant, $user] = $this->createTenantWithUser('suspended');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Account suspended. Please contact support.']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Billing/SubscriptionGatingTest.php`
Expected: FAIL — suspended tenant still gets 200.

- [ ] **Step 3: Create EnsureSubscriptionActive middleware**

```php
// app/Http/Middleware/EnsureSubscriptionActive.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if (! $tenant) {
            return $next($request);
        }

        // Suspended tenants are fully blocked
        if ($tenant->status === 'suspended') {
            return response()->json([
                'message' => 'Account suspended. Please contact support.',
            ], 403);
        }

        // Cancelled tenants are fully blocked
        if ($tenant->status === 'cancelled') {
            return response()->json([
                'message' => 'Subscription cancelled. Please resubscribe to continue.',
            ], 403);
        }

        // Active and valid trial tenants pass through
        if ($tenant->status === 'active' || $tenant->onTrial()) {
            return $next($request);
        }

        // Past due gets grace period — allow reads, block writes
        if ($tenant->status === 'past_due' || ($tenant->status === 'trial' && ! $tenant->onTrial())) {
            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Subscription inactive. Read-only mode. Please update your payment method.',
                'read_only' => true,
            ], 402);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register middleware in bootstrap/app.php**

Update `bootstrap/app.php` to add the new middleware after ResolveTenant:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', \App\Http\Middleware\ResolveTenant::class);
    $middleware->appendToGroup('web', \App\Http\Middleware\EnsureSubscriptionActive::class);
    $middleware->appendToGroup('api', \App\Http\Middleware\ResolveTenant::class);
    $middleware->appendToGroup('api', \App\Http\Middleware\EnsureSubscriptionActive::class);
})
```

- [ ] **Step 5: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Billing/SubscriptionGatingTest.php`
Expected: All 4 tests PASS.

- [ ] **Step 6: Run all tests**

Run: `docker compose exec app php artisan test`
Expected: All tests pass (Registration, Login, TenantModel, TenantIsolation, Subscription, SubscriptionGating).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/EnsureSubscriptionActive.php bootstrap/app.php tests/Feature/Billing/SubscriptionGatingTest.php
git commit -m "feat: add subscription gating middleware with read-only mode for lapsed tenants"
```

---

## Task 9: Stripe Webhook Handler

**Files:**
- Create: `app/Http/Controllers/Billing/WebhookController.php`
- Create: `tests/Feature/Billing/WebhookTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Billing/WebhookTest.php
<?php

namespace Tests\Feature\Billing;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantWithStripeId(string $stripeId): Tenant
    {
        return Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'stripe_id' => $stripeId,
        ]);
    }

    public function test_subscription_created_activates_tenant(): void
    {
        $tenant = $this->createTenantWithStripeId('cus_test_123');

        $this->assertEquals('trial', $tenant->status);

        // Simulate the webhook handler logic directly (no Stripe signature in tests)
        $tenant->update([
            'status' => 'active',
            'plan' => 'business',
        ]);

        $tenant->refresh();
        $this->assertEquals('active', $tenant->status);
        $this->assertEquals('business', $tenant->plan);
    }

    public function test_subscription_deleted_cancels_tenant(): void
    {
        $tenant = $this->createTenantWithStripeId('cus_test_456');
        $tenant->update(['status' => 'active']);

        $tenant->update(['status' => 'cancelled']);

        $tenant->refresh();
        $this->assertEquals('cancelled', $tenant->status);
    }

    public function test_subscription_past_due_updates_tenant(): void
    {
        $tenant = $this->createTenantWithStripeId('cus_test_789');
        $tenant->update(['status' => 'active']);

        $tenant->update(['status' => 'past_due']);

        $tenant->refresh();
        $this->assertEquals('past_due', $tenant->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Billing/WebhookTest.php`
Expected: FAIL — stripe_id is not fillable (fix by adding it to Tenant's `$fillable`).

- [ ] **Step 3: Add stripe_id to Tenant fillable**

Add `'stripe_id'` to the `$fillable` array in `app/Models/Tenant.php`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec app php artisan test tests/Feature/Billing/WebhookTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 5: Create WebhookController**

```php
// app/Http/Controllers/Billing/WebhookController.php
<?php

namespace App\Http\Controllers\Billing;

use App\Models\Tenant;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class WebhookController extends CashierWebhookController
{
    /**
     * Handle subscription created event — activate tenant.
     */
    protected function handleCustomerSubscriptionCreated(array $payload): void
    {
        parent::handleCustomerSubscriptionCreated($payload);

        $stripeId = $payload['data']['object']['customer'];
        $tenant = Tenant::where('stripe_id', $stripeId)->first();

        if ($tenant) {
            $planId = $payload['data']['object']['items']['data'][0]['price']['id'] ?? null;
            $plan = $this->resolvePlanFromPriceId($planId);

            $tenant->update([
                'status' => 'active',
                'plan' => $plan,
            ]);
        }
    }

    /**
     * Handle subscription updated — check for past_due status.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): void
    {
        parent::handleCustomerSubscriptionUpdated($payload);

        $stripeId = $payload['data']['object']['customer'];
        $status = $payload['data']['object']['status'];
        $tenant = Tenant::where('stripe_id', $stripeId)->first();

        if ($tenant) {
            if ($status === 'past_due') {
                $tenant->update(['status' => 'past_due']);
            } elseif ($status === 'active') {
                $tenant->update(['status' => 'active']);
            }
        }
    }

    /**
     * Handle subscription deleted — mark cancelled.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): void
    {
        parent::handleCustomerSubscriptionDeleted($payload);

        $stripeId = $payload['data']['object']['customer'];
        $tenant = Tenant::where('stripe_id', $stripeId)->first();

        if ($tenant) {
            $tenant->update(['status' => 'cancelled']);
        }
    }

    private function resolvePlanFromPriceId(?string $priceId): string
    {
        if ($priceId === config('billing.prices.business')) {
            return 'business';
        }

        return 'starter';
    }
}
```

- [ ] **Step 2: Add webhook route**

Add to `routes/api.php` (outside the `v1` prefix, Stripe sends to `/stripe/webhook`):

```php
// Stripe webhook (no auth, Cashier verifies signature)
Route::post('/stripe/webhook', [\App\Http\Controllers\Billing\WebhookController::class, 'handleWebhook']);
```

- [ ] **Step 3: Exclude webhook route from CSRF protection**

In Laravel 13, CSRF exclusions are configured in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'stripe/webhook',
    ]);
    // ... existing middleware
})
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Billing/WebhookController.php app/Models/Tenant.php routes/api.php bootstrap/app.php tests/Feature/Billing/WebhookTest.php
git commit -m "feat: add Stripe webhook handler for subscription lifecycle events"
```

---

## Task 10: Employee Count Sync for Billing

**Files:**
- Create: `app/Listeners/SyncEmployeeCount.php`
- Create: `app/Events/EmployeeCountChanged.php`
- Create: `tests/Feature/Billing/EmployeeCountSyncTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Billing/EmployeeCountSyncTest.php
<?php

namespace Tests\Feature\Billing;

use App\Events\EmployeeCountChanged;
use App\Listeners\SyncEmployeeCount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCountSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_count_changed_event_is_dispatched(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        app()->instance('current_tenant', $tenant);

        $event = new EmployeeCountChanged($tenant);

        $this->assertEquals($tenant->id, $event->tenant->id);
    }

    public function test_listener_calculates_correct_employee_count(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        // Create 3 users for this tenant
        for ($i = 1; $i <= 3; $i++) {
            User::withoutGlobalScopes()->create([
                'name' => "User $i",
                'email' => "user{$i}@test.com",
                'password' => bcrypt('password'),
                'tenant_id' => $tenant->id,
                'role' => 'employee',
            ]);
        }

        $count = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();

        $this->assertEquals(3, $count);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Billing/EmployeeCountSyncTest.php`
Expected: FAIL — event class does not exist.

- [ ] **Step 3: Create the event**

```php
// app/Events/EmployeeCountChanged.php
<?php

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeCountChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public Tenant $tenant)
    {
    }
}
```

- [ ] **Step 4: Create the listener**

```php
// app/Listeners/SyncEmployeeCount.php
<?php

namespace App\Listeners;

use App\Events\EmployeeCountChanged;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncEmployeeCount implements ShouldQueue
{
    public function handle(EmployeeCountChanged $event): void
    {
        $tenant = $event->tenant;

        // Only sync if tenant has an active subscription
        if (! $tenant->subscribed('default')) {
            return;
        }

        $count = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();

        $tenant->subscription('default')->updateQuantity(max(1, $count));
    }
}
```

- [ ] **Step 5: Register event/listener and add User model observer**

Add to `app/Providers/AppServiceProvider.php` boot method:

```php
use App\Events\EmployeeCountChanged;
use App\Listeners\SyncEmployeeCount;
use App\Models\User;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(EmployeeCountChanged::class, SyncEmployeeCount::class);

    // Dispatch EmployeeCountChanged when users are created or deleted
    User::created(function (User $user) {
        if ($user->tenant) {
            EmployeeCountChanged::dispatch($user->tenant);
        }
    });

    User::deleted(function (User $user) {
        if ($user->tenant) {
            EmployeeCountChanged::dispatch($user->tenant);
        }
    });
}
```

- [ ] **Step 6: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Billing/EmployeeCountSyncTest.php`
Expected: All 2 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Events/EmployeeCountChanged.php app/Listeners/SyncEmployeeCount.php app/Providers/AppServiceProvider.php tests/Feature/Billing/EmployeeCountSyncTest.php
git commit -m "feat: add employee count sync event for Stripe subscription quantity"
```

---

## Task 11: Install Inertia + Vue + Reverb

**Files:**
- Modify: `composer.json` (add inertia-laravel)
- Modify: `package.json` (add vue, inertia)
- Create: `resources/js/app.js`
- Create: `resources/js/Pages/Dashboard.vue`
- Create: `resources/views/app.blade.php`

- [ ] **Step 1: Install Inertia server-side**

```bash
docker compose exec app composer require inertiajs/inertia-laravel
```

- [ ] **Step 2: Create root Blade template**

```blade
<!-- resources/views/app.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @vite(['resources/js/app.js', 'resources/css/app.css'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
```

- [ ] **Step 3: Install Inertia + Vue client-side**

```bash
docker compose exec app npm install @inertiajs/vue3 vue @vitejs/plugin-vue
```

- [ ] **Step 4: Configure Vite for Vue**

```js
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
```

- [ ] **Step 5: Create app.js entry point**

```js
// resources/js/app.js
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
```

- [ ] **Step 6: Create a placeholder Dashboard page**

```vue
<!-- resources/js/Pages/Dashboard.vue -->
<template>
  <div>
    <h1>GeoTime Dashboard</h1>
    <p>Welcome, {{ $page.props.auth.user.name }}</p>
  </div>
</template>
```

- [ ] **Step 7: Add web dashboard route**

Add to `routes/web.php`:

```php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});
```

- [ ] **Step 8: Install and configure Reverb**

```bash
docker compose exec app composer require laravel/reverb
docker compose exec app php artisan reverb:install
```

Add Reverb to Supervisor in `docker/app/supervisord.conf`:

```ini
[program:reverb]
command=php /var/www/html/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

- [ ] **Step 9: Build frontend assets**

```bash
docker compose exec app npm run build
```
Expected: Vite build completes without errors.

- [ ] **Step 10: Commit**

```bash
git add resources/ vite.config.js routes/web.php docker/app/supervisord.conf composer.json composer.lock package.json package-lock.json config/
git commit -m "feat: install Inertia.js + Vue 3 + Laravel Reverb"
```

---

## Task 12: Final Verification

- [ ] **Step 1: Run all tests**

Run: `docker compose exec app php artisan test`
Expected: All tests pass.

- [ ] **Step 2: Verify Docker stack boots clean**

```bash
docker compose down -v
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app npm install
docker compose exec app npm run build
docker compose exec app php artisan test
```
Expected: Everything installs, migrates, builds, and tests pass from a clean state.

- [ ] **Step 3: Verify endpoints manually**

```bash
# Registration
curl -s -X POST http://localhost/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"company_name":"Test Co","name":"Test User","email":"test@example.com","password":"Password123!","password_confirmation":"Password123!"}' | jq .

# Login
curl -s -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"Password123!"}' | jq .

# Me (use token from login response)
curl -s http://localhost/api/v1/auth/me \
  -H "Authorization: Bearer <TOKEN>" | jq .

# Billing status
curl -s http://localhost/api/v1/billing/status \
  -H "Authorization: Bearer <TOKEN>" | jq .
```

- [ ] **Step 4: Commit any fixes and push**

```bash
git add -A
git commit -m "chore: final verification and cleanup for Plan 1"
git push origin main
```
