<template>
  <div class="flex flex-col h-full">
    <ContentHeader
      :title="$t('playlist.title')"
      :show-search="false"
      :show-filter="false"
      :show-view-toggle="true"
      v-model:viewMode="viewMode"
    >
      <template #actions>
        <!-- Favorites Toggle -->
        <button
          :class="
            showFavoritesOnly
              ? 'inline-flex items-center gap-2 px-3 py-1 rounded bg-audinary text-gray'
              : 'inline-flex items-center gap-2 px-3 py-1 border border-audinary rounded text-audinary hover:bg-audinary hover:text-gray'
          "
          @click="toggleFavorites"
        >
          <i class="bi bi-star-fill"></i>
          <span class="hidden md:inline">
            {{
              showFavoritesOnly
                ? $t("playlist.showAll")
                : $t("playlist.showFavorites")
            }}
          </span>
        </button>

        <!-- Create Playlist Button -->
        <button
          class="inline-flex items-center gap-2 px-3 py-1 border border-green-600/90 text-white hover:bg-green-700 rounded-lg"
          @click="showGlobalCreateModal = true"
        >
          <i class="bi bi-plus-lg"></i>
          <span class="hidden md:inline">{{ $t("playlist.create") }}</span>
        </button>
      </template>
    </ContentHeader>

    <!-- Playlists Content Area (scrollable) -->
    <div class="flex-1 overflow-y-auto py-4">
      <div class="max-w-full">
        <!-- Playlists Grid/List Container -->
        <!-- Grid View -->
        <div
          v-if="viewMode === 'grid' && filteredPlaylists.length > 0"
          class="flex gap-4 flex-wrap"
        >
          <div
            v-for="playlist in filteredPlaylists"
            :key="playlist.id"
            class="flex-shrink-0 w-60 group bg-white/10 backdrop-blur-lg rounded shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/20"
            @click="openPlaylistDetail(playlist.id)"
          >
            <div class="relative">
              <div class="relative overflow-hidden mx-auto aspect-square">
                <!-- Playlist cover with CD case overlay -->
                <div class="relative jewel-case" v-if="playlist.id">
                  <SimpleImage
                    imageType="playlist"
                    :imageId="`playlist_${playlist.id}`"
                    :alt="playlist.name"
                    class="absolute top-[2%] left-[10%] w-[87%] h-auto z-[2] object-cover"
                    :placeholder="'music-note-list'"
                    :placeholderSize="'80px'"
                    loading="lazy"
                  />
                  <img
                    :src="'/img/cdcases/default.webp'"
                    class="relative z-[3] w-full h-auto pointer-events-none"
                    alt="CD Case"
                  />
                </div>
                <div
                  v-else
                  class="flex items-center justify-center bg-gray-600 h-40"
                >
                  <i class="bi bi-music-note-list text-white text-6xl"></i>
                </div>

                <!-- Play overlay on hover -->
                <div
                  class="absolute top-[44%] left-1/2 -translate-x-1/2 -translate-y-1/2 w-12 h-12 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center cursor-pointer z-[10]"
                  @click.stop="playPlaylist(playlist.id)"
                >
                  <i
                    class="bi bi-play-circle-fill text-5xl text-white transition-colors hover:text-audinary drop-shadow-lg"
                  ></i>
                </div>

                <!-- Smart Playlist Badge (top left) -->
                <div
                  v-if="playlist.type === 'smart'"
                  class="absolute top-2 left-2 px-2 py-0.5 bg-purple-600/80 backdrop-blur-sm rounded-full flex items-center gap-1 z-[10]"
                >
                  <i class="bi bi-lightning-fill text-[10px] text-yellow-300"></i>
                  <span class="text-[10px] text-white font-medium">Smart</span>
                </div>

                <!-- Favorite icon (top right) -->
                <button
                  class="absolute top-2 right-2 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 z-[10]"
                  @click.stop="togglePlaylistFavorite(playlist)"
                  :title="
                    playlist.is_favorite
                      ? $t('playlist.removeFromFavorites')
                      : $t('playlist.addToFavorites')
                  "
                >
                  <i
                    class="bi text-xs"
                    :class="
                      playlist.is_favorite
                        ? 'bi-star-fill text-yellow-400'
                        : 'bi-star text-gray'
                    "
                  ></i>
                </button>

                <!-- Add to queue button (bottom left) -->
                <button
                  class="absolute bottom-8 left-6 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                  @click.stop="addPlaylistToQueue(playlist.id)"
                  :title="$t('player.addToQueue')"
                >
                  <i class="bi bi-list text-xs text-gray"></i>
                </button>

                <!-- Share button (bottom center) - hidden for smart playlists -->
                <button
                  v-if="canCreateShare && playlist.type !== 'smart'"
                  class="absolute bottom-8 right-12 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                  @click.stop="sharePlaylist(playlist)"
                  :title="$t('shares.share_playlist')"
                >
                  <i class="bi bi-share text-xs text-gray"></i>
                </button>

                <!-- Permissions button (bottom right) - hidden for smart playlists -->
                <button
                  v-if="playlist.type !== 'smart'"
                  class="absolute bottom-8 right-2 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                  @click.stop="managePlaylistPermissions(playlist)"
                  :title="$t('playlist.managePermissions')"
                >
                  <i class="bi bi-people text-xs text-gray"></i>
                </button>
              </div>
            </div>

            <!-- Playlist details -->
            <div class="p-2 pt-0">
              <p
                class="text-center font-semibold mb-1 text-audinary text-lg truncate"
              >
                {{ playlist.name }}
              </p>
              <p class="text-center text-white/80 text-xs truncate mb-1">
                {{ playlist.song_count || 0 }} {{ $t("common.songs") }}
                <span v-if="playlist.duration">
                  • {{ formatDuration(playlist.duration) }}</span
                >
              </p>
              <p
                v-if="playlist.description"
                class="text-center text-white/80 text-xs truncate"
              >
                {{ playlist.description }}
              </p>
            </div>
          </div>
        </div>

        <!-- List View -->
        <div
          v-if="viewMode === 'list' && filteredPlaylists.length > 0"
          class="space-y-2"
        >
          <div
            v-for="playlist in filteredPlaylists"
            :key="playlist.id"
            class="w-full"
          >
            <div
              class="bg-white/10 backdrop-blur-lg rounded shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/20"
              @click="openPlaylistDetail(playlist.id)"
            >
              <div class="flex items-center">
                <!-- Playlist cover (left side) -->
                <div class="relative w-20 h-20 flex-shrink-0">
                  <SimpleImage
                    v-if="playlist.cover_image_uuid"
                    imageType="playlist"
                    :imageId="`playlist_${playlist.cover_image_uuid}`"
                    :alt="playlist.name"
                    class="w-20 h-20 object-cover rounded-l-lg"
                    :placeholder="'music-note-list'"
                    :placeholderSize="'40px'"
                  />
                  <img
                    v-else
                    src="/img/placeholder_audinary.png"
                    :alt="playlist.name"
                    class="w-20 h-20 object-cover rounded-l-lg"
                  />
                  <!-- Play button overlay (appears on hover) -->
                  <div
                    class="absolute inset-0 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity duration-300 bg-gray/50 rounded-l-lg"
                    @click.stop="playPlaylist(playlist.id)"
                  >
                    <i class="bi bi-play-circle-fill text-3xl text-white"></i>
                  </div>
                </div>
                <!-- Playlist details (center) -->
                <div class="flex-1 min-w-0">
                  <div class="py-2 px-3">
                    <div class="flex justify-between items-center">
                      <div class="flex-1 min-w-0">
                        <h5
                          class="text-lg text-audinary font-bold mb-0 truncate"
                        >
                          <span v-if="playlist.type === 'smart'" class="inline-flex items-center gap-1 mr-1 px-1.5 py-0.5 bg-purple-600/60 rounded text-xs text-white font-medium align-middle">
                            <i class="bi bi-lightning-fill text-yellow-300 text-[10px]"></i> Smart
                          </span>
                          {{ playlist.name }}
                        </h5>
                        <p
                          class="text-white/80 mb-0 text-sm truncate"
                          v-if="playlist.description"
                        >
                          {{ playlist.description }}
                        </p>
                        <div
                          class="flex items-center gap-3 text-sm text-white/80"
                        >
                          <span
                            >{{ playlist.song_count || 0 }}
                            {{ $t("common.songs") }}</span
                          >
                          <span v-if="playlist.duration">{{
                            formatDuration(playlist.duration)
                          }}</span>
                        </div>
                      </div>
                      <!-- Additional info and actions (right side) -->
                      <div class="flex items-center mr-2 gap-3">
                        <div class="flex gap-1">
                          <button
                            class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded transition-colors"
                            @click.stop="addPlaylistToQueue(playlist.id)"
                            :title="$t('player.addToQueue')"
                          >
                            <i class="bi bi-music-note-list"></i>
                          </button>
                          <button
                            v-if="playlist.type !== 'smart'"
                            class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded transition-colors"
                            @click.stop="managePlaylistPermissions(playlist)"
                            :title="$t('playlist.managePermissions')"
                          >
                            <i class="bi bi-people"></i>
                          </button>
                          <button
                            v-if="canCreateShare && playlist.type !== 'smart'"
                            class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded transition-colors"
                            @click.stop="sharePlaylist(playlist)"
                            :title="$t('shares.share_playlist')"
                          >
                            <i class="bi bi-share"></i>
                          </button>
                          <button
                            v-if="playlist.type !== 'smart'"
                            class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded transition-colors"
                            @click.stop="openPlaylistDetail(playlist.id)"
                            :title="$t('common.edit')"
                          >
                            <i class="bi bi-pencil"></i>
                          </button>
                          <button
                            v-if="playlist.type !== 'smart'"
                            class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded transition-colors"
                            @click.stop="confirmDeletePlaylist(playlist)"
                            :title="$t('common.delete')"
                          >
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                        <button
                          class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded transition-colors"
                          @click.stop="togglePlaylistFavorite(playlist)"
                          :title="
                            playlist.is_favorite
                              ? $t('playlist.removeFromFavorites')
                              : $t('playlist.addToFavorites')
                          "
                        >
                          <i
                            class="bi"
                            :class="
                              playlist.is_favorite
                                ? 'bi-heart-fill text-red-500'
                                : 'bi-heart'
                            "
                          ></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-if="!isLoading && filteredPlaylists.length === 0"
        class="text-center py-12"
      >
        <i class="bi bi-music-note-beamed text-6xl text-gray-400"></i>
        <h4 class="mt-6 mb-4 text-xl font-semibold">
          {{ $t("playlist.noPlaylists") }}
        </h4>
        <p class="text-gray-400 mb-8">{{ $t("playlist.createFirst") }}</p>
        <button
          class="px-6 py-3 bg-green-600/90 hover:bg-green-700 text-white rounded-lg transition-colors"
          @click="showGlobalCreateModal = true"
        >
          <i class="bi bi-plus-lg mr-2"></i>
          {{ $t("playlist.create") }}
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="text-center py-12">
        <div
          class="inline-block w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"
          role="status"
        >
          <span class="sr-only">{{ $t("common.loading") }}</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <PlaylistDeleteModal
    :is-visible="showDeleteModal"
    :playlist-to-delete="playlistToDelete"
    :is-loading="isLoading"
    @close="showDeleteModal = false"
    @delete-playlist="deletePlaylist"
  />

  <!-- Create Playlist Modal -->
  <PlaylistCreateModal
    :is-visible="showGlobalCreateModal"
    @close="closeAllModals"
    @created="onPlaylistCreated"
  />

  <!-- Add to Playlist Modal -->
  <PlaylistAddToModal
    :is-visible="showPlaylistModal"
    :selected-item="selectedSong"
    @close="closePlaylistModal"
  />

  <!-- Playlist Detail Modal removed - now handled by PlaylistDetailView in MainView -->

  <!-- Playlist Permissions Modal -->
  <PlaylistPermissionsModal
    :is-visible="showPermissionsModal"
    :playlist="selectedPlaylistForPermissions"
    @close="closePermissionsModal"
  />

  <!-- Create Share Modal -->
  <PublicSharesCreateModal
    v-if="showShareModal"
    type="playlist"
    :item-id="selectedPlaylistForShare?.id"
    :item-data="selectedPlaylistForShare"
    @close="closeShareModal"
    @share-created="onShareCreated"
  />
</template>

<script>
import { ref, onMounted, computed, watch } from "vue";
import { useI18n } from "vue-i18n";
import { usePlaylistStore } from "@/stores/playlist";
import { usePlayerStore } from "@/stores/player";
import { useAlertStore } from "@/stores/alert";
import { useApiStore } from "@/stores/api";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import ContentHeader from "@/components/common/ContentHeader.vue";
import PlaylistCreateModal from "@/components/modals/PlaylistCreateModal.vue";
import PlaylistAddToModal from "@/components/modals/PlaylistAddToModal.vue";
import PlaylistPermissionsModal from "@/components/modals/PlaylistPermissionsModal.vue";
import PublicSharesCreateModal from "@/components/modals/PublicSharesCreateModal.vue";
import PlaylistDeleteModal from "@/components/modals/PlaylistDeleteModal.vue";
import { useDetailView } from "@/composables/useDetailView";
import SimpleImage from "@/components/common/SimpleImage.vue";

export default {
  name: "PlaylistsComponent",
  components: {
    ContentHeader,
    PlaylistCreateModal,
    PlaylistAddToModal,
    PlaylistPermissionsModal,
    PublicSharesCreateModal,
    PlaylistDeleteModal,
    SimpleImage,
  },
  setup() {
    const { t } = useI18n();
    const playlistStore = usePlaylistStore();
    const playerStore = usePlayerStore();
    const alertStore = useAlertStore();
    const apiStore = useApiStore();
    const authStore = useAuthStore();
    const route = useRoute();
    const router = useRouter();
    const { openPlaylistDetail: navigateToPlaylist } = useDetailView();

    // Local state
    const isLoading = ref(false);
    const error = ref(null);
    const showDeleteModal = ref(false);
    const playlistToDelete = ref(null);

    // Modal states
    const showGlobalCreateModal = ref(false);

    // Local state
    const selectedPlaylist = ref(null);

    // View and filter state
    const viewMode = ref("grid");
    const showFavoritesOnly = ref(false);

    // Computed
    const playlists = computed(() => playlistStore.userPlaylists);

    const filteredPlaylists = computed(() => {
      let filtered = playlists.value || [];

      // Filter by favorites if enabled
      if (showFavoritesOnly.value) {
        filtered = filtered.filter((playlist) => playlist.is_favorite);
      }

      return filtered;
    });

    // Methods
    async function loadPlaylists() {
      try {
        isLoading.value = true;
        error.value = null;
        await playlistStore.loadPlaylists();
      } catch (err) {
        error.value = err.message;
        alertStore.error(t("playlist.loadError"));
      } finally {
        isLoading.value = false;
      }
    }

    function openPlaylistDetail(playlistId) {
      navigateToPlaylist(playlistId);
    }

    function closeAllModals() {
      showGlobalCreateModal.value = false;
      showDeleteModal.value = false;
      showPermissionsModal.value = false;
    }

    function confirmDeletePlaylist(playlist) {
      playlistToDelete.value = playlist;
      showDeleteModal.value = true;
    }

    async function deletePlaylist() {
      try {
        isLoading.value = true;

        // Get the correct playlist ID - handle both id and playlist_id formats
        const playlistId =
          playlistToDelete.value.id || playlistToDelete.value.playlist_id;

        if (!playlistId) {
          alertStore.error(t("playlist.deleteError"));
          return;
        }

        const response = await fetch(`/api/media/playlists/${playlistId}`, {
          method: "DELETE",
          headers: {
            Authorization: `Bearer ${authStore.token}`,
            "Content-Type": "application/json",
          },
        });

        if (!response.ok) {
          alertStore.error(t("playlist.deleteError"));
          return;
        }

        // Close all modals and reset state
        closeAllModals();
        showDeleteModal.value = false;
        playlistToDelete.value = null;

        // Reload playlists from store
        await playlistStore.loadPlaylists();

        alertStore.success(
          t("playlist.deleted", {
            name: playlistToDelete.value?.name || "Playlist",
          }),
        );
      } catch (err) {
        console.error("Error deleting playlist:", err);
        alertStore.error(t("playlist.deleteError"));
      } finally {
        isLoading.value = false;
      }
    }

    async function playPlaylist(playlistId) {
      try {
        const response = await fetch(`/api/media/playlists/${playlistId}`, {
          headers: {
            Authorization: `Bearer ${authStore.token}`,
            "Content-Type": "application/json",
          },
        });

        if (!response.ok) {
          alertStore.error(t("playlist.playError"));
          return;
        }

        const data = await response.json();
        const songs = data.songs || [];

        if (songs.length === 0) {
          alertStore.warning(t("playlist.noSongs"));
          return;
        }

        playerStore.playPlaylist(songs);
        alertStore.success(t("playlist.playing", { name: "Playlist" }));
      } catch (err) {
        console.error("Error playing playlist:", err);
        alertStore.error(t("playlist.playError"));
      }
    }

    async function addPlaylistToQueue(playlistId) {
      try {
        const response = await fetch(`/api/media/playlists/${playlistId}`, {
          headers: {
            Authorization: `Bearer ${authStore.token}`,
            "Content-Type": "application/json",
          },
        });

        if (!response.ok) {
          alertStore.error(t("playlist.addToQueueError"));
          return;
        }

        const data = await response.json();
        const songs = data.songs || [];

        if (songs.length === 0) {
          alertStore.warning(t("playlist.noSongs"));
          return;
        }

        playerStore.addMultipleToQueue(songs);
        alertStore.success(t("playlist.addedToQueue", { name: "Playlist" }));
      } catch (err) {
        console.error("Error adding playlist to queue:", err);
        alertStore.error(t("playlist.addToQueueError"));
      }
    }

    // Add missing methods for consistent button functionality
    const showPlaylistModal = ref(false);
    const selectedSong = ref(null);

    // Sharing modal state
    const showPermissionsModal = ref(false);
    const selectedPlaylistForPermissions = ref(null);

    // Public share modal state
    const showShareModal = ref(false);
    const selectedPlaylistForShare = ref(null);

    function showPlaylistAddToModal(song) {
      selectedSong.value = song;
      showPlaylistModal.value = true;
    }

    function closePlaylistModal() {
      showPlaylistModal.value = false;
      selectedSong.value = null;
    }

    function formatDate(dateString) {
      if (!dateString) return "";
      return new Date(dateString).toLocaleDateString();
    }

    function formatDuration(seconds) {
      if (!seconds) return "0:00";
      const minutes = Math.floor(seconds / 60);
      const remainingSeconds = seconds % 60;
      return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
    }

    function formatTotalDuration(songs) {
      if (!songs || songs.length === 0) return "0:00";
      const totalSeconds = songs.reduce(
        (total, song) => total + song.duration,
        0,
      );
      const minutes = Math.floor(totalSeconds / 60);
      const remainingSeconds = totalSeconds % 60;
      return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
    }

    // Watch for URL parameters to open playlist from navigation
    watch(
      () => route.query,
      (newQuery) => {
        // Check if specific playlist should be opened from navigation
        if (newQuery.openPlaylist && playlists.value.length > 0) {
          const playlistId = newQuery.openPlaylist;
          navigateToPlaylist(playlistId);
        }
      },
      { immediate: true },
    );

    // Playlist creation handler
    const onPlaylistCreated = () => {
      // Refresh playlists to show the new one
      loadPlaylists();
    };

    // Toggle playlist favorite status
    const togglePlaylistFavorite = async (playlist) => {
      try {
        const playlistId = playlist.id || playlist.id;
        const currentlyFav = playlist.is_favorite;

        await apiStore.toggleFavorite({
          type: "playlist",
          itemId: playlistId,
          currentlyFav,
        });

        // Update local state
        playlist.is_favorite = !currentlyFav;

        // Refresh playlists to update the store
        await loadPlaylists();
      } catch (error) {
        console.error("Error toggling playlist favorite:", error);
        alertStore.error("Failed to toggle favorite status");
      }
    };

    // Manage playlist permissions - open permissions modal
    const managePlaylistPermissions = (playlist) => {
      selectedPlaylistForPermissions.value = playlist;
      showPermissionsModal.value = true;
    };

    function closePermissionsModal() {
      showPermissionsModal.value = false;
      selectedPlaylistForPermissions.value = null;
    }

    // Public share functionality
    const canCreateShare = computed(() => {
      return authStore.isAdmin || authStore.user?.can_create_public_share;
    });

    function sharePlaylist(playlist) {
      selectedPlaylistForShare.value = playlist;
      showShareModal.value = true;
    }

    function closeShareModal() {
      showShareModal.value = false;
      selectedPlaylistForShare.value = null;
    }

    function onShareCreated() {
      // Could show success notification here
      closeShareModal();
    }

    // View mode functions
    const setViewMode = (mode) => {
      viewMode.value = mode;
    };

    // Filter functions
    const toggleFavorites = () => {
      showFavoritesOnly.value = !showFavoritesOnly.value;
    };

    // Initialize
    onMounted(async () => {
      // Wait for authentication to be fully confirmed before loading data
      if (
        authStore.isAuthenticated &&
        authStore.isInitialized &&
        !authStore.isLoading
      ) {
        await loadPlaylists();
      } else {
        // Watch for authentication changes
        const unwatch = authStore.$subscribe(() => {
          if (
            authStore.isAuthenticated &&
            authStore.isInitialized &&
            !authStore.isLoading
          ) {
            loadPlaylists();
            unwatch(); // Stop watching once data is loaded
          }
        });
      }
    });

    return {
      // State
      isLoading,
      error,
      showDeleteModal,
      playlistToDelete,

      // Global modal state
      showGlobalCreateModal,
      closeAllModals,

      // Computed
      playlists,
      filteredPlaylists,

      // View and filter functionality
      viewMode,
      setViewMode,
      showFavoritesOnly,
      toggleFavorites,

      // Methods
      loadPlaylists,
      openPlaylistDetail,
      confirmDeletePlaylist,
      deletePlaylist,
      formatDate,
      formatDuration,
      formatTotalDuration,
      onPlaylistCreated,

      // Player functions
      playPlaylist,
      addPlaylistToQueue,

      // Playlist favorites functionality
      togglePlaylistFavorite,

      // Playlist modal (add-to)
      showPlaylistAddToModal,
      closePlaylistModal,
      showPlaylistModal,
      selectedSong,

      // Permissions functionality
      showPermissionsModal,
      selectedPlaylistForPermissions,
      closePermissionsModal,
      managePlaylistPermissions,

      // Public share functionality
      canCreateShare,
      showShareModal,
      selectedPlaylistForShare,
      sharePlaylist,
      closeShareModal,
      onShareCreated,

      // Playlist state
      selectedPlaylist,

      // i18n
      t,
    };
  },
};
</script>

<style scoped>
/* TailwindCSS handles most styling, minimal custom styles needed */

/* Performance optimizations for large lists */
.group {
  /* Browser automatically manages rendering for off-screen elements */
  content-visibility: auto;

  /* Reserve space to prevent layout shift */
  contain-intrinsic-size: 400px;

  /* Isolate this element's style recalculations */
  contain: layout style paint;

  /* Hint to browser that transforms will be animated */
  will-change: transform;
}

/* Group hover effects for actions */
.group:hover {
  /* Use transform instead of changing other properties for better performance */
  transform: translateZ(0);
}

.group:hover .group-hover\:opacity-100 {
  opacity: 1;
}

/* Mobile device optimizations - always show actions */
@media (max-width: 768px) {
  .group-hover\:opacity-100 {
    opacity: 1;
  }
}

/* Touch device optimizations - always show actions */
@media (hover: none) and (pointer: coarse) {
  .group-hover\:opacity-100 {
    opacity: 1;
  }
}

/* Jewel case effect for playlist covers */
.jewel-case::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(
    45deg,
    transparent 30%,
    rgba(255, 255, 255, 0.05) 50%,
    transparent 70%
  );
  border-radius: 4px;
  pointer-events: none;
  z-index: 25;
}
</style>
