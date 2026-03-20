<template>
  <div
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="$emit('cancel')"
  >
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-sm mx-4 shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
          <i class="bi" :class="getIcon()"></i>
          {{ title }}
        </h2>
        <button
          @click="$emit('cancel')"
          class="text-gray-400 hover:text-white transition-colors"
        >
          <i class="bi bi-x-lg text-lg"></i>
        </button>
      </div>

      <!-- Message -->
      <div class="mb-6">
        <p class="text-white/80">{{ message }}</p>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-3">
        <button
          type="button"
          @click="$emit('cancel')"
          class="px-4 py-2 text-gray-300 hover:text-white transition-colors"
        >
          {{ cancelText }}
        </button>
        <button
          type="button"
          @click="$emit('confirm')"
          class="px-6 py-2 font-semibold rounded-lg transition-all"
          :class="getButtonClasses()"
        >
          {{ confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
const emit = defineEmits(["confirm", "cancel"]);

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  message: {
    type: String,
    required: true,
  },
  confirmText: {
    type: String,
    default: "Confirm",
  },
  cancelText: {
    type: String,
    default: "Cancel",
  },
  variant: {
    type: String,
    default: "primary",
    validator: (value) => ["primary", "danger", "warning"].includes(value),
  },
});

function getIcon() {
  const icons = {
    primary: "bi-question-circle",
    danger: "bi-exclamation-triangle",
    warning: "bi-exclamation-triangle",
  };
  return icons[props.variant] || icons.primary;
}

function getButtonClasses() {
  const classes = {
    primary: "bg-audinary hover:bg-audinary/90 text-black",
    danger: "bg-red-600 hover:bg-red-700 text-white",
    warning: "bg-yellow-600 hover:bg-yellow-700 text-white",
  };
  return classes[props.variant] || classes.primary;
}
</script>
