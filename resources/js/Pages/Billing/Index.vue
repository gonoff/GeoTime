<template>
  <AppLayout>
    <template #title>Billing</template>

    <div class="card-grid">
      <!-- Plan Card -->
      <div class="info-card">
        <div class="info-card-header">
          <h2 class="info-card-title">Current Plan</h2>
        </div>
        <div class="info-card-body">
          <div class="plan-display">
            <span class="plan-name">{{ billing.plan }}</span>
            <span class="badge" :class="planStatusClass">{{ billing.status }}</span>
          </div>

          <div class="info-rows">
            <div class="info-row">
              <span class="info-label">Status</span>
              <span class="info-value">{{ billing.status }}</span>
            </div>
            <div v-if="billing.on_trial" class="info-row">
              <span class="info-label">Trial Ends</span>
              <span class="info-value tabular">{{ billing.trial_ends_at ?? '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Employees</span>
              <span class="info-value tabular">{{ billing.employee_count }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const billing = computed(() => page.props.billing ?? {
  plan: 'starter',
  status: 'trial',
  trial_ends_at: null,
  on_trial: false,
  employee_count: 0,
});

const planStatusClass = computed(() => {
  switch (billing.value.status) {
    case 'active': return 'badge--go';
    case 'trial': return 'badge--flag';
    case 'past_due': return 'badge--halt';
    case 'cancelled': return 'badge--muted';
    default: return 'badge--muted';
  }
});
</script>

<style scoped>
.card-grid {
  max-width: 480px;
}

.info-card {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
}

.info-card-header {
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid var(--seam-1);
}

.info-card-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
  margin: 0;
}

.info-card-body {
  padding: var(--sp-5);
}

.plan-display {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  margin-bottom: var(--sp-5);
}

.plan-name {
  font-size: 22px;
  font-weight: 700;
  color: var(--chalk-1);
  text-transform: capitalize;
  letter-spacing: -0.02em;
}

.info-rows {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--sp-2) 0;
  border-bottom: 1px solid var(--seam-1);
}

.info-row:last-child {
  border-bottom: none;
}

.info-label {
  font-size: 13px;
  color: var(--chalk-3);
}

.info-value {
  font-size: 13px;
  font-weight: 500;
  color: var(--chalk-1);
  text-transform: capitalize;
}

.tabular {
  font-variant-numeric: tabular-nums;
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

.badge--flag {
  color: var(--flag);
  background: var(--flag-soft);
}

.badge--muted {
  color: var(--chalk-3);
  background: var(--slab-3);
}
</style>
