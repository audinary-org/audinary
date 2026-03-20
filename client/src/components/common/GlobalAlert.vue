<template>
  <Teleport to="body">
    <div class="fixed bottom-24 right-2 z-[1065] pointer-events-none">
      <TransitionGroup
        name="alert"
        tag="div"
        class="flex flex-col gap-2 max-w-sm"
      >
        <div
          v-for="alert in alertStore.alerts"
          :key="alert.id"
          role="alert"
          aria-live="polite"
          class="pointer-events-auto flex items-center gap-3 p-4 rounded-md shadow-lg border-0"
          :class="{
            'bg-green-600 text-white': alert.type === 'success',
            'bg-red-600 text-white': alert.type === 'error',
            'bg-yellow-400 text-black': alert.type === 'warning',
            'bg-cyan-400 text-black': alert.type === 'info',
          }"
        >
          <i class="bi mr-2" :class="getAlertIcon(alert.type)"></i>
          <span class="flex-1">{{ alert.message }}</span>
          <button
            type="button"
            class="ml-2 inline-flex items-center justify-center rounded-full p-1 focus:outline-none focus:ring-2 focus:ring-offset-2"
            :class="
              alert.type === 'warning'
                ? 'text-black focus:ring-yellow-300'
                : 'text-white focus:ring-white/40'
            "
            aria-label="Close"
            @click="alertStore.removeAlert(alert.id)"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              viewBox="0 0 20 20"
              fill="currentColor"
              aria-hidden="true"
            >
              <path
                fill-rule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clip-rule="evenodd"
              />
            </svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useAlertStore } from "@/stores/alert";

const alertStore = useAlertStore();

function getAlertClass(type) {
  const classes = {
    success: "alert-success",
    error: "alert-danger",
    warning: "alert-warning",
    info: "alert-info",
  };
  return classes[type] || "alert-info";
}

function getAlertIcon(type) {
  const icons = {
    success: "bi-check-circle-fill",
    error: "bi-exclamation-triangle-fill",
    warning: "bi-exclamation-triangle-fill",
    info: "bi-info-circle-fill",
  };
  return icons[type] || "bi-info-circle-fill";
}
</script>

<style scoped>
.global-alerts-container {
  position: fixed;
  bottom: 90px;
  right: 10px;
  z-index: 2000;
  pointer-events: none;
}

.alerts-stack {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: 400px;
}

.alert {
  pointer-events: auto;
  margin-bottom: 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  border: none;
}

/* Alert Transitions */
.alert-enter-active {
  transition: all 0.3s ease-out;
}

.alert-leave-active {
  transition: all 0.3s ease-in;
}

.alert-enter-from {
  opacity: 0;
  transform: translateX(100%) scale(0.8);
}

.alert-leave-to {
  opacity: 0;
  transform: translateX(100%) scale(0.8);
}

.alert-move {
  transition: transform 0.3s ease;
}

/* Dark theme adjustments */
.alert-success {
  background-color: #198754;
  border-color: #146c43;
  color: white;
}

.alert-danger {
  background-color: #dc3545;
  border-color: #b02a37;
  color: white;
}

.alert-warning {
  background-color: #ffc107;
  border-color: #ffca2c;
  color: #000;
}

.alert-info {
  background-color: #0dcaf0;
  border-color: #3dd5f3;
  color: #000;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .global-alerts-container {
    display: none;
    top: 10px;
    right: 10px;
    left: 10px;
  }

  .alerts-stack {
    max-width: none;
  }
}
</style>
