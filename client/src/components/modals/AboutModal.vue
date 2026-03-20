<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
    @click="$emit('close')"
  >
    <div
      class="mx-4 w-full max-w-md rounded-lg text-white shadow-xl border border-white/10"
      :class="themeStore.backgroundGradient"
      @click.stop
    >
      <div
        class="flex items-center justify-between border-b border-white/10 p-4"
      >
        <h5 class="text-audinary text-lg font-semibold">
          {{ $t("about.title") }}
        </h5>
        <button
          type="button"
          class="rounded-full p-1 text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
          @click="$emit('close')"
        >
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="p-6 text-center">
        <!-- Logo and App Name -->
        <img
          src="/img/audinary-orange-orange-transparent.png"
          alt="Audinary Logo"
          class="mb-4 mx-auto w-full max-w-60"
        />

        <!-- App Description -->
        <p class="mb-6 text-gray-300">
          {{ $t("about.description") }}
        </p>

        <!-- Links Section -->
        <div class="flex flex-col sm:flex-row justify-center gap-2 mb-6">
          <a
            href="https://github.com/audinary-org/audinary"
            class="inline-flex items-center justify-center px-4 py-2 text-sm border border-white/20 rounded-lg text-white hover:bg-white/10 transition-colors"
            target="_blank"
          >
            <i class="bi bi-github mr-2"></i>{{ $t("about.links.github") }}
          </a>
          <a
            href="https://t.me/audinary_app"
            class="inline-flex items-center justify-center px-4 py-2 text-sm border border-white/20 rounded-lg text-white hover:bg-white/10 transition-colors"
            target="_blank"
          >
            <i class="bi bi-telegram mr-2"></i>{{ $t("about.links.telegram") }}
          </a>
        </div>

        <!-- Copyright and Version -->
        <hr class="border-white/20 mb-4" />
        <p class="text-gray-300 text-sm mb-2">
          <i class="bi bi-c-circle mr-1"></i>
          {{ new Date().getFullYear() }} Audinary
        </p>
        <p class="text-gray-300 text-sm mb-2">
          <span
            >{{ $t("about.credits") }}
            <i class="bi bi-heart text-red-500" aria-hidden="true"></i>
            {{ $t("about.credits2") }}</span
          >
        </p>
        <p class="text-gray-300 text-sm">
          {{ $t("about.version") }} {{ appVersion }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useApiStore } from "@/stores/api";
import { useThemeStore } from "@/stores/theme";

const apiStore = useApiStore();
const themeStore = useThemeStore();

defineEmits(["close"]);

// State
const versionInfo = ref(null);

// Computed
const appVersion = computed(() => {
  if (versionInfo.value) {
    return versionInfo.value.version || "Unbekannt";
  }
  return import.meta.env.VITE_APP_VERSION || "0.17.6"; // Fallback auf Frontend-Version
});

// Methods
async function loadVersion() {
  try {
    const response = await apiStore.get("/api/version");
    versionInfo.value = response.data || response;
  } catch (error) {
    console.warn("Could not load version from API, using fallback:", error);
    // Fallback wird über computed property gehandhabt
  }
}

// Lifecycle
onMounted(() => {
  loadVersion();
});
</script>

<style scoped>
/* TailwindCSS handles most styling, minimal custom styles needed */
</style>
