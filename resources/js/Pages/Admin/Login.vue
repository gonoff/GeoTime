<template>
  <div class="login-shell">
    <div class="login-card">
      <!-- Brand -->
      <div class="login-brand">
        <div class="brand-ring">
          <div class="brand-ring-inner" />
        </div>
        <h1 class="brand-title">GeoTime Admin</h1>
        <p class="brand-subtitle">Platform administration</p>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="login-form">
        <div class="field">
          <label class="field-label" for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="field-input"
            :class="{ 'field-input--error': form.errors.email }"
            placeholder="admin@geotime.com"
            autofocus
          />
          <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
        </div>

        <div class="field">
          <label class="field-label" for="password">Password</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            class="field-input"
            :class="{ 'field-input--error': form.errors.password }"
            placeholder="Enter your password"
          />
          <span v-if="form.errors.password" class="field-error">{{ form.errors.password }}</span>
        </div>

        <div class="field-row">
          <label class="checkbox-label">
            <input v-model="form.remember" type="checkbox" class="checkbox" />
            <span>Remember me</span>
          </label>
        </div>

        <button type="submit" class="login-button" :disabled="form.processing">
          <span v-if="form.processing" class="login-spinner" />
          <span v-else>Sign in</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

function submit() {
  form.post('/admin/login', {
    onFinish: () => form.reset('password'),
  });
}
</script>

<style scoped>
.login-shell {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--slab-0);
  padding: var(--sp-6);
}

.login-card {
  width: 100%;
  max-width: 380px;
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
  padding: var(--sp-10);
}

.login-brand {
  text-align: center;
  margin-bottom: var(--sp-8);
}

.brand-ring {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-full);
  border: 2.5px solid var(--zone);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto var(--sp-4);
}

.brand-ring-inner {
  width: 12px;
  height: 12px;
  border-radius: var(--radius-full);
  background: var(--zone);
}

.brand-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--chalk-1);
  letter-spacing: -0.03em;
  margin: 0 0 var(--sp-1);
}

.brand-subtitle {
  font-size: 13px;
  color: var(--chalk-3);
  margin: 0;
}

/* Form */
.login-form {
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.field-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--chalk-2);
  letter-spacing: 0.01em;
}

.field-input {
  width: 100%;
  padding: var(--sp-3) var(--sp-3);
  background: var(--pit);
  border: 1px solid var(--pit-border);
  border-radius: var(--radius-md);
  color: var(--chalk-1);
  font-size: 13px;
  font-family: inherit;
  transition: border-color var(--duration) var(--ease);
  box-sizing: border-box;
}

.field-input::placeholder {
  color: var(--chalk-4);
}

.field-input:focus {
  outline: none;
  border-color: rgba(80, 128, 176, 0.4);
}

.field-input--error {
  border-color: var(--halt);
}

.field-error {
  font-size: 11px;
  color: var(--halt);
}

.field-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: 12px;
  color: var(--chalk-2);
  cursor: pointer;
}

.checkbox {
  width: 14px;
  height: 14px;
  accent-color: var(--zone);
}

.login-button {
  width: 100%;
  padding: var(--sp-3);
  background: var(--zone);
  color: #fff;
  border: none;
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: background var(--duration) var(--ease);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--sp-2);
}

.login-button:hover:not(:disabled) {
  background: #4570a0;
}

.login-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.login-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: var(--radius-full);
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
