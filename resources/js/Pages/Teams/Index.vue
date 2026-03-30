<template>
  <AppLayout>
    <template #title>Teams</template>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left" />
      <button
        v-if="canManage"
        class="btn btn--primary"
        @click="openCreate"
      >
        <Plus :size="15" />
        Add Team
      </button>
    </div>

    <div v-if="teams.length === 0" class="panel">
      <div class="panel-body">
        <div class="empty-state">
          <UsersRound :size="24" :stroke-width="1.5" />
          <span>No teams created yet</span>
        </div>
      </div>
    </div>

    <div v-else class="team-grid">
      <div v-for="team in teams" :key="team.id" class="team-card">
        <div class="team-card-top">
          <div class="team-card-header">
            <div class="team-color" :style="{ background: team.color_tag || '#555' }" />
            <span class="team-name">{{ team.name }}</span>
          </div>
          <div class="team-card-actions">
            <span class="badge" :class="team.status === 'ACTIVE' ? 'badge--active' : 'badge--archived'">
              {{ team.status }}
            </span>
            <template v-if="canManage">
              <button class="icon-btn" @click="openEdit(team)" title="Edit">
                <Pencil :size="14" />
              </button>
              <button class="icon-btn icon-btn--danger" @click="openDelete(team)" title="Delete">
                <Trash2 :size="14" />
              </button>
            </template>
          </div>
        </div>

        <div class="team-card-body">
          <div class="team-meta-row">
            <span class="team-meta-label">Lead</span>
            <span class="team-meta-value">{{ team.lead_name || '--' }}</span>
          </div>
          <div class="team-meta-row">
            <span class="team-meta-label">Members</span>
            <span class="team-meta-value team-meta-value--count">{{ team.members_count }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <FormModal
      :show="showForm"
      :title="editingTeam ? 'Edit Team' : 'New Team'"
      :loading="form.processing"
      @close="closeForm"
      @submit="submitForm"
    >
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Name *</label>
          <input v-model="form.name" type="text" class="form-input" placeholder="Team name" />
          <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea v-model="form.description" class="form-input form-textarea" placeholder="Optional description" />
        </div>

        <div class="form-group">
          <label class="form-label">Color</label>
          <div class="color-swatches">
            <button
              v-for="color in colorOptions"
              :key="color"
              type="button"
              class="color-swatch"
              :class="{ 'color-swatch--selected': form.color_tag === color }"
              :style="{ background: color }"
              @click="form.color_tag = color"
            />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Team Lead</label>
          <select v-model="form.lead_employee_id" class="form-select">
            <option value="">— None —</option>
            <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.full_name }}</option>
          </select>
        </div>
      </div>
    </FormModal>

    <!-- Delete Dialog -->
    <DeleteDialog
      :show="showDelete"
      :entity-name="deletingTeam?.name ?? ''"
      entity-type="Team"
      :can-archive="true"
      @close="closeDelete"
      @archive="archiveTeam"
      @delete="deleteTeam"
    />
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormModal from '@/Components/FormModal.vue';
import DeleteDialog from '@/Components/DeleteDialog.vue';
import { UsersRound, Plus, Pencil, Trash2 } from 'lucide-vue-next';

const page = usePage();
const teams = computed(() => page.props.teams ?? []);
const employees = computed(() => page.props.employees ?? []);
const userRole = computed(() => page.props.auth?.user?.role);
const canManage = computed(() => ['admin', 'super_admin', 'manager', 'team_lead'].includes(userRole.value));

const colorOptions = [
  '#6366f1', '#3b82f6', '#10b981', '#f59e0b',
  '#ef4444', '#ec4899', '#8b5cf6', '#64748b',
];

// Form modal state
const showForm = ref(false);
const editingTeam = ref(null);

const form = useForm({
  name: '',
  description: '',
  color_tag: colorOptions[0],
  lead_employee_id: '',
});

function openCreate() {
  editingTeam.value = null;
  form.reset();
  form.color_tag = colorOptions[0];
  showForm.value = true;
}

function openEdit(team) {
  editingTeam.value = team;
  form.name = team.name;
  form.description = team.description ?? '';
  form.color_tag = team.color_tag ?? colorOptions[0];
  form.lead_employee_id = team.lead_employee_id ?? '';
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
  editingTeam.value = null;
  form.reset();
}

function submitForm() {
  if (editingTeam.value) {
    form.put(`/teams/${editingTeam.value.id}`, {
      onSuccess: () => closeForm(),
    });
  } else {
    form.post('/teams', {
      onSuccess: () => closeForm(),
    });
  }
}

// Delete dialog state
const showDelete = ref(false);
const deletingTeam = ref(null);

function openDelete(team) {
  deletingTeam.value = team;
  showDelete.value = true;
}

function closeDelete() {
  showDelete.value = false;
  deletingTeam.value = null;
}

function archiveTeam() {
  router.post(`/teams/${deletingTeam.value.id}/archive`, {}, {
    onSuccess: () => closeDelete(),
  });
}

function deleteTeam() {
  router.delete(`/teams/${deletingTeam.value.id}`, {
    onSuccess: () => closeDelete(),
  });
}
</script>

<style scoped>
/* === Toolbar === */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--sp-4);
}

.toolbar-left {
  flex: 1;
}

/* === Buttons === */
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

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  color: var(--chalk-4);
  cursor: pointer;
  padding: 4px;
  border-radius: var(--radius-md);
  transition: color 0.15s, background 0.15s;
}

.icon-btn:hover {
  color: var(--chalk-1);
  background: var(--slab-3);
}

.icon-btn--danger:hover {
  color: var(--halt);
  background: var(--halt-soft);
}

/* === Panel === */
.panel {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
}

.panel-body {
  padding: var(--sp-4) var(--sp-5);
}

/* === Team Grid === */
.team-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--sp-4);
}

.team-card {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
  padding: var(--sp-5);
  transition: border-color var(--duration) var(--ease);
}

.team-card:hover {
  border-color: var(--seam-2);
}

.team-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: var(--sp-4);
}

.team-card-header {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}

.team-color {
  width: 12px;
  height: 12px;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.team-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--chalk-1);
}

.team-card-actions {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
}

/* === Team Meta === */
.team-card-body {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding-top: var(--sp-4);
  border-top: 1px solid var(--seam-1);
}

.team-meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.team-meta-label {
  font-size: 12px;
  color: var(--chalk-3);
}

.team-meta-value {
  font-size: 13px;
  color: var(--chalk-1);
  font-weight: 500;
}

.team-meta-value--count {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
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

.badge--archived {
  background: var(--flag-soft);
  color: var(--flag);
}

/* === Empty State === */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-12) 0;
  color: var(--chalk-4);
  font-size: 13px;
}

/* === Form elements === */
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

.form-error {
  font-size: 12px;
  color: var(--halt);
}

/* === Color Swatches === */
.color-swatches {
  display: flex;
  gap: var(--sp-2);
  flex-wrap: wrap;
}

.color-swatch {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-full);
  border: 2px solid transparent;
  cursor: pointer;
  transition: transform 0.1s, border-color 0.1s;
}

.color-swatch:hover {
  transform: scale(1.15);
}

.color-swatch--selected {
  border-color: var(--chalk-1);
  transform: scale(1.1);
}
</style>
