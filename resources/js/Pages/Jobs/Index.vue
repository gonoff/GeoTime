<template>
  <AppLayout>
    <template #title>Job Sites</template>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="filter-group">
          <label class="filter-label">Status</label>
          <select class="filter-select" :value="filters.status ?? ''" @change="filterByStatus($event.target.value)">
            <option value="">All</option>
            <option value="ACTIVE">Active</option>
            <option value="ON_HOLD">On Hold</option>
            <option value="COMPLETED">Completed</option>
          </select>
        </div>
        <span class="record-count">{{ jobs.length }} job sites</span>
      </div>
      <button
        v-if="canManage"
        class="btn btn--primary"
        @click="openCreate"
      >
        <Plus :size="14" />
        Add Job
      </button>
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
              <th v-if="canManage" class="col-actions"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="jobs.length === 0">
              <td :colspan="canManage ? 7 : 6" class="empty-cell">No job sites found</td>
            </tr>
            <tr v-for="job in jobs" :key="job.id">
              <td class="cell-primary">{{ job.name }}</td>
              <td>{{ job.client_name ?? '—' }}</td>
              <td>
                <span class="badge" :class="statusClass(job.status)">{{ statusLabel(job.status) }}</span>
              </td>
              <td class="col-right tabular">{{ job.budget_hours ?? '—' }}</td>
              <td class="col-right tabular">{{ job.hourly_rate ? '$' + Number(job.hourly_rate).toFixed(2) : '—' }}</td>
              <td class="col-right tabular">{{ job.geofences_count }}</td>
              <td v-if="canManage" class="col-actions">
                <div class="row-actions">
                  <button class="icon-btn" title="Edit" @click="openEdit(job)">
                    <Pencil :size="14" />
                  </button>
                  <button
                    v-if="job.status === 'ACTIVE'"
                    class="icon-btn icon-btn--go"
                    title="Mark Complete"
                    @click="openComplete(job)"
                  >
                    <CheckCircle :size="14" />
                  </button>
                  <button
                    v-if="isAdmin"
                    class="icon-btn icon-btn--halt"
                    title="Delete"
                    @click="openDelete(job)"
                  >
                    <Trash2 :size="14" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit FormModal -->
    <FormModal
      :show="showForm"
      :title="editingJob ? 'Edit Job Site' : 'Add Job Site'"
      :loading="form.processing"
      @close="closeForm"
      @submit="submitForm"
    >
      <div class="form-group">
        <label class="form-label" for="job-name">Name *</label>
        <input id="job-name" class="form-input" type="text" v-model="form.name" />
        <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="job-client">Client Name</label>
        <input id="job-client" class="form-input" type="text" v-model="form.client_name" />
        <span v-if="form.errors.client_name" class="form-error">{{ form.errors.client_name }}</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="job-address">Address</label>
        <input id="job-address" class="form-input" type="text" v-model="form.address" />
        <span v-if="form.errors.address" class="form-error">{{ form.errors.address }}</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="job-status">Status</label>
        <select id="job-status" class="form-select" v-model="form.status">
          <option value="ACTIVE">Active</option>
          <option value="ON_HOLD">On Hold</option>
        </select>
        <span v-if="form.errors.status" class="form-error">{{ form.errors.status }}</span>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="job-budget">Budget Hours</label>
          <input id="job-budget" class="form-input" type="number" v-model.number="form.budget_hours" min="0" step="0.5" />
          <span v-if="form.errors.budget_hours" class="form-error">{{ form.errors.budget_hours }}</span>
        </div>
        <div class="form-group">
          <label class="form-label" for="job-rate">Hourly Rate ($)</label>
          <input id="job-rate" class="form-input" type="number" v-model.number="form.hourly_rate" min="0" step="0.01" />
          <span v-if="form.errors.hourly_rate" class="form-error">{{ form.errors.hourly_rate }}</span>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="job-start">Start Date</label>
          <input id="job-start" class="form-input" type="date" v-model="form.start_date" />
          <span v-if="form.errors.start_date" class="form-error">{{ form.errors.start_date }}</span>
        </div>
        <div class="form-group">
          <label class="form-label" for="job-end">End Date</label>
          <input id="job-end" class="form-input" type="date" v-model="form.end_date" />
          <span v-if="form.errors.end_date" class="form-error">{{ form.errors.end_date }}</span>
        </div>
      </div>
    </FormModal>

    <!-- Mark Complete ConfirmDialog -->
    <ConfirmDialog
      :show="showComplete"
      title="Mark Job Complete"
      :message="`Mark &quot;${completingJob?.name}&quot; as completed? This cannot be undone.`"
      confirmLabel="Mark Complete"
      confirmColor="green"
      @close="showComplete = false; completingJob = null"
      @confirm="submitComplete"
    />

    <!-- Delete Dialog -->
    <DeleteDialog
      :show="showDelete"
      :entityName="deletingJob?.name ?? ''"
      entityType="job"
      :canArchive="false"
      @close="showDelete = false; deletingJob = null"
      @delete="submitDelete"
    />
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, CheckCircle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormModal from '@/Components/FormModal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import DeleteDialog from '@/Components/DeleteDialog.vue';

const page = usePage();
const jobs = computed(() => page.props.jobs ?? []);
const filters = computed(() => page.props.filters ?? {});

const userRole = computed(() => page.props.auth?.user?.role);
const isAdmin = computed(() => ['admin', 'super_admin'].includes(userRole.value));
const canManage = computed(() => ['admin', 'super_admin', 'manager', 'team_lead'].includes(userRole.value));

// Form modal state
const showForm = ref(false);
const editingJob = ref(null);

const form = useForm({
  name: '',
  client_name: '',
  address: '',
  status: 'ACTIVE',
  budget_hours: null,
  hourly_rate: null,
  start_date: '',
  end_date: '',
});

function openCreate() {
  editingJob.value = null;
  form.reset();
  form.status = 'ACTIVE';
  showForm.value = true;
}

function openEdit(job) {
  editingJob.value = job;
  form.name = job.name;
  form.client_name = job.client_name ?? '';
  form.address = job.address ?? '';
  form.status = job.status;
  form.budget_hours = job.budget_hours ?? null;
  form.hourly_rate = job.hourly_rate ?? null;
  form.start_date = job.start_date ?? '';
  form.end_date = job.end_date ?? '';
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
  editingJob.value = null;
  form.clearErrors();
}

function submitForm() {
  if (editingJob.value) {
    form.put('/jobs/' + editingJob.value.id, {
      onSuccess: () => closeForm(),
    });
  } else {
    form.post('/jobs', {
      onSuccess: () => closeForm(),
    });
  }
}

// Mark complete
const showComplete = ref(false);
const completingJob = ref(null);

function openComplete(job) {
  completingJob.value = job;
  showComplete.value = true;
}

function submitComplete() {
  router.post('/jobs/' + completingJob.value.id + '/complete', {}, {
    onSuccess: () => {
      showComplete.value = false;
      completingJob.value = null;
    },
  });
}

// Delete
const showDelete = ref(false);
const deletingJob = ref(null);

function openDelete(job) {
  deletingJob.value = job;
  showDelete.value = true;
}

function submitDelete() {
  router.delete('/jobs/' + deletingJob.value.id, {
    onSuccess: () => {
      showDelete.value = false;
      deletingJob.value = null;
    },
  });
}

// Helpers
function filterByStatus(status) {
  router.get('/jobs', status ? { status } : {}, { preserveState: true });
}

function statusClass(status) {
  switch (status) {
    case 'ACTIVE': return 'badge--go';
    case 'COMPLETED': return 'badge--zone';
    case 'ON_HOLD': return 'badge--flag';
    default: return 'badge--muted';
  }
}

function statusLabel(status) {
  switch (status) {
    case 'ACTIVE': return 'Active';
    case 'COMPLETED': return 'Completed';
    case 'ON_HOLD': return 'On Hold';
    default: return status;
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

.toolbar-left {
  display: flex;
  align-items: center;
  gap: var(--sp-4);
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
  font-family: inherit;
}

.filter-select:focus {
  border-color: var(--pit-focus);
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
  padding: var(--sp-2) var(--sp-4);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  border: none;
  transition: opacity 0.15s;
}

.btn--primary {
  background: var(--viz);
  color: #fff;
}

.btn--primary:hover {
  opacity: 0.88;
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

.col-actions {
  width: 1px;
  white-space: nowrap;
}

.tabular {
  font-variant-numeric: tabular-nums;
}

.empty-cell {
  text-align: center;
  color: var(--chalk-4);
  padding: var(--sp-8) var(--sp-4) !important;
}

.row-actions {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  justify-content: flex-end;
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

.icon-btn--go:hover {
  background: var(--go-soft);
  color: var(--go);
}

.icon-btn--halt:hover {
  background: var(--halt-soft);
  color: var(--halt);
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

/* Form styles (used inside FormModal slot) */
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
</style>
