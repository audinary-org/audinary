import { defineStore } from "pinia";
import { ref } from "vue";
import { useApiStore } from "./api";

export const usePublicShareStore = defineStore("publicShare", () => {
  // State
  const publicShares = ref([]);
  const isLoading = ref(false);
  const error = ref(null);

  // Actions
  async function createPublicShare(type, itemId, options = {}) {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const shareData = {
        type, // 'song', 'album', 'playlist'
        item_id: itemId,
        download: options.download || false,
        expires_at: options.expires_at || null,
        password_hash: options.password_hash || null,
      };

      const response = await apiStore.makeRequest("/api/media/public-shares", {
        method: "POST",
        body: JSON.stringify(shareData),
      });

      if (!response.ok) {
        throw new Error("Failed to create public share");
      }

      const result = await response.json();

      // Add to local state
      if (result.share) {
        publicShares.value.unshift(result.share);
      }

      return result;
    } catch (err) {
      error.value = err.message;
      console.error("Create public share error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function getPublicShare(shareId) {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/public-shares/${shareId}`,
      );

      if (!response.ok) {
        throw new Error("Failed to load public share");
      }

      return await response.json();
    } catch (err) {
      error.value = err.message;
      console.error("Get public share error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function loadUserPublicShares() {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/media/public-shares");

      if (!response.ok) {
        throw new Error("Failed to load public shares");
      }

      const data = await response.json();
      publicShares.value = data.shares || data || [];
    } catch (err) {
      error.value = err.message;
      console.error("Load public shares error:", err);
    } finally {
      isLoading.value = false;
    }
  }

  async function updatePublicShare(shareId, options) {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/public-shares/${shareId}`,
        {
          method: "PUT",
          body: JSON.stringify(options),
        },
      );

      if (!response.ok) {
        throw new Error("Failed to update public share");
      }

      const updatedShare = await response.json();

      // Update in local state
      const index = publicShares.value.findIndex((s) => s.id === shareId);
      if (index !== -1) {
        publicShares.value[index] = updatedShare.share || updatedShare;
      }

      return updatedShare;
    } catch (err) {
      error.value = err.message;
      console.error("Update public share error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function deletePublicShare(shareId) {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/public-shares/${shareId}`,
        {
          method: "DELETE",
        },
      );

      if (!response.ok) {
        throw new Error("Failed to delete public share");
      }

      // Remove from local state
      publicShares.value = publicShares.value.filter((s) => s.id !== shareId);
    } catch (err) {
      error.value = err.message;
      console.error("Delete public share error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  function clearError() {
    error.value = null;
  }

  return {
    // State
    publicShares,
    isLoading,
    error,

    // Actions
    createPublicShare,
    getPublicShare,
    loadUserPublicShares,
    updatePublicShare,
    deletePublicShare,
    clearError,
  };
});
