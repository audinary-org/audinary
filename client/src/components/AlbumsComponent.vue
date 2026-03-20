<template>
  <div class="flex flex-col h-full">
    <ContentHeader
      :title="$t('nav.albums')"
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
              : $t("albums.filter.favorites")
          }}</span>
        </button>

        <!-- Play All Random Button -->
        <button
          class="inline-flex items-center gap-2 px-3 py-1 border border-green-600/90 text-white hover:bg-green-700 rounded-lg"
          @click="playAllRandom"
          :disabled="albums.length === 0 || loading"
          :title="$t('albums.playAllRandom')"
        >
          <i class="bi bi-shuffle"></i>
          <span class="hidden md:inline">{{ $t("albums.playAll") }}</span>
        </button>
      </template>

      <template #filters>
        <div class="bg-white/10 text-white shadow-lg rounded-lg p-4 mb-3">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Keep album-specific filter controls -->
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
                    {{ $t("albums.filter.artistAndAlbum") }}
                  </option>
                  <option value="album">
                    {{ $t("albums.filter.album") }}
                  </option>
                  <option value="year">
                    {{ $t("albums.filter.year") }}
                  </option>
                  <option value="added">
                    {{ $t("albums.filter.added") }}
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
                  {{ genre.name }} ({{ genre.album_count }})
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
                  {{ decade.decade }} ({{ decade.album_count }})
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

    <!-- Albums Content Area (scrollable) -->
    <div class="flex-1 overflow-y-auto py-4">
      <!-- Albums Grid/List Container -->
      <div
        v-if="albums.length > 0"
        :class="viewMode === 'grid' ? 'grid gap-4' : 'space-y-2'"
        :style="
          viewMode === 'grid'
            ? 'grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));'
            : ''
        "
      >
        <!-- Grid View Albums -->
        <template v-if="viewMode === 'grid'">
          <div
            v-for="album in albums"
            :key="album.album_id"
            class="group cursor-pointer"
            @click="showAlbumDetail(album)"
          >
            <div
              class="bg-white/10 backdrop-blur-lg rounded drop-shadow-2xl p-2 h-full transition-all duration-200 hover:bg-white/20"
            >
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
                    :imageType="'album'"
                    :imageId="album.album_id"
                    :alt="album.albumName"
                    class="absolute top-[2%] left-[10%] w-[87%] h-auto object-cover z-[2]"
                    :placeholder="'disc'"
                    :placeholderSize="'250px'"
                    loading="lazy"
                  />
                  <img
                    :src="getCdCaseImage(album.albumFiletype)"
                    class="relative z-[3] w-full h-auto pointer-events-none shadow-black/30 shadow-lg"
                    alt="CD Case"
                    @error="$event.target.src = '/img/cdcases/default.webp'"
                  />
                </div>
                <div
                  v-else
                  class="w-full h-full flex items-center justify-center bg-gray-600"
                >
                  <i class="bi bi-disc text-white text-6xl"></i>
                </div>
                <!-- Play overlay on card hover (white) and icon hover (orange) -->
                <div
                  class="absolute top-[44%] left-1/2 -translate-x-1/2 -translate-y-1/2 w-12 h-12 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center cursor-pointer z-3"
                  @click.stop="playAlbum(album)"
                  role="button"
                  tabindex="0"
                >
                  <i
                    class="bi bi-play-circle-fill text-5xl text-white transition-colors hover:text-audinary drop-shadow-lg"
                  ></i>
                </div>
                <!-- Favorite icon (top right) -->
                <button
                  class="absolute top-2 right-2 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 z-[10]"
                  @click.stop="toggleAlbumFavorite(album)"
                  :title="$t('common.favorite')"
                >
                  <i
                    class="bi text-xs"
                    :class="
                      album.albumIsFavorite
                        ? 'bi-heart-fill text-red-400'
                        : 'bi-heart text-gray'
                    "
                  ></i>
                </button>
                <!-- Add to queue button (bottom left) -->
                <button
                  class="absolute bottom-8 left-6 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                  @click.stop="addToQueue(album)"
                  :title="$t('songs.add-to-queue')"
                >
                  <i class="bi bi-list text-xs text-gray"></i>
                </button>
                <!-- Share button (bottom center) -->
                <button
                  v-if="canCreateShare"
                  class="absolute bottom-8 right-12 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                  @click.stop="shareAlbum(album)"
                  :title="$t('shares.share_album')"
                >
                  <i class="bi bi-share text-xs text-gray"></i>
                </button>
                <!-- Add to playlist button (bottom right) -->
                <button
                  class="absolute bottom-8 right-2 w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                  @click.stop="showAddAlbumToPlaylistModal(album)"
                  :title="$t('songs.add_to_playlist')"
                >
                  <i class="bi bi-music-note-list text-xs text-gray"></i>
                </button>
              </div>
              <!-- Album details -->
              <div class="p-2">
                <p
                  class="text-center font-bold mb-1 text-audinary text-lg truncate"
                >
                  {{ album.albumName }}
                </p>
                <p class="text-center text-white/80 text-sm truncate mb-1">
                  {{ album.albumArtist }}
                </p>
                <p class="text-center text-white/80 text-sm truncate">
                  {{ album.albumYear }}
                </p>
              </div>
            </div>
          </div>
        </template>

        <!-- List View Albums -->
        <template v-else>
          <div
            v-for="album in albums"
            :key="album.album_id"
            class="group cursor-pointer"
            @click="showAlbumDetail(album)"
          >
            <div
              class="bg-white/10 backdrop-blur-lg rounded drop-shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/20"
            >
              <div class="flex items-center">
                <!-- Album cover (left side) -->
                <div class="relative w-20 h-20 flex-shrink-0">
                  <!-- Gradient placeholder for list view -->
                  <div
                    v-if="album.coverGradient && album.coverGradient.colors"
                    class="absolute inset-0 rounded-l-lg"
                    :style="{
                      background: `linear-gradient(${album.coverGradient.angle || 135}deg, ${album.coverGradient.colors.join(', ')})`,
                      filter: 'blur(10px)',
                      transform: 'scale(1.05)',
                      zIndex: 1,
                    }"
                  ></div>

                  <SimpleImage
                    :imageType="'album'"
                    :imageId="album.album_id || 'default'"
                    :alt="album.albumName"
                    class="w-20 h-20 object-cover rounded-l-lg relative z-[2]"
                    :placeholder="'disc'"
                    :placeholderSize="'40px'"
                  />
                  <!-- Play button overlay (appears on card hover, icon white -> hover orange) -->
                  <div
                    class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 cursor-pointer rounded-l-lg z-3"
                    @click.stop="playAlbum(album)"
                    role="button"
                    tabindex="0"
                  >
                    <i
                      class="bi bi-play-circle-fill text-3xl text-white transition-colors hover:text-orange-400"
                    ></i>
                  </div>
                </div>

                <!-- Album details (center) -->
                <div class="flex-1 min-w-0 px-4 py-2">
                  <div class="flex justify-between items-center">
                    <div class="flex-1 min-w-0">
                      <h5 class="text-lg text-audinary font-bold mb-0 truncate">
                        {{ album.albumName }}
                      </h5>
                      <p class="text-white/80 mb-0 text-sm truncate">
                        {{ album.albumArtist }}
                      </p>
                      <div
                        class="flex items-center gap-3 text-sm text-white/80"
                      >
                        <span>{{ album.albumYear }}</span>
                        <span>{{ album.albumGenre || "--" }}</span>
                        <span
                          >{{ album.albumTracks || "--" }}
                          {{ $t("albums.tracks") }}</span
                        >
                      </div>
                    </div>

                    <div class="flex gap-1">
                      <button
                        class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                        @click.stop="playAlbum(album)"
                        :title="$t('player.play')"
                      >
                        <i class="bi bi-play-fill text-xs text-gray"></i>
                      </button>
                      <button
                        class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                        @click.stop="addToQueue(album)"
                        :title="$t('songs.add-to-queue')"
                      >
                        <i class="bi bi-list text-xs text-gray"></i>
                      </button>
                      <button
                        v-if="canCreateShare"
                        class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                        @click.stop="shareAlbum(album)"
                        :title="$t('shares.share_album')"
                      >
                        <i class="bi bi-share text-xs text-gray"></i>
                      </button>
                      <button
                        class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                        @click.stop="showAddAlbumToPlaylistModal(album)"
                        :title="$t('songs.add_to_playlist')"
                      >
                        <i class="bi bi-music-note-list text-xs text-gray"></i>
                      </button>
                      <button
                        class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                        @click.stop="toggleAlbumFavorite(album)"
                        :title="$t('songs.favorite')"
                      >
                        <i
                          class="bi text-xs"
                          :class="
                            album.albumIsFavorite
                              ? 'bi-heart-fill text-red-400'
                              : 'bi-heart text-gray'
                          "
                        ></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Loading State -->
      <div v-else-if="loading" class="text-center py-8">
        <div
          class="animate-spin w-8 h-8 border-4 border-gray-600 border-t-audinary rounded-full mx-auto"
        >
          <span class="sr-only">{{ $t("common.loading") }}</span>
        </div>
        <p class="mt-3 text-gray-400">{{ $t("common.loading") }}...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="!loading && albums.length === 0" class="text-center py-8">
        <i class="bi bi-disc text-5xl text-gray-400"></i>
        <h4 class="mt-3 text-gray-400 text-xl">{{ $t("library.noAlbums") }}</h4>
        <p class="text-gray-400">{{ $t("library.noAlbumsDescription") }}</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-8">
        <i class="bi bi-exclamation-triangle text-5xl text-red-500"></i>
        <h4 class="mt-3 text-red-500 text-xl">{{ $t("common.error") }}</h4>
        <p class="text-gray-400">{{ error }}</p>
        <button
          class="px-4 py-2 border border-green-600/90 text-green-500 hover:bg-green-600/90 hover:text-white rounded-lg transition-colors mobile-touch-target"
          @click="loadAlbums"
        >
          {{ $t("common.retry") }}
        </button>
      </div>

      <!-- Load More Trigger (invisible) -->
      <div
        v-if="hasMore && !loading && albums.length > 0"
        ref="loadMoreTrigger"
        class="flex justify-center items-center py-3"
        style="min-height: 100px"
      >
        <div
          class="animate-spin w-8 h-8 border-4 border-gray-600 border-t-audinary rounded-full"
        >
          <span class="sr-only">{{ $t("common.loading") }}</span>
        </div>
      </div>

      <!-- Album Detail Modal -->
      <AlbumDetailModal
        :album="selectedAlbum"
        ref="albumDetailModal"
        @close="closeAlbumDetail"
        @album-updated="handleAlbumUpdated"
      />

      <!-- Add to Playlist Modal -->
      <PlaylistAddToModal
        :isVisible="showPlaylistModal"
        :selectedTracks="albumTracksForPlaylist"
        :albumTitle="selectedAlbumForPlaylist?.albumName"
        @close="closePlaylistModal"
        @added="handlePlaylistAdded"
      />

      <!-- Create Share Modal -->
      <PublicSharesCreateModal
        v-if="showShareModal"
        type="album"
        :item-id="selectedAlbumForShare?.album_id"
        :item-data="selectedAlbumForShare"
        @close="closeShareModal"
        @share-created="onShareCreated"
      />
    </div>
  </div>
</template>

<script>
import { ref, onMounted, watch, computed, onUnmounted, nextTick } from "vue";
import { useI18n } from "vue-i18n";
import { usePlayerStore } from "@/stores/player";
import { useApiStore } from "@/stores/api";
import { useAlertStore } from "@/stores/alert";
import { useAuthStore } from "@/stores/auth";
import ContentHeader from "@/components/common/ContentHeader.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";
import AlbumDetailModal from "@/components/modals/AlbumDetailModal.vue";
import PlaylistAddToModal from "@/components/modals/PlaylistAddToModal.vue";
import PublicSharesCreateModal from "@/components/modals/PublicSharesCreateModal.vue";
import { getCdCaseImage } from "@/utils/cdCases.js";

export default {
  name: "AlbumsComponent",
  components: {
    ContentHeader,
    SimpleImage,
    AlbumDetailModal,
    PlaylistAddToModal,
    PublicSharesCreateModal,
  },
  props: {
    filteredByArtist: {
      type: String,
      default: null,
    },
    filteredByGenre: {
      type: String,
      default: null,
    },
  },
  emits: ["go-back"],
  setup(props, { emit }) {
    const { t } = useI18n();
    const playerStore = usePlayerStore();
    const apiStore = useApiStore();
    const alertStore = useAlertStore();
    const authStore = useAuthStore();

    const albums = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const showFavoritesOnly = ref(false);
    const currentFilter = ref("all-artist");
    const viewMode = ref("grid");
    const hasMore = ref(true);
    const currentPage = ref(0);
    const pageSize = 50;
    const selectedAlbum = ref(null);
    const albumDetailModal = ref(null);
    const activeFilters = ref({
      sort: "artistAndAlbum",
      sortDirection: "asc",
      genre: "",
      decade: "",
    });
    const showFilters = ref(false);
    const availableGenres = ref([]);
    const availableDecades = ref([]);
    const loadMoreTrigger = ref(null);
    const searchQuery = ref("");
    const searchTimeout = ref(null);
    const showPlaylistModal = ref(false);
    const selectedAlbumForPlaylist = ref(null);
    const albumTracksForPlaylist = ref([]);

    // Share modal state
    const showShareModal = ref(false);
    const selectedAlbumForShare = ref(null);

    // Mobile detection
    const isMobile = ref(window.innerWidth <= 845);

    // Handle window resize for mobile detection
    const handleResize = () => {
      isMobile.value = window.innerWidth <= 845;
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
      if (searchQuery.value) count++;
      return count;
    });

    const loadAlbums = async (reset = true) => {
      try {
        loading.value = true;
        error.value = null;

        if (reset) {
          currentPage.value = 0;
        }

        let response;

        // Use specialized genre method if filtered by genre
        if (props.filteredByGenre) {
          response = await apiStore.loadAlbumsChunk({
            start: currentPage.value * pageSize,
            limit: pageSize,
            genre: props.filteredByGenre,
          });
        }
        // Use specialized artist method or regular chunk method
        else {
          const params = {
            start: currentPage.value * pageSize,
            limit: pageSize,
            favorite: showFavoritesOnly.value ? 1 : 0,
            filter: currentFilter.value,
            ...activeFilters.value,
          };

          // Add search query if present
          if (searchQuery.value.trim()) {
            params.search = searchQuery.value.trim();
          }

          // Add artist filter if filtered by artist
          if (props.filteredByArtist) {
            params.artist = props.filteredByArtist;
          }

          response = await apiStore.loadAlbumsChunk(params);
        }

        const newAlbums = response.data || response || [];

        if (reset) {
          albums.value = newAlbums;
        } else {
          // Add new items
          albums.value.push(...newAlbums);
        }

        hasMore.value = newAlbums.length === pageSize;
      } catch (err) {
        console.error("AlbumsComponent error loading albums:", err);
        error.value = err.message || "Failed to load albums";
      } finally {
        loading.value = false;
      }
    };

    const loadMore = async () => {
      currentPage.value++;
      await loadAlbums(false);
    };

    const toggleFavorites = () => {
      showFavoritesOnly.value = !showFavoritesOnly.value;
      loadAlbums(true);
    };

    const setFilter = (filter) => {
      currentFilter.value = filter;
      if (filter === "favorites") {
        showFavoritesOnly.value = true;
      }
      loadAlbums(true);
    };

    const setViewMode = (mode) => {
      viewMode.value = mode;
    };

    const formatDuration = (seconds) => {
      if (!seconds) return "--:--";
      const hours = Math.floor(seconds / 3600);
      const mins = Math.floor((seconds % 3600) / 60);
      if (hours > 0) {
        return `${hours}:${mins.toString().padStart(2, "0")}h`;
      }
      return `${mins}min`;
    };

    const playAlbum = (album) => {
      playerStore.playAlbum(album.album_id);
    };

    const showAlbumDetail = (album) => {
      selectedAlbum.value = album;
      if (albumDetailModal.value) {
        albumDetailModal.value.show();
      }
    };

    const closeAlbumDetail = () => {
      selectedAlbum.value = null;
    };

    const handleAlbumUpdated = (updatedAlbum) => {
      // Update the album in the list
      const index = albums.value.findIndex(
        (a) => a.album_id === updatedAlbum.album_id,
      );
      if (index !== -1) {
        albums.value[index] = updatedAlbum;
      }
    };

    const toggleAlbumFavorite = async (album) => {
      try {
        await apiStore.toggleFavorite({
          type: "album",
          itemId: album.album_id,
          currentlyFav: album.albumIsFavorite,
        });
        album.albumIsFavorite = !album.albumIsFavorite;
      } catch (error) {
        console.error("Error toggling album favorite:", error);
      }
    };

    const goBack = () => {
      emit("go-back");
    };

    const toggleFilters = () => {
      showFilters.value = !showFilters.value;
    };

    const onFiltersChanged = () => {
      loadAlbums(true);
    };

    const clearFilters = () => {
      activeFilters.value = {
        sort: "artistAndAlbum",
        sortDirection: "asc",
        genre: "",
        decade: "",
      };
      loadAlbums(true);
    };

    const toggleSortDirection = () => {
      activeFilters.value.sortDirection =
        activeFilters.value.sortDirection === "asc" ? "desc" : "asc";
      onFiltersChanged();
    };

    const onSearchInput = () => {
      // Debounce search input
      if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
      }

      searchTimeout.value = setTimeout(() => {
        loadAlbums(true);
      }, 300); // 300ms debounce
    };

    const clearSearch = () => {
      searchQuery.value = "";
      loadAlbums(true);
    };

    const clearAll = () => {
      searchQuery.value = "";
      activeFilters.value = {
        sort: "artistAndAlbum",
        sortDirection: "asc",
        genre: "",
        decade: "",
      };
      loadAlbums(true);
    };
    const addToQueue = async (album) => {
      try {
        const albumResponse = await apiStore.loadAlbumSongs(album.album_id);
        const songs =
          albumResponse.tracks || albumResponse.data || albumResponse || [];

        if (songs && songs.length > 0) {
          songs.forEach((song) => {
            playerStore.addToQueue(song);
          });
          alertStore.success(t("songs.addedToQueue", { count: songs.length }));
        }
      } catch (error) {
        console.error("Error adding album to queue:", error);
        alertStore.error(t("songs.errorAddingToQueue"));
      }
    };

    const showAddAlbumToPlaylistModal = async (album) => {
      try {
        selectedAlbumForPlaylist.value = album;

        // Load album tracks
        const albumResponse = await apiStore.loadAlbumSongs(album.album_id);
        albumTracksForPlaylist.value =
          albumResponse.tracks || albumResponse.data || albumResponse || [];

        showPlaylistModal.value = true;
      } catch (error) {
        console.error("Error loading album tracks for playlist:", error);
        alertStore.error(t("songs.errorLoadingAlbum"));
      }
    };

    const closePlaylistModal = () => {
      showPlaylistModal.value = false;
      selectedAlbumForPlaylist.value = null;
      albumTracksForPlaylist.value = [];
    };

    const handlePlaylistAdded = (result) => {
      alertStore.success(
        t("songs.addedToPlaylist", { playlist: result.playlist.name }),
      );
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
      [hasMore, () => albums.value.length],
      () => {
        if (hasMore.value && albums.value.length > 0) {
          setupIntersectionObserver();
        }
      },
      { flush: "post" },
    );

    // Watch for filter changes
    watch([() => props.filteredByArtist, () => props.filteredByGenre], () => {
      loadAlbums(true);
    });

    const playAllRandom = async () => {
      if (albums.value.length === 0) {
        alertStore.warning(t("albums.noAlbumsToPlay"));
        return;
      }

      try {
        loading.value = true;
        alertStore.info(t("albums.loadingSongsForRandom"));

        // Take up to 250 albums from current view
        const albumsToPlay = albums.value.slice(0, 250);
        const allSongs = [];

        // Load songs for each album
        for (const album of albumsToPlay) {
          try {
            const albumResponse = await apiStore.loadAlbumSongs(album.album_id);

            // Handle the response format - use the same pattern as AlbumDetailModal
            const songs =
              albumResponse.tracks || albumResponse.data || albumResponse || [];

            if (songs && songs.length > 0) {
              allSongs.push(...songs);
            }
          } catch (error) {
            console.error(
              `Error loading songs for album ${album.album_id}:`,
              error,
            );
            // Continue with other albums even if one fails
          }

          // Limit total songs to prevent memory issues
          if (allSongs.length >= 2000) {
            break;
          }
        }

        if (allSongs.length === 0) {
          alertStore.warning(t("albums.noSongsFound"));
          return;
        }

        // Shuffle the songs array
        const shuffledSongs = [...allSongs].sort(() => Math.random() - 0.5);

        // Limit to 250 songs for performance
        const songsToQueue = shuffledSongs.slice(0, 250);

        // Clear current queue and add shuffled songs
        playerStore.clearQueue();

        // Play first song and add rest to queue
        if (songsToQueue.length > 0) {
          playerStore.playSong(songsToQueue[0]);

          // Add remaining songs to queue
          for (let i = 1; i < songsToQueue.length; i++) {
            playerStore.addToQueue(songsToQueue[i]);
          }

          const count = songsToQueue.length;
          alertStore.success(t("albums.playingRandomSongs", { count }));
        }
      } catch (error) {
        console.error("Error playing random albums:", error);
        alertStore.error(t("albums.errorPlayingRandom"));
      } finally {
        loading.value = false;
      }
    };

    // Public share functionality
    const canCreateShare = computed(() => {
      return authStore.isAdmin || authStore.user?.can_create_public_share;
    });

    function shareAlbum(album) {
      selectedAlbumForShare.value = album;
      showShareModal.value = true;
    }

    function closeShareModal() {
      showShareModal.value = false;
      selectedAlbumForShare.value = null;
    }

    function onShareCreated() {
      closeShareModal();
    }

    // Lifecycle
    onMounted(async () => {
      await loadAlbums(true);
      await loadGenres();
      await loadDecades();

      // Add resize listener for mobile detection
      window.addEventListener("resize", handleResize);
    });

    onUnmounted(() => {
      // Cleanup observer
      if (loadMoreTrigger.value?._observer) {
        loadMoreTrigger.value._observer.disconnect();
      }

      // Cleanup search timeout
      if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
      }

      // Remove resize listener
      window.removeEventListener("resize", handleResize);
    });

    return {
      albums,
      loading,
      error,
      showFavoritesOnly,
      currentFilter,
      viewMode,
      hasMore,
      selectedAlbum,
      albumDetailModal,
      loadMoreTrigger,
      loadAlbums,
      loadMore,
      toggleFavorites,
      setFilter,
      setViewMode,
      formatDuration,
      playAlbum,
      showAlbumDetail,
      addToQueue,
      showAddAlbumToPlaylistModal,
      closeAlbumDetail,
      handleAlbumUpdated,
      toggleAlbumFavorite,
      goBack,
      t,
      showFilters,
      activeFilters,
      activeFiltersCount,
      availableGenres,
      availableDecades,
      toggleFilters,
      onFiltersChanged,
      clearFilters,
      hasActiveFilters,
      toggleSortDirection,
      searchQuery,
      onSearchInput,
      clearSearch,
      clearAll,
      isMobile,
      playAllRandom,
      getCdCaseImage,
      showPlaylistModal,
      selectedAlbumForPlaylist,
      albumTracksForPlaylist,
      closePlaylistModal,
      handlePlaylistAdded,
      canCreateShare,
      showShareModal,
      selectedAlbumForShare,
      shareAlbum,
      closeShareModal,
      onShareCreated,
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
  contain-intrinsic-size: 400px;

  /* Isolate this element's style recalculations */
  contain: layout style paint;

  /* Hint to browser that transforms will be animated */
  will-change: transform;
}

</style>
