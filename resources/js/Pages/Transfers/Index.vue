<template>
  <AppLayout>
    <template #title>Transfers</template>

    <div class="toolbar">
      <span class="record-count">{{ transfers.length }} transfers</span>
      <button v-if="canManage" class="btn btn--primary" @click="openCreate">
        <Plus :size="15" />
        New Transfer
      </button>
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
              <th v-if="canApprove">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="transfers.length === 0">
              <td :colspan="canApprove ? 8 : 7" class="empty-cell">No transfers found</td>
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
              <td v-if="canApprove">
                <div v-if="transfer.status === 'PENDING'" class="action-btns">
                  <button class="btn-sm btn-sm--green" @click="openApprove(transfer)">
                    <Check :size="13" /> Approve
                  </button>
                  <button class="btn-sm btn-sm--red" @click="openReject(transfer)">
                    <X :size="13" /> Reject
                  </button>
                </div>
                <span v-else class="action-none">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- New Transfer Modal -->
    <FormModal
      :show="showForm"
      title="New Transfer Request"
      max-width="lg"
      :loading="form.processing"
      @close="closeForm"
      @submit="submitForm"
    >
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Employee *</label>
          <select v-model="form.employee_id" class="form-select" @change="onEmployeeChange">
            <option value="">— Select employee —</option>
            <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.full_name }}</option>
          </select>
          <span v-if="form.errors.employee_id" class="form-error">{{ form.errors.employee_id }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">From Team</label>
          <div class="form-static">{{ selectedEmployee?.current_team_name ?? '— none —' }}</div>
        </div>

        <div class="form-group">
          <label class="form-label">To Team *</label>
          <select v-model="form.to_team_id" class="form-select">
            <option value="">— Select team —</option>
            <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
          </select>
          <span v-if="form.errors.to_team_id" class="form-error">{{ form.errors.to_team_id }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Transfer Type *</label>
          <div class="radio-group">
            <label class="radio-label">
              <input type="radio" v-model="form.transfer_type" value="PERMANENT" />
              Permanent
            </label>
            <label class="radio-label">
              <input type="radio" v-model="form.transfer_type" value="TEMPORARY" />
              Temporary
            </label>
          </div>
          <span v-if="form.errors.transfer_type" class="form-error">{{ form.errors.transfer_type }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Reason Category *</label>
          <select v-model="form.reason_category" class="form-select">
            <option value="">— Select category —</option>
            <option v-for="cat in reasonCategories" :key="cat" :value="cat">{{ formatReason(cat) }}</option>
          </select>
          <span v-if="form.errors.reason_category" class="form-error">{{ form.errors.reason_category }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Reason Code *</label>
          <select v-model="form.reason_code" class="form-select">
            <option value="">— Select code —</option>
            <option v-for="code in reasonCodes" :key="code" :value="code">{{ formatReason(code) }}</option>
          </select>
          <span v-if="form.errors.reason_code" class="form-error">{{ form.errors.reason_code }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Effective Date *</label>
          <input v-model="form.effective_date" type="date" class="form-input" />
          <span v-if="form.errors.effective_date" class="form-error">{{ form.errors.effective_date }}</span>
        </div>

        <div v-if="form.transfer_type === 'TEMPORARY'" class="form-group">
          <label class="form-label">Expected Return Date *</label>
          <input v-model="form.expected_return_date" type="date" class="form-input" />
          <span v-if="form.errors.expected_return_date" class="form-error">{{ form.errors.expected_return_date }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="form.notes" class="form-input form-textarea" placeholder="Optional notes" />
          <span v-if="form.errors.notes" class="form-error">{{ form.errors.notes }}</span>
        </div>
      </div>
    </FormModal>

    <!-- Approve Confirm -->
    <ConfirmDialog
      :show="showApprove"
      title="Approve Transfer"
      :message="`Approve and execute transfer for ${actionTarget?.employee_name ?? 'this employee'}? The employee will be moved to ${actionTarget?.to_team ?? 'the new team'} immediately.`"
      confirm-label="Approve"
      confirm-color="green"
      @close="showApprove = false"
      @confirm="confirmApprove"
    />

    <!-- Reject Confirm -->
    <ConfirmDialog
      :show="showReject"
      title="Reject Transfer"
      :message="`Reject the transfer request for ${actionTarget?.employee_name ?? 'this employee'}?`"
      confirm-label="Reject"
      confirm-color="red"
      :destructive="true"
      @close="showReject = false"
      @confirm="confirmReject"
    />
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormModal from '@/Components/FormModal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Plus, Check, X } from 'lucide-vue-next';

const page = usePage();
const transfers = computed(() => page.props.transfers ?? []);
const employees = computed(() => page.props.employees ?? []);
const teams = computed(() => page.props.teams ?? []);
const reasonCategories = computed(() => page.props.reason_categories ?? []);
const reasonCodes = computed(() => page.props.reason_codes ?? []);

const userRole = computed(() => page.props.auth?.user?.role);
const canManage = computed(() => ['admin', 'super_admin', 'manager', 'team_lead'].includes(userRole.value));
const canApprove = computed(() => ['admin', 'super_admin', 'manager'].includes(userRole.value));

// Form state
const showForm = ref(false);
const selectedEmployee = ref(null);

const form = useForm({
  employee_id: '',
  from_team_id: '',
  to_team_id: '',
  transfer_type: 'PERMANENT',
  reason_category: '',
  reason_code: '',
  effective_date: '',
  expected_return_date: '',
  notes: '',
});

function openCreate() {
  form.reset();
  form.transfer_type = 'PERMANENT';
  selectedEmployee.value = null;
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
  form.reset();
  selectedEmployee.value = null;
}

function onEmployeeChange() {
  const emp = employees.value.find(e => e.id === form.employee_id);
  selectedEmployee.value = emp ?? null;
  form.from_team_id = emp?.current_team_id ?? '';
}

function submitForm() {
  form.post('/transfers', {
    onSuccess: () => closeForm(),
  });
}

// Approve / Reject
const showApprove = ref(false);
const showReject = ref(false);
const actionTarget = ref(null);

function openApprove(transfer) {
  actionTarget.value = transfer;
  showApprove.value = true;
}

function openReject(transfer) {
  actionTarget.value = transfer;
  showReject.value = true;
}

function confirmApprove() {
  router.post(`/transfers/${actionTarget.value.id}/approve`, {}, {
    onSuccess: () => { showApprove.value = false; actionTarget.value = null; },
  });
}

function confirmReject() {
  router.post(`/transfers/${actionTarget.value.id}/reject`, {}, {
    onSuccess: () => { showReject.value = false; actionTarget.value = null; },
  });
}

// Formatting helpers
function formatReason(category) {
  if (!category) return '—';
  return category.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()).toLowerCase().replace(/^\w/, c => c.toUpperCase());
}

function transferStatusClass(status) {
  switch (status) {
    case 'APPROVED': return 'badge--go';
    case 'COMPLETED': return 'badge--go';
    case 'PENDING': return 'badge--flag';
    case 'REJECTED': return 'badge--halt';
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
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  padding: var(--sp-2) var(--sp-4);
  border-radius: var(--radius-md);
  border: none;
  cursor: pointer;
  transition: opacity 0.15s;
}

.btn--primary {
  background: var(--viz);
  color: #fff;
}

.btn--primary:hover {
  opacity: 0.9;
}

.btn-sm {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 500;
  font-family: inherit;
  padding: 3px 10px;
  border-radius: var(--radius-md);
  border: none;
  cursor: pointer;
  transition: opacity 0.15s;
}

.btn-sm--green {
  background: var(--go-soft);
  color: var(--go);
}

.btn-sm--green:hover {
  opacity: 0.8;
}

.btn-sm--red {
  background: var(--halt-soft);
  color: var(--halt);
}

.btn-sm--red:hover {
  opacity: 0.8;
}

.action-btns {
  display: flex;
  gap: var(--sp-2);
}

.action-none {
  color: var(--chalk-4);
  font-size: 13px;
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

/* Form styles */
.form-grid {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.form-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--chalk-3);
}

.form-input,
.form-select {
  background: var(--slab-3);
  border: 1px solid var(--seam-2);
  border-radius: var(--radius-md);
  color: var(--chalk-1);
  font-size: 13px;
  padding: var(--sp-2) var(--sp-3);
  font-family: inherit;
  width: 100%;
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
}

.form-input:focus,
.form-select:focus {
  border-color: var(--viz);
}

.form-textarea {
  resize: vertical;
  min-height: 72px;
}

.form-select {
  appearance: none;
  cursor: pointer;
}

.form-static {
  font-size: 13px;
  color: var(--chalk-2);
  padding: var(--sp-2) var(--sp-3);
  background: var(--slab-3);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-md);
  opacity: 0.7;
}

.form-error {
  font-size: 12px;
  color: var(--halt);
}

.radio-group {
  display: flex;
  gap: var(--sp-5);
}

.radio-label {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: 13px;
  color: var(--chalk-2);
  cursor: pointer;
}
</style>
