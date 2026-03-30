# CRUD Functionality & Settings Configuration

## Problem

The GeoTime web UI has pages for Teams, Geofences, Transfers, PTO, Timesheets, and Settings that are display-only shells. The APIs exist and work, but the UI cannot create, edit, or take actions. Additionally, the dashboard hardcodes photo verification widgets without respecting the tenant's `clock_verification_mode` setting, and the Settings page is read-only.

## Solution Overview

1. Build a shared modal/form component system
2. Make the Settings page fully editable
3. Update the dashboard to conditionally render verification widgets
4. Add CRUD modals and action buttons to all shell pages

## Tech Stack Context

- Laravel 13, Vue 3 with Inertia.js, Tailwind CSS 4
- PostgreSQL, Redis sessions/cache/queue
- Existing API routes under `/api/v1` with Form Request validation
- Web routes use Inertia controllers that render Vue pages
- User roles: `admin`, `super_admin`, `manager`, `team_lead`, `employee`
- `User.isAdmin()` checks for admin/super_admin; `User.isManager()` checks for manager/admin/super_admin

## Authorization

All CRUD web routes require authentication. Role-based access mirrors the existing API authorization:

- **Settings**: admin only (isAdmin)
- **Teams/Jobs/Geofences create/edit/delete**: admin, manager, or team_lead
- **Transfers create**: admin, manager, or team_lead; approve/reject: admin or manager
- **PTO create**: admin, manager, or team_lead; approve/deny: admin, manager, or team_lead (matching existing `ReviewPtoRequest`)
- **Timesheets approve/reject**: admin, manager, or team_lead; process payroll: admin only
- **Delete (hard)**: admin only across all entities

Each controller method checks the user's role and aborts(403) if unauthorized. The Vue pages conditionally show action buttons based on `auth.user.role` from Inertia shared props.

## Tenant Scoping on Create

The `BelongsToTenant` trait auto-assigns `tenant_id` from `app('current_tenant')` in its `creating` hook, so explicit assignment is not strictly required. However, controllers should ensure the `ResolveTenant` middleware is active on all CRUD routes so the `current_tenant` binding is available.

---

## 1. Shared Components

### FormModal.vue
Reusable modal wrapper for create/edit forms.

**Props:**
- `show: Boolean` — controls visibility
- `title: String` — modal header text
- `maxWidth: String` — sm/md/lg/xl (default: lg)
- `loading: Boolean` — disables submit button, shows spinner

**Slots:**
- `default` — form body content
- `footer` — override default Cancel/Save buttons

**Emits:** `close`, `submit`

**Behavior:** Overlay backdrop, ESC to close, prevents body scroll when open. Footer has Cancel (emits close) and Save (emits submit) buttons. Save button shows loading state.

### ConfirmDialog.vue
Small modal for approve/reject/archive actions.

**Props:**
- `show: Boolean`
- `title: String`
- `message: String`
- `confirmLabel: String` — e.g. "Approve", "Reject"
- `confirmColor: String` — e.g. "green", "red", "yellow"
- `destructive: Boolean` — adds warning styling

**Emits:** `close`, `confirm`

### DeleteDialog.vue
Two-mode delete confirmation.

**Props:**
- `show: Boolean`
- `entityName: String` — display name of the thing being deleted
- `entityType: String` — e.g. "team", "geofence"
- `canArchive: Boolean` — whether archive option is available (default true)

**Behavior:**
- Primary action: Archive/Deactivate (soft delete)
- Secondary action: "Permanently delete" text link that expands to require typing the entity name to confirm
- Emits: `close`, `archive`, `delete`

### Toast Notifications
Add a toast component to AppLayout.vue that renders Inertia flash messages.

**Implementation:** Use Inertia's `session()->flash('success', 'message')` pattern. The toast component in AppLayout reads `$page.props.flash` and renders a dismissible notification (green for success, red for error) that auto-hides after 4 seconds.

---

## 2. Settings Page

### Route
- `PUT /settings` — handled by `SettingsPageController@update` (added to the existing controller)

### Controller: SettingsPageController (existing)
Add an `update` method that:
1. Checks `isAdmin()`, aborts 403 if not
2. Validates input fields
3. Updates the authenticated user's tenant record
4. Redirects back with flash message

Also fix the `index` method fallback values:
- Change `workweek_start_day` fallback from `'monday'` to `1` (integer, Monday)
- Change `clock_verification_mode` fallback from `'none'` to `'AUTO_ONLY'`
- Change `rounding_rule` fallback from `'none'` to `'EXACT'`

### Validation Rules
All fields are submitted together in a single form (not per-section). This avoids the partial validation problem.
```
timezone: required, string, timezone (PHP timezone validation)
workweek_start_day: required, integer, between:0,6
clock_verification_mode: required, in:AUTO_ONLY,AUTO_PHOTO
overtime_weekly_threshold: required, numeric, min:0
overtime_daily_threshold: nullable, numeric, min:0
overtime_multiplier: required, numeric, min:1, max:3
rounding_rule: required, in:EXACT,ROUND_UP,ROUND_DOWN,QUARTER,HALF
```

### Overtime Rule JSON Mapping
The form sends flat fields (`overtime_weekly_threshold`, `overtime_daily_threshold`, `overtime_multiplier`). The controller reconstructs the JSON structure before saving:
```php
$tenant->update([
    // ...other fields...
    'overtime_rule' => [
        'weekly_threshold' => $validated['overtime_weekly_threshold'],
        'daily_threshold' => $validated['overtime_daily_threshold'],
        'multiplier' => $validated['overtime_multiplier'],
    ],
]);
```

### Vue Page Updates (Settings.vue)

Refactor from read-only display to editable form. All sections in one form with a single Save button at the bottom.

**Section 1 — Company Info:**
- Timezone: searchable dropdown of common timezones
- Workweek Start Day: dropdown (Sunday=0 through Saturday=6)

**Section 2 — Clock Verification:**
- Radio group with two options:
  - "GPS Location Only" (AUTO_ONLY) — description: "Employees clock in/out with automatic GPS location verification"
  - "GPS + Photo Verification" (AUTO_PHOTO) — description: "Employees must also submit a selfie photo when clocking in"

**Section 3 — Overtime Rules:**
- Weekly threshold: number input (hours)
- Daily threshold: number input (hours, optional)
- Multiplier: number input (e.g. 1.5)

**Section 4 — Rounding Rules:**
- Radio group: Exact (EXACT), Round to nearest 15 min (QUARTER), Round to nearest 30 min (HALF), Always round up (ROUND_UP), Always round down (ROUND_DOWN)

**Form submission:** Single `router.put('/settings', allData)` via Inertia.

---

## 3. Dashboard Conditional Verification

### DashboardController Changes
The controller is at `app/Http/Controllers/DashboardController.php` (not in the `Web` subdirectory). It is an invokable controller (`__invoke`).

Add `clock_verification_mode` to the Inertia props passed to the dashboard page. Conditionally suppress the unverified entries alert when mode is `AUTO_ONLY`.

### Dashboard.vue Changes

The dashboard uses a 4-column stat grid. The verification stat card remains the same size in both modes — only its content changes.

**When `clock_verification_mode === 'AUTO_PHOTO'`:**
- Show unverified entries count stat (existing behavior)
- Show "X clock entries missing photo verification" alert when count > 0

**When `clock_verification_mode === 'AUTO_ONLY'`:**
- The fourth stat card becomes a prompt card (same grid dimensions): title "Photo Verification", subtitle "Enable in Settings to require selfie verification at clock-in", with a link to `/settings`
- Suppress "missing photo verification" alerts

---

## 4. Teams Page CRUD

### New Web Routes
- `POST /teams` — create team
- `PUT /teams/{team}` — update team
- `POST /teams/{team}/archive` — set status to 'archived' (no SoftDeletes trait; this is a status field update)
- `DELETE /teams/{team}` — hard delete

### Controller: TeamPageController
Add `store`, `update`, `archive`, `destroy` methods. Each validates via form request, performs the action, redirects back with flash. Archive sets `status = 'ARCHIVED'`. Restore from archive sets `status = 'ACTIVE'`. (Note: Team uses a `status` field, not `SoftDeletes`. All status values are uppercase to match the existing codebase convention.)

### Props Enhancement
Pass `employees` list (id + name) for the team lead dropdown.

### Vue Page Updates (Teams.vue)
- "Add Team" button in page header → opens FormModal
- Form fields: name (text), description (textarea), color tag (preset color swatches), team lead (employee dropdown), status (ACTIVE/ARCHIVED)
- Each row gets Edit and Delete icon buttons
- Edit opens FormModal pre-filled with team data
- Delete opens DeleteDialog (archive changes status to 'ARCHIVED', permanent delete removes record)
- Show member count per row

---

## 5. Geofences Page CRUD

### New Web Routes
- `POST /geofences` — create
- `PUT /geofences/{geofence}` — update
- `POST /geofences/{geofence}/deactivate` — set `is_active = false`
- `POST /geofences/{geofence}/activate` — set `is_active = true`
- `DELETE /geofences/{geofence}` — hard delete

### Controller: GeofencePageController
Add `store`, `update`, `deactivate`, `activate`, `destroy` methods.

### Props Enhancement
Pass `jobs` list (id + name) for the job site dropdown.

### Vue Page Updates (Geofences.vue)
- "Add Geofence" button → FormModal
- Form fields: name (text), job site (dropdown), latitude (number), longitude (number), radius in meters (number), active (toggle)
- Row actions: Edit, Deactivate/Activate toggle, Delete
- Delete opens DeleteDialog (deactivate sets `is_active = false`, permanent delete removes record)

---

## 6. Jobs Page CRUD

### New Web Routes
- `POST /jobs` — create
- `PUT /jobs/{job}` — update
- `POST /jobs/{job}/complete` — set `status = 'COMPLETED'`. Does NOT auto-deactivate geofences (they may be reused or manually managed).
- `DELETE /jobs/{job}` — hard delete

### Controller: JobPageController
Add `store`, `update`, `complete`, `destroy` methods.

### Status Values
The Job model's `status` field accepts: `ACTIVE`, `COMPLETED`, `ON_HOLD`. The "Mark Complete" action sets `status = 'COMPLETED'`. (All uppercase per codebase convention.)

### Vue Page Updates (Jobs.vue)
- "Add Job" button → FormModal
- Form fields: name (text), client name (text), address (text), status (dropdown: ACTIVE/ON_HOLD), budget hours (number), hourly rate (currency), start date (date), end date (date)
- Row actions: Edit, Mark Complete (ConfirmDialog), Delete (DeleteDialog)

---

## 7. Transfers Page CRUD

### New Web Routes
- `POST /transfers` — create transfer
- `POST /transfers/{transfer}/approve` — approve
- `POST /transfers/{transfer}/reject` — reject

### Controller: TransferPageController
Add `store`, `approve`, `reject` methods. Uses existing TransferService for execution. The `store` method auto-sets `initiated_by` from the authenticated user.

### Props Enhancement
Pass `employees` (id + name + current_team_id + current team name) and `teams` (id + name) for dropdowns.

### Vue Page Updates (Transfers.vue)
- "New Transfer" button → FormModal
- Form fields: employee (dropdown), from team (auto-filled from selected employee's current team, read-only), to team (dropdown), type (Permanent/Temporary radio), reason category (dropdown from Transfer::REASON_CATEGORIES), reason code (dropdown from Transfer::REASON_CODES — flat list, no category filtering since the model has no category-to-code mapping), notes (textarea), effective date (date), expected return date (date, shown only if Temporary)
- Row actions for PENDING transfers: Approve, Reject (both via ConfirmDialog)
- Completed/rejected transfers are read-only rows

---

## 8. PTO Page CRUD

### New Web Routes
- `POST /pto` — create request
- `POST /pto/{ptoRequest}/approve` — approve
- `POST /pto/{ptoRequest}/deny` — deny

### Controller: PtoPageController
Add `store`, `approve`, `deny` methods. Approve checks PTO balance before granting.

### Props Enhancement
Pass `employees` (id + name) for the employee dropdown.

### PTO Balance Lookup
Add a new web route `GET /pto/balance/{employee}` that returns a JSON response with the employee's PTO balance (hours by type). This is needed because the existing API endpoint (`/api/v1/pto/balance/{employeeId}`) requires Sanctum token auth, which the web session doesn't have (SPA stateful mode is not configured). The web route uses standard session auth. The modal makes a fetch call to this endpoint when an employee is selected, and displays a small info box below the employee dropdown with remaining hours per PTO type.

### PTO Hours Field
The `hours` field is manually entered by the submitter and represents the total PTO hours requested. It is NOT auto-calculated from the date range because employees may request partial days. Validation: hours must be > 0 and <= the employee's available balance for the selected PTO type (checked server-side on approve).

### Vue Page Updates (Pto.vue)
- "New Request" button → FormModal
- Form fields: employee (dropdown), type (Vacation/Sick/Personal/Unpaid dropdown), start date (date), end date (date), hours (number — manually entered), notes (textarea)
- When employee is selected, async fetch balance and display below dropdown
- Row actions for PENDING: Approve, Deny (both via ConfirmDialog)

---

## 9. Timesheets Page Actions

Timesheets are virtual aggregations (no Timesheet model exists). They are identified by `employee_id` + `week_start` pair. All routes use these two parameters instead of a model ID. The server computes `week_end` as `week_start + 6 days` to define the query range (matching the existing `TimesheetPageController::index` pattern).

### New Web Routes
- `POST /timesheets/approve` — approve (body: `employee_id`, `week_start`)
- `POST /timesheets/reject` — reject (body: `employee_id`, `week_start`)
- `POST /timesheets/process-payroll` — mark as payroll processed (body: `employee_id`, `week_start`)
- `POST /timesheets/bulk-approve` — approve multiple (body: `items[]` array of `{employee_id, week_start}`)
- `POST /timesheets/bulk-reject` — reject multiple (body: `items[]` array of `{employee_id, week_start}`)

### Controller: TimesheetPageController
Add `approve`, `reject`, `processPayroll`, `bulkApprove`, `bulkReject` methods. Each method:
1. Finds all TimeEntry records matching the `employee_id` + `week_start` date range
2. Updates their `status` field (APPROVED, REJECTED, or PAYROLL_PROCESSED)
3. Redirects back with flash message

Process payroll requires admin role. Approve/reject requires admin, manager, or team_lead (matching existing API authorization).

### Vue Page Updates (Timesheets.vue)
- Row-level actions: Approve (green), Reject (red) for SUBMITTED timesheets, Process Payroll for APPROVED timesheets
- All actions use ConfirmDialog. Process Payroll dialog includes warning: "This marks the timesheet as finalized for payroll and cannot be undone."
- Bulk selection: checkboxes on each row, bulk action bar appears when rows selected with "Approve All" and "Reject All" buttons
- Status-aware: only show relevant actions per row status
- Each row passes `employee_id` and `week_start` as the identifying parameters for actions

---

## 10. Billing Page

Remains read-only. Stripe checkout integration is out of scope for this spec.

---

## File Changes Summary

### New Files
- `resources/js/Components/FormModal.vue`
- `resources/js/Components/ConfirmDialog.vue`
- `resources/js/Components/DeleteDialog.vue`
- `resources/js/Components/Toast.vue`

### Modified Files
- `resources/js/Layouts/AppLayout.vue` — add Toast component
- `resources/js/Pages/Settings/Index.vue` — editable form with all sections
- `resources/js/Pages/Dashboard.vue` — conditional verification widget
- `resources/js/Pages/Teams.vue` (or Teams/Index.vue) — full CRUD with modals
- `resources/js/Pages/Geofences.vue` (or Geofences/Index.vue) — full CRUD with modals
- `resources/js/Pages/Jobs.vue` (or Jobs/Index.vue) — full CRUD with modals
- `resources/js/Pages/Transfers.vue` (or Transfers/Index.vue) — create + approve/reject
- `resources/js/Pages/Pto.vue` (or Pto/Index.vue) — create + approve/deny
- `resources/js/Pages/Timesheets/Index.vue` — approve/reject/payroll + bulk actions
- `app/Http/Controllers/DashboardController.php` — pass verification mode, conditionally suppress unverified alert
- `app/Http/Controllers/Web/SettingsPageController.php` — add update method, fix fallback values
- `app/Http/Controllers/Web/TeamPageController.php` — add CRUD methods
- `app/Http/Controllers/Web/GeofencePageController.php` — add CRUD methods
- `app/Http/Controllers/Web/JobPageController.php` — add CRUD methods
- `app/Http/Controllers/Web/TransferPageController.php` — add CRUD methods
- `app/Http/Controllers/Web/PtoPageController.php` — add CRUD methods
- `app/Http/Controllers/Web/TimesheetPageController.php` — add action methods
- `routes/web.php` — new routes for all CRUD operations
