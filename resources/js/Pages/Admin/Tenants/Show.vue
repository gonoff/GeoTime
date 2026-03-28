<template>
  <AdminLayout>
    <template #title>{{ tenant.name }}</template>

    <!-- Back link -->
    <a class="back-link" href="/admin/tenants" @click.prevent="router.visit('/admin/tenants')">
      <ArrowLeft :size="14" :stroke-width="1.75" />
      <span>Back to Tenants</span>
    </a>

    <!-- Flash messages -->
    <div v-if="$page.props.flash?.success" class="flash flash--success">
      {{ $page.props.flash.success }}
    </div>

    <!-- Tenant Info + Actions -->
    <div class="detail-grid">
      <div class="info-card">
        <h3 class="card-title">Tenant Information</h3>
        <div class="info-rows">
          <div class="info-row">
            <span class="info-label">Name</span>
            <span class="info-value">{{ tenant.name }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Plan</span>
            <span class="info-value capitalize">{{ tenant.plan }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Status</span>
            <span class="status-badge" :class="'status--' + tenant.status">{{ tenant.status }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Timezone</span>
            <span class="info-value">{{ tenant.timezone ?? 'Not set' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Workweek Start</span>
            <span class="info-value capitalize">{{ tenant.workweek_start_day ?? 'Not set' }}</span>
          </div>
          <div v-if="tenant.trial_ends_at" class="info-row">
            <span class="info-label">Trial Ends</span>
            <span class="info-value">{{ tenant.trial_ends_at }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Employees</span>
            <span class="info-value">{{ employeeCount }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Created</span>
            <span class="info-value">{{ tenant.created_at }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Updated</span>
            <span class="info-value">{{ tenant.updated_at }}</span>
          </div>
        </div>

        <!-- Actions -->
        <div class="action-row">
          <button
            v-if="tenant.status !== 'suspended'"
            class="action-btn action-btn--danger"
            @click="suspendTenant"
          >
            Suspend Tenant
          </button>
          <button
            v-if="tenant.status !== 'active'"
            class="action-btn action-btn--success"
            @click="activateTenant"
          >
            Activate Tenant
          </button>
        </div>
      </div>

      <!-- Users List -->
      <div class="info-card">
        <h3 class="card-title">Users ({{ users.length }})</h3>
        <div class="users-list">
          <div v-for="user in users" :key="user.id" class="user-row">
            <div class="user-avatar">{{ user.name.charAt(0).toUpperCase() }}</div>
            <div class="user-info">
              <span class="user-name">{{ user.name }}</span>
              <span class="user-email">{{ user.email }}</span>
            </div>
            <span class="user-role">{{ user.role }}</span>
          </div>
          <div v-if="users.length === 0" class="empty-state">No users</div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeft } from 'lucide-vue-next';

const props = defineProps({
  tenant: Object,
  users: Array,
  employeeCount: Number,
});

function suspendTenant() {
  if (confirm(`Suspend "${props.tenant.name}"? This will block their access.`)) {
    router.post(`/admin/tenants/${props.tenant.id}/suspend`);
  }
}

function activateTenant() {
  router.post(`/admin/tenants/${props.tenant.id}/activate`);
}
</script>

<style scoped>
.back-link {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: 12px;
  color: var(--chalk-3);
  text-decoration: none;
  margin-bottom: var(--sp-5);
  transition: color var(--duration) var(--ease);
}

.back-link:hover {
  color: var(--chalk-1);
}

/* Flash */
.flash {
  padding: var(--sp-3) var(--sp-4);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 500;
  margin-bottom: var(--sp-5);
}

.flash--success {
  background: var(--go-soft);
  color: var(--go);
  border: 1px solid rgba(90, 154, 106, 0.2);
}

/* Grid */
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sp-4);
}

.info-card {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
  padding: var(--sp-5);
}

.card-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
  margin: 0 0 var(--sp-4);
  padding-bottom: var(--sp-3);
  border-bottom: 1px solid var(--seam-1);
}

/* Info rows */
.info-rows {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.info-label {
  font-size: 12px;
  color: var(--chalk-3);
}

.info-value {
  font-size: 13px;
  color: var(--chalk-1);
  font-weight: 500;
}

.capitalize {
  text-transform: capitalize;
}

/* Actions */
.action-row {
  display: flex;
  gap: var(--sp-3);
  margin-top: var(--sp-5);
  padding-top: var(--sp-4);
  border-top: 1px solid var(--seam-1);
}

.action-btn {
  padding: var(--sp-2) var(--sp-4);
  border-radius: var(--radius-md);
  font-size: 12px;
  font-weight: 600;
  font-family: inherit;
  border: none;
  cursor: pointer;
  transition: all var(--duration) var(--ease);
}

.action-btn--danger {
  background: var(--halt-soft);
  color: var(--halt);
}

.action-btn--danger:hover {
  background: var(--halt);
  color: #fff;
}

.action-btn--success {
  background: var(--go-soft);
  color: var(--go);
}

.action-btn--success:hover {
  background: var(--go);
  color: #fff;
}

/* Users list */
.users-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.user-row {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-2) 0;
}

.user-avatar {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-full);
  background: var(--zone-soft);
  color: var(--zone);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
}

.user-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-name {
  font-size: 13px;
  font-weight: 500;
  color: var(--chalk-1);
}

.user-email {
  font-size: 11px;
  color: var(--chalk-3);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-role {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--chalk-3);
  flex-shrink: 0;
}

.empty-state {
  font-size: 13px;
  color: var(--chalk-4);
  text-align: center;
  padding: var(--sp-6) 0;
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
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
