<template>
  <div class="dashboard-section">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-semibold text-audinary m-0">
        {{ $t("dashboard.recent_played_albums") }}
      </h3>
      <a
        href="#"
        class="text-white/80 hover:text-audinary transition-colors mobile-touch-target flex items-center text-sm"
        @click.prevent="showAll"
      >
        {{ $t("common.show_all") }} <i class="bi bi-chevron-right ml-1"></i>
      </a>
    </div>

    <div class="relative">
      <button
        v-if="canScrollLeft"
        class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-10 h-10 bg-black/70 hover:bg-black/90 border-none rounded-full text-white flex items-center justify-center transition-colors mobile-touch-target"
        @click="scrollLeft"
        :aria-label="$t('common.scroll_left')"
      >
        <i class="bi bi-chevron-left"></i>
      </button>

      <div
        class="overflow-x-auto overflow-y-hidden scrollbar-hide px-5"
        ref="scrollContent"
        style="scroll-behavior: smooth"
      >
        <div v-if="loading" class="flex justify-center py-8">
          <div
            class="animate-spin w-8 h-8 border-4 border-gray-600 border-t-gray-300 rounded-full"
          >
            <span class="sr-only">{{ $t("common.loading") }}</span>
          </div>
        </div>

        <div v-else class="flex gap-4 pb-2">
          <div
            v-for="album in albums"
            :key="album.album_id"
            class="flex-shrink-0 w-60 group bg-white/15 rounded shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/25"
            @click="showAlbumDetail(album)"
          >
            <div class="relative">
              <div class="relative overflow-hidden mx-auto aspect-square">
                <!-- Album cover image with CD case overlay -->
                <div class="relative w-full h-full" v-if="album.album_id">
                  <!-- Gradient placeholder background -->
                  <div
                    v-if="album.coverGradient && album.coverGradient.colors"
                    class="absolute top-[2%] left-[10%] w-[87%]"
                    :style="{
                      height: '87%',
                      background: `linear-gradient(${album.coverGradient.angle || 135}deg, ${album.coverGradient.colors.join(', ')})`,
                      filter: 'blur(10px)',
                      zIndex: 1,
                    }"
                  ></div>
                  <SimpleImage
                    image-type="album"
                    :image-id="album.album_id.toString()"
                    :alt="album.albumName"
                    class="absolute top-[2%] left-[10%] w-[87%] h-auto z-[2] object-cover"
                    :placeholder="'disc'"
                    :placeholderSize="'80px'"
                    loading="lazy"
                  />
                  <img
                    :src="getCdCaseImage(album.albumFiletype)"
                    class="relative z-[3] w-full h-auto pointer-events-none"
                    alt="CD Case"
                    @error="$event.target.src = '/img/cdcases/default.webp'"
                  />
                </div>
                <div
                  v-else
                  class="flex items-center justify-center bg-gray-600 h-40"
                >
                  <i class="bi bi-disc text-white text-6xl"></i>
                </div>
                <!-- Play overlay on hover -->
                <div
                  class="absolute top-[44%] left-1/2 -translate-x-1/2 -translate-y-1/2 w-12 h-12 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center cursor-pointer z-[10]"
                  @click.stop="playAlbum(album)"
                >
                  <i
                    class="bi bi-play-circle-fill text-5xl text-white transition-colors hover:text-audinary drop-shadow-lg"
                  ></i>
                </div>
                <!-- Favorite icon (top right) -->
                <button
                  class="absolute top-2 right-2 w-8 h-8 bg-black/40 hover:bg-black/60 rounded-full flex items-center justify-center transition-all hover:scale-110 z-[10]"
                  @click.stop="toggleAlbumFavorite(album)"
                  :title="$t('common.favorite')"
                >
                  <i
                    class="bi text-xs"
                    :class="
                      album.albumIsFavorite
                        ? 'bi-heart-fill text-red-400'
                        : 'bi-heart text-white'
                    "
                  ></i>
                </button>
                <!-- Add to queue button (bottom left) -->
                <button
                  class="absolute bottom-8 left-6 w-8 h-8 bg-black/40 hover:bg-black/60 rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                  @click.stop="addToQueue(album)"
                  :title="$t('songs.add-to-queue')"
                >
                  <i class="bi bi-list text-xs text-white"></i>
                </button>
                <!-- Add to playlist button (bottom right) -->
                <button
                  class="absolute bottom-8 right-2 w-8 h-8 bg-black/40 hover:bg-black/60 rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                  @click.stop="showPlaylistAddToModal(album)"
                  :title="$t('songs.add_to_playlist')"
                >
                  <i class="bi bi-music-note-list text-xs text-white"></i>
                </button>
              </div>
            </div>
            <div class="p-2 pt-0">
              <p
                class="text-center font-semibold mb-1 text-audinary text-lg truncate"
              >
                {{ album.albumName }}
              </p>
              <p class="text-center text-white/80 text-xs truncate mb-1">
                {{ album.albumArtist }}
              </p>
              <p class="text-center text-white/80 text-xs truncate">
                {{ album.albumYear }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <button
        v-if="canScrollRight"
        class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-10 h-10 bg-black/70 hover:bg-black/90 border-none rounded-full text-white flex items-center justify-center transition-colors mobile-touch-target"
        @click="scrollRight"
        :aria-label="$t('common.scroll_right')"
      >
        <i class="bi bi-chevron-right"></i>
      </button>
    </div>
  </div>

  <!-- Add to Playlist Modal -->
  <PlaylistAddToModal
    :isVisible="showPlaylistModal"
    :selectedTracks="selectedAlbumTracks"
    :albumTitle="selectedAlbumForPlaylist?.albumName || ''"
    @close="closePlaylistModal"
    @added="handlePlaylistAdded"
  />
</template>

<script>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import { usePlayerStore } from "@/stores/player";
import { useApiStore } from "@/stores/api";
import { useAuthStore } from "@/stores/auth";
import { useAlertStore } from "@/stores/alert";
import SimpleImage from "@/components/common/SimpleImage.vue";
import { getCdCaseImage } from "@/utils/cdCases.js";
import PlaylistAddToModal from "@/components/modals/PlaylistAddToModal.vue";

export default {
  name: "RecentPlayedAlbumsSection",
  components: {
    SimpleImage,
    PlaylistAddToModal,
  },
  emits: ["show-album-detail"],
  setup(props, { emit }) {
    const { t } = useI18n();
    const router = useRouter();
    const playerStore = usePlayerStore();
    const apiStore = useApiStore();
    const authStore = useAuthStore();
    const alertStore = useAlertStore();

    const albums = ref([]);
    const loading = ref(true);
    const scrollContent = ref(null);
    const scrollPosition = ref(0);
    const showPlaylistModal = ref(false);
    const selectedAlbumForPlaylist = ref(null);
    const selectedAlbumTracks = ref([]);

    const canScrollLeft = computed(() => scrollPosition.value > 0);
    const canScrollRight = computed(() => {
      if (!scrollContent.value) return false;
      return (
        scrollPosition.value <
        scrollContent.value.scrollWidth - scrollContent.value.clientWidth
      );
    });

    const loadRecentPlayedAlbums = async () => {
      // Don't load data if not authenticated
      if (!authStore.isAuthenticated) {
        loading.value = false;
        return;
      }

      try {
        loading.value = true;
        // Use the new consolidated API method for recently played albums
        const response = await apiStore.loadRecentlyPlayedAlbums(20);

        // Use data directly as it comes from backend
        albums.value = Array.isArray(response) ? response : response.data || [];
      } catch (error) {
        console.error("Error loading recent played albums:", error);
        // Fallback: use recent albums for now
        try {
          const response = await apiStore.loadRecentAlbums(20);
          // Use data directly as it comes from backend
          albums.value = Array.isArray(response)
            ? response
            : response.data || [];
        } catch (fallbackError) {
          console.error("Error loading fallback albums:", fallbackError);
          albums.value = [];
        }
      } finally {
        loading.value = false;
      }
    };

    const playAlbum = (album) => {
      playerStore.playAlbum(album.album_id);
    };

    const showAlbumDetail = (album) => {
      emit("show-album-detail", album);
    };

    const toggleAlbumFavorite = async (album) => {
      try {
        const response = await apiStore.post(
          `/api/albums/${album.album_id}/favorite`,
          {
            is_favorite: !album.albumIsFavorite,
          },
        );

        if (response.success) {
          album.albumIsFavorite = !album.albumIsFavorite;
        }
      } catch (error) {
        console.error("Error toggling album favorite:", error);
      }
    };

    const showAll = () => {
      router.push("/?tab=albums");
    };

    const scrollLeft = () => {
      if (scrollContent.value) {
        scrollContent.value.scrollBy({ left: -200, behavior: "smooth" });
        updateScrollPosition();
      }
    };

    const scrollRight = () => {
      if (scrollContent.value) {
        scrollContent.value.scrollBy({ left: 200, behavior: "smooth" });
        updateScrollPosition();
      }
    };

    const updateScrollPosition = () => {
      if (scrollContent.value) {
        scrollPosition.value = scrollContent.value.scrollLeft;
      }
    };

    onMounted(async () => {
      // Wait for authentication to be confirmed before loading data
      if (authStore.isAuthenticated) {
        await loadRecentPlayedAlbums();
      } else {
        // Watch for authentication changes
        const unwatch = authStore.$subscribe(() => {
          if (authStore.isAuthenticated && albums.value.length === 0) {
            loadRecentPlayedAlbums();
            unwatch(); // Stop watching once data is loaded
          }
        });
      }

      if (scrollContent.value) {
        scrollContent.value.addEventListener("scroll", updateScrollPosition);
      }
    });

    return {
      albums,
      loading,
      scrollContent,
      canScrollLeft,
      canScrollRight,
      playAlbum,
      showAlbumDetail,
      toggleAlbumFavorite,
      showAll,
      scrollLeft,
      scrollRight,
      getCdCaseImage,
      loadRecentPlayedAlbums, // Expose for parent component refresh
      addToQueue: async (album) => {
        try {
          const albumResponse = await apiStore.loadAlbumSongs(album.album_id);
          const songs =
            albumResponse.tracks || albumResponse.data || albumResponse || [];

          if (songs && songs.length > 0) {
            playerStore.addMultipleToQueue(songs);
            alertStore.success(
              t("songs.addedToQueue", { count: songs.length }),
            );
          }
        } catch (error) {
          console.error("Error adding album to queue:", error);
          alertStore.error(t("songs.errorAddingToQueue"));
        }
      },
      showPlaylistAddToModal: async (album) => {
        try {
          selectedAlbumForPlaylist.value = album;
          // Load album tracks for playlist modal
          const albumResponse = await apiStore.loadAlbumSongs(album.album_id);
          selectedAlbumTracks.value =
            albumResponse.tracks || albumResponse.data || albumResponse || [];
          showPlaylistModal.value = true;
        } catch (error) {
          console.error("Error loading album tracks for playlist:", error);
          alertStore.error(t("albums.errorLoadingTracks"));
        }
      },
      closePlaylistModal: () => {
        showPlaylistModal.value = false;
        selectedAlbumForPlaylist.value = null;
        selectedAlbumTracks.value = [];
      },
      handlePlaylistAdded: (result) => {
        alertStore.success(
          t("songs.addedToPlaylist", { playlist: result.playlist.name }),
        );
      },
      showPlaylistModal,
      selectedAlbumForPlaylist,
      selectedAlbumTracks,
      t,
    };
  },
};
</script>

<style scoped>
/* Custom scrollbar hiding for dashboard sections */
.scrollbar-hide {
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

/* Mobile touch target utility */
.mobile-touch-target {
  min-height: 44px;
  min-width: 44px;
}
</style>
