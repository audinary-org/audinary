<template>
  <div class="h-full flex flex-col">
    <!-- Playlist Cover Background -->
    <div class="relative flex-shrink-0">
      <div class="relative h-48 md:h-64 overflow-hidden rounded-2xl mx-2 mt-2">
        <SimpleImage
          v-if="currentPlaylist?.id"
          imageType="playlist"
          :imageId="`playlist_${currentPlaylist.id}`"
          :alt="currentPlaylist?.name || 'Playlist'"
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
        <!-- Dark Overlay -->
        <div
          class="absolute inset-0 bg-black/50 backdrop-blur-[2px] z-[3]"
        ></div>

        <!-- Content over the cover -->
        <div class="absolute inset-0 z-10 flex flex-col justify-end p-6">
          <div class="flex gap-4 items-end">
            <!-- Playlist Metadata -->
            <div class="flex-1">
              <h1 class="text-2xl md:text-3xl font-bold text-white mb-1">
                <span
                  v-if="currentPlaylist?.type === 'smart'"
                  class="inline-flex items-center gap-1.5 mr-2 px-2.5 py-1 bg-purple-600/60 rounded-lg text-sm font-medium align-middle"
                >
                  <i class="bi bi-lightning-fill text-yellow-300"></i> Smart
                </span>
                {{ currentPlaylist?.name || "Playlist" }}
              </h1>
              <p
                v-if="currentPlaylist?.description"
                class="text-sm text-white/60 mb-2"
              >
                {{ currentPlaylist.description }}
              </p>
              <p class="text-lg text-white/80 mb-2">
                {{ songs.length || 0 }} {{ $t("common.songs") }}
              </p>
              <p class="text-sm text-white/60">
                {{ formatTotalDuration(songs) }}
              </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3 flex-shrink-0">
              <button
                class="w-12 h-12 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 disabled:opacity-50 shadow-lg"
                @click="playPlaylist"
                :disabled="songs.length === 0"
                :title="$t('playlist.playAll')"
              >
                <i class="bi bi-play-fill text-xl text-white"></i>
              </button>
              <button
                class="w-12 h-12 bg-white/15 hover:bg-white/25 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 disabled:opacity-50 shadow-lg"
                @click="addPlaylistToQueue"
                :disabled="songs.length === 0"
                :title="$t('player.addToQueue')"
              >
                <i class="bi bi-list text-lg text-white"></i>
              </button>
              <button
                v-if="canEdit && !isEditMode"
                class="w-12 h-12 bg-white/15 hover:bg-white/25 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 shadow-lg"
                @click="toggleEditMode"
                :title="$t('common.edit')"
              >
                <i class="bi bi-pencil text-lg text-white"></i>
              </button>
              <button
                v-if="canEdit && !isEditMode"
                class="w-12 h-12 bg-red-600/20 hover:bg-red-600/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 shadow-lg"
                @click="confirmDelete"
                :title="$t('common.delete')"
              >
                <i class="bi bi-trash text-lg text-red-400"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Back Button -->
        <button
          class="absolute top-4 left-4 z-10 w-10 h-10 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 shadow-lg"
          @click="goBack"
          :aria-label="$t('common.back')"
        >
          <i class="bi bi-arrow-left text-white"></i>
        </button>
      </div>
    </div>

    <!-- Edit Mode Actions -->
    <div
      v-if="isEditMode"
      class="flex-shrink-0 px-4 pt-4 flex gap-2 justify-center sm:justify-start"
    >
      <button
        class="px-4 py-2 bg-audinary hover:bg-audinary/90 text-black rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        @click="saveAllChanges"
        :disabled="isSaving"
      >
        <span
          v-if="isSaving"
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

    <!-- Songs List -->
    <div
      class="flex-1 overflow-y-auto px-2 py-4"
      ref="songsScrollContainer"
      @scroll="onSongsScroll"
    >
      <!-- Edit Form (if in edit mode) -->
      <div
        v-if="isEditMode && currentPlaylist"
        class="mb-6 bg-black/30 backdrop-blur-sm rounded-2xl p-4 mx-2"
      >
        <div class="mb-4">
          <label class="block text-sm font-medium text-white/80 mb-2">{{
            $t("playlist.name")
          }}</label>
          <input
            v-model="editableName"
            class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
            :placeholder="$t('playlist.namePlaceholder')"
          />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-white/80 mb-2">{{
            $t("playlist.description")
          }}</label>
          <textarea
            v-model="editableDescription"
            class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent resize-none"
            rows="2"
            :placeholder="$t('playlist.descriptionPlaceholder')"
          ></textarea>
        </div>
      </div>

      <!-- Songs List Header -->
      <div class="flex justify-between items-center mb-4 px-2">
        <h6 class="text-lg font-medium mb-0 text-white">
          {{ $t("playlist.songs") }}
        </h6>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="h-full flex items-center justify-center">
        <div
          class="animate-spin w-8 h-8 border-4 border-white/30 border-t-white rounded-full"
        >
          <span class="sr-only">{{ $t("common.loading") }}</span>
        </div>
      </div>

      <!-- Songs Content -->
      <template v-else-if="songs.length > 0">
        <!-- Draggable Songs List (Edit Mode) -->
        <draggable
          v-if="isEditMode"
          :model-value="editableSongs"
          @update:model-value="editableSongs = $event"
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
              class="flex items-center py-3 px-4 rounded-xl bg-white/[0.07] hover:bg-white/15 transition-colors"
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
                <SoundWaveAnimation :song="song" :track-number="index + 1" />
              </div>
              <div class="relative w-10 h-10 mr-4">
                <div
                  v-if="song.coverGradient && song.coverGradient.colors"
                  class="absolute inset-0 rounded"
                  :style="{
                    background: `linear-gradient(${song.coverGradient.angle || 135}deg, ${song.coverGradient.colors.join(', ')})`,
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

              <!-- Remove Button -->
              <div class="flex gap-2">
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
            v-for="(song, index) in visibleSongs"
            :key="`view-${song.id || song.song_id}-${index}`"
            class="flex items-center py-3 px-4 rounded-xl bg-white/[0.07] hover:bg-white/15 transition-colors cursor-pointer group"
            @click="playSong(song)"
          >
            <div
              class="w-8 text-center text-sm text-white/70 mr-4 font-mono font-bold"
            >
              <SoundWaveAnimation :song="song" :track-number="index + 1" />
            </div>
            <div class="relative w-10 h-10 mr-4">
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
      </template>

      <!-- Empty State -->
      <div v-else class="h-full flex items-center justify-center">
        <div class="text-center text-white/60">
          <i class="bi bi-music-note-list text-5xl mb-4"></i>
          <p>{{ $t("playlist.noSongs") }}</p>
        </div>
      </div>
    </div>

    <!-- Add to Playlist Modal -->
    <PlaylistAddToModal
      :is-visible="showAddToPlaylistModal"
      :selected-item="selectedSongForPlaylist"
      @close="closeAddToPlaylistModal"
    />

    <!-- Delete Confirmation Modal -->
    <PlaylistDeleteModal
      v-if="showDeleteModal"
      :playlist="currentPlaylist"
      @close="showDeleteModal = false"
      @deleted="onPlaylistDeleted"
    />
  </div>
</template>

<script>
import { ref, computed, watch } from "vue";
import { useI18n } from "vue-i18n";
import { usePlayerStore } from "@/stores/player";
import { useAlertStore } from "@/stores/alert";
import { useApiStore } from "@/stores/api";
import { useAuthStore } from "@/stores/auth";
import { useThemeStore } from "@/stores/theme";
import { usePlaylistStore } from "@/stores/playlist";
import { useDetailView } from "@/composables/useDetailView";
import draggable from "vuedraggable";
import SoundWaveAnimation from "@/components/common/SoundWaveAnimation.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";
import PlaylistAddToModal from "@/components/modals/PlaylistAddToModal.vue";
import PlaylistDeleteModal from "@/components/modals/PlaylistDeleteModal.vue";

export default {
  name: "PlaylistDetailView",
  components: {
    draggable,
    SoundWaveAnimation,
    SimpleImage,
    PlaylistAddToModal,
    PlaylistDeleteModal,
  },
  props: {
    playlistId: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const { t } = useI18n();
    const playerStore = usePlayerStore();
    const alertStore = useAlertStore();
    const apiStore = useApiStore();
    const authStore = useAuthStore();
    const themeStore = useThemeStore();
    const playlistStore = usePlaylistStore();
    const { closeDetail } = useDetailView();

    // Data
    const currentPlaylist = ref(null);
    const songs = ref([]);
    const isLoading = ref(false);
    const isSaving = ref(false);

    // Edit mode
    const isEditMode = ref(false);
    const editableName = ref("");
    const editableDescription = ref("");
    const editableSongs = ref([]);

    // Incremental rendering
    const RENDER_BATCH = 30;
    const renderLimit = ref(RENDER_BATCH);
    const songsScrollContainer = ref(null);

    // Modals
    const showAddToPlaylistModal = ref(false);
    const selectedSongForPlaylist = ref(null);
    const showDeleteModal = ref(false);

    const visibleSongs = computed(() => {
      return songs.value.slice(0, renderLimit.value);
    });

    const hasMoreSongs = computed(() => {
      return renderLimit.value < songs.value.length;
    });

    const canEdit = computed(() => {
      if (!currentPlaylist.value) return false;
      if (currentPlaylist.value.type === "smart") return false;
      const currentUser = authStore.user;
      if (!currentUser) return false;
      return (
        currentPlaylist.value.user_id === currentUser.user_id ||
        currentPlaylist.value.user_id === currentUser.id
      );
    });

    let scrollTicking = false;
    const onSongsScroll = (e) => {
      if (!hasMoreSongs.value || scrollTicking) return;
      scrollTicking = true;
      requestAnimationFrame(() => {
        const el = e.target;
        if (el.scrollTop + el.clientHeight >= el.scrollHeight - 200) {
          renderLimit.value = Math.min(
            renderLimit.value + RENDER_BATCH,
            songs.value.length,
          );
        }
        scrollTicking = false;
      });
    };

    // Track active fetch to cancel stale requests
    let activeFetchId = 0;

    async function loadPlaylist() {
      if (!props.playlistId) return;

      const fetchId = ++activeFetchId;
      isLoading.value = true;

      try {
        const response = await fetch(
          `/api/media/playlists/${props.playlistId}`,
          {
            headers: {
              Authorization: `Bearer ${authStore.token}`,
              "Content-Type": "application/json",
            },
          },
        );

        if (fetchId !== activeFetchId) return;

        if (!response.ok) {
          alertStore.error(t("playlist.loadError"));
          return;
        }

        const data = await response.json();

        if (fetchId !== activeFetchId) return;

        currentPlaylist.value = data.playlist;
        songs.value = data.songs || [];
        renderLimit.value = RENDER_BATCH;
      } catch (error) {
        if (fetchId !== activeFetchId) return;
        console.error("Error loading playlist:", error);
        alertStore.error(t("playlist.loadError"));
      } finally {
        if (fetchId === activeFetchId) {
          isLoading.value = false;
        }
      }
    }

    const goBack = () => {
      closeDetail();
    };

    // Player functions
    function playPlaylist() {
      if (songs.value.length > 0) {
        playerStore.playPlaylist(songs.value);
      }
    }

    function addPlaylistToQueue() {
      if (songs.value.length > 0) {
        playerStore.addMultipleToQueue(songs.value);
        alertStore.success(
          t("playlist.addedToQueue", {
            name: currentPlaylist.value?.name || "Playlist",
          }),
        );
      }
    }

    function playSong(song) {
      playerStore.playSong(song);

      // Add remaining songs to queue
      const songId = song.id || song.song_id;
      const selectedIndex = songs.value.findIndex(
        (s) => (s.id || s.song_id) === songId,
      );

      if (selectedIndex !== -1 && selectedIndex < songs.value.length - 1) {
        const remainingTracks = songs.value.slice(selectedIndex + 1);
        if (remainingTracks.length > 0) {
          playerStore.addMultipleToQueue(remainingTracks);
          alertStore.info(
            t("playlist.addedToQueue", {
              name: `${remainingTracks.length} Songs`,
            }),
          );
        }
      }
    }

    function addSongToQueue(song) {
      playerStore.addToQueue(song);
      alertStore.success(t("songs.addedToQueue", { title: song.title }));
    }

    // Favorite
    async function toggleSongFavorite(song) {
      try {
        const response = await apiStore.toggleFavorite({
          type: "song",
          itemId: song.id || song.song_id,
          currentlyFav: song.is_favorite,
        });

        if (response) {
          const songId = song.id || song.song_id;
          songs.value = songs.value.map((s) =>
            (s.id || s.song_id) === songId
              ? { ...s, is_favorite: !s.is_favorite }
              : s,
          );
        }
      } catch (error) {
        console.error("Error toggling song favorite:", error);
        alertStore.error("Failed to update favorite status");
      }
    }

    // Playlist add-to modal
    function showPlaylistAddToModal(song) {
      selectedSongForPlaylist.value = song;
      showAddToPlaylistModal.value = true;
    }

    function closeAddToPlaylistModal() {
      showAddToPlaylistModal.value = false;
      selectedSongForPlaylist.value = null;
    }

    // Edit mode
    function toggleEditMode() {
      isEditMode.value = true;
      editableName.value = currentPlaylist.value.name;
      editableDescription.value = currentPlaylist.value.description || "";
      editableSongs.value = songs.value.map((song) => ({ ...song }));
    }

    function cancelEdit() {
      isEditMode.value = false;
      editableName.value = "";
      editableDescription.value = "";
      editableSongs.value = [];
    }

    async function saveAllChanges() {
      try {
        isSaving.value = true;
        const playlistId =
          currentPlaylist.value.id || currentPlaylist.value.playlist_id;

        const metadataChanged =
          editableName.value !== currentPlaylist.value.name ||
          editableDescription.value !==
            (currentPlaylist.value.description || "");

        const originalSongIds = songs.value.map((s) => s.id || s.song_id);
        const newSongIds = editableSongs.value.map((s) => s.id || s.song_id);
        const orderChanged =
          originalSongIds.length !== newSongIds.length ||
          originalSongIds.some((id, index) => id !== newSongIds[index]);

        // Save metadata
        if (metadataChanged) {
          const response = await fetch(`/api/media/playlists/${playlistId}`, {
            method: "PUT",
            headers: {
              Authorization: `Bearer ${authStore.token}`,
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              name: editableName.value,
              description: editableDescription.value,
            }),
          });

          if (!response.ok) {
            throw new Error("Failed to save playlist metadata");
          }

          currentPlaylist.value.name = editableName.value;
          currentPlaylist.value.description = editableDescription.value;
        }

        // Save song order
        if (orderChanged && editableSongs.value.length > 0) {
          const songPositions = {};
          editableSongs.value.forEach((song, index) => {
            const songId = song.id || song.song_id;
            songPositions[songId] = index + 1;
          });

          await playlistStore.reorderPlaylistSongs(playlistId, songPositions);
          songs.value = editableSongs.value.map((song) => ({ ...song }));
        }

        // Show message
        if (metadataChanged && orderChanged) {
          alertStore.success(t("playlist.updateSuccess"));
        } else if (metadataChanged) {
          alertStore.success(t("playlist.metadataUpdateSuccess"));
        } else if (orderChanged) {
          alertStore.success(t("playlist.orderUpdateSuccess"));
        } else {
          alertStore.info(t("playlist.noChanges"));
        }

        isEditMode.value = false;
        editableSongs.value = [];

        if (metadataChanged) {
          await playlistStore.loadPlaylists();
        }
      } catch (err) {
        console.error("Error saving playlist changes:", err);
        alertStore.error(err.message);
      } finally {
        isSaving.value = false;
      }
    }

    async function removeFromEditableList(index) {
      const songToRemove = editableSongs.value[index];
      const playlistId =
        currentPlaylist.value.id || currentPlaylist.value.playlist_id;
      const songId = songToRemove.id || songToRemove.song_id;

      try {
        await playlistStore.removeSongFromPlaylist(playlistId, songId);

        editableSongs.value = editableSongs.value.filter((_, i) => i !== index);
        songs.value = songs.value.filter((s) => (s.id || s.song_id) !== songId);

        alertStore.success(t("playlist.songRemoved"));
      } catch (error) {
        console.error("Error removing song from playlist:", error);
        alertStore.error(error.message || t("playlist.removeSongError"));
      }
    }

    function onDragEnd() {
      // editableSongs is already updated by v-model
    }

    // Delete
    function confirmDelete() {
      showDeleteModal.value = true;
    }

    function onPlaylistDeleted() {
      showDeleteModal.value = false;
      playlistStore.loadPlaylists();
      closeDetail();
    }

    // Helpers
    function formatDuration(seconds) {
      if (!seconds) return "0:00";
      const minutes = Math.floor(seconds / 60);
      const remainingSeconds = seconds % 60;
      return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
    }

    function formatTotalDuration(songList) {
      if (!songList || songList.length === 0) return "0:00";
      const totalSeconds = songList.reduce(
        (total, song) => total + song.duration,
        0,
      );
      const minutes = Math.floor(totalSeconds / 60);
      const remainingSeconds = totalSeconds % 60;
      return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
    }

    // Watch for playlistId changes
    watch(
      () => props.playlistId,
      (newId) => {
        if (newId) {
          // Reset state
          isEditMode.value = false;
          editableSongs.value = [];
          renderLimit.value = RENDER_BATCH;
          loadPlaylist();
        }
      },
      { immediate: true },
    );

    return {
      currentPlaylist,
      songs,
      isLoading,
      isSaving,
      isEditMode,
      editableName,
      editableDescription,
      editableSongs,
      visibleSongs,
      canEdit,
      showAddToPlaylistModal,
      selectedSongForPlaylist,
      showDeleteModal,
      songsScrollContainer,
      goBack,
      playPlaylist,
      addPlaylistToQueue,
      playSong,
      addSongToQueue,
      toggleSongFavorite,
      showPlaylistAddToModal,
      closeAddToPlaylistModal,
      toggleEditMode,
      cancelEdit,
      saveAllChanges,
      removeFromEditableList,
      onDragEnd,
      onSongsScroll,
      confirmDelete,
      onPlaylistDeleted,
      formatDuration,
      formatTotalDuration,
      themeStore,
      t,
    };
  },
};
</script>

<style scoped>
@reference "tailwindcss";

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
