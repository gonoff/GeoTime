<template>
  <AdminLayout>
    <template #title>Tenants</template>

    <!-- Filters -->
    <div class="filters-bar">
      <div class="search-wrap">
        <Search :size="16" :stroke-width="1.75" class="search-icon" />
        <input
          v-model="filters.search"
          type="text"
          class="search-input"
          placeholder="Search tenants..."
          @input="debouncedSearch"
        />
      </div>

      <select v-model="filters.plan" class="filter-select" @change="applyFilters">
        <option value="">All Plans</option>
        <option value="starter">Starter</option>
        <option value="professional">Professional</option>
        <option value="enterprise">Enterprise</option>
      </select>

      <select v-model="filters.status" class="filter-select" @change="applyFilters">
        <option value="">All Statuses</option>
        <option value="trial">Trial</option>
        <option value="active">Active</option>
        <option value="past_due">Past Due</option>
        <option value="cancelled">Cancelled</option>
        <option value="suspended">Suspended</option>
      </select>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Plan</th>
            <th>Status</th>
            <th>Employees</th>
            <th>Users</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tenant in tenants.data" :key="tenant.id">
            <td>
              <a class="tenant-link" :href="'/admin/tenants/' + tenant.id" @click.prevent="router.visit('/admin/tenants/' + tenant.id)">
                {{ tenant.name }}
              </a>
            </td>
            <td class="cell-muted cell-capitalize">{{ tenant.plan }}</td>
            <td><span class="status-badge" :class="'status--' + tenant.status">{{ tenant.status }}</span></td>
            <td class="cell-muted">{{ tenant.employee_count }}</td>
            <td class="cell-muted">{{ tenant.users_count }}</td>
            <td class="cell-muted">{{ tenant.created_at }}</td>
            <td>
              <div class="action-buttons">
                <button
                  v-if="tenant.status !== 'suspended'"
                  class="action-btn action-btn--danger"
                  @click="suspendTenant(tenant)"
                >
                  Suspend
                </button>
                <button
                  v-if="tenant.status !== 'active'"
                  class="action-btn action-btn--success"
                  @click="activateTenant(tenant)"
                >
                  Activate
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="tenants.data.length === 0">
            <td colspan="7" class="cell-empty">No tenants found</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="tenants.last_page > 1" class="pagination">
      <button
        v-for="link in tenants.links"
        :key="link.label"
        class="page-btn"
        :class="{ 'page-btn--active': link.active, 'page-btn--disabled': !link.url }"
        :disabled="!link.url"
        @click="link.url && router.visit(link.url)"
        v-html="link.label"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Search } from 'lucide-vue-next';

const props = defineProps({
  tenants: Object,
  filters: Object,
});

const filters = reactive({
  search: props.filters?.search ?? '',
  plan: props.filters?.plan ?? '',
  status: props.filters?.status ?? '',
});

let searchTimeout = null;

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => applyFilters(), 300);
}

function applyFilters() {
  router.get('/admin/tenants', {
    search: filters.search || undefined,
    plan: filters.plan || undefined,
    status: filters.status || undefined,
  }, {
    preserveState: true,
    replace: true,
  });
}

function suspendTenant(tenant) {
  if (confirm(`Suspend "${tenant.name}"? This will block their access.`)) {
    router.post(`/admin/tenants/${tenant.id}/suspend`);
  }
}

function activateTenant(tenant) {
  router.post(`/admin/tenants/${tenant.id}/activate`);
}
</script>

<style scoped>
/* Filters */
.filters-bar {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  margin-bottom: var(--sp-4);
}

.search-wrap {
  position: relative;
  flex: 1;
  max-width: 320px;
}

.search-icon {
  position: absolute;
  left: var(--sp-3);
  top: 50%;
  transform: translateY(-50%);
  color: var(--chalk-4);
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: var(--sp-2) var(--sp-3) var(--sp-2) 36px;
  background: var(--pit);
  border: 1px solid var(--pit-border);
  border-radius: var(--radius-md);
  color: var(--chalk-1);
  font-size: 13px;
  font-family: inherit;
  box-sizing: border-box;
  transition: border-color var(--duration) var(--ease);
}

.search-input::placeholder {
  color: var(--chalk-4);
}

.search-input:focus {
  outline: none;
  border-color: rgba(80, 128, 176, 0.4);
}

.filter-select {
  padding: var(--sp-2) var(--sp-3);
  background: var(--pit);
  border: 1px solid var(--pit-border);
  border-radius: var(--radius-md);
  color: var(--chalk-1);
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
}

.filter-select option {
  background: var(--slab-3);
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

.cell-capitalize {
  text-transform: capitalize;
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

/* Actions */
.action-buttons {
  display: flex;
  gap: var(--sp-2);
}

.action-btn {
  padding: var(--sp-1) var(--sp-3);
  border-radius: var(--radius-sm);
  font-size: 11px;
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

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  margin-top: var(--sp-4);
  justify-content: center;
}

.page-btn {
  padding: var(--sp-1) var(--sp-3);
  border-radius: var(--radius-sm);
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  color: var(--chalk-2);
  font-size: 12px;
  font-family: inherit;
  cursor: pointer;
  transition: all var(--duration) var(--ease);
}

.page-btn:hover:not(:disabled) {
  background: var(--seam-1);
  color: var(--chalk-1);
}

.page-btn--active {
  background: var(--zone);
  border-color: var(--zone);
  color: #fff;
}

.page-btn--disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
</style>
