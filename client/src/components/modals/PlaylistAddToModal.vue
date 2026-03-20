<template>
  <div
    v-if="isVisible"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
    @click="handleBackdropClick"
  >
    <div
      class="w-full max-w-md max-h-[90vh] overflow-hidden rounded-lg text-white shadow-xl border border-white/10"
      :class="themeStore.backgroundGradient"
      @click.stop
    >
      <div
        class="flex items-center justify-between border-b border-white/10 p-4"
      >
        <h5 class="text-lg font-semibold">{{ $t("songs.add_to_playlist") }}</h5>
        <button
          type="button"
          class="rounded-full p-1 text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
          @click="closeModal"
        >
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="flex-1 p-6 overflow-y-auto">
        <!-- Selected Song/Track Display -->
        <div
          v-if="selectedItem || isAlbumMode"
          class="mb-4 p-3 rounded-lg bg-white/5 border border-white/10"
        >
          <!-- Single Song Mode -->
          <div v-if="selectedItem && !isAlbumMode" class="flex items-center">
            <div class="relative w-12 h-12 mr-3">
              <!-- Gradient placeholder background -->
              <div
                v-if="
                  selectedItem.coverGradient &&
                  selectedItem.coverGradient.colors
                "
                class="absolute inset-0 rounded"
                :style="{
                  background: `linear-gradient(${selectedItem.coverGradient.angle || 135}deg, ${selectedItem.coverGradient.colors.join(', ')})`,
                  filter: 'blur(10px)',
                  zIndex: 1,
                }"
              ></div>
              <SimpleImage
                :imageType="'album_thumbnail'"
                :imageId="selectedItem.album_id"
                :alt="selectedItem.album || selectedItem.albumTitle"
                class="rounded w-12 h-12 object-cover relative z-[2]"
                loading="lazy"
              />
            </div>
            <div>
              <h6 class="mb-1 font-medium">{{ selectedItem.title }}</h6>
              <p class="mb-0 text-gray-400 text-sm">
                {{ selectedItem.artist || selectedItem.albumArtist }} -
                {{ selectedItem.album || selectedItem.albumTitle }}
              </p>
            </div>
          </div>

          <!-- Album Mode -->
          <div v-else-if="isAlbumMode" class="flex items-center">
            <div class="relative w-12 h-12 mr-3">
              <!-- Gradient placeholder background -->
              <div
                v-if="
                  selectedTracks[0]?.coverGradient &&
                  selectedTracks[0].coverGradient.colors
                "
                class="absolute inset-0 rounded"
                :style="{
                  background: `linear-gradient(${selectedTracks[0].coverGradient.angle || 135}deg, ${selectedTracks[0].coverGradient.colors.join(', ')})`,
                  filter: 'blur(10px)',
                  zIndex: 1,
                }"
              ></div>
              <SimpleImage
                :imageType="'album_thumbnail'"
                :imageId="selectedTracks[0]?.album_id"
                :alt="albumTitle"
                class="rounded w-12 h-12 object-cover relative z-[2]"
                loading="lazy"
              />
            </div>
            <div>
              <h6 class="mb-1 font-medium">{{ albumTitle }}</h6>
              <p class="mb-0 text-gray-400 text-sm">
                <i class="bi bi-disc mr-1"></i>
                {{ selectedTracks.length }} {{ $t("common.songs") }}
                <span v-if="selectedTracks[0]?.artist">
                  - {{ selectedTracks[0].artist }}</span
                >
              </p>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="text-center py-8">
          <div
            class="inline-block w-8 h-8 border-4 border-gray-600 border-t-audinary rounded-full animate-spin"
          ></div>
          <span class="sr-only">{{ $t("common.loading") }}</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="playlists.length === 0" class="text-center py-8">
          <i class="bi bi-music-note-list text-5xl text-gray-400"></i>
          <p class="text-gray-400 mt-3 mb-3">
            {{ $t("playlist.noPlaylists") }}
          </p>
          <button
            class="px-4 py-2 bg-audinary hover:bg-audinary/90 rounded-lg text-black transition-colors"
            @click="$emit('create-playlist')"
          >
            <i class="bi bi-plus mr-2"></i>{{ $t("playlist.create") }}
          </button>
        </div>

        <!-- Playlists List -->
        <div v-else>
          <h6 class="mb-3 font-medium">
            {{
              isAlbumMode
                ? $t("album.addToPlaylist")
                : $t("playlist.allPlaylists")
            }}
          </h6>
          <div class="space-y-2 max-h-64 overflow-y-auto">
            <div
              v-for="playlist in playlists"
              :key="playlist.id"
              class="flex items-center justify-between p-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 cursor-pointer transition-colors"
              @click="handleAddToPlaylist(playlist)"
            >
              <div class="flex items-center flex-1 min-w-0">
                <img
                  :src="getPlaylistCover(playlist)"
                  :alt="playlist.name"
                  class="rounded mr-3 w-10 h-10 object-cover"
                />
                <div class="min-w-0 flex-1">
                  <div class="font-medium text-white truncate">
                    {{ playlist.name }}
                  </div>
                  <div class="text-gray-400 text-sm">
                    {{ playlist.song_count || 0 }} {{ $t("common.songs") }}
                    <span v-if="playlist.duration">
                      • {{ formatDuration(playlist.duration) }}</span
                    >
                  </div>
                </div>
              </div>
              <button
                class="px-3 py-1 text-sm border border-audinary text-audinary hover:bg-audinary hover:text-black rounded transition-colors disabled:opacity-50"
                @click.stop="handleAddToPlaylist(playlist)"
                :disabled="isAdding"
              >
                <span
                  v-if="isAdding"
                  class="inline-block w-4 h-4 border-2 border-audinary/50 border-t-audinary rounded-full animate-spin"
                ></span>
                <i v-else class="bi bi-plus"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="flex justify-end border-t border-white/10 p-4">
        <button
          type="button"
          class="px-4 py-2 text-sm border border-white/20 rounded-lg text-gray-300 hover:bg-white/10 transition-colors"
          @click="closeModal"
        >
          {{ $t("common.cancel") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch } from "vue";
import { useI18n } from "vue-i18n";
import { usePlaylistStore } from "@/stores/playlist";
import { useAlertStore } from "@/stores/alert";
import { useAuthStore } from "@/stores/auth";
import { useThemeStore } from "@/stores/theme";
import SimpleImage from "@/components/common/SimpleImage.vue";

export default {
  name: "PlaylistAddToModal",
  components: {
    SimpleImage,
  },
  emits: ["close", "added", "create-playlist"],
  props: {
    isVisible: {
      type: Boolean,
      default: false,
    },
    selectedItem: {
      type: Object,
      default: null,
    },
    selectedTracks: {
      type: Array,
      default: () => [],
    },
    albumTitle: {
      type: String,
      default: "",
    },
  },
  setup(props, { emit }) {
    const { t } = useI18n();
    const playlistStore = usePlaylistStore();
    const alertStore = useAlertStore();
    const authStore = useAuthStore();
    const themeStore = useThemeStore();

    const isAdding = ref(false);
    const isLoading = ref(false);
    const playlists = ref([]);

    // Computed properties
    const isAlbumMode = computed(
      () => props.selectedTracks && props.selectedTracks.length > 0,
    );
    const itemDisplayName = computed(() => {
      if (isAlbumMode.value) {
        return props.albumTitle || "Album";
      }
      return props.selectedItem?.title || "Song";
    });

    // Load playlists when modal becomes visible
    const isVisible = computed(() => props.isVisible);

    // Watch for visibility changes
    watch(isVisible, (newVal) => {
      if (newVal) {
        loadPlaylists();
      }
    });

    // Load playlists function
    async function loadPlaylists() {
      try {
        isLoading.value = true;
        await playlistStore.loadPlaylists();
        playlists.value = playlistStore.userPlaylists;
      } catch (error) {
        console.error("Error loading playlists:", error);
        alertStore.error(t("playlist.loadError"));
        playlists.value = [];
      } finally {
        isLoading.value = false;
      }
    }

    // Add single item to playlist
    async function addToPlaylist(playlist, item) {
      try {
        const playlistId = playlist.id;
        const itemId = item.id || item.song_id;

        await playlistStore.addSongToPlaylist(playlistId, itemId);
        alertStore.success(
          t("songs.addedToPlaylist", {
            title: item.title,
            playlist: playlist.name,
          }),
        );
      } catch (error) {
        console.error("Error adding to playlist:", error);
        alertStore.error(t("playlist.addError"));
        throw error;
      }
    }

    // Add multiple tracks (album) to playlist
    async function addAlbumToPlaylist(playlist, tracks, albumTitle) {
      try {
        const playlistId = playlist.id;
        const results = [];

        for (const track of tracks) {
          const trackId = track.id || track.song_id;
          await playlistStore.addSongToPlaylist(playlistId, trackId);
          results.push({ success: true, track });
        }

        alertStore.success(
          t("album.addedToPlaylist", {
            album: albumTitle,
            playlist: playlist.name,
            count: tracks.length,
          }),
        );

        return { success: true, results };
      } catch (error) {
        console.error("Error adding album to playlist:", error);
        alertStore.error(t("playlist.addError"));
        throw error;
      }
    }

    const closeModal = () => {
      emit("close");
    };

    const handleBackdropClick = () => {
      closeModal();
    };

    const handleAddToPlaylist = async (playlist) => {
      if (!props.selectedItem && !isAlbumMode.value) return;

      try {
        isAdding.value = true;

        if (isAlbumMode.value) {
          // Add all tracks from album
          const result = await addAlbumToPlaylist(
            playlist,
            props.selectedTracks,
            props.albumTitle,
          );
          emit("added", { playlist, tracks: props.selectedTracks, result });
        } else {
          // Add single song
          await addToPlaylist(playlist, props.selectedItem);
          emit("added", { playlist, item: props.selectedItem });
        }

        closeModal();
      } catch (error) {
        console.error("Error adding to playlist:", error);
      } finally {
        isAdding.value = false;
      }
    };

    const formatDuration = (seconds) => {
      if (!seconds) return "--:--";
      const mins = Math.floor(seconds / 60);
      const secs = seconds % 60;
      return `${mins}:${secs.toString().padStart(2, "0")}`;
    };

    const getPlaylistCover = (playlist) => {
      // Use the cover_url provided by the API if available
      if (playlist.cover_url) {
        return playlist.cover_url;
      }
      // Fallback: construct URL if cover_image exists
      if (playlist.cover_image) {
        return `/api/playlist-cover?playlistId=${playlist.id}`;
      }
      // Default placeholder
      return "/img/placeholder_audinary.png";
    };

    return {
      t,
      isAdding,
      playlists,
      isLoading,
      isAlbumMode,
      itemDisplayName,
      closeModal,
      handleBackdropClick,
      handleAddToPlaylist,
      formatDuration,
      getPlaylistCover,
      loadPlaylists,
      addToPlaylist,
      addAlbumToPlaylist,
      themeStore,
    };
  },
};
</script>

<style scoped>
/* TailwindCSS handles most styling, minimal custom styles needed */
/* Custom scrollbar styles for better UX */
.max-h-64::-webkit-scrollbar {
  width: 8px;
}

.max-h-64::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

.max-h-64::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 4px;
}

.max-h-64::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Responsive adjustments for mobile */
@media (max-width: 768px) {
  .max-h-64 {
    max-height: calc(100vh - 300px);
  }
}
</style>
