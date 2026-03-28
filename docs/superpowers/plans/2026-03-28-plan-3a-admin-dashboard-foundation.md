# Plan 3a: Admin Dashboard Foundation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the admin dashboard frontend foundation — Tailwind CSS, shared Vue components, layout system, Inertia auth pages, dashboard overview, employee management, team management, and transfer management pages.

**Architecture:** Inertia.js + Vue 3 SPA served by Laravel. All pages rendered via `Inertia::render()` with props passed from controllers. Shared data (auth user, tenant, notifications) injected via `HandleInertiaRequests` middleware. Session-based auth for web (NOT API tokens). Tailwind CSS utility classes for all styling. Reusable Vue components for tables, forms, and modals.

**Tech Stack:** Laravel 13, Inertia.js, Vue 3 (Composition API + `<script setup>`), Tailwind CSS 4.x, Vite

---

## File Structure

```
resources/
├── css/
│   └── app.css
├── js/
│   ├── app.js (modify)
│   ├── Layouts/
│   │   └── AppLayout.vue
│   ├── Components/
│   │   ├── DataTable.vue
│   │   ├── Modal.vue
│   │   ├── Button.vue
│   │   ├── Input.vue
│   │   ├── Select.vue
│   │   ├── Badge.vue
│   │   ├── Alert.vue
│   │   ├── StatsCard.vue
│   │   └── ColorPicker.vue
│   └── Pages/
│       ├── Auth/
│       │   ├── Login.vue
│       │   └── Register.vue
│       ├── Dashboard.vue (replace)
│       ├── Employees/
│       │   ├── Index.vue
│       │   ├── Create.vue
│       │   ├── Edit.vue
│       │   └── Import.vue
│       ├── Teams/
│       │   ├── Index.vue
│       │   ├── Create.vue
│       │   ├── Edit.vue
│       │   └── Members.vue
│       └── Transfers/
│           ├── Index.vue
│           └── Create.vue
app/
├── Http/
│   ├── Middleware/
│   │   └── HandleInertiaRequests.php
│   └── Controllers/
│       ├── Auth/
│       │   ├── WebLoginController.php
│       │   └── WebRegisterController.php
│       ├── DashboardController.php
│       ├── Web/
│       │   ├── EmployeePageController.php
│       │   ├── TeamPageController.php
│       │   └── TransferPageController.php
routes/
│   └── web.php (modify)
tests/
└── Feature/
    ├── Web/
    │   ├── AuthPageTest.php
    │   ├── DashboardPageTest.php
    │   ├── EmployeePageTest.php
    │   ├── TeamPageTest.php
    │   └── TransferPageTest.php
```

---

## Task 1: Tailwind CSS + Base Layout

**Files:**
- Modify: `resources/css/app.css`
- Create: `resources/js/Layouts/AppLayout.vue`
- Modify: `resources/js/app.js`
- Modify: `vite.config.js`

- [ ] **Step 1: Install Tailwind CSS**

```bash
docker compose exec app npm install tailwindcss @tailwindcss/vite
```

- [ ] **Step 2: Configure Vite for Tailwind**

```js
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
```

- [ ] **Step 3: Set up app.css with Tailwind**

```css
/* resources/css/app.css */
@import "tailwindcss";
```

- [ ] **Step 4: Create AppLayout.vue**

```vue
<!-- resources/js/Layouts/AppLayout.vue -->
<script setup>
import { ref, computed } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';

const page = usePage();
const auth = computed(() => page.props.auth);
const tenant = computed(() => page.props.tenant);
const notificationsCount = computed(() => page.props.unread_notifications_count || 0);

const sidebarOpen = ref(true);
const userMenuOpen = ref(false);

const navigation = [
    { name: 'Dashboard', href: '/dashboard', icon: 'home' },
    { name: 'Employees', href: '/employees', icon: 'users' },
    { name: 'Teams', href: '/teams', icon: 'user-group' },
    { name: 'Transfers', href: '/transfers', icon: 'arrows-right-left' },
    { name: 'Jobs', href: '/jobs', icon: 'briefcase' },
    { name: 'Geofences', href: '/geofences', icon: 'map-pin' },
    { name: 'Time Entries', href: '/time-entries', icon: 'clock' },
    { name: 'Reports', href: '/reports', icon: 'chart-bar' },
];

const isActive = (href) => {
    return page.url.startsWith(href);
};

const logout = () => {
    router.post('/logout');
};

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 flex flex-col bg-gray-900 text-white transition-all duration-200',
                sidebarOpen ? 'w-64' : 'w-16',
            ]"
        >
            <!-- Logo / Tenant Name -->
            <div class="flex h-16 items-center justify-between px-4 border-b border-gray-700">
                <span v-if="sidebarOpen" class="text-lg font-bold truncate">
                    {{ tenant?.name || 'GeoTime' }}
                </span>
                <span v-else class="text-lg font-bold">G</span>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4">
                <ul class="space-y-1 px-2">
                    <li v-for="item in navigation" :key="item.name">
                        <Link
                            :href="item.href"
                            :class="[
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                isActive(item.href)
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-300 hover:bg-gray-800 hover:text-white',
                            ]"
                        >
                            <span class="w-5 h-5 flex-shrink-0">
                                <!-- Icon placeholder — use first letter -->
                                {{ item.icon.charAt(0).toUpperCase() }}
                            </span>
                            <span v-if="sidebarOpen">{{ item.name }}</span>
                        </Link>
                    </li>
                </ul>
            </nav>

            <!-- Sidebar toggle -->
            <button
                @click="toggleSidebar"
                class="flex items-center justify-center h-12 border-t border-gray-700 text-gray-400 hover:text-white"
            >
                <span v-if="sidebarOpen">&larr; Collapse</span>
                <span v-else>&rarr;</span>
            </button>
        </aside>

        <!-- Main content area -->
        <div :class="['transition-all duration-200', sidebarOpen ? 'ml-64' : 'ml-16']">
            <!-- Top bar -->
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between bg-white border-b border-gray-200 px-6 shadow-sm">
                <div class="flex items-center gap-4">
                    <h1 class="text-lg font-semibold text-gray-800">
                        <slot name="header">Dashboard</slot>
                    </h1>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Notifications -->
                    <Link
                        href="/notifications"
                        class="relative p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                    >
                        <span class="text-sm">Notifications</span>
                        <span
                            v-if="notificationsCount > 0"
                            class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs text-white"
                        >
                            {{ notificationsCount > 99 ? '99+' : notificationsCount }}
                        </span>
                    </Link>

                    <!-- User dropdown -->
                    <div class="relative">
                        <button
                            @click="userMenuOpen = !userMenuOpen"
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        >
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white text-xs font-bold">
                                {{ auth?.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                            </div>
                            <span class="hidden sm:block">{{ auth?.user?.name }}</span>
                        </button>

                        <div
                            v-if="userMenuOpen"
                            @click="userMenuOpen = false"
                            class="fixed inset-0 z-40"
                        />
                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 z-50 mt-2 w-48 rounded-lg bg-white py-1 shadow-lg ring-1 ring-gray-200"
                        >
                            <div class="px-4 py-2 text-xs text-gray-500 border-b border-gray-100">
                                {{ auth?.user?.email }}
                            </div>
                            <Link href="/settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                Settings
                            </Link>
                            <button
                                @click="logout"
                                class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                            >
                                Sign out
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
```

- [ ] **Step 5: Update app.js to support layouts**

```js
// resources/js/app.js
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
```

- [ ] **Step 6: Verify build**

```bash
docker compose exec app npm run build
```
Expected: Vite build completes without errors.

- [ ] **Step 7: Commit**

```bash
git add resources/css/app.css resources/js/Layouts/AppLayout.vue resources/js/app.js vite.config.js package.json package-lock.json
git commit -m "feat: install Tailwind CSS and create AppLayout with sidebar and top bar"
```

---

## Task 2: Shared Vue Components

**Files:**
- Create: `resources/js/Components/Button.vue`
- Create: `resources/js/Components/Input.vue`
- Create: `resources/js/Components/Select.vue`
- Create: `resources/js/Components/Badge.vue`
- Create: `resources/js/Components/Alert.vue`
- Create: `resources/js/Components/Modal.vue`
- Create: `resources/js/Components/StatsCard.vue`
- Create: `resources/js/Components/DataTable.vue`
- Create: `resources/js/Components/ColorPicker.vue`

- [ ] **Step 1: Create Button.vue**

```vue
<!-- resources/js/Components/Button.vue -->
<script setup>
defineProps({
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' }, // primary, secondary, danger, ghost
    size: { type: String, default: 'md' }, // sm, md, lg
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const variantClasses = {
    primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
    secondary: 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-blue-500',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
    ghost: 'text-gray-600 hover:bg-gray-100 focus:ring-gray-500',
};

const sizeClasses = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-3 text-base',
};
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :class="[
            'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed',
            variantClasses[variant],
            sizeClasses[size],
        ]"
    >
        <svg v-if="loading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <slot />
    </button>
</template>
```

- [ ] **Step 2: Create Input.vue**

```vue
<!-- resources/js/Components/Input.vue -->
<script setup>
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        <input
            :type="type"
            :value="modelValue"
            @input="emit('update:modelValue', $event.target.value)"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :class="[
                'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-0',
                error
                    ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 text-gray-900 focus:border-blue-500 focus:ring-blue-500',
                disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white',
            ]"
        />
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
```

- [ ] **Step 3: Create Select.vue**

```vue
<!-- resources/js/Components/Select.vue -->
<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    options: { type: Array, default: () => [] }, // [{ value, label }]
    placeholder: { type: String, default: 'Select...' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        <select
            :value="modelValue"
            @change="emit('update:modelValue', $event.target.value)"
            :required="required"
            :disabled="disabled"
            :class="[
                'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-0',
                error
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white',
            ]"
        >
            <option value="" disabled>{{ placeholder }}</option>
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
```

- [ ] **Step 4: Create Badge.vue**

```vue
<!-- resources/js/Components/Badge.vue -->
<script setup>
defineProps({
    variant: { type: String, default: 'gray' }, // gray, green, red, yellow, blue, indigo
    size: { type: String, default: 'md' }, // sm, md
});

const variantClasses = {
    gray: 'bg-gray-100 text-gray-700',
    green: 'bg-green-100 text-green-700',
    red: 'bg-red-100 text-red-700',
    yellow: 'bg-yellow-100 text-yellow-800',
    blue: 'bg-blue-100 text-blue-700',
    indigo: 'bg-indigo-100 text-indigo-700',
};

const sizeClasses = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-xs',
};
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-full font-medium',
            variantClasses[variant],
            sizeClasses[size],
        ]"
    >
        <slot />
    </span>
</template>
```

- [ ] **Step 5: Create Alert.vue**

```vue
<!-- resources/js/Components/Alert.vue -->
<script setup>
import { ref } from 'vue';

const props = defineProps({
    type: { type: String, default: 'info' }, // info, success, warning, error
    dismissible: { type: Boolean, default: false },
});

const visible = ref(true);

const typeClasses = {
    info: 'bg-blue-50 text-blue-800 border-blue-200',
    success: 'bg-green-50 text-green-800 border-green-200',
    warning: 'bg-yellow-50 text-yellow-800 border-yellow-200',
    error: 'bg-red-50 text-red-800 border-red-200',
};
</script>

<template>
    <div
        v-if="visible"
        :class="['rounded-lg border p-4 text-sm', typeClasses[type]]"
        role="alert"
    >
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <slot />
            </div>
            <button
                v-if="dismissible"
                @click="visible = false"
                class="ml-3 flex-shrink-0 text-current opacity-50 hover:opacity-75"
            >
                &times;
            </button>
        </div>
    </div>
</template>
```

- [ ] **Step 6: Create Modal.vue**

```vue
<!-- resources/js/Components/Modal.vue -->
<script setup>
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'lg' }, // sm, md, lg, xl, 2xl
});

const emit = defineEmits(['close']);

const maxWidthClasses = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
};

const close = () => {
    emit('close');
};

watch(() => props.show, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50" @click="close" />

                <!-- Panel -->
                <div
                    :class="[
                        'relative w-full rounded-xl bg-white shadow-2xl',
                        maxWidthClasses[maxWidth],
                    ]"
                >
                    <!-- Header -->
                    <div v-if="title" class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                        <button @click="close" class="text-gray-400 hover:text-gray-600 text-xl leading-none">
                            &times;
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-4">
                        <slot />
                    </div>

                    <!-- Footer -->
                    <div v-if="$slots.footer" class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
```

- [ ] **Step 7: Create StatsCard.vue**

```vue
<!-- resources/js/Components/StatsCard.vue -->
<script setup>
defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    subtitle: { type: String, default: '' },
    trend: { type: String, default: '' }, // 'up', 'down', ''
    trendValue: { type: String, default: '' },
    color: { type: String, default: 'blue' }, // blue, green, red, yellow
});

const colorClasses = {
    blue: 'bg-blue-50 text-blue-600',
    green: 'bg-green-50 text-green-600',
    red: 'bg-red-50 text-red-600',
    yellow: 'bg-yellow-50 text-yellow-600',
};
</script>

<template>
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">{{ title }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ value }}</p>
                <p v-if="subtitle" class="mt-1 text-sm text-gray-500">{{ subtitle }}</p>
            </div>
            <div v-if="trend" class="flex items-center gap-1 text-sm font-medium" :class="trend === 'up' ? 'text-green-600' : 'text-red-600'">
                <span>{{ trend === 'up' ? '&#9650;' : '&#9660;' }}</span>
                <span>{{ trendValue }}</span>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 8: Create DataTable.vue**

```vue
<!-- resources/js/Components/DataTable.vue -->
<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from './Button.vue';

const props = defineProps({
    columns: {
        type: Array,
        required: true,
        // [{ key: 'name', label: 'Name', sortable: true }]
    },
    rows: { type: Array, default: () => [] },
    pagination: { type: Object, default: null },
    // { current_page, last_page, per_page, total, links }
    searchable: { type: Boolean, default: true },
    searchPlaceholder: { type: String, default: 'Search...' },
    filters: { type: Object, default: () => ({}) },
    baseUrl: { type: String, default: '' },
});

const emit = defineEmits(['row-click']);

const search = ref(props.filters.search || '');
const sortField = ref(props.filters.sort || '');
const sortDirection = ref(props.filters.direction || 'asc');

let searchTimeout = null;

const doSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        navigateWithParams();
    }, 300);
};

const toggleSort = (column) => {
    if (!column.sortable) return;

    if (sortField.value === column.key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = column.key;
        sortDirection.value = 'asc';
    }
    navigateWithParams();
};

const navigateWithParams = () => {
    if (!props.baseUrl) return;

    const params = {};
    if (search.value) params.search = search.value;
    if (sortField.value) {
        params.sort = sortField.value;
        params.direction = sortDirection.value;
    }

    router.get(props.baseUrl, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const sortIcon = (column) => {
    if (sortField.value !== column.key) return '';
    return sortDirection.value === 'asc' ? ' &#9650;' : ' &#9660;';
};
</script>

<template>
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <!-- Toolbar -->
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
            <div class="flex items-center gap-3">
                <input
                    v-if="searchable"
                    v-model="search"
                    @input="doSearch"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <slot name="filters" />
            </div>
            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            @click="toggleSort(col)"
                            :class="[
                                'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500',
                                col.sortable ? 'cursor-pointer select-none hover:text-gray-700' : '',
                            ]"
                        >
                            <span v-html="col.label + sortIcon(col)" />
                        </th>
                        <th v-if="$slots['row-actions']" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 text-right">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="(row, index) in rows"
                        :key="row.id || index"
                        @click="emit('row-click', row)"
                        class="hover:bg-gray-50 transition-colors"
                        :class="{ 'cursor-pointer': $attrs['onRow-click'] }"
                    >
                        <td v-for="col in columns" :key="col.key" class="px-4 py-3 text-gray-700">
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                {{ row[col.key] }}
                            </slot>
                        </td>
                        <td v-if="$slots['row-actions']" class="px-4 py-3 text-right">
                            <slot name="row-actions" :row="row" />
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td :colspan="columns.length + ($slots['row-actions'] ? 1 : 0)" class="px-4 py-12 text-center text-gray-400">
                            No records found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between border-t border-gray-200 px-4 py-3">
            <p class="text-sm text-gray-500">
                Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }}
                to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
                of {{ pagination.total }} results
            </p>
            <div class="flex items-center gap-1">
                <button
                    v-for="link in pagination.links"
                    :key="link.label"
                    @click="goToPage(link.url)"
                    :disabled="!link.url"
                    :class="[
                        'px-3 py-1 rounded text-sm',
                        link.active
                            ? 'bg-blue-600 text-white'
                            : link.url
                                ? 'text-gray-600 hover:bg-gray-100'
                                : 'text-gray-300 cursor-not-allowed',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 9: Create ColorPicker.vue**

```vue
<!-- resources/js/Components/ColorPicker.vue -->
<script setup>
defineProps({
    modelValue: { type: String, default: '#3B82F6' },
    label: { type: String, default: 'Color' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const presetColors = [
    '#EF4444', '#F97316', '#F59E0B', '#22C55E', '#14B8A6',
    '#3B82F6', '#6366F1', '#8B5CF6', '#EC4899', '#6B7280',
];
</script>

<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">{{ label }}</label>
        <div class="flex items-center gap-2 flex-wrap">
            <button
                v-for="color in presetColors"
                :key="color"
                @click="emit('update:modelValue', color)"
                type="button"
                :class="[
                    'h-8 w-8 rounded-full border-2 transition-transform hover:scale-110',
                    modelValue === color ? 'border-gray-900 scale-110' : 'border-transparent',
                ]"
                :style="{ backgroundColor: color }"
            />
            <input
                type="color"
                :value="modelValue"
                @input="emit('update:modelValue', $event.target.value)"
                class="h-8 w-8 cursor-pointer rounded border-0 p-0"
                title="Custom color"
            />
        </div>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
```

- [ ] **Step 10: Verify build**

```bash
docker compose exec app npm run build
```
Expected: Vite build completes without errors.

- [ ] **Step 11: Commit**

```bash
git add resources/js/Components/
git commit -m "feat: add shared Vue components (Button, Input, Select, Badge, Alert, Modal, StatsCard, DataTable, ColorPicker)"
```

---

## Task 3: HandleInertiaRequests Middleware

**Files:**
- Create: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 1: Create HandleInertiaRequests middleware**

```php
// app/Http/Middleware/HandleInertiaRequests.php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ] : null,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $tenant->plan,
                'status' => $tenant->status,
                'timezone' => $tenant->timezone,
            ] : null,
            'unread_notifications_count' => $user
                ? $user->unreadNotifications()->count()
                : 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
```

- [ ] **Step 2: Register middleware in bootstrap/app.php**

Add the `HandleInertiaRequests` middleware to the web group in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
    ]);
    $middleware->appendToGroup('web', \App\Http\Middleware\ResolveTenant::class);
    $middleware->appendToGroup('web', \App\Http\Middleware\EnsureSubscriptionActive::class);
    $middleware->appendToGroup('api', \App\Http\Middleware\ResolveTenant::class);
    $middleware->appendToGroup('api', \App\Http\Middleware\EnsureSubscriptionActive::class);
    $middleware->validateCsrfTokens(except: [
        'stripe/webhook',
    ]);
})
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php bootstrap/app.php
git commit -m "feat: add HandleInertiaRequests middleware to share auth, tenant, and notifications"
```

---

## Task 4: Auth Pages (Web — Session-Based)

**Files:**
- Create: `app/Http/Controllers/Auth/WebLoginController.php`
- Create: `app/Http/Controllers/Auth/WebRegisterController.php`
- Create: `resources/js/Pages/Auth/Login.vue`
- Create: `resources/js/Pages/Auth/Register.vue`
- Modify: `routes/web.php`
- Create: `tests/Feature/Web/AuthPageTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Web/AuthPageTest.php
<?php

namespace Tests\Feature\Web;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Auth/Login')
        );
    }

    public function test_register_page_renders(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Auth/Register')
        );
    }

    public function test_user_can_login_via_web(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('Password123!'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->post('/login', [
            'email' => 'test@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        User::withoutGlobalScopes()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('Password123!'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->post('/login', [
            'email' => 'test@test.com',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_register_via_web(): void
    {
        $response = $this->post('/register', [
            'company_name' => 'New Company',
            'name' => 'Jane Admin',
            'email' => 'jane@newco.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone' => 'America/Chicago',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $this->assertDatabaseHas('tenants', ['name' => 'New Company']);
        $this->assertDatabaseHas('users', ['email' => 'jane@newco.com', 'role' => 'admin']);
    }

    public function test_user_can_logout(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Web/AuthPageTest.php`
Expected: FAIL — routes and controllers do not exist.

- [ ] **Step 3: Create WebLoginController**

```php
// app/Http/Controllers/Auth/WebLoginController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WebLoginController extends Controller
{
    public function showLoginForm(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Use withoutGlobalScopes to find user across tenants for login
        $user = User::withoutGlobalScopes()
            ->where('email', $credentials['email'])
            ->first();

        if ($user && Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
```

- [ ] **Step 4: Create WebRegisterController**

```php
// app/Http/Controllers/Auth/WebRegisterController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class WebRegisterController extends Controller
{
    public function showRegistrationForm(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'name' => $validated['company_name'],
                'timezone' => $validated['timezone'] ?? 'America/New_York',
                'workweek_start_day' => 1,
                'plan' => 'business',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            return User::withoutGlobalScopes()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'tenant_id' => $tenant->id,
                'role' => 'admin',
            ]);
        });

        Auth::login($user);

        return redirect('/dashboard');
    }
}
```

- [ ] **Step 5: Create Login.vue**

```vue
<!-- resources/js/Pages/Auth/Login.vue -->
<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Input from '@/Components/Input.vue';
import Alert from '@/Components/Alert.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">GeoTime</h1>
                <p class="mt-2 text-sm text-gray-600">Sign in to your account</p>
            </div>

            <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
                <Alert v-if="form.errors.email && !form.hasErrors" type="error" class="mb-4">
                    {{ form.errors.email }}
                </Alert>

                <form @submit.prevent="submit" class="space-y-5">
                    <Input
                        v-model="form.email"
                        label="Email address"
                        type="email"
                        placeholder="you@company.com"
                        :error="form.errors.email"
                        required
                    />

                    <Input
                        v-model="form.password"
                        label="Password"
                        type="password"
                        placeholder="Enter your password"
                        :error="form.errors.password"
                        required
                    />

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            Remember me
                        </label>
                    </div>

                    <Button
                        type="submit"
                        variant="primary"
                        size="md"
                        :loading="form.processing"
                        :disabled="form.processing"
                        class="w-full"
                    >
                        Sign in
                    </Button>
                </form>

                <p class="mt-6 text-center text-sm text-gray-600">
                    Don't have an account?
                    <a href="/register" class="font-medium text-blue-600 hover:text-blue-500">
                        Start your free trial
                    </a>
                </p>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 6: Create Register.vue**

```vue
<!-- resources/js/Pages/Auth/Register.vue -->
<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Input from '@/Components/Input.vue';
import Select from '@/Components/Select.vue';

const form = useForm({
    company_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    timezone: 'America/New_York',
});

const timezones = [
    { value: 'America/New_York', label: 'Eastern Time (ET)' },
    { value: 'America/Chicago', label: 'Central Time (CT)' },
    { value: 'America/Denver', label: 'Mountain Time (MT)' },
    { value: 'America/Los_Angeles', label: 'Pacific Time (PT)' },
    { value: 'America/Anchorage', label: 'Alaska Time (AKT)' },
    { value: 'Pacific/Honolulu', label: 'Hawaii Time (HT)' },
    { value: 'UTC', label: 'UTC' },
];

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">GeoTime</h1>
                <p class="mt-2 text-sm text-gray-600">Start your 14-day free trial</p>
            </div>

            <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
                <form @submit.prevent="submit" class="space-y-5">
                    <Input
                        v-model="form.company_name"
                        label="Company name"
                        placeholder="Acme Construction"
                        :error="form.errors.company_name"
                        required
                    />

                    <Input
                        v-model="form.name"
                        label="Your name"
                        placeholder="John Smith"
                        :error="form.errors.name"
                        required
                    />

                    <Input
                        v-model="form.email"
                        label="Email address"
                        type="email"
                        placeholder="you@company.com"
                        :error="form.errors.email"
                        required
                    />

                    <Input
                        v-model="form.password"
                        label="Password"
                        type="password"
                        placeholder="At least 8 characters"
                        :error="form.errors.password"
                        required
                    />

                    <Input
                        v-model="form.password_confirmation"
                        label="Confirm password"
                        type="password"
                        placeholder="Confirm your password"
                        required
                    />

                    <Select
                        v-model="form.timezone"
                        label="Timezone"
                        :options="timezones"
                        :error="form.errors.timezone"
                    />

                    <Button
                        type="submit"
                        variant="primary"
                        size="md"
                        :loading="form.processing"
                        :disabled="form.processing"
                        class="w-full"
                    >
                        Create account
                    </Button>
                </form>

                <p class="mt-6 text-center text-sm text-gray-600">
                    Already have an account?
                    <a href="/login" class="font-medium text-blue-600 hover:text-blue-500">
                        Sign in
                    </a>
                </p>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 7: Update web routes**

```php
// routes/web.php
<?php

use App\Http\Controllers\Auth\WebLoginController;
use App\Http\Controllers\Auth\WebRegisterController;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebLoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [WebLoginController::class, 'login']);
    Route::get('/register', [WebRegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [WebRegisterController::class, 'register']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [WebLoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', function () {
        return \Inertia\Inertia::render('Dashboard');
    })->name('dashboard');
});

// Redirect root to dashboard
Route::get('/', function () {
    return redirect('/dashboard');
});
```

- [ ] **Step 8: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Web/AuthPageTest.php`
Expected: All 5 tests PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Auth/WebLoginController.php app/Http/Controllers/Auth/WebRegisterController.php resources/js/Pages/Auth/ routes/web.php tests/Feature/Web/AuthPageTest.php
git commit -m "feat: add Inertia auth pages (login, register, logout) with session-based web auth"
```

---

## Task 5: Dashboard Overview Page

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Replace: `resources/js/Pages/Dashboard.vue`
- Create: `tests/Feature/Web/DashboardPageTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Web/DashboardPageTest.php
<?php

namespace Tests\Feature\Web;

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $tenant);

        return [$tenant, $user];
    }

    public function test_dashboard_page_renders_for_authenticated_user(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard')
                ->has('stats')
                ->has('stats.total_employees')
                ->has('stats.clocked_in_now')
                ->has('stats.overtime_alerts')
                ->has('stats.pending_approvals')
                ->has('recent_activity')
                ->has('alerts')
        );
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_stats_reflect_tenant_data(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        // Create employees for this tenant
        Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Alice',
            'last_name' => 'Worker',
            'email' => 'alice@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Bob',
            'last_name' => 'Worker',
            'email' => 'bob@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 22.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard')
                ->where('stats.total_employees', 2)
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Web/DashboardPageTest.php`
Expected: FAIL — DashboardController does not exist, Dashboard.vue does not return expected props.

- [ ] **Step 3: Create DashboardController**

```php
// app/Http/Controllers/DashboardController.php
<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tenant = app('current_tenant');

        // Stats
        $totalEmployees = Employee::where('status', 'ACTIVE')->count();
        $clockedInNow = TimeEntry::whereNull('clock_out')
            ->whereDate('clock_in', today())
            ->count();
        $overtimeAlerts = TimeEntry::where('status', 'ACTIVE')
            ->where('overtime_hours', '>', 0)
            ->whereDate('clock_in', '>=', now()->startOfWeek())
            ->count();
        $pendingApprovals = Transfer::where('status', 'PENDING')->count()
            + TimeEntry::where('status', 'SUBMITTED')->count();

        // Recent activity — last 20 time entries today
        $recentActivity = TimeEntry::with(['employee:id,first_name,last_name', 'job:id,name'])
            ->whereDate('clock_in', today())
            ->orderByDesc('clock_in')
            ->limit(20)
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'employee_name' => $entry->employee
                    ? $entry->employee->first_name . ' ' . $entry->employee->last_name
                    : 'Unknown',
                'job_name' => $entry->job?->name ?? 'N/A',
                'action' => $entry->clock_out ? 'clocked_out' : 'clocked_in',
                'time' => $entry->clock_out?->format('g:i A') ?? $entry->clock_in->format('g:i A'),
                'method' => $entry->clock_method,
            ]);

        // Alerts — pending transfers + overtime + missed clock-outs
        $alerts = [];

        $pendingTransfers = Transfer::with(['employee:id,first_name,last_name'])
            ->where('status', 'PENDING')
            ->latest()
            ->limit(5)
            ->get();

        foreach ($pendingTransfers as $transfer) {
            $alerts[] = [
                'id' => $transfer->id,
                'type' => 'transfer',
                'severity' => 'warning',
                'message' => ($transfer->employee
                    ? $transfer->employee->first_name . ' ' . $transfer->employee->last_name
                    : 'Employee') . ' has a pending transfer request',
                'time' => $transfer->created_at->diffForHumans(),
            ];
        }

        // Missed clock-outs (clocked in more than 12 hours ago)
        $missedClockouts = TimeEntry::with(['employee:id,first_name,last_name'])
            ->whereNull('clock_out')
            ->where('clock_in', '<', now()->subHours(12))
            ->limit(5)
            ->get();

        foreach ($missedClockouts as $entry) {
            $alerts[] = [
                'id' => $entry->id,
                'type' => 'missed_clockout',
                'severity' => 'error',
                'message' => ($entry->employee
                    ? $entry->employee->first_name . ' ' . $entry->employee->last_name
                    : 'Employee') . ' may have missed clock-out',
                'time' => $entry->clock_in->diffForHumans(),
            ];
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_employees' => $totalEmployees,
                'clocked_in_now' => $clockedInNow,
                'overtime_alerts' => $overtimeAlerts,
                'pending_approvals' => $pendingApprovals,
            ],
            'recent_activity' => $recentActivity,
            'alerts' => $alerts,
        ]);
    }
}
```

- [ ] **Step 4: Replace Dashboard.vue**

```vue
<!-- resources/js/Pages/Dashboard.vue -->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatsCard from '@/Components/StatsCard.vue';
import Badge from '@/Components/Badge.vue';
import Alert from '@/Components/Alert.vue';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_employees: 0,
            clocked_in_now: 0,
            overtime_alerts: 0,
            pending_approvals: 0,
        }),
    },
    recent_activity: { type: Array, default: () => [] },
    alerts: { type: Array, default: () => [] },
});

const actionLabel = (action) => {
    return action === 'clocked_in' ? 'Clocked In' : 'Clocked Out';
};

const actionVariant = (action) => {
    return action === 'clocked_in' ? 'green' : 'gray';
};

const alertVariant = (severity) => {
    const map = { error: 'error', warning: 'warning', info: 'info' };
    return map[severity] || 'info';
};
</script>

<template>
    <AppLayout>
        <template #header>Dashboard</template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <StatsCard
                title="Total Employees"
                :value="stats.total_employees"
                color="blue"
            />
            <StatsCard
                title="Clocked In Now"
                :value="stats.clocked_in_now"
                color="green"
            />
            <StatsCard
                title="Overtime Alerts"
                :value="stats.overtime_alerts"
                color="red"
            />
            <StatsCard
                title="Pending Approvals"
                :value="stats.pending_approvals"
                color="yellow"
            />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Activity Feed -->
            <div class="lg:col-span-2 rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Today's Activity</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <div
                        v-for="entry in recent_activity"
                        :key="entry.id"
                        class="flex items-center justify-between px-6 py-3"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600">
                                {{ entry.employee_name.charAt(0) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ entry.employee_name }}</p>
                                <p class="text-xs text-gray-500">{{ entry.job_name }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Badge :variant="actionVariant(entry.action)" size="sm">
                                {{ actionLabel(entry.action) }}
                            </Badge>
                            <span class="text-xs text-gray-400">{{ entry.time }}</span>
                        </div>
                    </div>
                    <div v-if="recent_activity.length === 0" class="px-6 py-12 text-center text-sm text-gray-400">
                        No activity today yet.
                    </div>
                </div>
            </div>

            <!-- Alerts Panel -->
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Alerts</h2>
                </div>
                <div class="p-4 space-y-3">
                    <Alert
                        v-for="alert in alerts"
                        :key="alert.id"
                        :type="alertVariant(alert.severity)"
                    >
                        <p class="text-sm">{{ alert.message }}</p>
                        <p class="text-xs opacity-70 mt-1">{{ alert.time }}</p>
                    </Alert>
                    <div v-if="alerts.length === 0" class="py-8 text-center text-sm text-gray-400">
                        No alerts at this time.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 5: Update web routes to use DashboardController**

In `routes/web.php`, replace the inline dashboard route:

```php
// Replace:
// Route::get('/dashboard', function () {
//     return \Inertia\Inertia::render('Dashboard');
// })->name('dashboard');

// With:
Route::get('/dashboard', \App\Http\Controllers\DashboardController::class)->name('dashboard');
```

- [ ] **Step 6: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Web/DashboardPageTest.php`
Expected: All 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/DashboardController.php resources/js/Pages/Dashboard.vue routes/web.php tests/Feature/Web/DashboardPageTest.php
git commit -m "feat: add dashboard overview page with stats cards, activity feed, and alerts panel"
```

---

## Task 6: Employee Management Pages

**Files:**
- Create: `app/Http/Controllers/Web/EmployeePageController.php`
- Create: `resources/js/Pages/Employees/Index.vue`
- Create: `resources/js/Pages/Employees/Create.vue`
- Create: `resources/js/Pages/Employees/Edit.vue`
- Create: `resources/js/Pages/Employees/Import.vue`
- Modify: `routes/web.php`
- Create: `tests/Feature/Web/EmployeePageTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Web/EmployeePageTest.php
<?php

namespace Tests\Feature\Web;

use App\Models\Employee;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeePageTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $tenant);

        return [$tenant, $user];
    }

    public function test_employee_index_page_renders(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->get('/employees');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Employees/Index')
                ->has('employees.data', 1)
                ->has('employees.data.0.first_name')
        );
    }

    public function test_employee_create_page_renders(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user)->get('/employees/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Employees/Create')
                ->has('teams')
                ->has('roles')
        );
    }

    public function test_can_create_employee_via_web(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user)->post('/employees', [
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@test.com',
            'phone' => '555-0101',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 25.00,
            'hire_date' => '2026-03-01',
        ]);

        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', [
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@test.com',
        ]);
    }

    public function test_employee_edit_page_renders(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $employee = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->get("/employees/{$employee->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Employees/Edit')
                ->has('employee')
                ->where('employee.id', $employee->id)
        );
    }

    public function test_can_update_employee_via_web(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $employee = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->put("/employees/{$employee->id}", [
            'first_name' => 'Janet',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'role' => 'TEAM_LEAD',
            'hourly_rate' => 28.00,
            'hire_date' => '2026-01-01',
        ]);

        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'Janet',
            'role' => 'TEAM_LEAD',
        ]);
    }

    public function test_employee_import_page_renders(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user)->get('/employees/import');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Employees/Import')
        );
    }

    public function test_can_import_employees_via_csv(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $csv = "first_name,last_name,email,role,hourly_rate,hire_date\n";
        $csv .= "Alice,Smith,alice@test.com,EMPLOYEE,25.00,2026-01-15\n";
        $csv .= "Bob,Jones,bob@test.com,EMPLOYEE,22.50,2026-02-01\n";

        $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

        $response = $this->actingAs($user)->post('/employees/import', [
            'csv_file' => $file,
        ]);

        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['email' => 'alice@test.com']);
        $this->assertDatabaseHas('employees', ['email' => 'bob@test.com']);
    }

    public function test_employees_page_requires_authentication(): void
    {
        $response = $this->get('/employees');
        $response->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Web/EmployeePageTest.php`
Expected: FAIL — controller and routes do not exist.

- [ ] **Step 3: Create EmployeePageController**

```php
// app/Http/Controllers/Web/EmployeePageController.php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeePageController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Employee::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($sort = $request->input('sort')) {
            $direction = $request->input('direction', 'asc');
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('last_name');
        }

        $employees = $query->with('team:id,name,color_tag')->paginate(15)->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Employees/Create', [
            'teams' => Team::select('id', 'name', 'color_tag')->where('status', 'ACTIVE')->get(),
            'roles' => ['EMPLOYEE', 'TEAM_LEAD', 'MANAGER', 'ADMIN'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:employees,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['EMPLOYEE', 'TEAM_LEAD', 'MANAGER', 'ADMIN'])],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'hire_date' => ['required', 'date'],
            'current_team_id' => ['nullable', 'exists:teams,id'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'array'],
        ]);

        $validated['status'] = 'ACTIVE';

        Employee::create($validated);

        return redirect('/employees')->with('success', 'Employee created successfully.');
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('Employees/Edit', [
            'employee' => $employee->load('team:id,name'),
            'teams' => Team::select('id', 'name', 'color_tag')->where('status', 'ACTIVE')->get(),
            'roles' => ['EMPLOYEE', 'TEAM_LEAD', 'MANAGER', 'ADMIN'],
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('employees')->ignore($employee->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['EMPLOYEE', 'TEAM_LEAD', 'MANAGER', 'ADMIN'])],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'hire_date' => ['required', 'date'],
            'current_team_id' => ['nullable', 'exists:teams,id'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE', 'TERMINATED'])],
        ]);

        $employee->update($validated);

        return redirect('/employees')->with('success', 'Employee updated successfully.');
    }

    public function importForm(): Response
    {
        return Inertia::render('Employees/Import');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);

        $imported = 0;
        $errors = [];

        while ($row = fgetcsv($handle)) {
            $data = array_combine($header, $row);

            try {
                Employee::create([
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                    'email' => $data['email'] ?? '',
                    'role' => strtoupper($data['role'] ?? 'EMPLOYEE'),
                    'hourly_rate' => $data['hourly_rate'] ?? 0,
                    'hire_date' => $data['hire_date'] ?? now()->toDateString(),
                    'status' => 'ACTIVE',
                    'phone' => $data['phone'] ?? null,
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($imported + count($errors) + 2) . ": " . $e->getMessage();
            }
        }

        fclose($handle);

        $message = "Imported {$imported} employees.";
        if (count($errors) > 0) {
            $message .= " " . count($errors) . " rows had errors.";
        }

        return redirect('/employees')->with('success', $message);
    }
}
```

- [ ] **Step 4: Create Employees/Index.vue**

```vue
<!-- resources/js/Pages/Employees/Index.vue -->
<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Button from '@/Components/Button.vue';
import Badge from '@/Components/Badge.vue';

const props = defineProps({
    employees: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const columns = [
    { key: 'last_name', label: 'Name', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'role', label: 'Role', sortable: true },
    { key: 'hourly_rate', label: 'Rate', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'hire_date', label: 'Hire Date', sortable: true },
];

const statusVariant = (status) => {
    const map = { ACTIVE: 'green', INACTIVE: 'yellow', TERMINATED: 'red' };
    return map[status] || 'gray';
};

const roleLabel = (role) => {
    const map = {
        EMPLOYEE: 'Employee',
        TEAM_LEAD: 'Team Lead',
        MANAGER: 'Manager',
        ADMIN: 'Admin',
        SUPER_ADMIN: 'Super Admin',
    };
    return map[role] || role;
};
</script>

<template>
    <AppLayout>
        <template #header>Employees</template>

        <DataTable
            :columns="columns"
            :rows="employees.data"
            :pagination="employees"
            :filters="filters"
            base-url="/employees"
            search-placeholder="Search employees..."
        >
            <template #actions>
                <Link href="/employees/import">
                    <Button variant="secondary" size="sm">Import CSV</Button>
                </Link>
                <Link href="/employees/create">
                    <Button variant="primary" size="sm">Add Employee</Button>
                </Link>
            </template>

            <template #cell-last_name="{ row }">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-600">
                        {{ row.first_name?.charAt(0) }}{{ row.last_name?.charAt(0) }}
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">{{ row.first_name }} {{ row.last_name }}</p>
                        <p v-if="row.team" class="text-xs text-gray-500">{{ row.team.name }}</p>
                    </div>
                </div>
            </template>

            <template #cell-role="{ value }">
                <Badge variant="indigo" size="sm">{{ roleLabel(value) }}</Badge>
            </template>

            <template #cell-hourly_rate="{ value }">
                ${{ Number(value).toFixed(2) }}
            </template>

            <template #cell-status="{ value }">
                <Badge :variant="statusVariant(value)" size="sm">{{ value }}</Badge>
            </template>

            <template #row-actions="{ row }">
                <Link :href="`/employees/${row.id}/edit`" class="text-sm text-blue-600 hover:text-blue-800">
                    Edit
                </Link>
            </template>
        </DataTable>
    </AppLayout>
</template>
```

- [ ] **Step 5: Create Employees/Create.vue**

```vue
<!-- resources/js/Pages/Employees/Create.vue -->
<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/Button.vue';
import Input from '@/Components/Input.vue';
import Select from '@/Components/Select.vue';

const props = defineProps({
    teams: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    role: 'EMPLOYEE',
    hourly_rate: '',
    hire_date: '',
    current_team_id: '',
    date_of_birth: '',
});

const roleOptions = props.roles.map(r => ({ value: r, label: r.replace('_', ' ') }));
const teamOptions = props.teams.map(t => ({ value: t.id, label: t.name }));

const submit = () => {
    form.post('/employees');
};
</script>

<template>
    <AppLayout>
        <template #header>Add Employee</template>

        <div class="max-w-2xl">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <Input
                            v-model="form.first_name"
                            label="First name"
                            :error="form.errors.first_name"
                            required
                        />
                        <Input
                            v-model="form.last_name"
                            label="Last name"
                            :error="form.errors.last_name"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <Input
                            v-model="form.email"
                            label="Email"
                            type="email"
                            :error="form.errors.email"
                            required
                        />
                        <Input
                            v-model="form.phone"
                            label="Phone"
                            :error="form.errors.phone"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <Select
                            v-model="form.role"
                            label="Role"
                            :options="roleOptions"
                            :error="form.errors.role"
                            required
                        />
                        <Input
                            v-model="form.hourly_rate"
                            label="Hourly rate ($)"
                            type="number"
                            :error="form.errors.hourly_rate"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <Input
                            v-model="form.hire_date"
                            label="Hire date"
                            type="date"
                            :error="form.errors.hire_date"
                            required
                        />
                        <Input
                            v-model="form.date_of_birth"
                            label="Date of birth"
                            type="date"
                            :error="form.errors.date_of_birth"
                        />
                    </div>

                    <Select
                        v-model="form.current_team_id"
                        label="Team (optional)"
                        :options="teamOptions"
                        placeholder="No team assigned"
                        :error="form.errors.current_team_id"
                    />

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <Link href="/employees">
                            <Button variant="secondary">Cancel</Button>
                        </Link>
                        <Button type="submit" :loading="form.processing" :disabled="form.processing">
                            Create Employee
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 6: Create Employees/Edit.vue**

```vue
<!-- resources/js/Pages/Employees/Edit.vue -->
<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/Button.vue';
import Input from '@/Components/Input.vue';
import Select from '@/Components/Select.vue';

const props = defineProps({
    employee: { type: Object, required: true },
    teams: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
});

const form = useForm({
    first_name: props.employee.first_name,
    last_name: props.employee.last_name,
    email: props.employee.email,
    phone: props.employee.phone || '',
    role: props.employee.role,
    hourly_rate: props.employee.hourly_rate,
    hire_date: props.employee.hire_date,
    current_team_id: props.employee.current_team_id || '',
    date_of_birth: props.employee.date_of_birth || '',
    status: props.employee.status,
});

const roleOptions = props.roles.map(r => ({ value: r, label: r.replace('_', ' ') }));
const teamOptions = props.teams.map(t => ({ value: t.id, label: t.name }));
const statusOptions = [
    { value: 'ACTIVE', label: 'Active' },
    { value: 'INACTIVE', label: 'Inactive' },
    { value: 'TERMINATED', label: 'Terminated' },
];

const submit = () => {
    form.put(`/employees/${props.employee.id}`);
};
</script>

<template>
    <AppLayout>
        <template #header>Edit Employee: {{ employee.first_name }} {{ employee.last_name }}</template>

        <div class="max-w-2xl">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <Input
                            v-model="form.first_name"
                            label="First name"
                            :error="form.errors.first_name"
                            required
                        />
                        <Input
                            v-model="form.last_name"
                            label="Last name"
                            :error="form.errors.last_name"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <Input
                            v-model="form.email"
                            label="Email"
                            type="email"
                            :error="form.errors.email"
                            required
                        />
                        <Input
                            v-model="form.phone"
                            label="Phone"
                            :error="form.errors.phone"
                        />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <Select
                            v-model="form.role"
                            label="Role"
                            :options="roleOptions"
                            :error="form.errors.role"
                            required
                        />
                        <Input
                            v-model="form.hourly_rate"
                            label="Hourly rate ($)"
                            type="number"
                            :error="form.errors.hourly_rate"
                            required
                        />
                        <Select
                            v-model="form.status"
                            label="Status"
                            :options="statusOptions"
                            :error="form.errors.status"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <Input
                            v-model="form.hire_date"
                            label="Hire date"
                            type="date"
                            :error="form.errors.hire_date"
                            required
                        />
                        <Input
                            v-model="form.date_of_birth"
                            label="Date of birth"
                            type="date"
                            :error="form.errors.date_of_birth"
                        />
                    </div>

                    <Select
                        v-model="form.current_team_id"
                        label="Team"
                        :options="teamOptions"
                        placeholder="No team assigned"
                        :error="form.errors.current_team_id"
                    />

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <Link href="/employees">
                            <Button variant="secondary">Cancel</Button>
                        </Link>
                        <Button type="submit" :loading="form.processing" :disabled="form.processing">
                            Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 7: Create Employees/Import.vue**

```vue
<!-- resources/js/Pages/Employees/Import.vue -->
<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/Button.vue';
import Alert from '@/Components/Alert.vue';

const form = useForm({
    csv_file: null,
});

const onFileChange = (event) => {
    form.csv_file = event.target.files[0];
};

const submit = () => {
    form.post('/employees/import', {
        forceFormData: true,
    });
};
</script>

<template>
    <AppLayout>
        <template #header>Import Employees</template>

        <div class="max-w-2xl">
            <Alert type="info" class="mb-6">
                Upload a CSV file with the following columns:
                <strong>first_name, last_name, email, role, hourly_rate, hire_date</strong>.
                Optional columns: <strong>phone</strong>.
            </Alert>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CSV File</label>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            @change="onFileChange"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        />
                        <p v-if="form.errors.csv_file" class="mt-1 text-xs text-red-600">{{ form.errors.csv_file }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <Link href="/employees">
                            <Button variant="secondary">Cancel</Button>
                        </Link>
                        <Button
                            type="submit"
                            :loading="form.processing"
                            :disabled="form.processing || !form.csv_file"
                        >
                            Import
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Sample CSV template -->
            <div class="mt-6 rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200">
                <p class="text-sm font-medium text-gray-700 mb-2">Sample CSV format:</p>
                <pre class="text-xs text-gray-500 overflow-x-auto">first_name,last_name,email,role,hourly_rate,hire_date
John,Smith,john@example.com,EMPLOYEE,25.00,2026-01-15
Jane,Doe,jane@example.com,TEAM_LEAD,30.00,2026-02-01</pre>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 8: Add employee web routes**

Add to `routes/web.php` inside the authenticated group:

```php
use App\Http\Controllers\Web\EmployeePageController;

// Inside Route::middleware('auth')->group(function () { ... });
Route::get('/employees', [EmployeePageController::class, 'index'])->name('employees.index');
Route::get('/employees/create', [EmployeePageController::class, 'create'])->name('employees.create');
Route::post('/employees', [EmployeePageController::class, 'store'])->name('employees.store');
Route::get('/employees/import', [EmployeePageController::class, 'importForm'])->name('employees.import');
Route::post('/employees/import', [EmployeePageController::class, 'import'])->name('employees.import.store');
Route::get('/employees/{employee}/edit', [EmployeePageController::class, 'edit'])->name('employees.edit');
Route::put('/employees/{employee}', [EmployeePageController::class, 'update'])->name('employees.update');
```

- [ ] **Step 9: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Web/EmployeePageTest.php`
Expected: All 7 tests PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Web/EmployeePageController.php resources/js/Pages/Employees/ routes/web.php tests/Feature/Web/EmployeePageTest.php
git commit -m "feat: add employee management pages (index, create, edit, CSV import)"
```

---

## Task 7: Team Management Pages

**Files:**
- Create: `app/Http/Controllers/Web/TeamPageController.php`
- Create: `resources/js/Pages/Teams/Index.vue`
- Create: `resources/js/Pages/Teams/Create.vue`
- Create: `resources/js/Pages/Teams/Edit.vue`
- Create: `resources/js/Pages/Teams/Members.vue`
- Modify: `routes/web.php`
- Create: `tests/Feature/Web/TeamPageTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Web/TeamPageTest.php
<?php

namespace Tests\Feature\Web;

use App\Models\Employee;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPageTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $tenant);

        return [$tenant, $user];
    }

    public function test_team_index_page_renders(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        Team::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Alpha Team',
            'color_tag' => '#3B82F6',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->get('/teams');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Teams/Index')
                ->has('teams.data', 1)
                ->has('teams.data.0.name')
        );
    }

    public function test_team_create_page_renders(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user)->get('/teams/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Teams/Create')
                ->has('employees')
        );
    }

    public function test_can_create_team_via_web(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user)->post('/teams', [
            'name' => 'Bravo Team',
            'description' => 'Second team',
            'color_tag' => '#EF4444',
        ]);

        $response->assertRedirect('/teams');
        $this->assertDatabaseHas('teams', [
            'name' => 'Bravo Team',
            'color_tag' => '#EF4444',
        ]);
    }

    public function test_team_edit_page_renders(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $team = Team::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Alpha Team',
            'color_tag' => '#3B82F6',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->get("/teams/{$team->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Teams/Edit')
                ->has('team')
                ->where('team.id', $team->id)
        );
    }

    public function test_can_update_team_via_web(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $team = Team::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Alpha Team',
            'color_tag' => '#3B82F6',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->put("/teams/{$team->id}", [
            'name' => 'Alpha Team Updated',
            'description' => 'Updated description',
            'color_tag' => '#22C55E',
        ]);

        $response->assertRedirect('/teams');
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Alpha Team Updated',
            'color_tag' => '#22C55E',
        ]);
    }

    public function test_team_members_page_renders(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $team = Team::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Alpha Team',
            'color_tag' => '#3B82F6',
            'status' => 'ACTIVE',
        ]);

        Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Alice',
            'last_name' => 'Worker',
            'email' => 'alice@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
            'current_team_id' => $team->id,
        ]);

        $response = $this->actingAs($user)->get("/teams/{$team->id}/members");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Teams/Members')
                ->has('team')
                ->has('members')
                ->has('available_employees')
        );
    }

    public function test_can_assign_team_lead(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $team = Team::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Alpha Team',
            'color_tag' => '#3B82F6',
            'status' => 'ACTIVE',
        ]);

        $employee = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Alice',
            'last_name' => 'Worker',
            'email' => 'alice@test.com',
            'role' => 'TEAM_LEAD',
            'hourly_rate' => 25.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
            'current_team_id' => $team->id,
        ]);

        $response = $this->actingAs($user)->put("/teams/{$team->id}", [
            'name' => 'Alpha Team',
            'color_tag' => '#3B82F6',
            'lead_employee_id' => $employee->id,
        ]);

        $response->assertRedirect('/teams');
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'lead_employee_id' => $employee->id,
        ]);
    }

    public function test_teams_page_requires_authentication(): void
    {
        $response = $this->get('/teams');
        $response->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Web/TeamPageTest.php`
Expected: FAIL — controller and routes do not exist.

- [ ] **Step 3: Create TeamPageController**

```php
// app/Http/Controllers/Web/TeamPageController.php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamPageController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Team::withCount(['employees' => function ($q) {
            $q->where('status', 'ACTIVE');
        }]);

        if ($search = $request->input('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        if ($sort = $request->input('sort')) {
            $direction = $request->input('direction', 'asc');
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('name');
        }

        $teams = $query->with('lead:id,first_name,last_name')->paginate(15)->withQueryString();

        return Inertia::render('Teams/Index', [
            'teams' => $teams,
            'filters' => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Teams/Create', [
            'employees' => Employee::select('id', 'first_name', 'last_name')
                ->where('status', 'ACTIVE')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color_tag' => ['required', 'string', 'max:7'],
            'lead_employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        $validated['status'] = 'ACTIVE';

        Team::create($validated);

        return redirect('/teams')->with('success', 'Team created successfully.');
    }

    public function edit(Team $team): Response
    {
        return Inertia::render('Teams/Edit', [
            'team' => $team->load('lead:id,first_name,last_name'),
            'employees' => Employee::select('id', 'first_name', 'last_name')
                ->where('status', 'ACTIVE')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color_tag' => ['required', 'string', 'max:7'],
            'lead_employee_id' => ['nullable', 'exists:employees,id'],
            'status' => ['sometimes', 'in:ACTIVE,ARCHIVED'],
        ]);

        $team->update($validated);

        return redirect('/teams')->with('success', 'Team updated successfully.');
    }

    public function members(Team $team): Response
    {
        $members = Employee::where('current_team_id', $team->id)
            ->where('status', 'ACTIVE')
            ->orderBy('last_name')
            ->get();

        $availableEmployees = Employee::where('status', 'ACTIVE')
            ->where(function ($q) use ($team) {
                $q->whereNull('current_team_id')
                  ->orWhere('current_team_id', '!=', $team->id);
            })
            ->orderBy('last_name')
            ->get();

        return Inertia::render('Teams/Members', [
            'team' => $team->load('lead:id,first_name,last_name'),
            'members' => $members,
            'available_employees' => $availableEmployees,
        ]);
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
        ]);

        Employee::where('id', $validated['employee_id'])->update([
            'current_team_id' => $team->id,
        ]);

        return back()->with('success', 'Member added to team.');
    }

    public function removeMember(Team $team, Employee $employee): RedirectResponse
    {
        if ($employee->current_team_id === $team->id) {
            $employee->update(['current_team_id' => null]);
        }

        return back()->with('success', 'Member removed from team.');
    }
}
```

- [ ] **Step 4: Create Teams/Index.vue**

```vue
<!-- resources/js/Pages/Teams/Index.vue -->
<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Button from '@/Components/Button.vue';
import Badge from '@/Components/Badge.vue';

const props = defineProps({
    teams: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const columns = [
    { key: 'name', label: 'Team', sortable: true },
    { key: 'lead', label: 'Team Lead', sortable: false },
    { key: 'employees_count', label: 'Members', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
];
</script>

<template>
    <AppLayout>
        <template #header>Teams</template>

        <DataTable
            :columns="columns"
            :rows="teams.data"
            :pagination="teams"
            :filters="filters"
            base-url="/teams"
            search-placeholder="Search teams..."
        >
            <template #actions>
                <Link href="/teams/create">
                    <Button variant="primary" size="sm">Create Team</Button>
                </Link>
            </template>

            <template #cell-name="{ row }">
                <div class="flex items-center gap-3">
                    <div
                        class="h-3 w-3 rounded-full flex-shrink-0"
                        :style="{ backgroundColor: row.color_tag }"
                    />
                    <span class="font-medium text-gray-900">{{ row.name }}</span>
                </div>
            </template>

            <template #cell-lead="{ row }">
                <span v-if="row.lead" class="text-gray-700">
                    {{ row.lead.first_name }} {{ row.lead.last_name }}
                </span>
                <span v-else class="text-gray-400">No lead assigned</span>
            </template>

            <template #cell-employees_count="{ value }">
                <Badge variant="blue" size="sm">{{ value }} members</Badge>
            </template>

            <template #cell-status="{ value }">
                <Badge :variant="value === 'ACTIVE' ? 'green' : 'gray'" size="sm">{{ value }}</Badge>
            </template>

            <template #row-actions="{ row }">
                <div class="flex items-center gap-3">
                    <Link :href="`/teams/${row.id}/members`" class="text-sm text-gray-600 hover:text-gray-800">
                        Members
                    </Link>
                    <Link :href="`/teams/${row.id}/edit`" class="text-sm text-blue-600 hover:text-blue-800">
                        Edit
                    </Link>
                </div>
            </template>
        </DataTable>
    </AppLayout>
</template>
```

- [ ] **Step 5: Create Teams/Create.vue**

```vue
<!-- resources/js/Pages/Teams/Create.vue -->
<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/Button.vue';
import Input from '@/Components/Input.vue';
import Select from '@/Components/Select.vue';
import ColorPicker from '@/Components/ColorPicker.vue';

const props = defineProps({
    employees: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    description: '',
    color_tag: '#3B82F6',
    lead_employee_id: '',
});

const employeeOptions = props.employees.map(e => ({
    value: e.id,
    label: `${e.first_name} ${e.last_name}`,
}));

const submit = () => {
    form.post('/teams');
};
</script>

<template>
    <AppLayout>
        <template #header>Create Team</template>

        <div class="max-w-2xl">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <form @submit.prevent="submit" class="space-y-5">
                    <Input
                        v-model="form.name"
                        label="Team name"
                        placeholder="e.g. Alpha Team"
                        :error="form.errors.name"
                        required
                    />

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Optional team description"
                        />
                    </div>

                    <ColorPicker
                        v-model="form.color_tag"
                        label="Team color"
                        :error="form.errors.color_tag"
                    />

                    <Select
                        v-model="form.lead_employee_id"
                        label="Team Lead (optional)"
                        :options="employeeOptions"
                        placeholder="Select team lead..."
                        :error="form.errors.lead_employee_id"
                    />

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <Link href="/teams">
                            <Button variant="secondary">Cancel</Button>
                        </Link>
                        <Button type="submit" :loading="form.processing" :disabled="form.processing">
                            Create Team
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 6: Create Teams/Edit.vue**

```vue
<!-- resources/js/Pages/Teams/Edit.vue -->
<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/Button.vue';
import Input from '@/Components/Input.vue';
import Select from '@/Components/Select.vue';
import ColorPicker from '@/Components/ColorPicker.vue';

const props = defineProps({
    team: { type: Object, required: true },
    employees: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.team.name,
    description: props.team.description || '',
    color_tag: props.team.color_tag,
    lead_employee_id: props.team.lead_employee_id || '',
    status: props.team.status,
});

const employeeOptions = props.employees.map(e => ({
    value: e.id,
    label: `${e.first_name} ${e.last_name}`,
}));

const statusOptions = [
    { value: 'ACTIVE', label: 'Active' },
    { value: 'ARCHIVED', label: 'Archived' },
];

const submit = () => {
    form.put(`/teams/${props.team.id}`);
};
</script>

<template>
    <AppLayout>
        <template #header>Edit Team: {{ team.name }}</template>

        <div class="max-w-2xl">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <Input
                                v-model="form.name"
                                label="Team name"
                                :error="form.errors.name"
                                required
                            />
                        </div>
                        <Select
                            v-model="form.status"
                            label="Status"
                            :options="statusOptions"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <ColorPicker
                        v-model="form.color_tag"
                        label="Team color"
                        :error="form.errors.color_tag"
                    />

                    <Select
                        v-model="form.lead_employee_id"
                        label="Team Lead"
                        :options="employeeOptions"
                        placeholder="Select team lead..."
                        :error="form.errors.lead_employee_id"
                    />

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <Link href="/teams">
                            <Button variant="secondary">Cancel</Button>
                        </Link>
                        <Button type="submit" :loading="form.processing" :disabled="form.processing">
                            Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 7: Create Teams/Members.vue**

```vue
<!-- resources/js/Pages/Teams/Members.vue -->
<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/Button.vue';
import Select from '@/Components/Select.vue';
import Badge from '@/Components/Badge.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    team: { type: Object, required: true },
    members: { type: Array, default: () => [] },
    available_employees: { type: Array, default: () => [] },
});

const showAddModal = ref(false);

const addForm = useForm({
    employee_id: '',
});

const employeeOptions = props.available_employees.map(e => ({
    value: e.id,
    label: `${e.first_name} ${e.last_name}`,
}));

const addMember = () => {
    addForm.post(`/teams/${props.team.id}/members`, {
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const removeMember = (employeeId) => {
    if (confirm('Remove this member from the team?')) {
        router.delete(`/teams/${props.team.id}/members/${employeeId}`);
    }
};
</script>

<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="h-4 w-4 rounded-full"
                    :style="{ backgroundColor: team.color_tag }"
                />
                {{ team.name }} — Members
            </div>
        </template>

        <div class="max-w-3xl">
            <!-- Team info bar -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <Badge variant="blue">{{ members.length }} members</Badge>
                    <span v-if="team.lead" class="text-sm text-gray-600">
                        Lead: {{ team.lead.first_name }} {{ team.lead.last_name }}
                    </span>
                </div>
                <Button variant="primary" size="sm" @click="showAddModal = true">
                    Add Member
                </Button>
            </div>

            <!-- Members list -->
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 divide-y divide-gray-100">
                <div
                    v-for="member in members"
                    :key="member.id"
                    class="flex items-center justify-between px-6 py-4"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-600">
                            {{ member.first_name?.charAt(0) }}{{ member.last_name?.charAt(0) }}
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">
                                {{ member.first_name }} {{ member.last_name }}
                            </p>
                            <p class="text-xs text-gray-500">{{ member.email }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <Badge
                            v-if="team.lead_employee_id === member.id"
                            variant="indigo"
                            size="sm"
                        >
                            Team Lead
                        </Badge>
                        <Badge variant="gray" size="sm">{{ member.role }}</Badge>
                        <button
                            @click="removeMember(member.id)"
                            class="text-sm text-red-500 hover:text-red-700"
                        >
                            Remove
                        </button>
                    </div>
                </div>

                <div v-if="members.length === 0" class="px-6 py-12 text-center text-sm text-gray-400">
                    No members in this team yet.
                </div>
            </div>

            <div class="mt-4">
                <Link href="/teams" class="text-sm text-gray-500 hover:text-gray-700">
                    &larr; Back to teams
                </Link>
            </div>
        </div>

        <!-- Add Member Modal -->
        <Modal :show="showAddModal" title="Add Team Member" @close="showAddModal = false">
            <form @submit.prevent="addMember" class="space-y-4">
                <Select
                    v-model="addForm.employee_id"
                    label="Select employee"
                    :options="employeeOptions"
                    placeholder="Choose an employee..."
                    :error="addForm.errors.employee_id"
                    required
                />
            </form>

            <template #footer>
                <Button variant="secondary" @click="showAddModal = false">Cancel</Button>
                <Button
                    @click="addMember"
                    :loading="addForm.processing"
                    :disabled="addForm.processing || !addForm.employee_id"
                >
                    Add to Team
                </Button>
            </template>
        </Modal>
    </AppLayout>
</template>
```

- [ ] **Step 8: Add team web routes**

Add to `routes/web.php` inside the authenticated group:

```php
use App\Http\Controllers\Web\TeamPageController;

// Inside Route::middleware('auth')->group(function () { ... });
Route::get('/teams', [TeamPageController::class, 'index'])->name('teams.index');
Route::get('/teams/create', [TeamPageController::class, 'create'])->name('teams.create');
Route::post('/teams', [TeamPageController::class, 'store'])->name('teams.store');
Route::get('/teams/{team}/edit', [TeamPageController::class, 'edit'])->name('teams.edit');
Route::put('/teams/{team}', [TeamPageController::class, 'update'])->name('teams.update');
Route::get('/teams/{team}/members', [TeamPageController::class, 'members'])->name('teams.members');
Route::post('/teams/{team}/members', [TeamPageController::class, 'addMember'])->name('teams.members.add');
Route::delete('/teams/{team}/members/{employee}', [TeamPageController::class, 'removeMember'])->name('teams.members.remove');
```

- [ ] **Step 9: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Web/TeamPageTest.php`
Expected: All 7 tests PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Web/TeamPageController.php resources/js/Pages/Teams/ routes/web.php tests/Feature/Web/TeamPageTest.php
git commit -m "feat: add team management pages (index, create, edit, members with add/remove)"
```

---

## Task 8: Transfer Management Pages

**Files:**
- Create: `app/Http/Controllers/Web/TransferPageController.php`
- Create: `resources/js/Pages/Transfers/Index.vue`
- Create: `resources/js/Pages/Transfers/Create.vue`
- Modify: `routes/web.php`
- Create: `tests/Feature/Web/TransferPageTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Web/TransferPageTest.php
<?php

namespace Tests\Feature\Web;

use App\Models\Employee;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferPageTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedAdmin(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'plan' => 'business',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        app()->instance('current_tenant', $tenant);

        return [$tenant, $user];
    }

    private function createTeamsAndEmployee($tenant): array
    {
        $teamA = Team::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Alpha',
            'color_tag' => '#3B82F6',
            'status' => 'ACTIVE',
        ]);

        $teamB = Team::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Bravo',
            'color_tag' => '#EF4444',
            'status' => 'ACTIVE',
        ]);

        $employee = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Alice',
            'last_name' => 'Worker',
            'email' => 'alice@test.com',
            'role' => 'EMPLOYEE',
            'hourly_rate' => 20.00,
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
            'current_team_id' => $teamA->id,
        ]);

        return [$teamA, $teamB, $employee];
    }

    public function test_transfer_index_page_renders(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user)->get('/transfers');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Transfers/Index')
                ->has('transfers')
        );
    }

    public function test_transfer_index_shows_existing_transfers(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();
        [$teamA, $teamB, $employee] = $this->createTeamsAndEmployee($tenant);

        // Create an initiator employee for initiated_by
        $initiator = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Manager',
            'last_name' => 'Admin',
            'email' => 'manager@test.com',
            'role' => 'ADMIN',
            'hourly_rate' => 40.00,
            'hire_date' => '2025-01-01',
            'status' => 'ACTIVE',
        ]);

        Transfer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'from_team_id' => $teamA->id,
            'to_team_id' => $teamB->id,
            'reason_category' => 'OPERATIONAL',
            'reason_code' => 'WORKLOAD_BALANCE',
            'transfer_type' => 'PERMANENT',
            'effective_date' => '2026-04-01',
            'initiated_by' => $initiator->id,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($user)->get('/transfers');

        $response->assertInertia(fn ($page) =>
            $page->component('Transfers/Index')
                ->has('transfers.data', 1)
        );
    }

    public function test_transfer_create_page_renders(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();

        $response = $this->actingAs($user)->get('/transfers/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Transfers/Create')
                ->has('employees')
                ->has('teams')
                ->has('reason_categories')
        );
    }

    public function test_can_create_transfer_via_web(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();
        [$teamA, $teamB, $employee] = $this->createTeamsAndEmployee($tenant);

        $initiator = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Manager',
            'last_name' => 'Admin',
            'email' => 'manager@test.com',
            'role' => 'ADMIN',
            'hourly_rate' => 40.00,
            'hire_date' => '2025-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->post('/transfers', [
            'employee_id' => $employee->id,
            'from_team_id' => $teamA->id,
            'to_team_id' => $teamB->id,
            'reason_category' => 'OPERATIONAL',
            'reason_code' => 'WORKLOAD_BALANCE',
            'transfer_type' => 'PERMANENT',
            'effective_date' => '2026-04-01',
            'initiated_by' => $initiator->id,
            'notes' => '',
        ]);

        $response->assertRedirect('/transfers');
        $this->assertDatabaseHas('transfers', [
            'employee_id' => $employee->id,
            'reason_code' => 'WORKLOAD_BALANCE',
            'status' => 'PENDING',
        ]);
    }

    public function test_can_approve_transfer(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();
        [$teamA, $teamB, $employee] = $this->createTeamsAndEmployee($tenant);

        $initiator = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Manager',
            'last_name' => 'Admin',
            'email' => 'manager@test.com',
            'role' => 'ADMIN',
            'hourly_rate' => 40.00,
            'hire_date' => '2025-01-01',
            'status' => 'ACTIVE',
        ]);

        $transfer = Transfer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'from_team_id' => $teamA->id,
            'to_team_id' => $teamB->id,
            'reason_category' => 'OPERATIONAL',
            'reason_code' => 'SKILL_MATCH',
            'transfer_type' => 'PERMANENT',
            'effective_date' => '2026-04-01',
            'initiated_by' => $initiator->id,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($user)->post("/transfers/{$transfer->id}/approve");

        $response->assertRedirect('/transfers');
        $this->assertDatabaseHas('transfers', [
            'id' => $transfer->id,
            'status' => 'APPROVED',
        ]);
    }

    public function test_can_reject_transfer(): void
    {
        [$tenant, $user] = $this->createAuthenticatedAdmin();
        [$teamA, $teamB, $employee] = $this->createTeamsAndEmployee($tenant);

        $initiator = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Manager',
            'last_name' => 'Admin',
            'email' => 'manager@test.com',
            'role' => 'ADMIN',
            'hourly_rate' => 40.00,
            'hire_date' => '2025-01-01',
            'status' => 'ACTIVE',
        ]);

        $transfer = Transfer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'from_team_id' => $teamA->id,
            'to_team_id' => $teamB->id,
            'reason_category' => 'EMPLOYEE_REQUEST',
            'reason_code' => 'PERSONAL_REQUEST',
            'transfer_type' => 'PERMANENT',
            'effective_date' => '2026-04-01',
            'initiated_by' => $initiator->id,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($user)->post("/transfers/{$transfer->id}/reject");

        $response->assertRedirect('/transfers');
        $this->assertDatabaseHas('transfers', [
            'id' => $transfer->id,
            'status' => 'REJECTED',
        ]);
    }

    public function test_transfers_page_requires_authentication(): void
    {
        $response = $this->get('/transfers');
        $response->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/Web/TransferPageTest.php`
Expected: FAIL — controller and routes do not exist.

- [ ] **Step 3: Create TransferPageController**

```php
// app/Http/Controllers/Web/TransferPageController.php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Team;
use App\Models\Transfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransferPageController extends Controller
{
    private const REASON_CATEGORIES = [
        'OPERATIONAL' => [
            'WORKLOAD_BALANCE' => 'Redistributing headcount across teams',
            'SKILL_MATCH' => "Employee's skills better fit the target team",
            'PROJECT_NEED' => 'Temporary or permanent need on a specific project',
            'LOCATION_CHANGE' => 'Employee relocated or job site changed',
        ],
        'PERFORMANCE' => [
            'PERFORMANCE_IMPROVEMENT' => 'Move to a team better suited for development',
            'PROMOTION' => 'Role change requiring different team',
            'MENTOR_ASSIGNMENT' => 'Paired with a senior team member',
        ],
        'EMPLOYEE_REQUEST' => [
            'PERSONAL_REQUEST' => 'Employee initiated the transfer',
            'SCHEDULE_ACCOMMODATION' => 'Better schedule fit on target team',
            'CONFLICT_RESOLUTION' => 'Interpersonal issue requiring separation',
        ],
        'ADMINISTRATIVE' => [
            'TEAM_RESTRUCTURE' => 'Org-wide restructuring',
            'TEAM_DISSOLUTION' => 'Source team being shut down',
            'SEASONAL_ADJUSTMENT' => 'Seasonal staffing needs',
            'OTHER' => 'Other reason (notes required)',
        ],
    ];

    public function index(Request $request): Response
    {
        $query = Transfer::with([
            'employee:id,first_name,last_name',
            'fromTeam:id,name,color_tag',
            'toTeam:id,name,color_tag',
            'initiator:id,first_name,last_name',
        ]);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%");
            });
        }

        $transfers = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
            'filters' => $request->only('search', 'status'),
            'status_options' => ['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED', 'REVERTED'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Transfers/Create', [
            'employees' => Employee::select('id', 'first_name', 'last_name', 'current_team_id')
                ->where('status', 'ACTIVE')
                ->with('team:id,name')
                ->orderBy('last_name')
                ->get(),
            'teams' => Team::select('id', 'name', 'color_tag')
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'reason_categories' => self::REASON_CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $allReasonCodes = collect(self::REASON_CATEGORIES)->flatten()->keys()->all();

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'from_team_id' => ['required', 'exists:teams,id'],
            'to_team_id' => ['required', 'exists:teams,id', 'different:from_team_id'],
            'reason_category' => ['required', Rule::in(array_keys(self::REASON_CATEGORIES))],
            'reason_code' => ['required', Rule::in($allReasonCodes)],
            'transfer_type' => ['required', Rule::in(['PERMANENT', 'TEMPORARY'])],
            'effective_date' => ['required', 'date', 'after_or_equal:today'],
            'expected_return_date' => ['nullable', 'date', 'after:effective_date'],
            'initiated_by' => ['required', 'exists:employees,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Notes required if reason_code is OTHER
        if ($validated['reason_code'] === 'OTHER' && empty($validated['notes'])) {
            return back()->withErrors(['notes' => 'Notes are required when reason is OTHER.']);
        }

        // Expected return date required for temporary transfers
        if ($validated['transfer_type'] === 'TEMPORARY' && empty($validated['expected_return_date'])) {
            return back()->withErrors(['expected_return_date' => 'Expected return date is required for temporary transfers.']);
        }

        $validated['status'] = 'PENDING';

        Transfer::create($validated);

        return redirect('/transfers')->with('success', 'Transfer request created.');
    }

    public function approve(Transfer $transfer): RedirectResponse
    {
        if ($transfer->status !== 'PENDING') {
            return back()->with('error', 'Only pending transfers can be approved.');
        }

        $transfer->update([
            'status' => 'APPROVED',
            'approved_by' => auth()->user()->id,
        ]);

        // Update employee's team
        $transfer->employee()->update([
            'current_team_id' => $transfer->to_team_id,
        ]);

        return redirect('/transfers')->with('success', 'Transfer approved.');
    }

    public function reject(Transfer $transfer): RedirectResponse
    {
        if ($transfer->status !== 'PENDING') {
            return back()->with('error', 'Only pending transfers can be rejected.');
        }

        $transfer->update(['status' => 'REJECTED']);

        return redirect('/transfers')->with('success', 'Transfer rejected.');
    }
}
```

- [ ] **Step 4: Create Transfers/Index.vue**

```vue
<!-- resources/js/Pages/Transfers/Index.vue -->
<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Button from '@/Components/Button.vue';
import Badge from '@/Components/Badge.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    transfers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    status_options: { type: Array, default: () => [] },
});

const columns = [
    { key: 'employee', label: 'Employee', sortable: false },
    { key: 'from_team', label: 'From', sortable: false },
    { key: 'to_team', label: 'To', sortable: false },
    { key: 'reason_code', label: 'Reason', sortable: false },
    { key: 'transfer_type', label: 'Type', sortable: false },
    { key: 'effective_date', label: 'Effective Date', sortable: false },
    { key: 'status', label: 'Status', sortable: false },
];

const statusVariant = (status) => {
    const map = {
        PENDING: 'yellow',
        APPROVED: 'green',
        REJECTED: 'red',
        COMPLETED: 'blue',
        REVERTED: 'gray',
    };
    return map[status] || 'gray';
};

const reasonLabel = (code) => {
    return code?.replace(/_/g, ' ').toLowerCase().replace(/^\w/, c => c.toUpperCase()) || '';
};

const selectedTransfer = ref(null);
const showDetailModal = ref(false);

const viewDetail = (transfer) => {
    selectedTransfer.value = transfer;
    showDetailModal.value = true;
};

const approveTransfer = (id) => {
    router.post(`/transfers/${id}/approve`);
    showDetailModal.value = false;
};

const rejectTransfer = (id) => {
    router.post(`/transfers/${id}/reject`);
    showDetailModal.value = false;
};

const filterByStatus = (status) => {
    router.get('/transfers', { status: status || undefined }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <AppLayout>
        <template #header>Transfers</template>

        <DataTable
            :columns="columns"
            :rows="transfers.data"
            :pagination="transfers"
            :filters="filters"
            base-url="/transfers"
            search-placeholder="Search by employee name..."
            @row-click="viewDetail"
        >
            <template #filters>
                <select
                    :value="filters.status || ''"
                    @change="filterByStatus($event.target.value)"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">All statuses</option>
                    <option v-for="s in status_options" :key="s" :value="s">{{ s }}</option>
                </select>
            </template>

            <template #actions>
                <Link href="/transfers/create">
                    <Button variant="primary" size="sm">New Transfer</Button>
                </Link>
            </template>

            <template #cell-employee="{ row }">
                <span v-if="row.employee" class="font-medium text-gray-900">
                    {{ row.employee.first_name }} {{ row.employee.last_name }}
                </span>
            </template>

            <template #cell-from_team="{ row }">
                <div v-if="row.from_team" class="flex items-center gap-2">
                    <div class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: row.from_team.color_tag }" />
                    {{ row.from_team.name }}
                </div>
            </template>

            <template #cell-to_team="{ row }">
                <div v-if="row.to_team" class="flex items-center gap-2">
                    <div class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: row.to_team.color_tag }" />
                    {{ row.to_team.name }}
                </div>
            </template>

            <template #cell-reason_code="{ value }">
                <span class="text-xs text-gray-600">{{ reasonLabel(value) }}</span>
            </template>

            <template #cell-transfer_type="{ value }">
                <Badge :variant="value === 'PERMANENT' ? 'indigo' : 'yellow'" size="sm">{{ value }}</Badge>
            </template>

            <template #cell-status="{ value }">
                <Badge :variant="statusVariant(value)" size="sm">{{ value }}</Badge>
            </template>

            <template #row-actions="{ row }">
                <div v-if="row.status === 'PENDING'" class="flex items-center gap-2">
                    <button @click.stop="approveTransfer(row.id)" class="text-sm text-green-600 hover:text-green-800">
                        Approve
                    </button>
                    <button @click.stop="rejectTransfer(row.id)" class="text-sm text-red-500 hover:text-red-700">
                        Reject
                    </button>
                </div>
            </template>
        </DataTable>

        <!-- Transfer Detail Modal -->
        <Modal
            :show="showDetailModal"
            :title="selectedTransfer ? 'Transfer Details' : ''"
            max-width="lg"
            @close="showDetailModal = false"
        >
            <div v-if="selectedTransfer" class="space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Employee</p>
                        <p class="font-medium">
                            {{ selectedTransfer.employee?.first_name }}
                            {{ selectedTransfer.employee?.last_name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500">Status</p>
                        <Badge :variant="statusVariant(selectedTransfer.status)" size="sm">
                            {{ selectedTransfer.status }}
                        </Badge>
                    </div>
                    <div>
                        <p class="text-gray-500">From</p>
                        <p class="font-medium">{{ selectedTransfer.from_team?.name }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">To</p>
                        <p class="font-medium">{{ selectedTransfer.to_team?.name }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Category</p>
                        <p>{{ reasonLabel(selectedTransfer.reason_category) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Reason</p>
                        <p>{{ reasonLabel(selectedTransfer.reason_code) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Type</p>
                        <Badge :variant="selectedTransfer.transfer_type === 'PERMANENT' ? 'indigo' : 'yellow'" size="sm">
                            {{ selectedTransfer.transfer_type }}
                        </Badge>
                    </div>
                    <div>
                        <p class="text-gray-500">Effective Date</p>
                        <p>{{ selectedTransfer.effective_date }}</p>
                    </div>
                    <div v-if="selectedTransfer.expected_return_date" class="col-span-2">
                        <p class="text-gray-500">Expected Return</p>
                        <p>{{ selectedTransfer.expected_return_date }}</p>
                    </div>
                    <div v-if="selectedTransfer.notes" class="col-span-2">
                        <p class="text-gray-500">Notes</p>
                        <p class="text-gray-700">{{ selectedTransfer.notes }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Initiated By</p>
                        <p>
                            {{ selectedTransfer.initiator?.first_name }}
                            {{ selectedTransfer.initiator?.last_name }}
                        </p>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showDetailModal = false">Close</Button>
                <template v-if="selectedTransfer?.status === 'PENDING'">
                    <Button variant="danger" @click="rejectTransfer(selectedTransfer.id)">Reject</Button>
                    <Button @click="approveTransfer(selectedTransfer.id)">Approve</Button>
                </template>
            </template>
        </Modal>
    </AppLayout>
</template>
```

- [ ] **Step 5: Create Transfers/Create.vue**

```vue
<!-- resources/js/Pages/Transfers/Create.vue -->
<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/Button.vue';
import Input from '@/Components/Input.vue';
import Select from '@/Components/Select.vue';
import Alert from '@/Components/Alert.vue';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    reason_categories: { type: Object, default: () => ({}) },
});

const form = useForm({
    employee_id: '',
    from_team_id: '',
    to_team_id: '',
    reason_category: '',
    reason_code: '',
    transfer_type: 'PERMANENT',
    effective_date: '',
    expected_return_date: '',
    initiated_by: '',
    notes: '',
});

const employeeOptions = props.employees.map(e => ({
    value: e.id,
    label: `${e.first_name} ${e.last_name}${e.team ? ` (${e.team.name})` : ''}`,
}));

const teamOptions = props.teams.map(t => ({
    value: t.id,
    label: t.name,
}));

const categoryOptions = Object.keys(props.reason_categories).map(c => ({
    value: c,
    label: c.replace(/_/g, ' ').toLowerCase().replace(/^\w/, ch => ch.toUpperCase()),
}));

const reasonCodeOptions = computed(() => {
    if (!form.reason_category || !props.reason_categories[form.reason_category]) {
        return [];
    }
    return Object.entries(props.reason_categories[form.reason_category]).map(([code, desc]) => ({
        value: code,
        label: `${code.replace(/_/g, ' ').toLowerCase().replace(/^\w/, c => c.toUpperCase())} — ${desc}`,
    }));
});

const transferTypeOptions = [
    { value: 'PERMANENT', label: 'Permanent' },
    { value: 'TEMPORARY', label: 'Temporary' },
];

const initiatorOptions = props.employees
    .filter(e => ['ADMIN', 'MANAGER'].includes(e.role))
    .map(e => ({
        value: e.id,
        label: `${e.first_name} ${e.last_name}`,
    }));

// Auto-fill from_team when employee is selected
watch(() => form.employee_id, (newVal) => {
    const employee = props.employees.find(e => e.id === newVal);
    if (employee?.current_team_id) {
        form.from_team_id = employee.current_team_id;
    }
});

// Reset reason_code when category changes
watch(() => form.reason_category, () => {
    form.reason_code = '';
});

const notesRequired = computed(() => form.reason_code === 'OTHER');
const showReturnDate = computed(() => form.transfer_type === 'TEMPORARY');

const submit = () => {
    form.post('/transfers');
};
</script>

<template>
    <AppLayout>
        <template #header>Initiate Transfer</template>

        <div class="max-w-2xl">
            <Alert type="info" class="mb-6">
                Transfer requests require admin approval before they take effect.
                The employee's team assignment will update on the effective date after approval.
            </Alert>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Employee Selection -->
                    <Select
                        v-model="form.employee_id"
                        label="Employee"
                        :options="employeeOptions"
                        placeholder="Select employee to transfer..."
                        :error="form.errors.employee_id"
                        required
                    />

                    <!-- Teams -->
                    <div class="grid grid-cols-2 gap-4">
                        <Select
                            v-model="form.from_team_id"
                            label="From Team"
                            :options="teamOptions"
                            :error="form.errors.from_team_id"
                            required
                        />
                        <Select
                            v-model="form.to_team_id"
                            label="To Team"
                            :options="teamOptions"
                            :error="form.errors.to_team_id"
                            required
                        />
                    </div>

                    <!-- Reason Category + Code -->
                    <div class="p-4 bg-gray-50 rounded-lg space-y-4">
                        <p class="text-sm font-semibold text-gray-700">Transfer Reason</p>

                        <Select
                            v-model="form.reason_category"
                            label="Category"
                            :options="categoryOptions"
                            placeholder="Select reason category..."
                            :error="form.errors.reason_category"
                            required
                        />

                        <Select
                            v-model="form.reason_code"
                            label="Specific Reason"
                            :options="reasonCodeOptions"
                            placeholder="Select reason..."
                            :error="form.errors.reason_code"
                            :disabled="!form.reason_category"
                            required
                        />
                    </div>

                    <!-- Transfer Type + Dates -->
                    <div class="grid grid-cols-2 gap-4">
                        <Select
                            v-model="form.transfer_type"
                            label="Transfer Type"
                            :options="transferTypeOptions"
                            :error="form.errors.transfer_type"
                            required
                        />
                        <Input
                            v-model="form.effective_date"
                            label="Effective Date"
                            type="date"
                            :error="form.errors.effective_date"
                            required
                        />
                    </div>

                    <Input
                        v-if="showReturnDate"
                        v-model="form.expected_return_date"
                        label="Expected Return Date"
                        type="date"
                        :error="form.errors.expected_return_date"
                        required
                    />

                    <!-- Initiated By -->
                    <Select
                        v-model="form.initiated_by"
                        label="Initiated By"
                        :options="initiatorOptions.length ? initiatorOptions : employeeOptions"
                        placeholder="Select initiator..."
                        :error="form.errors.initiated_by"
                        required
                    />

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Notes
                            <span v-if="notesRequired" class="text-red-500">* (required for "Other")</span>
                        </label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            :required="notesRequired"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Additional notes about this transfer..."
                        />
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-red-600">{{ form.errors.notes }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <Link href="/transfers">
                            <Button variant="secondary">Cancel</Button>
                        </Link>
                        <Button type="submit" :loading="form.processing" :disabled="form.processing">
                            Submit Transfer Request
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 6: Add transfer web routes**

Add to `routes/web.php` inside the authenticated group:

```php
use App\Http\Controllers\Web\TransferPageController;

// Inside Route::middleware('auth')->group(function () { ... });
Route::get('/transfers', [TransferPageController::class, 'index'])->name('transfers.index');
Route::get('/transfers/create', [TransferPageController::class, 'create'])->name('transfers.create');
Route::post('/transfers', [TransferPageController::class, 'store'])->name('transfers.store');
Route::post('/transfers/{transfer}/approve', [TransferPageController::class, 'approve'])->name('transfers.approve');
Route::post('/transfers/{transfer}/reject', [TransferPageController::class, 'reject'])->name('transfers.reject');
```

- [ ] **Step 7: Run tests**

Run: `docker compose exec app php artisan test tests/Feature/Web/TransferPageTest.php`
Expected: All 7 tests PASS.

- [ ] **Step 8: Run all Plan 3a tests**

Run: `docker compose exec app php artisan test tests/Feature/Web/`
Expected: All tests pass across AuthPageTest, DashboardPageTest, EmployeePageTest, TeamPageTest, TransferPageTest.

- [ ] **Step 9: Build frontend assets and verify**

```bash
docker compose exec app npm run build
```
Expected: Vite build completes without errors.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Web/TransferPageController.php resources/js/Pages/Transfers/ routes/web.php tests/Feature/Web/TransferPageTest.php
git commit -m "feat: add transfer management pages (index, create, approve/reject flow with categorized reasons)"
```
