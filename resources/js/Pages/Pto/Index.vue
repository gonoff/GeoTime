<template>
  <AppLayout>
    <template #title>Time Off</template>

    <div class="toolbar">
      <span class="record-count">{{ requests.length }} requests</span>
    </div>

    <div class="panel">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Type</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="requests.length === 0">
              <td colspan="6" class="empty-cell">No time off requests</td>
            </tr>
            <tr v-for="req in requests" :key="req.id">
              <td class="cell-primary">{{ req.employee_name ?? '—' }}</td>
              <td>
                <span class="badge" :class="typeClass(req.type)">{{ req.type }}</span>
              </td>
              <td class="tabular">{{ req.start_date }}</td>
              <td class="tabular">{{ req.end_date }}</td>
              <td>
                <span class="badge" :class="statusClass(req.status)">{{ req.status }}</span>
              </td>
              <td>
                <div v-if="req.status === 'PENDING'" class="action-group">
                  <button class="btn-action btn-action--approve">Approve</button>
                  <button class="btn-action btn-action--deny">Deny</button>
                </div>
                <span v-else class="text-muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const requests = computed(() => page.props.requests ?? []);

function typeClass(type) {
  switch (type) {
    case 'VACATION': return 'badge--zone';
    case 'SICK': return 'badge--halt';
    case 'PERSONAL': return 'badge--flag';
    case 'UNPAID': return 'badge--muted';
    default: return 'badge--muted';
  }
}

function statusClass(status) {
  switch (status) {
    case 'APPROVED': return 'badge--go';
    case 'PENDING': return 'badge--flag';
    case 'DENIED': return 'badge--halt';
    case 'CANCELLED': return 'badge--muted';
    default: return 'badge--muted';
  }
}
</script>

<style scoped>
.toolbar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  margin-bottom: var(--sp-4);
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

.tabular {
  font-variant-numeric: tabular-nums;
}

.empty-cell {
  text-align: center;
  color: var(--chalk-4);
  padding: var(--sp-8) var(--sp-4) !important;
}

.text-muted {
  color: var(--chalk-4);
  font-size: 12px;
}

.action-group {
  display: flex;
  gap: var(--sp-2);
}

.btn-action {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 10px;
  border-radius: var(--radius-md);
  border: 1px solid transparent;
  cursor: pointer;
  transition: all var(--duration) var(--ease);
}

.btn-action--approve {
  color: var(--go);
  background: var(--go-soft);
  border-color: var(--go);
}

.btn-action--approve:hover {
  background: var(--go);
  color: var(--chalk-1);
}

.btn-action--deny {
  color: var(--halt);
  background: var(--halt-soft);
  border-color: var(--halt);
}

.btn-action--deny:hover {
  background: var(--halt);
  color: var(--chalk-1);
}

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
