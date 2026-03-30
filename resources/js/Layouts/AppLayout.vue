<template>
  <div class="app-shell">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="brand">
          <div class="brand-ring">
            <div class="brand-ring-inner" />
          </div>
          <span class="brand-name">GeoTime</span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <span class="nav-section-label">Overview</span>
        <a
          v-for="item in primaryNav"
          :key="item.href"
          :href="item.href"
          class="nav-item"
          :class="{ 'nav-item--active': isActive(item.href) }"
          @click.prevent="navigate(item.href)"
        >
          <component :is="item.icon" :size="18" :stroke-width="1.75" />
          <span>{{ item.label }}</span>
        </a>

        <span class="nav-section-label">Manage</span>
        <a
          v-for="item in manageNav"
          :key="item.href"
          :href="item.href"
          class="nav-item"
          :class="{ 'nav-item--active': isActive(item.href) }"
          @click.prevent="navigate(item.href)"
        >
          <component :is="item.icon" :size="18" :stroke-width="1.75" />
          <span>{{ item.label }}</span>
        </a>

        <span class="nav-section-label">System</span>
        <a
          v-for="item in systemNav"
          :key="item.href"
          :href="item.href"
          class="nav-item"
          :class="{ 'nav-item--active': isActive(item.href) }"
          @click.prevent="navigate(item.href)"
        >
          <component :is="item.icon" :size="18" :stroke-width="1.75" />
          <span>{{ item.label }}</span>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="tenant-badge">
          <div class="tenant-avatar">{{ tenantInitial }}</div>
          <div class="tenant-info">
            <span class="tenant-name">{{ tenant?.name ?? 'Company' }}</span>
            <span class="tenant-plan">{{ tenant?.plan ?? 'starter' }}</span>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main -->
    <div class="main-area">
      <!-- Top Bar -->
      <header class="topbar">
        <div class="topbar-left">
          <h1 class="page-title"><slot name="title">Dashboard</slot></h1>
        </div>
        <div class="topbar-right">
          <div class="user-menu-wrapper">
            <div class="user-menu" @click="showUserMenu = !showUserMenu">
              <div class="user-avatar">{{ userInitial }}</div>
              <span class="user-name">{{ user?.name ?? 'User' }}</span>
              <ChevronDown :size="14" :stroke-width="2" class="user-chevron" />
            </div>
            <div v-if="showUserMenu" class="user-dropdown">
              <div class="dropdown-header">
                <span class="dropdown-name">{{ user?.name }}</span>
                <span class="dropdown-email">{{ user?.email }}</span>
              </div>
              <div class="dropdown-divider" />
              <button class="dropdown-item" @click="logout">
                <LogOut :size="14" :stroke-width="2" />
                <span>Sign out</span>
              </button>
            </div>
            <div v-if="showUserMenu" class="dropdown-overlay" @click="showUserMenu = false" />
          </div>
        </div>
      </header>

      <!-- Content -->
      <main class="content">
        <slot />
      </main>
    </div>
    <Toast />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import Toast from '@/Components/Toast.vue';
import {
  LayoutDashboard,
  Users,
  UsersRound,
  MapPin,
  Clock,
  Briefcase,
  ArrowRightLeft,
  CalendarDays,
  FileText,
  Settings,
  CreditCard,
  Shield,
  ChevronDown,
  LogOut,
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.auth?.tenant);
const showUserMenu = ref(false);

const userInitial = computed(() => user.value?.name?.charAt(0)?.toUpperCase() ?? 'U');
const tenantInitial = computed(() => tenant.value?.name?.charAt(0)?.toUpperCase() ?? 'C');

const primaryNav = [
  { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  { label: 'Time Entries', href: '/time-entries', icon: Clock },
  { label: 'Timesheets', href: '/timesheets', icon: FileText },
];

const manageNav = [
  { label: 'Employees', href: '/employees', icon: Users },
  { label: 'Teams', href: '/teams', icon: UsersRound },
  { label: 'Transfers', href: '/transfers', icon: ArrowRightLeft },
  { label: 'Job Sites', href: '/jobs', icon: Briefcase },
  { label: 'Geofences', href: '/geofences', icon: MapPin },
  { label: 'Time Off', href: '/pto', icon: CalendarDays },
];

const systemNav = [
  { label: 'Billing', href: '/billing', icon: CreditCard },
  { label: 'Settings', href: '/settings', icon: Settings },
  { label: 'Audit Log', href: '/audit-log', icon: Shield },
];

function isActive(href) {
  return page.url === href || page.url.startsWith(href + '/');
}

function navigate(href) {
  router.visit(href);
}

function logout() {
  router.post('/logout');
}
</script>

<style scoped>
.app-shell {
  display: flex;
  min-height: 100vh;
}

/* === Sidebar === */
.sidebar {
  width: 240px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background: var(--slab-1);
  border-right: 1px solid var(--seam-1);
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 40;
}

.sidebar-header {
  padding: var(--sp-5) var(--sp-4);
  border-bottom: 1px solid var(--seam-1);
}

.brand {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}

/* Geofence ring motif — the signature */
.brand-ring {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-full);
  border: 2px solid var(--viz);
  display: flex;
  align-items: center;
  justify-content: center;
}

.brand-ring-inner {
  width: 8px;
  height: 8px;
  border-radius: var(--radius-full);
  background: var(--viz);
}

.brand-name {
  font-size: 16px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--chalk-1);
}

/* Nav */
.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: var(--sp-3) var(--sp-2);
}

.nav-section-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--chalk-4);
  padding: var(--sp-4) var(--sp-3) var(--sp-2);
}

.nav-item {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-2) var(--sp-3);
  border-radius: var(--radius-md);
  color: var(--chalk-2);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: all var(--duration) var(--ease);
  cursor: pointer;
}

.nav-item:hover {
  color: var(--chalk-1);
  background: var(--seam-1);
}

.nav-item--active {
  color: var(--chalk-1);
  background: var(--seam-2);
}

.nav-item--active::before {
  content: '';
  position: absolute;
  left: 0;
  width: 2px;
  height: 20px;
  background: var(--viz);
  border-radius: 0 2px 2px 0;
}

/* Sidebar footer */
.sidebar-footer {
  padding: var(--sp-3) var(--sp-3);
  border-top: 1px solid var(--seam-1);
}

.tenant-badge {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-2);
  border-radius: var(--radius-md);
}

.tenant-avatar {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-md);
  background: var(--slab-3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-2);
}

.tenant-info {
  display: flex;
  flex-direction: column;
}

.tenant-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
}

.tenant-plan {
  font-size: 11px;
  color: var(--chalk-3);
  text-transform: capitalize;
}

/* === Main Area === */
.main-area {
  flex: 1;
  margin-left: 240px;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* Topbar */
.topbar {
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 var(--sp-6);
  border-bottom: 1px solid var(--seam-1);
  background: var(--slab-1);
  position: sticky;
  top: 0;
  z-index: 30;
}

.page-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--chalk-1);
  letter-spacing: -0.01em;
  margin: 0;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}

.user-menu-wrapper {
  position: relative;
}

.user-menu {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-1) var(--sp-2);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: background var(--duration) var(--ease);
}

.user-menu:hover {
  background: var(--seam-1);
}

.user-avatar {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-full);
  background: var(--viz-soft);
  color: var(--viz);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
}

.user-name {
  font-size: 13px;
  color: var(--chalk-2);
  font-weight: 500;
}

.user-chevron {
  color: var(--chalk-3);
}

/* Dropdown */
.dropdown-overlay {
  position: fixed;
  inset: 0;
  z-index: 49;
}

.user-dropdown {
  position: absolute;
  top: calc(100% + var(--sp-2));
  right: 0;
  width: 220px;
  background: var(--slab-3);
  border: 1px solid var(--seam-2);
  border-radius: var(--radius-lg);
  z-index: 50;
  overflow: hidden;
}

.dropdown-header {
  padding: var(--sp-3) var(--sp-4);
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dropdown-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
}

.dropdown-email {
  font-size: 11px;
  color: var(--chalk-3);
}

.dropdown-divider {
  height: 1px;
  background: var(--seam-1);
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  width: 100%;
  padding: var(--sp-3) var(--sp-4);
  background: none;
  border: none;
  color: var(--chalk-2);
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
  transition: all var(--duration) var(--ease);
}

.dropdown-item:hover {
  background: var(--seam-1);
  color: var(--chalk-1);
}

/* Content */
.content {
  flex: 1;
  padding: var(--sp-6);
}
</style>
