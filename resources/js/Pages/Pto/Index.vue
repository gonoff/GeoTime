<template>
  <AppLayout>
    <template #title>Time Off</template>

    <div class="toolbar">
      <span class="record-count">{{ requests.length }} requests</span>
      <button
        v-if="canManage"
        class="btn btn--primary"
        @click="openCreateModal"
      >
        New Request
      </button>
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
              <th>Hours</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="requests.length === 0">
              <td colspan="7" class="empty-cell">No time off requests</td>
            </tr>
            <tr v-for="req in requests" :key="req.id">
              <td class="cell-primary">{{ req.employee_name ?? '—' }}</td>
              <td>
                <span class="badge" :class="typeClass(req.type)">{{ req.type }}</span>
              </td>
              <td class="tabular">{{ req.start_date }}</td>
              <td class="tabular">{{ req.end_date }}</td>
              <td class="tabular">{{ req.hours }}h</td>
              <td>
                <span class="badge" :class="statusClass(req.status)">{{ req.status }}</span>
              </td>
              <td>
                <div v-if="req.status === 'PENDING' && canManage" class="action-group">
                  <button class="btn-action btn-action--approve" @click="promptApprove(req)">Approve</button>
                  <button class="btn-action btn-action--deny" @click="promptDeny(req)">Deny</button>
                </div>
                <span v-else class="text-muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create PTO Request Modal -->
    <FormModal
      :show="showCreateModal"
      title="New PTO Request"
      max-width="md"
      :loading="form.processing"
      @close="closeCreateModal"
      @submit="submitCreate"
    >
      <div class="form-group">
        <label class="form-label">Employee</label>
        <select
          v-model="form.employee_id"
          class="form-select"
          @change="fetchBalance"
        >
          <option value="">Select employee...</option>
          <option v-for="emp in employees" :key="emp.id" :value="emp.id">
            {{ emp.full_name }}
          </option>
        </select>
        <span v-if="form.errors.employee_id" class="form-error">{{ form.errors.employee_id }}</span>
      </div>

      <!-- Balance info box -->
      <div v-if="balanceInfo && form.employee_id" class="balance-box">
        <div class="balance-box__title">PTO Balance ({{ currentYear }})</div>
        <div class="balance-grid">
          <div v-for="(bal, type) in balanceInfo" :key="type" class="balance-item">
            <span class="balance-type">{{ type }}</span>
            <span class="balance-hours">{{ bal.remaining }}h remaining</span>
            <span class="balance-detail">{{ bal.used }}h used of {{ bal.accrued }}h</span>
          </div>
          <div v-if="Object.keys(balanceInfo).length === 0" class="balance-empty">
            No balance records found for this year.
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Type</label>
        <select v-model="form.type" class="form-select">
          <option value="">Select type...</option>
          <option value="VACATION">VACATION</option>
          <option value="SICK">SICK</option>
          <option value="PERSONAL">PERSONAL</option>
          <option value="UNPAID">UNPAID</option>
        </select>
        <span v-if="form.errors.type" class="form-error">{{ form.errors.type }}</span>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Start Date</label>
          <input v-model="form.start_date" type="date" class="form-input" />
          <span v-if="form.errors.start_date" class="form-error">{{ form.errors.start_date }}</span>
        </div>
        <div class="form-group">
          <label class="form-label">End Date</label>
          <input v-model="form.end_date" type="date" class="form-input" />
          <span v-if="form.errors.end_date" class="form-error">{{ form.errors.end_date }}</span>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Hours</label>
        <input v-model="form.hours" type="number" class="form-input" min="0.5" step="0.5" placeholder="e.g. 8" />
        <span v-if="form.errors.hours" class="form-error">{{ form.errors.hours }}</span>
      </div>

      <div class="form-group">
        <label class="form-label">Notes (optional)</label>
        <textarea v-model="form.notes" class="form-input form-textarea" rows="3" placeholder="Any additional notes..."></textarea>
        <span v-if="form.errors.notes" class="form-error">{{ form.errors.notes }}</span>
      </div>
    </FormModal>

    <!-- Approve Confirm Dialog -->
    <ConfirmDialog
      :show="showApproveDialog"
      title="Approve PTO Request"
      :message="`Approve the ${pendingRequest?.type} request for ${pendingRequest?.employee_name}?`"
      confirm-label="Approve"
      confirm-color="green"
      @close="showApproveDialog = false"
      @confirm="submitApprove"
    />

    <!-- Deny Confirm Dialog -->
    <ConfirmDialog
      :show="showDenyDialog"
      title="Deny PTO Request"
      :message="`Deny the ${pendingRequest?.type} request for ${pendingRequest?.employee_name}?`"
      confirm-label="Deny"
      confirm-color="red"
      :destructive="true"
      @close="showDenyDialog = false"
      @confirm="submitDeny"
    />
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormModal from '@/Components/FormModal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

const page = usePage();
const requests = computed(() => page.props.requests ?? []);
const employees = computed(() => page.props.employees ?? []);

const currentYear = new Date().getFullYear();

const canManage = computed(() => {
  const role = page.props.auth?.user?.role;
  return ['admin', 'super_admin', 'manager', 'team_lead'].includes(role);
});

// Create modal
const showCreateModal = ref(false);
const balanceInfo = ref(null);

const form = useForm({
  employee_id: '',
  type: '',
  start_date: '',
  end_date: '',
  hours: '',
  notes: '',
});

function openCreateModal() {
  form.reset();
  balanceInfo.value = null;
  showCreateModal.value = true;
}

function closeCreateModal() {
  showCreateModal.value = false;
  balanceInfo.value = null;
}

async function fetchBalance() {
  if (!form.employee_id) {
    balanceInfo.value = null;
    return;
  }
  try {
    const res = await fetch(`/pto/balance/${form.employee_id}`);
    balanceInfo.value = await res.json();
  } catch {
    balanceInfo.value = null;
  }
}

function submitCreate() {
  form.post('/pto', {
    onSuccess: () => closeCreateModal(),
  });
}

// Approve / Deny
const showApproveDialog = ref(false);
const showDenyDialog = ref(false);
const pendingRequest = ref(null);

function promptApprove(req) {
  pendingRequest.value = req;
  showApproveDialog.value = true;
}

function promptDeny(req) {
  pendingRequest.value = req;
  showDenyDialog.value = true;
}

function submitApprove() {
  showApproveDialog.value = false;
  router.post(`/pto/${pendingRequest.value.id}/approve`);
}

function submitDeny() {
  showDenyDialog.value = false;
  router.post(`/pto/${pendingRequest.value.id}/deny`);
}

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
  justify-content: space-between;
  margin-bottom: var(--sp-4);
}

.record-count {
  font-size: 12px;
  color: var(--chalk-3);
  font-variant-numeric: tabular-nums;
}

.btn {
  font-size: 13px;
  font-weight: 600;
  padding: var(--sp-2) var(--sp-4);
  border-radius: var(--radius-md);
  border: 1px solid transparent;
  cursor: pointer;
  transition: all var(--duration) var(--ease);
}

.btn--primary {
  background: var(--viz);
  color: #fff;
  border-color: var(--viz);
}

.btn--primary:hover {
  opacity: 0.9;
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

/* Form styles */
.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
  margin-bottom: var(--sp-3);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sp-3);
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--chalk-3);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.form-input,
.form-select {
  background: var(--slab-1);
  border: 1px solid var(--seam-2);
  border-radius: var(--radius-md);
  padding: var(--sp-2) var(--sp-3);
  color: var(--chalk-1);
  font-size: 13px;
  width: 100%;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: var(--viz);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.form-error {
  font-size: 11px;
  color: var(--halt);
}

/* Balance info box */
.balance-box {
  background: var(--slab-3);
  border: 1px solid var(--seam-2);
  border-radius: var(--radius-md);
  padding: var(--sp-3);
  margin-bottom: var(--sp-3);
}

.balance-box__title {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--chalk-3);
  margin-bottom: var(--sp-2);
}

.balance-grid {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.balance-item {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  font-size: 12px;
}

.balance-type {
  font-weight: 600;
  color: var(--chalk-2);
  min-width: 80px;
}

.balance-hours {
  color: var(--go);
  font-weight: 600;
}

.balance-detail {
  color: var(--chalk-4);
}

.balance-empty {
  font-size: 12px;
  color: var(--chalk-4);
}
</style>
