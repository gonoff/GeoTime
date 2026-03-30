<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
        <div class="confirm-container">
          <div class="confirm-icon confirm-icon--destructive">
            <Trash2 :size="24" />
          </div>
          <h3 class="confirm-title">Delete {{ entityType }}</h3>

          <template v-if="canArchive && !showPermanent">
            <p class="confirm-message">
              Archive "{{ entityName }}" instead of deleting? Archived items can be restored later.
            </p>
            <div class="confirm-actions">
              <button class="btn btn--ghost" @click="$emit('close')">Cancel</button>
              <button class="btn btn--yellow" @click="$emit('archive')">Archive</button>
            </div>
            <button class="permanent-link" @click="showPermanent = true">
              Permanently delete instead...
            </button>
          </template>

          <template v-else>
            <p class="confirm-message">
              This will permanently delete "{{ entityName }}". This cannot be undone.
            </p>
            <div class="permanent-confirm">
              <label class="form-label">Type "{{ entityName }}" to confirm:</label>
              <input
                v-model="confirmText"
                class="form-input"
                :placeholder="entityName"
              />
            </div>
            <div class="confirm-actions">
              <button class="btn btn--ghost" @click="cancel">Cancel</button>
              <button
                class="btn btn--red"
                :disabled="confirmText !== entityName"
                @click="$emit('delete')"
              >Delete Permanently</button>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Trash2 } from 'lucide-vue-next';

const props = defineProps({
  show: { type: Boolean, default: false },
  entityName: { type: String, default: '' },
  entityType: { type: String, default: 'item' },
  canArchive: { type: Boolean, default: true },
});

defineEmits(['close', 'archive', 'delete']);

const showPermanent = ref(false);
const confirmText = ref('');

function cancel() {
  showPermanent.value = false;
  confirmText.value = '';
}

watch(() => props.show, (val) => {
  if (!val) {
    showPermanent.value = false;
    confirmText.value = '';
  }
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

.permanent-link {
  background: none;
  border: none;
  font-size: 12px;
  color: var(--chalk-4);
  cursor: pointer;
  text-decoration: underline;
  font-family: inherit;
  padding: 0;
}

.permanent-link:hover {
  color: var(--halt);
}

.permanent-confirm {
  width: 100%;
  text-align: left;
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
  box-sizing: border-box;
}

.form-input:focus {
  border-color: var(--halt);
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

.btn--ghost {
  background: var(--slab-3);
  color: var(--chalk-2);
  border: 1px solid var(--seam-1);
}

.btn--ghost:hover:not(:disabled) {
  color: var(--chalk-1);
  background: var(--seam-1);
}

.btn--red {
  background: var(--halt);
  color: #fff;
}

.btn--red:hover:not(:disabled) {
  opacity: 0.9;
}

.btn--yellow {
  background: var(--flag);
  color: #fff;
}

.btn--yellow:hover:not(:disabled) {
  opacity: 0.9;
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
