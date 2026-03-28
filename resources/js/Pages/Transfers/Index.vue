<template>
  <AppLayout>
    <template #title>Transfers</template>

    <div class="toolbar">
      <span class="record-count">{{ transfers.length }} transfers</span>
    </div>

    <div class="panel">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>From Team</th>
              <th>To Team</th>
              <th>Reason</th>
              <th>Type</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="transfers.length === 0">
              <td colspan="7" class="empty-cell">No transfers found</td>
            </tr>
            <tr v-for="transfer in transfers" :key="transfer.id">
              <td class="cell-primary">{{ transfer.employee_name ?? '—' }}</td>
              <td>{{ transfer.from_team ?? '—' }}</td>
              <td>{{ transfer.to_team ?? '—' }}</td>
              <td>
                <span class="reason-text">{{ formatReason(transfer.reason_category) }}</span>
              </td>
              <td>
                <span class="badge" :class="transfer.transfer_type === 'PERMANENT' ? 'badge--zone' : 'badge--flag'">
                  {{ transfer.transfer_type }}
                </span>
              </td>
              <td>
                <span class="badge" :class="transferStatusClass(transfer.status)">{{ transfer.status }}</span>
              </td>
              <td class="tabular">{{ transfer.effective_date ?? '—' }}</td>
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
const transfers = computed(() => page.props.transfers ?? []);

function formatReason(category) {
  if (!category) return '—';
  return category.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()).toLowerCase().replace(/^\w/, c => c.toUpperCase());
}

function transferStatusClass(status) {
  switch (status) {
    case 'APPROVED': return 'badge--go';
    case 'PENDING': return 'badge--flag';
    case 'REJECTED': return 'badge--halt';
    case 'COMPLETED': return 'badge--zone';
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

.reason-text {
  font-size: 12px;
  color: var(--chalk-2);
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
