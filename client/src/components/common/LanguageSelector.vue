<template>
  <div class="relative" ref="languageSelector">
    <label v-if="label" class="block text-sm font-medium text-white mb-2">{{
      label
    }}</label>

    <!-- Custom Dropdown Button -->
    <button
      type="button"
      class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent flex items-center justify-between hover:bg-white/15 transition-colors"
      @click="isOpen = !isOpen"
    >
      <div class="flex items-center gap-3">
        <FlagIcon
          v-if="selectedLocale?.countryCode"
          :countryCode="selectedLocale.countryCode"
          size="sm"
          :shadow="false"
        />
        <span v-else class="text-lg">{{ selectedLocale?.flag }}</span>
        <span>{{ selectedLocale?.name || "Select Language" }}</span>
      </div>
      <i
        class="bi bi-chevron-down transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
      ></i>
    </button>

    <!-- Dropdown Menu -->
    <div
      v-show="isOpen"
      class="absolute z-10 w-full mt-1 bg-black/90 backdrop-blur-md border border-white/10 rounded-lg shadow-lg max-h-60 overflow-auto"
    >
      <button
        v-for="locale in availableLocales"
        :key="locale.code"
        type="button"
        class="w-full px-3 py-2.5 flex items-center gap-3 text-left hover:bg-white/10 transition-colors focus:bg-white/10 focus:outline-none"
        :class="{ 'bg-white/5': locale.code === modelValue }"
        @click="selectLanguage(locale)"
      >
        <FlagIcon
          v-if="locale.countryCode"
          :countryCode="locale.countryCode"
          size="sm"
          :shadow="false"
        />
        <span v-else class="text-lg">{{ locale.flag }}</span>
        <div class="flex-1">
          <div class="text-white font-medium">{{ locale.name }}</div>
          <div v-if="locale.code === 'auto'" class="text-xs text-gray-400">
            {{ $t("settings.language.auto_description") }}
          </div>
        </div>
        <i
          v-if="locale.code === modelValue"
          class="bi bi-check text-audinary"
        ></i>
      </button>
    </div>

    <!-- Description -->
    <div v-if="description" class="text-xs text-gray-400 mt-1">
      {{ description }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useI18n } from "vue-i18n";
import { getAvailableLocales } from "@/i18n";
import FlagIcon from "@/components/common/FlagIcon.vue";

const { t } = useI18n();

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  label: {
    type: String,
    default: "",
  },
  description: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update:modelValue", "change"]);

// Reactive data
const isOpen = ref(false);
const availableLocales = getAvailableLocales();
const languageSelector = ref(null);

// Computed
const selectedLocale = computed(() => {
  return availableLocales.find((locale) => locale.code === props.modelValue);
});

// Methods
function selectLanguage(locale) {
  emit("update:modelValue", locale.code);
  emit("change", locale.code);
  isOpen.value = false;
}

function handleOutsideClick(event) {
  if (
    languageSelector.value &&
    !languageSelector.value.contains(event.target)
  ) {
    isOpen.value = false;
  }
}

// Lifecycle
onMounted(() => {
  // Close dropdown when clicking outside
  document.addEventListener("click", handleOutsideClick);
});

onUnmounted(() => {
  document.removeEventListener("click", handleOutsideClick);
});
</script>

<style scoped>
/* Smooth animations */
.rotate-180 {
  transform: rotate(180deg);
}

/* Custom scrollbar for dropdown */
.max-h-60::-webkit-scrollbar {
  width: 6px;
}

.max-h-60::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.max-h-60::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
}

.max-h-60::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}
</style>
