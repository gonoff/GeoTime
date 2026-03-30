<template>
  <Teleport to="body">
    <Transition name="toast">
      <div v-if="visible" class="toast" :class="'toast--' + type">
        <component :is="icon" :size="16" />
        <span class="toast-message">{{ message }}</span>
        <button class="toast-close" @click="dismiss">
          <X :size="14" />
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, watch, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle, AlertCircle, X } from 'lucide-vue-next';

const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref('success');
let timer = null;

const icon = computed(() => type.value === 'success' ? CheckCircle : AlertCircle);

watch(() => page.props.flash, (flash) => {
  if (flash?.success) {
    show(flash.success, 'success');
  } else if (flash?.error) {
    show(flash.error, 'error');
  }
}, { deep: true, immediate: true });

function show(msg, t) {
  message.value = msg;
  type.value = t;
  visible.value = true;
  clearTimeout(timer);
  timer = setTimeout(dismiss, 4000);
}

function dismiss() {
  visible.value = false;
  clearTimeout(timer);
}
</script>

<style scoped>
.toast {
  position: fixed;
  top: var(--sp-4);
  right: var(--sp-4);
  z-index: 60;
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  background: var(--slab-3);
  border: 1px solid var(--seam-2);
  border-radius: var(--radius-lg);
  padding: var(--sp-3) var(--sp-4);
  min-width: 280px;
  max-width: 400px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
  border-left: 3px solid var(--go);
}

.toast--success {
  border-left-color: var(--go);
  color: var(--go);
}

.toast--error {
  border-left-color: var(--halt);
  color: var(--halt);
}

.toast-message {
  flex: 1;
  font-size: 13px;
  color: var(--chalk-1);
  line-height: 1.4;
}

.toast-close {
  display: flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  color: var(--chalk-4);
  cursor: pointer;
  padding: 2px;
  border-radius: var(--radius-md);
  flex-shrink: 0;
  transition: color 0.15s;
}

.toast-close:hover {
  color: var(--chalk-2);
}

/* Transition */
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(16px);
}
</style>
