<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
        <div class="modal-container" :class="'modal--' + maxWidth" @keydown.esc="$emit('close')">
          <div class="modal-header">
            <h2 class="modal-title">{{ title }}</h2>
            <button class="modal-close" @click="$emit('close')">
              <X :size="18" />
            </button>
          </div>
          <div class="modal-body">
            <slot />
          </div>
          <div class="modal-footer">
            <slot name="footer">
              <button class="btn btn--ghost" @click="$emit('close')" :disabled="loading">Cancel</button>
              <button class="btn btn--primary" @click="$emit('submit')" :disabled="loading">
                <span v-if="loading" class="spinner" />
                {{ loading ? 'Saving...' : 'Save' }}
              </button>
            </slot>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  maxWidth: { type: String, default: 'lg' },
  loading: { type: Boolean, default: false },
});

defineEmits(['close', 'submit']);

watch(() => props.show, (val) => {
  document.body.style.overflow = val ? 'hidden' : '';
});
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--sp-4);
}

.modal-container {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
  width: 100%;
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}

.modal--sm { max-width: 400px; }
.modal--md { max-width: 520px; }
.modal--lg { max-width: 640px; }
.modal--xl { max-width: 800px; }

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid var(--seam-1);
  flex-shrink: 0;
}

.modal-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--chalk-1);
  margin: 0;
}

.modal-close {
  display: flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  color: var(--chalk-3);
  cursor: pointer;
  padding: var(--sp-1);
  border-radius: var(--radius-md);
  transition: color 0.15s, background 0.15s;
}

.modal-close:hover {
  color: var(--chalk-1);
  background: var(--slab-3);
}

.modal-body {
  padding: var(--sp-5);
  overflow-y: auto;
  max-height: 60vh;
  flex: 1;
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--sp-3);
  padding: var(--sp-4) var(--sp-5);
  border-top: 1px solid var(--seam-1);
  flex-shrink: 0;
}

/* Shared button styles */
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
  transition: opacity 0.15s, background 0.15s;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn--primary {
  background: var(--viz);
  color: #fff;
}

.btn--primary:hover:not(:disabled) {
  opacity: 0.9;
}

.btn--ghost {
  background: var(--slab-3);
  color: var(--chalk-2);
  border: 1px solid var(--seam-1);
}

.btn--ghost:hover:not(:disabled) {
  color: var(--chalk-1);
  background: var(--seam-1);
}

.btn--green {
  background: var(--go);
  color: #fff;
}

.btn--red {
  background: var(--halt);
  color: #fff;
}

.btn--yellow {
  background: var(--flag);
  color: #fff;
}

/* Spinner */
.spinner {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: var(--radius-full);
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Form elements for modal content */
:deep(.form-group) {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

:deep(.form-label) {
  font-size: 12px;
  font-weight: 500;
  color: var(--chalk-3);
}

:deep(.form-input),
:deep(.form-select) {
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
}

:deep(.form-input:focus),
:deep(.form-select:focus) {
  border-color: var(--viz);
}

:deep(.form-select) {
  appearance: none;
  cursor: pointer;
}

:deep(.form-grid) {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
}

/* Transition */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
