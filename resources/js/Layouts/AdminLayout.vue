<template>
  <div class="app-shell">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="brand">
          <div class="brand-ring">
            <div class="brand-ring-inner" />
          </div>
          <span class="brand-name">GeoTime Admin</span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <span class="nav-section-label">Platform</span>
        <a
          v-for="item in navItems"
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
        <div class="admin-badge">
          <div class="admin-avatar">
            <Shield :size="16" :stroke-width="1.75" />
          </div>
          <div class="admin-info">
            <span class="admin-label">Platform Admin</span>
            <span class="admin-email">{{ user?.email ?? 'admin' }}</span>
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
          <form @submit.prevent="logout">
            <button type="submit" class="logout-button">
              <LogOut :size="16" :stroke-width="1.75" />
              <span>Logout</span>
            </button>
          </form>
        </div>
      </header>

      <!-- Content -->
      <main class="content">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
  LayoutDashboard,
  Building2,
  Shield,
  LogOut,
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const navItems = [
  { label: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
  { label: 'Tenants', href: '/admin/tenants', icon: Building2 },
];

function isActive(href) {
  return page.url === href || page.url.startsWith(href + '/') || page.url.startsWith(href + '?');
}

function navigate(href) {
  router.visit(href);
}

function logout() {
  router.post('/admin/logout');
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

.brand-ring {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-full);
  border: 2px solid var(--zone);
  display: flex;
  align-items: center;
  justify-content: center;
}

.brand-ring-inner {
  width: 8px;
  height: 8px;
  border-radius: var(--radius-full);
  background: var(--zone);
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
  background: var(--zone);
  border-radius: 0 2px 2px 0;
}

/* Sidebar footer */
.sidebar-footer {
  padding: var(--sp-3) var(--sp-3);
  border-top: 1px solid var(--seam-1);
}

.admin-badge {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-2);
  border-radius: var(--radius-md);
}

.admin-avatar {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-md);
  background: var(--zone-soft);
  color: var(--zone);
  display: flex;
  align-items: center;
  justify-content: center;
}

.admin-info {
  display: flex;
  flex-direction: column;
}

.admin-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
}

.admin-email {
  font-size: 11px;
  color: var(--chalk-3);
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

.logout-button {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-1) var(--sp-3);
  border-radius: var(--radius-md);
  background: none;
  border: 1px solid var(--seam-2);
  color: var(--chalk-2);
  font-size: 12px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  transition: all var(--duration) var(--ease);
}

.logout-button:hover {
  background: var(--seam-1);
  color: var(--chalk-1);
}

/* Content */
.content {
  flex: 1;
  padding: var(--sp-6);
}
</style>
