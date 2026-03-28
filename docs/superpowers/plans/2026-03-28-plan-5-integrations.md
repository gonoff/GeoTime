# Plan 5: Integrations

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire up QuickBooks Online integration (OAuth, customer/employee/estimate/invoice/bank feed sync), real-time event broadcasting via Laravel Reverb, FCM push notifications, and scheduled jobs for automated maintenance tasks.

**Architecture:** QBO integration uses a thin Guzzle HTTP client wrapped in a `QuickBooksService` class, with OAuth 2.0 token management (auto-refresh) stored encrypted on the tenants table. Sync operations are dispatched as queued jobs and logged in a `qbo_sync_log` table. Real-time events broadcast on private tenant channels via Reverb. FCM push notifications use the Laravel notification system with a custom FCM channel. Scheduled jobs handle trial expiration, temporary transfer reversion, token refresh, and sync retries.

**Tech Stack:** Laravel 13, Guzzle 7, Laravel Reverb 1.x, Laravel Echo, Firebase Cloud Messaging, `laravel-notification-channels/fcm` 4.x, Redis queues

---

## File Structure

```
GeoTime/
├── app/
│   ├── Services/
│   │   └── QuickBooks/
│   │       ├── QuickBooksClient.php
│   │       ├── QuickBooksAuthService.php
│   │       ├── QuickBooksCustomerService.php
│   │       ├── QuickBooksEmployeeService.php
│   │       ├── QuickBooksEstimateService.php
│   │       ├── QuickBooksInvoiceService.php
│   │       ├── QuickBooksBankFeedService.php
│   │       └── QuickBooksServiceItemService.php
│   ├── Models/
│   │   ├── QboSyncLog.php
│   │   ├── QboServiceItemMapping.php
│   │   └── DeviceToken.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── QuickBooksAuthController.php
│   │   │       ├── QuickBooksCustomerController.php
│   │   │       ├── QuickBooksEmployeeController.php
│   │   │       ├── QuickBooksEstimateController.php
│   │   │       ├── QuickBooksInvoiceController.php
│   │   │       ├── QuickBooksBankFeedController.php
│   │   │       ├── QuickBooksServiceItemController.php
│   │   │       ├── QuickBooksWebhookController.php
│   │   │       └── DeviceTokenController.php
│   │   └── Middleware/
│   │       └── EnsureQuickBooksConnected.php
│   ├── Jobs/
│   │   ├── QboSyncCustomersJob.php
│   │   ├── QboSyncEmployeesJob.php
│   │   ├── QboPushEstimateJob.php
│   │   ├── QboPushInvoiceJob.php
│   │   ├── QboPushBankFeedJob.php
│   │   ├── QboRefreshTokensJob.php
│   │   ├── QboRetrySyncJob.php
│   │   ├── CheckTrialExpirationsJob.php
│   │   ├── RevertTemporaryTransfersJob.php
│   │   └── SendPushNotificationJob.php
│   ├── Events/
│   │   ├── TimeEntryCreated.php
│   │   ├── TimeEntryUpdated.php
│   │   ├── ComplianceAlert.php
│   │   └── SyncStatusUpdate.php
│   ├── Listeners/
│   │   ├── BroadcastTimeEntryCreated.php
│   │   ├── BroadcastTimeEntryUpdated.php
│   │   └── BroadcastComplianceAlert.php
│   ├── Notifications/
│   │   ├── ClockConfirmationNotification.php
│   │   ├── BreakReminderNotification.php
│   │   ├── OvertimeApproachingNotification.php
│   │   ├── TimesheetApprovalNotification.php
│   │   ├── TransferNotification.php
│   │   └── ScheduleChangeNotification.php
│   └── Channels/
│       └── FcmChannel.php
├── config/
│   ├── quickbooks.php
│   └── fcm.php
├── database/
│   └── migrations/
│       ├── xxxx_add_qbo_columns_to_tenants_table.php
│       ├── xxxx_create_qbo_sync_log_table.php
│       ├── xxxx_create_qbo_service_item_mappings_table.php
│       └── xxxx_create_device_tokens_table.php
├── routes/
│   ├── api.php (modify — add QBO and device token routes)
│   └── channels.php (modify — add tenant broadcast channel auth)
└── tests/
    ├── Unit/
    │   ├── QuickBooks/
    │   │   ├── QuickBooksClientTest.php
    │   │   ├── QuickBooksAuthServiceTest.php
    │   │   ├── QuickBooksCustomerServiceTest.php
    │   │   ├── QuickBooksEmployeeServiceTest.php
    │   │   ├── QuickBooksEstimateServiceTest.php
    │   │   ├── QuickBooksInvoiceServiceTest.php
    │   │   └── QuickBooksBankFeedServiceTest.php
    │   └── Notifications/
    │       └── FcmChannelTest.php
    └── Feature/
        ├── QuickBooks/
        │   ├── QuickBooksAuthTest.php
        │   ├── QuickBooksCustomerSyncTest.php
        │   ├── QuickBooksEmployeeSyncTest.php
        │   ├── QuickBooksEstimateTest.php
        │   ├── QuickBooksInvoiceTest.php
        │   ├── QuickBooksBankFeedTest.php
        │   ├── QuickBooksServiceItemTest.php
        │   └── QuickBooksWebhookTest.php
        ├── Broadcasting/
        │   └── TenantBroadcastTest.php
        ├── Notifications/
        │   └── PushNotificationTest.php
        └── Jobs/
            ├── TrialExpirationTest.php
            ├── TransferReversionTest.php
            └── QboRetrySyncTest.php
```

---

## Task 1: QBO Configuration & Migration

**Files:**
- Create: `config/quickbooks.php`
- Create: `database/migrations/xxxx_add_qbo_columns_to_tenants_table.php`
- Create: `database/migrations/xxxx_create_qbo_sync_log_table.php`
- Create: `database/migrations/xxxx_create_qbo_service_item_mappings_table.php`

- [ ] **Step 1: Create QuickBooks config**

```php
// config/quickbooks.php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | QuickBooks Online API Configuration
    |--------------------------------------------------------------------------
    */

    'client_id' => env('QBO_CLIENT_ID'),
    'client_secret' => env('QBO_CLIENT_SECRET'),

    // OAuth endpoints
    'auth_url' => env('QBO_AUTH_URL', 'https://appcenter.intuit.com/connect/oauth2'),
    'token_url' => env('QBO_TOKEN_URL', 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer'),

    // API base URL (sandbox vs production)
    'base_url' => env('QBO_BASE_URL', 'https://sandbox-quickbooks.api.intuit.com'),

    // OAuth redirect URI
    'redirect_uri' => env('QBO_REDIRECT_URI', '/api/v1/qbo/callback'),

    // Scopes
    'scopes' => 'com.intuit.quickbooks.accounting',

    // Rate limiting
    'rate_limit' => [
        'max_requests' => 500,
        'per_minutes' => 1,
    ],

    // Batch API
    'batch_max_operations' => 30,

    // Token refresh buffer (refresh 5 minutes before expiry)
    'token_refresh_buffer_minutes' => 5,

    // Rutter (bank feeds middleware)
    'rutter' => [
        'base_url' => env('RUTTER_BASE_URL', 'https://production.rutterapi.com'),
        'client_id' => env('RUTTER_CLIENT_ID'),
        'secret' => env('RUTTER_SECRET'),
    ],

    // Webhook verification token
    'webhook_verifier_token' => env('QBO_WEBHOOK_VERIFIER_TOKEN'),
];
```

- [ ] **Step 2: Create migration to add QBO columns to tenants**

```php
// database/migrations/2024_01_01_000010_add_qbo_columns_to_tenants_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('qbo_realm_id', 50)->nullable()->after('status');
            $table->text('qbo_access_token')->nullable()->after('qbo_realm_id');
            $table->text('qbo_refresh_token')->nullable()->after('qbo_access_token');
            $table->timestamp('qbo_token_expires_at')->nullable()->after('qbo_refresh_token');
            $table->string('rutter_access_token')->nullable()->after('qbo_token_expires_at');
            $table->index('qbo_realm_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['qbo_realm_id']);
            $table->dropColumn([
                'qbo_realm_id',
                'qbo_access_token',
                'qbo_refresh_token',
                'qbo_token_expires_at',
                'rutter_access_token',
            ]);
        });
    }
};
```

- [ ] **Step 3: Create QBO sync log migration**

```php
// database/migrations/2024_01_01_000011_create_qbo_sync_log_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qbo_sync_log', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('tenant_id');
            $table->string('entity_type', 30); // ESTIMATE, INVOICE, CUSTOMER, EMPLOYEE, PAYMENT, BANK_FEED
            $table->uuid('geotime_entity_id')->nullable();
            $table->string('qbo_entity_id', 50)->nullable();
            $table->string('direction', 10); // PUSH, PULL
            $table->string('status', 20); // SUCCESS, FAILED, PENDING
            $table->text('error_message')->nullable();
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'entity_type']);
            $table->index(['status', 'next_retry_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qbo_sync_log');
    }
};
```

- [ ] **Step 4: Create QBO service item mappings migration**

```php
// database/migrations/2024_01_01_000012_create_qbo_service_item_mappings_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qbo_service_item_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('tenant_id');
            $table->string('geotime_job_type', 100); // e.g., "General Labor", "Supervision"
            $table->string('qbo_item_id', 50);
            $table->string('qbo_item_name', 255);
            $table->decimal('default_rate', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'geotime_job_type']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qbo_service_item_mappings');
    }
};
```

- [ ] **Step 5: Update .env.example with QBO variables**

Add these lines to `.env.example`:

```dotenv
# QuickBooks Online
QBO_CLIENT_ID=
QBO_CLIENT_SECRET=
QBO_AUTH_URL=https://appcenter.intuit.com/connect/oauth2
QBO_TOKEN_URL=https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer
QBO_BASE_URL=https://sandbox-quickbooks.api.intuit.com
QBO_REDIRECT_URI=/api/v1/qbo/callback
QBO_WEBHOOK_VERIFIER_TOKEN=

# Rutter (Bank Feeds)
RUTTER_BASE_URL=https://production.rutterapi.com
RUTTER_CLIENT_ID=
RUTTER_SECRET=

# Firebase Cloud Messaging
FCM_CREDENTIALS_FILE=storage/app/firebase-credentials.json
```

- [ ] **Step 6: Run migrations**

Run: `docker compose exec app php artisan migrate`
Expected: 3 new migrations run successfully.

- [ ] **Step 7: Commit**

```bash
git add config/quickbooks.php database/migrations/*qbo* database/migrations/*service_item* .env.example
git commit -m "feat: add QBO config, sync log migration, and service item mappings table"
```

---

## Task 2: QBO Sync Log & Service Item Mapping Models

**Files:**
- Create: `app/Models/QboSyncLog.php`
- Create: `app/Models/QboServiceItemMapping.php`
- Create: `tests/Unit/QuickBooks/QboSyncLogTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/QuickBooks/QboSyncLogTest.php
<?php

namespace Tests\Unit\QuickBooks;

use App\Models\QboSyncLog;
use App\Models\QboServiceItemMapping;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QboSyncLogTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);
    }

    public function test_can_create_sync_log_entry(): void
    {
        $tenant = $this->createTenant();

        $log = QboSyncLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'INVOICE',
            'geotime_entity_id' => fake()->uuid(),
            'qbo_entity_id' => '123',
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
            'request_payload' => ['Line' => [['Amount' => 500]]],
            'response_payload' => ['Id' => '123', 'SyncToken' => '0'],
        ]);

        $this->assertNotNull($log->id);
        $this->assertEquals('INVOICE', $log->entity_type);
        $this->assertEquals('PUSH', $log->direction);
        $this->assertEquals('SUCCESS', $log->status);
        $this->assertIsArray($log->request_payload);
    }

    public function test_sync_log_belongs_to_tenant(): void
    {
        $tenant = $this->createTenant();

        $log = QboSyncLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'CUSTOMER',
            'direction' => 'PULL',
            'status' => 'SUCCESS',
        ]);

        $this->assertEquals($tenant->id, $log->tenant->id);
    }

    public function test_can_filter_failed_sync_logs(): void
    {
        $tenant = $this->createTenant();

        QboSyncLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'INVOICE',
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
        ]);

        QboSyncLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'ESTIMATE',
            'direction' => 'PUSH',
            'status' => 'FAILED',
            'error_message' => 'Rate limit exceeded',
            'retry_count' => 1,
            'next_retry_at' => now()->addMinutes(5),
        ]);

        $failed = QboSyncLog::where('status', 'FAILED')->get();
        $this->assertCount(1, $failed);
        $this->assertEquals('Rate limit exceeded', $failed->first()->error_message);
    }

    public function test_can_create_service_item_mapping(): void
    {
        $tenant = $this->createTenant();

        $mapping = QboServiceItemMapping::create([
            'tenant_id' => $tenant->id,
            'geotime_job_type' => 'General Labor',
            'qbo_item_id' => '42',
            'qbo_item_name' => 'Labor - General',
            'default_rate' => 45.00,
        ]);

        $this->assertNotNull($mapping->id);
        $this->assertEquals('General Labor', $mapping->geotime_job_type);
        $this->assertEquals(45.00, $mapping->default_rate);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Unit/QuickBooks/QboSyncLogTest.php`
Expected: FAIL — models do not exist.

- [ ] **Step 3: Create QboSyncLog model**

```php
// app/Models/QboSyncLog.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QboSyncLog extends Model
{
    use HasUuids, BelongsToTenant;

    protected $table = 'qbo_sync_log';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'geotime_entity_id',
        'qbo_entity_id',
        'direction',
        'status',
        'error_message',
        'request_payload',
        'response_payload',
        'retry_count',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'next_retry_at' => 'datetime',
        ];
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'FAILED');
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', 'FAILED')
            ->where('retry_count', '<', 5)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                  ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function markSuccess(array $responsePayload = []): void
    {
        $this->update([
            'status' => 'SUCCESS',
            'response_payload' => $responsePayload,
            'error_message' => null,
        ]);
    }

    public function markFailed(string $errorMessage, array $responsePayload = []): void
    {
        $retryCount = $this->retry_count + 1;
        $backoffMinutes = min(pow(2, $retryCount), 60); // Exponential backoff: 2, 4, 8, 16, 32, 60 min

        $this->update([
            'status' => 'FAILED',
            'error_message' => $errorMessage,
            'response_payload' => $responsePayload,
            'retry_count' => $retryCount,
            'next_retry_at' => now()->addMinutes($backoffMinutes),
        ]);
    }
}
```

- [ ] **Step 4: Create QboServiceItemMapping model**

```php
// app/Models/QboServiceItemMapping.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QboServiceItemMapping extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'geotime_job_type',
        'qbo_item_id',
        'qbo_item_name',
        'default_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 5: Update Tenant model with QBO casts and relationship**

Add these to the Tenant model:

In the `$fillable` array, add:
```php
'qbo_realm_id',
'qbo_access_token',
'qbo_refresh_token',
'qbo_token_expires_at',
'rutter_access_token',
```

In the `casts()` method, add:
```php
'qbo_access_token' => 'encrypted',
'qbo_refresh_token' => 'encrypted',
'qbo_token_expires_at' => 'datetime',
'rutter_access_token' => 'encrypted',
```

Add these methods:
```php
public function isQboConnected(): bool
{
    return ! empty($this->qbo_realm_id) && ! empty($this->qbo_refresh_token);
}

public function isQboTokenExpired(): bool
{
    if (! $this->qbo_token_expires_at) {
        return true;
    }

    return $this->qbo_token_expires_at->subMinutes(
        config('quickbooks.token_refresh_buffer_minutes', 5)
    )->isPast();
}

public function qboSyncLogs()
{
    return $this->hasMany(QboSyncLog::class);
}

public function qboServiceItemMappings()
{
    return $this->hasMany(QboServiceItemMapping::class);
}
```

- [ ] **Step 6: Run tests**

Run: `docker compose exec app php artisan test tests/Unit/QuickBooks/QboSyncLogTest.php`
Expected: All 4 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Models/QboSyncLog.php app/Models/QboServiceItemMapping.php app/Models/Tenant.php tests/Unit/QuickBooks/
git commit -m "feat: add QboSyncLog and QboServiceItemMapping models with tests"
```

---

## Task 3: QuickBooks HTTP Client

**Files:**
- Create: `app/Services/QuickBooks/QuickBooksClient.php`
- Create: `tests/Unit/QuickBooks/QuickBooksClientTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/QuickBooks/QuickBooksClientTest.php
<?php

namespace Tests\Unit\QuickBooks;

use App\Models\Tenant;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuickBooksClientTest extends TestCase
{
    use RefreshDatabase;

    private function createConnectedTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'fake-access-token',
            'qbo_refresh_token' => 'fake-refresh-token',
            'qbo_token_expires_at' => now()->addMinutes(30),
        ]);
    }

    public function test_can_make_get_request(): void
    {
        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'QueryResponse' => [
                    'Customer' => [
                        ['Id' => '1', 'DisplayName' => 'Test Client'],
                    ],
                ],
            ], 200),
        ]);

        $tenant = $this->createConnectedTenant();
        $client = new QuickBooksClient($tenant);

        $response = $client->get('/v3/company/1234567890/query', [
            'query' => "SELECT * FROM Customer",
        ]);

        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('QueryResponse', $response->json());
    }

    public function test_can_make_post_request(): void
    {
        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'Invoice' => [
                    'Id' => '42',
                    'SyncToken' => '0',
                    'TotalAmt' => 500.00,
                ],
            ], 200),
        ]);

        $tenant = $this->createConnectedTenant();
        $client = new QuickBooksClient($tenant);

        $response = $client->post('/v3/company/1234567890/invoice', [
            'Line' => [
                [
                    'Amount' => 500.00,
                    'DetailType' => 'SalesItemLineDetail',
                ],
            ],
            'CustomerRef' => ['value' => '1'],
        ]);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('42', $response->json('Invoice.Id'));
    }

    public function test_auto_refreshes_expired_token(): void
    {
        $tenant = $this->createConnectedTenant();
        $tenant->qbo_token_expires_at = now()->subMinutes(5); // expired
        $tenant->save();

        Http::fake([
            // Token refresh endpoint
            'oauth.platform.intuit.com/*' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ], 200),
            // Actual API call
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'QueryResponse' => ['Customer' => []],
            ], 200),
        ]);

        $client = new QuickBooksClient($tenant);
        $response = $client->get('/v3/company/1234567890/query', [
            'query' => "SELECT * FROM Customer",
        ]);

        $this->assertEquals(200, $response->status());

        // Verify token was refreshed
        $tenant->refresh();
        $this->assertTrue($tenant->qbo_token_expires_at->isFuture());
    }

    public function test_batch_request(): void
    {
        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'BatchItemResponse' => [
                    ['bId' => '1', 'Customer' => ['Id' => '10']],
                    ['bId' => '2', 'Customer' => ['Id' => '11']],
                ],
            ], 200),
        ]);

        $tenant = $this->createConnectedTenant();
        $client = new QuickBooksClient($tenant);

        $operations = [
            ['bId' => '1', 'operation' => 'create', 'Customer' => ['DisplayName' => 'Client A']],
            ['bId' => '2', 'operation' => 'create', 'Customer' => ['DisplayName' => 'Client B']],
        ];

        $response = $client->batch($operations);

        $this->assertEquals(200, $response->status());
        $this->assertCount(2, $response->json('BatchItemResponse'));
    }

    public function test_throws_exception_when_not_connected(): void
    {
        $tenant = Tenant::create([
            'name' => 'Not Connected Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $this->expectException(\App\Exceptions\QuickBooksNotConnectedException::class);

        $client = new QuickBooksClient($tenant);
        $client->get('/v3/company/test/query');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Unit/QuickBooks/QuickBooksClientTest.php`
Expected: FAIL — class does not exist.

- [ ] **Step 3: Create QuickBooksNotConnectedException**

```php
// app/Exceptions/QuickBooksNotConnectedException.php
<?php

namespace App\Exceptions;

use RuntimeException;

class QuickBooksNotConnectedException extends RuntimeException
{
    public function __construct(string $message = 'QuickBooks Online is not connected for this tenant.')
    {
        parent::__construct($message);
    }
}
```

- [ ] **Step 4: Create QuickBooksClient**

```php
// app/Services/QuickBooks/QuickBooksClient.php
<?php

namespace App\Services\QuickBooks;

use App\Exceptions\QuickBooksNotConnectedException;
use App\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuickBooksClient
{
    private Tenant $tenant;
    private string $baseUrl;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->baseUrl = rtrim(config('quickbooks.base_url'), '/');
    }

    /**
     * Make a GET request to the QBO API.
     */
    public function get(string $path, array $query = []): Response
    {
        $this->ensureConnected();
        $this->ensureTokenFresh();

        return Http::withToken($this->tenant->qbo_access_token)
            ->accept('application/json')
            ->get($this->baseUrl . $path, $query);
    }

    /**
     * Make a POST request to the QBO API.
     */
    public function post(string $path, array $data = []): Response
    {
        $this->ensureConnected();
        $this->ensureTokenFresh();

        return Http::withToken($this->tenant->qbo_access_token)
            ->accept('application/json')
            ->contentType('application/json')
            ->post($this->baseUrl . $path, $data);
    }

    /**
     * Make a batch API request (up to 30 operations).
     */
    public function batch(array $operations): Response
    {
        $this->ensureConnected();
        $this->ensureTokenFresh();

        $maxOps = config('quickbooks.batch_max_operations', 30);
        $operations = array_slice($operations, 0, $maxOps);

        $path = "/v3/company/{$this->tenant->qbo_realm_id}/batch";

        return Http::withToken($this->tenant->qbo_access_token)
            ->accept('application/json')
            ->contentType('application/json')
            ->post($this->baseUrl . $path, [
                'BatchItemRequest' => $operations,
            ]);
    }

    /**
     * Query QBO entities using SQL-like syntax.
     */
    public function query(string $sql): Response
    {
        $path = "/v3/company/{$this->tenant->qbo_realm_id}/query";

        return $this->get($path, ['query' => $sql]);
    }

    /**
     * Get the realm ID (company ID) for this tenant.
     */
    public function getRealmId(): string
    {
        return $this->tenant->qbo_realm_id;
    }

    /**
     * Ensure the tenant has a QBO connection.
     */
    private function ensureConnected(): void
    {
        if (! $this->tenant->isQboConnected()) {
            throw new QuickBooksNotConnectedException();
        }
    }

    /**
     * Refresh the access token if it's expired or about to expire.
     */
    private function ensureTokenFresh(): void
    {
        if (! $this->tenant->isQboTokenExpired()) {
            return;
        }

        $this->refreshToken();
    }

    /**
     * Refresh the OAuth access token using the refresh token.
     */
    public function refreshToken(): void
    {
        $tokenUrl = config('quickbooks.token_url');

        $response = Http::asForm()
            ->withBasicAuth(
                config('quickbooks.client_id'),
                config('quickbooks.client_secret')
            )
            ->post($tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->tenant->qbo_refresh_token,
            ]);

        if (! $response->successful()) {
            Log::error('QBO token refresh failed', [
                'tenant_id' => $this->tenant->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to refresh QuickBooks access token.');
        }

        $data = $response->json();

        $this->tenant->update([
            'qbo_access_token' => $data['access_token'],
            'qbo_refresh_token' => $data['refresh_token'],
            'qbo_token_expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        $this->tenant->refresh();
    }
}
```

- [ ] **Step 5: Run tests**

Run: `docker compose exec app php artisan test tests/Unit/QuickBooks/QuickBooksClientTest.php`
Expected: All 5 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/QuickBooks/QuickBooksClient.php app/Exceptions/QuickBooksNotConnectedException.php tests/Unit/QuickBooks/QuickBooksClientTest.php
git commit -m "feat: add QuickBooksClient HTTP wrapper with auto token refresh and batch support"
```

---

## Task 4: QBO OAuth 2.0 Flow

**Files:**
- Create: `app/Services/QuickBooks/QuickBooksAuthService.php`
- Create: `app/Http/Controllers/Api/QuickBooksAuthController.php`
- Create: `app/Http/Middleware/EnsureQuickBooksConnected.php`
- Create: `tests/Feature/QuickBooks/QuickBooksAuthTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/QuickBooks/QuickBooksAuthTest.php
<?php

namespace Tests\Feature\QuickBooks;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuickBooksAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): array
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

        return [$tenant, $user];
    }

    public function test_connect_endpoint_returns_auth_url(): void
    {
        [$tenant, $user] = $this->createAdminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/qbo/connect');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['auth_url']]);

        $authUrl = $response->json('data.auth_url');
        $this->assertStringContainsString('appcenter.intuit.com', $authUrl);
        $this->assertStringContainsString('response_type=code', $authUrl);
    }

    public function test_callback_exchanges_code_for_tokens(): void
    {
        [$tenant, $user] = $this->createAdminUser();

        Http::fake([
            'oauth.platform.intuit.com/*' => Http::response([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ], 200),
        ]);

        // Simulate the state by setting it in session/cache
        cache()->put("qbo_state_{$tenant->id}", 'test-state', 600);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/qbo/callback?' . http_build_query([
                'code' => 'auth-code-from-intuit',
                'state' => 'test-state',
                'realmId' => '9876543210',
            ]));

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'connected' => true,
                    'realm_id' => '9876543210',
                ],
            ]);

        // Verify tokens stored on tenant
        $tenant->refresh();
        $this->assertEquals('9876543210', $tenant->qbo_realm_id);
        $this->assertNotNull($tenant->qbo_access_token);
        $this->assertNotNull($tenant->qbo_refresh_token);
        $this->assertTrue($tenant->qbo_token_expires_at->isFuture());
    }

    public function test_callback_rejects_invalid_state(): void
    {
        [$tenant, $user] = $this->createAdminUser();

        cache()->put("qbo_state_{$tenant->id}", 'valid-state', 600);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/qbo/callback?' . http_build_query([
                'code' => 'auth-code',
                'state' => 'wrong-state',
                'realmId' => '9876543210',
            ]));

        $response->assertStatus(422);
    }

    public function test_disconnect_clears_qbo_credentials(): void
    {
        [$tenant, $user] = $this->createAdminUser();

        $tenant->update([
            'qbo_realm_id' => '9876543210',
            'qbo_access_token' => 'token',
            'qbo_refresh_token' => 'refresh',
            'qbo_token_expires_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/qbo/disconnect');

        $response->assertStatus(200)
            ->assertJson(['data' => ['connected' => false]]);

        $tenant->refresh();
        $this->assertNull($tenant->qbo_realm_id);
        $this->assertNull($tenant->qbo_access_token);
    }

    public function test_status_endpoint_returns_connection_state(): void
    {
        [$tenant, $user] = $this->createAdminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/qbo/status');

        $response->assertStatus(200)
            ->assertJson(['data' => ['connected' => false]]);

        $tenant->update([
            'qbo_realm_id' => '123',
            'qbo_refresh_token' => 'token',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/qbo/status');

        $response->assertStatus(200)
            ->assertJson(['data' => ['connected' => true]]);
    }

    public function test_non_admin_cannot_connect_qbo(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $employee = User::withoutGlobalScopes()->create([
            'name' => 'Employee',
            'email' => 'emp@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/qbo/connect');

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksAuthTest.php`
Expected: FAIL — routes and controllers don't exist.

- [ ] **Step 3: Create QuickBooksAuthService**

```php
// app/Services/QuickBooks/QuickBooksAuthService.php
<?php

namespace App\Services\QuickBooks;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class QuickBooksAuthService
{
    /**
     * Generate the OAuth 2.0 authorization URL.
     */
    public function getAuthUrl(Tenant $tenant): string
    {
        $state = Str::random(40);

        // Store state for CSRF verification (10 minutes TTL)
        Cache::put("qbo_state_{$tenant->id}", $state, 600);

        $params = http_build_query([
            'client_id' => config('quickbooks.client_id'),
            'response_type' => 'code',
            'scope' => config('quickbooks.scopes'),
            'redirect_uri' => url(config('quickbooks.redirect_uri')),
            'state' => $state,
        ]);

        return config('quickbooks.auth_url') . '?' . $params;
    }

    /**
     * Exchange authorization code for access and refresh tokens.
     */
    public function exchangeCodeForTokens(Tenant $tenant, string $code, string $state, string $realmId): void
    {
        // Verify state (CSRF protection)
        $storedState = Cache::pull("qbo_state_{$tenant->id}");

        if (! $storedState || $storedState !== $state) {
            throw new \InvalidArgumentException('Invalid OAuth state parameter.');
        }

        $response = Http::asForm()
            ->withBasicAuth(
                config('quickbooks.client_id'),
                config('quickbooks.client_secret')
            )
            ->post(config('quickbooks.token_url'), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => url(config('quickbooks.redirect_uri')),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to exchange authorization code: ' . $response->body());
        }

        $data = $response->json();

        $tenant->update([
            'qbo_realm_id' => $realmId,
            'qbo_access_token' => $data['access_token'],
            'qbo_refresh_token' => $data['refresh_token'],
            'qbo_token_expires_at' => now()->addSeconds($data['expires_in']),
        ]);
    }

    /**
     * Disconnect QBO by clearing all credentials.
     */
    public function disconnect(Tenant $tenant): void
    {
        // Optionally revoke token at Intuit (best effort)
        if ($tenant->qbo_access_token) {
            Http::asForm()
                ->withBasicAuth(
                    config('quickbooks.client_id'),
                    config('quickbooks.client_secret')
                )
                ->post('https://developer.api.intuit.com/v2/oauth2/tokens/revoke', [
                    'token' => $tenant->qbo_refresh_token,
                ]);
        }

        $tenant->update([
            'qbo_realm_id' => null,
            'qbo_access_token' => null,
            'qbo_refresh_token' => null,
            'qbo_token_expires_at' => null,
        ]);
    }
}
```

- [ ] **Step 4: Create QuickBooksAuthController**

```php
// app/Http/Controllers/Api/QuickBooksAuthController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuickBooks\QuickBooksAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickBooksAuthController extends Controller
{
    public function __construct(
        private QuickBooksAuthService $authService
    ) {}

    /**
     * GET /api/v1/qbo/connect — returns auth URL for QBO OAuth.
     */
    public function connect(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $tenant = app('current_tenant');
        $authUrl = $this->authService->getAuthUrl($tenant);

        return response()->json([
            'data' => ['auth_url' => $authUrl],
        ]);
    }

    /**
     * GET /api/v1/qbo/callback — handles OAuth redirect from Intuit.
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
            'realmId' => 'required|string',
        ]);

        $tenant = app('current_tenant');

        try {
            $this->authService->exchangeCodeForTokens(
                $tenant,
                $request->query('code'),
                $request->query('state'),
                $request->query('realmId')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'connected' => true,
                'realm_id' => $request->query('realmId'),
            ],
        ]);
    }

    /**
     * POST /api/v1/qbo/disconnect — clears QBO credentials.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $tenant = app('current_tenant');
        $this->authService->disconnect($tenant);

        return response()->json([
            'data' => ['connected' => false],
        ]);
    }

    /**
     * GET /api/v1/qbo/status — returns QBO connection status.
     */
    public function status(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        return response()->json([
            'data' => [
                'connected' => $tenant->isQboConnected(),
                'realm_id' => $tenant->qbo_realm_id,
            ],
        ]);
    }
}
```

- [ ] **Step 5: Create EnsureQuickBooksConnected middleware**

```php
// app/Http/Middleware/EnsureQuickBooksConnected.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureQuickBooksConnected
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if (! $tenant || ! $tenant->isQboConnected()) {
            return response()->json([
                'message' => 'QuickBooks Online is not connected. Please connect your QBO account first.',
            ], 428); // 428 Precondition Required
        }

        return $next($request);
    }
}
```

- [ ] **Step 6: Add QBO auth routes to api.php**

Add to the existing `Route::prefix('v1')` group in `routes/api.php`:

```php
use App\Http\Controllers\Api\QuickBooksAuthController;

// Inside the v1 prefix group, after existing routes:

// QBO Auth (requires authentication)
Route::middleware('auth:sanctum')->prefix('qbo')->group(function () {
    Route::get('/connect', [QuickBooksAuthController::class, 'connect']);
    Route::get('/callback', [QuickBooksAuthController::class, 'callback']);
    Route::post('/disconnect', [QuickBooksAuthController::class, 'disconnect']);
    Route::get('/status', [QuickBooksAuthController::class, 'status']);
});
```

- [ ] **Step 7: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksAuthTest.php`
Expected: All 6 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/QuickBooks/QuickBooksAuthService.php app/Http/Controllers/Api/QuickBooksAuthController.php app/Http/Middleware/EnsureQuickBooksConnected.php routes/api.php tests/Feature/QuickBooks/QuickBooksAuthTest.php
git commit -m "feat: add QBO OAuth 2.0 connect/disconnect/callback/status endpoints"
```

---

## Task 5: QBO Customer Sync (Bidirectional)

**Files:**
- Create: `app/Services/QuickBooks/QuickBooksCustomerService.php`
- Create: `app/Http/Controllers/Api/QuickBooksCustomerController.php`
- Create: `app/Jobs/QboSyncCustomersJob.php`
- Create: `tests/Feature/QuickBooks/QuickBooksCustomerSyncTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/QuickBooks/QuickBooksCustomerSyncTest.php
<?php

namespace Tests\Feature\QuickBooks;

use App\Jobs\QboSyncCustomersJob;
use App\Models\Job as GeoTimeJob;
use App\Models\QboSyncLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QuickBooksCustomerSyncTest extends TestCase
{
    use RefreshDatabase;

    private function createConnectedTenantWithAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'fake-access-token',
            'qbo_refresh_token' => 'fake-refresh-token',
            'qbo_token_expires_at' => now()->addMinutes(30),
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

    public function test_pull_customers_from_qbo(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'QueryResponse' => [
                    'Customer' => [
                        [
                            'Id' => '1',
                            'DisplayName' => 'Acme Corp',
                            'PrimaryEmailAddr' => ['Address' => 'acme@test.com'],
                            'BillAddr' => [
                                'Line1' => '123 Main St',
                                'City' => 'Austin',
                                'CountrySubDivisionCode' => 'TX',
                                'PostalCode' => '78701',
                            ],
                            'Active' => true,
                        ],
                        [
                            'Id' => '2',
                            'DisplayName' => 'Beta LLC',
                            'Active' => true,
                        ],
                    ],
                    'maxResults' => 2,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/qbo/customers/pull');

        $response->assertStatus(200)
            ->assertJsonPath('data.synced_count', 2);

        // Verify sync log created
        $this->assertDatabaseHas('qbo_sync_log', [
            'tenant_id' => $tenant->id,
            'entity_type' => 'CUSTOMER',
            'direction' => 'PULL',
            'status' => 'SUCCESS',
        ]);
    }

    public function test_push_customer_to_qbo(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        $job = GeoTimeJob::create([
            'tenant_id' => $tenant->id,
            'name' => 'Website Redesign',
            'client_name' => 'New Client Inc',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 75.00,
        ]);

        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'Customer' => [
                    'Id' => '99',
                    'DisplayName' => 'New Client Inc',
                    'SyncToken' => '0',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/qbo/customers/push/{$job->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.qbo_customer_id', '99');

        // Verify job updated with QBO customer ID
        $job->refresh();
        $this->assertEquals('99', $job->qbo_customer_id);

        // Verify sync log
        $this->assertDatabaseHas('qbo_sync_log', [
            'entity_type' => 'CUSTOMER',
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
            'qbo_entity_id' => '99',
        ]);
    }

    public function test_sync_customers_job_dispatches(): void
    {
        Queue::fake();

        [$tenant, $user] = $this->createConnectedTenantWithAdmin();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/qbo/customers/sync');

        $response->assertStatus(202);

        Queue::assertPushed(QboSyncCustomersJob::class, function ($job) use ($tenant) {
            return $job->tenantId === $tenant->id;
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksCustomerSyncTest.php`
Expected: FAIL — service, controller, job do not exist.

- [ ] **Step 3: Create QuickBooksCustomerService**

```php
// app/Services/QuickBooks/QuickBooksCustomerService.php
<?php

namespace App\Services\QuickBooks;

use App\Models\Job as GeoTimeJob;
use App\Models\QboSyncLog;
use App\Models\Tenant;

class QuickBooksCustomerService
{
    private QuickBooksClient $client;
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->client = new QuickBooksClient($tenant);
    }

    /**
     * Pull all customers from QBO into local reference.
     *
     * @return array{customers: array, synced_count: int}
     */
    public function pullCustomers(): array
    {
        $response = $this->client->query("SELECT * FROM Customer WHERE Active = true MAXRESULTS 1000");

        $customers = $response->json('QueryResponse.Customer', []);

        $syncLog = QboSyncLog::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'CUSTOMER',
            'direction' => 'PULL',
            'status' => 'SUCCESS',
            'response_payload' => ['customer_count' => count($customers)],
        ]);

        // Update any GeoTime jobs that match QBO customer IDs
        foreach ($customers as $customer) {
            GeoTimeJob::where('tenant_id', $this->tenant->id)
                ->where('qbo_customer_id', $customer['Id'])
                ->update([
                    'client_name' => $customer['DisplayName'],
                ]);
        }

        return [
            'customers' => $customers,
            'synced_count' => count($customers),
        ];
    }

    /**
     * Push a GeoTime job's client to QBO as a Customer.
     *
     * @return array{qbo_customer_id: string}
     */
    public function pushCustomer(GeoTimeJob $job): array
    {
        $realmId = $this->client->getRealmId();

        $customerData = [
            'DisplayName' => $job->client_name,
        ];

        // If job has address info, add it
        if ($job->address) {
            $customerData['BillAddr'] = [
                'Line1' => $job->address,
            ];
        }

        $response = $this->client->post("/v3/company/{$realmId}/customer", $customerData);

        $qboCustomerId = $response->json('Customer.Id');

        // Update the job with QBO customer ID
        $job->update(['qbo_customer_id' => $qboCustomerId]);

        // Log the sync
        QboSyncLog::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'CUSTOMER',
            'geotime_entity_id' => $job->id,
            'qbo_entity_id' => $qboCustomerId,
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
            'request_payload' => $customerData,
            'response_payload' => $response->json(),
        ]);

        return ['qbo_customer_id' => $qboCustomerId];
    }

    /**
     * Find or create a QBO customer from a GeoTime job.
     */
    public function findOrCreateCustomer(GeoTimeJob $job): string
    {
        if ($job->qbo_customer_id) {
            return $job->qbo_customer_id;
        }

        // Search QBO for existing customer by name
        $response = $this->client->query(
            "SELECT * FROM Customer WHERE DisplayName = '{$job->client_name}'"
        );

        $existing = $response->json('QueryResponse.Customer.0');

        if ($existing) {
            $job->update(['qbo_customer_id' => $existing['Id']]);
            return $existing['Id'];
        }

        // Create new customer
        $result = $this->pushCustomer($job);
        return $result['qbo_customer_id'];
    }
}
```

- [ ] **Step 4: Create QboSyncCustomersJob**

```php
// app/Jobs/QboSyncCustomersJob.php
<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\QuickBooks\QuickBooksCustomerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QboSyncCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $tenantId
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        app()->instance('current_tenant', $tenant);

        try {
            $service = new QuickBooksCustomerService($tenant);
            $result = $service->pullCustomers();

            Log::info('QBO customer sync completed', [
                'tenant_id' => $this->tenantId,
                'synced_count' => $result['synced_count'],
            ]);
        } catch (\Exception $e) {
            Log::error('QBO customer sync failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

- [ ] **Step 5: Create QuickBooksCustomerController**

```php
// app/Http/Controllers/Api/QuickBooksCustomerController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\QboSyncCustomersJob;
use App\Models\Job as GeoTimeJob;
use App\Services\QuickBooks\QuickBooksCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickBooksCustomerController extends Controller
{
    /**
     * POST /api/v1/qbo/customers/pull — pull customers from QBO now.
     */
    public function pull(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');
        $service = new QuickBooksCustomerService($tenant);
        $result = $service->pullCustomers();

        return response()->json([
            'data' => [
                'synced_count' => $result['synced_count'],
                'customers' => $result['customers'],
            ],
        ]);
    }

    /**
     * POST /api/v1/qbo/customers/push/{job} — push a job's client to QBO.
     */
    public function push(Request $request, string $jobId): JsonResponse
    {
        $tenant = app('current_tenant');
        $job = GeoTimeJob::where('tenant_id', $tenant->id)->findOrFail($jobId);

        $service = new QuickBooksCustomerService($tenant);
        $result = $service->pushCustomer($job);

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * POST /api/v1/qbo/customers/sync — dispatch async bidirectional sync.
     */
    public function sync(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        QboSyncCustomersJob::dispatch($tenant->id);

        return response()->json([
            'data' => ['message' => 'Customer sync job dispatched.'],
        ], 202);
    }
}
```

- [ ] **Step 6: Add customer sync routes to api.php**

Add inside the `qbo` route group (after auth routes), wrapped with the QBO connected middleware:

```php
use App\Http\Controllers\Api\QuickBooksCustomerController;
use App\Http\Middleware\EnsureQuickBooksConnected;

// Inside the auth:sanctum + qbo prefix group:
Route::middleware(EnsureQuickBooksConnected::class)->group(function () {
    // Customers
    Route::post('/customers/pull', [QuickBooksCustomerController::class, 'pull']);
    Route::post('/customers/push/{job}', [QuickBooksCustomerController::class, 'push']);
    Route::post('/customers/sync', [QuickBooksCustomerController::class, 'sync']);
});
```

- [ ] **Step 7: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksCustomerSyncTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/QuickBooks/QuickBooksCustomerService.php app/Http/Controllers/Api/QuickBooksCustomerController.php app/Jobs/QboSyncCustomersJob.php routes/api.php tests/Feature/QuickBooks/QuickBooksCustomerSyncTest.php
git commit -m "feat: add QBO customer sync (pull/push/async bidirectional)"
```

---

## Task 6: QBO Employee Sync

**Files:**
- Create: `app/Services/QuickBooks/QuickBooksEmployeeService.php`
- Create: `app/Http/Controllers/Api/QuickBooksEmployeeController.php`
- Create: `app/Jobs/QboSyncEmployeesJob.php`
- Create: `tests/Feature/QuickBooks/QuickBooksEmployeeSyncTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/QuickBooks/QuickBooksEmployeeSyncTest.php
<?php

namespace Tests\Feature\QuickBooks;

use App\Jobs\QboSyncEmployeesJob;
use App\Models\Employee;
use App\Models\QboSyncLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QuickBooksEmployeeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function createConnectedTenantWithAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'fake-access-token',
            'qbo_refresh_token' => 'fake-refresh-token',
            'qbo_token_expires_at' => now()->addMinutes(30),
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

    public function test_pull_employees_from_qbo(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'QueryResponse' => [
                    'Employee' => [
                        [
                            'Id' => '10',
                            'GivenName' => 'John',
                            'FamilyName' => 'Doe',
                            'PrimaryEmailAddr' => ['Address' => 'john@test.com'],
                            'Active' => true,
                        ],
                        [
                            'Id' => '11',
                            'GivenName' => 'Jane',
                            'FamilyName' => 'Smith',
                            'Active' => true,
                        ],
                    ],
                    'maxResults' => 2,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/qbo/employees/pull');

        $response->assertStatus(200)
            ->assertJsonPath('data.synced_count', 2);

        $this->assertDatabaseHas('qbo_sync_log', [
            'entity_type' => 'EMPLOYEE',
            'direction' => 'PULL',
            'status' => 'SUCCESS',
        ]);
    }

    public function test_push_employee_to_qbo(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Alice',
            'last_name' => 'Johnson',
            'email' => 'alice@test.com',
            'role' => 'employee',
            'hourly_rate' => 25.00,
            'status' => 'ACTIVE',
        ]);

        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'Employee' => [
                    'Id' => '55',
                    'GivenName' => 'Alice',
                    'FamilyName' => 'Johnson',
                    'SyncToken' => '0',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/qbo/employees/push/{$employee->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.qbo_employee_id', '55');

        $employee->refresh();
        $this->assertEquals('55', $employee->qbo_employee_id);
    }

    public function test_sync_employees_dispatches_job(): void
    {
        Queue::fake();
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/qbo/employees/sync');

        $response->assertStatus(202);
        Queue::assertPushed(QboSyncEmployeesJob::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksEmployeeSyncTest.php`
Expected: FAIL — service, controller, job do not exist.

- [ ] **Step 3: Create QuickBooksEmployeeService**

```php
// app/Services/QuickBooks/QuickBooksEmployeeService.php
<?php

namespace App\Services\QuickBooks;

use App\Models\Employee;
use App\Models\QboSyncLog;
use App\Models\Tenant;

class QuickBooksEmployeeService
{
    private QuickBooksClient $client;
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->client = new QuickBooksClient($tenant);
    }

    /**
     * Pull all employees from QBO.
     */
    public function pullEmployees(): array
    {
        $response = $this->client->query("SELECT * FROM Employee WHERE Active = true MAXRESULTS 1000");

        $qboEmployees = $response->json('QueryResponse.Employee', []);

        // Match QBO employees to GeoTime employees by qbo_employee_id or email
        foreach ($qboEmployees as $qboEmp) {
            $email = $qboEmp['PrimaryEmailAddr']['Address'] ?? null;

            $employee = Employee::where('tenant_id', $this->tenant->id)
                ->where(function ($q) use ($qboEmp, $email) {
                    $q->where('qbo_employee_id', $qboEmp['Id']);
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                })
                ->first();

            if ($employee) {
                $employee->update(['qbo_employee_id' => $qboEmp['Id']]);
            }
        }

        QboSyncLog::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'EMPLOYEE',
            'direction' => 'PULL',
            'status' => 'SUCCESS',
            'response_payload' => ['employee_count' => count($qboEmployees)],
        ]);

        return [
            'employees' => $qboEmployees,
            'synced_count' => count($qboEmployees),
        ];
    }

    /**
     * Push a GeoTime employee to QBO.
     */
    public function pushEmployee(Employee $employee): array
    {
        $realmId = $this->client->getRealmId();

        $employeeData = [
            'GivenName' => $employee->first_name,
            'FamilyName' => $employee->last_name,
        ];

        if ($employee->email) {
            $employeeData['PrimaryEmailAddr'] = ['Address' => $employee->email];
        }

        if ($employee->phone) {
            $employeeData['PrimaryPhone'] = ['FreeFormNumber' => $employee->phone];
        }

        $response = $this->client->post("/v3/company/{$realmId}/employee", $employeeData);

        $qboEmployeeId = $response->json('Employee.Id');

        $employee->update(['qbo_employee_id' => $qboEmployeeId]);

        QboSyncLog::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'EMPLOYEE',
            'geotime_entity_id' => $employee->id,
            'qbo_entity_id' => $qboEmployeeId,
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
            'request_payload' => $employeeData,
            'response_payload' => $response->json(),
        ]);

        return ['qbo_employee_id' => $qboEmployeeId];
    }
}
```

- [ ] **Step 4: Create QboSyncEmployeesJob**

```php
// app/Jobs/QboSyncEmployeesJob.php
<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\QuickBooks\QuickBooksEmployeeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QboSyncEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $tenantId
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        app()->instance('current_tenant', $tenant);

        try {
            $service = new QuickBooksEmployeeService($tenant);
            $result = $service->pullEmployees();

            Log::info('QBO employee sync completed', [
                'tenant_id' => $this->tenantId,
                'synced_count' => $result['synced_count'],
            ]);
        } catch (\Exception $e) {
            Log::error('QBO employee sync failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

- [ ] **Step 5: Create QuickBooksEmployeeController**

```php
// app/Http/Controllers/Api/QuickBooksEmployeeController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\QboSyncEmployeesJob;
use App\Models\Employee;
use App\Services\QuickBooks\QuickBooksEmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickBooksEmployeeController extends Controller
{
    public function pull(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');
        $service = new QuickBooksEmployeeService($tenant);
        $result = $service->pullEmployees();

        return response()->json([
            'data' => [
                'synced_count' => $result['synced_count'],
                'employees' => $result['employees'],
            ],
        ]);
    }

    public function push(Request $request, string $employeeId): JsonResponse
    {
        $tenant = app('current_tenant');
        $employee = Employee::where('tenant_id', $tenant->id)->findOrFail($employeeId);

        $service = new QuickBooksEmployeeService($tenant);
        $result = $service->pushEmployee($employee);

        return response()->json(['data' => $result]);
    }

    public function sync(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');
        QboSyncEmployeesJob::dispatch($tenant->id);

        return response()->json([
            'data' => ['message' => 'Employee sync job dispatched.'],
        ], 202);
    }
}
```

- [ ] **Step 6: Add employee sync routes**

Add inside the `EnsureQuickBooksConnected` middleware group in `routes/api.php`:

```php
use App\Http\Controllers\Api\QuickBooksEmployeeController;

// Employees
Route::post('/employees/pull', [QuickBooksEmployeeController::class, 'pull']);
Route::post('/employees/push/{employee}', [QuickBooksEmployeeController::class, 'push']);
Route::post('/employees/sync', [QuickBooksEmployeeController::class, 'sync']);
```

- [ ] **Step 7: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksEmployeeSyncTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/QuickBooks/QuickBooksEmployeeService.php app/Http/Controllers/Api/QuickBooksEmployeeController.php app/Jobs/QboSyncEmployeesJob.php routes/api.php tests/Feature/QuickBooks/QuickBooksEmployeeSyncTest.php
git commit -m "feat: add QBO employee sync (pull/push/async)"
```

---

## Task 7: QBO Estimate Generation

**Files:**
- Create: `app/Services/QuickBooks/QuickBooksEstimateService.php`
- Create: `app/Http/Controllers/Api/QuickBooksEstimateController.php`
- Create: `app/Jobs/QboPushEstimateJob.php`
- Create: `tests/Feature/QuickBooks/QuickBooksEstimateTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/QuickBooks/QuickBooksEstimateTest.php
<?php

namespace Tests\Feature\QuickBooks;

use App\Jobs\QboPushEstimateJob;
use App\Models\Job as GeoTimeJob;
use App\Models\QboServiceItemMapping;
use App\Models\QboSyncLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QuickBooksEstimateTest extends TestCase
{
    use RefreshDatabase;

    private function createConnectedTenantWithAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'fake-access-token',
            'qbo_refresh_token' => 'fake-refresh-token',
            'qbo_token_expires_at' => now()->addMinutes(30),
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

    public function test_generate_estimate_from_job(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        $job = GeoTimeJob::create([
            'tenant_id' => $tenant->id,
            'name' => 'Office Renovation',
            'client_name' => 'Acme Corp',
            'qbo_customer_id' => '1',
            'status' => 'ACTIVE',
            'budget_hours' => 200,
            'hourly_rate' => 75.00,
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
        ]);

        QboServiceItemMapping::create([
            'tenant_id' => $tenant->id,
            'geotime_job_type' => 'General Labor',
            'qbo_item_id' => '5',
            'qbo_item_name' => 'Labor Services',
            'default_rate' => 75.00,
        ]);

        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'Estimate' => [
                    'Id' => '201',
                    'SyncToken' => '0',
                    'TotalAmt' => 15000.00,
                    'TxnStatus' => 'Pending',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/qbo/estimates/generate/{$job->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.qbo_estimate_id', '201')
            ->assertJsonPath('data.total', 15000.00);

        $this->assertDatabaseHas('qbo_sync_log', [
            'entity_type' => 'ESTIMATE',
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
            'qbo_entity_id' => '201',
        ]);
    }

    public function test_pull_estimate_status(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'Estimate' => [
                    'Id' => '201',
                    'TxnStatus' => 'Accepted',
                    'TotalAmt' => 15000.00,
                    'SyncToken' => '1',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/qbo/estimates/201/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Accepted');
    }

    public function test_generate_estimate_requires_customer(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        $job = GeoTimeJob::create([
            'tenant_id' => $tenant->id,
            'name' => 'No Client Job',
            'client_name' => 'Test',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 50.00,
        ]);
        // No qbo_customer_id set

        Http::fake([
            // Customer search returns empty
            'sandbox-quickbooks.api.intuit.com/*/query*' => Http::response([
                'QueryResponse' => [],
            ], 200),
            // Customer create
            'sandbox-quickbooks.api.intuit.com/*/customer' => Http::response([
                'Customer' => ['Id' => '88', 'DisplayName' => 'Test'],
            ], 200),
            // Estimate create
            'sandbox-quickbooks.api.intuit.com/*/estimate' => Http::response([
                'Estimate' => ['Id' => '300', 'TotalAmt' => 5000.00, 'SyncToken' => '0', 'TxnStatus' => 'Pending'],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/qbo/estimates/generate/{$job->id}");

        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksEstimateTest.php`
Expected: FAIL — service/controller don't exist.

- [ ] **Step 3: Create QuickBooksEstimateService**

```php
// app/Services/QuickBooks/QuickBooksEstimateService.php
<?php

namespace App\Services\QuickBooks;

use App\Models\Job as GeoTimeJob;
use App\Models\QboServiceItemMapping;
use App\Models\QboSyncLog;
use App\Models\Tenant;

class QuickBooksEstimateService
{
    private QuickBooksClient $client;
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->client = new QuickBooksClient($tenant);
    }

    /**
     * Generate an estimate from a GeoTime job and push to QBO.
     */
    public function generateEstimate(GeoTimeJob $job): array
    {
        $realmId = $this->client->getRealmId();

        // Ensure the job has a QBO customer
        $customerService = new QuickBooksCustomerService($this->tenant);
        $customerId = $customerService->findOrCreateCustomer($job);

        // Build line items
        $lineItems = $this->buildEstimateLineItems($job);

        $estimateData = [
            'CustomerRef' => ['value' => $customerId],
            'Line' => $lineItems,
            'TxnDate' => now()->format('Y-m-d'),
            'ExpirationDate' => now()->addDays(30)->format('Y-m-d'),
            'CustomField' => [
                [
                    'DefinitionId' => '1',
                    'Name' => 'GeoTime Job',
                    'Type' => 'StringType',
                    'StringValue' => "{$job->name} (ID: {$job->id})",
                ],
            ],
        ];

        // Add bill email if client has one
        if ($job->client_email) {
            $estimateData['BillEmail'] = ['Address' => $job->client_email];
        }

        $response = $this->client->post("/v3/company/{$realmId}/estimate", $estimateData);

        $estimateId = $response->json('Estimate.Id');
        $totalAmt = $response->json('Estimate.TotalAmt');

        QboSyncLog::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'ESTIMATE',
            'geotime_entity_id' => $job->id,
            'qbo_entity_id' => $estimateId,
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
            'request_payload' => $estimateData,
            'response_payload' => $response->json(),
        ]);

        return [
            'qbo_estimate_id' => $estimateId,
            'total' => (float) $totalAmt,
        ];
    }

    /**
     * Get estimate status from QBO.
     */
    public function getEstimateStatus(string $estimateId): array
    {
        $realmId = $this->client->getRealmId();
        $response = $this->client->get("/v3/company/{$realmId}/estimate/{$estimateId}");

        $estimate = $response->json('Estimate');

        return [
            'qbo_estimate_id' => $estimate['Id'],
            'status' => $estimate['TxnStatus'] ?? 'Unknown',
            'total' => (float) ($estimate['TotalAmt'] ?? 0),
            'sync_token' => $estimate['SyncToken'] ?? '0',
        ];
    }

    /**
     * Build line items for the estimate based on job budget.
     */
    private function buildEstimateLineItems(GeoTimeJob $job): array
    {
        $lines = [];

        // Look for a service item mapping
        $mapping = QboServiceItemMapping::where('tenant_id', $this->tenant->id)
            ->active()
            ->first();

        $amount = (float) $job->budget_hours * (float) $job->hourly_rate;

        $lineItem = [
            'Amount' => $amount,
            'DetailType' => 'SalesItemLineDetail',
            'Description' => "Labor: {$job->name} — {$job->budget_hours} hours @ \${$job->hourly_rate}/hr",
            'SalesItemLineDetail' => [
                'Qty' => (float) $job->budget_hours,
                'UnitPrice' => (float) $job->hourly_rate,
            ],
        ];

        // Map to QBO service item if available
        if ($mapping) {
            $lineItem['SalesItemLineDetail']['ItemRef'] = [
                'value' => $mapping->qbo_item_id,
                'name' => $mapping->qbo_item_name,
            ];
        }

        $lines[] = $lineItem;

        return $lines;
    }
}
```

- [ ] **Step 4: Create QboPushEstimateJob**

```php
// app/Jobs/QboPushEstimateJob.php
<?php

namespace App\Jobs;

use App\Models\Job as GeoTimeJob;
use App\Models\QboSyncLog;
use App\Models\Tenant;
use App\Services\QuickBooks\QuickBooksEstimateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QboPushEstimateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $tenantId,
        public string $jobId
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        app()->instance('current_tenant', $tenant);

        $job = GeoTimeJob::where('tenant_id', $tenant->id)->findOrFail($this->jobId);

        try {
            $service = new QuickBooksEstimateService($tenant);
            $result = $service->generateEstimate($job);

            Log::info('QBO estimate pushed', [
                'tenant_id' => $this->tenantId,
                'job_id' => $this->jobId,
                'qbo_estimate_id' => $result['qbo_estimate_id'],
            ]);
        } catch (\Exception $e) {
            QboSyncLog::create([
                'tenant_id' => $this->tenantId,
                'entity_type' => 'ESTIMATE',
                'geotime_entity_id' => $this->jobId,
                'direction' => 'PUSH',
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('QBO estimate push failed', [
                'tenant_id' => $this->tenantId,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

- [ ] **Step 5: Create QuickBooksEstimateController**

```php
// app/Http/Controllers/Api/QuickBooksEstimateController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\QboPushEstimateJob;
use App\Models\Job as GeoTimeJob;
use App\Services\QuickBooks\QuickBooksEstimateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickBooksEstimateController extends Controller
{
    /**
     * POST /api/v1/qbo/estimates/generate/{job} — generate and push estimate.
     */
    public function generate(Request $request, string $jobId): JsonResponse
    {
        $tenant = app('current_tenant');
        $job = GeoTimeJob::where('tenant_id', $tenant->id)->findOrFail($jobId);

        $service = new QuickBooksEstimateService($tenant);
        $result = $service->generateEstimate($job);

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/v1/qbo/estimates/{id}/status — get estimate status from QBO.
     */
    public function status(Request $request, string $estimateId): JsonResponse
    {
        $tenant = app('current_tenant');
        $service = new QuickBooksEstimateService($tenant);
        $result = $service->getEstimateStatus($estimateId);

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/v1/qbo/estimates/generate-async/{job} — dispatch async estimate generation.
     */
    public function generateAsync(Request $request, string $jobId): JsonResponse
    {
        $tenant = app('current_tenant');
        GeoTimeJob::where('tenant_id', $tenant->id)->findOrFail($jobId);

        QboPushEstimateJob::dispatch($tenant->id, $jobId);

        return response()->json([
            'data' => ['message' => 'Estimate generation job dispatched.'],
        ], 202);
    }
}
```

- [ ] **Step 6: Add estimate routes**

Add inside the `EnsureQuickBooksConnected` middleware group:

```php
use App\Http\Controllers\Api\QuickBooksEstimateController;

// Estimates
Route::post('/estimates/generate/{job}', [QuickBooksEstimateController::class, 'generate']);
Route::post('/estimates/generate-async/{job}', [QuickBooksEstimateController::class, 'generateAsync']);
Route::get('/estimates/{estimate}/status', [QuickBooksEstimateController::class, 'status']);
```

- [ ] **Step 7: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksEstimateTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/QuickBooks/QuickBooksEstimateService.php app/Http/Controllers/Api/QuickBooksEstimateController.php app/Jobs/QboPushEstimateJob.php routes/api.php tests/Feature/QuickBooks/QuickBooksEstimateTest.php
git commit -m "feat: add QBO estimate generation from job data with status sync"
```

---

## Task 8: QBO Invoice Generation

**Files:**
- Create: `app/Services/QuickBooks/QuickBooksInvoiceService.php`
- Create: `app/Http/Controllers/Api/QuickBooksInvoiceController.php`
- Create: `app/Jobs/QboPushInvoiceJob.php`
- Create: `tests/Feature/QuickBooks/QuickBooksInvoiceTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/QuickBooks/QuickBooksInvoiceTest.php
<?php

namespace Tests\Feature\QuickBooks;

use App\Models\Job as GeoTimeJob;
use App\Models\QboServiceItemMapping;
use App\Models\QboSyncLog;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuickBooksInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function createConnectedTenantWithAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'fake-access-token',
            'qbo_refresh_token' => 'fake-refresh-token',
            'qbo_token_expires_at' => now()->addMinutes(30),
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

    public function test_generate_invoice_from_time_entries(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        $job = GeoTimeJob::create([
            'tenant_id' => $tenant->id,
            'name' => 'Office Renovation',
            'client_name' => 'Acme Corp',
            'qbo_customer_id' => '1',
            'status' => 'ACTIVE',
            'budget_hours' => 200,
            'hourly_rate' => 75.00,
        ]);

        // Create time entries totaling 40 hours
        TimeEntry::create([
            'tenant_id' => $tenant->id,
            'employee_id' => fake()->uuid(),
            'job_id' => $job->id,
            'clock_in' => now()->subDays(5),
            'clock_out' => now()->subDays(5)->addHours(8),
            'total_hours' => 8.00,
            'status' => 'APPROVED',
            'clock_method' => 'GEOFENCE',
        ]);

        TimeEntry::create([
            'tenant_id' => $tenant->id,
            'employee_id' => fake()->uuid(),
            'job_id' => $job->id,
            'clock_in' => now()->subDays(4),
            'clock_out' => now()->subDays(4)->addHours(8),
            'total_hours' => 8.00,
            'status' => 'APPROVED',
            'clock_method' => 'GEOFENCE',
        ]);

        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'Invoice' => [
                    'Id' => '501',
                    'SyncToken' => '0',
                    'TotalAmt' => 1200.00,
                    'Balance' => 1200.00,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/qbo/invoices/generate/{$job->id}", [
                'date_from' => now()->subDays(7)->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
                'due_days' => 30,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.qbo_invoice_id', '501');

        $this->assertDatabaseHas('qbo_sync_log', [
            'entity_type' => 'INVOICE',
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
            'qbo_entity_id' => '501',
        ]);
    }

    public function test_convert_estimate_to_invoice(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        $job = GeoTimeJob::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Job',
            'client_name' => 'Client',
            'qbo_customer_id' => '1',
            'status' => 'ACTIVE',
            'budget_hours' => 100,
            'hourly_rate' => 50.00,
        ]);

        // 110 actual hours (10% over budget, triggers variance flag)
        TimeEntry::create([
            'tenant_id' => $tenant->id,
            'employee_id' => fake()->uuid(),
            'job_id' => $job->id,
            'clock_in' => now()->subDays(1),
            'clock_out' => now()->subDays(1)->addHours(10),
            'total_hours' => 110.00,
            'status' => 'APPROVED',
            'clock_method' => 'GEOFENCE',
        ]);

        Http::fake([
            // Get estimate
            'sandbox-quickbooks.api.intuit.com/*/estimate/*' => Http::response([
                'Estimate' => [
                    'Id' => '201',
                    'TotalAmt' => 5000.00,
                    'Line' => [
                        [
                            'Amount' => 5000.00,
                            'DetailType' => 'SalesItemLineDetail',
                            'SalesItemLineDetail' => ['Qty' => 100, 'UnitPrice' => 50],
                        ],
                    ],
                    'CustomerRef' => ['value' => '1'],
                    'SyncToken' => '1',
                ],
            ], 200),
            // Create invoice
            'sandbox-quickbooks.api.intuit.com/*/invoice' => Http::response([
                'Invoice' => [
                    'Id' => '502',
                    'TotalAmt' => 5500.00,
                    'Balance' => 5500.00,
                    'SyncToken' => '0',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/qbo/invoices/convert-estimate", [
                'qbo_estimate_id' => '201',
                'job_id' => $job->id,
                'use_actual_hours' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.qbo_invoice_id', '502')
            ->assertJsonStructure(['data' => ['variance']]);
    }

    public function test_get_invoice_payment_status(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'Invoice' => [
                    'Id' => '501',
                    'TotalAmt' => 1200.00,
                    'Balance' => 0.00,
                    'SyncToken' => '2',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/qbo/invoices/501/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.payment_status', 'Paid');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksInvoiceTest.php`
Expected: FAIL — service/controller don't exist.

- [ ] **Step 3: Create QuickBooksInvoiceService**

```php
// app/Services/QuickBooks/QuickBooksInvoiceService.php
<?php

namespace App\Services\QuickBooks;

use App\Models\Job as GeoTimeJob;
use App\Models\QboServiceItemMapping;
use App\Models\QboSyncLog;
use App\Models\Tenant;
use App\Models\TimeEntry;

class QuickBooksInvoiceService
{
    private QuickBooksClient $client;
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->client = new QuickBooksClient($tenant);
    }

    /**
     * Generate an invoice from actual hours worked on a job.
     */
    public function generateInvoice(GeoTimeJob $job, string $dateFrom, string $dateTo, int $dueDays = 30): array
    {
        $realmId = $this->client->getRealmId();

        // Ensure QBO customer exists
        $customerService = new QuickBooksCustomerService($this->tenant);
        $customerId = $customerService->findOrCreateCustomer($job);

        // Get approved time entries for the date range
        $timeEntries = TimeEntry::where('tenant_id', $this->tenant->id)
            ->where('job_id', $job->id)
            ->where('status', 'APPROVED')
            ->whereBetween('clock_in', [$dateFrom, $dateTo])
            ->get();

        $totalHours = $timeEntries->sum('total_hours');
        $rate = (float) $job->hourly_rate;
        $totalAmount = $totalHours * $rate;

        $lineItems = $this->buildInvoiceLineItems($job, $totalHours, $rate, $dateFrom, $dateTo);

        $invoiceData = [
            'CustomerRef' => ['value' => $customerId],
            'Line' => $lineItems,
            'DueDate' => now()->addDays($dueDays)->format('Y-m-d'),
            'TxnDate' => now()->format('Y-m-d'),
            'CustomField' => [
                [
                    'DefinitionId' => '1',
                    'Name' => 'GeoTime Reference',
                    'Type' => 'StringType',
                    'StringValue' => "Job: {$job->name} | Period: {$dateFrom} to {$dateTo}",
                ],
            ],
        ];

        $response = $this->client->post("/v3/company/{$realmId}/invoice", $invoiceData);

        $invoiceId = $response->json('Invoice.Id');

        QboSyncLog::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'INVOICE',
            'geotime_entity_id' => $job->id,
            'qbo_entity_id' => $invoiceId,
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
            'request_payload' => $invoiceData,
            'response_payload' => $response->json(),
        ]);

        return [
            'qbo_invoice_id' => $invoiceId,
            'total_hours' => $totalHours,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Convert a QBO estimate to an invoice, optionally using actual hours.
     */
    public function convertEstimateToInvoice(string $qboEstimateId, GeoTimeJob $job, bool $useActualHours = false): array
    {
        $realmId = $this->client->getRealmId();

        // Fetch the estimate from QBO
        $estimateResponse = $this->client->get("/v3/company/{$realmId}/estimate/{$qboEstimateId}");
        $estimate = $estimateResponse->json('Estimate');

        $estimatedTotal = (float) $estimate['TotalAmt'];

        // Calculate actual hours if requested
        $actualHours = 0;
        $variance = null;

        if ($useActualHours) {
            $actualHours = TimeEntry::where('tenant_id', $this->tenant->id)
                ->where('job_id', $job->id)
                ->where('status', 'APPROVED')
                ->sum('total_hours');

            $actualTotal = $actualHours * (float) $job->hourly_rate;
            $variancePercent = $estimatedTotal > 0
                ? (($actualTotal - $estimatedTotal) / $estimatedTotal) * 100
                : 0;

            $variance = [
                'estimated_total' => $estimatedTotal,
                'actual_total' => $actualTotal,
                'variance_percent' => round($variancePercent, 2),
                'exceeds_threshold' => abs($variancePercent) > 10,
            ];
        }

        // Build invoice from estimate data
        $invoiceData = [
            'CustomerRef' => $estimate['CustomerRef'],
            'Line' => $estimate['Line'],
            'TxnDate' => now()->format('Y-m-d'),
            'DueDate' => now()->addDays(30)->format('Y-m-d'),
            'CustomField' => [
                [
                    'DefinitionId' => '1',
                    'Name' => 'GeoTime Reference',
                    'Type' => 'StringType',
                    'StringValue' => "Converted from Estimate #{$qboEstimateId}",
                ],
            ],
        ];

        // If using actual hours, update the line item amounts
        if ($useActualHours && $actualHours > 0) {
            $invoiceData['Line'] = [[
                'Amount' => $actualHours * (float) $job->hourly_rate,
                'DetailType' => 'SalesItemLineDetail',
                'Description' => "Actual hours: {$actualHours} @ \${$job->hourly_rate}/hr",
                'SalesItemLineDetail' => [
                    'Qty' => (float) $actualHours,
                    'UnitPrice' => (float) $job->hourly_rate,
                ],
            ]];
        }

        $response = $this->client->post("/v3/company/{$realmId}/invoice", $invoiceData);

        $invoiceId = $response->json('Invoice.Id');

        QboSyncLog::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'INVOICE',
            'geotime_entity_id' => $job->id,
            'qbo_entity_id' => $invoiceId,
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
            'request_payload' => $invoiceData,
            'response_payload' => $response->json(),
        ]);

        return [
            'qbo_invoice_id' => $invoiceId,
            'converted_from_estimate' => $qboEstimateId,
            'variance' => $variance,
        ];
    }

    /**
     * Get invoice payment status from QBO.
     */
    public function getInvoiceStatus(string $invoiceId): array
    {
        $realmId = $this->client->getRealmId();
        $response = $this->client->get("/v3/company/{$realmId}/invoice/{$invoiceId}");

        $invoice = $response->json('Invoice');
        $totalAmt = (float) ($invoice['TotalAmt'] ?? 0);
        $balance = (float) ($invoice['Balance'] ?? 0);

        $paymentStatus = 'Unpaid';
        if ($balance <= 0) {
            $paymentStatus = 'Paid';
        } elseif ($balance < $totalAmt) {
            $paymentStatus = 'Partial';
        }

        return [
            'qbo_invoice_id' => $invoice['Id'],
            'total' => $totalAmt,
            'balance' => $balance,
            'payment_status' => $paymentStatus,
        ];
    }

    /**
     * Build line items for the invoice.
     */
    private function buildInvoiceLineItems(GeoTimeJob $job, float $totalHours, float $rate, string $dateFrom, string $dateTo): array
    {
        $mapping = QboServiceItemMapping::where('tenant_id', $this->tenant->id)
            ->active()
            ->first();

        $lineItem = [
            'Amount' => $totalHours * $rate,
            'DetailType' => 'SalesItemLineDetail',
            'Description' => "Labor: {$job->name} — {$totalHours} hours @ \${$rate}/hr ({$dateFrom} to {$dateTo})",
            'SalesItemLineDetail' => [
                'Qty' => $totalHours,
                'UnitPrice' => $rate,
            ],
        ];

        if ($mapping) {
            $lineItem['SalesItemLineDetail']['ItemRef'] = [
                'value' => $mapping->qbo_item_id,
                'name' => $mapping->qbo_item_name,
            ];
        }

        return [$lineItem];
    }
}
```

- [ ] **Step 4: Create QboPushInvoiceJob**

```php
// app/Jobs/QboPushInvoiceJob.php
<?php

namespace App\Jobs;

use App\Models\Job as GeoTimeJob;
use App\Models\QboSyncLog;
use App\Models\Tenant;
use App\Services\QuickBooks\QuickBooksInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QboPushInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $tenantId,
        public string $jobId,
        public string $dateFrom,
        public string $dateTo,
        public int $dueDays = 30
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        app()->instance('current_tenant', $tenant);

        $job = GeoTimeJob::where('tenant_id', $tenant->id)->findOrFail($this->jobId);

        try {
            $service = new QuickBooksInvoiceService($tenant);
            $result = $service->generateInvoice($job, $this->dateFrom, $this->dateTo, $this->dueDays);

            Log::info('QBO invoice pushed', [
                'tenant_id' => $this->tenantId,
                'job_id' => $this->jobId,
                'qbo_invoice_id' => $result['qbo_invoice_id'],
            ]);
        } catch (\Exception $e) {
            QboSyncLog::create([
                'tenant_id' => $this->tenantId,
                'entity_type' => 'INVOICE',
                'geotime_entity_id' => $this->jobId,
                'direction' => 'PUSH',
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

- [ ] **Step 5: Create QuickBooksInvoiceController**

```php
// app/Http/Controllers/Api/QuickBooksInvoiceController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\QboPushInvoiceJob;
use App\Models\Job as GeoTimeJob;
use App\Services\QuickBooks\QuickBooksInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickBooksInvoiceController extends Controller
{
    /**
     * POST /api/v1/qbo/invoices/generate/{job} — generate invoice from actual hours.
     */
    public function generate(Request $request, string $jobId): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'due_days' => 'sometimes|integer|min:1|max:365',
        ]);

        $tenant = app('current_tenant');
        $job = GeoTimeJob::where('tenant_id', $tenant->id)->findOrFail($jobId);

        $service = new QuickBooksInvoiceService($tenant);
        $result = $service->generateInvoice(
            $job,
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('due_days', 30)
        );

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/v1/qbo/invoices/convert-estimate — convert estimate to invoice.
     */
    public function convertEstimate(Request $request): JsonResponse
    {
        $request->validate([
            'qbo_estimate_id' => 'required|string',
            'job_id' => 'required|uuid',
            'use_actual_hours' => 'sometimes|boolean',
        ]);

        $tenant = app('current_tenant');
        $job = GeoTimeJob::where('tenant_id', $tenant->id)->findOrFail($request->input('job_id'));

        $service = new QuickBooksInvoiceService($tenant);
        $result = $service->convertEstimateToInvoice(
            $request->input('qbo_estimate_id'),
            $job,
            $request->boolean('use_actual_hours', false)
        );

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/v1/qbo/invoices/{id}/status — get payment status.
     */
    public function status(Request $request, string $invoiceId): JsonResponse
    {
        $tenant = app('current_tenant');
        $service = new QuickBooksInvoiceService($tenant);
        $result = $service->getInvoiceStatus($invoiceId);

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/v1/qbo/invoices/generate-async/{job} — dispatch async.
     */
    public function generateAsync(Request $request, string $jobId): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'due_days' => 'sometimes|integer|min:1|max:365',
        ]);

        $tenant = app('current_tenant');
        GeoTimeJob::where('tenant_id', $tenant->id)->findOrFail($jobId);

        QboPushInvoiceJob::dispatch(
            $tenant->id,
            $jobId,
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('due_days', 30)
        );

        return response()->json([
            'data' => ['message' => 'Invoice generation job dispatched.'],
        ], 202);
    }
}
```

- [ ] **Step 6: Add invoice routes**

Add inside the `EnsureQuickBooksConnected` middleware group:

```php
use App\Http\Controllers\Api\QuickBooksInvoiceController;

// Invoices
Route::post('/invoices/generate/{job}', [QuickBooksInvoiceController::class, 'generate']);
Route::post('/invoices/generate-async/{job}', [QuickBooksInvoiceController::class, 'generateAsync']);
Route::post('/invoices/convert-estimate', [QuickBooksInvoiceController::class, 'convertEstimate']);
Route::get('/invoices/{invoice}/status', [QuickBooksInvoiceController::class, 'status']);
```

- [ ] **Step 7: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksInvoiceTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/QuickBooks/QuickBooksInvoiceService.php app/Http/Controllers/Api/QuickBooksInvoiceController.php app/Jobs/QboPushInvoiceJob.php routes/api.php tests/Feature/QuickBooks/QuickBooksInvoiceTest.php
git commit -m "feat: add QBO invoice generation, estimate-to-invoice conversion with variance flagging"
```

---

## Task 9: QBO Bank Feeds (Rutter Middleware)

**Files:**
- Create: `app/Services/QuickBooks/QuickBooksBankFeedService.php`
- Create: `app/Http/Controllers/Api/QuickBooksBankFeedController.php`
- Create: `app/Jobs/QboPushBankFeedJob.php`
- Create: `tests/Feature/QuickBooks/QuickBooksBankFeedTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/QuickBooks/QuickBooksBankFeedTest.php
<?php

namespace Tests\Feature\QuickBooks;

use App\Models\Job as GeoTimeJob;
use App\Models\QboSyncLog;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuickBooksBankFeedTest extends TestCase
{
    use RefreshDatabase;

    private function createConnectedTenantWithAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'fake-access-token',
            'qbo_refresh_token' => 'fake-refresh-token',
            'qbo_token_expires_at' => now()->addMinutes(30),
            'rutter_access_token' => 'fake-rutter-token',
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

    public function test_push_labor_cost_to_bank_feed(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        $job = GeoTimeJob::create([
            'tenant_id' => $tenant->id,
            'name' => 'Construction Site A',
            'client_name' => 'Client A',
            'status' => 'ACTIVE',
            'budget_hours' => 500,
            'hourly_rate' => 65.00,
        ]);

        Http::fake([
            'production.rutterapi.com/*' => Http::response([
                'bank_feed_transaction' => [
                    'id' => 'rutter_txn_001',
                    'status' => 'posted',
                    'amount' => 2600.00,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/qbo/bank-feeds/push', [
                'job_id' => $job->id,
                'amount' => 2600.00,
                'description' => 'Weekly labor cost - Construction Site A',
                'date' => '2026-03-27',
                'type' => 'debit',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rutter_transaction_id', 'rutter_txn_001');

        $this->assertDatabaseHas('qbo_sync_log', [
            'entity_type' => 'BANK_FEED',
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
        ]);
    }

    public function test_get_reconciliation_status(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        // Create some sync log entries for bank feeds
        QboSyncLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'BANK_FEED',
            'direction' => 'PUSH',
            'status' => 'SUCCESS',
            'qbo_entity_id' => 'txn_1',
            'request_payload' => ['amount' => 1000],
        ]);

        QboSyncLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'BANK_FEED',
            'direction' => 'PUSH',
            'status' => 'FAILED',
            'error_message' => 'Connection timeout',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/qbo/bank-feeds/reconciliation');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_pushed', 2)
            ->assertJsonPath('data.successful', 1)
            ->assertJsonPath('data.failed', 1);
    }

    public function test_push_requires_rutter_connection(): void
    {
        $tenant = Tenant::create([
            'name' => 'No Rutter Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'token',
            'qbo_refresh_token' => 'refresh',
            'qbo_token_expires_at' => now()->addMinutes(30),
            // No rutter_access_token
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Admin',
            'email' => 'admin2@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/qbo/bank-feeds/push', [
                'amount' => 1000,
                'description' => 'Test',
                'date' => '2026-03-27',
                'type' => 'debit',
            ]);

        $response->assertStatus(428);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksBankFeedTest.php`
Expected: FAIL.

- [ ] **Step 3: Create QuickBooksBankFeedService**

```php
// app/Services/QuickBooks/QuickBooksBankFeedService.php
<?php

namespace App\Services\QuickBooks;

use App\Models\QboSyncLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

class QuickBooksBankFeedService
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Push a labor cost transaction to QBO bank feeds via Rutter.
     */
    public function pushTransaction(float $amount, string $description, string $date, string $type = 'debit'): array
    {
        if (empty($this->tenant->rutter_access_token)) {
            throw new \RuntimeException('Rutter is not connected for bank feeds.');
        }

        $payload = [
            'transaction' => [
                'amount' => $amount,
                'date' => $date,
                'description' => $description,
                'type' => $type, // debit or credit
                'currency_code' => 'USD',
            ],
        ];

        $response = Http::withToken($this->tenant->rutter_access_token)
            ->withHeaders([
                'X-Rutter-Version' => '2024-08-31',
            ])
            ->post(
                config('quickbooks.rutter.base_url') . '/accounting/bank_feed_transactions',
                $payload
            );

        $rutterTxnId = $response->json('bank_feed_transaction.id');

        $syncLog = QboSyncLog::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'BANK_FEED',
            'qbo_entity_id' => $rutterTxnId,
            'direction' => 'PUSH',
            'status' => $response->successful() ? 'SUCCESS' : 'FAILED',
            'error_message' => $response->successful() ? null : $response->body(),
            'request_payload' => $payload,
            'response_payload' => $response->json(),
        ]);

        if (! $response->successful()) {
            $syncLog->markFailed($response->body(), $response->json());
            throw new \RuntimeException('Rutter bank feed push failed: ' . $response->body());
        }

        return [
            'rutter_transaction_id' => $rutterTxnId,
            'status' => $response->json('bank_feed_transaction.status'),
            'amount' => $amount,
        ];
    }

    /**
     * Get reconciliation status for all bank feed transactions.
     */
    public function getReconciliationStatus(): array
    {
        $logs = QboSyncLog::where('tenant_id', $this->tenant->id)
            ->where('entity_type', 'BANK_FEED')
            ->get();

        $successful = $logs->where('status', 'SUCCESS')->count();
        $failed = $logs->where('status', 'FAILED')->count();
        $pending = $logs->where('status', 'PENDING')->count();

        return [
            'total_pushed' => $logs->count(),
            'successful' => $successful,
            'failed' => $failed,
            'pending' => $pending,
            'recent_transactions' => $logs->sortByDesc('created_at')
                ->take(10)
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'qbo_entity_id' => $log->qbo_entity_id,
                    'status' => $log->status,
                    'amount' => $log->request_payload['transaction']['amount'] ?? null,
                    'date' => $log->created_at->toIso8601String(),
                    'error' => $log->error_message,
                ])
                ->values()
                ->toArray(),
        ];
    }
}
```

- [ ] **Step 4: Create QboPushBankFeedJob**

```php
// app/Jobs/QboPushBankFeedJob.php
<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\QuickBooks\QuickBooksBankFeedService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QboPushBankFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120;

    public function __construct(
        public string $tenantId,
        public float $amount,
        public string $description,
        public string $date,
        public string $type = 'debit'
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        app()->instance('current_tenant', $tenant);

        try {
            $service = new QuickBooksBankFeedService($tenant);
            $service->pushTransaction($this->amount, $this->description, $this->date, $this->type);

            Log::info('Bank feed transaction pushed', [
                'tenant_id' => $this->tenantId,
                'amount' => $this->amount,
            ]);
        } catch (\Exception $e) {
            Log::error('Bank feed push failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

- [ ] **Step 5: Create QuickBooksBankFeedController**

```php
// app/Http/Controllers/Api/QuickBooksBankFeedController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuickBooks\QuickBooksBankFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickBooksBankFeedController extends Controller
{
    /**
     * POST /api/v1/qbo/bank-feeds/push — push a labor cost transaction.
     */
    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'job_id' => 'sometimes|uuid',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'date' => 'required|date',
            'type' => 'required|in:debit,credit',
        ]);

        $tenant = app('current_tenant');

        if (empty($tenant->rutter_access_token)) {
            return response()->json([
                'message' => 'Rutter bank feeds integration is not connected.',
            ], 428);
        }

        $service = new QuickBooksBankFeedService($tenant);
        $result = $service->pushTransaction(
            $request->input('amount'),
            $request->input('description'),
            $request->input('date'),
            $request->input('type')
        );

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/v1/qbo/bank-feeds/reconciliation — get reconciliation dashboard data.
     */
    public function reconciliation(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');
        $service = new QuickBooksBankFeedService($tenant);
        $result = $service->getReconciliationStatus();

        return response()->json(['data' => $result]);
    }
}
```

- [ ] **Step 6: Add bank feed routes**

Add inside the `EnsureQuickBooksConnected` middleware group:

```php
use App\Http\Controllers\Api\QuickBooksBankFeedController;

// Bank Feeds
Route::post('/bank-feeds/push', [QuickBooksBankFeedController::class, 'push']);
Route::get('/bank-feeds/reconciliation', [QuickBooksBankFeedController::class, 'reconciliation']);
```

- [ ] **Step 7: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksBankFeedTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/QuickBooks/QuickBooksBankFeedService.php app/Http/Controllers/Api/QuickBooksBankFeedController.php app/Jobs/QboPushBankFeedJob.php routes/api.php tests/Feature/QuickBooks/QuickBooksBankFeedTest.php
git commit -m "feat: add QBO bank feeds via Rutter middleware with reconciliation tracking"
```

---

## Task 10: QBO Service Item Mapping & Webhooks

**Files:**
- Create: `app/Services/QuickBooks/QuickBooksServiceItemService.php`
- Create: `app/Http/Controllers/Api/QuickBooksServiceItemController.php`
- Create: `app/Http/Controllers/Api/QuickBooksWebhookController.php`
- Create: `tests/Feature/QuickBooks/QuickBooksServiceItemTest.php`
- Create: `tests/Feature/QuickBooks/QuickBooksWebhookTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the service item test**

```php
// tests/Feature/QuickBooks/QuickBooksServiceItemTest.php
<?php

namespace Tests\Feature\QuickBooks;

use App\Models\QboServiceItemMapping;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuickBooksServiceItemTest extends TestCase
{
    use RefreshDatabase;

    private function createConnectedTenantWithAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'fake-access-token',
            'qbo_refresh_token' => 'fake-refresh-token',
            'qbo_token_expires_at' => now()->addMinutes(30),
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

    public function test_pull_service_items_from_qbo(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'QueryResponse' => [
                    'Item' => [
                        [
                            'Id' => '5',
                            'Name' => 'Labor - General',
                            'Type' => 'Service',
                            'UnitPrice' => 75.00,
                            'Active' => true,
                        ],
                        [
                            'Id' => '6',
                            'Name' => 'Labor - Skilled',
                            'Type' => 'Service',
                            'UnitPrice' => 95.00,
                            'Active' => true,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/qbo/service-items');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_create_mapping(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/qbo/service-items/mappings', [
                'geotime_job_type' => 'General Labor',
                'qbo_item_id' => '5',
                'qbo_item_name' => 'Labor - General',
                'default_rate' => 75.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.geotime_job_type', 'General Labor');

        $this->assertDatabaseHas('qbo_service_item_mappings', [
            'tenant_id' => $tenant->id,
            'geotime_job_type' => 'General Labor',
            'qbo_item_id' => '5',
        ]);
    }

    public function test_list_mappings(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        QboServiceItemMapping::create([
            'tenant_id' => $tenant->id,
            'geotime_job_type' => 'General Labor',
            'qbo_item_id' => '5',
            'qbo_item_name' => 'Labor - General',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/qbo/service-items/mappings');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_delete_mapping(): void
    {
        [$tenant, $user] = $this->createConnectedTenantWithAdmin();
        app()->instance('current_tenant', $tenant);

        $mapping = QboServiceItemMapping::create([
            'tenant_id' => $tenant->id,
            'geotime_job_type' => 'General Labor',
            'qbo_item_id' => '5',
            'qbo_item_name' => 'Labor - General',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/qbo/service-items/mappings/{$mapping->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('qbo_service_item_mappings', ['id' => $mapping->id]);
    }
}
```

- [ ] **Step 2: Write the webhook test**

```php
// tests/Feature/QuickBooks/QuickBooksWebhookTest.php
<?php

namespace Tests\Feature\QuickBooks;

use App\Models\QboSyncLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickBooksWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_processes_invoice_payment_event(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
        ]);

        $payload = [
            'eventNotifications' => [
                [
                    'realmId' => '1234567890',
                    'dataChangeEvent' => [
                        'entities' => [
                            [
                                'name' => 'Invoice',
                                'id' => '501',
                                'operation' => 'Update',
                                'lastUpdated' => '2026-03-28T12:00:00.000Z',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // QBO webhooks use HMAC-SHA256 verification
        $signature = base64_encode(
            hash_hmac('sha256', json_encode($payload), config('quickbooks.webhook_verifier_token', 'test-token'), true)
        );

        $response = $this->postJson('/api/v1/qbo/webhooks', $payload, [
            'intuit-signature' => $signature,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('qbo_sync_log', [
            'entity_type' => 'INVOICE',
            'qbo_entity_id' => '501',
            'direction' => 'PULL',
            'status' => 'SUCCESS',
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'eventNotifications' => [],
        ];

        $response = $this->postJson('/api/v1/qbo/webhooks', $payload, [
            'intuit-signature' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksServiceItemTest.php tests/Feature/QuickBooks/QuickBooksWebhookTest.php`
Expected: FAIL.

- [ ] **Step 4: Create QuickBooksServiceItemService**

```php
// app/Services/QuickBooks/QuickBooksServiceItemService.php
<?php

namespace App\Services\QuickBooks;

use App\Models\Tenant;

class QuickBooksServiceItemService
{
    private QuickBooksClient $client;
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->client = new QuickBooksClient($tenant);
    }

    /**
     * Pull service items (Type=Service) from QBO.
     */
    public function pullServiceItems(): array
    {
        $response = $this->client->query("SELECT * FROM Item WHERE Type = 'Service' AND Active = true MAXRESULTS 1000");

        return $response->json('QueryResponse.Item', []);
    }
}
```

- [ ] **Step 5: Create QuickBooksServiceItemController**

```php
// app/Http/Controllers/Api/QuickBooksServiceItemController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QboServiceItemMapping;
use App\Services\QuickBooks\QuickBooksServiceItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickBooksServiceItemController extends Controller
{
    /**
     * GET /api/v1/qbo/service-items — pull service items from QBO.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');
        $service = new QuickBooksServiceItemService($tenant);
        $items = $service->pullServiceItems();

        return response()->json(['data' => ['items' => $items]]);
    }

    /**
     * GET /api/v1/qbo/service-items/mappings — list local mappings.
     */
    public function listMappings(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        $mappings = QboServiceItemMapping::where('tenant_id', $tenant->id)
            ->orderBy('geotime_job_type')
            ->get();

        return response()->json(['data' => $mappings]);
    }

    /**
     * POST /api/v1/qbo/service-items/mappings — create a mapping.
     */
    public function createMapping(Request $request): JsonResponse
    {
        $request->validate([
            'geotime_job_type' => 'required|string|max:100',
            'qbo_item_id' => 'required|string|max:50',
            'qbo_item_name' => 'required|string|max:255',
            'default_rate' => 'sometimes|numeric|min:0',
        ]);

        $tenant = app('current_tenant');

        $mapping = QboServiceItemMapping::create([
            'tenant_id' => $tenant->id,
            'geotime_job_type' => $request->input('geotime_job_type'),
            'qbo_item_id' => $request->input('qbo_item_id'),
            'qbo_item_name' => $request->input('qbo_item_name'),
            'default_rate' => $request->input('default_rate'),
        ]);

        return response()->json(['data' => $mapping], 201);
    }

    /**
     * DELETE /api/v1/qbo/service-items/mappings/{id} — delete a mapping.
     */
    public function deleteMapping(Request $request, string $id): JsonResponse
    {
        $tenant = app('current_tenant');

        $mapping = QboServiceItemMapping::where('tenant_id', $tenant->id)->findOrFail($id);
        $mapping->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
```

- [ ] **Step 6: Create QuickBooksWebhookController**

```php
// app/Http/Controllers/Api/QuickBooksWebhookController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QboSyncLog;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuickBooksWebhookController extends Controller
{
    /**
     * POST /api/v1/qbo/webhooks — handle incoming QBO webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        // Verify webhook signature
        if (! $this->verifySignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $notifications = $request->input('eventNotifications', []);

        foreach ($notifications as $notification) {
            $realmId = $notification['realmId'] ?? null;

            if (! $realmId) {
                continue;
            }

            $tenant = Tenant::where('qbo_realm_id', $realmId)->first();

            if (! $tenant) {
                Log::warning('QBO webhook for unknown realm', ['realm_id' => $realmId]);
                continue;
            }

            $entities = $notification['dataChangeEvent']['entities'] ?? [];

            foreach ($entities as $entity) {
                $this->processEntity($tenant, $entity);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify the Intuit webhook HMAC-SHA256 signature.
     */
    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('intuit-signature');

        if (! $signature) {
            return false;
        }

        $verifierToken = config('quickbooks.webhook_verifier_token', '');
        $payload = $request->getContent();

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $payload, $verifierToken, true)
        );

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process a single entity change event.
     */
    private function processEntity(Tenant $tenant, array $entity): void
    {
        $entityName = $entity['name'] ?? 'Unknown';
        $entityId = $entity['id'] ?? null;
        $operation = $entity['operation'] ?? 'Unknown';

        // Map QBO entity names to our entity types
        $entityTypeMap = [
            'Invoice' => 'INVOICE',
            'Estimate' => 'ESTIMATE',
            'Customer' => 'CUSTOMER',
            'Employee' => 'EMPLOYEE',
            'Payment' => 'PAYMENT',
        ];

        $entityType = $entityTypeMap[$entityName] ?? $entityName;

        QboSyncLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => $entityType,
            'qbo_entity_id' => $entityId,
            'direction' => 'PULL',
            'status' => 'SUCCESS',
            'response_payload' => [
                'operation' => $operation,
                'entity' => $entity,
                'received_at' => now()->toIso8601String(),
            ],
        ]);

        Log::info('QBO webhook processed', [
            'tenant_id' => $tenant->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'operation' => $operation,
        ]);
    }
}
```

- [ ] **Step 7: Add service item and webhook routes**

Add to `routes/api.php`:

```php
use App\Http\Controllers\Api\QuickBooksServiceItemController;
use App\Http\Controllers\Api\QuickBooksWebhookController;

// Inside the auth:sanctum + qbo prefix + EnsureQuickBooksConnected group:
Route::get('/service-items', [QuickBooksServiceItemController::class, 'index']);
Route::get('/service-items/mappings', [QuickBooksServiceItemController::class, 'listMappings']);
Route::post('/service-items/mappings', [QuickBooksServiceItemController::class, 'createMapping']);
Route::delete('/service-items/mappings/{mapping}', [QuickBooksServiceItemController::class, 'deleteMapping']);

// QBO Webhooks (NO auth — Intuit sends these directly, verified by HMAC signature)
// Place this OUTSIDE the auth:sanctum group but inside the v1 prefix:
Route::post('/qbo/webhooks', [QuickBooksWebhookController::class, 'handle']);
```

- [ ] **Step 8: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/QuickBooks/QuickBooksServiceItemTest.php tests/Feature/QuickBooks/QuickBooksWebhookTest.php`
Expected: All 6 tests PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Services/QuickBooks/QuickBooksServiceItemService.php app/Http/Controllers/Api/QuickBooksServiceItemController.php app/Http/Controllers/Api/QuickBooksWebhookController.php routes/api.php tests/Feature/QuickBooks/
git commit -m "feat: add QBO service item mapping CRUD and webhook handler with signature verification"
```

---

## Task 11: QBO Sync Log API

**Files:**
- Modify: `routes/api.php`
- Create: `app/Http/Controllers/Api/QboSyncLogController.php`

- [ ] **Step 1: Create QboSyncLogController**

```php
// app/Http/Controllers/Api/QboSyncLogController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QboSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QboSyncLogController extends Controller
{
    /**
     * GET /api/v1/qbo/sync-log — paginated sync log.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        $query = QboSyncLog::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        $logs = $query->paginate($request->input('per_page', 25));

        return response()->json($logs);
    }

    /**
     * GET /api/v1/qbo/sync-log/{id} — single sync log entry with payloads.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenant = app('current_tenant');
        $log = QboSyncLog::where('tenant_id', $tenant->id)->findOrFail($id);

        return response()->json(['data' => $log]);
    }

    /**
     * GET /api/v1/qbo/sync-log/summary — sync statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        $stats = QboSyncLog::where('tenant_id', $tenant->id)
            ->selectRaw("
                entity_type,
                status,
                COUNT(*) as count,
                MAX(created_at) as last_sync_at
            ")
            ->groupBy('entity_type', 'status')
            ->get();

        return response()->json(['data' => $stats]);
    }
}
```

- [ ] **Step 2: Add sync log routes**

Add inside the `EnsureQuickBooksConnected` middleware group:

```php
use App\Http\Controllers\Api\QboSyncLogController;

// Sync Log
Route::get('/sync-log/summary', [QboSyncLogController::class, 'summary']);
Route::get('/sync-log/{id}', [QboSyncLogController::class, 'show']);
Route::get('/sync-log', [QboSyncLogController::class, 'index']);
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/QboSyncLogController.php routes/api.php
git commit -m "feat: add QBO sync log API with filtering, pagination, and summary"
```

---

## Task 12: Real-Time Event Broadcasting

**Files:**
- Create: `app/Events/TimeEntryCreated.php`
- Create: `app/Events/TimeEntryUpdated.php`
- Create: `app/Events/ComplianceAlert.php`
- Create: `app/Events/SyncStatusUpdate.php`
- Modify: `routes/channels.php`
- Create: `tests/Feature/Broadcasting/TenantBroadcastTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Broadcasting/TenantBroadcastTest.php
<?php

namespace Tests\Feature\Broadcasting;

use App\Events\ComplianceAlert;
use App\Events\SyncStatusUpdate;
use App\Events\TimeEntryCreated;
use App\Events\TimeEntryUpdated;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TenantBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantAndUser(): array
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

        return [$tenant, $user];
    }

    public function test_time_entry_created_broadcasts_on_tenant_channel(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $event = new TimeEntryCreated($tenant->id, [
            'id' => fake()->uuid(),
            'employee_name' => 'John Doe',
            'job_name' => 'Site A',
            'clock_in' => now()->toIso8601String(),
            'clock_method' => 'GEOFENCE',
        ]);

        $this->assertEquals("private-tenant.{$tenant->id}.events", $event->broadcastOn()->name);
        $this->assertEquals('time-entry.created', $event->broadcastAs());
    }

    public function test_time_entry_updated_broadcasts_on_tenant_channel(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $event = new TimeEntryUpdated($tenant->id, [
            'id' => fake()->uuid(),
            'employee_name' => 'John Doe',
            'clock_out' => now()->toIso8601String(),
            'total_hours' => 8.5,
        ]);

        $this->assertEquals("private-tenant.{$tenant->id}.events", $event->broadcastOn()->name);
        $this->assertEquals('time-entry.updated', $event->broadcastAs());
    }

    public function test_compliance_alert_broadcasts_on_tenant_channel(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $event = new ComplianceAlert($tenant->id, [
            'type' => 'MISSING_CLOCK_OUT',
            'employee_name' => 'Jane Smith',
            'message' => 'Employee has been clocked in for 12 hours without clock-out.',
            'severity' => 'warning',
        ]);

        $this->assertEquals("private-tenant.{$tenant->id}.events", $event->broadcastOn()->name);
        $this->assertEquals('compliance.alert', $event->broadcastAs());
        $this->assertArrayHasKey('type', $event->broadcastWith());
    }

    public function test_sync_status_update_broadcasts(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $event = new SyncStatusUpdate($tenant->id, [
            'entity_type' => 'INVOICE',
            'status' => 'SUCCESS',
            'message' => 'Invoice #501 pushed to QBO.',
        ]);

        $this->assertEquals("private-tenant.{$tenant->id}.events", $event->broadcastOn()->name);
        $this->assertEquals('sync.status', $event->broadcastAs());
    }

    public function test_tenant_channel_authorization(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        // User from this tenant should be authorized
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-tenant.{$tenant->id}.events",
            ]);

        $response->assertStatus(200);
    }

    public function test_tenant_channel_rejects_other_tenant(): void
    {
        [$tenantA, $userA] = $this->createTenantAndUser();

        $tenantB = Tenant::create([
            'name' => 'Other Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        // User A should NOT be authorized to listen on tenant B's channel
        $response = $this->actingAs($userA, 'sanctum')
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-tenant.{$tenantB->id}.events",
            ]);

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Broadcasting/TenantBroadcastTest.php`
Expected: FAIL — event classes and channel auth don't exist.

- [ ] **Step 3: Create TimeEntryCreated event**

```php
// app/Events/TimeEntryCreated.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimeEntryCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantId,
        public array $data
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("tenant.{$this->tenantId}.events");
    }

    public function broadcastAs(): string
    {
        return 'time-entry.created';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
```

- [ ] **Step 4: Create TimeEntryUpdated event**

```php
// app/Events/TimeEntryUpdated.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimeEntryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantId,
        public array $data
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("tenant.{$this->tenantId}.events");
    }

    public function broadcastAs(): string
    {
        return 'time-entry.updated';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
```

- [ ] **Step 5: Create ComplianceAlert event**

```php
// app/Events/ComplianceAlert.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplianceAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantId,
        public array $data
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("tenant.{$this->tenantId}.events");
    }

    public function broadcastAs(): string
    {
        return 'compliance.alert';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
```

- [ ] **Step 6: Create SyncStatusUpdate event**

```php
// app/Events/SyncStatusUpdate.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncStatusUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantId,
        public array $data
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("tenant.{$this->tenantId}.events");
    }

    public function broadcastAs(): string
    {
        return 'sync.status';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
```

- [ ] **Step 7: Configure channel authorization in routes/channels.php**

```php
// routes/channels.php
<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Authorize private/presence broadcast channels for the application.
| The tenant channel ensures users can only listen to their own tenant's events.
|
*/

Broadcast::channel('tenant.{tenantId}.events', function ($user, $tenantId) {
    return $user->tenant_id === $tenantId;
});
```

- [ ] **Step 8: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Broadcasting/TenantBroadcastTest.php`
Expected: All 6 tests PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Events/ routes/channels.php tests/Feature/Broadcasting/
git commit -m "feat: add real-time broadcast events (TimeEntry, ComplianceAlert, SyncStatus) with tenant channel auth"
```

---

## Task 13: FCM Push Notifications

**Files:**
- Create: `config/fcm.php`
- Create: `database/migrations/xxxx_create_device_tokens_table.php`
- Create: `app/Models/DeviceToken.php`
- Create: `app/Channels/FcmChannel.php`
- Create: `app/Http/Controllers/Api/DeviceTokenController.php`
- Create: `app/Notifications/ClockConfirmationNotification.php`
- Create: `app/Notifications/BreakReminderNotification.php`
- Create: `app/Notifications/OvertimeApproachingNotification.php`
- Create: `app/Notifications/TimesheetApprovalNotification.php`
- Create: `app/Notifications/TransferNotification.php`
- Create: `app/Notifications/ScheduleChangeNotification.php`
- Create: `tests/Feature/Notifications/PushNotificationTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create FCM config**

```php
// config/fcm.php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging Configuration
    |--------------------------------------------------------------------------
    */

    // Path to the Firebase service account credentials JSON file
    'credentials_file' => env('FCM_CREDENTIALS_FILE', storage_path('app/firebase-credentials.json')),

    // FCM API endpoint
    'api_url' => 'https://fcm.googleapis.com/v1/projects/',

    // Firebase project ID
    'project_id' => env('FCM_PROJECT_ID'),
];
```

- [ ] **Step 2: Create device tokens migration**

```php
// database/migrations/2024_01_01_000013_create_device_tokens_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_id');
            $table->uuid('tenant_id');
            $table->string('token', 500);
            $table->string('platform', 10); // ios, android
            $table->string('device_id', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['user_id', 'token']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
```

- [ ] **Step 3: Create DeviceToken model**

```php
// app/Models/DeviceToken.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'token',
        'platform',
        'device_id',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 4: Create FcmChannel**

```php
// app/Channels/FcmChannel.php
<?php

namespace App\Channels;

use App\Models\DeviceToken;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmChannel
{
    /**
     * Send the given notification via FCM.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $fcmPayload = $notification->toFcm($notifiable);

        // Get all active device tokens for this user
        $tokens = DeviceToken::where('user_id', $notifiable->id)
            ->active()
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $projectId = config('fcm.project_id');
        $credentialsFile = config('fcm.credentials_file');

        // In test/local mode, just log the notification
        if (! file_exists($credentialsFile) || app()->environment('testing')) {
            Log::info('FCM notification (simulated)', [
                'user_id' => $notifiable->id,
                'tokens' => count($tokens),
                'payload' => $fcmPayload,
            ]);
            return;
        }

        $accessToken = $this->getAccessToken($credentialsFile);

        foreach ($tokens as $token) {
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $fcmPayload['title'] ?? 'GeoTime',
                        'body' => $fcmPayload['body'] ?? '',
                    ],
                    'data' => $fcmPayload['data'] ?? [],
                ],
            ];

            try {
                $response = Http::withToken($accessToken)
                    ->post(
                        config('fcm.api_url') . "{$projectId}/messages:send",
                        $message
                    );

                if (! $response->successful()) {
                    Log::warning('FCM send failed', [
                        'user_id' => $notifiable->id,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    // If token is invalid, deactivate it
                    if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                        DeviceToken::where('token', $token)->update(['is_active' => false]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('FCM send exception', [
                    'user_id' => $notifiable->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get a Google OAuth access token from the service account credentials.
     */
    private function getAccessToken(string $credentialsFile): string
    {
        $credentials = json_decode(file_get_contents($credentialsFile), true);

        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claimSet = base64_encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signatureInput = "{$header}.{$claimSet}";
        openssl_sign($signatureInput, $signature, $credentials['private_key'], 'SHA256');
        $jwt = "{$signatureInput}." . base64_encode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        return $response->json('access_token');
    }
}
```

- [ ] **Step 5: Create notification classes**

```php
// app/Notifications/ClockConfirmationNotification.php
<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClockConfirmationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $action, // 'in' or 'out'
        private string $jobName,
        private string $time
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        $actionLabel = $this->action === 'in' ? 'Clocked In' : 'Clocked Out';

        return [
            'title' => $actionLabel,
            'body' => "{$actionLabel} at {$this->jobName} — {$this->time}",
            'data' => [
                'type' => 'clock_confirmation',
                'action' => $this->action,
                'job_name' => $this->jobName,
                'time' => $this->time,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'clock_confirmation',
            'action' => $this->action,
            'job_name' => $this->jobName,
            'time' => $this->time,
        ];
    }
}
```

```php
// app/Notifications/BreakReminderNotification.php
<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BreakReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private float $hoursWorked
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Break Reminder',
            'body' => "You've been working for {$this->hoursWorked} hours. Time to take a meal break!",
            'data' => [
                'type' => 'break_reminder',
                'hours_worked' => $this->hoursWorked,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'break_reminder',
            'hours_worked' => $this->hoursWorked,
        ];
    }
}
```

```php
// app/Notifications/OvertimeApproachingNotification.php
<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OvertimeApproachingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private float $currentHours,
        private int $threshold // 35, 38, or 40
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        $message = match ($this->threshold) {
            35 => "You've worked {$this->currentHours} hours this week. Approaching overtime.",
            38 => "You've worked {$this->currentHours} hours this week. Overtime starts at 40 hours.",
            40 => "You've reached {$this->currentHours} hours this week. You are now in overtime.",
            default => "Weekly hours update: {$this->currentHours} hours.",
        };

        return [
            'title' => 'Overtime Alert',
            'body' => $message,
            'data' => [
                'type' => 'overtime_approaching',
                'current_hours' => $this->currentHours,
                'threshold' => $this->threshold,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'overtime_approaching',
            'current_hours' => $this->currentHours,
            'threshold' => $this->threshold,
        ];
    }
}
```

```php
// app/Notifications/TimesheetApprovalNotification.php
<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TimesheetApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $status, // 'approved', 'rejected'
        private string $weekEnding,
        private ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        $statusLabel = ucfirst($this->status);
        $body = "Your timesheet for week ending {$this->weekEnding} has been {$this->status}.";
        if ($this->reason) {
            $body .= " Reason: {$this->reason}";
        }

        return [
            'title' => "Timesheet {$statusLabel}",
            'body' => $body,
            'data' => [
                'type' => 'timesheet_approval',
                'status' => $this->status,
                'week_ending' => $this->weekEnding,
                'reason' => $this->reason,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'timesheet_approval',
            'status' => $this->status,
            'week_ending' => $this->weekEnding,
            'reason' => $this->reason,
        ];
    }
}
```

```php
// app/Notifications/TransferNotification.php
<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransferNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $fromTeam,
        private string $toTeam,
        private string $transferType, // PERMANENT, TEMPORARY
        private string $effectiveDate,
        private ?string $expectedReturnDate = null
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        $body = "You've been transferred from {$this->fromTeam} to {$this->toTeam}, effective {$this->effectiveDate}.";
        if ($this->transferType === 'TEMPORARY' && $this->expectedReturnDate) {
            $body .= " This is a temporary transfer. Expected return: {$this->expectedReturnDate}.";
        }

        return [
            'title' => 'Team Transfer',
            'body' => $body,
            'data' => [
                'type' => 'transfer',
                'from_team' => $this->fromTeam,
                'to_team' => $this->toTeam,
                'transfer_type' => $this->transferType,
                'effective_date' => $this->effectiveDate,
                'expected_return_date' => $this->expectedReturnDate,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'transfer',
            'from_team' => $this->fromTeam,
            'to_team' => $this->toTeam,
            'transfer_type' => $this->transferType,
            'effective_date' => $this->effectiveDate,
            'expected_return_date' => $this->expectedReturnDate,
        ];
    }
}
```

```php
// app/Notifications/ScheduleChangeNotification.php
<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ScheduleChangeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $changeDescription,
        private string $effectiveDate
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Schedule Change',
            'body' => "{$this->changeDescription} — Effective {$this->effectiveDate}.",
            'data' => [
                'type' => 'schedule_change',
                'description' => $this->changeDescription,
                'effective_date' => $this->effectiveDate,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'schedule_change',
            'description' => $this->changeDescription,
            'effective_date' => $this->effectiveDate,
        ];
    }
}
```

- [ ] **Step 6: Create DeviceTokenController**

```php
// app/Http/Controllers/Api/DeviceTokenController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * POST /api/v1/device-tokens — register or update a device token.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:500',
            'platform' => 'required|in:ios,android',
            'device_id' => 'sometimes|string|max:255',
        ]);

        $user = $request->user();
        $tenant = app('current_tenant');

        $deviceToken = DeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $request->input('token'),
            ],
            [
                'tenant_id' => $tenant->id,
                'platform' => $request->input('platform'),
                'device_id' => $request->input('device_id'),
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'data' => [
                'id' => $deviceToken->id,
                'registered' => true,
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/device-tokens — remove a device token (logout/uninstall).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = $request->user();

        DeviceToken::where('user_id', $user->id)
            ->where('token', $request->input('token'))
            ->delete();

        return response()->json([
            'data' => ['removed' => true],
        ]);
    }
}
```

- [ ] **Step 7: Write notification tests**

```php
// tests/Feature/Notifications/PushNotificationTest.php
<?php

namespace Tests\Feature\Notifications;

use App\Channels\FcmChannel;
use App\Models\DeviceToken;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ClockConfirmationNotification;
use App\Notifications\OvertimeApproachingNotification;
use App\Notifications\TransferNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantAndUser(): array
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

        return [$tenant, $user];
    }

    public function test_register_device_token(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/device-tokens', [
                'token' => 'fcm-token-abc123',
                'platform' => 'ios',
                'device_id' => 'iphone-12-xxx',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.registered', true);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-abc123',
            'platform' => 'ios',
        ]);
    }

    public function test_remove_device_token(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        DeviceToken::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'token' => 'fcm-token-abc123',
            'platform' => 'ios',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/device-tokens', [
                'token' => 'fcm-token-abc123',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-token-abc123']);
    }

    public function test_clock_confirmation_notification_structure(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $notification = new ClockConfirmationNotification('in', 'Site A', '9:00 AM');

        $fcmPayload = $notification->toFcm($user);

        $this->assertEquals('Clocked In', $fcmPayload['title']);
        $this->assertStringContainsString('Site A', $fcmPayload['body']);
        $this->assertEquals('clock_confirmation', $fcmPayload['data']['type']);
    }

    public function test_overtime_notification_at_thresholds(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $notification35 = new OvertimeApproachingNotification(35.5, 35);
        $payload35 = $notification35->toFcm($user);
        $this->assertStringContainsString('35.5 hours', $payload35['body']);

        $notification40 = new OvertimeApproachingNotification(40.0, 40);
        $payload40 = $notification40->toFcm($user);
        $this->assertStringContainsString('overtime', $payload40['body']);
    }

    public function test_transfer_notification_includes_team_info(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $notification = new TransferNotification(
            'Team Alpha', 'Team Beta', 'TEMPORARY', '2026-04-01', '2026-05-01'
        );

        $fcmPayload = $notification->toFcm($user);

        $this->assertStringContainsString('Team Alpha', $fcmPayload['body']);
        $this->assertStringContainsString('Team Beta', $fcmPayload['body']);
        $this->assertStringContainsString('temporary', strtolower($fcmPayload['body']));
    }

    public function test_notification_dispatches_via_fake(): void
    {
        Notification::fake();

        [$tenant, $user] = $this->createTenantAndUser();

        $user->notify(new ClockConfirmationNotification('in', 'Site A', '9:00 AM'));

        Notification::assertSentTo($user, ClockConfirmationNotification::class);
    }
}
```

- [ ] **Step 8: Add device token routes**

Add to `routes/api.php` inside the `auth:sanctum` v1 group:

```php
use App\Http\Controllers\Api\DeviceTokenController;

// Device Tokens (FCM)
Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
```

- [ ] **Step 9: Run migrations and tests**

Run: `docker compose exec app php artisan migrate`
Run: `docker compose exec app php artisan test tests/Feature/Notifications/PushNotificationTest.php`
Expected: All 6 tests PASS.

- [ ] **Step 10: Commit**

```bash
git add config/fcm.php database/migrations/*device_tokens* app/Models/DeviceToken.php app/Channels/FcmChannel.php app/Http/Controllers/Api/DeviceTokenController.php app/Notifications/ routes/api.php tests/Feature/Notifications/
git commit -m "feat: add FCM push notifications with 6 notification types and device token management"
```

---

## Task 14: Scheduled Jobs

**Files:**
- Create: `app/Jobs/CheckTrialExpirationsJob.php`
- Create: `app/Jobs/RevertTemporaryTransfersJob.php`
- Create: `app/Jobs/QboRefreshTokensJob.php`
- Create: `app/Jobs/QboRetrySyncJob.php`
- Modify: `routes/console.php` (Laravel 13 schedule definition)
- Create: `tests/Feature/Jobs/TrialExpirationTest.php`
- Create: `tests/Feature/Jobs/TransferReversionTest.php`
- Create: `tests/Feature/Jobs/QboRetrySyncTest.php`

- [ ] **Step 1: Create CheckTrialExpirationsJob**

```php
// app/Jobs/CheckTrialExpirationsJob.php
<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckTrialExpirationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $expiredTrials = Tenant::where('status', 'trial')
            ->where('trial_ends_at', '<=', now())
            ->get();

        foreach ($expiredTrials as $tenant) {
            // Check if they have an active Stripe subscription
            if ($tenant->subscribed()) {
                $tenant->update(['status' => 'active']);
                Log::info('Trial tenant converted to active', ['tenant_id' => $tenant->id]);
            } else {
                $tenant->update(['status' => 'past_due']);
                Log::info('Trial expired, tenant set to past_due', ['tenant_id' => $tenant->id]);
            }
        }

        Log::info('Trial expiration check completed', [
            'expired_count' => $expiredTrials->count(),
        ]);
    }
}
```

- [ ] **Step 2: Create RevertTemporaryTransfersJob**

```php
// app/Jobs/RevertTemporaryTransfersJob.php
<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\TransferRecord;
use App\Notifications\TransferNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RevertTemporaryTransfersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $transfersDue = TransferRecord::where('transfer_type', 'TEMPORARY')
            ->where('status', 'COMPLETED')
            ->where('expected_return_date', '<=', now()->format('Y-m-d'))
            ->get();

        foreach ($transfersDue as $transfer) {
            try {
                $employee = Employee::find($transfer->employee_id);

                if (! $employee) {
                    continue;
                }

                // Revert employee to original team
                $employee->update(['current_team_id' => $transfer->from_team_id]);

                // Mark transfer as reverted
                $transfer->update(['status' => 'REVERTED']);

                // Create a reversion transfer record
                TransferRecord::create([
                    'tenant_id' => $transfer->tenant_id,
                    'employee_id' => $transfer->employee_id,
                    'from_team_id' => $transfer->to_team_id,
                    'to_team_id' => $transfer->from_team_id,
                    'reason_category' => 'ADMINISTRATIVE',
                    'reason_code' => 'TEAM_RESTRUCTURE',
                    'notes' => "Auto-reversion of temporary transfer #{$transfer->id}",
                    'transfer_type' => 'PERMANENT',
                    'effective_date' => now()->format('Y-m-d'),
                    'initiated_by' => null, // System-initiated
                    'status' => 'COMPLETED',
                ]);

                // Notify the employee via their user account
                $user = $employee->user ?? null;
                if ($user) {
                    $fromTeam = $transfer->toTeam->name ?? 'Unknown';
                    $toTeam = $transfer->fromTeam->name ?? 'Unknown';
                    $user->notify(new TransferNotification(
                        $fromTeam,
                        $toTeam,
                        'PERMANENT',
                        now()->format('Y-m-d')
                    ));
                }

                Log::info('Temporary transfer auto-reverted', [
                    'transfer_id' => $transfer->id,
                    'employee_id' => $transfer->employee_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to revert temporary transfer', [
                    'transfer_id' => $transfer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Temporary transfer reversion check completed', [
            'reverted_count' => $transfersDue->count(),
        ]);
    }
}
```

- [ ] **Step 3: Create QboRefreshTokensJob**

```php
// app/Jobs/QboRefreshTokensJob.php
<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QboRefreshTokensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Find all tenants with QBO connected whose tokens expire within 24 hours
        // Refresh tokens proactively to avoid interruptions
        $tenants = Tenant::whereNotNull('qbo_realm_id')
            ->whereNotNull('qbo_refresh_token')
            ->where('qbo_token_expires_at', '<=', now()->addHours(24))
            ->get();

        $refreshed = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $client = new QuickBooksClient($tenant);
                $client->refreshToken();
                $refreshed++;

                Log::info('QBO token refreshed proactively', ['tenant_id' => $tenant->id]);
            } catch (\Exception $e) {
                $failed++;
                Log::error('QBO token refresh failed', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('QBO token refresh job completed', [
            'refreshed' => $refreshed,
            'failed' => $failed,
        ]);
    }
}
```

- [ ] **Step 4: Create QboRetrySyncJob**

```php
// app/Jobs/QboRetrySyncJob.php
<?php

namespace App\Jobs;

use App\Models\QboSyncLog;
use App\Models\Tenant;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QboRetrySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Find all retryable sync log entries (failed, retry count < 5, due for retry)
        $retryable = QboSyncLog::retryable()
            ->with([])
            ->limit(50) // Process max 50 per run
            ->get();

        $retried = 0;
        $succeeded = 0;

        foreach ($retryable as $log) {
            try {
                $tenant = Tenant::find($log->tenant_id);

                if (! $tenant || ! $tenant->isQboConnected()) {
                    continue;
                }

                app()->instance('current_tenant', $tenant);

                $client = new QuickBooksClient($tenant);

                // Re-attempt the original request
                if ($log->direction === 'PUSH' && $log->request_payload) {
                    $realmId = $client->getRealmId();
                    $entityPath = strtolower($log->entity_type);

                    $response = $client->post(
                        "/v3/company/{$realmId}/{$entityPath}",
                        $log->request_payload
                    );

                    if ($response->successful()) {
                        $log->markSuccess($response->json());
                        $succeeded++;
                    } else {
                        $log->markFailed($response->body(), $response->json());
                    }
                }

                $retried++;
            } catch (\Exception $e) {
                $log->markFailed($e->getMessage());
                Log::error('QBO sync retry failed', [
                    'sync_log_id' => $log->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('QBO sync retry job completed', [
            'retried' => $retried,
            'succeeded' => $succeeded,
        ]);
    }
}
```

- [ ] **Step 5: Register scheduled jobs in routes/console.php**

```php
// routes/console.php
<?php

use App\Jobs\CheckTrialExpirationsJob;
use App\Jobs\QboRefreshTokensJob;
use App\Jobs\QboRetrySyncJob;
use App\Jobs\RevertTemporaryTransfersJob;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Check for expired trials — runs daily at midnight
Schedule::job(new CheckTrialExpirationsJob)->daily()->at('00:00')
    ->name('check-trial-expirations')
    ->withoutOverlapping();

// Revert temporary transfers that have reached their return date — daily at 6 AM
Schedule::job(new RevertTemporaryTransfersJob)->daily()->at('06:00')
    ->name('revert-temporary-transfers')
    ->withoutOverlapping();

// Proactively refresh QBO tokens — every 6 hours
Schedule::job(new QboRefreshTokensJob)->everySixHours()
    ->name('qbo-refresh-tokens')
    ->withoutOverlapping();

// Retry failed QBO sync operations — every 15 minutes
Schedule::job(new QboRetrySyncJob)->everyFifteenMinutes()
    ->name('qbo-retry-sync')
    ->withoutOverlapping();
```

- [ ] **Step 6: Write trial expiration test**

```php
// tests/Feature/Jobs/TrialExpirationTest.php
<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CheckTrialExpirationsJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_trial_set_to_past_due(): void
    {
        $tenant = Tenant::create([
            'name' => 'Expired Trial Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        (new CheckTrialExpirationsJob)->handle();

        $tenant->refresh();
        $this->assertEquals('past_due', $tenant->status);
    }

    public function test_active_trial_not_affected(): void
    {
        $tenant = Tenant::create([
            'name' => 'Active Trial Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(7),
        ]);

        (new CheckTrialExpirationsJob)->handle();

        $tenant->refresh();
        $this->assertEquals('trial', $tenant->status);
    }

    public function test_non_trial_tenants_not_affected(): void
    {
        $tenant = Tenant::create([
            'name' => 'Active Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        (new CheckTrialExpirationsJob)->handle();

        $tenant->refresh();
        $this->assertEquals('active', $tenant->status);
    }
}
```

- [ ] **Step 7: Write transfer reversion test**

```php
// tests/Feature/Jobs/TransferReversionTest.php
<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RevertTemporaryTransfersJob;
use App\Models\Employee;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TransferRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferReversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_transfer_reverted_on_return_date(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $teamA = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Team Alpha',
            'status' => 'ACTIVE',
        ]);

        $teamB = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Team Beta',
            'status' => 'ACTIVE',
        ]);

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@test.com',
            'role' => 'employee',
            'hourly_rate' => 25.00,
            'current_team_id' => $teamB->id, // Currently on Team B (temp transfer)
            'status' => 'ACTIVE',
        ]);

        $transfer = TransferRecord::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'from_team_id' => $teamA->id,
            'to_team_id' => $teamB->id,
            'reason_category' => 'OPERATIONAL',
            'reason_code' => 'PROJECT_NEED',
            'transfer_type' => 'TEMPORARY',
            'effective_date' => now()->subDays(30)->format('Y-m-d'),
            'expected_return_date' => now()->subDay()->format('Y-m-d'), // Due yesterday
            'status' => 'COMPLETED',
        ]);

        (new RevertTemporaryTransfersJob)->handle();

        $employee->refresh();
        $this->assertEquals($teamA->id, $employee->current_team_id);

        $transfer->refresh();
        $this->assertEquals('REVERTED', $transfer->status);

        // Verify reversion transfer record was created
        $this->assertDatabaseHas('transfer_records', [
            'employee_id' => $employee->id,
            'from_team_id' => $teamB->id,
            'to_team_id' => $teamA->id,
            'notes' => "Auto-reversion of temporary transfer #{$transfer->id}",
        ]);
    }

    public function test_future_transfers_not_reverted(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $teamA = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Team Alpha',
            'status' => 'ACTIVE',
        ]);

        $teamB = Team::create([
            'tenant_id' => $tenant->id,
            'name' => 'Team Beta',
            'status' => 'ACTIVE',
        ]);

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@test.com',
            'role' => 'employee',
            'hourly_rate' => 30.00,
            'current_team_id' => $teamB->id,
            'status' => 'ACTIVE',
        ]);

        TransferRecord::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'from_team_id' => $teamA->id,
            'to_team_id' => $teamB->id,
            'reason_category' => 'OPERATIONAL',
            'reason_code' => 'PROJECT_NEED',
            'transfer_type' => 'TEMPORARY',
            'effective_date' => now()->subDays(5)->format('Y-m-d'),
            'expected_return_date' => now()->addDays(25)->format('Y-m-d'), // Not due yet
            'status' => 'COMPLETED',
        ]);

        (new RevertTemporaryTransfersJob)->handle();

        $employee->refresh();
        $this->assertEquals($teamB->id, $employee->current_team_id); // Still on Team B
    }
}
```

- [ ] **Step 8: Write QBO retry sync test**

```php
// tests/Feature/Jobs/QboRetrySyncTest.php
<?php

namespace Tests\Feature\Jobs;

use App\Jobs\QboRetrySyncJob;
use App\Models\QboSyncLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QboRetrySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_retries_failed_sync_operations(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'fake-token',
            'qbo_refresh_token' => 'fake-refresh',
            'qbo_token_expires_at' => now()->addMinutes(30),
        ]);

        $failedLog = QboSyncLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'INVOICE',
            'direction' => 'PUSH',
            'status' => 'FAILED',
            'error_message' => 'Rate limit exceeded',
            'retry_count' => 1,
            'next_retry_at' => now()->subMinute(), // Due for retry
            'request_payload' => [
                'CustomerRef' => ['value' => '1'],
                'Line' => [['Amount' => 500]],
            ],
        ]);

        Http::fake([
            'sandbox-quickbooks.api.intuit.com/*' => Http::response([
                'Invoice' => ['Id' => '999', 'SyncToken' => '0'],
            ], 200),
        ]);

        (new QboRetrySyncJob)->handle();

        $failedLog->refresh();
        $this->assertEquals('SUCCESS', $failedLog->status);
    }

    public function test_skips_logs_not_due_for_retry(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'fake-token',
            'qbo_refresh_token' => 'fake-refresh',
            'qbo_token_expires_at' => now()->addMinutes(30),
        ]);

        $futureRetry = QboSyncLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'INVOICE',
            'direction' => 'PUSH',
            'status' => 'FAILED',
            'retry_count' => 1,
            'next_retry_at' => now()->addHour(), // Not due yet
            'request_payload' => ['Line' => []],
        ]);

        Http::fake();

        (new QboRetrySyncJob)->handle();

        $futureRetry->refresh();
        $this->assertEquals('FAILED', $futureRetry->status); // Not retried
        Http::assertNothingSent();
    }

    public function test_does_not_retry_after_max_attempts(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'qbo_realm_id' => '1234567890',
            'qbo_access_token' => 'fake-token',
            'qbo_refresh_token' => 'fake-refresh',
            'qbo_token_expires_at' => now()->addMinutes(30),
        ]);

        $maxedOut = QboSyncLog::create([
            'tenant_id' => $tenant->id,
            'entity_type' => 'INVOICE',
            'direction' => 'PUSH',
            'status' => 'FAILED',
            'retry_count' => 5, // At max
            'next_retry_at' => now()->subMinute(),
            'request_payload' => ['Line' => []],
        ]);

        Http::fake();

        (new QboRetrySyncJob)->handle();

        $maxedOut->refresh();
        $this->assertEquals('FAILED', $maxedOut->status);
        Http::assertNothingSent();
    }
}
```

- [ ] **Step 9: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Jobs/`
Expected: All 8 tests PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Jobs/CheckTrialExpirationsJob.php app/Jobs/RevertTemporaryTransfersJob.php app/Jobs/QboRefreshTokensJob.php app/Jobs/QboRetrySyncJob.php routes/console.php tests/Feature/Jobs/
git commit -m "feat: add scheduled jobs for trial expiration, transfer reversion, QBO token refresh, and sync retry"
```

---

## Task 15: Complete API Routes Assembly & Verification

**Files:**
- Modify: `routes/api.php` (final assembled version)

- [ ] **Step 1: Verify complete routes/api.php**

The final `routes/api.php` should look like this (combining all additions from Tasks 4-13):

```php
// routes/api.php
<?php

use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\QboSyncLogController;
use App\Http\Controllers\Api\QuickBooksAuthController;
use App\Http\Controllers\Api\QuickBooksBankFeedController;
use App\Http\Controllers\Api\QuickBooksCustomerController;
use App\Http\Controllers\Api\QuickBooksEmployeeController;
use App\Http\Controllers\Api\QuickBooksEstimateController;
use App\Http\Controllers\Api\QuickBooksInvoiceController;
use App\Http\Controllers\Api\QuickBooksServiceItemController;
use App\Http\Controllers\Api\QuickBooksWebhookController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Middleware\EnsureQuickBooksConnected;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth (public)
    Route::post('/auth/register', RegisterController::class);
    Route::post('/auth/login', [LoginController::class, 'login']);

    // QBO Webhooks (public — verified by HMAC signature, not user auth)
    Route::post('/qbo/webhooks', [QuickBooksWebhookController::class, 'handle']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/auth/me', [LoginController::class, 'me']);

        // Device Tokens (FCM)
        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

        // QuickBooks Integration
        Route::prefix('qbo')->group(function () {
            // Auth (connect/disconnect/status)
            Route::get('/connect', [QuickBooksAuthController::class, 'connect']);
            Route::get('/callback', [QuickBooksAuthController::class, 'callback']);
            Route::post('/disconnect', [QuickBooksAuthController::class, 'disconnect']);
            Route::get('/status', [QuickBooksAuthController::class, 'status']);

            // QBO operations (requires active QBO connection)
            Route::middleware(EnsureQuickBooksConnected::class)->group(function () {

                // Customers
                Route::post('/customers/pull', [QuickBooksCustomerController::class, 'pull']);
                Route::post('/customers/push/{job}', [QuickBooksCustomerController::class, 'push']);
                Route::post('/customers/sync', [QuickBooksCustomerController::class, 'sync']);

                // Employees
                Route::post('/employees/pull', [QuickBooksEmployeeController::class, 'pull']);
                Route::post('/employees/push/{employee}', [QuickBooksEmployeeController::class, 'push']);
                Route::post('/employees/sync', [QuickBooksEmployeeController::class, 'sync']);

                // Estimates
                Route::post('/estimates/generate/{job}', [QuickBooksEstimateController::class, 'generate']);
                Route::post('/estimates/generate-async/{job}', [QuickBooksEstimateController::class, 'generateAsync']);
                Route::get('/estimates/{estimate}/status', [QuickBooksEstimateController::class, 'status']);

                // Invoices
                Route::post('/invoices/generate/{job}', [QuickBooksInvoiceController::class, 'generate']);
                Route::post('/invoices/generate-async/{job}', [QuickBooksInvoiceController::class, 'generateAsync']);
                Route::post('/invoices/convert-estimate', [QuickBooksInvoiceController::class, 'convertEstimate']);
                Route::get('/invoices/{invoice}/status', [QuickBooksInvoiceController::class, 'status']);

                // Bank Feeds
                Route::post('/bank-feeds/push', [QuickBooksBankFeedController::class, 'push']);
                Route::get('/bank-feeds/reconciliation', [QuickBooksBankFeedController::class, 'reconciliation']);

                // Service Items
                Route::get('/service-items', [QuickBooksServiceItemController::class, 'index']);
                Route::get('/service-items/mappings', [QuickBooksServiceItemController::class, 'listMappings']);
                Route::post('/service-items/mappings', [QuickBooksServiceItemController::class, 'createMapping']);
                Route::delete('/service-items/mappings/{mapping}', [QuickBooksServiceItemController::class, 'deleteMapping']);

                // Sync Log
                Route::get('/sync-log/summary', [QboSyncLogController::class, 'summary']);
                Route::get('/sync-log/{id}', [QboSyncLogController::class, 'show']);
                Route::get('/sync-log', [QboSyncLogController::class, 'index']);
            });
        });
    });
});
```

- [ ] **Step 2: List all routes to verify**

Run: `docker compose exec app php artisan route:list --path=api/v1`

Expected output should show all QBO, device token, and auth routes.

- [ ] **Step 3: Run the full test suite**

Run: `docker compose exec app php artisan test`

Expected: All tests PASS.

- [ ] **Step 4: Commit**

```bash
git add routes/api.php
git commit -m "feat: assemble complete API routes for QBO integration, notifications, and broadcasting"
```

---

## Summary of Deliverables

| # | Component | Files | Tests |
|---|-----------|-------|-------|
| 1 | QBO Config & Migrations | 4 files | — |
| 2 | QboSyncLog & ServiceItemMapping models | 2 models | 4 tests |
| 3 | QuickBooksClient (HTTP) | 1 service + 1 exception | 5 tests |
| 4 | QBO OAuth 2.0 Flow | 1 service + 1 controller + 1 middleware | 6 tests |
| 5 | QBO Customer Sync | 1 service + 1 controller + 1 job | 3 tests |
| 6 | QBO Employee Sync | 1 service + 1 controller + 1 job | 3 tests |
| 7 | QBO Estimate Generation | 1 service + 1 controller + 1 job | 3 tests |
| 8 | QBO Invoice Generation | 1 service + 1 controller + 1 job | 3 tests |
| 9 | QBO Bank Feeds (Rutter) | 1 service + 1 controller + 1 job | 3 tests |
| 10 | QBO Service Items & Webhooks | 2 services + 2 controllers | 6 tests |
| 11 | QBO Sync Log API | 1 controller | — |
| 12 | Real-Time Broadcasting | 4 events + channels.php | 6 tests |
| 13 | FCM Push Notifications | 6 notifications + 1 channel + 1 controller + 1 model + 1 migration | 6 tests |
| 14 | Scheduled Jobs | 4 jobs + console.php | 8 tests |
| 15 | Route Assembly | routes/api.php | Full suite |

**Total:** ~45 new files, ~56 tests
