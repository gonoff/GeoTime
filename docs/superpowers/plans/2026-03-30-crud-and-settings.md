# CRUD Functionality & Settings Configuration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use team-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Agent teams are preferred over subagents for better coordination and fewer mistakes. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add working CRUD operations to all shell pages, make Settings editable, and make the dashboard verification widget respect the tenant's verification mode.

**Architecture:** Shared modal components (FormModal, ConfirmDialog, DeleteDialog, Toast) provide consistent UX across all pages. Each page controller gets new methods for create/update/delete/actions, reusing existing Form Request validation classes. Routes added to `routes/web.php` within the existing `auth` middleware group.

**Tech Stack:** Laravel 13, Vue 3 + Inertia.js, Tailwind CSS 4, PostgreSQL

**Spec:** `docs/superpowers/specs/2026-03-30-crud-and-settings-design.md`

**Prerequisites:**
- The `resources/js/Components/` directory does not exist yet — it will be created when writing the first component file.
- The User model has `isAdmin()` (admin, super_admin) and `isManager()` (manager, admin, super_admin) but no method including `team_lead`. For authorization checks that include team_lead, use inline role checks: `in_array($user->role, ['admin', 'super_admin', 'manager', 'team_lead'])`.
- The Job model maps to the `job_sites` table (`protected $table = 'job_sites'`). Route model binding `{job}` still works since Laravel resolves by model class.
- Timesheets are virtual aggregations — `startOfWeek(Carbon::MONDAY)` is hardcoded in the existing `index` method. New action methods follow the same pattern for consistency. The configurable `workweek_start_day` setting can be wired in as a follow-up.

---

## File Map

### New Files
| File | Responsibility |
|------|---------------|
| `resources/js/Components/FormModal.vue` | Reusable modal wrapper with overlay, slots, loading state |
| `resources/js/Components/ConfirmDialog.vue` | Small confirm/cancel modal for actions |
| `resources/js/Components/DeleteDialog.vue` | Two-mode delete (archive + permanent) |
| `resources/js/Components/Toast.vue` | Flash message notification renderer |

### Modified Files
| File | Changes |
|------|---------|
| `resources/js/Layouts/AppLayout.vue` | Import and mount Toast component |
| `routes/web.php` | Add all new CRUD and action routes |
| `app/Http/Controllers/Web/SettingsPageController.php` | Add `update` method, fix fallback values |
| `app/Http/Controllers/DashboardController.php` | Pass `clock_verification_mode`, conditional alerts |
| `resources/js/Pages/Dashboard.vue` | Conditional verification stat card |
| `resources/js/Pages/Settings/Index.vue` | Full editable form |
| `app/Http/Controllers/Web/TeamPageController.php` | Add `store`, `update`, `archive`, `destroy` |
| `resources/js/Pages/Teams/Index.vue` | CRUD modals and actions |
| `app/Http/Controllers/Web/GeofencePageController.php` | Add `store`, `update`, `deactivate`, `activate`, `destroy` |
| `resources/js/Pages/Geofences/Index.vue` | CRUD modals and actions |
| `app/Http/Controllers/Web/JobPageController.php` | Add `store`, `update`, `complete`, `destroy` |
| `resources/js/Pages/Jobs/Index.vue` | CRUD modals and actions |
| `app/Http/Controllers/Web/TransferPageController.php` | Add `store`, `approve`, `reject` |
| `resources/js/Pages/Transfers/Index.vue` | Create modal and approve/reject |
| `app/Http/Controllers/Web/PtoPageController.php` | Add `store`, `approve`, `deny`, `balance` |
| `resources/js/Pages/Pto/Index.vue` | Create modal and approve/deny |
| `app/Http/Controllers/Web/TimesheetPageController.php` | Add `approve`, `reject`, `processPayroll`, bulk methods |
| `resources/js/Pages/Timesheets/Index.vue` | ConfirmDialogs, bulk actions, process payroll |

---

## Task 1: Shared Components — FormModal, ConfirmDialog, DeleteDialog

**Files:**
- Create: `resources/js/Components/FormModal.vue`
- Create: `resources/js/Components/ConfirmDialog.vue`
- Create: `resources/js/Components/DeleteDialog.vue`

- [ ] **Step 1: Create FormModal.vue**

```vue
<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
        <div class="modal-container" :class="'modal--' + maxWidth" @keydown.esc="$emit('close')">
          <div class="modal-header">
            <h2 class="modal-title">{{ title }}</h2>
            <button class="modal-close" @click="$emit('close')">
              <X :size="18" />
            </button>
          </div>
          <div class="modal-body">
            <slot />
          </div>
          <div class="modal-footer">
            <slot name="footer">
              <button class="btn btn--ghost" @click="$emit('close')" :disabled="loading">Cancel</button>
              <button class="btn btn--primary" @click="$emit('submit')" :disabled="loading">
                <span v-if="loading" class="spinner" />
                {{ loading ? 'Saving...' : 'Save' }}
              </button>
            </slot>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  maxWidth: { type: String, default: 'lg' },
  loading: { type: Boolean, default: false },
});

defineEmits(['close', 'submit']);

watch(() => props.show, (val) => {
  document.body.style.overflow = val ? 'hidden' : '';
});
</script>
```

Style the modal with the project's CSS variable system (`--slab-2`, `--seam-1`, `--radius-lg`, `--sp-*`, `--chalk-*`). Use `.modal--sm { max-width: 400px }`, `.modal--md { max-width: 520px }`, `.modal--lg { max-width: 640px }`, `.modal--xl { max-width: 800px }`. Backdrop is `rgba(0,0,0,0.5)` with `z-index: 50`. Modal body has `max-height: 60vh; overflow-y: auto`. Add form input styles (`.form-group`, `.form-label`, `.form-input`, `.form-select`) matching the Settings page patterns.

- [ ] **Step 2: Create ConfirmDialog.vue**

```vue
<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
        <div class="confirm-container">
          <div class="confirm-icon" :class="{ 'confirm-icon--destructive': destructive }">
            <AlertTriangle :size="24" />
          </div>
          <h3 class="confirm-title">{{ title }}</h3>
          <p class="confirm-message">{{ message }}</p>
          <div class="confirm-actions">
            <button class="btn btn--ghost" @click="$emit('close')">Cancel</button>
            <button
              class="btn"
              :class="'btn--' + confirmColor"
              @click="$emit('confirm')"
            >{{ confirmLabel }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { AlertTriangle } from 'lucide-vue-next';

defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Confirm' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirm' },
  confirmColor: { type: String, default: 'primary' },
  destructive: { type: Boolean, default: false },
});

defineEmits(['close', 'confirm']);
</script>
```

Style: centered small modal (max-width 400px), text-center layout. Button colors: `.btn--green { background: var(--go); }`, `.btn--red { background: var(--halt); }`, `.btn--primary { background: var(--viz); }`.

- [ ] **Step 3: Create DeleteDialog.vue**

```vue
<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
        <div class="confirm-container">
          <div class="confirm-icon confirm-icon--destructive">
            <Trash2 :size="24" />
          </div>
          <h3 class="confirm-title">Delete {{ entityType }}</h3>

          <template v-if="canArchive && !showPermanent">
            <p class="confirm-message">
              Archive "{{ entityName }}" instead of deleting? Archived items can be restored later.
            </p>
            <div class="confirm-actions">
              <button class="btn btn--ghost" @click="$emit('close')">Cancel</button>
              <button class="btn btn--yellow" @click="$emit('archive')">Archive</button>
            </div>
            <button class="permanent-link" @click="showPermanent = true">
              Permanently delete instead...
            </button>
          </template>

          <template v-else>
            <p class="confirm-message">
              This will permanently delete "{{ entityName }}". This cannot be undone.
            </p>
            <div class="permanent-confirm">
              <label class="form-label">Type "{{ entityName }}" to confirm:</label>
              <input
                v-model="confirmText"
                class="form-input"
                :placeholder="entityName"
              />
            </div>
            <div class="confirm-actions">
              <button class="btn btn--ghost" @click="cancel">Cancel</button>
              <button
                class="btn btn--red"
                :disabled="confirmText !== entityName"
                @click="$emit('delete')"
              >Delete Permanently</button>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Trash2 } from 'lucide-vue-next';

const props = defineProps({
  show: { type: Boolean, default: false },
  entityName: { type: String, default: '' },
  entityType: { type: String, default: 'item' },
  canArchive: { type: Boolean, default: true },
});

defineEmits(['close', 'archive', 'delete']);

const showPermanent = ref(false);
const confirmText = ref('');

function cancel() {
  showPermanent.value = false;
  confirmText.value = '';
}

watch(() => props.show, (val) => {
  if (!val) {
    showPermanent.value = false;
    confirmText.value = '';
  }
});
</script>
```

- [ ] **Step 4: Verify components render correctly**

Run: `npm run build` (or `npx vite build`)
Expected: No Vue compilation errors

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/FormModal.vue resources/js/Components/ConfirmDialog.vue resources/js/Components/DeleteDialog.vue
git commit -m "feat: add shared modal components (FormModal, ConfirmDialog, DeleteDialog)"
```

---

## Task 2: Toast Notifications

**Files:**
- Create: `resources/js/Components/Toast.vue`
- Modify: `resources/js/Layouts/AppLayout.vue`

- [ ] **Step 1: Create Toast.vue**

```vue
<template>
  <Teleport to="body">
    <Transition name="toast">
      <div v-if="visible" class="toast" :class="'toast--' + type">
        <component :is="icon" :size="16" />
        <span class="toast-message">{{ message }}</span>
        <button class="toast-close" @click="dismiss">
          <X :size="14" />
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, watch, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle, AlertCircle, X } from 'lucide-vue-next';

const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref('success');
let timer = null;

const icon = computed(() => type.value === 'success' ? CheckCircle : AlertCircle);

watch(() => page.props.flash, (flash) => {
  if (flash?.success) {
    show(flash.success, 'success');
  } else if (flash?.error) {
    show(flash.error, 'error');
  }
}, { deep: true, immediate: true });

function show(msg, t) {
  message.value = msg;
  type.value = t;
  visible.value = true;
  clearTimeout(timer);
  timer = setTimeout(dismiss, 4000);
}

function dismiss() {
  visible.value = false;
  clearTimeout(timer);
}
</script>
```

Style: fixed position `top: var(--sp-4); right: var(--sp-4); z-index: 60`. Success uses `var(--go)` border-left. Error uses `var(--halt)`. Background `var(--slab-3)`, border `var(--seam-2)`, `border-radius: var(--radius-lg)`.

- [ ] **Step 2: Mount Toast in AppLayout.vue**

Add import and component at `resources/js/Layouts/AppLayout.vue`:

```vue
// In <script setup> — add import:
import Toast from '@/Components/Toast.vue';

// In <template> — add at end, before closing </div> of .app-shell:
<Toast />
```

- [ ] **Step 3: Verify build**

Run: `npm run build`
Expected: No compilation errors

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Toast.vue resources/js/Layouts/AppLayout.vue
git commit -m "feat: add toast notification component for flash messages"
```

---

## Task 3: Settings Page — Editable

**Files:**
- Modify: `app/Http/Controllers/Web/SettingsPageController.php`
- Modify: `resources/js/Pages/Settings/Index.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Fix fallback values and add `update` method to SettingsPageController**

At `app/Http/Controllers/Web/SettingsPageController.php`, fix the `index` method fallback values and add an `update` method:

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsPageController extends Controller
{
    public function index()
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return Inertia::render('Settings/Index', [
            'settings' => [
                'company_name' => $tenant?->name ?? '',
                'timezone' => $tenant?->timezone ?? 'America/New_York',
                'workweek_start_day' => $tenant?->workweek_start_day ?? 1,
                'overtime_rule' => $tenant?->overtime_rule ?? ['weekly_threshold' => 40, 'daily_threshold' => null, 'multiplier' => 1.5],
                'rounding_rule' => $tenant?->rounding_rule ?? 'EXACT',
                'clock_verification_mode' => $tenant?->clock_verification_mode ?? 'AUTO_ONLY',
            ],
        ]);
    }

    public function update(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'timezone' => ['required', 'string', 'timezone'],
            'workweek_start_day' => ['required', 'integer', 'between:0,6'],
            'clock_verification_mode' => ['required', 'in:AUTO_ONLY,AUTO_PHOTO'],
            'overtime_weekly_threshold' => ['required', 'numeric', 'min:0'],
            'overtime_daily_threshold' => ['nullable', 'numeric', 'min:0'],
            'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:3'],
            'rounding_rule' => ['required', 'in:EXACT,ROUND_UP,ROUND_DOWN,QUARTER,HALF'],
        ]);

        $tenant = app('current_tenant');
        $tenant->update([
            'timezone' => $validated['timezone'],
            'workweek_start_day' => $validated['workweek_start_day'],
            'clock_verification_mode' => $validated['clock_verification_mode'],
            'overtime_rule' => [
                'weekly_threshold' => $validated['overtime_weekly_threshold'],
                'daily_threshold' => $validated['overtime_daily_threshold'],
                'multiplier' => $validated['overtime_multiplier'],
            ],
            'rounding_rule' => $validated['rounding_rule'],
        ]);

        return back()->with('success', 'Settings updated successfully.');
    }
}
```

- [ ] **Step 2: Add route to web.php**

In `routes/web.php`, inside the `auth` middleware group, add:

```php
Route::put('/settings', [SettingsPageController::class, 'update'])->name('settings.update');
```

- [ ] **Step 3: Rewrite Settings/Index.vue as editable form**

Replace the entire `resources/js/Pages/Settings/Index.vue` with an editable form using `useForm` from Inertia. The form should:
- Use `useForm({ timezone, workweek_start_day, clock_verification_mode, overtime_weekly_threshold, overtime_daily_threshold, overtime_multiplier, rounding_rule })` initialized from `page.props.settings`
- Map `overtime_rule` object fields to flat form fields on init
- Submit via `form.put('/settings')`
- Show validation errors per field via `form.errors`
- Organize into 4 sections (Company Info, Clock Verification, Overtime Rules, Rounding Rules) with a single Save button
- Keep the same card-based styling using the existing CSS variables
- Radio groups for clock_verification_mode and rounding_rule
- Number inputs for overtime fields
- Dropdown for timezone and workweek_start_day

- [ ] **Step 4: Verify the settings form renders and submits**

Run: `npm run build`
Expected: No compilation errors

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/SettingsPageController.php resources/js/Pages/Settings/Index.vue routes/web.php
git commit -m "feat: make settings page editable with all tenant configuration options"
```

---

## Task 4: Dashboard — Conditional Verification Widget

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.vue`

- [ ] **Step 1: Update DashboardController to pass verification mode and conditionally suppress alert**

At `app/Http/Controllers/DashboardController.php`:

1. Add `clock_verification_mode` to the Inertia props: `'clock_verification_mode' => $tenant?->clock_verification_mode ?? 'AUTO_ONLY'`
2. Wrap the unverified entries alert in a condition: only push the alert if `$tenant->clock_verification_mode === 'AUTO_PHOTO'`
3. Only count unverified entries when mode is `AUTO_PHOTO` (avoid unnecessary query)

- [ ] **Step 2: Update Dashboard.vue**

In `resources/js/Pages/Dashboard.vue`:

1. Add `const verificationMode = computed(() => page.props.clock_verification_mode ?? 'AUTO_ONLY');`
2. Replace the 4th stat card with conditional rendering:
   - When `verificationMode === 'AUTO_PHOTO'`: show existing unverified entries ring (current behavior)
   - When `verificationMode === 'AUTO_ONLY'`: show a prompt card same dimensions, with `Settings` icon, title "Photo Verification", subtitle "Enable in Settings to require selfie verification", and a link `<a href="/settings">` styled subtly

Import `Settings` from `lucide-vue-next` for the prompt card icon.

- [ ] **Step 3: Verify build**

Run: `npm run build`
Expected: No compilation errors

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/DashboardController.php resources/js/Pages/Dashboard.vue
git commit -m "feat: dashboard verification widget respects tenant clock_verification_mode"
```

---

## Task 5: Teams Page — Full CRUD

**Files:**
- Modify: `app/Http/Controllers/Web/TeamPageController.php`
- Modify: `resources/js/Pages/Teams/Index.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Add CRUD methods to TeamPageController**

Update `app/Http/Controllers/Web/TeamPageController.php`:

1. Update `index` to also pass `employees` (id + full_name) for the team lead dropdown
2. Add `store` method: validate with `StoreTeamRequest`, create Team, redirect back with flash
3. Add `update` method: validate with `UpdateTeamRequest`, update Team, redirect back with flash
4. Add `archive` method: check role, set `status = 'ARCHIVED'`, redirect back with flash
5. Add `destroy` method: check `isAdmin()`, delete Team, redirect back with flash

Use existing `StoreTeamRequest` and `UpdateTeamRequest` for validation (they already exist). Note: `StoreTeamRequest::authorize()` checks `isAdmin() || isManager()` — this does NOT include `team_lead`. To match the spec's authorization (which includes team_lead), either update the Form Request's `authorize()` to also check `team_lead`, or add a manual role check in the controller and bypass the Form Request's authorize (set it to return `true` and handle auth in the controller). The simpler approach: update the Form Requests to include team_lead by checking `in_array($this->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])`. Apply the same pattern to all Form Requests that need team_lead access.

- [ ] **Step 2: Add routes to web.php**

Inside the `auth` middleware group:

```php
Route::post('/teams', [TeamPageController::class, 'store'])->name('teams.store');
Route::put('/teams/{team}', [TeamPageController::class, 'update'])->name('teams.update');
Route::post('/teams/{team}/archive', [TeamPageController::class, 'archive'])->name('teams.archive');
Route::delete('/teams/{team}', [TeamPageController::class, 'destroy'])->name('teams.destroy');
```

- [ ] **Step 3: Update Teams/Index.vue with CRUD modals**

Rewrite `resources/js/Pages/Teams/Index.vue`:

1. Import `FormModal`, `DeleteDialog` from `@/Components/*`
2. Import `useForm` from `@inertiajs/vue3`
3. Add "Add Team" button in toolbar next to title
4. Add create/edit FormModal with fields: name, description, color_tag (preset swatches: 6-8 hex colors to pick from), lead_employee_id (dropdown from `page.props.employees`), status
5. Add edit/delete icon buttons to each team card (pencil icon, trash icon)
6. Edit opens FormModal pre-filled, submits via `form.put('/teams/' + team.id)`
7. Delete opens DeleteDialog, archive submits `router.post('/teams/' + team.id + '/archive')`, delete submits `router.delete('/teams/' + team.id)`
8. Show `page.props.auth.user.role` to conditionally show action buttons
9. Keep existing card grid layout, add action icons to card top-right

- [ ] **Step 4: Verify build**

Run: `npm run build`
Expected: No compilation errors

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/TeamPageController.php resources/js/Pages/Teams/Index.vue routes/web.php
git commit -m "feat: add CRUD operations to Teams page"
```

---

## Task 6: Geofences Page — Full CRUD

**Files:**
- Modify: `app/Http/Controllers/Web/GeofencePageController.php`
- Modify: `resources/js/Pages/Geofences/Index.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Add CRUD methods to GeofencePageController**

Update `app/Http/Controllers/Web/GeofencePageController.php`:

1. Update `index` to pass `jobs` (id + name) for the job site dropdown
2. Add `store`: validate with `StoreGeofenceRequest`, create, redirect with flash
3. Add `update`: validate with `UpdateGeofenceRequest`, update, redirect with flash
4. Add `deactivate`: set `is_active = false`, redirect with flash
5. Add `activate`: set `is_active = true`, redirect with flash
6. Add `destroy`: check `isAdmin()`, delete, redirect with flash

- [ ] **Step 2: Add routes to web.php**

```php
Route::post('/geofences', [GeofencePageController::class, 'store'])->name('geofences.store');
Route::put('/geofences/{geofence}', [GeofencePageController::class, 'update'])->name('geofences.update');
Route::post('/geofences/{geofence}/deactivate', [GeofencePageController::class, 'deactivate'])->name('geofences.deactivate');
Route::post('/geofences/{geofence}/activate', [GeofencePageController::class, 'activate'])->name('geofences.activate');
Route::delete('/geofences/{geofence}', [GeofencePageController::class, 'destroy'])->name('geofences.destroy');
```

- [ ] **Step 3: Update Geofences/Index.vue with CRUD modals**

Update `resources/js/Pages/Geofences/Index.vue`:

1. Import `FormModal`, `DeleteDialog`
2. Add "Add Geofence" button in toolbar
3. FormModal fields: name (text), job_id (dropdown from `page.props.jobs`), latitude (number, step 0.000001), longitude (number, step 0.000001), radius_meters (number), is_active (toggle/checkbox)
4. Add Actions column to table with Edit, Activate/Deactivate toggle button, Delete icon
5. Edit opens FormModal pre-filled
6. Delete opens DeleteDialog (deactivate as archive action, permanent delete)
7. Conditionally show actions based on user role

- [ ] **Step 4: Verify build**

Run: `npm run build`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/GeofencePageController.php resources/js/Pages/Geofences/Index.vue routes/web.php
git commit -m "feat: add CRUD operations to Geofences page"
```

---

## Task 7: Jobs Page — Full CRUD

**Files:**
- Modify: `app/Http/Controllers/Web/JobPageController.php`
- Modify: `resources/js/Pages/Jobs/Index.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Add CRUD methods to JobPageController**

Update `app/Http/Controllers/Web/JobPageController.php`:

1. Add `store`: validate with `StoreJobRequest`, create, redirect with flash
2. Add `update`: validate with `UpdateJobRequest`, update, redirect with flash
3. Add `complete`: set `status = 'COMPLETED'`, redirect with flash
4. Add `destroy`: check `isAdmin()`, delete, redirect with flash

- [ ] **Step 2: Add routes to web.php**

```php
Route::post('/jobs', [JobPageController::class, 'store'])->name('jobs.store');
Route::put('/jobs/{job}', [JobPageController::class, 'update'])->name('jobs.update');
Route::post('/jobs/{job}/complete', [JobPageController::class, 'complete'])->name('jobs.complete');
Route::delete('/jobs/{job}', [JobPageController::class, 'destroy'])->name('jobs.destroy');
```

- [ ] **Step 3: Update Jobs/Index.vue with CRUD modals**

Update `resources/js/Pages/Jobs/Index.vue`:

1. Import `FormModal`, `ConfirmDialog`, `DeleteDialog`
2. Add "Add Job" button in toolbar (left side, next to filter)
3. FormModal fields: name, client_name, address, status (dropdown: ACTIVE/ON_HOLD), budget_hours (number), hourly_rate (number, step 0.01), start_date (date), end_date (date)
4. Add Actions column with Edit, Mark Complete (only for ACTIVE jobs), Delete
5. Mark Complete uses ConfirmDialog: "Mark this job as completed?"
6. Delete uses DeleteDialog (no archive for jobs — set `canArchive` to false). Only show Delete button to admin users (`page.props.auth.user.role` is 'admin' or 'super_admin')
7. Conditionally show action buttons based on user role (same pattern as Teams)

- [ ] **Step 4: Verify build**

Run: `npm run build`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/JobPageController.php resources/js/Pages/Jobs/Index.vue routes/web.php
git commit -m "feat: add CRUD operations to Jobs page"
```

---

## Task 8: Transfers Page — Create + Approve/Reject

**Files:**
- Modify: `app/Http/Controllers/Web/TransferPageController.php`
- Modify: `resources/js/Pages/Transfers/Index.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Add methods to TransferPageController**

Update `app/Http/Controllers/Web/TransferPageController.php`:

1. Update `index` to pass `employees` (id, full_name, current_team_id, current team name) and `teams` (id, name), and `reason_categories` / `reason_codes` constants from Transfer model
2. Add `store`: validate with `StoreTransferRequest`, create Transfer record directly (set `initiated_by` from `auth()->id()`, set `status` to 'PENDING'), redirect with flash
3. Add `approve`: check role (`isAdmin() || isManager()`), set `approved_by` and `status = 'APPROVED'`, then call `TransferService::executeTransfer()` to move the employee, redirect with flash
4. Add `reject`: check role, update `status = 'REJECTED'`, redirect with flash

Import `App\Services\TransferService`. Note: TransferService only handles the execution of approved transfers (updating team assignments, moving employee). The Transfer record itself is created directly in the `store` method.

- [ ] **Step 2: Add routes to web.php**

```php
Route::post('/transfers', [TransferPageController::class, 'store'])->name('transfers.store');
Route::post('/transfers/{transfer}/approve', [TransferPageController::class, 'approve'])->name('transfers.approve');
Route::post('/transfers/{transfer}/reject', [TransferPageController::class, 'reject'])->name('transfers.reject');
```

- [ ] **Step 3: Update Transfers/Index.vue**

Update `resources/js/Pages/Transfers/Index.vue`:

1. Import `FormModal`, `ConfirmDialog`
2. Add "New Transfer" button in toolbar
3. FormModal fields: employee_id (dropdown), from_team (auto-filled read-only span showing selected employee's current team), to_team_id (dropdown), transfer_type (radio: PERMANENT/TEMPORARY), reason_category (dropdown from Transfer::REASON_CATEGORIES), reason_code (dropdown from Transfer::REASON_CODES), notes (textarea), effective_date (date), expected_return_date (date, shown only if TEMPORARY)
4. Watch `employee_id` selection to auto-fill from_team from the employees list
5. For PENDING transfers: add Approve and Reject buttons in Actions column
6. Both use ConfirmDialog before submitting

- [ ] **Step 4: Verify build**

Run: `npm run build`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/TransferPageController.php resources/js/Pages/Transfers/Index.vue routes/web.php
git commit -m "feat: add create and approve/reject to Transfers page"
```

---

## Task 9: PTO Page — Create + Approve/Deny

**Files:**
- Modify: `app/Http/Controllers/Web/PtoPageController.php`
- Modify: `resources/js/Pages/Pto/Index.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Add methods to PtoPageController**

Update `app/Http/Controllers/Web/PtoPageController.php`:

1. Update `index` to pass `employees` (id, full_name) for dropdown
2. Add `store`: validate with `StorePtoRequest`, create PtoRequest, redirect with flash
3. Add `approve`: check role, validate balance (query PtoBalance for employee + type + year), update status to 'APPROVED', set `reviewed_by` and `reviewed_at`, redirect with flash
4. Add `deny`: check role, update status to 'DENIED', set reviewed fields, redirect with flash
5. Add `balance` method: return JSON response with employee's PTO balance by type for current year (query `PtoBalance` model)

```php
public function balance(Employee $employee)
{
    $balances = PtoBalance::where('employee_id', $employee->id)
        ->where('year', now()->year)
        ->get()
        ->mapWithKeys(fn ($b) => [$b->type => [
            'accrued' => $b->accrued_hours,
            'used' => $b->used_hours,
            'remaining' => $b->balance_hours,
        ]]);

    return response()->json($balances);
}
```

- [ ] **Step 2: Add routes to web.php**

```php
Route::post('/pto', [PtoPageController::class, 'store'])->name('pto.store');
Route::post('/pto/{ptoRequest}/approve', [PtoPageController::class, 'approve'])->name('pto.approve');
Route::post('/pto/{ptoRequest}/deny', [PtoPageController::class, 'deny'])->name('pto.deny');
Route::get('/pto/balance/{employee}', [PtoPageController::class, 'balance'])->name('pto.balance');
```

Note: The balance route must come BEFORE any route with `{ptoRequest}` parameter to avoid conflicts.

- [ ] **Step 3: Update Pto/Index.vue**

Update `resources/js/Pages/Pto/Index.vue`:

1. Import `FormModal`, `ConfirmDialog`
2. Add "New Request" button in toolbar
3. FormModal fields: employee_id (dropdown), type (dropdown: VACATION/SICK/PERSONAL/UNPAID), start_date, end_date, hours (number), notes (textarea)
4. When employee is selected, fetch `/pto/balance/{employeeId}` via `fetch()` and display a small info box showing remaining hours per PTO type
5. Wire up existing Approve/Deny buttons (currently non-functional) to use ConfirmDialog + `router.post`
6. Approve submits `router.post('/pto/' + req.id + '/approve')`
7. Deny submits `router.post('/pto/' + req.id + '/deny')`

- [ ] **Step 4: Verify build**

Run: `npm run build`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/PtoPageController.php resources/js/Pages/Pto/Index.vue routes/web.php
git commit -m "feat: add create and approve/deny to PTO page with balance lookup"
```

---

## Task 10: Timesheets Page — Approve/Reject/Payroll + Bulk Actions

**Files:**
- Modify: `app/Http/Controllers/Web/TimesheetPageController.php`
- Modify: `resources/js/Pages/Timesheets/Index.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Add action methods to TimesheetPageController**

Update `app/Http/Controllers/Web/TimesheetPageController.php`:

Since timesheets are virtual (no model), all methods accept `employee_id` + `week_start` and query TimeEntry records for that range.

```php
public function approve(Request $request)
{
    if (!in_array($request->user()->role, ['admin', 'super_admin', 'manager', 'team_lead'])) {
        abort(403);
    }

    $request->validate([
        'employee_id' => ['required', 'uuid', 'exists:employees,id'],
        'week_start' => ['required', 'date'],
    ]);

    $weekStart = Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY);
    $weekEnd = $weekStart->copy()->addDays(6);

    TimeEntry::where('employee_id', $request->employee_id)
        ->whereDate('clock_in', '>=', $weekStart)
        ->whereDate('clock_in', '<=', $weekEnd)
        ->where('status', 'SUBMITTED')
        ->update(['status' => 'APPROVED']);

    return back()->with('success', 'Timesheet approved.');
}
```

Add similar `reject` (same role check), `processPayroll` (admin only via `isAdmin()`, updates APPROVED -> PAYROLL_PROCESSED), `bulkApprove`, and `bulkReject` methods. All methods must include authorization checks.

Bulk methods accept `items` array of `{employee_id, week_start}` pairs and loop through each.

- [ ] **Step 2: Add routes to web.php**

```php
Route::post('/timesheets/approve', [TimesheetPageController::class, 'approve'])->name('timesheets.approve');
Route::post('/timesheets/reject', [TimesheetPageController::class, 'reject'])->name('timesheets.reject');
Route::post('/timesheets/process-payroll', [TimesheetPageController::class, 'processPayroll'])->name('timesheets.process-payroll');
Route::post('/timesheets/bulk-approve', [TimesheetPageController::class, 'bulkApprove'])->name('timesheets.bulk-approve');
Route::post('/timesheets/bulk-reject', [TimesheetPageController::class, 'bulkReject'])->name('timesheets.bulk-reject');
```

- [ ] **Step 3: Update Timesheets/Index.vue**

Update `resources/js/Pages/Timesheets/Index.vue`:

1. Import `ConfirmDialog`
2. Replace the existing direct `router.post('/api/timesheets/review', ...)` calls with proper web route calls via ConfirmDialog confirmation
3. Add "Process Payroll" button for APPROVED rows with a ConfirmDialog warning: "This marks the timesheet as finalized for payroll and cannot be undone."
4. Add checkbox column to each row for bulk selection
5. Add bulk action bar that appears when rows are selected: shows "X selected" count with "Approve All" and "Reject All" buttons
6. Track selected rows with a `ref(new Set())` keyed by `employee_id`
7. Bulk actions submit to `/timesheets/bulk-approve` or `/timesheets/bulk-reject` with items array
8. Show action buttons conditionally based on user role

- [ ] **Step 4: Verify build**

Run: `npm run build`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/TimesheetPageController.php resources/js/Pages/Timesheets/Index.vue routes/web.php
git commit -m "feat: add approve/reject/payroll actions and bulk operations to Timesheets page"
```

---

## Task 11: Final Verification

- [ ] **Step 1: Run full build**

Run: `npm run build`
Expected: Clean build, no errors

- [ ] **Step 2: Check all routes are registered**

Run: `php artisan route:list --path=/ --columns=method,uri,name`
Expected: All new routes appear (settings.update, teams.store, teams.update, etc.)

- [ ] **Step 3: Commit any remaining changes**

```bash
git status
# If any uncommitted changes remain, add and commit
```
