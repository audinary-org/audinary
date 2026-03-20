import { defineStore } from "pinia";
import { ref } from "vue";

export const useConfigStore = defineStore("config", () => {
  // State
  const config = ref(null);
  const loading = ref(false);
  const error = ref(null);

  // Getters
  const isWishlistEnabled = ref(false);
  const isLastfmConfigured = ref(false);

  // Actions
  async function loadConfig() {
    loading.value = true;
    error.value = null;

    try {
      const response = await fetch("/api/config");
      const data = await response.json();

      if (response.ok && data.success) {
        config.value = data;
        isWishlistEnabled.value = data.wishlist?.enabled || false;
        isLastfmConfigured.value = data.wishlist?.lastfm_configured || false;
      } else {
        throw new Error(data.message || "Failed to load config");
      }
    } catch (err) {
      error.value = err.message;
      console.error("Failed to load config:", err);
    } finally {
      loading.value = false;
    }
  }

  function reset() {
    config.value = null;
    isWishlistEnabled.value = false;
    isLastfmConfigured.value = false;
    error.value = null;
  }

  return {
    // State
    config,
    loading,
    error,
    isWishlistEnabled,
    isLastfmConfigured,

    // Actions
    loadConfig,
    reset,
  };
});
