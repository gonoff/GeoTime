<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
        <div class="confirm-container">
          <div class="confirm-icon" :class="{ 'confirm-icon--destructive': destructive }">
            <AlertTriangle :size="24" />
          </div>
          <h3 class="confirm-title">{{ title }}</h3>
          <p class="confirm-message">{{ message }}</p>
          <div class="confirm-actions">
            <button class="btn btn--ghost" @click="$emit('close')">Cancel</button>
            <button
              class="btn"
              :class="'btn--' + confirmColor"
              @click="$emit('confirm')"
            >{{ confirmLabel }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { AlertTriangle } from 'lucide-vue-next';

defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Confirm' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirm' },
  confirmColor: { type: String, default: 'primary' },
  destructive: { type: Boolean, default: false },
});

defineEmits(['close', 'confirm']);
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

.confirm-container {
  background: var(--slab-2);
  border: 1px solid var(--seam-1);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 400px;
  padding: var(--sp-6);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-3);
}

.confirm-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: var(--radius-full);
  background: var(--flag-soft);
  color: var(--flag);
}

.confirm-icon--destructive {
  background: var(--halt-soft);
  color: var(--halt);
}

.confirm-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--chalk-1);
  margin: 0;
}

.confirm-message {
  font-size: 13px;
  color: var(--chalk-3);
  margin: 0;
  line-height: 1.5;
}

.confirm-actions {
  display: flex;
  gap: var(--sp-3);
  margin-top: var(--sp-2);
}

/* Buttons */
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
