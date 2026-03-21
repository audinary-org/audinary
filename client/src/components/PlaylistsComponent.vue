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
              <p v-if="playlist.type === 'smart'" class="text-center text-purple-400 text-xs mb-1">
                <i class="bi bi-lightning-fill text-yellow-300"></i> {{ $t('playlist.smartPlaylist') }}
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

  <!-- Playlist Detail Modal -->
  <PlaylistDetailModal
    :is-visible="showDetailModal"
    :current-playlist-detail="currentPlaylistDetail"
    :playlist-songs="playlistSongs"
    :is-loading="isLoading"
    :is-edit-mode="isEditMode"
    :editable-playlist="editablePlaylist"
    :editable-playlist-songs="editablePlaylistSongs"
    :can-edit-playlist="canEditPlaylist"
    :format-duration="formatDuration"
    :format-total-duration="formatTotalDuration"
    @close="closeDetailModal"
    @toggle-inline-edit-mode="toggleInlineEditMode"
    @save-changes="saveAllChanges"
    @cancel-edit="cancelEdit"
    @play-playlist="playPlaylistFromDetail"
    @add-playlist-to-queue="addPlaylistToQueueFromDetail"
    @play-song="playSong"
    @add-song-to-queue="addSongToQueue"
    @remove-song="removeSongFromPlaylist"
    @show-add-to-playlist-modal="showPlaylistAddToModal"
    @toggle-song-favorite="toggleSongFavorite"
    @change-playlist-cover="changePlaylistCover"
    @update-editable-playlist-songs="updateEditablePlaylistSongs"
    @remove-from-editable-list="removeFromEditableList"
    @confirm-delete-current-playlist="confirmDeleteCurrentPlaylist"
    @on-drag-end="onDragEnd"
  />

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
import PlaylistDetailModal from "@/components/modals/PlaylistDetailModal.vue";
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
    PlaylistDetailModal,
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

    // Local state
    const isLoading = ref(false);
    const error = ref(null);
    const showDeleteModal = ref(false);
    const playlistToDelete = ref(null);

    // Modal states
    const showGlobalCreateModal = ref(false);
    const currentPlaylistDetail = ref(null);

    // Local playlist detail modal state
    const showDetailModal = ref(false);
    const selectedPlaylist = ref(null);
    const playlistSongs = ref([]);

    // Edit mode state
    const isEditMode = ref(false);
    const editablePlaylist = ref({
      name: "",
      description: "",
    });
    const editablePlaylistSongs = ref([]);

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

    async function openPlaylistDetail(playlistId) {
      try {
        isLoading.value = true;

        const response = await fetch(`/api/media/playlists/${playlistId}`, {
          headers: {
            Authorization: `Bearer ${authStore.token}`,
            "Content-Type": "application/json",
          },
        });

        if (!response.ok) {
          alertStore.error(t("playlist.loadError"));
          return;
        }

        const data = await response.json();
        currentPlaylistDetail.value = data.playlist;
        playlistSongs.value = data.songs || [];

        // Open the modal
        showDetailModal.value = true;
      } catch (error) {
        console.error("Error opening playlist:", error);
        alertStore.error(t("playlist.loadError"));
      } finally {
        isLoading.value = false;
      }
    }

    function closeDetailModal() {
      showDetailModal.value = false;
      selectedPlaylist.value = null;
      playlistSongs.value = [];
    }

    function closeAllModals() {
      showGlobalCreateModal.value = false;
      showDeleteModal.value = false;
      showPlaylistModal.value = false;
      showPermissionsModal.value = false;
      showDetailModal.value = false;
    }

    // Player functions for the detail modal
    async function playPlaylistFromDetail() {
      try {
        if (playlistSongs.value.length > 0) {
          playerStore.playPlaylist(playlistSongs.value);
        }
      } catch (err) {
        console.error("Error playing playlist:", err);
      }
    }

    async function addPlaylistToQueueFromDetail() {
      try {
        if (playlistSongs.value.length > 0) {
          playlistSongs.value.forEach((song) => {
            playerStore.addToQueue(song);
          });
        }
      } catch (err) {
        console.error("Error adding playlist to queue:", err);
      }
    }

    function confirmDeletePlaylist(playlist) {
      playlistToDelete.value = playlist;
      showDeleteModal.value = true;
    }

    function confirmDeleteCurrentPlaylist() {
      if (!currentPlaylistDetail.value) return;

      // Set the current playlist as the one to delete and show the modal
      playlistToDelete.value = currentPlaylistDetail.value;
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
        closeDetailModal();
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
        // If we have the playlist details loaded, use the songs directly
        if (
          currentPlaylistDetail.value &&
          (currentPlaylistDetail.value.id === playlistId ||
            currentPlaylistDetail.value.playlist_id === playlistId) &&
          playlistSongs.value.length > 0
        ) {
          playerStore.playPlaylist(playlistSongs.value);
          alertStore.success(
            t("playlist.playing", {
              name: currentPlaylistDetail.value?.name || "Playlist",
            }),
          );
          return;
        }

        // Otherwise, load the playlist songs first
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

    async function playSong(song) {
      try {
        playerStore.playSong(song);
      } catch (err) {
        console.error("Error playing song:", err);
        alertStore.error(t("songs.playError"));
      }
    }

    async function addSongToQueue(song) {
      try {
        playerStore.addToQueue(song);
        alertStore.success(t("songs.addedToQueue", { title: song.title }));
      } catch (err) {
        console.error("Error adding song to queue:", err);
        alertStore.error(t("songs.addToQueueError"));
      }
    }

    async function addPlaylistToQueue(playlistId) {
      try {
        // If we have the playlist details loaded, use the songs directly
        if (
          currentPlaylistDetail.value &&
          (currentPlaylistDetail.value.id === playlistId ||
            currentPlaylistDetail.value.playlist_id === playlistId) &&
          playlistSongs.value.length > 0
        ) {
          playlistSongs.value.forEach((song) => {
            playerStore.addToQueue(song);
          });
          alertStore.success(
            t("playlist.addedToQueue", {
              name: currentPlaylistDetail.value?.name || "Playlist",
            }),
          );
          return;
        }

        // Otherwise, load the playlist songs first
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

        songs.forEach((song) => {
          playerStore.addToQueue(song);
        });
        alertStore.success(t("playlist.addedToQueue", { name: "Playlist" }));
      } catch (err) {
        console.error("Error adding playlist to queue:", err);
        alertStore.error(t("playlist.addToQueueError"));
      }
    }

    async function removeSongFromPlaylist(songId) {
      try {
        const playlistId =
          currentPlaylistDetail.value.id ||
          currentPlaylistDetail.value.playlist_id;
        const response = await fetch(
          `/api/media/playlists/${playlistId}/songs/${songId}`,
          {
            method: "DELETE",
            headers: {
              Authorization: `Bearer ${authStore.token}`,
              "Content-Type": "application/json",
            },
          },
        );

        if (!response.ok) {
          alertStore.error(t("playlist.removeSongError"));
          return;
        }

        // Remove from local list
        playlistSongs.value = playlistSongs.value.filter(
          (song) => (song.id || song.song_id) !== songId,
        );
        alertStore.success(t("playlist.songRemoved"));
      } catch (err) {
        console.error("Error removing song from playlist:", err);
        alertStore.error(t("playlist.removeSongError"));
      }
    }

    function toggleInlineEditMode() {
      if (isEditMode.value) {
        // Exiting edit mode - save all changes
        saveAllChanges();
      } else {
        // Entering edit mode - initialize editable data
        isEditMode.value = true;
        editablePlaylist.value = {
          name: currentPlaylistDetail.value.name,
          description: currentPlaylistDetail.value.description || "",
        };
        editablePlaylistSongs.value = playlistSongs.value.map((song) => ({
          ...song,
        }));
      }
    }

    async function saveAllChanges() {
      try {
        isLoading.value = true;
        const playlistId =
          currentPlaylistDetail.value.id ||
          currentPlaylistDetail.value.playlist_id;

        // Check if playlist metadata has changed
        const metadataChanged =
          editablePlaylist.value.name !== currentPlaylistDetail.value.name ||
          editablePlaylist.value.description !==
            (currentPlaylistDetail.value.description || "");

        // Check if song order has changed
        const originalSongIds = playlistSongs.value.map(
          (song) => song.id || song.song_id,
        );
        const newSongIds = editablePlaylistSongs.value.map(
          (song) => song.id || song.song_id,
        );
        const orderChanged =
          originalSongIds.length !== newSongIds.length ||
          originalSongIds.some((id, index) => id !== newSongIds[index]);

        // Save playlist metadata only if it has changed
        if (metadataChanged) {
          await savePlaylistMetadata(playlistId);
        }

        // Save song order only if it has changed and songs exist
        if (orderChanged && editablePlaylistSongs.value.length > 0) {
          await savePlaylistOrder();
        }

        // Show appropriate success message
        if (metadataChanged && orderChanged) {
          alertStore.success(t("playlist.updateSuccess"));
        } else if (metadataChanged) {
          alertStore.success(t("playlist.metadataUpdateSuccess"));
        } else if (orderChanged) {
          alertStore.success(t("playlist.orderUpdateSuccess"));
        } else {
          alertStore.info(t("playlist.noChanges"));
        }

        // Exit edit mode
        isEditMode.value = false;

        // Reload playlists to update the list and store only if metadata changed
        if (metadataChanged) {
          await loadPlaylists();
          await playlistStore.loadPlaylists();
        }
      } catch (err) {
        console.error("Error saving playlist changes:", err);
        alertStore.error(err.message);
      } finally {
        isLoading.value = false;
      }
    }

    async function savePlaylistMetadata(playlistId) {
      const response = await fetch(`/api/media/playlists/${playlistId}`, {
        method: "PUT",
        headers: {
          Authorization: `Bearer ${authStore.token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          name: editablePlaylist.value.name,
          description: editablePlaylist.value.description,
        }),
      });

      if (!response.ok) {
        throw new Error("Failed to save playlist metadata");
      }

      // Update current playlist detail with new data
      currentPlaylistDetail.value.name = editablePlaylist.value.name;
      currentPlaylistDetail.value.description =
        editablePlaylist.value.description;

      // Update modal title and any other references
      // The reactive currentPlaylistDetail will automatically update the UI
    }

    async function savePlaylistOrder() {
      const playlistId =
        currentPlaylistDetail.value.id ||
        currentPlaylistDetail.value.playlist_id;

      // Create song_positions object with songId => position mapping
      const songPositions = {};
      editablePlaylistSongs.value.forEach((song, index) => {
        const songId = song.id || song.song_id;
        songPositions[songId] = index + 1; // 1-based position
      });

      await playlistStore.reorderPlaylistSongs(playlistId, songPositions);

      // Update the main playlist songs with the new order
      playlistSongs.value = editablePlaylistSongs.value.map((song) => ({
        ...song,
      }));
    }

    async function removeFromEditableList(index) {
      const songToRemove = editablePlaylistSongs.value[index];
      const playlistId =
        currentPlaylistDetail.value.id ||
        currentPlaylistDetail.value.playlist_id;
      const songId = songToRemove.id || songToRemove.song_id;

      try {
        // Remove from server immediately
        await playlistStore.removeSongFromPlaylist(playlistId, songId);

        // Remove from editable list
        editablePlaylistSongs.value.splice(index, 1);

        // Also remove from main playlist songs array to keep them in sync
        const songIndex = playlistSongs.value.findIndex(
          (s) => (s.id || s.song_id) === songId,
        );
        if (songIndex !== -1) {
          playlistSongs.value.splice(songIndex, 1);
        }

        alertStore.success(t("playlist.songRemoved"));
      } catch (error) {
        console.error("Error removing song from playlist:", error);
        alertStore.error(error.message || t("playlist.removeSongError"));
      }
    }

    function updateEditablePlaylistSongs(newSongs) {
      editablePlaylistSongs.value = newSongs;
    }

    function onDragEnd(event) {
      // The editablePlaylistSongs is already updated by v-model
      // Changes will be saved when exiting edit mode
    }

    function canEditPlaylist(playlist) {
      if (!playlist) return false;

      // Smart playlists are not editable by users (admin-only)
      if (playlist.type === 'smart') return false;

      // Get current user from auth store
      const currentUser = authStore.user;
      if (!currentUser) return false;

      // Only the playlist owner can edit
      return (
        playlist.user_id === currentUser.user_id ||
        playlist.user_id === currentUser.id
      );
    }

    function changePlaylistCover() {
      // Create a file input element
      const fileInput = document.createElement("input");
      fileInput.type = "file";
      fileInput.accept = "image/jpeg,image/jpg,image/png";
      fileInput.style.display = "none";

      fileInput.addEventListener("change", async (event) => {
        const file = event.target.files[0];
        if (!file) return;

        // Validate file type
        const validTypes = ["image/jpeg", "image/jpg", "image/png"];
        if (!validTypes.includes(file.type)) {
          alertStore.error("Please select a JPG or PNG image file.");
          return;
        }

        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
          alertStore.error("Image file size must be less than 5MB.");
          return;
        }

        try {
          // Create FormData for file upload
          const formData = new FormData();
          formData.append("playlistCover", file);

          const response = await fetch(
            `/api/media/playlists/${currentPlaylistDetail.value.playlist_id}/cover`,
            {
              method: "POST",
              headers: {
                Authorization: `Bearer ${authStore.token}`,
              },
              body: formData,
            },
          );

          const result = await response.json();

          if (response.ok && result.success) {
            // Update the playlist's cover_image_uuid
            currentPlaylistDetail.value.cover_image_uuid =
              result.cover_image_uuid;

            // Update in playlists array
            const playlistIndex = playlists.value.findIndex(
              (p) => p.playlist_id === currentPlaylistDetail.value.playlist_id,
            );
            if (playlistIndex !== -1) {
              playlists.value[playlistIndex].cover_image_uuid =
                result.cover_image_uuid;
            }

            alertStore.success("Playlist cover updated successfully!");
          } else {
            alertStore.error(result.error || "Failed to upload playlist cover");
          }
        } catch (error) {
          console.error("Cover upload error:", error);
          alertStore.error(
            "Failed to upload playlist cover. Please try again.",
          );
        }
      });

      // Trigger file selection
      document.body.appendChild(fileInput);
      fileInput.click();
      document.body.removeChild(fileInput);
    }

    function cancelEdit() {
      // Reset to original values
      isEditMode.value = false;
      editablePlaylist.value = {
        name: "",
        description: "",
      };
      editablePlaylistSongs.value = [];
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

    async function toggleSongFavorite(song) {
      try {
        const response = await apiStore.toggleFavorite({
          type: "song",
          itemId: song.id || song.song_id,
          currentlyFav: song.is_favorite,
        });

        if (response) {
          song.is_favorite = !song.is_favorite;
        }
      } catch (error) {
        console.error("Error toggling song favorite:", error);
        alertStore.error("Failed to update favorite status");
      }
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

    async function loadAndOpenPlaylistDetail(playlistId) {
      try {
        isLoading.value = true;
        const response = await fetch(`/api/media/playlists/${playlistId}`, {
          headers: {
            Authorization: `Bearer ${authStore.token}`,
            "Content-Type": "application/json",
          },
        });

        if (!response.ok) {
          alertStore.error(t("playlist.loadError"));
          closeAllModals();
          return;
        }

        const data = await response.json();
        currentPlaylistDetail.value = data.playlist;
        playlistSongs.value = data.songs || [];

        // Ensure local modal is also opened
        showDetailModal.value = true;
      } catch (err) {
        console.error("Error loading playlist detail:", err);
        alertStore.error(t("playlist.loadError"));

        // Reset global state on error
        closeAllModals();
      } finally {
        isLoading.value = false;
      }
    }

    // Watch for URL parameters to open playlist from navigation
    watch(
      () => route.query,
      (newQuery) => {
        // Check if specific playlist should be opened from navigation
        if (newQuery.openPlaylist && playlists.value.length > 0) {
          const playlistId = newQuery.openPlaylist;
          openPlaylistDetail(playlistId);

          // Remove the openPlaylist parameter from URL after opening
          router.replace({
            path: "/",
            query: { tab: "playlists" },
          });
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
      currentPlaylistDetail,

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
      closeDetailModal,
      confirmDeletePlaylist,
      confirmDeleteCurrentPlaylist,
      deletePlaylist,
      removeSongFromPlaylist,
      formatDate,
      formatDuration,
      formatTotalDuration,
      onPlaylistCreated,

      // Player functions for detail modal
      playPlaylist,
      addPlaylistToQueue,
      playSong,
      addSongToQueue,
      playPlaylistFromDetail,
      addPlaylistToQueueFromDetail,

      // Playlist favorites functionality
      togglePlaylistFavorite,

      // New methods for consistent button functionality
      showPlaylistAddToModal,
      closePlaylistModal,
      toggleSongFavorite,
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

      // Edit mode functionality
      isEditMode,
      editablePlaylist,
      editablePlaylistSongs,
      toggleInlineEditMode,
      saveAllChanges,
      cancelEdit,
      removeFromEditableList,
      updateEditablePlaylistSongs,
      onDragEnd,
      canEditPlaylist,
      changePlaylistCover,
      loadAndOpenPlaylistDetail,

      // Modal state
      showDetailModal,
      selectedPlaylist,
      playlistSongs,

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
