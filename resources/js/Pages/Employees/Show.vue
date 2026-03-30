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
          <div class="panel-header-actions">
            <span class="badge" :class="badgeClass(employee.status)">{{ employee.status }}</span>
            <button v-if="canManage" class="icon-btn" title="Edit" @click="openEdit">
              <Pencil :size="14" />
            </button>
          </div>
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

    <!-- Actions -->
    <div v-if="canManage && employee.status === 'ACTIVE'" class="action-bar">
      <button class="btn btn--warning" @click="showTerminate = true">
        Terminate Employee
      </button>
      <button v-if="isAdmin" class="btn btn--danger" @click="showDelete = true">
        Delete Permanently
      </button>
    </div>

    <!-- Edit Modal -->
    <FormModal
      :show="showForm"
      title="Edit Employee"
      :loading="form.processing"
      @close="closeForm"
      @submit="submitForm"
    >
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name *</label>
          <input class="form-input" type="text" v-model="form.first_name" />
          <span v-if="form.errors.first_name" class="form-error">{{ form.errors.first_name }}</span>
        </div>
        <div class="form-group">
          <label class="form-label">Last Name *</label>
          <input class="form-input" type="text" v-model="form.last_name" />
          <span v-if="form.errors.last_name" class="form-error">{{ form.errors.last_name }}</span>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input class="form-input" type="email" v-model="form.email" />
          <span v-if="form.errors.email" class="form-error">{{ form.errors.email }}</span>
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input class="form-input" type="text" v-model="form.phone" />
          <span v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</span>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Hourly Rate *</label>
          <input class="form-input" type="number" v-model.number="form.hourly_rate" min="0" step="0.50" />
          <span v-if="form.errors.hourly_rate" class="form-error">{{ form.errors.hourly_rate }}</span>
        </div>
        <div class="form-group">
          <label class="form-label">Hire Date *</label>
          <input class="form-input" type="date" v-model="form.hire_date" />
          <span v-if="form.errors.hire_date" class="form-error">{{ form.errors.hire_date }}</span>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Date of Birth</label>
        <input class="form-input" type="date" v-model="form.date_of_birth" />
      </div>

      <fieldset class="form-fieldset">
        <legend class="form-legend">Address</legend>
        <div class="form-group">
          <label class="form-label">Street</label>
          <input class="form-input" type="text" v-model="form.address.street" />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">City</label>
            <input class="form-input" type="text" v-model="form.address.city" />
          </div>
          <div class="form-group" style="max-width: 80px;">
            <label class="form-label">State</label>
            <input class="form-input" type="text" v-model="form.address.state" maxlength="2" />
          </div>
          <div class="form-group" style="max-width: 100px;">
            <label class="form-label">ZIP</label>
            <input class="form-input" type="text" v-model="form.address.zip" maxlength="10" />
          </div>
        </div>
      </fieldset>
    </FormModal>

    <!-- Terminate Dialog -->
    <ConfirmDialog
      :show="showTerminate"
      title="Terminate Employee"
      :message="`Terminate ${employee.full_name}? They will no longer be able to clock in.`"
      confirmLabel="Terminate"
      confirmColor="red"
      :destructive="true"
      @close="showTerminate = false"
      @confirm="submitTerminate"
    />

    <!-- Delete Dialog -->
    <ConfirmDialog
      :show="showDelete"
      title="Delete Employee"
      :message="`Permanently delete ${employee.full_name}? This cannot be undone.`"
      confirmLabel="Delete"
      confirmColor="red"
      :destructive="true"
      @close="showDelete = false"
      @confirm="submitDelete"
    />

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
import { ref, computed } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormModal from '@/Components/FormModal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ArrowLeft, Clock, UsersRound, Pencil } from 'lucide-vue-next';

const page = usePage();
const employee = page.props.employee;
const teams = computed(() => page.props.teams ?? []);
const recentTimeEntries = page.props.recentTimeEntries ?? [];

const userRole = computed(() => page.props.auth?.user?.role);
const isAdmin = computed(() => ['admin', 'super_admin'].includes(userRole.value));
const canManage = computed(() => ['admin', 'super_admin', 'manager', 'team_lead'].includes(userRole.value));

// Edit form
const showForm = ref(false);
const form = useForm({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  hourly_rate: null,
  hire_date: '',
  date_of_birth: '',
  address: { street: '', city: '', state: '', zip: '' },
});

function openEdit() {
  form.first_name = employee.first_name;
  form.last_name = employee.last_name;
  form.email = employee.email;
  form.phone = employee.phone ?? '';
  form.hourly_rate = employee.hourly_rate;
  form.hire_date = employee.hire_date_raw ?? '';
  form.date_of_birth = employee.date_of_birth_raw ?? '';
  form.address = employee.address ?? { street: '', city: '', state: '', zip: '' };
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
  form.clearErrors();
}

function submitForm() {
  form.put('/employees/' + employee.id, {
    onSuccess: () => closeForm(),
  });
}

// Terminate
const showTerminate = ref(false);
function submitTerminate() {
  router.post('/employees/' + employee.id + '/terminate', {}, {
    onSuccess: () => { showTerminate.value = false; },
  });
}

// Delete
const showDelete = ref(false);
function submitDelete() {
  router.delete('/employees/' + employee.id, {
    onSuccess: () => { showDelete.value = false; },
  });
}

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

/* === Panel Header Actions === */
.panel-header-actions {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

/* === Action Bar === */
.action-bar {
  display: flex;
  gap: var(--sp-3);
  margin-top: var(--sp-4);
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-2) var(--sp-4);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  border: none;
  transition: opacity 0.15s;
}

.btn--warning {
  background: var(--flag-soft);
  color: var(--flag);
  border: 1px solid var(--flag);
}

.btn--warning:hover {
  background: var(--flag);
  color: #fff;
}

.btn--danger {
  background: var(--halt-soft);
  color: var(--halt);
  border: 1px solid var(--halt);
}

.btn--danger:hover {
  background: var(--halt);
  color: #fff;
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: var(--radius-md);
  border: none;
  background: transparent;
  color: var(--chalk-3);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.icon-btn:hover {
  background: var(--slab-3);
  color: var(--chalk-1);
}

/* === Form Styles === */
.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
  margin-bottom: var(--sp-4);
}

.form-row {
  display: flex;
  gap: var(--sp-4);
}

.form-row .form-group {
  flex: 1;
}

.form-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--chalk-3);
}

.form-error {
  font-size: 11px;
  color: var(--halt);
}

.form-input,
.form-select {
  background: var(--pit);
  border: 1px solid var(--pit-border);
  border-radius: var(--radius-md);
  color: var(--chalk-2);
  font-size: 13px;
  padding: var(--sp-2) var(--sp-3);
  font-family: inherit;
  width: 100%;
  box-sizing: border-box;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: var(--viz);
}

.form-fieldset {
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-md);
  padding: var(--sp-4);
  margin: 0 0 var(--sp-4) 0;
}

.form-legend {
  font-size: 12px;
  font-weight: 600;
  color: var(--chalk-3);
  padding: 0 var(--sp-2);
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
