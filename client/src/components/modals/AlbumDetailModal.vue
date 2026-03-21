<template>
  <teleport to="body">
    <!-- Backdrop -->
    <div
      v-if="album"
      class="backdrop fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 transition-all duration-300 ease-out"
      :class="{
        'backdrop-fade-in': isOpening && !isClosing,
        'backdrop-fade-out': isClosing,
      }"
      @click="handleBackdropClick"
      style="perspective: 1200px"
    >
      <!-- CD Case Modal with Animation -->
      <div
        class="cd-modal relative rounded-2xl shadow-2xl overflow-hidden transform transition-all duration-500 ease-out"
        :class="{
          'w-full max-w-5xl': true,
          'h-[75vh]': playerStore.currentSong,
          'h-[80vh]': !playerStore.currentSong,
          'animate-flip-in': isOpening && !isClosing,
          'animate-flip-out': isClosing,
        }"
        @click.stop
      >
        <!-- Album Cover Background -->
        <div class="absolute inset-0 z-0">
          <!-- Gradient placeholder background -->
          <div
            v-if="album?.coverGradient && album.coverGradient.colors"
            class="absolute inset-0"
            :style="{
              background: `linear-gradient(${album.coverGradient.angle || 135}deg, ${album.coverGradient.colors.join(', ')})`,
              filter: 'blur(10px)',
              zIndex: 1,
            }"
          ></div>
          <SimpleImage
            :imageType="'album'"
            :imageId="album?.album_id"
            :alt="albumTitle"
            class="w-full h-full object-cover relative z-[2]"
            :placeholder="'disc'"
            :placeholderSize="'500px'"
          />
          <!-- Dark Overlay for text readability -->
          <div
            class="absolute inset-0 bg-black/50 backdrop-blur-[2px] z-[3]"
          ></div>
        </div>

        <!-- Content Layer -->
        <div class="relative z-10 h-full flex flex-col">
          <!-- Top Section: Fixed Header -->
          <div class="flex-shrink-0 p-6">
            <div class="flex gap-4 items-start">
              <!-- Album Metadata with Actions -->
              <div class="flex-1 bg-black/30 backdrop-blur-sm rounded-2xl p-4">
                <div class="flex flex-col sm:flex-row gap-4 items-start">
                  <div class="flex-1 text-center sm:text-left">
                    <h1 class="text-2xl md:text-3xl font-bold text-white mb-1">
                      {{ albumTitle }}
                    </h1>
                    <p class="text-lg text-white/80 mb-2">{{ albumArtist }}</p>
                    <div
                      class="flex flex-wrap justify-center sm:justify-start gap-4 text-sm text-white/70"
                    >
                      <span>{{ albumYear }}</span>
                      <span>•</span>
                      <span>{{ trackCount }} {{ $t("common.songs") }}</span>
                      <span>•</span>
                      <span>{{ albumGenre }}</span>
                      <span>•</span>
                      <span>{{ albumDuration }}</span>
                    </div>
                  </div>

                  <!-- Album Actions -->
                  <div class="flex gap-3 flex-shrink-0">
                    <button
                      class="w-12 h-12 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 disabled:opacity-50 shadow-lg"
                      @click="playAlbum"
                      :disabled="loading"
                      :title="$t('album.play')"
                    >
                      <i class="bi bi-play-fill text-xl text-white"></i>
                    </button>
                    <button
                      class="w-12 h-12 bg-white/15 hover:bg-white/25 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 disabled:opacity-50 shadow-lg"
                      @click="addToQueue"
                      :disabled="loading"
                      :title="$t('player.addToQueue')"
                    >
                      <i class="bi bi-list text-lg text-lg text-white"></i>
                    </button>
                    <button
                      class="w-12 h-12 bg-white/15 hover:bg-white/25 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 disabled:opacity-50 shadow-lg"
                      @click="showAddAlbumToPlaylistModal"
                      :disabled="loading || tracks.length === 0"
                      :title="$t('album.addToPlaylist')"
                    >
                      <i class="bi bi-music-note-list text-lg text-white"></i>
                    </button>
                    <button
                      class="w-12 h-12 bg-white/15 hover:bg-white/25 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 disabled:opacity-50 shadow-lg"
                      @click="toggleFavorite"
                      :disabled="loading"
                      :title="$t('common.favorite')"
                    >
                      <i
                        class="bi text-lg"
                        :class="
                          album?.albumIsFavorite
                            ? 'bi-heart-fill text-red-400'
                            : 'bi-heart text-white'
                        "
                      ></i>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Close Button -->
              <button
                class="flex-shrink-0 w-10 h-10 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 shadow-lg"
                @click="closeModal"
                aria-label="Close"
              >
                <i class="bi bi-x-lg text-white"></i>
              </button>
            </div>
          </div>

          <!-- Middle Section: Track List -->
          <div class="flex-1 mx-6 mb-6 overflow-hidden">
            <!-- Loading State -->
            <div v-if="loading" class="h-full flex items-center justify-center">
              <div
                class="animate-spin w-8 h-8 border-4 border-white/30 border-t-white rounded-full"
              >
                <span class="sr-only">{{ $t("common.loading") }}</span>
              </div>
            </div>

            <!-- Tracks List -->
            <div
              v-else-if="tracks.length > 0"
              class="h-full overflow-y-auto pr-2 custom-scrollbar"
            >
              <!-- Multi-Disc Album -->
              <template v-if="isMultiDisc">
                <div
                  v-for="(disc, discKey) in groupedTracks"
                  :key="discKey"
                  class="mb-6 last:mb-0"
                >
                  <h6
                    class="text-blue-400 mb-3 pb-2 border-b border-blue-400/30 flex items-center"
                  >
                    <i class="bi bi-disc mr-2"></i>
                    CD {{ disc.discNumber }}
                    <small class="text-gray-400 ml-2"
                      >({{ disc.tracks.length }}
                      {{ $t("common.songs") }})</small
                    >
                  </h6>
                  <div class="space-y-1">
                    <div
                      v-for="track in disc.tracks"
                      :key="track.song_id"
                      class="flex items-center py-3 px-4 rounded-xl bg-white/[0.07] hover:bg-white/15 transition-colors cursor-pointer group mb-2"
                      @click="playTrack(track)"
                    >
                      <div
                        class="w-8 text-center text-sm text-white/70 mr-4 font-mono font-bold"
                      >
                        <SoundWaveAnimation
                          :song="track"
                          :track-number="track.track_number || '--'"
                        />
                      </div>
                      <div class="flex-1 min-w-0 mr-4">
                        <div class="font-semibold text-white truncate text-lg">
                          {{ track.title }}
                        </div>
                        <div class="text-white/60 text-sm">
                          {{ formatDuration(track.duration) }}
                        </div>
                      </div>
                      <div
                        class="flex gap-2 opacity-0 group-hover:opacity-100 transition-all md:opacity-100"
                      >
                        <button
                          class="w-8 h-8 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                          @click.stop="addTrackToQueue(track)"
                          :title="$t('player.addToQueue')"
                        >
                          <i class="bi bi-list text-lg text-xs text-white"></i>
                        </button>
                        <button
                          class="w-8 h-8 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                          @click.stop="showPlaylistAddToModal(track)"
                          :title="$t('songs.add_to_playlist')"
                        >
                          <i
                            class="bi bi-music-note-list text-xs text-white"
                          ></i>
                        </button>
                        <button
                          class="w-8 h-8 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                          @click.stop="toggleTrackFavorite(track)"
                          :title="$t('common.favorite')"
                        >
                          <i
                            class="bi text-xs"
                            :class="
                              track.is_favorite
                                ? 'bi-heart-fill text-red-400'
                                : 'bi-heart text-white'
                            "
                          ></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </template>

              <!-- Single Disc Album -->
              <div v-else class="space-y-2">
                <div
                  v-for="track in tracks"
                  :key="track.song_id"
                  class="flex items-center py-3 px-4 rounded-xl bg-white/[0.07] hover:bg-white/15 transition-colors cursor-pointer group"
                  @click="playTrack(track)"
                >
                  <div
                    class="w-8 text-center text-sm text-white/70 mr-4 font-mono font-bold"
                  >
                    <SoundWaveAnimation
                      :song="track"
                      :track-number="track.track_number || '--'"
                    />
                  </div>
                  <div class="flex-1 min-w-0 mr-4">
                    <div class="font-semibold text-white truncate text-lg">
                      {{ track.title }}
                    </div>
                    <div class="text-white/60 text-sm">
                      {{ formatDuration(track.duration) }}
                    </div>
                  </div>
                  <div
                    class="flex gap-2 opacity-0 group-hover:opacity-100 transition-all md:opacity-100"
                  >
                    <button
                      class="w-8 h-8 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                      @click.stop="addTrackToQueue(track)"
                      :title="$t('player.addToQueue')"
                    >
                      <i class="bi bi-list text-lg text-xs text-white"></i>
                    </button>
                    <button
                      class="w-8 h-8 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                      @click.stop="showPlaylistAddToModal(track)"
                      :title="$t('songs.add_to_playlist')"
                    >
                      <i class="bi bi-music-note-list text-xs text-white"></i>
                    </button>
                    <button
                      class="w-8 h-8 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                      @click.stop="toggleTrackFavorite(track)"
                      :title="$t('common.favorite')"
                    >
                      <i
                        class="bi text-xs"
                        :class="
                          track.is_favorite
                            ? 'bi-heart-fill text-red-400'
                            : 'bi-heart text-white'
                        "
                      ></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div v-else class="h-full flex items-center justify-center">
              <div class="text-center text-white/60">
                <i class="bi bi-music-note-list text-5xl mb-4"></i>
                <p>{{ $t("album.noTracks") }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add to Playlist Modal -->
    <PlaylistAddToModal
      :is-visible="showPlaylistModal"
      :selected-item="selectedTrack"
      :selected-tracks="showAlbumPlaylistModal ? tracks : []"
      :album-title="showAlbumPlaylistModal ? albumTitle : ''"
      @close="closePlaylistModal"
      @added="onTrackAddedToPlaylist"
      @create-playlist="createNewPlaylist"
    />
  </teleport>
</template>

<script>
import { ref, shallowRef, computed, watch, onMounted, onUnmounted } from "vue";
import { useI18n } from "vue-i18n";
import { usePlayerStore } from "@/stores/player";
import { useApiStore } from "@/stores/api";
import { useAlertStore } from "@/stores/alert";
import { useThemeStore } from "@/stores/theme";
import PlaylistAddToModal from "@/components/modals/PlaylistAddToModal.vue";
import SoundWaveAnimation from "@/components/common/SoundWaveAnimation.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";
import { getCdCaseImage } from "@/utils/cdCases.js";

export default {
  name: "AlbumDetailModal",
  components: {
    PlaylistAddToModal,
    SoundWaveAnimation,
    SimpleImage,
  },
  props: {
    album: {
      type: Object,
      default: null,
    },
  },
  emits: ["close", "album-updated"],
  setup(props, { emit }) {
    const { t } = useI18n();
    const playerStore = usePlayerStore();
    const apiStore = useApiStore();
    const alertStore = useAlertStore();
    const themeStore = useThemeStore();

    const tracks = shallowRef([]);
    const loading = ref(false);

    // Playlist modal state
    const showPlaylistModal = ref(false);
    const selectedTrack = ref(null);
    const showAlbumPlaylistModal = ref(false);

    // Mobile dropdown state
    const activeDropdown = ref(null);

    // Animation state
    const isOpening = ref(false);
    const isClosing = ref(false);

    // Computed properties for cleaner template
    const albumTitle = computed(
      () => props.album?.albumName || props.album?.album_name || "Album Title",
    );
    const albumArtist = computed(() => props.album?.albumArtist || "--");
    const albumYear = computed(
      () => props.album?.albumYear || props.album?.year || "--",
    );
    const albumGenre = computed(
      () =>
        props.album?.albumGenre ||
        props.album?.album_genre ||
        props.album?.genre ||
        "--",
    );
    const albumDuration = computed(() =>
      formatDuration(
        props.album?.albumDuration ||
          props.album?.album_duration ||
          props.album?.total_duration,
      ),
    );
    const trackCount = computed(
      () => tracks.value.length || props.album?.track_count || 0,
    );

    // Group tracks by CD/Disc number
    const groupedTracks = computed(() => {
      if (!tracks.value || tracks.value.length === 0) return {};

      const grouped = {};
      tracks.value.forEach((track) => {
        const discNumber =
          track.disc_number || track.cd_number || track.disc || track.cd || 1;
        const discKey = `disc_${discNumber}`;

        if (!grouped[discKey]) {
          grouped[discKey] = {
            discNumber: discNumber,
            tracks: [],
          };
        }
        grouped[discKey].tracks.push(track);
      });

      // Sort tracks within each disc by track number
      Object.keys(grouped).forEach((discKey) => {
        grouped[discKey].tracks.sort((a, b) => {
          const trackA = parseInt(a.track_number) || 0;
          const trackB = parseInt(b.track_number) || 0;
          return trackA - trackB;
        });
      });

      return grouped;
    });

    const isMultiDisc = computed(
      () => Object.keys(groupedTracks.value).length > 1,
    );

    const formatDuration = (seconds) => {
      if (!seconds) return "--:--";
      const mins = Math.floor(seconds / 60);
      const secs = seconds % 60;
      return `${mins}:${secs.toString().padStart(2, "0")}`;
    };

    const loadAlbumTracks = async () => {
      if (!props.album?.album_id) return;

      loading.value = true;
      try {
        const response = await apiStore.loadAlbumSongs(props.album.album_id);
        tracks.value = response.tracks || response.data || response || [];
      } catch (error) {
        console.error("Error loading album tracks:", error);
        tracks.value = [];
      } finally {
        loading.value = false;
      }
    };

    const playAlbum = () => {
      if (props.album?.album_id) {
        playerStore.playAlbum(props.album.album_id);
      }
    };

    const playTrack = (track) => {
      // Start playing the selected track
      playerStore.playSong(track);

      // Add remaining tracks from album to queue
      addRemainingTracksToQueue(track);
    };

    const addRemainingTracksToQueue = (selectedTrack) => {
      // Get all tracks (flattened for multi-disc albums)
      let allTracks = [];

      if (isMultiDisc.value) {
        // For multi-disc albums, maintain disc order
        Object.keys(groupedTracks.value)
          .sort((a, b) => {
            const discA = groupedTracks.value[a].discNumber;
            const discB = groupedTracks.value[b].discNumber;
            return discA - discB;
          })
          .forEach((discKey) => {
            allTracks = allTracks.concat(groupedTracks.value[discKey].tracks);
          });
      } else {
        allTracks = [...tracks.value].sort((a, b) => {
          const trackA = parseInt(a.track_number) || 0;
          const trackB = parseInt(b.track_number) || 0;
          return trackA - trackB;
        });
      }

      // Find the index of the selected track
      const selectedIndex = allTracks.findIndex(
        (track) => track.song_id === selectedTrack.song_id,
      );

      if (selectedIndex !== -1 && selectedIndex < allTracks.length - 1) {
        // Get remaining tracks (after the selected one)
        const remainingTracks = allTracks.slice(selectedIndex + 1);

        // Add remaining tracks to queue
        playerStore.addMultipleToQueue(remainingTracks);

        if (remainingTracks.length > 0) {
          alertStore.info(
            `${remainingTracks.length} Songs zur Warteschlange hinzugefügt`,
          );
        }
      }
    };

    const addToQueue = () => {
      if (props.album?.album_id) {
        playerStore.addAlbumToQueue(props.album.album_id);
      }
    };

    const addTrackToQueue = (track) => {
      playerStore.addToQueue(track);
    };

    const toggleFavorite = async () => {
      if (!props.album?.album_id) return;

      try {
        await apiStore.toggleFavorite({
          type: "album",
          itemId: props.album.album_id,
          currentlyFav: props.album.albumIsFavorite,
        });
        const updatedAlbum = {
          ...props.album,
          albumIsFavorite: !props.album.albumIsFavorite,
        };
        emit("album-updated", updatedAlbum);
      } catch (error) {
        console.error("Error toggling album favorite:", error);
      }
    };

    const toggleTrackFavorite = async (track) => {
      try {
        await apiStore.toggleFavorite({
          type: "song",
          itemId: track.song_id,
          currentlyFav: track.is_favorite,
        });
        // Replace array to trigger shallowRef reactivity
        tracks.value = tracks.value.map((t) =>
          t.song_id === track.song_id
            ? { ...t, is_favorite: !t.is_favorite }
            : t,
        );
      } catch (error) {
        console.error("Error toggling track favorite:", error);
        alertStore.error("Fehler beim Ändern der Favoriten");
      }
    };

    // Playlist Functions
    const showPlaylistAddToModal = (track) => {
      selectedTrack.value = track;
      showAlbumPlaylistModal.value = false;
      showPlaylistModal.value = true;
    };

    const showAddAlbumToPlaylistModal = () => {
      selectedTrack.value = null;
      showAlbumPlaylistModal.value = true;
      showPlaylistModal.value = true;
    };

    const closePlaylistModal = () => {
      showPlaylistModal.value = false;
      showAlbumPlaylistModal.value = false;
      selectedTrack.value = null;
    };

    const onTrackAddedToPlaylist = (data) => {
      // Event handler for successful addition
      if (data.tracks) {
        // Album was added
        console.log("Album tracks added to playlist:", data);
        const successCount = data.result?.successCount || 0;
        if (successCount > 0) {
          alertStore.success(`${successCount} Songs zum Playlist hinzugefügt`);
        }
      } else {
        // Single track was added
        console.log("Track added to playlist:", data);
      }
    };

    const createNewPlaylist = () => {
      closePlaylistModal();
      alertStore.info(
        "Bitte verwenden Sie die Navigation um eine neue Playlist zu erstellen",
      );
    };

    // Mobile dropdown functions
    const toggleTrackDropdown = (trackId) => {
      activeDropdown.value = activeDropdown.value === trackId ? null : trackId;
    };

    const closeDropdown = () => {
      activeDropdown.value = null;
    };

    const handleBackdropClick = () => {
      closeDropdown();
      closeModal();
    };

    const show = () => {
      // Modal is always shown via v-if in parent, no need for instance
    };

    const hide = () => {
      closeModal();
    };

    const closeModal = () => {
      // Clear data and emit close immediately to free memory
      tracks.value = [];
      selectedTrack.value = null;
      showPlaylistModal.value = false;
      showAlbumPlaylistModal.value = false;
      activeDropdown.value = null;
      emit("close");

      isClosing.value = true;
      setTimeout(() => {
        // Reset animation states after closing
        isClosing.value = false;
        isOpening.value = false;
      }, 500); // Match the animation duration
    };

    // Watch for album changes
    watch(
      () => props.album,
      (newAlbum) => {
        if (newAlbum) {
          // Clear previous album data immediately to free memory
          tracks.value = [];
          selectedTrack.value = null;
          showPlaylistModal.value = false;
          showAlbumPlaylistModal.value = false;

          // Reset animation states when a new album is opened
          isClosing.value = false;
          isOpening.value = false;
          loadAlbumTracks();

          // Trigger opening animation
          setTimeout(() => {
            isOpening.value = true;
          }, 50);
        }
      },
      { immediate: true },
    );

    // Close dropdown when clicking outside
    const handleDocumentClick = (event) => {
      if (activeDropdown.value && !event.target.closest(".relative")) {
        closeDropdown();
      }
    };

    onMounted(() => {
      // Add document click listener for dropdown
      document.addEventListener("click", handleDocumentClick);
    });

    // Clean up event listener
    onUnmounted(() => {
      document.removeEventListener("click", handleDocumentClick);
    });

    return {
      tracks,
      loading,
      showPlaylistModal,
      selectedTrack,
      showAlbumPlaylistModal,
      groupedTracks,
      isMultiDisc,
      albumTitle,
      albumArtist,
      albumYear,
      albumGenre,
      albumDuration,
      trackCount,
      formatDuration,
      playAlbum,
      playTrack,
      addToQueue,
      addTrackToQueue,
      toggleFavorite,
      toggleTrackFavorite,
      showPlaylistAddToModal,
      showAddAlbumToPlaylistModal,
      closePlaylistModal,
      onTrackAddedToPlaylist,
      createNewPlaylist,
      toggleTrackDropdown,
      closeDropdown,
      activeDropdown,
      isOpening,
      isClosing,
      handleBackdropClick,
      show,
      hide,
      closeModal,
      addRemainingTracksToQueue,
      playerStore,
      themeStore,
      t,
      getCdCaseImage,
    };
  },
};
</script>

<style scoped>
/* CD Modal Animation */
.cd-modal {
  transform: scale(0.6) rotateY(-60deg) rotateX(20deg);
  opacity: 0;
  transform-style: preserve-3d;
  transform-origin: center center;
}

.cd-modal.animate-flip-in {
  transform: scale(1) rotateY(0deg) rotateX(0deg);
  opacity: 1;
}

.cd-modal.animate-flip-out {
  transform: scale(0.6) rotateY(60deg) rotateX(-20deg);
  opacity: 0;
}

/* Backdrop Animation */
.backdrop {
  opacity: 0;
}

.backdrop.backdrop-fade-in {
  opacity: 1;
}

.backdrop.backdrop-fade-out {
  opacity: 0;
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Mobile touch target utility */
.mobile-touch-target {
  min-height: 44px;
  min-width: 44px;
}

/* Additional hover effects */
.group:hover .shadow-sm {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}
</style>
