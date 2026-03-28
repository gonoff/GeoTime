<template>
  <AppLayout>
    <template #title>Job Sites</template>

    <!-- Filters -->
    <div class="toolbar">
      <div class="filter-group">
        <label class="filter-label">Status</label>
        <select class="filter-select" :value="filters.status ?? ''" @change="filterByStatus($event.target.value)">
          <option value="">All</option>
          <option value="ACTIVE">Active</option>
          <option value="COMPLETED">Completed</option>
          <option value="CANCELLED">Cancelled</option>
        </select>
      </div>
      <span class="record-count">{{ jobs.length }} job sites</span>
    </div>

    <!-- Table -->
    <div class="panel">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Client</th>
              <th>Status</th>
              <th class="col-right">Budget Hours</th>
              <th class="col-right">Hourly Rate</th>
              <th class="col-right">Geofences</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="jobs.length === 0">
              <td colspan="6" class="empty-cell">No job sites found</td>
            </tr>
            <tr v-for="job in jobs" :key="job.id">
              <td class="cell-primary">{{ job.name }}</td>
              <td>{{ job.client_name ?? '—' }}</td>
              <td>
                <span class="badge" :class="statusClass(job.status)">{{ job.status }}</span>
              </td>
              <td class="col-right tabular">{{ job.budget_hours ?? '—' }}</td>
              <td class="col-right tabular">{{ job.hourly_rate ? '$' + Number(job.hourly_rate).toFixed(2) : '—' }}</td>
              <td class="col-right tabular">{{ job.geofences_count }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const jobs = computed(() => page.props.jobs ?? []);
const filters = computed(() => page.props.filters ?? {});

function statusClass(status) {
  switch (status) {
    case 'ACTIVE': return 'badge--go';
    case 'COMPLETED': return 'badge--zone';
    case 'CANCELLED': return 'badge--halt';
    default: return 'badge--muted';
  }
}

function filterByStatus(status) {
  router.get('/jobs', status ? { status } : {}, { preserveState: true });
}
</script>

<style scoped>
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--sp-4);
}

.filter-group {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

.filter-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--chalk-3);
}

.filter-select {
  background: var(--pit);
  border: 1px solid var(--pit-border);
  border-radius: var(--radius-md);
  color: var(--chalk-1);
  font-size: 13px;
  padding: var(--sp-1) var(--sp-3);
  outline: none;
}

.filter-select:focus {
  border-color: var(--pit-focus);
}

.record-count {
  font-size: 12px;
  color: var(--chalk-3);
  font-variant-numeric: tabular-nums;
}

.panel {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
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
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--chalk-3);
  padding: var(--sp-3) var(--sp-4);
  border-bottom: 1px solid var(--seam-2);
  white-space: nowrap;
}

.data-table td {
  padding: var(--sp-3) var(--sp-4);
  border-bottom: 1px solid var(--seam-1);
  color: var(--chalk-2);
}

.data-table tr:last-child td {
  border-bottom: none;
}

.data-table tr:hover td {
  background: var(--seam-1);
}

.cell-primary {
  color: var(--chalk-1);
  font-weight: 500;
}

.col-right {
  text-align: right;
}

.tabular {
  font-variant-numeric: tabular-nums;
}

.empty-cell {
  text-align: center;
  color: var(--chalk-4);
  padding: var(--sp-8) var(--sp-4) !important;
}

/* Badges */
.badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-full);
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
</style>
