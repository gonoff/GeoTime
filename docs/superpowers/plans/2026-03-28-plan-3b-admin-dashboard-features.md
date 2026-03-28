# Plan 3b: Admin Dashboard Features (Tasks 9-16)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the admin dashboard with job/geofence management, real-time map, timesheet approval, reports with CSV/PDF export, PTO management, billing settings, and company settings pages.

**Architecture:** Vue 3 + Inertia.js pages rendered by Laravel controllers. Leaflet.js for map views (geofence editor + real-time dashboard). Laravel Echo + Reverb for live updates. Chart.js for report visualizations. Laravel Cashier for billing UI. DomPDF for PDF export.

**Tech Stack:** Vue 3 (Composition API), Inertia.js, Tailwind CSS, Leaflet.js 1.9, @vue-leaflet/vue-leaflet, leaflet-draw, Laravel Echo, Chart.js, vue-chartjs, barryvdh/laravel-dompdf, Laravel Cashier 16.x

**Note:** This is Part B of Plan 3. Part A (tasks 1-8) covered layout, shared components, auth pages, dashboard overview, employee CRUD, team CRUD, and transfer management. All shared components (DataTable, Modal, Button, FormInput, StatusBadge, etc.) and the authenticated layout are already built.

---

## File Structure

```
GeoTime/
├── app/
│   └── Http/
│       └── Controllers/
│           └── Admin/
│               ├── JobController.php
│               ├── GeofenceController.php
│               ├── MapDashboardController.php
│               ├── TimesheetController.php
│               ├── ReportController.php
│               ├── PtoController.php
│               ├── BillingController.php
│               └── CompanySettingsController.php
├── app/
│   └── Events/
│       └── EmployeeLocationUpdated.php
├── app/
│   └── Exports/
│       ├── PayrollSummaryExport.php
│       ├── AttendanceExport.php
│       ├── OvertimeExport.php
│       ├── JobCostingExport.php
│       ├── TeamUtilizationExport.php
│       ├── TransferHistoryExport.php
│       ├── ComplianceAuditExport.php
│       └── GeofenceActivityExport.php
├── resources/
│   └── js/
│       ├── Pages/
│       │   ├── Jobs/
│       │   │   ├── Index.vue
│       │   │   ├── Create.vue
│       │   │   └── Edit.vue
│       │   ├── Geofences/
│       │   │   ├── Index.vue
│       │   │   └── Editor.vue
│       │   ├── Map/
│       │   │   └── Dashboard.vue
│       │   ├── Timesheets/
│       │   │   └── Index.vue
│       │   ├── Reports/
│       │   │   ├── Index.vue
│       │   │   ├── PayrollSummary.vue
│       │   │   ├── Attendance.vue
│       │   │   ├── Overtime.vue
│       │   │   ├── JobCosting.vue
│       │   │   ├── TeamUtilization.vue
│       │   │   ├── TransferHistory.vue
│       │   │   ├── ComplianceAudit.vue
│       │   │   └── GeofenceActivity.vue
│       │   ├── Pto/
│       │   │   └── Index.vue
│       │   ├── Billing/
│       │   │   └── Index.vue
│       │   └── Settings/
│       │       └── Company.vue
│       └── Components/
│           ├── LeafletMap.vue
│           ├── GeofenceDrawer.vue
│           ├── RealtimeMap.vue
│           ├── ReportDateFilter.vue
│           ├── PtoCalendar.vue
│           └── ChartWrapper.vue
├── resources/
│   └── views/
│       └── pdf/
│           ├── payroll-summary.blade.php
│           ├── attendance.blade.php
│           └── job-costing.blade.php
├── routes/
│   └── web.php (append)
└── tests/
    └── Feature/
        └── Admin/
            ├── JobManagementTest.php
            ├── GeofenceManagementTest.php
            ├── MapDashboardTest.php
            ├── TimesheetApprovalTest.php
            ├── ReportTest.php
            ├── PtoManagementTest.php
            ├── BillingSettingsTest.php
            └── CompanySettingsTest.php
```

---

## Task 9: Job / Job Site Management

**Files:**
- Create: `app/Http/Controllers/Admin/JobController.php`
- Create: `resources/js/Pages/Jobs/Index.vue`
- Create: `resources/js/Pages/Jobs/Create.vue`
- Create: `resources/js/Pages/Jobs/Edit.vue`
- Create: `tests/Feature/Admin/JobManagementTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the feature test**

```php
// tests/Feature/Admin/JobManagementTest.php
<?php

namespace Tests\Feature\Admin;

use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $this->tenant);
    }

    public function test_job_index_page_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.jobs.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Jobs/Index')
        );
    }

    public function test_can_create_job(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.jobs.store'), [
                'name' => 'Downtown Office Build',
                'client_name' => 'Acme Corp',
                'address' => '123 Main St, New York, NY 10001',
                'status' => 'active',
                'budget_hours' => 500.00,
                'hourly_rate' => 45.00,
                'start_date' => '2026-04-01',
                'end_date' => '2026-09-30',
            ]);

        $response->assertRedirect(route('admin.jobs.index'));
        $this->assertDatabaseHas('jobs', [
            'name' => 'Downtown Office Build',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_update_job_status(): void
    {
        $job = Job::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Job',
            'client_name' => 'Client',
            'address' => '456 Elm St',
            'status' => 'active',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-03-01',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.jobs.update', $job), [
                'name' => 'Test Job',
                'client_name' => 'Client',
                'address' => '456 Elm St',
                'status' => 'completed',
                'budget_hours' => 100,
                'hourly_rate' => 30,
                'start_date' => '2026-03-01',
            ]);

        $response->assertRedirect(route('admin.jobs.index'));
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'status' => 'completed',
        ]);
    }

    public function test_can_delete_job(): void
    {
        $job = Job::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Delete Me',
            'client_name' => 'Client',
            'address' => '789 Oak Ave',
            'status' => 'on_hold',
            'budget_hours' => 50,
            'hourly_rate' => 25,
            'start_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.jobs.destroy', $job));

        $response->assertRedirect(route('admin.jobs.index'));
        $this->assertSoftDeleted('jobs', ['id' => $job->id]);
    }

    public function test_job_index_shows_budget_hours_tracking(): void
    {
        $job = Job::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Budget Job',
            'client_name' => 'Client',
            'address' => '100 Test Blvd',
            'status' => 'active',
            'budget_hours' => 200,
            'hourly_rate' => 40,
            'start_date' => '2026-03-01',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jobs.index'));

        $response->assertInertia(fn ($page) =>
            $page->component('Jobs/Index')
                ->has('jobs.data', 1)
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Admin/JobManagementTest.php`
Expected: FAIL — controller/routes do not exist.

- [ ] **Step 3: Add routes**

Append to `routes/web.php` inside the authenticated admin group:

```php
// routes/web.php — add inside the admin middleware group
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\GeofenceController;
use App\Http\Controllers\Admin\MapDashboardController;
use App\Http\Controllers\Admin\TimesheetController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\PtoController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\CompanySettingsController;

Route::resource('jobs', JobController::class)->names('admin.jobs');
```

- [ ] **Step 4: Create JobController**

```php
// app/Http/Controllers/Admin/JobController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Geofence;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $jobs = Job::query()
            ->withCount('geofences')
            ->withSum('timeEntries', 'total_hours')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")
                ->orWhere('client_name', 'ilike', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Jobs/Create', [
            'geofences' => Geofence::whereNull('job_id')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'status' => 'required|in:active,completed,on_hold',
            'budget_hours' => 'required|numeric|min:0',
            'hourly_rate' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        Job::create($validated);

        return redirect()->route('admin.jobs.index')
            ->with('success', 'Job created successfully.');
    }

    public function edit(Job $job)
    {
        $job->load('geofences');
        $actualHours = $job->timeEntries()->sum('total_hours');

        return Inertia::render('Jobs/Edit', [
            'job' => $job,
            'actualHours' => (float) $actualHours,
        ]);
    }

    public function update(Request $request, Job $job)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'status' => 'required|in:active,completed,on_hold',
            'budget_hours' => 'required|numeric|min:0',
            'hourly_rate' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $job->update($validated);

        return redirect()->route('admin.jobs.index')
            ->with('success', 'Job updated successfully.');
    }

    public function destroy(Job $job)
    {
        $job->delete();

        return redirect()->route('admin.jobs.index')
            ->with('success', 'Job deleted successfully.');
    }
}
```

- [ ] **Step 5: Create Jobs/Index.vue**

```vue
<!-- resources/js/Pages/Jobs/Index.vue -->
<script setup>
import { ref, watch } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DataTable from '@/Components/DataTable.vue'
import Button from '@/Components/Button.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
    jobs: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')
const showDeleteModal = ref(false)
const jobToDelete = ref(null)

const columns = [
    { key: 'name', label: 'Job Name', sortable: true },
    { key: 'client_name', label: 'Client', sortable: true },
    { key: 'status', label: 'Status' },
    { key: 'budget_hours', label: 'Budget Hours', align: 'right' },
    { key: 'time_entries_sum_total_hours', label: 'Actual Hours', align: 'right' },
    { key: 'progress', label: 'Progress' },
    { key: 'geofences_count', label: 'Geofences', align: 'center' },
    { key: 'actions', label: '', align: 'right' },
]

watch([search, statusFilter], () => {
    router.get(route('admin.jobs.index'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, replace: true })
})

function confirmDelete(job) {
    jobToDelete.value = job
    showDeleteModal.value = true
}

function deleteJob() {
    router.delete(route('admin.jobs.destroy', jobToDelete.value.id), {
        onFinish: () => {
            showDeleteModal.value = false
            jobToDelete.value = null
        },
    })
}

function progressPercent(job) {
    if (!job.budget_hours || job.budget_hours === 0) return 0
    return Math.min(100, Math.round(((job.time_entries_sum_total_hours || 0) / job.budget_hours) * 100))
}

function progressColor(pct) {
    if (pct >= 90) return 'bg-red-500'
    if (pct >= 75) return 'bg-yellow-500'
    return 'bg-green-500'
}

const statusMap = {
    active: { label: 'Active', color: 'green' },
    completed: { label: 'Completed', color: 'blue' },
    on_hold: { label: 'On Hold', color: 'yellow' },
}
</script>

<template>
    <Head title="Jobs" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Jobs / Job Sites</h2>
                <Button :href="route('admin.jobs.create')">+ New Job</Button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="mb-4 flex flex-wrap gap-4">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search jobs or clients..."
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <select
                        v-model="statusFilter"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                    </select>
                </div>

                <!-- Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-for="col in columns" :key="col.key"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                    :class="{ 'text-right': col.align === 'right', 'text-center': col.align === 'center' }">
                                    {{ col.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="job in jobs.data" :key="job.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ job.name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ job.client_name }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <StatusBadge :color="statusMap[job.status]?.color">
                                        {{ statusMap[job.status]?.label }}
                                    </StatusBadge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600">
                                    {{ job.budget_hours ? Number(job.budget_hours).toFixed(1) : '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600">
                                    {{ (job.time_entries_sum_total_hours || 0).toFixed(1) }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                                            <div
                                                :class="progressColor(progressPercent(job))"
                                                class="h-full rounded-full transition-all"
                                                :style="{ width: progressPercent(job) + '%' }"
                                            />
                                        </div>
                                        <span class="text-xs text-gray-500">{{ progressPercent(job) }}%</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-600">
                                    {{ job.geofences_count }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Button size="sm" variant="secondary" :href="route('admin.jobs.edit', job.id)">Edit</Button>
                                    <button @click="confirmDelete(job)" class="ml-2 text-red-600 hover:text-red-800 text-sm">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="!jobs.data.length">
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500">
                                    No jobs found. Create your first job to get started.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Delete confirmation modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Delete Job</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Are you sure you want to delete "{{ jobToDelete?.name }}"? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <Button variant="secondary" @click="showDeleteModal = false">Cancel</Button>
                    <Button variant="danger" @click="deleteJob">Delete Job</Button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 6: Create Jobs/Create.vue**

```vue
<!-- resources/js/Pages/Jobs/Create.vue -->
<script setup>
import { useForm, Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Button from '@/Components/Button.vue'
import FormInput from '@/Components/FormInput.vue'

const form = useForm({
    name: '',
    client_name: '',
    address: '',
    status: 'active',
    budget_hours: '',
    hourly_rate: '',
    start_date: '',
    end_date: '',
})

function submit() {
    form.post(route('admin.jobs.store'))
}
</script>

<template>
    <Head title="Create Job" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Create Job</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6 rounded-lg bg-white p-6 shadow">
                    <FormInput v-model="form.name" label="Job Name" :error="form.errors.name" required />
                    <FormInput v-model="form.client_name" label="Client Name" :error="form.errors.client_name" required />

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea
                            v-model="form.address"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        />
                        <p v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <FormInput v-model="form.budget_hours" label="Budget Hours" type="number" step="0.5" :error="form.errors.budget_hours" required />
                        <FormInput v-model="form.hourly_rate" label="Hourly Rate ($)" type="number" step="0.01" :error="form.errors.hourly_rate" required />
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <FormInput v-model="form.start_date" label="Start Date" type="date" :error="form.errors.start_date" required />
                        <FormInput v-model="form.end_date" label="End Date" type="date" :error="form.errors.end_date" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button variant="secondary" :href="route('admin.jobs.index')">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Create Job</Button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 7: Create Jobs/Edit.vue**

```vue
<!-- resources/js/Pages/Jobs/Edit.vue -->
<script setup>
import { useForm, Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Button from '@/Components/Button.vue'
import FormInput from '@/Components/FormInput.vue'

const props = defineProps({
    job: Object,
    actualHours: Number,
})

const form = useForm({
    name: props.job.name,
    client_name: props.job.client_name,
    address: props.job.address,
    status: props.job.status,
    budget_hours: props.job.budget_hours,
    hourly_rate: props.job.hourly_rate,
    start_date: props.job.start_date,
    end_date: props.job.end_date || '',
})

function submit() {
    form.put(route('admin.jobs.update', props.job.id))
}

const budgetUsed = props.job.budget_hours
    ? Math.round((props.actualHours / props.job.budget_hours) * 100)
    : 0
</script>

<template>
    <Head :title="`Edit: ${job.name}`" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Edit Job: {{ job.name }}</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <!-- Budget tracking card -->
                <div class="mb-6 rounded-lg bg-white p-4 shadow">
                    <h3 class="text-sm font-medium text-gray-500">Budget Tracking</h3>
                    <div class="mt-2 flex items-center gap-4">
                        <div class="flex-1">
                            <div class="flex justify-between text-sm">
                                <span>{{ actualHours.toFixed(1) }} / {{ Number(job.budget_hours).toFixed(1) }} hrs</span>
                                <span :class="budgetUsed > 90 ? 'text-red-600' : 'text-gray-600'">{{ budgetUsed }}%</span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="budgetUsed > 90 ? 'bg-red-500' : budgetUsed > 75 ? 'bg-yellow-500' : 'bg-green-500'"
                                    :style="{ width: Math.min(100, budgetUsed) + '%' }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Linked geofences -->
                <div v-if="job.geofences?.length" class="mb-6 rounded-lg bg-white p-4 shadow">
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Linked Geofences</h3>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="gf in job.geofences" :key="gf.id"
                            class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
                            {{ gf.name }} ({{ gf.radius_meters }}m)
                        </span>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6 rounded-lg bg-white p-6 shadow">
                    <FormInput v-model="form.name" label="Job Name" :error="form.errors.name" required />
                    <FormInput v-model="form.client_name" label="Client Name" :error="form.errors.client_name" required />

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea
                            v-model="form.address"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        />
                        <p v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <FormInput v-model="form.budget_hours" label="Budget Hours" type="number" step="0.5" :error="form.errors.budget_hours" required />
                        <FormInput v-model="form.hourly_rate" label="Hourly Rate ($)" type="number" step="0.01" :error="form.errors.hourly_rate" required />
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <FormInput v-model="form.start_date" label="Start Date" type="date" :error="form.errors.start_date" required />
                        <FormInput v-model="form.end_date" label="End Date" type="date" :error="form.errors.end_date" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button variant="secondary" :href="route('admin.jobs.index')">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Save Changes</Button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 8: Run tests and verify**

Run: `docker compose exec app php artisan test tests/Feature/Admin/JobManagementTest.php`
Expected: All tests PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/JobController.php resources/js/Pages/Jobs/ tests/Feature/Admin/JobManagementTest.php routes/web.php
git commit -m "feat: add job/job site management with CRUD, budget tracking, and status management"
```

---

## Task 10: Geofence Editor

**Files:**
- Create: `app/Http/Controllers/Admin/GeofenceController.php`
- Create: `resources/js/Pages/Geofences/Index.vue`
- Create: `resources/js/Pages/Geofences/Editor.vue`
- Create: `resources/js/Components/LeafletMap.vue`
- Create: `resources/js/Components/GeofenceDrawer.vue`
- Create: `tests/Feature/Admin/GeofenceManagementTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Install Leaflet dependencies**

```bash
docker compose exec app npm install leaflet @vue-leaflet/vue-leaflet leaflet-draw
```

- [ ] **Step 2: Write the feature test**

```php
// tests/Feature/Admin/GeofenceManagementTest.php
<?php

namespace Tests\Feature\Admin;

use App\Models\Geofence;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofenceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->job = Job::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Job',
            'client_name' => 'Client',
            'address' => '123 Main St',
            'status' => 'active',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-03-01',
        ]);

        app()->instance('current_tenant', $this->tenant);
    }

    public function test_geofence_index_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.geofences.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Geofences/Index')
        );
    }

    public function test_geofence_editor_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.geofences.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Geofences/Editor')
        );
    }

    public function test_can_create_geofence(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.geofences.store'), [
                'name' => 'Main Entrance',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'radius_meters' => 150,
                'job_id' => $this->job->id,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.geofences.index'));
        $this->assertDatabaseHas('geofences', [
            'name' => 'Main Entrance',
            'radius_meters' => 150,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_radius_must_be_between_50_and_500(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.geofences.store'), [
                'name' => 'Too Small',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'radius_meters' => 10,
                'job_id' => $this->job->id,
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors('radius_meters');
    }

    public function test_can_toggle_geofence_active_status(): void
    {
        $geofence = Geofence::create([
            'tenant_id' => $this->tenant->id,
            'job_id' => $this->job->id,
            'name' => 'Toggle Test',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.geofences.toggle', $geofence));

        $response->assertRedirect();
        $this->assertDatabaseHas('geofences', [
            'id' => $geofence->id,
            'is_active' => false,
        ]);
    }

    public function test_can_update_geofence(): void
    {
        $geofence = Geofence::create([
            'tenant_id' => $this->tenant->id,
            'job_id' => $this->job->id,
            'name' => 'Original',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.geofences.update', $geofence), [
                'name' => 'Updated',
                'latitude' => 40.7130,
                'longitude' => -74.0065,
                'radius_meters' => 250,
                'job_id' => $this->job->id,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.geofences.index'));
        $this->assertDatabaseHas('geofences', [
            'id' => $geofence->id,
            'name' => 'Updated',
            'radius_meters' => 250,
        ]);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Admin/GeofenceManagementTest.php`
Expected: FAIL.

- [ ] **Step 4: Add routes**

Append to `routes/web.php` inside the admin group:

```php
Route::resource('geofences', GeofenceController::class)->names('admin.geofences');
Route::patch('geofences/{geofence}/toggle', [GeofenceController::class, 'toggle'])->name('admin.geofences.toggle');
```

- [ ] **Step 5: Create GeofenceController**

```php
// app/Http/Controllers/Admin/GeofenceController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use App\Models\Job;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GeofenceController extends Controller
{
    public function index(Request $request)
    {
        $geofences = Geofence::query()
            ->with('job:id,name')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Geofences/Index', [
            'geofences' => $geofences,
            'filters' => $request->only(['search', 'active']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Geofences/Editor', [
            'geofence' => null,
            'jobs' => Job::where('status', 'active')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:50|max:500',
            'job_id' => 'required|exists:jobs,id',
            'is_active' => 'boolean',
        ]);

        Geofence::create($validated);

        return redirect()->route('admin.geofences.index')
            ->with('success', 'Geofence created successfully.');
    }

    public function edit(Geofence $geofence)
    {
        return Inertia::render('Geofences/Editor', [
            'geofence' => $geofence,
            'jobs' => Job::where('status', 'active')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Geofence $geofence)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:50|max:500',
            'job_id' => 'required|exists:jobs,id',
            'is_active' => 'boolean',
        ]);

        $geofence->update($validated);

        return redirect()->route('admin.geofences.index')
            ->with('success', 'Geofence updated successfully.');
    }

    public function destroy(Geofence $geofence)
    {
        $geofence->delete();

        return redirect()->route('admin.geofences.index')
            ->with('success', 'Geofence deleted successfully.');
    }

    public function toggle(Geofence $geofence)
    {
        $geofence->update(['is_active' => !$geofence->is_active]);

        return redirect()->back()
            ->with('success', 'Geofence ' . ($geofence->is_active ? 'activated' : 'deactivated') . '.');
    }
}
```

- [ ] **Step 6: Create LeafletMap.vue component**

```vue
<!-- resources/js/Components/LeafletMap.vue -->
<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
    center: { type: Array, default: () => [39.8283, -98.5795] },
    zoom: { type: Number, default: 5 },
    height: { type: String, default: '500px' },
})

const emit = defineEmits(['mapReady'])
const mapContainer = ref(null)
let map = null

onMounted(() => {
    map = L.map(mapContainer.value).setView(props.center, props.zoom)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map)

    // Fix Leaflet default icon path issue with bundlers
    delete L.Icon.Default.prototype._getIconUrl
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
        iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
        shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
    })

    emit('mapReady', map)
})

watch(() => props.center, (newCenter) => {
    if (map) map.setView(newCenter, props.zoom)
})

onUnmounted(() => {
    if (map) {
        map.remove()
        map = null
    }
})

defineExpose({ getMap: () => map })
</script>

<template>
    <div ref="mapContainer" :style="{ height, width: '100%' }" class="rounded-lg border border-gray-200" />
</template>
```

- [ ] **Step 7: Create GeofenceDrawer.vue component**

```vue
<!-- resources/js/Components/GeofenceDrawer.vue -->
<script setup>
import { ref, onMounted, watch } from 'vue'
import L from 'leaflet'
import 'leaflet-draw'
import 'leaflet-draw/dist/leaflet.draw.css'

const props = defineProps({
    map: { type: Object, default: null },
    initialCircle: { type: Object, default: null }, // { lat, lng, radius }
})

const emit = defineEmits(['circleDrawn', 'circleUpdated'])

let drawnItems = null
let currentCircle = null

function initDrawing(map) {
    drawnItems = new L.FeatureGroup()
    map.addLayer(drawnItems)

    // If editing existing geofence, draw it
    if (props.initialCircle) {
        currentCircle = L.circle(
            [props.initialCircle.lat, props.initialCircle.lng],
            { radius: props.initialCircle.radius, color: '#4f46e5', fillOpacity: 0.2 }
        )
        drawnItems.addLayer(currentCircle)
        map.setView([props.initialCircle.lat, props.initialCircle.lng], 15)
    }

    const drawControl = new L.Control.Draw({
        position: 'topright',
        draw: {
            polygon: false,
            polyline: false,
            rectangle: false,
            marker: false,
            circlemarker: false,
            circle: {
                shapeOptions: { color: '#4f46e5', fillOpacity: 0.2 },
            },
        },
        edit: {
            featureGroup: drawnItems,
        },
    })
    map.addControl(drawControl)

    map.on(L.Draw.Event.CREATED, (e) => {
        // Remove previous circle if any
        if (currentCircle) drawnItems.removeLayer(currentCircle)

        currentCircle = e.layer
        drawnItems.addLayer(currentCircle)

        const center = currentCircle.getLatLng()
        const radius = Math.round(currentCircle.getRadius())
        emit('circleDrawn', { lat: center.lat, lng: center.lng, radius: Math.min(500, Math.max(50, radius)) })
    })

    map.on(L.Draw.Event.EDITED, () => {
        if (currentCircle) {
            const center = currentCircle.getLatLng()
            const radius = Math.round(currentCircle.getRadius())
            emit('circleUpdated', { lat: center.lat, lng: center.lng, radius: Math.min(500, Math.max(50, radius)) })
        }
    })
}

watch(() => props.map, (map) => {
    if (map) initDrawing(map)
})

onMounted(() => {
    if (props.map) initDrawing(props.map)
})

function updateRadius(radius) {
    if (currentCircle) {
        currentCircle.setRadius(radius)
        const center = currentCircle.getLatLng()
        emit('circleUpdated', { lat: center.lat, lng: center.lng, radius })
    }
}

defineExpose({ updateRadius })
</script>

<template>
    <div />
</template>
```

- [ ] **Step 8: Create Geofences/Index.vue**

```vue
<!-- resources/js/Pages/Geofences/Index.vue -->
<script setup>
import { ref, watch } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Button from '@/Components/Button.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({
    geofences: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')

watch(search, () => {
    router.get(route('admin.geofences.index'), {
        search: search.value || undefined,
    }, { preserveState: true, replace: true })
})

function toggleActive(geofence) {
    router.patch(route('admin.geofences.toggle', geofence.id), {}, { preserveState: true })
}
</script>

<template>
    <Head title="Geofences" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Geofences</h2>
                <Button :href="route('admin.geofences.create')">+ New Geofence</Button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search geofences..."
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Job</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Radius (m)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Coordinates</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="gf in geofences.data" :key="gf.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ gf.name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ gf.job?.name || '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-600">{{ gf.radius_meters }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ Number(gf.latitude).toFixed(5) }}, {{ Number(gf.longitude).toFixed(5) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-center">
                                    <button @click="toggleActive(gf)" class="focus:outline-none">
                                        <StatusBadge :color="gf.is_active ? 'green' : 'gray'">
                                            {{ gf.is_active ? 'Active' : 'Inactive' }}
                                        </StatusBadge>
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Button size="sm" variant="secondary" :href="route('admin.geofences.edit', gf.id)">Edit</Button>
                                </td>
                            </tr>
                            <tr v-if="!geofences.data.length">
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">
                                    No geofences found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 9: Create Geofences/Editor.vue**

```vue
<!-- resources/js/Pages/Geofences/Editor.vue -->
<script setup>
import { ref } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import LeafletMap from '@/Components/LeafletMap.vue'
import GeofenceDrawer from '@/Components/GeofenceDrawer.vue'
import Button from '@/Components/Button.vue'
import FormInput from '@/Components/FormInput.vue'

const props = defineProps({
    geofence: Object,
    jobs: Array,
})

const isEditing = !!props.geofence
const mapInstance = ref(null)
const drawerRef = ref(null)

const form = useForm({
    name: props.geofence?.name || '',
    latitude: props.geofence?.latitude || '',
    longitude: props.geofence?.longitude || '',
    radius_meters: props.geofence?.radius_meters || 100,
    job_id: props.geofence?.job_id || '',
    is_active: props.geofence?.is_active ?? true,
})

function onMapReady(map) {
    mapInstance.value = map
}

function onCircleDrawn(data) {
    form.latitude = data.lat.toFixed(7)
    form.longitude = data.lng.toFixed(7)
    form.radius_meters = data.radius
}

function onCircleUpdated(data) {
    form.latitude = data.lat.toFixed(7)
    form.longitude = data.lng.toFixed(7)
    form.radius_meters = data.radius
}

function onRadiusChange() {
    if (drawerRef.value) {
        drawerRef.value.updateRadius(Number(form.radius_meters))
    }
}

function submit() {
    if (isEditing) {
        form.put(route('admin.geofences.update', props.geofence.id))
    } else {
        form.post(route('admin.geofences.store'))
    }
}

const initialCircle = props.geofence
    ? { lat: Number(props.geofence.latitude), lng: Number(props.geofence.longitude), radius: props.geofence.radius_meters }
    : null
</script>

<template>
    <Head :title="isEditing ? `Edit: ${geofence.name}` : 'New Geofence'" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                {{ isEditing ? `Edit Geofence: ${geofence.name}` : 'Create Geofence' }}
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Map (2/3 width) -->
                    <div class="lg:col-span-2">
                        <div class="rounded-lg bg-white p-4 shadow">
                            <p class="mb-2 text-sm text-gray-500">Click the circle tool on the map to draw a geofence, or enter coordinates manually.</p>
                            <LeafletMap
                                :center="initialCircle ? [initialCircle.lat, initialCircle.lng] : [39.8283, -98.5795]"
                                :zoom="initialCircle ? 15 : 5"
                                height="500px"
                                @map-ready="onMapReady"
                            />
                            <GeofenceDrawer
                                ref="drawerRef"
                                :map="mapInstance"
                                :initial-circle="initialCircle"
                                @circle-drawn="onCircleDrawn"
                                @circle-updated="onCircleUpdated"
                            />
                        </div>
                    </div>

                    <!-- Form (1/3 width) -->
                    <div>
                        <form @submit.prevent="submit" class="space-y-5 rounded-lg bg-white p-6 shadow">
                            <FormInput v-model="form.name" label="Geofence Name" :error="form.errors.name" placeholder="e.g., Main Entrance" required />

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Linked Job</label>
                                <select
                                    v-model="form.job_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Select a job...</option>
                                    <option v-for="job in jobs" :key="job.id" :value="job.id">{{ job.name }}</option>
                                </select>
                                <p v-if="form.errors.job_id" class="mt-1 text-sm text-red-600">{{ form.errors.job_id }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <FormInput v-model="form.latitude" label="Latitude" type="number" step="0.0000001" :error="form.errors.latitude" required />
                                <FormInput v-model="form.longitude" label="Longitude" type="number" step="0.0000001" :error="form.errors.longitude" required />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Radius: {{ form.radius_meters }}m
                                </label>
                                <input
                                    v-model.number="form.radius_meters"
                                    type="range"
                                    min="50"
                                    max="500"
                                    step="10"
                                    class="mt-2 w-full"
                                    @input="onRadiusChange"
                                />
                                <div class="mt-1 flex justify-between text-xs text-gray-400">
                                    <span>50m</span>
                                    <span>500m</span>
                                </div>
                                <p v-if="form.errors.radius_meters" class="mt-1 text-sm text-red-600">{{ form.errors.radius_meters }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    id="is_active"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <label for="is_active" class="text-sm text-gray-700">Active</label>
                            </div>

                            <div class="flex flex-col gap-2">
                                <Button type="submit" :disabled="form.processing" class="w-full">
                                    {{ isEditing ? 'Save Changes' : 'Create Geofence' }}
                                </Button>
                                <Button variant="secondary" :href="route('admin.geofences.index')" class="w-full">Cancel</Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 10: Run tests and verify**

Run: `docker compose exec app php artisan test tests/Feature/Admin/GeofenceManagementTest.php`
Expected: All tests PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/Admin/GeofenceController.php resources/js/Pages/Geofences/ resources/js/Components/LeafletMap.vue resources/js/Components/GeofenceDrawer.vue tests/Feature/Admin/GeofenceManagementTest.php routes/web.php package.json package-lock.json
git commit -m "feat: add geofence editor with Leaflet.js map, circle drawing, and radius slider"
```

---

## Task 11: Real-Time Map Dashboard

**Files:**
- Create: `app/Http/Controllers/Admin/MapDashboardController.php`
- Create: `app/Events/EmployeeLocationUpdated.php`
- Create: `resources/js/Pages/Map/Dashboard.vue`
- Create: `resources/js/Components/RealtimeMap.vue`
- Create: `tests/Feature/Admin/MapDashboardTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the feature test**

```php
// tests/Feature/Admin/MapDashboardTest.php
<?php

namespace Tests\Feature\Admin;

use App\Models\Geofence;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $this->tenant);
    }

    public function test_map_dashboard_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.map'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Map/Dashboard')
                ->has('geofences')
                ->has('activeEmployees')
        );
    }

    public function test_map_dashboard_includes_geofences(): void
    {
        $job = Job::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Job',
            'client_name' => 'Client',
            'address' => '123 Main',
            'status' => 'active',
            'budget_hours' => 100,
            'hourly_rate' => 30,
            'start_date' => '2026-03-01',
        ]);

        Geofence::create([
            'tenant_id' => $this->tenant->id,
            'job_id' => $job->id,
            'name' => 'Site A',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'radius_meters' => 150,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.map'));

        $response->assertInertia(fn ($page) =>
            $page->has('geofences', 1)
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Admin/MapDashboardTest.php`
Expected: FAIL.

- [ ] **Step 3: Create EmployeeLocationUpdated event**

```php
// app/Events/EmployeeLocationUpdated.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int|string $tenantId,
        public array $employeeData,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.events"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'employee.location.updated';
    }

    public function broadcastWith(): array
    {
        return $this->employeeData;
    }
}
```

- [ ] **Step 4: Add route**

Append to `routes/web.php` inside the admin group:

```php
Route::get('map', [MapDashboardController::class, 'index'])->name('admin.map');
```

- [ ] **Step 5: Create MapDashboardController**

```php
// app/Http/Controllers/Admin/MapDashboardController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use App\Models\TimeEntry;
use Inertia\Inertia;

class MapDashboardController extends Controller
{
    public function index()
    {
        $geofences = Geofence::where('is_active', true)
            ->with('job:id,name')
            ->get(['id', 'name', 'latitude', 'longitude', 'radius_meters', 'job_id']);

        // Get employees currently clocked in (no clock_out)
        $activeEntries = TimeEntry::whereNull('clock_out')
            ->with('employee:id,first_name,last_name,current_team_id')
            ->with('job:id,name')
            ->get(['id', 'employee_id', 'job_id', 'clock_in', 'clock_in_lat', 'clock_in_lng']);

        $activeEmployees = $activeEntries->map(fn ($entry) => [
            'id' => $entry->employee_id,
            'name' => $entry->employee?->first_name . ' ' . $entry->employee?->last_name,
            'job' => $entry->job?->name,
            'clock_in' => $entry->clock_in,
            'lat' => $entry->clock_in_lat,
            'lng' => $entry->clock_in_lng,
        ]);

        return Inertia::render('Map/Dashboard', [
            'geofences' => $geofences,
            'activeEmployees' => $activeEmployees,
            'tenantId' => app('current_tenant')->id,
        ]);
    }
}
```

- [ ] **Step 6: Create RealtimeMap.vue component**

```vue
<!-- resources/js/Components/RealtimeMap.vue -->
<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
    geofences: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    height: { type: String, default: '600px' },
})

const mapContainer = ref(null)
let map = null
const geofenceCircles = {}
const employeeMarkers = {}

// Custom colored marker
function createEmployeeIcon() {
    return L.divIcon({
        className: 'employee-marker',
        html: '<div style="background:#4f46e5;width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>',
        iconSize: [16, 16],
        iconAnchor: [8, 8],
    })
}

onMounted(() => {
    map = L.map(mapContainer.value).setView([39.8283, -98.5795], 5)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map)

    renderGeofences()
    renderEmployees()
    fitBounds()
})

function renderGeofences() {
    props.geofences.forEach(gf => {
        const circle = L.circle([gf.latitude, gf.longitude], {
            radius: gf.radius_meters,
            color: '#4f46e5',
            fillColor: '#4f46e5',
            fillOpacity: 0.1,
            weight: 2,
        }).addTo(map)

        circle.bindPopup(`<strong>${gf.name}</strong><br>Job: ${gf.job?.name || 'Unlinked'}<br>Radius: ${gf.radius_meters}m`)
        geofenceCircles[gf.id] = circle
    })
}

function renderEmployees() {
    props.employees.forEach(emp => {
        if (!emp.lat || !emp.lng) return
        const marker = L.marker([emp.lat, emp.lng], { icon: createEmployeeIcon() }).addTo(map)
        marker.bindPopup(`<strong>${emp.name}</strong><br>Job: ${emp.job || '-'}<br>Since: ${new Date(emp.clock_in).toLocaleTimeString()}`)
        employeeMarkers[emp.id] = marker
    })
}

function fitBounds() {
    const allPoints = [
        ...props.geofences.map(gf => [gf.latitude, gf.longitude]),
        ...props.employees.filter(e => e.lat && e.lng).map(e => [e.lat, e.lng]),
    ]
    if (allPoints.length > 0) {
        map.fitBounds(allPoints, { padding: [50, 50], maxZoom: 14 })
    }
}

function updateEmployee(data) {
    if (!map) return
    if (employeeMarkers[data.id]) {
        employeeMarkers[data.id].setLatLng([data.lat, data.lng])
        employeeMarkers[data.id].setPopupContent(
            `<strong>${data.name}</strong><br>Job: ${data.job || '-'}<br>Since: ${new Date(data.clock_in).toLocaleTimeString()}`
        )
    } else {
        const marker = L.marker([data.lat, data.lng], { icon: createEmployeeIcon() }).addTo(map)
        marker.bindPopup(`<strong>${data.name}</strong><br>Job: ${data.job || '-'}`)
        employeeMarkers[data.id] = marker
    }
}

function removeEmployee(employeeId) {
    if (employeeMarkers[employeeId]) {
        map.removeLayer(employeeMarkers[employeeId])
        delete employeeMarkers[employeeId]
    }
}

onUnmounted(() => {
    if (map) {
        map.remove()
        map = null
    }
})

defineExpose({ updateEmployee, removeEmployee })
</script>

<template>
    <div ref="mapContainer" :style="{ height, width: '100%' }" class="rounded-lg border border-gray-200" />
</template>
```

- [ ] **Step 7: Create Map/Dashboard.vue**

```vue
<!-- resources/js/Pages/Map/Dashboard.vue -->
<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import RealtimeMap from '@/Components/RealtimeMap.vue'

const props = defineProps({
    geofences: Array,
    activeEmployees: Array,
    tenantId: [String, Number],
})

const realtimeMapRef = ref(null)
let echoChannel = null

onMounted(() => {
    if (window.Echo) {
        echoChannel = window.Echo.private(`tenant.${props.tenantId}.events`)

        echoChannel.listen('.employee.location.updated', (data) => {
            if (data.action === 'clock_out') {
                realtimeMapRef.value?.removeEmployee(data.id)
            } else {
                realtimeMapRef.value?.updateEmployee(data)
            }
        })
    }
})

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening('.employee.location.updated')
        window.Echo.leave(`tenant.${props.tenantId}.events`)
    }
})
</script>

<template>
    <Head title="Live Map" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Live Map Dashboard</h2>
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="inline-block h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
                    Live — {{ activeEmployees.length }} employee{{ activeEmployees.length !== 1 ? 's' : '' }} clocked in
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                    <!-- Map (3/4 width) -->
                    <div class="lg:col-span-3">
                        <RealtimeMap
                            ref="realtimeMapRef"
                            :geofences="geofences"
                            :employees="activeEmployees"
                            height="650px"
                        />
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-4">
                        <!-- Active employees list -->
                        <div class="rounded-lg bg-white p-4 shadow">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Clocked In</h3>
                            <div v-if="activeEmployees.length" class="space-y-2 max-h-96 overflow-y-auto">
                                <div
                                    v-for="emp in activeEmployees"
                                    :key="emp.id"
                                    class="flex items-center gap-2 rounded-md bg-gray-50 px-3 py-2"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900">{{ emp.name }}</p>
                                        <p class="truncate text-xs text-gray-500">{{ emp.job || 'No job' }}</p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-400">No employees clocked in.</p>
                        </div>

                        <!-- Geofence summary -->
                        <div class="rounded-lg bg-white p-4 shadow">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Active Geofences</h3>
                            <div class="space-y-2 max-h-60 overflow-y-auto">
                                <div
                                    v-for="gf in geofences"
                                    :key="gf.id"
                                    class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2"
                                >
                                    <span class="text-sm text-gray-700">{{ gf.name }}</span>
                                    <span class="text-xs text-gray-400">{{ gf.radius_meters }}m</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 8: Run tests and verify**

Run: `docker compose exec app php artisan test tests/Feature/Admin/MapDashboardTest.php`
Expected: All tests PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/MapDashboardController.php app/Events/EmployeeLocationUpdated.php resources/js/Pages/Map/ resources/js/Components/RealtimeMap.vue tests/Feature/Admin/MapDashboardTest.php routes/web.php
git commit -m "feat: add real-time map dashboard with Leaflet.js, employee pins, and Laravel Echo integration"
```

---

## Task 12: Timesheet Approval

**Files:**
- Create: `app/Http/Controllers/Admin/TimesheetController.php`
- Create: `resources/js/Pages/Timesheets/Index.vue`
- Create: `tests/Feature/Admin/TimesheetApprovalTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the feature test**

```php
// tests/Feature/Admin/TimesheetApprovalTest.php
<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $team = Team::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Team',
            'status' => 'active',
        ]);

        $this->employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'current_team_id' => $team->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@test.com',
            'role' => 'employee',
            'hourly_rate' => 25.00,
            'status' => 'active',
        ]);

        app()->instance('current_tenant', $this->tenant);
    }

    public function test_timesheet_index_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.timesheets.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Timesheets/Index')
        );
    }

    public function test_can_approve_timesheet(): void
    {
        $entry = TimeEntry::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee->id,
            'clock_in' => now()->subHours(8),
            'clock_out' => now(),
            'total_hours' => 8.0,
            'status' => 'submitted',
            'clock_method' => 'geofence',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.timesheets.approve', $entry), [
                'notes' => 'Looks good',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'status' => 'approved',
        ]);
    }

    public function test_can_reject_timesheet(): void
    {
        $entry = TimeEntry::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee->id,
            'clock_in' => now()->subHours(8),
            'clock_out' => now(),
            'total_hours' => 8.0,
            'status' => 'submitted',
            'clock_method' => 'manual',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.timesheets.reject', $entry), [
                'notes' => 'Hours look incorrect',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'status' => 'rejected',
        ]);
    }

    public function test_can_bulk_approve_timesheets(): void
    {
        $entries = collect([1, 2, 3])->map(fn ($i) => TimeEntry::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee->id,
            'clock_in' => now()->subDays($i)->subHours(8),
            'clock_out' => now()->subDays($i),
            'total_hours' => 8.0,
            'status' => 'submitted',
            'clock_method' => 'geofence',
        ]));

        $response = $this->actingAs($this->admin)
            ->post(route('admin.timesheets.bulk-approve'), [
                'ids' => $entries->pluck('id')->toArray(),
            ]);

        $response->assertRedirect();
        foreach ($entries as $entry) {
            $this->assertDatabaseHas('time_entries', [
                'id' => $entry->id,
                'status' => 'approved',
            ]);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Admin/TimesheetApprovalTest.php`
Expected: FAIL.

- [ ] **Step 3: Add routes**

Append to `routes/web.php` inside the admin group:

```php
Route::get('timesheets', [TimesheetController::class, 'index'])->name('admin.timesheets.index');
Route::patch('timesheets/{timeEntry}/approve', [TimesheetController::class, 'approve'])->name('admin.timesheets.approve');
Route::patch('timesheets/{timeEntry}/reject', [TimesheetController::class, 'reject'])->name('admin.timesheets.reject');
Route::post('timesheets/bulk-approve', [TimesheetController::class, 'bulkApprove'])->name('admin.timesheets.bulk-approve');
```

- [ ] **Step 4: Create TimesheetController**

```php
// app/Http/Controllers/Admin/TimesheetController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimesheetController extends Controller
{
    public function index(Request $request)
    {
        $entries = TimeEntry::query()
            ->with(['employee:id,first_name,last_name,current_team_id', 'employee.team:id,name', 'job:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', 'submitted'))
            ->when($request->team_id, fn ($q, $t) => $q->whereHas('employee', fn ($eq) => $eq->where('current_team_id', $t)))
            ->when($request->search, fn ($q, $s) => $q->whereHas('employee', fn ($eq) =>
                $eq->where('first_name', 'ilike', "%{$s}%")
                    ->orWhere('last_name', 'ilike', "%{$s}%")))
            ->orderBy('clock_in', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Timesheets/Index', [
            'entries' => $entries,
            'teams' => Team::where('status', 'active')->get(['id', 'name']),
            'filters' => $request->only(['status', 'team_id', 'search']),
        ]);
    }

    public function approve(Request $request, TimeEntry $timeEntry)
    {
        $timeEntry->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approval_notes' => $request->input('notes'),
        ]);

        return redirect()->back()->with('success', 'Timesheet approved.');
    }

    public function reject(Request $request, TimeEntry $timeEntry)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        $timeEntry->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approval_notes' => $request->input('notes'),
        ]);

        return redirect()->back()->with('success', 'Timesheet rejected.');
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:time_entries,id',
        ]);

        TimeEntry::whereIn('id', $request->ids)
            ->where('status', 'submitted')
            ->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
            ]);

        return redirect()->back()->with('success', count($request->ids) . ' timesheets approved.');
    }
}
```

- [ ] **Step 5: Create Timesheets/Index.vue**

```vue
<!-- resources/js/Pages/Timesheets/Index.vue -->
<script setup>
import { ref, watch, computed } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Button from '@/Components/Button.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
    entries: Object,
    teams: Array,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || 'submitted')
const teamFilter = ref(props.filters?.team_id || '')
const selectedIds = ref([])
const showRejectModal = ref(false)
const rejectEntry = ref(null)
const rejectNotes = ref('')

const allSelected = computed(() =>
    props.entries.data.length > 0 && selectedIds.value.length === props.entries.data.length
)

function toggleAll() {
    if (allSelected.value) {
        selectedIds.value = []
    } else {
        selectedIds.value = props.entries.data.map(e => e.id)
    }
}

watch([search, statusFilter, teamFilter], () => {
    router.get(route('admin.timesheets.index'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        team_id: teamFilter.value || undefined,
    }, { preserveState: true, replace: true })
})

function approveEntry(entry) {
    router.patch(route('admin.timesheets.approve', entry.id), { notes: '' }, { preserveState: true })
}

function openRejectModal(entry) {
    rejectEntry.value = entry
    rejectNotes.value = ''
    showRejectModal.value = true
}

function submitReject() {
    router.patch(route('admin.timesheets.reject', rejectEntry.value.id), {
        notes: rejectNotes.value,
    }, {
        preserveState: true,
        onFinish: () => {
            showRejectModal.value = false
            rejectEntry.value = null
        },
    })
}

function bulkApprove() {
    router.post(route('admin.timesheets.bulk-approve'), {
        ids: selectedIds.value,
    }, {
        preserveState: true,
        onFinish: () => { selectedIds.value = [] },
    })
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatTime(dateStr) {
    return new Date(dateStr).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

const statusColors = {
    submitted: 'yellow',
    approved: 'green',
    rejected: 'red',
    active: 'blue',
}
</script>

<template>
    <Head title="Timesheet Approval" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Timesheet Approval</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="mb-4 flex flex-wrap items-center gap-4">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search employees..."
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <select
                        v-model="statusFilter"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="submitted">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <select
                        v-model="teamFilter"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All Teams</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>

                    <Button
                        v-if="selectedIds.length > 0"
                        size="sm"
                        @click="bulkApprove"
                    >
                        Approve Selected ({{ selectedIds.length }})
                    </Button>
                </div>

                <!-- Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3">
                                    <input type="checkbox" :checked="allSelected" @change="toggleAll"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Team</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Clock In/Out</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Hours</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Method</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-gray-50">
                                <td class="px-4 py-4">
                                    <input
                                        type="checkbox"
                                        :value="entry.id"
                                        v-model="selectedIds"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ entry.employee?.first_name }} {{ entry.employee?.last_name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                    {{ entry.employee?.team?.name || '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                    {{ formatDate(entry.clock_in) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                    {{ formatTime(entry.clock_in) }} - {{ entry.clock_out ? formatTime(entry.clock_out) : 'Active' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600">
                                    {{ entry.total_hours ? Number(entry.total_hours).toFixed(2) : '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 capitalize">
                                    {{ entry.clock_method }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-center">
                                    <StatusBadge :color="statusColors[entry.status]">{{ entry.status }}</StatusBadge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <template v-if="entry.status === 'submitted'">
                                        <button @click="approveEntry(entry)" class="text-green-600 hover:text-green-800 mr-2">Approve</button>
                                        <button @click="openRejectModal(entry)" class="text-red-600 hover:text-red-800">Reject</button>
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="!entries.data.length">
                                <td colspan="9" class="px-6 py-12 text-center text-sm text-gray-500">
                                    No timesheets found matching your filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Reject modal -->
        <Modal :show="showRejectModal" @close="showRejectModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Reject Timesheet</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Rejecting entry for {{ rejectEntry?.employee?.first_name }} {{ rejectEntry?.employee?.last_name }}.
                    Please provide a reason.
                </p>
                <textarea
                    v-model="rejectNotes"
                    rows="3"
                    placeholder="Reason for rejection (required)..."
                    class="mt-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <div class="mt-6 flex justify-end gap-3">
                    <Button variant="secondary" @click="showRejectModal = false">Cancel</Button>
                    <Button variant="danger" @click="submitReject" :disabled="!rejectNotes.trim()">Reject</Button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 6: Run tests and verify**

Run: `docker compose exec app php artisan test tests/Feature/Admin/TimesheetApprovalTest.php`
Expected: All tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/TimesheetController.php resources/js/Pages/Timesheets/ tests/Feature/Admin/TimesheetApprovalTest.php routes/web.php
git commit -m "feat: add timesheet approval with approve/reject, bulk approve, and team/status filtering"
```

---

## Task 13: Reports

**Files:**
- Create: `app/Http/Controllers/Admin/ReportController.php`
- Create: `app/Exports/PayrollSummaryExport.php`
- Create: `app/Exports/AttendanceExport.php`
- Create: `app/Exports/OvertimeExport.php`
- Create: `app/Exports/JobCostingExport.php`
- Create: `app/Exports/TeamUtilizationExport.php`
- Create: `app/Exports/TransferHistoryExport.php`
- Create: `app/Exports/ComplianceAuditExport.php`
- Create: `app/Exports/GeofenceActivityExport.php`
- Create: `resources/js/Pages/Reports/Index.vue`
- Create: `resources/js/Pages/Reports/PayrollSummary.vue`
- Create: `resources/js/Pages/Reports/Attendance.vue`
- Create: `resources/js/Pages/Reports/Overtime.vue`
- Create: `resources/js/Pages/Reports/JobCosting.vue`
- Create: `resources/js/Pages/Reports/TeamUtilization.vue`
- Create: `resources/js/Pages/Reports/TransferHistory.vue`
- Create: `resources/js/Pages/Reports/ComplianceAudit.vue`
- Create: `resources/js/Pages/Reports/GeofenceActivity.vue`
- Create: `resources/js/Components/ReportDateFilter.vue`
- Create: `resources/js/Components/ChartWrapper.vue`
- Create: `resources/views/pdf/payroll-summary.blade.php`
- Create: `resources/views/pdf/attendance.blade.php`
- Create: `resources/views/pdf/job-costing.blade.php`
- Create: `tests/Feature/Admin/ReportTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Install dependencies**

```bash
docker compose exec app composer require barryvdh/laravel-dompdf
docker compose exec app npm install chart.js vue-chartjs
```

- [ ] **Step 2: Write the feature test**

```php
// tests/Feature/Admin/ReportTest.php
<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\Job;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $this->tenant);
    }

    public function test_report_index_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Reports/Index')
        );
    }

    public function test_payroll_summary_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.payroll-summary', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Reports/PayrollSummary')
        );
    }

    public function test_payroll_summary_csv_export(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.payroll-summary.csv', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_payroll_summary_pdf_export(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.payroll-summary.pdf', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_attendance_report_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Reports/Attendance')
        );
    }

    public function test_job_costing_report_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.job-costing', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Reports/JobCosting')
        );
    }

    public function test_geofence_activity_csv_export(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.geofence-activity.csv', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Admin/ReportTest.php`
Expected: FAIL.

- [ ] **Step 4: Add routes**

Append to `routes/web.php` inside the admin group:

```php
Route::prefix('reports')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/payroll-summary', [ReportController::class, 'payrollSummary'])->name('admin.reports.payroll-summary');
    Route::get('/payroll-summary/csv', [ReportController::class, 'payrollSummaryCsv'])->name('admin.reports.payroll-summary.csv');
    Route::get('/payroll-summary/pdf', [ReportController::class, 'payrollSummaryPdf'])->name('admin.reports.payroll-summary.pdf');
    Route::get('/attendance', [ReportController::class, 'attendance'])->name('admin.reports.attendance');
    Route::get('/attendance/csv', [ReportController::class, 'attendanceCsv'])->name('admin.reports.attendance.csv');
    Route::get('/attendance/pdf', [ReportController::class, 'attendancePdf'])->name('admin.reports.attendance.pdf');
    Route::get('/overtime', [ReportController::class, 'overtime'])->name('admin.reports.overtime');
    Route::get('/overtime/csv', [ReportController::class, 'overtimeCsv'])->name('admin.reports.overtime.csv');
    Route::get('/job-costing', [ReportController::class, 'jobCosting'])->name('admin.reports.job-costing');
    Route::get('/job-costing/csv', [ReportController::class, 'jobCostingCsv'])->name('admin.reports.job-costing.csv');
    Route::get('/job-costing/pdf', [ReportController::class, 'jobCostingPdf'])->name('admin.reports.job-costing.pdf');
    Route::get('/team-utilization', [ReportController::class, 'teamUtilization'])->name('admin.reports.team-utilization');
    Route::get('/team-utilization/csv', [ReportController::class, 'teamUtilizationCsv'])->name('admin.reports.team-utilization.csv');
    Route::get('/transfer-history', [ReportController::class, 'transferHistory'])->name('admin.reports.transfer-history');
    Route::get('/transfer-history/csv', [ReportController::class, 'transferHistoryCsv'])->name('admin.reports.transfer-history.csv');
    Route::get('/compliance-audit', [ReportController::class, 'complianceAudit'])->name('admin.reports.compliance-audit');
    Route::get('/compliance-audit/csv', [ReportController::class, 'complianceAuditCsv'])->name('admin.reports.compliance-audit.csv');
    Route::get('/geofence-activity', [ReportController::class, 'geofenceActivity'])->name('admin.reports.geofence-activity');
    Route::get('/geofence-activity/csv', [ReportController::class, 'geofenceActivityCsv'])->name('admin.reports.geofence-activity.csv');
});
```

- [ ] **Step 5: Create ReportController**

```php
// app/Http/Controllers/Admin/ReportController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Job;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\TransferRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports/Index');
    }

    // ─── Payroll Summary ────────────────────────────

    public function payrollSummary(Request $request)
    {
        $data = $this->getPayrollData($request);

        return Inertia::render('Reports/PayrollSummary', [
            'reportData' => $data,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function payrollSummaryCsv(Request $request): StreamedResponse
    {
        $data = $this->getPayrollData($request);
        return $this->streamCsv('payroll-summary.csv', [
            'Employee', 'Regular Hours', 'Overtime Hours', 'Total Hours', 'Hourly Rate', 'Regular Pay', 'Overtime Pay', 'Total Pay',
        ], $data);
    }

    public function payrollSummaryPdf(Request $request)
    {
        $data = $this->getPayrollData($request);
        $pdf = Pdf::loadView('pdf.payroll-summary', [
            'data' => $data,
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
            'tenantName' => app('current_tenant')->name,
        ]);
        return $pdf->download('payroll-summary.pdf');
    }

    private function getPayrollData(Request $request): array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return Employee::where('status', 'active')
            ->get()
            ->map(function ($emp) use ($request) {
                $entries = TimeEntry::where('employee_id', $emp->id)
                    ->where('status', 'approved')
                    ->whereBetween('clock_in', [$request->start_date, $request->end_date . ' 23:59:59'])
                    ->get();

                $totalHours = $entries->sum('total_hours');
                $overtimeHours = $entries->sum('overtime_hours');
                $regularHours = $totalHours - $overtimeHours;
                $rate = (float) $emp->hourly_rate;

                return [
                    'Employee' => $emp->first_name . ' ' . $emp->last_name,
                    'Regular Hours' => round($regularHours, 2),
                    'Overtime Hours' => round($overtimeHours, 2),
                    'Total Hours' => round($totalHours, 2),
                    'Hourly Rate' => $rate,
                    'Regular Pay' => round($regularHours * $rate, 2),
                    'Overtime Pay' => round($overtimeHours * $rate * 1.5, 2),
                    'Total Pay' => round(($regularHours * $rate) + ($overtimeHours * $rate * 1.5), 2),
                ];
            })
            ->filter(fn ($row) => $row['Total Hours'] > 0)
            ->values()
            ->toArray();
    }

    // ─── Attendance ─────────────────────────────────

    public function attendance(Request $request)
    {
        $data = $this->getAttendanceData($request);
        return Inertia::render('Reports/Attendance', [
            'reportData' => $data,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function attendanceCsv(Request $request): StreamedResponse
    {
        $data = $this->getAttendanceData($request);
        return $this->streamCsv('attendance.csv', [
            'Employee', 'Date', 'Clock In', 'Clock Out', 'Total Hours', 'Method', 'Status',
        ], $data);
    }

    public function attendancePdf(Request $request)
    {
        $data = $this->getAttendanceData($request);
        $pdf = Pdf::loadView('pdf.attendance', [
            'data' => $data,
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
            'tenantName' => app('current_tenant')->name,
        ]);
        return $pdf->download('attendance.pdf');
    }

    private function getAttendanceData(Request $request): array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return TimeEntry::with('employee:id,first_name,last_name')
            ->whereBetween('clock_in', [$request->start_date, $request->end_date . ' 23:59:59'])
            ->orderBy('clock_in')
            ->get()
            ->map(fn ($e) => [
                'Employee' => $e->employee?->first_name . ' ' . $e->employee?->last_name,
                'Date' => $e->clock_in->format('Y-m-d'),
                'Clock In' => $e->clock_in->format('H:i'),
                'Clock Out' => $e->clock_out?->format('H:i') ?? 'Active',
                'Total Hours' => round($e->total_hours ?? 0, 2),
                'Method' => ucfirst($e->clock_method),
                'Status' => ucfirst($e->status),
            ])
            ->toArray();
    }

    // ─── Overtime ───────────────────────────────────

    public function overtime(Request $request)
    {
        $data = $this->getOvertimeData($request);
        return Inertia::render('Reports/Overtime', [
            'reportData' => $data,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function overtimeCsv(Request $request): StreamedResponse
    {
        $data = $this->getOvertimeData($request);
        return $this->streamCsv('overtime.csv', [
            'Employee', 'Total Hours', 'Overtime Hours', 'OT Percentage',
        ], $data);
    }

    private function getOvertimeData(Request $request): array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return Employee::where('status', 'active')
            ->get()
            ->map(function ($emp) use ($request) {
                $entries = TimeEntry::where('employee_id', $emp->id)
                    ->whereBetween('clock_in', [$request->start_date, $request->end_date . ' 23:59:59'])
                    ->get();

                $total = $entries->sum('total_hours');
                $ot = $entries->sum('overtime_hours');

                return [
                    'Employee' => $emp->first_name . ' ' . $emp->last_name,
                    'Total Hours' => round($total, 2),
                    'Overtime Hours' => round($ot, 2),
                    'OT Percentage' => $total > 0 ? round(($ot / $total) * 100, 1) : 0,
                ];
            })
            ->filter(fn ($row) => $row['Overtime Hours'] > 0)
            ->values()
            ->toArray();
    }

    // ─── Job Costing ────────────────────────────────

    public function jobCosting(Request $request)
    {
        $data = $this->getJobCostingData($request);
        return Inertia::render('Reports/JobCosting', [
            'reportData' => $data,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function jobCostingCsv(Request $request): StreamedResponse
    {
        $data = $this->getJobCostingData($request);
        return $this->streamCsv('job-costing.csv', [
            'Job', 'Client', 'Budget Hours', 'Actual Hours', 'Budget Used %', 'Labor Cost',
        ], $data);
    }

    public function jobCostingPdf(Request $request)
    {
        $data = $this->getJobCostingData($request);
        $pdf = Pdf::loadView('pdf.job-costing', [
            'data' => $data,
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
            'tenantName' => app('current_tenant')->name,
        ]);
        return $pdf->download('job-costing.pdf');
    }

    private function getJobCostingData(Request $request): array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return Job::all()->map(function ($job) use ($request) {
            $entries = TimeEntry::where('job_id', $job->id)
                ->whereBetween('clock_in', [$request->start_date, $request->end_date . ' 23:59:59'])
                ->with('employee:id,hourly_rate')
                ->get();

            $actualHours = $entries->sum('total_hours');
            $laborCost = $entries->sum(fn ($e) => ($e->total_hours ?? 0) * ($e->employee?->hourly_rate ?? $job->hourly_rate));

            return [
                'Job' => $job->name,
                'Client' => $job->client_name,
                'Budget Hours' => (float) $job->budget_hours,
                'Actual Hours' => round($actualHours, 2),
                'Budget Used %' => $job->budget_hours > 0 ? round(($actualHours / $job->budget_hours) * 100, 1) : 0,
                'Labor Cost' => round($laborCost, 2),
            ];
        })->toArray();
    }

    // ─── Team Utilization ───────────────────────────

    public function teamUtilization(Request $request)
    {
        $data = $this->getTeamUtilizationData($request);
        return Inertia::render('Reports/TeamUtilization', [
            'reportData' => $data,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function teamUtilizationCsv(Request $request): StreamedResponse
    {
        $data = $this->getTeamUtilizationData($request);
        return $this->streamCsv('team-utilization.csv', [
            'Team', 'Members', 'Total Hours', 'Avg Hours/Member',
        ], $data);
    }

    private function getTeamUtilizationData(Request $request): array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return Team::where('status', 'active')
            ->withCount('employees')
            ->get()
            ->map(function ($team) use ($request) {
                $hours = TimeEntry::where('team_id', $team->id)
                    ->whereBetween('clock_in', [$request->start_date, $request->end_date . ' 23:59:59'])
                    ->sum('total_hours');

                return [
                    'Team' => $team->name,
                    'Members' => $team->employees_count,
                    'Total Hours' => round($hours, 2),
                    'Avg Hours/Member' => $team->employees_count > 0 ? round($hours / $team->employees_count, 2) : 0,
                ];
            })
            ->toArray();
    }

    // ─── Transfer History ───────────────────────────

    public function transferHistory(Request $request)
    {
        $data = $this->getTransferHistoryData($request);
        return Inertia::render('Reports/TransferHistory', [
            'reportData' => $data,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function transferHistoryCsv(Request $request): StreamedResponse
    {
        $data = $this->getTransferHistoryData($request);
        return $this->streamCsv('transfer-history.csv', [
            'Employee', 'From Team', 'To Team', 'Reason', 'Type', 'Effective Date', 'Status',
        ], $data);
    }

    private function getTransferHistoryData(Request $request): array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return TransferRecord::with(['employee:id,first_name,last_name', 'fromTeam:id,name', 'toTeam:id,name'])
            ->whereBetween('effective_date', [$request->start_date, $request->end_date])
            ->orderBy('effective_date')
            ->get()
            ->map(fn ($t) => [
                'Employee' => $t->employee?->first_name . ' ' . $t->employee?->last_name,
                'From Team' => $t->fromTeam?->name ?? '-',
                'To Team' => $t->toTeam?->name ?? '-',
                'Reason' => str_replace('_', ' ', $t->reason_code),
                'Type' => ucfirst(strtolower($t->transfer_type)),
                'Effective Date' => $t->effective_date->format('Y-m-d'),
                'Status' => ucfirst($t->status),
            ])
            ->toArray();
    }

    // ─── Compliance Audit ───────────────────────────

    public function complianceAudit(Request $request)
    {
        $data = $this->getComplianceAuditData($request);
        return Inertia::render('Reports/ComplianceAudit', [
            'reportData' => $data,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function complianceAuditCsv(Request $request): StreamedResponse
    {
        $data = $this->getComplianceAuditData($request);
        return $this->streamCsv('compliance-audit.csv', [
            'Employee', 'Date', 'Clock In', 'Clock Out', 'Hours', 'Overtime', 'Break Minutes', 'Method',
        ], $data);
    }

    private function getComplianceAuditData(Request $request): array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return TimeEntry::with(['employee:id,first_name,last_name', 'breaks'])
            ->whereBetween('clock_in', [$request->start_date, $request->end_date . ' 23:59:59'])
            ->orderBy('clock_in')
            ->get()
            ->map(fn ($e) => [
                'Employee' => $e->employee?->first_name . ' ' . $e->employee?->last_name,
                'Date' => $e->clock_in->format('Y-m-d'),
                'Clock In' => $e->clock_in->format('H:i:s'),
                'Clock Out' => $e->clock_out?->format('H:i:s') ?? 'Missing',
                'Hours' => round($e->total_hours ?? 0, 2),
                'Overtime' => round($e->overtime_hours ?? 0, 2),
                'Break Minutes' => $e->breaks->sum('duration_minutes'),
                'Method' => ucfirst($e->clock_method),
            ])
            ->toArray();
    }

    // ─── Geofence Activity ──────────────────────────

    public function geofenceActivity(Request $request)
    {
        $data = $this->getGeofenceActivityData($request);
        return Inertia::render('Reports/GeofenceActivity', [
            'reportData' => $data,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function geofenceActivityCsv(Request $request): StreamedResponse
    {
        $data = $this->getGeofenceActivityData($request);
        return $this->streamCsv('geofence-activity.csv', [
            'Employee', 'Job', 'Clock In', 'Clock Out', 'In Lat', 'In Lng', 'Out Lat', 'Out Lng', 'Method',
        ], $data);
    }

    private function getGeofenceActivityData(Request $request): array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return TimeEntry::with(['employee:id,first_name,last_name', 'job:id,name'])
            ->whereBetween('clock_in', [$request->start_date, $request->end_date . ' 23:59:59'])
            ->where('clock_method', 'geofence')
            ->orderBy('clock_in')
            ->get()
            ->map(fn ($e) => [
                'Employee' => $e->employee?->first_name . ' ' . $e->employee?->last_name,
                'Job' => $e->job?->name ?? '-',
                'Clock In' => $e->clock_in->format('Y-m-d H:i:s'),
                'Clock Out' => $e->clock_out?->format('Y-m-d H:i:s') ?? 'Active',
                'In Lat' => $e->clock_in_lat,
                'In Lng' => $e->clock_in_lng,
                'Out Lat' => $e->clock_out_lat,
                'Out Lng' => $e->clock_out_lng,
                'Method' => 'Geofence',
            ])
            ->toArray();
    }

    // ─── CSV Streaming Helper ───────────────────────

    private function streamCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
```

- [ ] **Step 6: Create ReportDateFilter.vue component**

```vue
<!-- resources/js/Components/ReportDateFilter.vue -->
<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Button from '@/Components/Button.vue'

const props = defineProps({
    routeName: String,
    filters: { type: Object, default: () => ({}) },
    csvRoute: { type: String, default: null },
    pdfRoute: { type: String, default: null },
})

const startDate = ref(props.filters.start_date || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const endDate = ref(props.filters.end_date || new Date().toISOString().split('T')[0])

function applyFilter() {
    router.get(route(props.routeName), {
        start_date: startDate.value,
        end_date: endDate.value,
    }, { preserveState: true })
}

function exportCsv() {
    window.location.href = route(props.csvRoute, {
        start_date: startDate.value,
        end_date: endDate.value,
    })
}

function exportPdf() {
    window.location.href = route(props.pdfRoute, {
        start_date: startDate.value,
        end_date: endDate.value,
    })
}
</script>

<template>
    <div class="flex flex-wrap items-end gap-4 rounded-lg bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-gray-500">Start Date</label>
            <input v-model="startDate" type="date"
                class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">End Date</label>
            <input v-model="endDate" type="date"
                class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <Button size="sm" @click="applyFilter">Apply</Button>
        <Button v-if="csvRoute" size="sm" variant="secondary" @click="exportCsv">CSV</Button>
        <Button v-if="pdfRoute" size="sm" variant="secondary" @click="exportPdf">PDF</Button>
    </div>
</template>
```

- [ ] **Step 7: Create ChartWrapper.vue component**

```vue
<!-- resources/js/Components/ChartWrapper.vue -->
<script setup>
import { computed } from 'vue'
import { Bar, Pie, Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    Title, Tooltip, Legend,
    BarElement, CategoryScale, LinearScale,
    ArcElement, PointElement, LineElement,
} from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement, PointElement, LineElement)

const props = defineProps({
    type: { type: String, default: 'bar' }, // bar, pie, line
    labels: { type: Array, required: true },
    datasets: { type: Array, required: true },
    height: { type: Number, default: 300 },
})

const chartData = computed(() => ({
    labels: props.labels,
    datasets: props.datasets,
}))

const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
    },
}

const componentMap = { bar: Bar, pie: Pie, line: Line }
const ChartComponent = computed(() => componentMap[props.type] || Bar)
</script>

<template>
    <div :style="{ height: height + 'px' }">
        <component :is="ChartComponent" :data="chartData" :options="options" />
    </div>
</template>
```

- [ ] **Step 8: Create Reports/Index.vue**

```vue
<!-- resources/js/Pages/Reports/Index.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const reports = [
    { name: 'Payroll Summary', description: 'Hours, overtime, PTO, and gross wages per employee', route: 'admin.reports.payroll-summary', formats: ['CSV', 'PDF'] },
    { name: 'Attendance', description: 'Daily clock-in/out times, lates, and absences', route: 'admin.reports.attendance', formats: ['CSV', 'PDF'] },
    { name: 'Overtime', description: 'Employees approaching or exceeding weekly overtime', route: 'admin.reports.overtime', formats: ['CSV'] },
    { name: 'Job Costing', description: 'Hours and labor cost per job/client', route: 'admin.reports.job-costing', formats: ['CSV', 'PDF'] },
    { name: 'Team Utilization', description: 'Hours per team with capacity analysis', route: 'admin.reports.team-utilization', formats: ['CSV'] },
    { name: 'Transfer History', description: 'All employee transfers with reasons and dates', route: 'admin.reports.transfer-history', formats: ['CSV'] },
    { name: 'Compliance Audit', description: 'FLSA-required records for a date range', route: 'admin.reports.compliance-audit', formats: ['CSV'] },
    { name: 'Geofence Activity', description: 'Clock events with GPS coordinates and geofence data', route: 'admin.reports.geofence-activity', formats: ['CSV'] },
]
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Reports</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <a
                        v-for="report in reports"
                        :key="report.name"
                        :href="route(report.route, { start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0], end_date: new Date().toISOString().split('T')[0] })"
                        class="group rounded-lg bg-white p-5 shadow transition hover:shadow-md hover:ring-1 hover:ring-indigo-200"
                    >
                        <h3 class="text-sm font-semibold text-gray-900 group-hover:text-indigo-600">{{ report.name }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ report.description }}</p>
                        <div class="mt-3 flex gap-1">
                            <span
                                v-for="fmt in report.formats"
                                :key="fmt"
                                class="inline-flex rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                            >{{ fmt }}</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 9: Create Reports/PayrollSummary.vue**

```vue
<!-- resources/js/Pages/Reports/PayrollSummary.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ReportDateFilter from '@/Components/ReportDateFilter.vue'
import ChartWrapper from '@/Components/ChartWrapper.vue'
import { computed } from 'vue'

const props = defineProps({
    reportData: Array,
    filters: Object,
})

const chartLabels = computed(() => props.reportData.map(r => r.Employee))
const chartDatasets = computed(() => [
    { label: 'Regular Pay', data: props.reportData.map(r => r['Regular Pay']), backgroundColor: '#4f46e5' },
    { label: 'Overtime Pay', data: props.reportData.map(r => r['Overtime Pay']), backgroundColor: '#ef4444' },
])

const totals = computed(() => ({
    regularHours: props.reportData.reduce((sum, r) => sum + r['Regular Hours'], 0).toFixed(2),
    overtimeHours: props.reportData.reduce((sum, r) => sum + r['Overtime Hours'], 0).toFixed(2),
    totalPay: props.reportData.reduce((sum, r) => sum + r['Total Pay'], 0).toFixed(2),
}))
</script>

<template>
    <Head title="Payroll Summary Report" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Payroll Summary</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <ReportDateFilter
                    route-name="admin.reports.payroll-summary"
                    :filters="filters"
                    csv-route="admin.reports.payroll-summary.csv"
                    pdf-route="admin.reports.payroll-summary.pdf"
                />

                <!-- Summary cards -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">Regular Hours</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ totals.regularHours }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">Overtime Hours</p>
                        <p class="mt-1 text-2xl font-bold text-red-600">{{ totals.overtimeHours }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">Total Pay</p>
                        <p class="mt-1 text-2xl font-bold text-green-600">${{ totals.totalPay }}</p>
                    </div>
                </div>

                <!-- Chart -->
                <div v-if="reportData.length" class="rounded-lg bg-white p-4 shadow">
                    <ChartWrapper type="bar" :labels="chartLabels" :datasets="chartDatasets" :height="300" />
                </div>

                <!-- Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Reg Hrs</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">OT Hrs</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total Hrs</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Rate</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total Pay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="row in reportData" :key="row.Employee" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ row.Employee }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600">{{ row['Regular Hours'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600">{{ row['Overtime Hours'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600">{{ row['Total Hours'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600">${{ row['Hourly Rate'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-gray-900">${{ row['Total Pay'] }}</td>
                            </tr>
                            <tr v-if="!reportData.length">
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">No payroll data for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 10: Create remaining report pages (Attendance, Overtime, JobCosting, TeamUtilization, TransferHistory, ComplianceAudit, GeofenceActivity)**

Each follows the same pattern as PayrollSummary. Below is each file.

**Reports/Attendance.vue:**

```vue
<!-- resources/js/Pages/Reports/Attendance.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ReportDateFilter from '@/Components/ReportDateFilter.vue'

const props = defineProps({ reportData: Array, filters: Object })
</script>

<template>
    <Head title="Attendance Report" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Attendance Report</h2>
        </template>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <ReportDateFilter route-name="admin.reports.attendance" :filters="filters"
                    csv-route="admin.reports.attendance.csv" pdf-route="admin.reports.attendance.pdf" />
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-for="h in ['Employee','Date','Clock In','Clock Out','Hours','Method','Status']" :key="h"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="(row, i) in reportData" :key="i" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ row.Employee }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row.Date }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Clock In'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Clock Out'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Total Hours'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ row.Method }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ row.Status }}</td>
                            </tr>
                            <tr v-if="!reportData.length">
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">No attendance data for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

**Reports/Overtime.vue:**

```vue
<!-- resources/js/Pages/Reports/Overtime.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ReportDateFilter from '@/Components/ReportDateFilter.vue'
import ChartWrapper from '@/Components/ChartWrapper.vue'
import { computed } from 'vue'

const props = defineProps({ reportData: Array, filters: Object })

const chartLabels = computed(() => props.reportData.map(r => r.Employee))
const chartDatasets = computed(() => [
    { label: 'Overtime Hours', data: props.reportData.map(r => r['Overtime Hours']), backgroundColor: '#ef4444' },
])
</script>

<template>
    <Head title="Overtime Report" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Overtime Report</h2>
        </template>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <ReportDateFilter route-name="admin.reports.overtime" :filters="filters"
                    csv-route="admin.reports.overtime.csv" />
                <div v-if="reportData.length" class="rounded-lg bg-white p-4 shadow">
                    <ChartWrapper type="bar" :labels="chartLabels" :datasets="chartDatasets" :height="300" />
                </div>
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-for="h in ['Employee','Total Hours','Overtime Hours','OT %']" :key="h"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="row in reportData" :key="row.Employee" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ row.Employee }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Total Hours'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-red-600 font-medium">{{ row['Overtime Hours'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['OT Percentage'] }}%</td>
                            </tr>
                            <tr v-if="!reportData.length">
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No overtime recorded for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

**Reports/JobCosting.vue:**

```vue
<!-- resources/js/Pages/Reports/JobCosting.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ReportDateFilter from '@/Components/ReportDateFilter.vue'
import ChartWrapper from '@/Components/ChartWrapper.vue'
import { computed } from 'vue'

const props = defineProps({ reportData: Array, filters: Object })

const chartLabels = computed(() => props.reportData.map(r => r.Job))
const chartDatasets = computed(() => [
    { label: 'Budget Hours', data: props.reportData.map(r => r['Budget Hours']), backgroundColor: '#94a3b8' },
    { label: 'Actual Hours', data: props.reportData.map(r => r['Actual Hours']), backgroundColor: '#4f46e5' },
])
</script>

<template>
    <Head title="Job Costing Report" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Job Costing Report</h2>
        </template>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <ReportDateFilter route-name="admin.reports.job-costing" :filters="filters"
                    csv-route="admin.reports.job-costing.csv" pdf-route="admin.reports.job-costing.pdf" />
                <div v-if="reportData.length" class="rounded-lg bg-white p-4 shadow">
                    <ChartWrapper type="bar" :labels="chartLabels" :datasets="chartDatasets" :height="300" />
                </div>
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-for="h in ['Job','Client','Budget Hrs','Actual Hrs','Used %','Labor Cost']" :key="h"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="row in reportData" :key="row.Job" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ row.Job }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row.Client }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Budget Hours'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Actual Hours'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm" :class="row['Budget Used %'] > 90 ? 'text-red-600 font-medium' : 'text-gray-600'">
                                    {{ row['Budget Used %'] }}%
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">${{ row['Labor Cost'].toFixed(2) }}</td>
                            </tr>
                            <tr v-if="!reportData.length">
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">No job costing data for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

**Reports/TeamUtilization.vue:**

```vue
<!-- resources/js/Pages/Reports/TeamUtilization.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ReportDateFilter from '@/Components/ReportDateFilter.vue'

const props = defineProps({ reportData: Array, filters: Object })
</script>

<template>
    <Head title="Team Utilization Report" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Team Utilization</h2>
        </template>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <ReportDateFilter route-name="admin.reports.team-utilization" :filters="filters"
                    csv-route="admin.reports.team-utilization.csv" />
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-for="h in ['Team','Members','Total Hours','Avg Hours/Member']" :key="h"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="row in reportData" :key="row.Team" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ row.Team }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row.Members }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Total Hours'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Avg Hours/Member'] }}</td>
                            </tr>
                            <tr v-if="!reportData.length">
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No team data for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

**Reports/TransferHistory.vue:**

```vue
<!-- resources/js/Pages/Reports/TransferHistory.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ReportDateFilter from '@/Components/ReportDateFilter.vue'

const props = defineProps({ reportData: Array, filters: Object })
</script>

<template>
    <Head title="Transfer History Report" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Transfer History</h2>
        </template>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <ReportDateFilter route-name="admin.reports.transfer-history" :filters="filters"
                    csv-route="admin.reports.transfer-history.csv" />
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-for="h in ['Employee','From Team','To Team','Reason','Type','Date','Status']" :key="h"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="(row, i) in reportData" :key="i" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ row.Employee }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['From Team'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['To Team'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 capitalize">{{ row.Reason }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ row.Type }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Effective Date'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ row.Status }}</td>
                            </tr>
                            <tr v-if="!reportData.length">
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">No transfers for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

**Reports/ComplianceAudit.vue:**

```vue
<!-- resources/js/Pages/Reports/ComplianceAudit.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ReportDateFilter from '@/Components/ReportDateFilter.vue'

const props = defineProps({ reportData: Array, filters: Object })
</script>

<template>
    <Head title="Compliance Audit Report" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Compliance Audit</h2>
        </template>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <ReportDateFilter route-name="admin.reports.compliance-audit" :filters="filters"
                    csv-route="admin.reports.compliance-audit.csv" />
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-for="h in ['Employee','Date','Clock In','Clock Out','Hours','Overtime','Break Min','Method']" :key="h"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="(row, i) in reportData" :key="i" class="hover:bg-gray-50"
                                :class="{ 'bg-yellow-50': row['Clock Out'] === 'Missing' }">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ row.Employee }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row.Date }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Clock In'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm" :class="row['Clock Out'] === 'Missing' ? 'text-red-600 font-medium' : 'text-gray-600'">
                                    {{ row['Clock Out'] }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row.Hours }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row.Overtime }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Break Minutes'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ row.Method }}</td>
                            </tr>
                            <tr v-if="!reportData.length">
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500">No compliance data for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

**Reports/GeofenceActivity.vue:**

```vue
<!-- resources/js/Pages/Reports/GeofenceActivity.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ReportDateFilter from '@/Components/ReportDateFilter.vue'

const props = defineProps({ reportData: Array, filters: Object })
</script>

<template>
    <Head title="Geofence Activity Report" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Geofence Activity</h2>
        </template>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <ReportDateFilter route-name="admin.reports.geofence-activity" :filters="filters"
                    csv-route="admin.reports.geofence-activity.csv" />
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-for="h in ['Employee','Job','Clock In','Clock Out','In Lat','In Lng','Out Lat','Out Lng']" :key="h"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="(row, i) in reportData" :key="i" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ row.Employee }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row.Job }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Clock In'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ row['Clock Out'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-400">{{ row['In Lat'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-400">{{ row['In Lng'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-400">{{ row['Out Lat'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-400">{{ row['Out Lng'] }}</td>
                            </tr>
                            <tr v-if="!reportData.length">
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500">No geofence activity for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 11: Create PDF Blade templates**

**resources/views/pdf/payroll-summary.blade.php:**

```blade
{{-- resources/views/pdf/payroll-summary.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll Summary</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; font-size: 11px; text-transform: uppercase; }
        .right { text-align: right; }
        .total-row { font-weight: bold; background: #fafafa; }
    </style>
</head>
<body>
    <h1>{{ $tenantName }} - Payroll Summary</h1>
    <p class="meta">Period: {{ $startDate }} to {{ $endDate }}</p>

    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th class="right">Reg Hours</th>
                <th class="right">OT Hours</th>
                <th class="right">Total Hours</th>
                <th class="right">Rate</th>
                <th class="right">Reg Pay</th>
                <th class="right">OT Pay</th>
                <th class="right">Total Pay</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr>
                <td>{{ $row['Employee'] }}</td>
                <td class="right">{{ $row['Regular Hours'] }}</td>
                <td class="right">{{ $row['Overtime Hours'] }}</td>
                <td class="right">{{ $row['Total Hours'] }}</td>
                <td class="right">${{ number_format($row['Hourly Rate'], 2) }}</td>
                <td class="right">${{ number_format($row['Regular Pay'], 2) }}</td>
                <td class="right">${{ number_format($row['Overtime Pay'], 2) }}</td>
                <td class="right">${{ number_format($row['Total Pay'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td>Totals</td>
                <td class="right">{{ collect($data)->sum('Regular Hours') }}</td>
                <td class="right">{{ collect($data)->sum('Overtime Hours') }}</td>
                <td class="right">{{ collect($data)->sum('Total Hours') }}</td>
                <td></td>
                <td class="right">${{ number_format(collect($data)->sum('Regular Pay'), 2) }}</td>
                <td class="right">${{ number_format(collect($data)->sum('Overtime Pay'), 2) }}</td>
                <td class="right">${{ number_format(collect($data)->sum('Total Pay'), 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
```

**resources/views/pdf/attendance.blade.php:**

```blade
{{-- resources/views/pdf/attendance.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; }
        th { background: #f5f5f5; font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>{{ $tenantName }} - Attendance Report</h1>
    <p class="meta">Period: {{ $startDate }} to {{ $endDate }}</p>

    <table>
        <thead>
            <tr>
                <th>Employee</th><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Method</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr>
                <td>{{ $row['Employee'] }}</td>
                <td>{{ $row['Date'] }}</td>
                <td>{{ $row['Clock In'] }}</td>
                <td>{{ $row['Clock Out'] }}</td>
                <td>{{ $row['Total Hours'] }}</td>
                <td>{{ $row['Method'] }}</td>
                <td>{{ $row['Status'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
```

**resources/views/pdf/job-costing.blade.php:**

```blade
{{-- resources/views/pdf/job-costing.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Job Costing Report</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; font-size: 11px; text-transform: uppercase; }
        .right { text-align: right; }
        .over-budget { color: #dc2626; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $tenantName }} - Job Costing Report</h1>
    <p class="meta">Period: {{ $startDate }} to {{ $endDate }}</p>

    <table>
        <thead>
            <tr>
                <th>Job</th><th>Client</th><th class="right">Budget Hrs</th><th class="right">Actual Hrs</th><th class="right">Used %</th><th class="right">Labor Cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr>
                <td>{{ $row['Job'] }}</td>
                <td>{{ $row['Client'] }}</td>
                <td class="right">{{ $row['Budget Hours'] }}</td>
                <td class="right">{{ $row['Actual Hours'] }}</td>
                <td class="right {{ $row['Budget Used %'] > 90 ? 'over-budget' : '' }}">{{ $row['Budget Used %'] }}%</td>
                <td class="right">${{ number_format($row['Labor Cost'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
```

- [ ] **Step 12: Run tests and verify**

Run: `docker compose exec app php artisan test tests/Feature/Admin/ReportTest.php`
Expected: All tests PASS.

- [ ] **Step 13: Commit**

```bash
git add app/Http/Controllers/Admin/ReportController.php resources/js/Pages/Reports/ resources/js/Components/ReportDateFilter.vue resources/js/Components/ChartWrapper.vue resources/views/pdf/ tests/Feature/Admin/ReportTest.php routes/web.php
git commit -m "feat: add reports module with 8 report types, CSV/PDF export, and Chart.js visualizations"
```

---

## Task 14: PTO Management

**Files:**
- Create: `app/Http/Controllers/Admin/PtoController.php`
- Create: `resources/js/Pages/Pto/Index.vue`
- Create: `resources/js/Components/PtoCalendar.vue`
- Create: `tests/Feature/Admin/PtoManagementTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the feature test**

```php
// tests/Feature/Admin/PtoManagementTest.php
<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\PtoRequest;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PtoManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $team = Team::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Team',
            'status' => 'active',
        ]);

        $this->employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'current_team_id' => $team->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@test.com',
            'role' => 'employee',
            'hourly_rate' => 22.00,
            'status' => 'active',
        ]);

        app()->instance('current_tenant', $this->tenant);
    }

    public function test_pto_index_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.pto.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Pto/Index')
        );
    }

    public function test_can_approve_pto_request(): void
    {
        $pto = PtoRequest::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee->id,
            'type' => 'vacation',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-03',
            'notes' => 'Family vacation',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.pto.approve', $pto), [
                'admin_notes' => 'Approved. Enjoy!',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pto_requests', [
            'id' => $pto->id,
            'status' => 'approved',
        ]);
    }

    public function test_can_deny_pto_request(): void
    {
        $pto = PtoRequest::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee->id,
            'type' => 'vacation',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-15',
            'notes' => 'Need time off',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.pto.deny', $pto), [
                'admin_notes' => 'Too many people off that week.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pto_requests', [
            'id' => $pto->id,
            'status' => 'denied',
        ]);
    }

    public function test_pto_calendar_data_is_provided(): void
    {
        PtoRequest::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee->id,
            'type' => 'sick',
            'start_date' => '2026-03-20',
            'end_date' => '2026-03-20',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.pto.index'));

        $response->assertInertia(fn ($page) =>
            $page->has('calendarEvents')
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Admin/PtoManagementTest.php`
Expected: FAIL.

- [ ] **Step 3: Add routes**

Append to `routes/web.php` inside the admin group:

```php
Route::get('pto', [PtoController::class, 'index'])->name('admin.pto.index');
Route::patch('pto/{ptoRequest}/approve', [PtoController::class, 'approve'])->name('admin.pto.approve');
Route::patch('pto/{ptoRequest}/deny', [PtoController::class, 'deny'])->name('admin.pto.deny');
```

- [ ] **Step 4: Create PtoController**

```php
// app/Http/Controllers/Admin/PtoController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PtoRequest;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PtoController extends Controller
{
    public function index(Request $request)
    {
        $requests = PtoRequest::query()
            ->with('employee:id,first_name,last_name,current_team_id')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', 'pending'))
            ->when($request->team_id, fn ($q, $t) => $q->whereHas('employee', fn ($eq) => $eq->where('current_team_id', $t)))
            ->orderBy('start_date')
            ->paginate(20)
            ->withQueryString();

        // Calendar events: all approved PTO for current month +/- 1 month
        $calendarStart = now()->subMonth()->startOfMonth();
        $calendarEnd = now()->addMonth()->endOfMonth();

        $calendarEvents = PtoRequest::where('status', 'approved')
            ->whereBetween('start_date', [$calendarStart, $calendarEnd])
            ->with('employee:id,first_name,last_name')
            ->get()
            ->map(fn ($pto) => [
                'id' => $pto->id,
                'title' => $pto->employee?->first_name . ' ' . $pto->employee?->last_name . ' - ' . ucfirst($pto->type),
                'start' => $pto->start_date->format('Y-m-d'),
                'end' => $pto->end_date->addDay()->format('Y-m-d'), // end is exclusive in calendar
                'type' => $pto->type,
            ]);

        return Inertia::render('Pto/Index', [
            'requests' => $requests,
            'calendarEvents' => $calendarEvents,
            'teams' => Team::where('status', 'active')->get(['id', 'name']),
            'filters' => $request->only(['status', 'team_id']),
        ]);
    }

    public function approve(Request $request, PtoRequest $ptoRequest)
    {
        $ptoRequest->update([
            'status' => 'approved',
            'admin_notes' => $request->input('admin_notes'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'PTO request approved.');
    }

    public function deny(Request $request, PtoRequest $ptoRequest)
    {
        $ptoRequest->update([
            'status' => 'denied',
            'admin_notes' => $request->input('admin_notes'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'PTO request denied.');
    }
}
```

- [ ] **Step 5: Create PtoCalendar.vue component**

```vue
<!-- resources/js/Components/PtoCalendar.vue -->
<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    events: { type: Array, default: () => [] },
})

const currentDate = ref(new Date())
const currentMonth = computed(() => currentDate.value.getMonth())
const currentYear = computed(() => currentDate.value.getFullYear())

const monthName = computed(() =>
    currentDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
)

const daysInMonth = computed(() => new Date(currentYear.value, currentMonth.value + 1, 0).getDate())
const firstDayOfWeek = computed(() => new Date(currentYear.value, currentMonth.value, 1).getDay())

const calendarDays = computed(() => {
    const days = []
    // Padding for days before the 1st
    for (let i = 0; i < firstDayOfWeek.value; i++) {
        days.push({ day: null, events: [] })
    }
    for (let d = 1; d <= daysInMonth.value; d++) {
        const dateStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
        const dayEvents = props.events.filter(e => e.start <= dateStr && e.end > dateStr)
        days.push({ day: d, date: dateStr, events: dayEvents })
    }
    return days
})

function prevMonth() {
    currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1)
}

function nextMonth() {
    currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1)
}

const typeColors = {
    vacation: 'bg-blue-100 text-blue-700',
    sick: 'bg-red-100 text-red-700',
    personal: 'bg-purple-100 text-purple-700',
    unpaid: 'bg-gray-100 text-gray-700',
}
</script>

<template>
    <div class="rounded-lg bg-white p-4 shadow">
        <div class="mb-4 flex items-center justify-between">
            <button @click="prevMonth" class="rounded p-1 hover:bg-gray-100 text-gray-600">&larr;</button>
            <h3 class="text-sm font-semibold text-gray-900">{{ monthName }}</h3>
            <button @click="nextMonth" class="rounded p-1 hover:bg-gray-100 text-gray-600">&rarr;</button>
        </div>

        <div class="grid grid-cols-7 gap-px bg-gray-200 rounded overflow-hidden text-center text-xs">
            <div v-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day"
                class="bg-gray-50 py-2 font-medium text-gray-500">
                {{ day }}
            </div>
            <div
                v-for="(cell, i) in calendarDays"
                :key="i"
                class="min-h-[70px] bg-white p-1"
                :class="{ 'bg-gray-50': !cell.day }"
            >
                <span v-if="cell.day" class="text-xs text-gray-600">{{ cell.day }}</span>
                <div v-for="ev in cell.events" :key="ev.id"
                    class="mt-0.5 truncate rounded px-1 py-0.5 text-[10px] leading-tight"
                    :class="typeColors[ev.type] || 'bg-gray-100 text-gray-700'"
                    :title="ev.title"
                >
                    {{ ev.title }}
                </div>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 6: Create Pto/Index.vue**

```vue
<!-- resources/js/Pages/Pto/Index.vue -->
<script setup>
import { ref, watch } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Button from '@/Components/Button.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Modal from '@/Components/Modal.vue'
import PtoCalendar from '@/Components/PtoCalendar.vue'

const props = defineProps({
    requests: Object,
    calendarEvents: Array,
    teams: Array,
    filters: Object,
})

const statusFilter = ref(props.filters?.status || 'pending')
const teamFilter = ref(props.filters?.team_id || '')
const showActionModal = ref(false)
const actionType = ref('')
const actionRequest = ref(null)
const adminNotes = ref('')

watch([statusFilter, teamFilter], () => {
    router.get(route('admin.pto.index'), {
        status: statusFilter.value || undefined,
        team_id: teamFilter.value || undefined,
    }, { preserveState: true, replace: true })
})

function openAction(type, request) {
    actionType.value = type
    actionRequest.value = request
    adminNotes.value = ''
    showActionModal.value = true
}

function submitAction() {
    const routeName = actionType.value === 'approve' ? 'admin.pto.approve' : 'admin.pto.deny'
    router.patch(route(routeName, actionRequest.value.id), {
        admin_notes: adminNotes.value,
    }, {
        preserveState: true,
        onFinish: () => {
            showActionModal.value = false
            actionRequest.value = null
        },
    })
}

const statusColors = { pending: 'yellow', approved: 'green', denied: 'red' }
const typeLabels = { vacation: 'Vacation', sick: 'Sick', personal: 'Personal', unpaid: 'Unpaid' }
</script>

<template>
    <Head title="PTO Management" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">PTO Management</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- PTO Requests Table (2/3) -->
                    <div class="lg:col-span-2">
                        <div class="mb-4 flex flex-wrap gap-4">
                            <select v-model="statusFilter"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="denied">Denied</option>
                            </select>
                            <select v-model="teamFilter"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Teams</option>
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                        </div>

                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Dates</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Notes</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="req in requests.data" :key="req.id" class="hover:bg-gray-50">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                            {{ req.employee?.first_name }} {{ req.employee?.last_name }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                            {{ typeLabels[req.type] || req.type }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                            {{ req.start_date }} to {{ req.end_date }}
                                        </td>
                                        <td class="max-w-[200px] truncate px-6 py-4 text-sm text-gray-500">
                                            {{ req.notes || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-center">
                                            <StatusBadge :color="statusColors[req.status]">{{ req.status }}</StatusBadge>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                            <template v-if="req.status === 'pending'">
                                                <button @click="openAction('approve', req)" class="text-green-600 hover:text-green-800 mr-2">Approve</button>
                                                <button @click="openAction('deny', req)" class="text-red-600 hover:text-red-800">Deny</button>
                                            </template>
                                        </td>
                                    </tr>
                                    <tr v-if="!requests.data.length">
                                        <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">No PTO requests found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Calendar (1/3) -->
                    <div>
                        <PtoCalendar :events="calendarEvents" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Action modal -->
        <Modal :show="showActionModal" @close="showActionModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 capitalize">{{ actionType }} PTO Request</h3>
                <p class="mt-2 text-sm text-gray-600">
                    {{ actionRequest?.employee?.first_name }} {{ actionRequest?.employee?.last_name }}:
                    {{ actionRequest?.start_date }} to {{ actionRequest?.end_date }}
                </p>
                <textarea
                    v-model="adminNotes"
                    rows="3"
                    placeholder="Notes (optional)..."
                    class="mt-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <div class="mt-6 flex justify-end gap-3">
                    <Button variant="secondary" @click="showActionModal = false">Cancel</Button>
                    <Button
                        :variant="actionType === 'approve' ? 'primary' : 'danger'"
                        @click="submitAction"
                    >
                        {{ actionType === 'approve' ? 'Approve' : 'Deny' }}
                    </Button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 7: Run tests and verify**

Run: `docker compose exec app php artisan test tests/Feature/Admin/PtoManagementTest.php`
Expected: All tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/PtoController.php resources/js/Pages/Pto/ resources/js/Components/PtoCalendar.vue tests/Feature/Admin/PtoManagementTest.php routes/web.php
git commit -m "feat: add PTO management with approve/deny workflow and calendar view"
```

---

## Task 15: Billing Settings Page

**Files:**
- Create: `app/Http/Controllers/Admin/BillingController.php`
- Create: `resources/js/Pages/Billing/Index.vue`
- Create: `tests/Feature/Admin/BillingSettingsTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the feature test**

```php
// tests/Feature/Admin/BillingSettingsTest.php
<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $this->tenant);
    }

    public function test_billing_page_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.billing.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Billing/Index')
                ->has('currentPlan')
                ->has('invoices')
        );
    }

    public function test_billing_page_shows_current_plan(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.billing.index'));

        $response->assertInertia(fn ($page) =>
            $page->where('currentPlan', 'business')
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Admin/BillingSettingsTest.php`
Expected: FAIL.

- [ ] **Step 3: Add routes**

Append to `routes/web.php` inside the admin group:

```php
Route::get('billing', [BillingController::class, 'index'])->name('admin.billing.index');
Route::post('billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('admin.billing.checkout');
Route::post('billing/portal', [BillingController::class, 'portal'])->name('admin.billing.portal');
```

- [ ] **Step 4: Create BillingController**

```php
// app/Http/Controllers/Admin/BillingController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function index()
    {
        $tenant = app('current_tenant');
        $subscription = $tenant->subscription();

        $invoices = [];
        if ($tenant->stripe_id) {
            try {
                $invoices = $tenant->invoices()->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'date' => $invoice->date()->format('M d, Y'),
                    'total' => $invoice->total(),
                    'status' => $invoice->status,
                    'url' => $invoice->invoiceUrl(),
                    'pdf' => $invoice->invoicePdf(),
                ]);
            } catch (\Exception $e) {
                $invoices = [];
            }
        }

        return Inertia::render('Billing/Index', [
            'currentPlan' => $tenant->plan,
            'status' => $tenant->status,
            'trialEndsAt' => $tenant->trial_ends_at?->format('M d, Y'),
            'onTrial' => $tenant->onTrial(),
            'subscribed' => $subscription?->active() ?? false,
            'paymentMethod' => $tenant->pm_type ? [
                'type' => $tenant->pm_type,
                'lastFour' => $tenant->pm_last_four,
            ] : null,
            'activeEmployeeCount' => Employee::where('status', 'active')->count(),
            'invoices' => $invoices,
            'plans' => [
                'starter' => ['name' => 'Starter', 'price' => 8, 'features' => ['Geofencing', 'Time tracking', 'Team management', 'Mobile app', 'Basic reports']],
                'business' => ['name' => 'Business', 'price' => 12, 'features' => ['Everything in Starter', 'QBO integration', 'Advanced reports', 'Job costing', 'Bank feeds']],
            ],
        ]);
    }

    public function checkout(Request $request, string $plan)
    {
        $tenant = app('current_tenant');
        $priceId = config("billing.plans.{$plan}.stripe_price_id");

        if (!$priceId) {
            return redirect()->back()->with('error', 'Invalid plan selected.');
        }

        $employeeCount = Employee::where('status', 'active')->count();

        $checkout = $tenant->newSubscription('default', $priceId)
            ->quantity(max(1, $employeeCount))
            ->checkout([
                'success_url' => route('admin.billing.index') . '?success=true',
                'cancel_url' => route('admin.billing.index') . '?cancelled=true',
            ]);

        return Inertia::location($checkout->url);
    }

    public function portal(Request $request)
    {
        $tenant = app('current_tenant');

        return Inertia::location(
            $tenant->billingPortalUrl(route('admin.billing.index'))
        );
    }
}
```

- [ ] **Step 5: Create Billing/Index.vue**

```vue
<!-- resources/js/Pages/Billing/Index.vue -->
<script setup>
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Button from '@/Components/Button.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({
    currentPlan: String,
    status: String,
    trialEndsAt: String,
    onTrial: Boolean,
    subscribed: Boolean,
    paymentMethod: Object,
    activeEmployeeCount: Number,
    invoices: Array,
    plans: Object,
})

function checkout(plan) {
    router.post(route('admin.billing.checkout', plan))
}

function openPortal() {
    router.post(route('admin.billing.portal'))
}

const statusColors = {
    active: 'green',
    trial: 'blue',
    past_due: 'red',
    cancelled: 'gray',
    suspended: 'red',
}
</script>

<template>
    <Head title="Billing" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Billing & Subscription</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Current status -->
                <div class="rounded-lg bg-white p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Current Plan</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                <span class="font-medium capitalize">{{ currentPlan }}</span>
                                &mdash; ${{ plans[currentPlan]?.price }}/user/month
                            </p>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ activeEmployeeCount }} active employee{{ activeEmployeeCount !== 1 ? 's' : '' }}
                                &mdash; est. ${{ activeEmployeeCount * (plans[currentPlan]?.price || 0) }}/mo
                            </p>
                        </div>
                        <StatusBadge :color="statusColors[status]">{{ status }}</StatusBadge>
                    </div>

                    <div v-if="onTrial" class="mt-4 rounded-md bg-blue-50 p-3">
                        <p class="text-sm text-blue-700">
                            Your trial ends on <strong>{{ trialEndsAt }}</strong>. Subscribe to keep using all features.
                        </p>
                    </div>
                </div>

                <!-- Plans -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div
                        v-for="(plan, key) in plans"
                        :key="key"
                        class="rounded-lg bg-white p-6 shadow ring-1"
                        :class="currentPlan === key ? 'ring-indigo-500' : 'ring-gray-200'"
                    >
                        <h4 class="text-lg font-semibold text-gray-900">{{ plan.name }}</h4>
                        <p class="mt-1 text-3xl font-bold text-gray-900">${{ plan.price }}<span class="text-sm font-normal text-gray-500">/user/mo</span></p>
                        <ul class="mt-4 space-y-2">
                            <li v-for="feature in plan.features" :key="feature" class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                {{ feature }}
                            </li>
                        </ul>
                        <div class="mt-6">
                            <Button
                                v-if="currentPlan !== key"
                                class="w-full"
                                @click="checkout(key)"
                            >
                                {{ currentPlan === 'business' ? 'Switch to Starter' : 'Upgrade to Business' }}
                            </Button>
                            <p v-else class="text-center text-sm font-medium text-indigo-600">Current Plan</p>
                        </div>
                    </div>
                </div>

                <!-- Payment method -->
                <div class="rounded-lg bg-white p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Payment Method</h3>
                            <p v-if="paymentMethod" class="mt-1 text-sm text-gray-600 capitalize">
                                {{ paymentMethod.type }} ending in {{ paymentMethod.lastFour }}
                            </p>
                            <p v-else class="mt-1 text-sm text-gray-400">No payment method on file.</p>
                        </div>
                        <Button v-if="subscribed" size="sm" variant="secondary" @click="openPortal">
                            Manage
                        </Button>
                    </div>
                </div>

                <!-- Invoice history -->
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-sm font-medium text-gray-900 mb-4">Invoice History</h3>
                    <div v-if="invoices.length" class="divide-y divide-gray-100">
                        <div v-for="invoice in invoices" :key="invoice.id"
                            class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm text-gray-900">{{ invoice.date }}</p>
                                <p class="text-xs text-gray-500">{{ invoice.total }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <StatusBadge :color="invoice.status === 'paid' ? 'green' : 'yellow'">
                                    {{ invoice.status }}
                                </StatusBadge>
                                <a :href="invoice.pdf" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-800">PDF</a>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No invoices yet.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 6: Run tests and verify**

Run: `docker compose exec app php artisan test tests/Feature/Admin/BillingSettingsTest.php`
Expected: All tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/BillingController.php resources/js/Pages/Billing/ tests/Feature/Admin/BillingSettingsTest.php routes/web.php
git commit -m "feat: add billing settings with plan display, Stripe Checkout, and invoice history"
```

---

## Task 16: Company Settings Page

**Files:**
- Create: `app/Http/Controllers/Admin/CompanySettingsController.php`
- Create: `resources/js/Pages/Settings/Company.vue`
- Create: `tests/Feature/Admin/CompanySettingsTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the feature test**

```php
// tests/Feature/Admin/CompanySettingsTest.php
<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Original Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
            'overtime_rule' => ['weekly_threshold' => 40, 'daily_threshold' => null, 'multiplier' => 1.5],
            'rounding_rule' => 'EXACT',
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $this->tenant);
    }

    public function test_settings_page_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.settings.company'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Settings/Company')
                ->has('tenant')
        );
    }

    public function test_can_update_company_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.settings.company.update'), [
                'name' => 'Renamed Co',
                'timezone' => 'America/New_York',
                'workweek_start_day' => 1,
                'overtime_weekly_threshold' => 40,
                'overtime_daily_threshold' => '',
                'overtime_multiplier' => 1.5,
                'rounding_rule' => 'EXACT',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'name' => 'Renamed Co',
        ]);
    }

    public function test_can_update_overtime_rules(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.settings.company.update'), [
                'name' => 'Original Co',
                'timezone' => 'America/Los_Angeles',
                'workweek_start_day' => 0,
                'overtime_weekly_threshold' => 40,
                'overtime_daily_threshold' => 8,
                'overtime_multiplier' => 1.5,
                'rounding_rule' => 'NEAREST_15',
            ]);

        $response->assertRedirect();
        $this->tenant->refresh();

        $this->assertEquals('America/Los_Angeles', $this->tenant->timezone);
        $this->assertEquals(0, $this->tenant->workweek_start_day);
        $this->assertEquals(8, $this->tenant->overtime_rule['daily_threshold']);
        $this->assertEquals('NEAREST_15', $this->tenant->rounding_rule);
    }

    public function test_rounding_rule_must_be_valid(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.settings.company.update'), [
                'name' => 'Original Co',
                'timezone' => 'America/New_York',
                'workweek_start_day' => 1,
                'overtime_weekly_threshold' => 40,
                'overtime_daily_threshold' => '',
                'overtime_multiplier' => 1.5,
                'rounding_rule' => 'INVALID',
            ]);

        $response->assertSessionHasErrors('rounding_rule');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Admin/CompanySettingsTest.php`
Expected: FAIL.

- [ ] **Step 3: Add routes**

Append to `routes/web.php` inside the admin group:

```php
Route::get('settings/company', [CompanySettingsController::class, 'edit'])->name('admin.settings.company');
Route::put('settings/company', [CompanySettingsController::class, 'update'])->name('admin.settings.company.update');
```

- [ ] **Step 4: Create CompanySettingsController**

```php
// app/Http/Controllers/Admin/CompanySettingsController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanySettingsController extends Controller
{
    public function edit()
    {
        $tenant = app('current_tenant');

        return Inertia::render('Settings/Company', [
            'tenant' => [
                'name' => $tenant->name,
                'timezone' => $tenant->timezone,
                'workweek_start_day' => $tenant->workweek_start_day,
                'overtime_rule' => $tenant->overtime_rule,
                'rounding_rule' => $tenant->rounding_rule,
            ],
            'timezones' => $this->getTimezones(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'required|string|timezone',
            'workweek_start_day' => 'required|integer|min:0|max:6',
            'overtime_weekly_threshold' => 'required|numeric|min:0|max:168',
            'overtime_daily_threshold' => 'nullable|numeric|min:0|max:24',
            'overtime_multiplier' => 'required|numeric|min:1|max:3',
            'rounding_rule' => 'required|in:EXACT,NEAREST_5,NEAREST_6,NEAREST_15',
        ]);

        $tenant = app('current_tenant');

        $tenant->update([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'workweek_start_day' => $validated['workweek_start_day'],
            'overtime_rule' => [
                'weekly_threshold' => (float) $validated['overtime_weekly_threshold'],
                'daily_threshold' => $validated['overtime_daily_threshold'] ? (float) $validated['overtime_daily_threshold'] : null,
                'multiplier' => (float) $validated['overtime_multiplier'],
            ],
            'rounding_rule' => $validated['rounding_rule'],
        ]);

        return redirect()->back()->with('success', 'Company settings updated.');
    }

    private function getTimezones(): array
    {
        $common = [
            'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
            'America/Anchorage', 'Pacific/Honolulu', 'America/Phoenix',
            'America/Indiana/Indianapolis', 'America/Detroit',
            'America/Kentucky/Louisville', 'America/Boise',
        ];

        return collect($common)->map(fn ($tz) => [
            'value' => $tz,
            'label' => str_replace(['America/', 'Pacific/', '_'], ['', '', ' '], $tz) . ' (' . now()->timezone($tz)->format('T') . ')',
        ])->toArray();
    }
}
```

- [ ] **Step 5: Create Settings/Company.vue**

```vue
<!-- resources/js/Pages/Settings/Company.vue -->
<script setup>
import { useForm, Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Button from '@/Components/Button.vue'
import FormInput from '@/Components/FormInput.vue'

const props = defineProps({
    tenant: Object,
    timezones: Array,
})

const form = useForm({
    name: props.tenant.name,
    timezone: props.tenant.timezone,
    workweek_start_day: props.tenant.workweek_start_day,
    overtime_weekly_threshold: props.tenant.overtime_rule?.weekly_threshold ?? 40,
    overtime_daily_threshold: props.tenant.overtime_rule?.daily_threshold ?? '',
    overtime_multiplier: props.tenant.overtime_rule?.multiplier ?? 1.5,
    rounding_rule: props.tenant.rounding_rule,
})

function submit() {
    form.put(route('admin.settings.company.update'))
}

const daysOfWeek = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
]

const roundingOptions = [
    { value: 'EXACT', label: 'Exact (no rounding)' },
    { value: 'NEAREST_5', label: 'Nearest 5 minutes' },
    { value: 'NEAREST_6', label: 'Nearest 6 minutes (1/10 hour)' },
    { value: 'NEAREST_15', label: 'Nearest 15 minutes (1/4 hour)' },
]
</script>

<template>
    <Head title="Company Settings" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Company Settings</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-8">
                    <!-- General -->
                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="text-base font-semibold text-gray-900 mb-4">General</h3>
                        <div class="space-y-4">
                            <FormInput v-model="form.name" label="Company Name" :error="form.errors.name" required />

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Timezone</label>
                                <select
                                    v-model="form.timezone"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                                </select>
                                <p v-if="form.errors.timezone" class="mt-1 text-sm text-red-600">{{ form.errors.timezone }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Workweek Start Day</label>
                                <select
                                    v-model.number="form.workweek_start_day"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option v-for="day in daysOfWeek" :key="day.value" :value="day.value">{{ day.label }}</option>
                                </select>
                                <p v-if="form.errors.workweek_start_day" class="mt-1 text-sm text-red-600">{{ form.errors.workweek_start_day }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Overtime Rules -->
                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="text-base font-semibold text-gray-900 mb-4">Overtime Rules</h3>
                        <div class="space-y-4">
                            <FormInput
                                v-model="form.overtime_weekly_threshold"
                                label="Weekly Overtime Threshold (hours)"
                                type="number"
                                step="0.5"
                                :error="form.errors.overtime_weekly_threshold"
                                required
                            />
                            <p class="text-xs text-gray-500 -mt-2">Hours per week before overtime kicks in. Federal FLSA default: 40 hours.</p>

                            <FormInput
                                v-model="form.overtime_daily_threshold"
                                label="Daily Overtime Threshold (hours, optional)"
                                type="number"
                                step="0.5"
                                :error="form.errors.overtime_daily_threshold"
                            />
                            <p class="text-xs text-gray-500 -mt-2">Leave blank for no daily threshold. California requires 8 hours/day.</p>

                            <FormInput
                                v-model="form.overtime_multiplier"
                                label="Overtime Rate Multiplier"
                                type="number"
                                step="0.1"
                                min="1"
                                max="3"
                                :error="form.errors.overtime_multiplier"
                                required
                            />
                            <p class="text-xs text-gray-500 -mt-2">Standard: 1.5x (time and a half). Double time: 2.0x.</p>
                        </div>
                    </div>

                    <!-- Rounding Rules -->
                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="text-base font-semibold text-gray-900 mb-4">Time Rounding</h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Rounding Rule</label>
                            <select
                                v-model="form.rounding_rule"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option v-for="opt in roundingOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <p v-if="form.errors.rounding_rule" class="mt-1 text-sm text-red-600">{{ form.errors.rounding_rule }}</p>
                            <p class="mt-2 text-xs text-gray-500">
                                Rounding is applied at the display/payroll level only. Raw timestamps are always preserved.
                                Per FLSA, rounding must be neutral and cannot consistently favor the employer.
                            </p>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing">Save Settings</Button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 6: Run tests and verify**

Run: `docker compose exec app php artisan test tests/Feature/Admin/CompanySettingsTest.php`
Expected: All tests PASS.

- [ ] **Step 7: Run full test suite**

Run: `docker compose exec app php artisan test tests/Feature/Admin/`
Expected: All tests across tasks 9-16 PASS (JobManagementTest, GeofenceManagementTest, MapDashboardTest, TimesheetApprovalTest, ReportTest, PtoManagementTest, BillingSettingsTest, CompanySettingsTest).

- [ ] **Step 8: Final manual verification checklist**

Verify each page loads without JS errors:

1. `/admin/jobs` — Job list with budget progress bars
2. `/admin/jobs/create` — Job creation form with address field
3. `/admin/geofences` — Geofence list with active/inactive toggle
4. `/admin/geofences/create` — Map editor with circle drawing tool
5. `/admin/map` — Real-time map with geofence circles and employee pins
6. `/admin/timesheets` — Timesheet list with approve/reject and bulk approve
7. `/admin/reports` — Report index with 8 report cards
8. `/admin/reports/payroll-summary?start_date=2026-03-01&end_date=2026-03-31` — Chart + table + CSV/PDF
9. `/admin/pto` — PTO requests with calendar sidebar
10. `/admin/billing` — Plan comparison, payment method, invoice history
11. `/admin/settings/company` — Company name, timezone, overtime rules, rounding

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/CompanySettingsController.php resources/js/Pages/Settings/ tests/Feature/Admin/CompanySettingsTest.php routes/web.php
git commit -m "feat: add company settings with timezone, overtime rules, and rounding configuration"
```

- [ ] **Step 10: Final commit — update route summary**

Verify `routes/web.php` has all the routes added in tasks 9-16. Full route block that should exist inside the admin middleware group:

```php
// Task 9: Jobs
Route::resource('jobs', JobController::class)->names('admin.jobs');

// Task 10: Geofences
Route::resource('geofences', GeofenceController::class)->names('admin.geofences');
Route::patch('geofences/{geofence}/toggle', [GeofenceController::class, 'toggle'])->name('admin.geofences.toggle');

// Task 11: Map
Route::get('map', [MapDashboardController::class, 'index'])->name('admin.map');

// Task 12: Timesheets
Route::get('timesheets', [TimesheetController::class, 'index'])->name('admin.timesheets.index');
Route::patch('timesheets/{timeEntry}/approve', [TimesheetController::class, 'approve'])->name('admin.timesheets.approve');
Route::patch('timesheets/{timeEntry}/reject', [TimesheetController::class, 'reject'])->name('admin.timesheets.reject');
Route::post('timesheets/bulk-approve', [TimesheetController::class, 'bulkApprove'])->name('admin.timesheets.bulk-approve');

// Task 13: Reports
Route::prefix('reports')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/payroll-summary', [ReportController::class, 'payrollSummary'])->name('admin.reports.payroll-summary');
    Route::get('/payroll-summary/csv', [ReportController::class, 'payrollSummaryCsv'])->name('admin.reports.payroll-summary.csv');
    Route::get('/payroll-summary/pdf', [ReportController::class, 'payrollSummaryPdf'])->name('admin.reports.payroll-summary.pdf');
    Route::get('/attendance', [ReportController::class, 'attendance'])->name('admin.reports.attendance');
    Route::get('/attendance/csv', [ReportController::class, 'attendanceCsv'])->name('admin.reports.attendance.csv');
    Route::get('/attendance/pdf', [ReportController::class, 'attendancePdf'])->name('admin.reports.attendance.pdf');
    Route::get('/overtime', [ReportController::class, 'overtime'])->name('admin.reports.overtime');
    Route::get('/overtime/csv', [ReportController::class, 'overtimeCsv'])->name('admin.reports.overtime.csv');
    Route::get('/job-costing', [ReportController::class, 'jobCosting'])->name('admin.reports.job-costing');
    Route::get('/job-costing/csv', [ReportController::class, 'jobCostingCsv'])->name('admin.reports.job-costing.csv');
    Route::get('/job-costing/pdf', [ReportController::class, 'jobCostingPdf'])->name('admin.reports.job-costing.pdf');
    Route::get('/team-utilization', [ReportController::class, 'teamUtilization'])->name('admin.reports.team-utilization');
    Route::get('/team-utilization/csv', [ReportController::class, 'teamUtilizationCsv'])->name('admin.reports.team-utilization.csv');
    Route::get('/transfer-history', [ReportController::class, 'transferHistory'])->name('admin.reports.transfer-history');
    Route::get('/transfer-history/csv', [ReportController::class, 'transferHistoryCsv'])->name('admin.reports.transfer-history.csv');
    Route::get('/compliance-audit', [ReportController::class, 'complianceAudit'])->name('admin.reports.compliance-audit');
    Route::get('/compliance-audit/csv', [ReportController::class, 'complianceAuditCsv'])->name('admin.reports.compliance-audit.csv');
    Route::get('/geofence-activity', [ReportController::class, 'geofenceActivity'])->name('admin.reports.geofence-activity');
    Route::get('/geofence-activity/csv', [ReportController::class, 'geofenceActivityCsv'])->name('admin.reports.geofence-activity.csv');
});

// Task 14: PTO
Route::get('pto', [PtoController::class, 'index'])->name('admin.pto.index');
Route::patch('pto/{ptoRequest}/approve', [PtoController::class, 'approve'])->name('admin.pto.approve');
Route::patch('pto/{ptoRequest}/deny', [PtoController::class, 'deny'])->name('admin.pto.deny');

// Task 15: Billing
Route::get('billing', [BillingController::class, 'index'])->name('admin.billing.index');
Route::post('billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('admin.billing.checkout');
Route::post('billing/portal', [BillingController::class, 'portal'])->name('admin.billing.portal');

// Task 16: Settings
Route::get('settings/company', [CompanySettingsController::class, 'edit'])->name('admin.settings.company');
Route::put('settings/company', [CompanySettingsController::class, 'update'])->name('admin.settings.company.update');
```

```bash
git add routes/web.php
git commit -m "chore: verify all Plan 3b routes are registered"
```
