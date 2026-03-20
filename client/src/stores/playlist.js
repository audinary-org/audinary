import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { useAuthStore } from "./auth";
import { useApiStore } from "./api";

export const usePlaylistStore = defineStore("playlist", () => {
  // State
  const playlists = ref([]);
  const currentPlaylist = ref(null);
  const isLoading = ref(false);
  const error = ref(null);

  // Computed
  const userPlaylists = computed(() => {
    const authStore = useAuthStore();
    const userId = authStore.user?.user_id || authStore.user?.id;

    return playlists.value.filter(
      (playlist) => playlist.user_id === userId || playlist.type === "user",
    );
  });

  const sharedPlaylists = computed(() => {
    const authStore = useAuthStore();
    const userId = authStore.user?.user_id || authStore.user?.id;

    // Playlists shared with current user (they have permissions but don't own)
    return playlists.value.filter(
      (playlist) =>
        playlist.user_id !== userId && playlist.shared_with_me === true,
    );
  });

  const favoritePlaylistsForNavbar = computed(() => {
    // This will be handled by the favorites store instead
    // For backward compatibility, return empty array for now
    return [];
  });

  // Actions
  async function loadPlaylists() {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/media/playlists");

      if (!response.ok) {
        throw new Error("Failed to load playlists");
      }

      const data = await response.json();
      playlists.value = data.playlists || data || [];
    } catch (err) {
      error.value = err.message;
      console.error("Load playlists error:", err);
    } finally {
      isLoading.value = false;
    }
  }

  async function loadPlaylist(playlistId) {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}`,
      );

      if (!response.ok) {
        throw new Error("Failed to load playlist");
      }

      const playlist = await response.json();
      currentPlaylist.value = playlist;
      return playlist;
    } catch (err) {
      error.value = err.message;
      console.error("Load playlist error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function createPlaylist(playlistData) {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/media/playlists", {
        method: "POST",
        body: JSON.stringify(playlistData),
      });

      if (!response.ok) {
        throw new Error("Failed to create playlist");
      }

      const result = await response.json();

      // Add the new playlist to local state
      if (result.playlist) {
        playlists.value.unshift(result.playlist);
        return result.playlist;
      } else if (result.id) {
        // If only ID returned, fetch the full playlist
        const newPlaylist = await loadPlaylist(result.id);
        return newPlaylist;
      } else {
        throw new Error("Invalid response format from API");
      }
    } catch (err) {
      error.value = err.message;
      console.error("Create playlist error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }
  async function updatePlaylist(playlistId, playlistData) {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}`,
        {
          method: "PUT",
          body: JSON.stringify(playlistData),
        },
      );

      if (!response.ok) {
        throw new Error("Failed to update playlist");
      }

      const updatedPlaylist = await response.json();

      // Update in local state
      const index = playlists.value.findIndex((p) => p.id === playlistId);
      if (index !== -1) {
        playlists.value[index] = updatedPlaylist.playlist || updatedPlaylist;
      }

      if (currentPlaylist.value?.id === playlistId) {
        currentPlaylist.value = updatedPlaylist.playlist || updatedPlaylist;
      }

      return updatedPlaylist.playlist || updatedPlaylist;
    } catch (err) {
      error.value = err.message;
      console.error("Update playlist error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function deletePlaylist(playlistId) {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}`,
        {
          method: "DELETE",
        },
      );

      if (!response.ok) {
        throw new Error("Failed to delete playlist");
      }

      // Remove from local state
      playlists.value = playlists.value.filter((p) => p.id !== playlistId);

      if (currentPlaylist.value?.id === playlistId) {
        currentPlaylist.value = null;
      }
    } catch (err) {
      error.value = err.message;
      console.error("Delete playlist error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function deletePlaylist(playlistId) {
    isLoading.value = true;
    error.value = null;

    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}`,
        {
          method: "DELETE",
        },
      );

      if (!response.ok) {
        throw new Error("Failed to delete playlist");
      }

      // Remove from local state
      playlists.value = playlists.value.filter((p) => p.id !== playlistId);

      if (currentPlaylist.value?.id === playlistId) {
        currentPlaylist.value = null;
      }
    } catch (err) {
      error.value = err.message;
      console.error("Delete playlist error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function addSongToPlaylist(playlistId, songId, position = null) {
    try {
      const apiStore = useApiStore();
      const body = { song_id: songId };
      if (position !== null) {
        body.position = position;
      }

      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}/songs`,
        {
          method: "POST",
          body: JSON.stringify(body),
        },
      );

      if (!response.ok) {
        throw new Error("Failed to add song to playlist");
      }

      // Update playlist song count if needed
      const playlist = playlists.value.find((p) => p.id === playlistId);
      if (playlist) {
        playlist.song_count = (playlist.song_count || 0) + 1;
      }

      // Update current playlist if it's the one being modified
      if (currentPlaylist.value?.id === playlistId) {
        await loadPlaylist(playlistId); // Reload to get updated songs
      }
    } catch (err) {
      error.value = err.message;
      console.error("Add song to playlist error:", err);
      throw err;
    }
  }

  async function removeSongFromPlaylist(playlistId, songId) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}/songs/${songId}`,
        {
          method: "DELETE",
        },
      );

      if (!response.ok) {
        throw new Error("Failed to remove song from playlist");
      }

      // Update current playlist if it's the one being modified
      if (currentPlaylist.value?.id === playlistId) {
        currentPlaylist.value.songs = currentPlaylist.value.songs.filter(
          (song) => song.id !== songId,
        );
      }

      // Update playlist song count in list
      const playlist = playlists.value.find((p) => p.id === playlistId);
      if (playlist && playlist.song_count > 0) {
        playlist.song_count--;
      }
    } catch (err) {
      error.value = err.message;
      console.error("Remove song from playlist error:", err);
      throw err;
    }
  }

  async function updateSongPosition(playlistId, songId, position) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}/songs/${songId}`,
        {
          method: "PUT",
          body: JSON.stringify({ position }),
        },
      );

      if (!response.ok) {
        throw new Error("Failed to update song position");
      }

      // Reload current playlist to get updated order
      if (currentPlaylist.value?.id === playlistId) {
        await loadPlaylist(playlistId);
      }
    } catch (err) {
      error.value = err.message;
      console.error("Update song position error:", err);
      throw err;
    }
  }

  async function reorderPlaylistSongs(playlistId, songPositions) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}/songs/reorder`,
        {
          method: "PUT",
          body: JSON.stringify({ song_positions: songPositions }),
        },
      );

      if (!response.ok) {
        throw new Error("Failed to reorder playlist songs");
      }

      // Reload current playlist to get updated order
      if (currentPlaylist.value?.id === playlistId) {
        await loadPlaylist(playlistId);
      }
    } catch (err) {
      error.value = err.message;
      console.error("Reorder playlist songs error:", err);
      throw err;
    }
  }

  function clearCurrentPlaylist() {
    currentPlaylist.value = null;
  }

  function clearError() {
    error.value = null;
  }

  // Playlist Permissions Management
  async function getPlaylistPermissions(playlistId) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}/permissions`,
      );

      if (!response.ok) {
        throw new Error("Failed to load playlist permissions");
      }

      return await response.json();
    } catch (err) {
      error.value = err.message;
      console.error("Get playlist permissions error:", err);
      throw err;
    }
  }

  async function grantPlaylistPermission(
    playlistId,
    userId,
    permissionType = "view",
  ) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}/share`,
        {
          method: "POST",
          body: JSON.stringify({
            user_id: userId,
            permission_type: permissionType,
          }),
        },
      );

      if (!response.ok) {
        throw new Error("Failed to grant playlist permission");
      }

      return await response.json();
    } catch (err) {
      error.value = err.message;
      console.error("Grant playlist permission error:", err);
      throw err;
    }
  }

  async function updatePlaylistPermission(playlistId, userId, permissionType) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}/share`,
        {
          method: "POST",
          body: JSON.stringify({
            user_id: userId,
            permission_type: permissionType,
          }),
        },
      );

      if (!response.ok) {
        throw new Error("Failed to update playlist permission");
      }

      return await response.json();
    } catch (err) {
      error.value = err.message;
      console.error("Update playlist permission error:", err);
      throw err;
    }
  }

  async function revokePlaylistPermission(playlistId, userId) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/media/playlists/${playlistId}/share/${userId}`,
        {
          method: "DELETE",
        },
      );

      if (!response.ok) {
        throw new Error("Failed to revoke playlist permission");
      }
    } catch (err) {
      error.value = err.message;
      console.error("Revoke playlist permission error:", err);
      throw err;
    }
  }

  // User search functions for playlist sharing
  async function getAvailableUsers() {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/users/available");

      if (!response.ok) {
        throw new Error("Failed to load available users");
      }

      return await response.json();
    } catch (err) {
      error.value = err.message;
      console.error("Get available users error:", err);
      throw err;
    }
  }

  async function searchUsers(query) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest(
        `/api/users/search?q=${encodeURIComponent(query)}`,
      );

      if (!response.ok) {
        throw new Error("Failed to search users");
      }

      return await response.json();
    } catch (err) {
      error.value = err.message;
      console.error("Search users error:", err);
      throw err;
    }
  }

  return {
    // State
    playlists,
    currentPlaylist,
    isLoading,
    error,

    // Computed
    userPlaylists,
    sharedPlaylists,
    favoritePlaylistsForNavbar,

    // Actions
    loadPlaylists,
    loadPlaylist,
    createPlaylist,
    updatePlaylist,
    deletePlaylist,
    addSongToPlaylist,
    removeSongFromPlaylist,
    updateSongPosition,
    reorderPlaylistSongs,
    clearCurrentPlaylist,
    clearError,

    // Permission management
    getPlaylistPermissions,
    grantPlaylistPermission,
    updatePlaylistPermission,
    revokePlaylistPermission,

    // User search
    getAvailableUsers,
    searchUsers,
  };
});
