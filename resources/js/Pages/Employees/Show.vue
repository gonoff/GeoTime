<template>
  <AppLayout>
    <template #title>{{ employee.full_name }}</template>

    <!-- Back Link -->
    <div class="back-row">
      <a href="/employees" class="back-link" @click.prevent="router.visit('/employees')">
        <ArrowLeft :size="16" :stroke-width="1.75" />
        <span>Employees</span>
      </a>
    </div>

    <div class="detail-grid">
      <!-- Info Card -->
      <div class="panel">
        <div class="panel-header">
          <h2 class="panel-title">Employee Details</h2>
          <span class="badge" :class="badgeClass(employee.status)">{{ employee.status }}</span>
        </div>
        <div class="panel-body">
          <div class="profile-header">
            <div class="profile-avatar">{{ employee.first_name.charAt(0) }}{{ employee.last_name.charAt(0) }}</div>
            <div class="profile-identity">
              <span class="profile-name">{{ employee.full_name }}</span>
              <span class="profile-role">{{ employee.role }}</span>
            </div>
          </div>

          <div class="field-grid">
            <div class="field">
              <span class="field-label">Email</span>
              <span class="field-value">{{ employee.email }}</span>
            </div>
            <div class="field">
              <span class="field-label">Phone</span>
              <span class="field-value">{{ employee.phone || '--' }}</span>
            </div>
            <div class="field">
              <span class="field-label">Hourly Rate</span>
              <span class="field-value field-value--mono">${{ employee.hourly_rate }}</span>
            </div>
            <div class="field">
              <span class="field-label">Hire Date</span>
              <span class="field-value">{{ employee.hire_date || '--' }}</span>
            </div>
            <div class="field">
              <span class="field-label">Date of Birth</span>
              <span class="field-value">{{ employee.date_of_birth || '--' }}</span>
            </div>
            <div class="field">
              <span class="field-label">Status</span>
              <span class="field-value">{{ employee.status }}</span>
            </div>
          </div>

          <div v-if="employee.address" class="address-section">
            <span class="field-label">Address</span>
            <span class="field-value">
              {{ [employee.address.street, employee.address.city, employee.address.state, employee.address.zip].filter(Boolean).join(', ') || '--' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Team Assignment -->
      <div class="panel">
        <div class="panel-header">
          <h2 class="panel-title">Team Assignment</h2>
        </div>
        <div class="panel-body">
          <div v-if="employee.team" class="team-assignment">
            <div class="team-color-dot" :style="{ background: employee.team.color_tag || '#555' }" />
            <div class="team-detail">
              <span class="team-name">{{ employee.team.name }}</span>
              <span class="team-meta">Currently assigned</span>
            </div>
          </div>
          <div v-else class="empty-state">
            <UsersRound :size="24" :stroke-width="1.5" />
            <span>No team assigned</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Time Entries -->
    <div class="panel" style="margin-top: var(--sp-4)">
      <div class="panel-header">
        <h2 class="panel-title">Recent Time Entries</h2>
        <span class="panel-count">{{ recentTimeEntries.length }}</span>
      </div>
      <div class="panel-body panel-body--flush">
        <table v-if="recentTimeEntries.length > 0" class="data-table">
          <thead>
            <tr>
              <th>Clock In</th>
              <th>Clock Out</th>
              <th>Hours</th>
              <th>Job</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in recentTimeEntries" :key="entry.id">
              <td>{{ entry.clock_in }}</td>
              <td>{{ entry.clock_out || '--' }}</td>
              <td class="text-mono">{{ entry.total_hours ?? '--' }}</td>
              <td class="text-secondary">{{ entry.job_name || '--' }}</td>
              <td>
                <span class="badge" :class="entryBadgeClass(entry.status)">{{ entry.status }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty-state">
          <Clock :size="24" :stroke-width="1.5" />
          <span>No time entries yet</span>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Clock, UsersRound } from 'lucide-vue-next';

const page = usePage();
const employee = page.props.employee;
const recentTimeEntries = page.props.recentTimeEntries ?? [];

function badgeClass(status) {
  const map = {
    ACTIVE: 'badge--active',
    INACTIVE: 'badge--inactive',
    TERMINATED: 'badge--terminated',
  };
  return map[status] || '';
}

function entryBadgeClass(status) {
  const map = {
    ACTIVE: 'badge--active',
    SUBMITTED: 'badge--info',
    APPROVED: 'badge--active',
    REJECTED: 'badge--terminated',
  };
  return map[status] || '';
}
</script>

<style scoped>
/* === Back Row === */
.back-row {
  margin-bottom: var(--sp-4);
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: 13px;
  color: var(--chalk-3);
  text-decoration: none;
  transition: color var(--duration) var(--ease);
}

.back-link:hover {
  color: var(--chalk-1);
}

/* === Detail Grid === */
.detail-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: var(--sp-4);
}

/* === Panel === */
.panel {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid var(--seam-1);
}

.panel-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
  margin: 0;
}

.panel-count {
  font-size: 12px;
  font-weight: 600;
  color: var(--chalk-3);
  background: var(--slab-3);
  padding: 2px 8px;
  border-radius: var(--radius-full);
  font-variant-numeric: tabular-nums;
}

.panel-body {
  padding: var(--sp-4) var(--sp-5);
}

.panel-body--flush {
  padding: 0;
}

/* === Profile Header === */
.profile-header {
  display: flex;
  align-items: center;
  gap: var(--sp-4);
  margin-bottom: var(--sp-5);
  padding-bottom: var(--sp-5);
  border-bottom: 1px solid var(--seam-1);
}

.profile-avatar {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-full);
  background: var(--viz-soft);
  color: var(--viz);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 600;
  flex-shrink: 0;
  letter-spacing: -0.02em;
}

.profile-identity {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.profile-name {
  font-size: 16px;
  font-weight: 600;
  color: var(--chalk-1);
}

.profile-role {
  font-size: 13px;
  color: var(--chalk-3);
}

/* === Field Grid === */
.field-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sp-4);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.field-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--chalk-3);
}

.field-value {
  font-size: 13px;
  color: var(--chalk-1);
}

.field-value--mono {
  font-variant-numeric: tabular-nums;
}

/* === Address === */
.address-section {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: var(--sp-4);
  padding-top: var(--sp-4);
  border-top: 1px solid var(--seam-1);
}

/* === Team Assignment === */
.team-assignment {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}

.team-color-dot {
  width: 12px;
  height: 12px;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.team-detail {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.team-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--chalk-1);
}

.team-meta {
  font-size: 11px;
  color: var(--chalk-3);
}

/* === Data Table === */
.data-table {
  width: 100%;
  border-collapse: collapse;
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
}

.data-table td {
  font-size: 13px;
  color: var(--chalk-1);
  padding: var(--sp-3) var(--sp-4);
  border-bottom: 1px solid var(--seam-1);
}

.data-table tr:hover td {
  background: var(--seam-1);
}

/* === Text Variants === */
.text-secondary {
  color: var(--chalk-2);
}

.text-mono {
  font-variant-numeric: tabular-nums;
}

/* === Badge === */
.badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-full);
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.badge--active {
  background: var(--go-soft);
  color: var(--go);
}

.badge--inactive {
  background: var(--flag-soft);
  color: var(--flag);
}

.badge--terminated {
  background: var(--halt-soft);
  color: var(--halt);
}

.badge--info {
  background: var(--zone-soft);
  color: var(--zone);
}

/* === Empty State === */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-8) 0;
  color: var(--chalk-4);
  font-size: 13px;
}
</style>
