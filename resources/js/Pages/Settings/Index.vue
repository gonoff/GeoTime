<template>
  <AppLayout>
    <template #title>Settings</template>

    <form class="settings-container" @submit.prevent="submit">

      <!-- Section 1: Company Info -->
      <div class="settings-card">
        <div class="settings-card-header">
          <h2 class="settings-card-title">Company Info</h2>
        </div>
        <div class="settings-card-body">
          <div class="form-group">
            <label class="form-label">Company Name</label>
            <input type="text" class="form-input" :value="settings.company_name" disabled />
            <span class="form-hint">Contact support to change your company name.</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="timezone">Timezone</label>
            <select id="timezone" class="form-select" v-model="form.timezone">
              <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
            </select>
            <span v-if="form.errors.timezone" class="form-error">{{ form.errors.timezone }}</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="workweek_start_day">Workweek Start Day</label>
            <select id="workweek_start_day" class="form-select" v-model="form.workweek_start_day">
              <option :value="0">Sunday</option>
              <option :value="1">Monday</option>
              <option :value="2">Tuesday</option>
              <option :value="3">Wednesday</option>
              <option :value="4">Thursday</option>
              <option :value="5">Friday</option>
              <option :value="6">Saturday</option>
            </select>
            <span v-if="form.errors.workweek_start_day" class="form-error">{{ form.errors.workweek_start_day }}</span>
          </div>
        </div>
      </div>

      <!-- Section 2: Clock Verification -->
      <div class="settings-card">
        <div class="settings-card-header">
          <h2 class="settings-card-title">Clock Verification</h2>
        </div>
        <div class="settings-card-body">
          <div class="radio-group">
            <label class="radio-option" :class="{ 'radio-option--active': form.clock_verification_mode === 'AUTO_ONLY' }">
              <input type="radio" class="radio-input" v-model="form.clock_verification_mode" value="AUTO_ONLY" />
              <div class="radio-content">
                <span class="radio-label">GPS Location Only</span>
                <span class="radio-desc">Employees clock in/out with automatic GPS location verification</span>
              </div>
            </label>
            <label class="radio-option" :class="{ 'radio-option--active': form.clock_verification_mode === 'AUTO_PHOTO' }">
              <input type="radio" class="radio-input" v-model="form.clock_verification_mode" value="AUTO_PHOTO" />
              <div class="radio-content">
                <span class="radio-label">GPS + Photo Verification</span>
                <span class="radio-desc">Employees must also submit a selfie photo when clocking in</span>
              </div>
            </label>
          </div>
          <span v-if="form.errors.clock_verification_mode" class="form-error">{{ form.errors.clock_verification_mode }}</span>
        </div>
      </div>

      <!-- Section 3: Overtime Rules -->
      <div class="settings-card">
        <div class="settings-card-header">
          <h2 class="settings-card-title">Overtime Rules</h2>
        </div>
        <div class="settings-card-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="overtime_weekly_threshold">Weekly Threshold (hours)</label>
              <input
                id="overtime_weekly_threshold"
                type="number"
                class="form-input"
                v-model.number="form.overtime_weekly_threshold"
                min="0"
                step="0.5"
              />
              <span v-if="form.errors.overtime_weekly_threshold" class="form-error">{{ form.errors.overtime_weekly_threshold }}</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="overtime_daily_threshold">Daily Threshold (hours, optional)</label>
              <input
                id="overtime_daily_threshold"
                type="number"
                class="form-input"
                v-model.number="form.overtime_daily_threshold"
                min="0"
                step="0.5"
                placeholder="None"
              />
              <span v-if="form.errors.overtime_daily_threshold" class="form-error">{{ form.errors.overtime_daily_threshold }}</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="overtime_multiplier">Multiplier (e.g. 1.5)</label>
              <input
                id="overtime_multiplier"
                type="number"
                class="form-input"
                v-model.number="form.overtime_multiplier"
                min="1"
                max="3"
                step="0.25"
              />
              <span v-if="form.errors.overtime_multiplier" class="form-error">{{ form.errors.overtime_multiplier }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 4: Rounding Rules -->
      <div class="settings-card">
        <div class="settings-card-header">
          <h2 class="settings-card-title">Rounding Rules</h2>
        </div>
        <div class="settings-card-body">
          <div class="radio-group">
            <label
              v-for="opt in roundingOptions"
              :key="opt.value"
              class="radio-option"
              :class="{ 'radio-option--active': form.rounding_rule === opt.value }"
            >
              <input type="radio" class="radio-input" v-model="form.rounding_rule" :value="opt.value" />
              <div class="radio-content">
                <span class="radio-label">{{ opt.label }}</span>
              </div>
            </label>
          </div>
          <span v-if="form.errors.rounding_rule" class="form-error">{{ form.errors.rounding_rule }}</span>
        </div>
      </div>

      <!-- Save Button -->
      <div class="settings-actions">
        <button type="submit" class="btn btn--primary" :disabled="form.processing">
          <span v-if="form.processing" class="spinner" />
          {{ form.processing ? 'Saving...' : 'Save Settings' }}
        </button>
      </div>

    </form>
  </AppLayout>
</template>

<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const settings = page.props.settings ?? {
  company_name: '',
  timezone: 'America/New_York',
  workweek_start_day: 1,
  overtime_rule: { weekly_threshold: 40, daily_threshold: null, multiplier: 1.5 },
  rounding_rule: 'EXACT',
  clock_verification_mode: 'AUTO_ONLY',
};

const form = useForm({
  timezone: settings.timezone,
  workweek_start_day: settings.workweek_start_day,
  clock_verification_mode: settings.clock_verification_mode,
  overtime_weekly_threshold: settings.overtime_rule?.weekly_threshold ?? 40,
  overtime_daily_threshold: settings.overtime_rule?.daily_threshold ?? null,
  overtime_multiplier: settings.overtime_rule?.multiplier ?? 1.5,
  rounding_rule: settings.rounding_rule,
});

function submit() {
  form.put('/settings');
}

const roundingOptions = [
  { value: 'EXACT', label: 'Exact — record precise clock in/out times' },
  { value: 'QUARTER', label: 'Round to nearest 15 min' },
  { value: 'HALF', label: 'Round to nearest 30 min' },
  { value: 'ROUND_UP', label: 'Always round up' },
  { value: 'ROUND_DOWN', label: 'Always round down' },
];

const timezones = [
  { value: 'America/New_York', label: 'Eastern Time (America/New_York)' },
  { value: 'America/Chicago', label: 'Central Time (America/Chicago)' },
  { value: 'America/Denver', label: 'Mountain Time (America/Denver)' },
  { value: 'America/Los_Angeles', label: 'Pacific Time (America/Los_Angeles)' },
  { value: 'America/Anchorage', label: 'Alaska Time (America/Anchorage)' },
  { value: 'Pacific/Honolulu', label: 'Hawaii Time (Pacific/Honolulu)' },
  { value: 'America/Phoenix', label: 'Arizona Time (America/Phoenix)' },
  { value: 'America/Toronto', label: 'Eastern Time (America/Toronto)' },
  { value: 'America/Vancouver', label: 'Pacific Time (America/Vancouver)' },
  { value: 'America/Winnipeg', label: 'Central Time (America/Winnipeg)' },
  { value: 'Europe/London', label: 'London (Europe/London)' },
  { value: 'Europe/Paris', label: 'Paris (Europe/Paris)' },
  { value: 'Europe/Berlin', label: 'Berlin (Europe/Berlin)' },
  { value: 'Europe/Amsterdam', label: 'Amsterdam (Europe/Amsterdam)' },
  { value: 'Europe/Madrid', label: 'Madrid (Europe/Madrid)' },
  { value: 'Europe/Rome', label: 'Rome (Europe/Rome)' },
  { value: 'Europe/Stockholm', label: 'Stockholm (Europe/Stockholm)' },
  { value: 'Europe/Warsaw', label: 'Warsaw (Europe/Warsaw)' },
  { value: 'Europe/Helsinki', label: 'Helsinki (Europe/Helsinki)' },
  { value: 'Europe/Athens', label: 'Athens (Europe/Athens)' },
  { value: 'Europe/Istanbul', label: 'Istanbul (Europe/Istanbul)' },
  { value: 'Europe/Moscow', label: 'Moscow (Europe/Moscow)' },
  { value: 'Asia/Dubai', label: 'Dubai (Asia/Dubai)' },
  { value: 'Asia/Karachi', label: 'Karachi (Asia/Karachi)' },
  { value: 'Asia/Kolkata', label: 'India (Asia/Kolkata)' },
  { value: 'Asia/Dhaka', label: 'Dhaka (Asia/Dhaka)' },
  { value: 'Asia/Bangkok', label: 'Bangkok (Asia/Bangkok)' },
  { value: 'Asia/Singapore', label: 'Singapore (Asia/Singapore)' },
  { value: 'Asia/Shanghai', label: 'China (Asia/Shanghai)' },
  { value: 'Asia/Tokyo', label: 'Tokyo (Asia/Tokyo)' },
  { value: 'Asia/Seoul', label: 'Seoul (Asia/Seoul)' },
  { value: 'Australia/Sydney', label: 'Sydney (Australia/Sydney)' },
  { value: 'Australia/Melbourne', label: 'Melbourne (Australia/Melbourne)' },
  { value: 'Australia/Brisbane', label: 'Brisbane (Australia/Brisbane)' },
  { value: 'Pacific/Auckland', label: 'Auckland (Pacific/Auckland)' },
  { value: 'UTC', label: 'UTC' },
];
</script>

<style scoped>
.settings-container {
  max-width: 640px;
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
}

.settings-card {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
}

.settings-card-header {
  display: flex;
  align-items: center;
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid var(--seam-1);
}

.settings-card-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--chalk-1);
  margin: 0;
}

.settings-card-body {
  padding: var(--sp-5);
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.form-row {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
}

@media (min-width: 520px) {
  .form-row {
    flex-direction: row;
  }

  .form-row .form-group {
    flex: 1;
  }
}

.form-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--chalk-3);
}

.form-hint {
  font-size: 11px;
  color: var(--chalk-4);
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

.form-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: var(--viz);
}

.radio-group {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.radio-option {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-4);
  background: var(--slab-3);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: border-color 0.15s;
}

.radio-option--active {
  border-color: var(--viz);
  background: var(--slab-2);
}

.radio-input {
  margin-top: 2px;
  flex-shrink: 0;
  accent-color: var(--viz);
}

.radio-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.radio-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--chalk-1);
}

.radio-desc {
  font-size: 12px;
  color: var(--chalk-3);
}

.settings-actions {
  display: flex;
  justify-content: flex-end;
  padding-bottom: var(--sp-2);
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-2) var(--sp-5);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  border: none;
  transition: opacity 0.15s;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn--primary {
  background: var(--viz);
  color: #fff;
}

.btn--primary:not(:disabled):hover {
  opacity: 0.88;
}

.spinner {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
