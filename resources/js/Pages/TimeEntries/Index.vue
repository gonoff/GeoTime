<template>
  <AppLayout>
    <template #title>Time Entries</template>

    <!-- Filters -->
    <div class="filters-bar">
      <div class="filter-group">
        <input
          type="text"
          class="filter-input filter-input--search"
          placeholder="Search employee..."
          :value="filters.search"
          @input="debounceSearch($event.target.value)"
        />
      </div>
      <div class="filter-group">
        <input
          type="date"
          class="filter-input"
          :value="filters.date_from"
          @change="applyFilter('date_from', $event.target.value)"
        />
        <span class="filter-separator">to</span>
        <input
          type="date"
          class="filter-input"
          :value="filters.date_to"
          @change="applyFilter('date_to', $event.target.value)"
        />
      </div>
      <div class="filter-group">
        <select
          class="filter-input"
          :value="filters.status"
          @change="applyFilter('status', $event.target.value)"
        >
          <option value="">All Statuses</option>
          <option value="ACTIVE">Active</option>
          <option value="SUBMITTED">Submitted</option>
          <option value="APPROVED">Approved</option>
          <option value="REJECTED">Rejected</option>
          <option value="PAYROLL_PROCESSED">Payroll Processed</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <div class="panel">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Job</th>
              <th>Clock In</th>
              <th>Clock Out</th>
              <th>Total</th>
              <th>Method</th>
              <th>Status</th>
              <th>Verification</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="entries.data.length === 0">
              <td colspan="8" class="empty-cell">
                <div class="empty-state">
                  <Clock :size="24" :stroke-width="1.5" />
                  <span>No time entries found</span>
                </div>
              </td>
            </tr>
            <tr v-for="entry in entries.data" :key="entry.id">
              <td class="cell-primary">{{ entry.employee_name }}</td>
              <td>{{ entry.job_name }}</td>
              <td class="cell-mono">{{ entry.clock_in ?? '-' }}</td>
              <td class="cell-mono">{{ entry.clock_out ?? '-' }}</td>
              <td class="cell-mono">{{ entry.total_hours ? entry.total_hours + 'h' : '-' }}</td>
              <td>
                <span class="badge" :class="methodClass(entry.clock_method)">
                  {{ entry.clock_method }}
                </span>
              </td>
              <td>
                <span class="badge" :class="statusClass(entry.status)">
                  {{ formatStatus(entry.status) }}
                </span>
              </td>
              <td>
                <span
                  v-if="entry.verification_status === 'VERIFIED'"
                  class="badge badge--go"
                >
                  Verified
                </span>
                <span
                  v-else-if="entry.verification_status === 'UNVERIFIED'"
                  class="badge badge--flag"
                >
                  <svg class="geofence-icon" width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.5" />
                    <circle cx="6" cy="6" r="1.5" fill="currentColor" />
                  </svg>
                  Unverified
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="entries.last_page > 1" class="pagination">
        <button
          class="page-btn"
          :disabled="!entries.prev_page_url"
          @click="goToPage(entries.current_page - 1)"
        >
          <ChevronLeft :size="16" />
        </button>
        <span class="page-info">
          Page {{ entries.current_page }} of {{ entries.last_page }}
        </span>
        <button
          class="page-btn"
          :disabled="!entries.next_page_url"
          @click="goToPage(entries.current_page + 1)"
        >
          <ChevronRight :size="16" />
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Clock, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const page = usePage();
const entries = computed(() => page.props.entries);
const filters = computed(() => page.props.filters);

let searchTimeout = null;

function debounceSearch(value) {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    applyFilter('search', value);
  }, 300);
}

function applyFilter(key, value) {
  const params = { ...filters.value, [key]: value, page: 1 };
  // Remove empty params
  Object.keys(params).forEach(k => {
    if (params[k] === '' || params[k] === null) delete params[k];
  });
  router.get('/time-entries', params, { preserveState: true, replace: true });
}

function goToPage(pageNum) {
  const params = { ...filters.value, page: pageNum };
  Object.keys(params).forEach(k => {
    if (params[k] === '' || params[k] === null) delete params[k];
  });
  router.get('/time-entries', params, { preserveState: true, replace: true });
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
  return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()).toLowerCase().replace(/^\w/, l => l.toUpperCase());
}

function methodClass(method) {
  switch (method) {
    case 'GEOFENCE': return 'badge--zone';
    case 'MANUAL': return 'badge--muted';
    case 'KIOSK': return 'badge--muted';
    default: return 'badge--muted';
  }
}
</script>

<style scoped>
/* === Filters === */
.filters-bar {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  margin-bottom: var(--sp-5);
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

.filter-separator {
  font-size: 12px;
  color: var(--chalk-4);
}

.filter-input {
  height: 34px;
  padding: 0 var(--sp-3);
  background: var(--pit);
  border: 1px solid var(--pit-border);
  border-radius: var(--radius-md);
  color: var(--chalk-1);
  font-size: 13px;
  font-family: inherit;
  transition: border-color var(--duration) var(--ease);
}

.filter-input:focus {
  outline: none;
  border-color: var(--pit-focus);
}

.filter-input--search {
  width: 220px;
}

.filter-input option {
  background: var(--slab-3);
  color: var(--chalk-1);
}

/* color-scheme for date inputs */
.filter-input[type="date"] {
  color-scheme: dark;
}

select.filter-input {
  cursor: pointer;
  appearance: none;
  padding-right: var(--sp-6);
  background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%237a7a72' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
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

.cell-mono {
  font-variant-numeric: tabular-nums;
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

.badge--flag {
  color: var(--flag);
  background: var(--flag-soft);
}

.badge--muted {
  color: var(--chalk-3);
  background: var(--slab-3);
}

.geofence-icon {
  flex-shrink: 0;
}

/* === Pagination === */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-4);
  border-top: 1px solid var(--seam-1);
}

.page-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: 1px solid var(--seam-2);
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--chalk-2);
  cursor: pointer;
  transition: all var(--duration) var(--ease);
}

.page-btn:hover:not(:disabled) {
  background: var(--seam-1);
  color: var(--chalk-1);
}

.page-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.page-info {
  font-size: 12px;
  color: var(--chalk-3);
  font-variant-numeric: tabular-nums;
}
</style>
