<template>
  <div class="flex flex-col h-full">
    <ContentHeader
      :title="$t('nav.songs')"
      v-model:searchQuery="searchQuery"
      @update:searchQuery="onSearchInput"
      :show-search="true"
      :show-filter="true"
      :filters-open="showFilters"
      @toggle-filters="toggleFilters"
      :active-filters-count="activeFiltersCount"
      :show-view-toggle="true"
      v-model:viewMode="viewMode"
    >
      <template #actions>
        <!-- Favorites Toggle -->
        <button
          @click="toggleFavorites"
          :class="
            showFavoritesOnly
              ? 'inline-flex items-center gap-2 px-3 py-1 rounded bg-audinary text-black'
              : 'inline-flex items-center gap-2 px-3 py-1 border border-audinary rounded text-audinary hover:bg-audinary hover:text-black'
          "
        >
          <i class="bi bi-heart-fill"></i>
          <span class="hidden md:inline">{{
            showFavoritesOnly
              ? $t("albums.filter.all")
              : $t("songs.filter.favorites")
          }}</span>
        </button>

        <!-- Play All Random Button -->
        <button
          class="inline-flex items-center gap-2 px-3 py-1 border border-green-600/90 text-white rounded-lg hover:bg-green-700"
          @click="playAllRandom"
          :disabled="songs.length === 0 || loading"
          :title="$t('songs.playAllRandom')"
        >
          <i class="bi bi-shuffle"></i>
          <span class="hidden md:inline">{{ $t("songs.playAll") }}</span>
        </button>
      </template>

      <template #filters>
        <div
          class="bg-white/10 text-white shadow-lg rounded-lg p-4 mb-3 border border-white/20"
        >
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <h6 class="text-sm font-medium">
                {{ $t("common.sortBy") }}
              </h6>
              <div class="flex gap-2 mt-2 items-center">
                <select
                  v-model="activeFilters.sort"
                  class="bg-white/20 text-white rounded px-2 py-1"
                  @change="onFiltersChanged"
                >
                  <option value="artistAndAlbum">
                    {{ $t("songs.filter.artist") }}
                  </option>
                  <option value="album">
                    {{ $t("songs.filter.album") }}
                  </option>
                  <option value="year">
                    {{ $t("albums.filter.year") }}
                  </option>
                  <option value="added">
                    {{ $t("songs.filter.added") }}
                  </option>
                </select>
                <button
                  type="button"
                  class="px-2 py-1 border border-white/20 rounded text-white"
                  @click="toggleSortDirection"
                  :title="
                    activeFilters.sortDirection === 'asc'
                      ? $t('common.ascending')
                      : $t('common.descending')
                  "
                >
                  <i
                    :class="
                      activeFilters.sortDirection === 'asc'
                        ? 'bi bi-arrow-up'
                        : 'bi bi-arrow-down'
                    "
                  ></i>
                </button>
              </div>
            </div>

            <div>
              <h6 class="text-sm font-medium">{{ $t("nav.genres") }}</h6>
              <select
                v-model="activeFilters.genre"
                class="bg-white/20 text-white rounded px-2 py-1 w-full"
                @change="onFiltersChanged"
              >
                <option value="">{{ $t("common.all") }}</option>
                <option
                  v-for="genre in availableGenres"
                  :key="genre.name"
                  :value="genre.name"
                >
                  {{ genre.name }} ({{ genre.track_count }})
                </option>
              </select>
            </div>

            <div>
              <h6 class="text-sm font-medium">{{ $t("nav.decades") }}</h6>
              <select
                v-model="activeFilters.decade"
                class="bg-white/20 text-white rounded px-2 py-1 w-full"
                @change="onFiltersChanged"
              >
                <option value="">{{ $t("common.all") }}</option>
                <option
                  v-for="decade in availableDecades"
                  :key="decade.start_year"
                  :value="decade.start_year"
                >
                  {{ decade.decade }} ({{ decade.track_count }})
                </option>
              </select>
            </div>
          </div>

          <div
            class="mt-4 pt-3 border-t border-white/20"
            v-if="hasActiveFilters || searchQuery"
          >
            <div class="flex gap-2">
              <button
                class="px-2 py-1 border border-white/20 rounded text-white hover:bg-white/10"
                @click="clearFilters"
                v-if="hasActiveFilters"
              >
                <i class="bi bi-x-circle me-1"></i>
                {{ $t("common.clear") }} Filter
              </button>
              <button
                class="px-2 py-1 border border-red-600/90 rounded text-red-400 hover:bg-red-600/20"
                @click="clearAll"
                v-if="hasActiveFilters || searchQuery"
              >
                <i class="bi bi-x-circle me-1"></i>
                {{ $t("common.clear") }} Alle
              </button>
            </div>
          </div>
        </div>
      </template>
    </ContentHeader>

    <!-- Songs Content Area (scrollable) -->
    <div class="flex-1 overflow-y-auto py-4">
      <div>
        <!-- List View (Default) -->
        <div v-if="viewMode === 'list'" class="songs-list flex flex-col gap-2">
          <div
            v-for="song in songs"
            :key="song.song_id"
            class="flex items-center p-2 rounded-md group bg-white/10 backdrop-blur-lg drop-shadow-lg hover:bg-white/20"
            @click="playSong(song)"
          >
            <div class="w-10 text-center text-white/80 mr-3">
              <SoundWaveAnimation
                :song="song"
                :track-number="songs.indexOf(song) + 1"
              />
            </div>

            <div class="relative mr-3" style="width: 40px; height: 40px">
              <!-- Gradient placeholder background -->
              <div
                v-if="song.coverGradient && song.coverGradient.colors"
                class="absolute inset-0"
                :style="{
                  background: `linear-gradient(${song.coverGradient.angle || 135}deg, ${song.coverGradient.colors.join(', ')})`,
                  filter: 'blur(10px)',
                  zIndex: 1,
                }"
              ></div>
              <SimpleImage
                :imageType="'album_thumbnail'"
                :imageId="song.album_id || 'default'"
                :alt="song.album"
                :class="'rounded relative z-[2]'"
                :style="'width: 40px; height: 40px; object-fit: cover;'"
                :placeholder="'disc'"
                :placeholderSize="'20px'"
              />
            </div>

            <div class="flex-1 min-w-0">
              <div class="font-medium text-lg text-audinary truncate">
                {{ song.title }}
              </div>
              <div class="text-white/80 text-sm truncate">
                {{ song.artist }} - {{ song.album }}
              </div>
              <div class="text-white/80 text-sm md:hidden truncate">
                {{ song.year || "--" }} • {{ song.genre || "--" }}
              </div>
            </div>

            <div class="hidden md:block text-white/80 mr-3 text-sm">
              {{ song.year || "--" }}
            </div>
            <div class="hidden lg:block text-white/80 mr-3 text-sm">
              {{ song.genre || "--" }}
            </div>
            <div class="text-white/80 mr-3">
              {{ formatDuration(song.duration) }}
            </div>

            <div class="flex gap-1">
              <button
                class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                @click.stop="playSong(song)"
                :title="$t('player.play')"
              >
                <i class="bi bi-play-fill text-xs text-gray"></i>
              </button>
              <button
                class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                @click.stop="addToQueue(song)"
                :title="$t('songs.add-to-queue')"
              >
                <i class="bi bi-list text-xs text-gray"></i>
              </button>
              <button
                v-if="canCreateShare"
                class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                @click.stop="shareSong(song)"
                :title="$t('shares.share_song')"
              >
                <i class="bi bi-share text-xs text-gray"></i>
              </button>
              <button
                class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                @click.stop="showPlaylistAddToModal(song)"
                :title="$t('songs.add_to_playlist')"
              >
                <i class="bi bi-music-note-list text-xs text-gray"></i>
              </button>
              <button
                class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                @click.stop="toggleSongFavorite(song)"
                :title="$t('songs.favorite')"
              >
                <i
                  class="bi text-xs"
                  :class="
                    song.is_favorite
                      ? 'bi-heart-fill text-red-400'
                      : 'bi-heart text-gray'
                  "
                ></i>
              </button>
            </div>

            <!-- Mobile Dropdown -->
            <div class="relative md:hidden">
              <button
                class="px-2 py-1 rounded bg-transparent border border-white/20 text-white"
                type="button"
                :id="`songDropdown-${song.song_id}`"
                @click.stop="toggleDropdown(song.song_id)"
              >
                <i class="bi bi-three-dots-vertical"></i>
              </button>

              <ul
                v-if="openDropdownId === song.song_id"
                class="absolute right-0 mt-2 w-48 bg-white/20 backdrop-blur-lg border border-white/20 rounded shadow-lg z-50 py-1"
              >
                <li>
                  <button
                    class="w-full text-left px-4 py-2 text-white hover:bg-white/10"
                    @click.stop="handleDropdownAction(() => playSong(song))"
                  >
                    <i class="bi bi-play me-2"></i>{{ $t("player.play") }}
                  </button>
                </li>
                <li>
                  <button
                    class="w-full text-left px-4 py-2 text-white hover:bg-white/10"
                    @click.stop="handleDropdownAction(() => addToQueue(song))"
                  >
                    <i class="bi bi-list me-2"></i
                    >{{ $t("songs.add-to-queue") }}
                  </button>
                </li>
                <li>
                  <button
                    class="w-full text-left px-4 py-2 text-white hover:bg-white/10"
                    @click.stop="
                      handleDropdownAction(() => showPlaylistAddToModal(song))
                    "
                  >
                    <i class="bi bi-music-note-list me-2"></i
                    >{{ $t("songs.add_to_playlist") }}
                  </button>
                </li>
                <li class="border-t border-white/20 my-1"></li>
                <li>
                  <button
                    class="w-full text-left px-4 py-2 text-white hover:bg-white/10"
                    @click.stop="
                      handleDropdownAction(() => toggleSongFavorite(song))
                    "
                  >
                    <i
                      class="bi me-2"
                      :class="
                        song.is_favorite
                          ? 'bi-heart-fill text-red-500'
                          : 'bi-heart'
                      "
                    ></i>
                    {{ $t("songs.favorite") }}
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Card View -->
        <div
          v-else-if="viewMode === 'grid'"
          :class="viewMode === 'grid' ? 'grid gap-4' : 'space-y-2'"
          :style="
            viewMode === 'grid'
              ? 'grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));'
              : ''
          "
        >
          <div
            v-for="song in songs"
            :key="song.song_id"
            class="group cursor-pointer"
            @click="playSong(song)"
          >
            <div
              class="bg-white/10 backdrop-blur-lg rounded drop-shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/20"
            >
              <div class="relative">
                <div class="relative overflow-hidden mx-auto aspect-square">
                  <!-- Album cover with CD case overlay (if available) -->
                  <div v-if="song.album_id" class="w-full h-full relative">
                    <!-- Gradient placeholder background -->
                    <div
                      v-if="song.coverGradient && song.coverGradient.colors"
                      class="absolute top-[2%] left-[10%] w-[87%]"
                      :style="{
                        height: '87%',
                        background: `linear-gradient(${song.coverGradient.angle || 135}deg, ${song.coverGradient.colors.join(', ')})`,
                        filter: 'blur(10px)',
                        zIndex: 1,
                      }"
                    ></div>
                    <SimpleImage
                      :imageType="'album'"
                      :imageId="song.album_id"
                      :alt="song.album"
                      class="absolute top-[2%] left-[10%] w-[87%] h-auto z-[2] object-cover"
                      :placeholder="'disc'"
                      :placeholderSize="'80px'"
                      loading="lazy"
                    />
                    <img
                      :src="getCdCaseImage(song.filetype)"
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
                    @click.stop="playSong(song)"
                  >
                    <i
                      class="bi bi-play-circle-fill text-5xl text-white transition-colors hover:text-audinary drop-shadow-lg"
                    ></i>
                  </div>

                  <!-- Favorite icon (top right) -->
                  <button
                    class="absolute top-2 right-2 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 z-[10]"
                    @click.stop="toggleSongFavorite(song)"
                    :title="$t('common.favorite')"
                  >
                    <i
                      class="bi text-xs"
                      :class="
                        song.is_favorite
                          ? 'bi-heart-fill text-red-400'
                          : 'bi-heart text-gray'
                      "
                    ></i>
                  </button>

                  <!-- Add to queue button (bottom left) -->
                  <button
                    class="absolute bottom-8 left-6 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                    @click.stop="addToQueue(song)"
                    :title="$t('songs.add-to-queue')"
                  >
                    <i class="bi bi-list text-xs text-gray"></i>
                  </button>

                  <!-- Share button (bottom center) -->
                  <button
                    v-if="canCreateShare"
                    class="absolute bottom-8 right-12 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                    @click.stop="shareSong(song)"
                    :title="$t('shares.share_song')"
                  >
                    <i class="bi bi-share text-xs text-gray"></i>
                  </button>

                  <!-- Add to playlist button (bottom right) -->
                  <button
                    class="absolute bottom-8 right-2 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                    @click.stop="showPlaylistAddToModal(song)"
                    :title="$t('songs.add_to_playlist')"
                  >
                    <i class="bi bi-music-note-list text-xs text-gray"></i>
                  </button>
                </div>
              </div>

              <!-- Song details -->
              <div class="p-2 pt-0">
                <p
                  class="text-center font-semibold mb-1 text-audinary text-lg truncate"
                >
                  {{ song.title }}
                </p>
                <p class="text-center text-white/80 text-xs truncate mb-1">
                  {{ song.artist }}
                </p>
                <p class="text-center text-white/80 text-xs truncate mb-1">
                  {{ song.album }}
                </p>
                <div class="text-center text-white/80 text-xs truncate">
                  {{ song.year || "--" }} • {{ song.genre || "--" }} •
                  {{ formatDuration(song.duration) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">{{ $t("common.loading") }}</span>
          </div>
          <p class="mt-3 text-muted">{{ $t("common.loading") }}...</p>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="!loading && songs.length === 0"
          class="text-center py-5"
        >
          <i class="bi bi-music-note-list fs-1 text-muted"></i>
          <h4 class="mt-3 text-muted">{{ $t("library.noSongs") }}</h4>
          <p class="text-muted">{{ $t("library.noSongsDescription") }}</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="text-center py-5">
          <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
          <h4 class="mt-3 text-red-500">{{ $t("common.error") }}</h4>
          <p class="text-gray-400">{{ error }}</p>
          <button
            class="border border-green-600/90 text-green-500 px-4 py-2 rounded hover:bg-green-600/10"
            @click="loadSongs"
          >
            {{ $t("common.retry") }}
          </button>
        </div>

        <!-- Load More Trigger (invisible) -->
        <div
          v-if="hasMore && !loading && songs.length > 0"
          ref="loadMoreTrigger"
          class="load-more-trigger d-flex justify-content-center align-items-center py-3"
          style="min-height: 100px"
        >
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">{{ $t("common.loading") }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add to Playlist Modal -->
  <PlaylistAddToModal
    :is-visible="showPlaylistModal"
    :selected-item="selectedSong"
    @close="closePlaylistModal"
    @added="onSongAddedToPlaylist"
    @create-playlist="createNewPlaylist"
  />

  <!-- Create Share Modal -->
  <PublicSharesCreateModal
    v-if="showShareModal"
    type="song"
    :item-id="selectedSongForShare?.song_id"
    :item-data="selectedSongForShare"
    @close="closeShareModal"
    @share-created="onShareCreated"
  />
</template>

<script>
import { ref, onMounted, computed, nextTick, watch, onUnmounted } from "vue";
import { useI18n } from "vue-i18n";
import { usePlayerStore } from "@/stores/player";
import { useApiStore } from "@/stores/api";
import { useAlertStore } from "@/stores/alert";
import { useAuthStore } from "@/stores/auth";
import ContentHeader from "@/components/common/ContentHeader.vue";
import PlaylistAddToModal from "@/components/modals/PlaylistAddToModal.vue";
import PublicSharesCreateModal from "@/components/modals/PublicSharesCreateModal.vue";
import SoundWaveAnimation from "@/components/common/SoundWaveAnimation.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";
import { getCdCaseImage } from "@/utils/cdCases.js";

export default {
  name: "SongsComponent",
  components: {
    ContentHeader,
    PlaylistAddToModal,
    PublicSharesCreateModal,
    SoundWaveAnimation,
    SimpleImage,
  },
  setup() {
    const { t } = useI18n();
    const playerStore = usePlayerStore();
    const apiStore = useApiStore();
    const alertStore = useAlertStore();
    const authStore = useAuthStore();
    const songs = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const showFavoritesOnly = ref(false);
    const currentFilter = ref("all");
    const activeFilters = ref({
      sort: "artistAndAlbum",
      sortDirection: "asc",
      genre: "",
      decade: "",
    });
    const hasMore = ref(true);
    const currentPage = ref(0);
    const pageSize = 50;
    const showFilters = ref(false);
    const viewMode = ref("list");
    const availableGenres = ref([]);
    const availableDecades = ref([]);
    const loadMoreTrigger = ref(null);

    // Search functionality
    const searchQuery = ref("");
    const searchTimeout = ref(null);
    const showMobileSearch = ref(false);
    const mobileSearchInput = ref(null);

    // Dropdown-Management
    const openDropdownId = ref(null);
    const openGridDropdown = ref(null);

    // Playlist-bezogene Refs
    const showPlaylistModal = ref(false);
    const selectedSong = ref(null);

    // Share modal state
    const showShareModal = ref(false);
    const selectedSongForShare = ref(null);

    // Dropdown-Management
    const toggleDropdown = (songId) => {
      if (openDropdownId.value === songId) {
        openDropdownId.value = null;
      } else {
        openDropdownId.value = songId;
        adjustDropdownPosition(songId);
      }
    };

    const closeDropdown = () => {
      openDropdownId.value = null;
    };

    const handleDropdownAction = (action) => {
      clearTextSelection();
      action();
      closeDropdown();
    };

    // Click outside to close dropdown
    const handleDocumentClick = (event) => {
      if (openDropdownId.value && !event.target.closest(".dropdown")) {
        closeDropdown();
      }
    };

    // Scroll event to close dropdown
    const handleScroll = () => {
      if (openDropdownId.value) {
        closeDropdown();
      }
    };

    // Improved dropdown positioning
    const adjustDropdownPosition = (songId) => {
      nextTick(() => {
        const dropdownElement = document.querySelector(
          `#songDropdown-${songId}`,
        );
        const menuElement = dropdownElement?.nextElementSibling;

        if (dropdownElement && menuElement) {
          const rect = dropdownElement.getBoundingClientRect();
          const menuRect = menuElement.getBoundingClientRect();
          const viewportHeight = window.innerHeight;
          // Reset positioning
          menuElement.style.top = "";
          menuElement.style.bottom = "";
          menuElement.style.left = "";
          menuElement.style.right = "";

          // Check if dropdown would go off-screen vertically
          if (rect.bottom + menuRect.height > viewportHeight) {
            menuElement.style.top = "auto";
            menuElement.style.bottom = "100%";
          }

          // Check if dropdown would go off-screen horizontally
          if (rect.right - menuRect.width < 0) {
            menuElement.style.right = "auto";
            menuElement.style.left = "0";
          }
        }
      });
    };

    // Computed
    const hasActiveFilters = computed(() => {
      return (
        activeFilters.value.genre ||
        activeFilters.value.decade ||
        activeFilters.value.sort !== "artistAndAlbum" ||
        activeFilters.value.sortDirection !== "asc"
      );
    });

    const activeFiltersCount = computed(() => {
      let count = 0;
      if (activeFilters.value.genre) count++;
      if (activeFilters.value.decade) count++;
      if (activeFilters.value.sort !== "artistAndAlbum") count++;
      if (activeFilters.value.sortDirection !== "asc") count++;
      return count;
    });

    const loadSongs = async (reset = true) => {
      if (reset) {
        currentPage.value = 0;
        songs.value = [];
        closeDropdown(); // Close any open dropdown
      }

      loading.value = true;
      error.value = null;

      try {
        const params = {
          start: currentPage.value * pageSize,
          limit: pageSize,
          favorite: showFavoritesOnly.value ? 1 : 0,
          search: searchQuery.value || null,
          ...activeFilters.value,
        };

        const response = await apiStore.loadSongsChunk(params);

        if (reset) {
          songs.value = response.data || response || [];
        } else {
          songs.value.push(...(response.data || response || []));
        }

        hasMore.value = (response.data || response || []).length === pageSize;
      } catch (err) {
        console.error("SongsComponent error loading songs:", err);
        error.value = err.message || "Failed to load songs";
      } finally {
        loading.value = false;
      }
    };

    const loadMore = async () => {
      currentPage.value++;
      await loadSongs(false);
    };

    const toggleFavorites = async () => {
      showFavoritesOnly.value = !showFavoritesOnly.value;
      closeDropdown(); // Close any open dropdown
      await loadSongs();
    };

    const setFilter = async (filter) => {
      currentFilter.value = filter;
      closeDropdown(); // Close any open dropdown
      await loadSongs();
    };

    const setViewMode = (mode) => {
      viewMode.value = mode;
      closeDropdown(); // Close any open dropdown
    };

    const formatDuration = (seconds) => {
      if (!seconds) return "--:--";
      const mins = Math.floor(seconds / 60);
      const secs = seconds % 60;
      return `${mins}:${secs.toString().padStart(2, "0")}`;
    };

    const playSong = (song) => {
      playerStore.playSong(song);
    };

    const addToQueue = (song) => {
      playerStore.addToQueue(song);
      alertStore.success(t("songs.addedToQueue", { title: song.title }));
    };

    // Utility function to clear text selection
    const clearTextSelection = () => {
      if (window.getSelection) {
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
          selection.removeAllRanges();
        }
      } else if (document.selection) {
        // IE fallback
        document.selection.empty();
      }
    };

    // Search functionality
    const onSearchInput = () => {
      if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
      }

      searchTimeout.value = setTimeout(() => {
        loadSongs(true);
      }, 300);
    };

    const clearSearch = () => {
      searchQuery.value = "";
      loadSongs(true);
    };

    const toggleMobileSearch = () => {
      showMobileSearch.value = !showMobileSearch.value;
      if (showMobileSearch.value) {
        nextTick(() => {
          if (mobileSearchInput.value) {
            mobileSearchInput.value.focus();
          }
        });
      } else {
        if (searchQuery.value) {
          clearSearch();
        }
      }
    };

    const clearAll = async () => {
      searchQuery.value = "";
      activeFilters.value = {
        sort: "artistAndAlbum",
        sortDirection: "asc",
        genre: "",
        decade: "",
      };
      showMobileSearch.value = false;
      await loadSongs();
    };

    const toggleSongFavorite = async (song) => {
      try {
        await apiStore.toggleFavorite({
          type: "song",
          itemId: song.song_id,
          currentlyFav: song.is_favorite,
        });
        song.is_favorite = !song.is_favorite;

        const favoriteText = song.is_favorite
          ? t("songs.addedToFavorites")
          : t("songs.removedFromFavorites");
        alertStore.success(favoriteText);
      } catch (error) {
        console.error("Error toggling song favorite:", error);
        alertStore.error(t("songs.favoriteError"));
      }
    };

    // Playlist Functions
    const showPlaylistAddToModal = (song) => {
      selectedSong.value = song;
      showPlaylistModal.value = true;
    };

    const closePlaylistModal = () => {
      showPlaylistModal.value = false;
      selectedSong.value = null;
    };

    const onSongAddedToPlaylist = (data) => {
      // Event handler for successful addition
      console.log("Song added to playlist:", data);
    };

    const createNewPlaylist = () => {
      closePlaylistModal();
      alertStore.info(
        "Bitte verwenden Sie das Playlists-Tab um eine neue Playlist zu erstellen",
      );
    };

    const toggleFilters = () => {
      showFilters.value = !showFilters.value;
    };

    const onFiltersChanged = async () => {
      await loadSongs();
    };

    const clearFilters = async () => {
      activeFilters.value = {
        sort: "artistAndAlbum",
        sortDirection: "asc",
        genre: "",
        decade: "",
      };
      await loadSongs();
    };

    const toggleSortDirection = async () => {
      activeFilters.value.sortDirection =
        activeFilters.value.sortDirection === "asc" ? "desc" : "asc";
      await loadSongs();
    };

    const loadGenres = async () => {
      try {
        const response = await apiStore.loadAllGenres();
        availableGenres.value = response || [];
      } catch (error) {
        console.error("Error loading genres:", error);
      }
    };

    const loadDecades = async () => {
      try {
        const response = await apiStore.loadAllDecades();
        availableDecades.value = response || [];
      } catch (error) {
        console.error("Error loading decades:", error);
      }
    };

    const setupIntersectionObserver = () => {
      // Cleanup existing observer
      if (loadMoreTrigger.value?._observer) {
        loadMoreTrigger.value._observer.disconnect();
        delete loadMoreTrigger.value._observer;
      }

      nextTick(() => {
        if (loadMoreTrigger.value && hasMore.value) {
          const observer = new IntersectionObserver(
            (entries) => {
              const entry = entries[0];
              if (entry.isIntersecting && hasMore.value && !loading.value) {
                loadMore();
              }
            },
            {
              root: null,
              rootMargin: "200px",
              threshold: 0.1,
            },
          );

          observer.observe(loadMoreTrigger.value);
          loadMoreTrigger.value._observer = observer;
        }
      });
    };

    // Watch for changes that might trigger observer setup
    watch(
      [hasMore, () => songs.value.length],
      () => {
        if (hasMore.value && songs.value.length > 0) {
          setupIntersectionObserver();
        }
      },
      { flush: "post" },
    );

    const playAllRandom = async () => {
      if (songs.value.length === 0) {
        alertStore.warning(t("songs.noSongsToPlay"));
        return;
      }

      try {
        // Take up to 250 songs from current view
        const songsToPlay = songs.value.slice(0, 250);

        // Shuffle the songs array
        const shuffledSongs = [...songsToPlay].sort(() => Math.random() - 0.5);

        // Clear current queue and add shuffled songs
        playerStore.clearQueue();

        // Play first song and add rest to queue
        if (shuffledSongs.length > 0) {
          playerStore.playSong(shuffledSongs[0]);

          // Add remaining songs to queue
          for (let i = 1; i < shuffledSongs.length; i++) {
            playerStore.addToQueue(shuffledSongs[i]);
          }

          const count = shuffledSongs.length;
          alertStore.success(t("songs.playingRandomSongs", { count }));
        }
      } catch (error) {
        console.error("Error playing random songs:", error);
        alertStore.error(t("songs.errorPlayingRandom"));
      }
    };

    onMounted(async () => {
      await loadSongs();
      await loadGenres();
      await loadDecades();

      // Document-Click-Listener für Dropdown-Schließung
      document.addEventListener("click", handleDocumentClick);

      // Scroll-Listener für Dropdown-Schließung
      document.addEventListener("scroll", handleScroll, true);
      window.addEventListener("scroll", handleScroll, true);
    });

    onUnmounted(() => {
      // Cleanup observer
      if (loadMoreTrigger.value?._observer) {
        loadMoreTrigger.value._observer.disconnect();
      }

      // Remove event listeners
      document.removeEventListener("click", handleDocumentClick);
      document.removeEventListener("scroll", handleScroll, true);
      window.removeEventListener("scroll", handleScroll, true);
    });

    // Public share functionality
    const canCreateShare = computed(() => {
      return authStore.isAdmin || authStore.user?.can_create_public_share;
    });

    function shareSong(song) {
      selectedSongForShare.value = song;
      showShareModal.value = true;
    }

    function closeShareModal() {
      showShareModal.value = false;
      selectedSongForShare.value = null;
    }

    function onShareCreated() {
      closeShareModal();
    }

    return {
      songs,
      loading,
      error,
      showFavoritesOnly,
      currentFilter,
      viewMode,
      hasMore,
      loadMoreTrigger,
      loadSongs,
      loadMore,
      toggleFavorites,
      setFilter,
      setViewMode,
      formatDuration,
      playSong,
      addToQueue,
      toggleSongFavorite,
      showFilters,
      hasActiveFilters,
      activeFiltersCount,
      toggleFilters,
      onFiltersChanged,
      clearFilters,
      toggleSortDirection,
      availableGenres,
      availableDecades,
      activeFilters,
      openDropdownId,
      toggleDropdown,
      closeDropdown,
      handleDropdownAction,
      handleDocumentClick,
      handleScroll,
      adjustDropdownPosition,
      clearTextSelection,
      t,
      showPlaylistModal,
      selectedSong,
      showPlaylistAddToModal,
      closePlaylistModal,
      onSongAddedToPlaylist,
      createNewPlaylist,
      // Search functionality
      searchQuery,
      onSearchInput,
      clearSearch,
      toggleMobileSearch,
      showMobileSearch,
      mobileSearchInput,
      clearAll,
      playAllRandom,
      // Share functionality
      canCreateShare,
      showShareModal,
      selectedSongForShare,
      shareSong,
      closeShareModal,
      onShareCreated,
      getCdCaseImage,
      openGridDropdown,
      toggleGridDropdown: (songId) => {
        if (openGridDropdown.value === songId) {
          openGridDropdown.value = null;
        } else {
          openGridDropdown.value = songId;
        }
      },
      closeGridDropdown: () => {
        openGridDropdown.value = null;
      },
    };
  },
};
</script>

<style scoped>
/* Performance optimizations for large lists */
.group {
  /* Browser automatically manages rendering for off-screen elements */
  content-visibility: auto;

  /* Reserve space to prevent layout shift */
  contain-intrinsic-size: 80px;

  /* Isolate this element's style recalculations */
  contain: layout style paint;

  /* Hint to browser that transforms will be animated */
  will-change: transform;
}

/* Optimize hover effects */
.group:hover {
  /* Use transform instead of changing other properties for better performance */
  transform: translateZ(0);
}
</style>
