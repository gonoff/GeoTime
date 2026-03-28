<template>
  <AdminLayout>
    <template #title>Platform Dashboard</template>

    <!-- Metrics Cards -->
    <div class="metrics-grid">
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-label">Total Tenants</span>
          <Building2 :size="18" :stroke-width="1.75" class="metric-icon" />
        </div>
        <span class="metric-value">{{ stats.totalTenants }}</span>
      </div>

      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-label">Total Users</span>
          <Users :size="18" :stroke-width="1.75" class="metric-icon" />
        </div>
        <span class="metric-value">{{ stats.totalUsers }}</span>
      </div>

      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-label">Total Employees</span>
          <UsersRound :size="18" :stroke-width="1.75" class="metric-icon" />
        </div>
        <span class="metric-value">{{ stats.totalEmployees }}</span>
      </div>

      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-label">Active Subscriptions</span>
          <CreditCard :size="18" :stroke-width="1.75" class="metric-icon" />
        </div>
        <span class="metric-value">{{ stats.activeSubscriptions }}</span>
      </div>
    </div>

    <!-- Breakdown Cards -->
    <div class="breakdown-row">
      <div class="breakdown-card">
        <h3 class="breakdown-title">Tenants by Plan</h3>
        <div class="breakdown-list">
          <div v-for="(count, plan) in tenantsByPlan" :key="plan" class="breakdown-item">
            <span class="breakdown-label">{{ plan }}</span>
            <span class="breakdown-count">{{ count }}</span>
          </div>
          <div v-if="Object.keys(tenantsByPlan).length === 0" class="breakdown-empty">No data</div>
        </div>
      </div>

      <div class="breakdown-card">
        <h3 class="breakdown-title">Tenants by Status</h3>
        <div class="breakdown-list">
          <div v-for="(count, status) in tenantsByStatus" :key="status" class="breakdown-item">
            <span class="status-badge" :class="'status--' + status">{{ status }}</span>
            <span class="breakdown-count">{{ count }}</span>
          </div>
          <div v-if="Object.keys(tenantsByStatus).length === 0" class="breakdown-empty">No data</div>
        </div>
      </div>
    </div>

    <!-- Recent Tenants -->
    <div class="section">
      <h3 class="section-title">Recent Signups</h3>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Plan</th>
              <th>Status</th>
              <th>Users</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tenant in recentTenants" :key="tenant.id">
              <td>
                <a class="tenant-link" :href="'/admin/tenants/' + tenant.id" @click.prevent="router.visit('/admin/tenants/' + tenant.id)">
                  {{ tenant.name }}
                </a>
              </td>
              <td class="cell-muted">{{ tenant.plan }}</td>
              <td><span class="status-badge" :class="'status--' + tenant.status">{{ tenant.status }}</span></td>
              <td class="cell-muted">{{ tenant.users_count }}</td>
              <td class="cell-muted">{{ tenant.created_at }}</td>
            </tr>
            <tr v-if="recentTenants.length === 0">
              <td colspan="5" class="cell-empty">No tenants yet</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Building2, Users, UsersRound, CreditCard } from 'lucide-vue-next';

defineProps({
  stats: Object,
  tenantsByPlan: Object,
  tenantsByStatus: Object,
  recentTenants: Array,
});
</script>

<style scoped>
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--sp-4);
  margin-bottom: var(--sp-6);
}

.metric-card {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
  padding: var(--sp-5);
}

.metric-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--sp-3);
}

.metric-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--chalk-3);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.metric-icon {
  color: var(--zone);
}

.metric-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--chalk-1);
  letter-spacing: -0.02em;
}

/* Breakdown */
.breakdown-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sp-4);
  margin-bottom: var(--sp-6);
}

.breakdown-card {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
  padding: var(--sp-5);
}

.breakdown-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
  margin: 0 0 var(--sp-4);
}

.breakdown-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.breakdown-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.breakdown-label {
  font-size: 13px;
  color: var(--chalk-2);
  text-transform: capitalize;
}

.breakdown-count {
  font-size: 14px;
  font-weight: 600;
  color: var(--chalk-1);
}

.breakdown-empty {
  font-size: 13px;
  color: var(--chalk-4);
}

/* Section */
.section {
  margin-bottom: var(--sp-6);
}

.section-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
  margin: 0 0 var(--sp-4);
}

/* Table */
.table-wrap {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
  overflow: hidden;
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
  border-bottom: 1px solid var(--seam-1);
  background: var(--slab-3);
}

.data-table td {
  padding: var(--sp-3) var(--sp-4);
  border-bottom: 1px solid var(--seam-1);
  color: var(--chalk-1);
}

.data-table tr:last-child td {
  border-bottom: none;
}

.data-table tr:hover td {
  background: var(--seam-1);
}

.cell-muted {
  color: var(--chalk-2);
}

.cell-empty {
  text-align: center;
  color: var(--chalk-4);
  padding: var(--sp-8) var(--sp-4) !important;
}

.tenant-link {
  color: var(--zone);
  text-decoration: none;
  font-weight: 500;
}

.tenant-link:hover {
  text-decoration: underline;
}

/* Status badges */
.status-badge {
  display: inline-block;
  padding: 2px var(--sp-2);
  border-radius: var(--radius-sm);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.status--trial {
  background: var(--zone-soft);
  color: var(--zone);
}

.status--active {
  background: var(--go-soft);
  color: var(--go);
}

.status--past_due {
  background: var(--flag-soft);
  color: var(--flag);
}

.status--cancelled {
  background: var(--halt-soft);
  color: var(--halt);
}

.status--suspended {
  background: var(--halt-soft);
  color: var(--halt);
}

@media (max-width: 900px) {
  .metrics-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .breakdown-row {
    grid-template-columns: 1fr;
  }
}
</style>
