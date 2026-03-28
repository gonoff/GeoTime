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

    <!-- Timesheets Table -->
    <div class="panel">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
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
              <td colspan="11" class="empty-cell">
                <div class="empty-state">
                  <FileText :size="24" :stroke-width="1.5" />
                  <span>No timesheets for this week</span>
                </div>
              </td>
            </tr>
            <tr v-for="ts in timesheets" :key="ts.employee_id">
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
                <template v-if="ts.status === 'SUBMITTED'">
                  <button
                    class="action-btn action-btn--approve"
                    :disabled="approving === ts.employee_id"
                    @click="reviewTimesheet(ts.employee_id, 'approve')"
                  >
                    <Check :size="14" />
                    Approve
                  </button>
                  <button
                    class="action-btn action-btn--reject"
                    :disabled="approving === ts.employee_id"
                    @click="reviewTimesheet(ts.employee_id, 'reject')"
                  >
                    <X :size="14" />
                    Reject
                  </button>
                </template>
                <span v-else class="cell-meta">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChevronLeft, ChevronRight, FileText, Check, X } from 'lucide-vue-next';

const page = usePage();
const timesheets = computed(() => page.props.timesheets ?? []);
const weekStart = computed(() => page.props.week_start);
const weekLabel = computed(() => page.props.week_label);

const approving = ref(null);

function changeWeek(delta) {
  const current = new Date(weekStart.value);
  current.setDate(current.getDate() + delta * 7);
  const yyyy = current.getFullYear();
  const mm = String(current.getMonth() + 1).padStart(2, '0');
  const dd = String(current.getDate()).padStart(2, '0');
  router.get('/timesheets', { week_start: `${yyyy}-${mm}-${dd}` }, { preserveState: true, replace: true });
}

function reviewTimesheet(employeeId, action) {
  approving.value = employeeId;
  router.post('/api/timesheets/review', {
    employee_id: employeeId,
    week_start: weekStart.value,
    week_end: page.props.week_end,
    action,
  }, {
    preserveState: false,
    onFinish: () => {
      approving.value = null;
    },
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
</style>
