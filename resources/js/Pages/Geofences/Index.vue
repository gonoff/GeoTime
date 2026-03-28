<template>
  <AppLayout>
    <template #title>Geofences</template>

    <div class="toolbar">
      <span class="record-count">{{ geofences.length }} geofences</span>
    </div>

    <div class="panel">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Job Site</th>
              <th class="col-right">Lat</th>
              <th class="col-right">Lng</th>
              <th class="col-right">Radius (m)</th>
              <th>Active</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="geofences.length === 0">
              <td colspan="6" class="empty-cell">No geofences found</td>
            </tr>
            <tr v-for="geofence in geofences" :key="geofence.id">
              <td class="cell-primary">{{ geofence.name }}</td>
              <td>{{ geofence.job_name ?? '—' }}</td>
              <td class="col-right tabular">{{ geofence.latitude }}</td>
              <td class="col-right tabular">{{ geofence.longitude }}</td>
              <td class="col-right tabular">{{ geofence.radius_meters }}</td>
              <td>
                <span class="badge" :class="geofence.is_active ? 'badge--go' : 'badge--muted'">
                  {{ geofence.is_active ? 'Active' : 'Inactive' }}
                </span>
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
const geofences = computed(() => page.props.geofences ?? []);
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

.badge--muted {
  color: var(--chalk-3);
  background: var(--slab-3);
}
</style>
