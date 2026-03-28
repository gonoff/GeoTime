<template>
  <AppLayout>
    <template #title>Employees</template>

    <!-- Toolbar -->
    <div class="toolbar">
      <input
        v-model="searchQuery"
        type="text"
        class="search-input"
        placeholder="Search employees..."
        @input="debouncedSearch"
      />
      <select v-model="statusFilter" class="filter-select" @change="applyFilters">
        <option value="">All Statuses</option>
        <option value="ACTIVE">Active</option>
        <option value="INACTIVE">Inactive</option>
        <option value="TERMINATED">Terminated</option>
      </select>
      <div class="toolbar-spacer" />
      <span class="result-count">{{ employees.total }} employee{{ employees.total !== 1 ? 's' : '' }}</span>
    </div>

    <!-- Table Panel -->
    <div class="panel">
      <div class="panel-body panel-body--flush">
        <table v-if="employees.data.length > 0" class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Hourly Rate</th>
              <th>Status</th>
              <th>Hire Date</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="emp in employees.data"
              :key="emp.id"
              class="row-link"
              @click="visitEmployee(emp.id)"
            >
              <td>
                <div class="employee-name-cell">
                  <div class="employee-avatar">{{ emp.first_name.charAt(0) }}{{ emp.last_name.charAt(0) }}</div>
                  <div>
                    <span class="employee-fullname">{{ emp.full_name }}</span>
                    <span v-if="emp.team_name" class="employee-team">{{ emp.team_name }}</span>
                  </div>
                </div>
              </td>
              <td class="text-secondary">{{ emp.email }}</td>
              <td>{{ emp.role }}</td>
              <td class="text-mono">${{ emp.hourly_rate }}</td>
              <td>
                <span class="badge" :class="badgeClass(emp.status)">{{ emp.status }}</span>
              </td>
              <td class="text-secondary">{{ emp.hire_date }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Empty State -->
        <div v-else class="empty-state">
          <Users :size="24" :stroke-width="1.5" />
          <span>No employees found</span>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="employees.last_page > 1" class="pagination">
      <button
        v-for="link in employees.links"
        :key="link.label"
        class="page-btn"
        :class="{ 'page-btn--active': link.active, 'page-btn--disabled': !link.url }"
        :disabled="!link.url"
        @click="link.url && visitPage(link.url)"
        v-html="link.label"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Users } from 'lucide-vue-next';

const page = usePage();
const employees = ref(page.props.employees);
const searchQuery = ref(page.props.filters?.search ?? '');
const statusFilter = ref(page.props.filters?.status ?? '');

let searchTimeout = null;

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => applyFilters(), 300);
}

function applyFilters() {
  router.get('/employees', {
    search: searchQuery.value || undefined,
    status: statusFilter.value || undefined,
  }, {
    preserveState: true,
    replace: true,
    onSuccess: () => {
      employees.value = usePage().props.employees;
    },
  });
}

function visitEmployee(id) {
  router.visit(`/employees/${id}`);
}

function visitPage(url) {
  router.visit(url, {
    preserveState: true,
    onSuccess: () => {
      employees.value = usePage().props.employees;
    },
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
</script>

<style scoped>
/* === Toolbar === */
.toolbar {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  margin-bottom: var(--sp-4);
}

.search-input {
  padding: var(--sp-2) var(--sp-3);
  background: var(--pit);
  border: 1px solid var(--pit-border);
  border-radius: var(--radius-md);
  color: var(--chalk-1);
  font-size: 13px;
  font-family: inherit;
  width: 260px;
}

.search-input::placeholder {
  color: var(--chalk-4);
}

.search-input:focus {
  outline: none;
  border-color: var(--pit-focus);
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

.filter-select:focus {
  outline: none;
  border-color: var(--pit-focus);
}

.toolbar-spacer {
  flex: 1;
}

.result-count {
  font-size: 12px;
  color: var(--chalk-3);
  font-variant-numeric: tabular-nums;
}

/* === Panel === */
.panel {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
}

.panel-body--flush {
  padding: 0;
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

.row-link {
  cursor: pointer;
}

/* === Employee Name Cell === */
.employee-name-cell {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}

.employee-avatar {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-full);
  background: var(--viz-soft);
  color: var(--viz);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 600;
  flex-shrink: 0;
  letter-spacing: -0.02em;
}

.employee-fullname {
  display: block;
  font-weight: 500;
  color: var(--chalk-1);
}

.employee-team {
  display: block;
  font-size: 11px;
  color: var(--chalk-3);
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

/* === Pagination === */
.pagination {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  margin-top: var(--sp-4);
  justify-content: center;
}

.page-btn {
  padding: var(--sp-1) var(--sp-3);
  font-size: 12px;
  font-family: inherit;
  color: var(--chalk-2);
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all var(--duration) var(--ease);
}

.page-btn:hover:not(.page-btn--disabled) {
  background: var(--slab-3);
  color: var(--chalk-1);
}

.page-btn--active {
  background: var(--viz-soft);
  color: var(--viz);
  border-color: var(--viz);
}

.page-btn--disabled {
  opacity: 0.4;
  cursor: default;
}
</style>
