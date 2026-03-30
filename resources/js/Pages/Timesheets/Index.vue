<template>
  <AppLayout>
    <template #title>Timesheets</template>

    <!-- Week Selector -->
    <div class="week-selector">
      <button class="week-btn" @click="changeWeek(-1)">
        <ChevronLeft :size="18" />
      </button>
      <span class="week-label">{{ weekLabel }}</span>
      <button class="week-btn" @click="changeWeek(1)">
        <ChevronRight :size="18" />
      </button>
    </div>

    <!-- Bulk Action Bar -->
    <div v-if="selectedCount > 0" class="bulk-bar">
      <span class="bulk-count">{{ selectedCount }} selected</span>
      <button class="action-btn action-btn--approve" @click="promptBulkApprove">
        <Check :size="14" /> Approve All
      </button>
      <button class="action-btn action-btn--reject" @click="promptBulkReject">
        <X :size="14" /> Reject All
      </button>
      <button class="bulk-clear" @click="clearSelection">Clear</button>
    </div>

    <!-- Timesheets Table -->
    <div class="panel">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th class="col-check">
                <input type="checkbox" :checked="allSubmittedSelected" @change="toggleSelectAll" />
              </th>
              <th class="col-employee">Employee</th>
              <th class="col-day">Mon</th>
              <th class="col-day">Tue</th>
              <th class="col-day">Wed</th>
              <th class="col-day">Thu</th>
              <th class="col-day">Fri</th>
              <th class="col-day">Sat</th>
              <th class="col-day">Sun</th>
              <th class="col-total">Total</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="timesheets.length === 0">
              <td colspan="12" class="empty-cell">
                <div class="empty-state">
                  <FileText :size="24" :stroke-width="1.5" />
                  <span>No timesheets for this week</span>
                </div>
              </td>
            </tr>
            <tr v-for="ts in timesheets" :key="ts.employee_id" :class="{ 'row--selected': selected.has(ts.employee_id) }">
              <td class="col-check">
                <input
                  v-if="ts.status === 'SUBMITTED'"
                  type="checkbox"
                  :checked="selected.has(ts.employee_id)"
                  @change="toggleSelect(ts)"
                />
              </td>
              <td class="cell-primary">{{ ts.employee_name }}</td>
              <td
                v-for="(hours, idx) in ts.daily_hours"
                :key="idx"
                class="cell-hours"
                :class="{ 'cell-hours--over': hours > 8 }"
              >
                {{ hours > 0 ? hours + 'h' : '' }}
              </td>
              <td class="cell-total">{{ ts.total_hours }}h</td>
              <td>
                <span class="badge" :class="statusClass(ts.status)">
                  {{ formatStatus(ts.status) }}
                </span>
              </td>
              <td class="cell-actions">
                <template v-if="ts.status === 'SUBMITTED' && canManage">
                  <button
                    class="action-btn action-btn--approve"
                    @click="promptApprove(ts)"
                  >
                    <Check :size="14" />
                    Approve
                  </button>
                  <button
                    class="action-btn action-btn--reject"
                    @click="promptReject(ts)"
                  >
                    <X :size="14" />
                    Reject
                  </button>
                </template>
                <template v-else-if="ts.status === 'APPROVED' && isAdmin">
                  <button
                    class="action-btn action-btn--payroll"
                    @click="promptPayroll(ts)"
                  >
                    Process Payroll
                  </button>
                </template>
                <span v-else class="cell-meta">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Approve ConfirmDialog -->
    <ConfirmDialog
      :show="showApproveDialog"
      title="Approve Timesheet"
      :message="`Approve the timesheet for ${pendingTs?.employee_name}?`"
      confirm-label="Approve"
      confirm-color="green"
      @close="showApproveDialog = false"
      @confirm="submitApprove"
    />

    <!-- Reject ConfirmDialog -->
    <ConfirmDialog
      :show="showRejectDialog"
      title="Reject Timesheet"
      :message="`Reject the timesheet for ${pendingTs?.employee_name}?`"
      confirm-label="Reject"
      confirm-color="red"
      :destructive="true"
      @close="showRejectDialog = false"
      @confirm="submitReject"
    />

    <!-- Process Payroll ConfirmDialog -->
    <ConfirmDialog
      :show="showPayrollDialog"
      title="Process Payroll"
      :message="`This marks ${pendingTs?.employee_name}'s timesheet as finalized for payroll and cannot be undone.`"
      confirm-label="Process Payroll"
      confirm-color="primary"
      :destructive="true"
      @close="showPayrollDialog = false"
      @confirm="submitPayroll"
    />

    <!-- Bulk Approve ConfirmDialog -->
    <ConfirmDialog
      :show="showBulkApproveDialog"
      title="Bulk Approve"
      :message="`Approve ${selectedCount} selected timesheets?`"
      confirm-label="Approve All"
      confirm-color="green"
      @close="showBulkApproveDialog = false"
      @confirm="submitBulkApprove"
    />

    <!-- Bulk Reject ConfirmDialog -->
    <ConfirmDialog
      :show="showBulkRejectDialog"
      title="Bulk Reject"
      :message="`Reject ${selectedCount} selected timesheets?`"
      confirm-label="Reject All"
      confirm-color="red"
      :destructive="true"
      @close="showBulkRejectDialog = false"
      @confirm="submitBulkReject"
    />
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ChevronLeft, ChevronRight, FileText, Check, X } from 'lucide-vue-next';

const page = usePage();
const timesheets = computed(() => page.props.timesheets ?? []);
const weekStart = computed(() => page.props.week_start);
const weekLabel = computed(() => page.props.week_label);

const canManage = computed(() => {
  const role = page.props.auth?.user?.role;
  return ['admin', 'super_admin', 'manager', 'team_lead'].includes(role);
});

const isAdmin = computed(() => {
  const role = page.props.auth?.user?.role;
  return ['admin', 'super_admin'].includes(role);
});

// Week navigation
function changeWeek(delta) {
  const current = new Date(weekStart.value);
  current.setDate(current.getDate() + delta * 7);
  const yyyy = current.getFullYear();
  const mm = String(current.getMonth() + 1).padStart(2, '0');
  const dd = String(current.getDate()).padStart(2, '0');
  router.get('/timesheets', { week_start: `${yyyy}-${mm}-${dd}` }, { preserveState: true, replace: true });
}

// Bulk selection
const selected = ref(new Set());

const selectedCount = computed(() => selected.value.size);

const submittedTimesheets = computed(() =>
  timesheets.value.filter(ts => ts.status === 'SUBMITTED')
);

const allSubmittedSelected = computed(() =>
  submittedTimesheets.value.length > 0 &&
  submittedTimesheets.value.every(ts => selected.value.has(ts.employee_id))
);

function toggleSelect(ts) {
  const next = new Set(selected.value);
  if (next.has(ts.employee_id)) {
    next.delete(ts.employee_id);
  } else {
    next.add(ts.employee_id);
  }
  selected.value = next;
}

function toggleSelectAll() {
  if (allSubmittedSelected.value) {
    selected.value = new Set();
  } else {
    selected.value = new Set(submittedTimesheets.value.map(ts => ts.employee_id));
  }
}

function clearSelection() {
  selected.value = new Set();
}

// Single approve
const showApproveDialog = ref(false);
const pendingTs = ref(null);

function promptApprove(ts) {
  pendingTs.value = ts;
  showApproveDialog.value = true;
}

function submitApprove() {
  showApproveDialog.value = false;
  router.post('/timesheets/approve', {
    employee_id: pendingTs.value.employee_id,
    week_start: weekStart.value,
  });
}

// Single reject
const showRejectDialog = ref(false);

function promptReject(ts) {
  pendingTs.value = ts;
  showRejectDialog.value = true;
}

function submitReject() {
  showRejectDialog.value = false;
  router.post('/timesheets/reject', {
    employee_id: pendingTs.value.employee_id,
    week_start: weekStart.value,
  });
}

// Process payroll
const showPayrollDialog = ref(false);

function promptPayroll(ts) {
  pendingTs.value = ts;
  showPayrollDialog.value = true;
}

function submitPayroll() {
  showPayrollDialog.value = false;
  router.post('/timesheets/process-payroll', {
    employee_id: pendingTs.value.employee_id,
    week_start: weekStart.value,
  });
}

// Bulk approve
const showBulkApproveDialog = ref(false);

function promptBulkApprove() {
  showBulkApproveDialog.value = true;
}

function submitBulkApprove() {
  showBulkApproveDialog.value = false;
  const items = [...selected.value].map(employee_id => ({
    employee_id,
    week_start: weekStart.value,
  }));
  router.post('/timesheets/bulk-approve', { items }, {
    onSuccess: () => clearSelection(),
  });
}

// Bulk reject
const showBulkRejectDialog = ref(false);

function promptBulkReject() {
  showBulkRejectDialog.value = true;
}

function submitBulkReject() {
  showBulkRejectDialog.value = false;
  const items = [...selected.value].map(employee_id => ({
    employee_id,
    week_start: weekStart.value,
  }));
  router.post('/timesheets/bulk-reject', { items }, {
    onSuccess: () => clearSelection(),
  });
}

function statusClass(status) {
  switch (status) {
    case 'ACTIVE': return 'badge--go';
    case 'SUBMITTED': return 'badge--zone';
    case 'APPROVED': return 'badge--go';
    case 'REJECTED': return 'badge--halt';
    case 'PAYROLL_PROCESSED': return 'badge--muted';
    default: return 'badge--muted';
  }
}

function formatStatus(status) {
  if (!status) return '-';
  if (status === 'PAYROLL_PROCESSED') return 'Processed';
  return status.charAt(0) + status.slice(1).toLowerCase();
}
</script>

<style scoped>
/* === Week Selector === */
.week-selector {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  margin-bottom: var(--sp-5);
}

.week-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border: 1px solid var(--seam-2);
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--chalk-2);
  cursor: pointer;
  transition: all var(--duration) var(--ease);
}

.week-btn:hover {
  background: var(--seam-1);
  color: var(--chalk-1);
}

.week-label {
  font-size: 15px;
  font-weight: 600;
  color: var(--chalk-1);
  letter-spacing: -0.01em;
  min-width: 160px;
  text-align: center;
}

/* === Bulk Bar === */
.bulk-bar {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-4);
  background: var(--slab-3);
  border: 1px solid var(--seam-2);
  border-radius: var(--radius-md);
  margin-bottom: var(--sp-4);
}

.bulk-count {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
  margin-right: var(--sp-2);
}

.bulk-clear {
  margin-left: auto;
  font-size: 12px;
  color: var(--chalk-3);
  background: none;
  border: none;
  cursor: pointer;
  padding: var(--sp-1) var(--sp-2);
  border-radius: var(--radius-md);
}

.bulk-clear:hover {
  color: var(--chalk-1);
  background: var(--seam-1);
}

/* === Panel / Table === */
.panel {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.table-wrap {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.data-table th {
  text-align: left;
  padding: var(--sp-3) var(--sp-4);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--chalk-3);
  border-bottom: 1px solid var(--seam-2);
  white-space: nowrap;
}

.col-check {
  width: 36px;
  padding-left: var(--sp-4) !important;
}

.col-day {
  text-align: center;
  min-width: 56px;
}

.col-total {
  text-align: right;
}

.col-employee {
  min-width: 160px;
}

.data-table td {
  padding: var(--sp-3) var(--sp-4);
  color: var(--chalk-2);
  border-bottom: 1px solid var(--seam-1);
  white-space: nowrap;
}

.data-table tbody tr:hover {
  background: var(--seam-1);
}

.data-table tbody tr:last-child td {
  border-bottom: none;
}

.row--selected td {
  background: color-mix(in srgb, var(--viz) 6%, transparent);
}

.cell-primary {
  color: var(--chalk-1);
  font-weight: 500;
}

.cell-hours {
  text-align: center;
  font-variant-numeric: tabular-nums;
  font-size: 12px;
  color: var(--chalk-2);
}

.cell-hours--over {
  color: var(--flag);
  font-weight: 600;
}

.cell-total {
  text-align: right;
  font-weight: 600;
  color: var(--chalk-1);
  font-variant-numeric: tabular-nums;
}

.cell-actions {
  display: flex;
  gap: var(--sp-2);
  align-items: center;
}

.cell-meta {
  color: var(--chalk-4);
  font-size: 12px;
}

.empty-cell {
  text-align: center;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-8) 0;
  color: var(--chalk-4);
  font-size: 13px;
}

/* === Badges === */
.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  padding: 2px 8px;
  border-radius: var(--radius-full);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.01em;
  white-space: nowrap;
}

.badge--go {
  color: var(--go);
  background: var(--go-soft);
}

.badge--halt {
  color: var(--halt);
  background: var(--halt-soft);
}

.badge--zone {
  color: var(--zone);
  background: var(--zone-soft);
}

.badge--muted {
  color: var(--chalk-3);
  background: var(--slab-3);
}

/* === Action Buttons === */
.action-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-1) var(--sp-3);
  border-radius: var(--radius-md);
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  transition: all var(--duration) var(--ease);
}

.action-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.action-btn--approve {
  color: var(--go);
  background: var(--go-soft);
  border-color: rgba(90, 154, 106, 0.2);
}

.action-btn--approve:hover:not(:disabled) {
  background: rgba(90, 154, 106, 0.2);
}

.action-btn--reject {
  color: var(--halt);
  background: var(--halt-soft);
  border-color: rgba(192, 80, 80, 0.2);
}

.action-btn--reject:hover:not(:disabled) {
  background: rgba(192, 80, 80, 0.2);
}

.action-btn--payroll {
  color: var(--viz);
  background: color-mix(in srgb, var(--viz) 10%, transparent);
  border-color: color-mix(in srgb, var(--viz) 30%, transparent);
  font-size: 11px;
}

.action-btn--payroll:hover {
  background: color-mix(in srgb, var(--viz) 20%, transparent);
}
</style>
