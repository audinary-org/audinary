<template>
  <teleport to="body">
    <!-- Backdrop -->
    <div
      v-if="isVisible"
      class="backdrop fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 transition-all duration-300 ease-out"
      :class="{
        'backdrop-fade-in': isOpening && !isClosing,
        'backdrop-fade-out': isClosing,
      }"
      @click="handleBackdropClick"
      style="perspective: 1200px"
    >
      <!-- Playlist Modal with Animation -->
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
        <!-- Playlist Cover Background -->
        <div class="absolute inset-0 z-0">
          <SimpleImage
            v-if="currentPlaylistDetail?.id"
            imageType="playlist"
            :imageId="`playlist_${currentPlaylistDetail.id}`"
            :alt="currentPlaylistDetail?.name || 'Playlist'"
            class="w-full h-full object-cover relative z-[2]"
            :placeholder="'music-note-list'"
            :placeholderSize="'500px'"
          />
          <div
            v-else
            class="w-full h-full flex items-center justify-center"
            :class="themeStore.backgroundGradient"
          >
            <i class="bi bi-music-note-list text-white/20 text-[200px]"></i>
          </div>
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
              <!-- Playlist Metadata with Actions -->
              <div class="flex-1 bg-black/30 backdrop-blur-sm rounded-2xl p-4">
                <div class="flex flex-col sm:flex-row gap-4 items-start">
                  <div class="flex-1 text-center sm:text-left">
                    <h1 class="text-2xl md:text-3xl font-bold text-white mb-1">
                      <span v-if="currentPlaylistDetail?.type === 'smart'" class="inline-flex items-center gap-1.5 mr-2 px-2.5 py-1 bg-purple-600/60 rounded-lg text-sm font-medium align-middle">
                        <i class="bi bi-lightning-fill text-yellow-300"></i> Smart
                      </span>
                      {{ currentPlaylistDetail?.name || "Playlist" }}
                    </h1>
                    <p v-if="currentPlaylistDetail?.description" class="text-sm text-white/60 mb-2">
                      {{ currentPlaylistDetail.description }}
                    </p>
                    <p class="text-lg text-white/80 mb-2">
                      {{ playlistSongs.length || 0 }} {{ $t("common.songs") }}
                    </p>
                    <p class="text-sm text-white/60 mb-4">
                      {{ formatTotalDuration(playlistSongs) }}
                    </p>
                  </div>

                  <!-- Action Buttons -->
                  <div class="flex gap-3 flex-shrink-0">
                    <button
                      class="w-12 h-12 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 disabled:opacity-50 shadow-lg"
                      @click="
                        playPlaylist(
                          currentPlaylistDetail.id ||
                            currentPlaylistDetail.playlist_id,
                        )
                      "
                      :disabled="playlistSongs.length === 0"
                      :title="$t('playlist.playAll')"
                    >
                      <i class="bi bi-play-fill text-xl text-white"></i>
                    </button>
                    <button
                      class="w-12 h-12 bg-white/15 hover:bg-white/25 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 disabled:opacity-50 shadow-lg"
                      @click="
                        addPlaylistToQueue(
                          currentPlaylistDetail.id ||
                            currentPlaylistDetail.playlist_id,
                        )
                      "
                      :disabled="playlistSongs.length === 0"
                      :title="$t('player.addToQueue')"
                    >
                      <i class="bi bi-list text-lg text-white"></i>
                    </button>
                    <button
                      v-if="
                        canEditPlaylist(currentPlaylistDetail) && !isEditMode
                      "
                      class="w-12 h-12 bg-white/15 hover:bg-white/25 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 shadow-lg"
                      @click="toggleInlineEditMode"
                      :title="$t('common.edit')"
                    >
                      <i class="bi bi-pencil text-lg text-white"></i>
                    </button>
                    <button
                      v-if="
                        canEditPlaylist(currentPlaylistDetail) && !isEditMode
                      "
                      class="w-12 h-12 bg-red-600/20 hover:bg-red-600/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 shadow-lg"
                      @click="confirmDeleteCurrentPlaylist"
                      :title="$t('common.delete')"
                    >
                      <i class="bi bi-trash text-lg text-red-400"></i>
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

            <!-- Edit Mode Actions -->
            <div
              v-if="isEditMode"
              class="mt-4 flex gap-2 justify-center sm:justify-start"
            >
              <button
                class="px-4 py-2 bg-audinary hover:bg-audinary/90 text-black rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                @click="toggleInlineEditMode"
                :disabled="isLoading"
              >
                <span
                  v-if="isLoading"
                  class="inline-block w-4 h-4 border-2 border-black/30 border-t-black rounded-full animate-spin"
                ></span>
                <i v-else class="bi bi-check-lg"></i>
                {{ $t("common.save") }}
              </button>
              <button
                class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg transition-colors flex items-center gap-2"
                @click="cancelEdit"
              >
                <i class="bi bi-x-lg"></i> {{ $t("common.cancel") }}
              </button>
            </div>
          </div>

          <!-- Middle Section: Songs List -->
          <div class="flex-1 mx-6 mb-6 overflow-hidden">
            <!-- Edit Form (if in edit mode) -->
            <div
              v-if="isEditMode && currentPlaylistDetail"
              class="mb-6 bg-black/30 backdrop-blur-sm rounded-2xl p-4"
            >
              <!-- Playlist Name -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-white/80 mb-2">{{
                  $t("playlist.name")
                }}</label>
                <input
                  v-model="playlistName"
                  class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
                  :placeholder="$t('playlist.namePlaceholder')"
                />
              </div>

              <!-- Description -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-white/80 mb-2">{{
                  $t("playlist.description")
                }}</label>
                <textarea
                  v-model="playlistDescription"
                  class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent resize-none"
                  rows="2"
                  :placeholder="$t('playlist.descriptionPlaceholder')"
                ></textarea>
              </div>
            </div>

            <!-- Songs List Header -->
            <div class="flex justify-between items-center mb-4">
              <h6 class="text-lg font-medium mb-0 text-white">
                {{ $t("playlist.songs") }}
              </h6>
            </div>

            <!-- Loading State -->
            <div
              v-if="isLoading"
              class="h-full flex items-center justify-center"
            >
              <div
                class="animate-spin w-8 h-8 border-4 border-white/30 border-t-white rounded-full"
              >
                <span class="sr-only">{{ $t("common.loading") }}</span>
              </div>
            </div>

            <!-- Songs List -->
            <div
              v-else-if="playlistSongs.length > 0"
              class="h-full overflow-y-auto pr-2 custom-scrollbar"
            >
              <!-- Draggable Songs List (Edit Mode) -->
              <draggable
                v-if="isEditMode"
                :model-value="editablePlaylistSongs"
                @update:model-value="updateEditablePlaylistSongs"
                @end="onDragEnd"
                :animation="200"
                :touch-start-threshold="20"
                class="space-y-2"
                ghost-class="ghost-dragging"
                chosen-class="chosen-dragging"
                drag-class="drag-dragging"
                item-key="id"
              >
                <template #item="{ element: song, index }">
                  <div
                    :key="`edit-${song.id || song.song_id}-${index}`"
                    class="flex items-center py-3 px-4 rounded-xl backdrop-blur-sm bg-white/5 hover:bg-white/15 transition-all duration-300 shadow-sm"
                  >
                    <!-- Drag Handle -->
                    <div
                      class="cursor-grab active:cursor-grabbing mr-4 text-white/40 hover:text-white/70 transition-colors mobile-touch-target"
                    >
                      <i class="bi bi-grip-vertical"></i>
                    </div>

                    <div
                      class="w-8 text-center text-white/70 mr-4 text-sm font-mono font-bold"
                    >
                      <SoundWaveAnimation
                        :song="song"
                        :track-number="index + 1"
                      />
                    </div>
                    <div class="relative w-10 h-10 mr-4">
                      <!-- Gradient placeholder background -->
                      <div
                        v-if="song.coverGradient && song.coverGradient.colors"
                        class="absolute inset-0 rounded"
                        :style="{
                          background: `linear-gradient(${song.coverGradient.angle || 135}deg, ${song.coverGradient.colors.join(', ')})`,
                          filter: 'blur(10px)',
                          zIndex: 1,
                        }"
                      ></div>
                      <SimpleImage
                        :imageType="'album_thumbnail'"
                        :imageId="song.album_id || 'default'"
                        :alt="song.album_name || song.album"
                        class="w-10 h-10 object-cover rounded relative z-[2]"
                        :placeholder="'disc'"
                        :placeholderSize="'20px'"
                      />
                    </div>
                    <div class="flex-1 min-w-0 mr-4">
                      <div class="font-semibold text-white truncate text-lg">
                        {{ song.title }}
                      </div>
                      <div class="text-white/60 text-sm truncate">
                        {{ song.artist_name || song.artist }} -
                        {{ song.album_name || song.album }}
                      </div>
                    </div>
                    <div class="text-white/60 mr-4 text-sm">
                      {{ formatDuration(song.duration) }}
                    </div>

                    <!-- Edit Mode Actions -->
                    <div class="flex gap-2">
                      <!-- Remove Button -->
                      <button
                        class="w-8 h-8 bg-red-600/20 hover:bg-red-600/40 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                        @click="removeFromEditableList(index)"
                        :title="'Remove'"
                      >
                        <i class="bi bi-x text-xs text-red-400"></i>
                      </button>
                    </div>
                  </div>
                </template>
              </draggable>

              <!-- Normal Songs List (View Mode) -->
              <div v-else class="space-y-2">
                <div
                  v-for="(song, index) in playlistSongs"
                  :key="`view-${song.id || song.song_id}-${index}`"
                  class="flex items-center py-3 px-4 rounded-xl backdrop-blur-sm bg-white/5 hover:bg-white/15 transition-all cursor-pointer group shadow-sm"
                  @click="playSong(song)"
                >
                  <div
                    class="w-8 text-center text-sm text-white/70 mr-4 font-mono font-bold"
                  >
                    <SoundWaveAnimation
                      :song="song"
                      :track-number="index + 1"
                    />
                  </div>
                  <div class="relative w-10 h-10 mr-4">
                    <!-- Gradient placeholder background -->
                    <div
                      v-if="song.coverGradient && song.coverGradient.colors"
                      class="absolute inset-0 rounded"
                      :style="{
                        background: `linear-gradient(${song.coverGradient.angle || 135}deg, ${song.coverGradient.colors.join(', ')})`,
                        filter: 'blur(10px)',
                        zIndex: 1,
                      }"
                    ></div>
                    <SimpleImage
                      :imageType="'album_thumbnail'"
                      :imageId="song.album_id || 'default'"
                      :alt="song.album_name || song.album"
                      class="w-10 h-10 object-cover rounded relative z-[2]"
                      :placeholder="'disc'"
                      :placeholderSize="'20px'"
                    />
                  </div>
                  <div class="flex-1 min-w-0 mr-4">
                    <div class="font-semibold text-white truncate text-lg">
                      {{ song.title }}
                    </div>
                    <div class="text-white/60 text-sm truncate">
                      {{ song.artist_name || song.artist }} -
                      {{ song.album_name || song.album }}
                    </div>
                  </div>
                  <div class="text-white/60 mr-4 text-sm">
                    {{ formatDuration(song.duration) }}
                  </div>

                  <!-- Quick Actions -->
                  <div
                    class="flex gap-2 opacity-0 group-hover:opacity-100 transition-all md:opacity-100"
                  >
                    <button
                      class="w-8 h-8 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                      @click.stop="playSong(song)"
                      :title="$t('player.play')"
                    >
                      <i class="bi bi-play-fill text-xs text-white"></i>
                    </button>
                    <button
                      class="w-8 h-8 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                      @click.stop="addSongToQueue(song)"
                      :title="$t('player.addToQueue')"
                    >
                      <i class="bi bi-list text-lg text-xs text-white"></i>
                    </button>
                    <button
                      class="w-8 h-8 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                      @click.stop="showPlaylistAddToModal(song)"
                      :title="$t('songs.add_to_playlist')"
                    >
                      <i class="bi bi-music-note-list text-xs text-white"></i>
                    </button>
                    <button
                      class="w-8 h-8 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                      @click.stop="toggleSongFavorite(song)"
                      :title="$t('songs.favorite')"
                    >
                      <i
                        class="bi text-xs"
                        :class="
                          song.is_favorite
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
                <p>{{ $t("playlist.noSongs") }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script>
import { ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";
import { usePlayerStore } from "@/stores/player";
import { useThemeStore } from "@/stores/theme";
import { useAlertStore } from "@/stores/alert";
import draggable from "vuedraggable";
import SoundWaveAnimation from "@/components/common/SoundWaveAnimation.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";

export default {
  name: "PlaylistDetailModal",
  components: {
    draggable,
    SoundWaveAnimation,
    SimpleImage,
  },
  emits: [
    "close",
    "play-playlist",
    "add-playlist-to-queue",
    "play-song",
    "add-song-to-queue",
    "show-add-to-playlist-modal",
    "toggle-song-favorite",
    "toggle-inline-edit-mode",
    "confirm-delete-current-playlist",
    "change-playlist-cover",
    "cancel-edit",
    "on-drag-end",
    "remove-from-editable-list",
    "update-editable-playlist-songs",
    "save-changes",
    "remove-song",
  ],
  props: {
    isVisible: {
      type: Boolean,
      default: false,
    },
    currentPlaylistDetail: {
      type: Object,
      default: null,
    },
    playlistSongs: {
      type: Array,
      default: () => [],
    },
    isEditMode: {
      type: Boolean,
      default: false,
    },
    editablePlaylistSongs: {
      type: Array,
      default: () => [],
    },
    editablePlaylist: {
      type: Object,
      default: () => ({}),
    },
    isLoading: {
      type: Boolean,
      default: false,
    },
    canEditPlaylist: {
      type: Function,
      required: true,
    },
    formatDuration: {
      type: Function,
      required: true,
    },
    formatTotalDuration: {
      type: Function,
      required: true,
    },
  },
  setup(props, { emit }) {
    const { t } = useI18n();
    const playerStore = usePlayerStore();
    const themeStore = useThemeStore();
    const alertStore = useAlertStore();

    // Animation state
    const isOpening = ref(false);
    const isClosing = ref(false);

    // Computed properties for editable playlist fields to avoid prop mutation
    const playlistName = computed({
      get: () => props.editablePlaylist.name,
      set: (value) => {
        emit("update:editablePlaylist", {
          ...props.editablePlaylist,
          name: value,
        });
      },
    });

    const playlistDescription = computed({
      get: () => props.editablePlaylist.description,
      set: (value) => {
        emit("update:editablePlaylist", {
          ...props.editablePlaylist,
          description: value,
        });
      },
    });

    const closeModal = () => {
      isClosing.value = true;
      setTimeout(() => {
        emit("close");
        // Reset animation states after closing
        isClosing.value = false;
        isOpening.value = false;
      }, 500); // Match the animation duration
    };

    const handleBackdropClick = () => {
      if (props.isEditMode) {
        return;
      }
      closeModal();
    };

    // Watch for visibility changes to trigger animations
    watch(
      () => props.isVisible,
      (newValue) => {
        if (newValue) {
          // Reset animation states when modal opens
          isClosing.value = false;
          isOpening.value = false;

          // Trigger opening animation
          setTimeout(() => {
            isOpening.value = true;
          }, 50);
        }
      },
      { immediate: true },
    );

    const playPlaylist = (playlistId) => {
      emit("play-playlist", playlistId);
    };

    const addPlaylistToQueue = (playlistId) => {
      emit("add-playlist-to-queue", playlistId);
    };

    const playSong = (song) => {
      playerStore.playSong(song);

      // Add remaining songs from playlist to queue
      const songId = song.id || song.song_id;
      const selectedIndex = props.playlistSongs.findIndex(
        (s) => (s.id || s.song_id) === songId,
      );

      if (selectedIndex !== -1 && selectedIndex < props.playlistSongs.length - 1) {
        const remainingTracks = props.playlistSongs.slice(selectedIndex + 1);
        remainingTracks.forEach((track) => {
          playerStore.addToQueue(track);
        });

        if (remainingTracks.length > 0) {
          alertStore.info(
            t("playlist.addedToQueue", { name: `${remainingTracks.length} Songs` }),
          );
        }
      }
    };

    const addSongToQueue = (song) => {
      emit("add-song-to-queue", song);
    };

    const showPlaylistAddToModal = (song) => {
      emit("show-add-to-playlist-modal", song);
    };

    const toggleSongFavorite = (song) => {
      emit("toggle-song-favorite", song);
    };

    const toggleInlineEditMode = () => {
      emit("toggle-inline-edit-mode");
    };

    const confirmDeleteCurrentPlaylist = () => {
      emit("confirm-delete-current-playlist");
    };

    const changePlaylistCover = () => {
      emit("change-playlist-cover");
    };

    const cancelEdit = () => {
      emit("cancel-edit");
    };

    const onDragEnd = (event) => {
      emit("on-drag-end", event);
    };

    const removeFromEditableList = (index) => {
      emit("remove-from-editable-list", index);
    };

    const updateEditablePlaylistSongs = (newSongs) => {
      emit("update-editable-playlist-songs", newSongs);
    };

    return {
      t,
      closeModal,
      handleBackdropClick,
      playPlaylist,
      addPlaylistToQueue,
      playSong,
      addSongToQueue,
      showPlaylistAddToModal,
      toggleSongFavorite,
      toggleInlineEditMode,
      confirmDeleteCurrentPlaylist,
      changePlaylistCover,
      cancelEdit,
      onDragEnd,
      removeFromEditableList,
      updateEditablePlaylistSongs,
      playerStore,
      themeStore,
      isOpening,
      isClosing,
      playlistName,
      playlistDescription,
    };
  },
};
</script>

<style scoped>
@reference "tailwindcss";

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

/* Dragging states */
.ghost-dragging {
  @apply opacity-50 bg-white/30 border-2 border-dashed border-white/50 transform rotate-1;
}

.chosen-dragging {
  @apply bg-white/20 border-white/40 transform scale-105 shadow-lg;
}

.drag-dragging {
  @apply bg-white/10 border-white/30 opacity-90;
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

/* Mobile device optimizations */
@media (max-width: 768px) {
  .group-hover\:opacity-100 {
    opacity: 1;
  }
}

/* Touch device optimizations */
@media (hover: none) and (pointer: coarse) {
  .group-hover\:opacity-100 {
    opacity: 1;
  }
}
</style>
