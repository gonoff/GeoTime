<template>
  <AppLayout>
    <template #title>Geofences</template>

    <div class="toolbar">
      <span class="record-count">{{ geofences.length }} geofences</span>
      <button
        v-if="canManage"
        class="btn btn--primary"
        @click="openCreate"
      >
        <Plus :size="14" />
        Add Geofence
      </button>
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
              <th v-if="canManage" class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="geofences.length === 0">
              <td :colspan="canManage ? 7 : 6" class="empty-cell">No geofences found</td>
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
              <td v-if="canManage" class="col-actions">
                <div class="action-btns">
                  <button class="icon-btn" title="Edit" @click="openEdit(geofence)">
                    <Pencil :size="14" />
                  </button>
                  <button
                    class="icon-btn"
                    :title="geofence.is_active ? 'Deactivate' : 'Activate'"
                    @click="toggleActive(geofence)"
                  >
                    <component :is="geofence.is_active ? ToggleRight : ToggleLeft" :size="14" />
                  </button>
                  <button
                    v-if="isAdmin"
                    class="icon-btn icon-btn--danger"
                    title="Delete"
                    @click="openDelete(geofence)"
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

    <!-- Create / Edit Modal -->
    <FormModal
      :show="showModal"
      :title="editingGeofence ? 'Edit Geofence' : 'Add Geofence'"
      :loading="form.processing"
      @close="closeModal"
      @submit="submitForm"
    >
      <div class="form-group">
        <label class="form-label">Name <span class="required">*</span></label>
        <input v-model="form.name" class="form-input" type="text" placeholder="Geofence name" />
        <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
      </div>

      <div class="form-group">
        <label class="form-label">Job Site <span class="required">*</span></label>
        <select v-model="form.job_id" class="form-select">
          <option value="">Select job site...</option>
          <option v-for="job in jobs" :key="job.id" :value="job.id">{{ job.name }}</option>
        </select>
        <span v-if="form.errors.job_id" class="form-error">{{ form.errors.job_id }}</span>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Latitude <span class="required">*</span></label>
          <input v-model="form.latitude" class="form-input" type="number" step="0.000001" placeholder="0.000000" />
          <span v-if="form.errors.latitude" class="form-error">{{ form.errors.latitude }}</span>
        </div>
        <div class="form-group">
          <label class="form-label">Longitude <span class="required">*</span></label>
          <input v-model="form.longitude" class="form-input" type="number" step="0.000001" placeholder="0.000000" />
          <span v-if="form.errors.longitude" class="form-error">{{ form.errors.longitude }}</span>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Radius (meters) <span class="required">*</span></label>
        <input v-model="form.radius_meters" class="form-input" type="number" min="50" max="500" placeholder="100" />
        <span v-if="form.errors.radius_meters" class="form-error">{{ form.errors.radius_meters }}</span>
      </div>

      <div class="form-group form-group--inline">
        <label class="form-label">Active</label>
        <input v-model="form.is_active" type="checkbox" class="form-checkbox" />
      </div>
    </FormModal>

    <!-- Delete Dialog -->
    <DeleteDialog
      :show="showDelete"
      :entity-name="deletingGeofence?.name ?? ''"
      entity-type="Geofence"
      :can-archive="false"
      @close="showDelete = false"
      @delete="confirmDelete"
    />
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormModal from '@/Components/FormModal.vue';
import DeleteDialog from '@/Components/DeleteDialog.vue';
import { Plus, Pencil, Trash2, ToggleLeft, ToggleRight } from 'lucide-vue-next';

const page = usePage();
const geofences = computed(() => page.props.geofences ?? []);
const jobs = computed(() => page.props.jobs ?? []);

const userRole = computed(() => page.props.auth?.user?.role);
const canManage = computed(() => ['admin', 'super_admin', 'manager', 'team_lead'].includes(userRole.value));
const isAdmin = computed(() => ['admin', 'super_admin'].includes(userRole.value));

// Modal state
const showModal = ref(false);
const editingGeofence = ref(null);

const form = useForm({
  name: '',
  job_id: '',
  latitude: '',
  longitude: '',
  radius_meters: '',
  is_active: true,
});

function openCreate() {
  editingGeofence.value = null;
  form.reset();
  form.is_active = true;
  showModal.value = true;
}

function openEdit(geofence) {
  editingGeofence.value = geofence;
  form.name = geofence.name;
  form.job_id = geofence.job_id ?? '';
  form.latitude = geofence.latitude;
  form.longitude = geofence.longitude;
  form.radius_meters = geofence.radius_meters;
  form.is_active = geofence.is_active;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingGeofence.value = null;
  form.reset();
}

function submitForm() {
  if (editingGeofence.value) {
    form.put(`/geofences/${editingGeofence.value.id}`, {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post('/geofences', {
      onSuccess: () => closeModal(),
    });
  }
}

// Toggle active
function toggleActive(geofence) {
  const url = geofence.is_active
    ? `/geofences/${geofence.id}/deactivate`
    : `/geofences/${geofence.id}/activate`;
  router.post(url);
}

// Delete
const showDelete = ref(false);
const deletingGeofence = ref(null);

function openDelete(geofence) {
  deletingGeofence.value = geofence;
  showDelete.value = true;
}

function confirmDelete() {
  router.delete(`/geofences/${deletingGeofence.value.id}`, {
    onSuccess: () => {
      showDelete.value = false;
      deletingGeofence.value = null;
    },
  });
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
  padding: var(--sp-2) var(--sp-4);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: opacity 0.15s;
}

.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn--primary { background: var(--viz); color: #fff; }
.btn--primary:hover:not(:disabled) { opacity: 0.9; }

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
  text-align: right;
  width: 100px;
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

/* Action buttons */
.action-btns {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--sp-1);
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  border-radius: var(--radius-md);
  color: var(--chalk-3);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.icon-btn:hover {
  background: var(--slab-3);
  color: var(--chalk-1);
}

.icon-btn--danger:hover {
  background: var(--halt-soft);
  color: var(--halt);
}

/* Form */
.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
  margin-bottom: var(--sp-4);
}

.form-group--inline {
  flex-direction: row;
  align-items: center;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sp-4);
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--chalk-2);
}

.required {
  color: var(--halt);
}

.form-input,
.form-select {
  padding: var(--sp-2) var(--sp-3);
  background: var(--slab-3);
  border: 1px solid var(--seam-2);
  border-radius: var(--radius-md);
  color: var(--chalk-1);
  font-size: 13px;
  outline: none;
  transition: border-color 0.15s;
}

.form-input:focus,
.form-select:focus {
  border-color: var(--viz);
}

.form-checkbox {
  width: 16px;
  height: 16px;
  cursor: pointer;
  margin-left: var(--sp-2);
}

.form-error {
  font-size: 11px;
  color: var(--halt);
}
</style>
