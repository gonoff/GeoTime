<template>
  <AppLayout>
    <template #title>Settings</template>

    <div class="settings-container">
      <div class="info-card">
        <div class="info-card-header">
          <h2 class="info-card-title">Company Settings</h2>
          <span class="read-only-badge">Read Only</span>
        </div>
        <div class="info-card-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Company Name</label>
              <input type="text" class="form-input" :value="settings.company_name" disabled />
            </div>

            <div class="form-group">
              <label class="form-label">Timezone</label>
              <input type="text" class="form-input" :value="settings.timezone" disabled />
            </div>

            <div class="form-group">
              <label class="form-label">Workweek Start Day</label>
              <input type="text" class="form-input" :value="dayName(settings.workweek_start_day)" disabled />
            </div>

            <div class="form-group">
              <label class="form-label">Overtime Rule</label>
              <div class="rule-display">
                <div class="rule-row">
                  <span class="rule-label">Weekly Threshold</span>
                  <span class="rule-value tabular">{{ settings.overtime_rule?.weekly_threshold ?? '—' }}h</span>
                </div>
                <div class="rule-row">
                  <span class="rule-label">Daily Threshold</span>
                  <span class="rule-value tabular">{{ settings.overtime_rule?.daily_threshold ? settings.overtime_rule.daily_threshold + 'h' : 'None' }}</span>
                </div>
                <div class="rule-row">
                  <span class="rule-label">Multiplier</span>
                  <span class="rule-value tabular">{{ settings.overtime_rule?.multiplier ?? '—' }}x</span>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Rounding Rule</label>
              <input type="text" class="form-input" :value="capitalize(settings.rounding_rule)" disabled />
            </div>

            <div class="form-group">
              <label class="form-label">Clock Verification Mode</label>
              <input type="text" class="form-input" :value="capitalize(settings.clock_verification_mode)" disabled />
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
const settings = computed(() => page.props.settings ?? {
  company_name: '',
  timezone: 'America/New_York',
  workweek_start_day: 'monday',
  overtime_rule: { weekly_threshold: 40, daily_threshold: null, multiplier: 1.5 },
  rounding_rule: 'none',
  clock_verification_mode: 'none',
});

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function capitalize(val) {
  if (val === null || val === undefined) return '—';
  const str = String(val);
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function dayName(day) {
  return dayNames[day] ?? '—';
}
</script>

<style scoped>
.settings-container {
  max-width: 560px;
}

.info-card {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
}

.info-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid var(--seam-1);
}

.info-card-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
  margin: 0;
}

.read-only-badge {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--chalk-3);
  background: var(--slab-3);
  padding: 2px 8px;
  border-radius: var(--radius-full);
}

.info-card-body {
  padding: var(--sp-5);
}

.form-grid {
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
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

.form-input {
  background: var(--pit);
  border: 1px solid var(--pit-border);
  border-radius: var(--radius-md);
  color: var(--chalk-2);
  font-size: 13px;
  padding: var(--sp-2) var(--sp-3);
  font-family: inherit;
}

.form-input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.rule-display {
  background: var(--pit);
  border: 1px solid var(--pit-border);
  border-radius: var(--radius-md);
  padding: var(--sp-3);
}

.rule-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--sp-1) 0;
}

.rule-row + .rule-row {
  border-top: 1px solid var(--seam-1);
  margin-top: var(--sp-1);
  padding-top: var(--sp-2);
}

.rule-label {
  font-size: 12px;
  color: var(--chalk-3);
}

.rule-value {
  font-size: 13px;
  font-weight: 500;
  color: var(--chalk-1);
}

.tabular {
  font-variant-numeric: tabular-nums;
}
</style>
