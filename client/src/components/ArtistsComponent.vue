<template>
  <div class="flex flex-col h-full">
    <ContentHeader
      :title="$t('nav.artists')"
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
            showFavoritesOnly ? $t("common.favorites") : $t("common.favorites")
          }}</span>
        </button>

        <!-- Play All Random Button -->
        <button
          class="inline-flex items-center gap-2 px-3 py-1 border border-green-600/90 text-white hover:bg-green-700 rounded-lg"
          @click="playAllRandom"
          :disabled="artists.length === 0 || loading"
          :title="$t('artists.playAllRandom')"
        >
          <i class="bi bi-shuffle"></i>
          <span class="hidden md:inline">{{ $t("artists.playAll") }}</span>
        </button>
      </template>

      <template #filters>
        <div
          class="bg-white/10 text-white shadow-lg rounded-lg p-4 mb-3 shadow-lg"
        >
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <h6 class="text-sm font-medium">
                {{ $t("common.sortBy") }}
              </h6>
              <div class="flex gap-2 mt-2 items-center">
                <select
                  v-model="activeFilters.sort"
                  class=""
                  @change="onFiltersChanged"
                >
                  <option value="name">{{ $t("common.name") }}</option>
                  <option value="year">
                    {{ $t("albums.filter.year") }}
                  </option>
                  <option value="albumCount">
                    {{ $t("artists.filter.albumCount") }}
                  </option>
                  <option value="songCount">
                    {{ $t("artists.filter.songCount") }}
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
                class="w-full"
                @change="onFiltersChanged"
              >
                <option value="">{{ $t("common.all") }}</option>
                <option
                  v-for="genre in availableGenres"
                  :key="genre.name"
                  :value="genre.name"
                >
                  {{ genre.name }} ({{ genre.artist_count }})
                </option>
              </select>
            </div>

            <div>
              <h6 class="text-sm font-medium">{{ $t("nav.decades") }}</h6>
              <select
                v-model="activeFilters.decade"
                class="w-full"
                @change="onFiltersChanged"
              >
                <option value="">{{ $t("common.all") }}</option>
                <option
                  v-for="decade in availableDecades"
                  :key="decade.start_year"
                  :value="decade.start_year"
                >
                  {{ decade.decade }} ({{ decade.artist_count }})
                </option>
              </select>
            </div>
          </div>

          <!-- Quick Actions -->
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

    <!-- Artists Content Area (scrollable) -->
    <div class="flex-1 overflow-y-auto py-4">
      <!-- Artists Grid/List Container -->
      <div
        v-if="artists.length > 0"
        :class="viewMode === 'grid' ? 'grid gap-4' : 'space-y-2'"
        :style="
          viewMode === 'grid'
            ? 'grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));'
            : ''
        "
      >
        <template v-if="viewMode === 'grid'">
          <div
            v-for="artist in artists"
            :key="artist.artist_id"
            class="group cursor-pointer"
            @click="viewArtistAlbums(artist)"
          >
            <div
              class="bg-white/10 backdrop-blur-lg rounded shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/20"
            >
              <div class="relative overflow-hidden mx-auto aspect-square">
                <!-- Artist image with frame overlay -->
                <div class="relative w-full h-full" v-if="artist.artist_id">
                  <!-- Gradient placeholder background -->
                  <div
                    v-if="artist.artistGradient && artist.artistGradient.colors"
                    class="absolute top-[10%] left-[10%] w-[80%]"
                    :style="{
                      height: '80%',
                      background: `linear-gradient(${artist.artistGradient.angle || 135}deg, ${artist.artistGradient.colors.join(', ')})`,
                      filter: 'blur(10px)',
                      zIndex: 1,
                    }"
                  ></div>
                  <SimpleImage
                    :imageType="'artist'"
                    :imageId="artist.artist_id"
                    :alt="artist.artistName"
                    class="absolute top-[10%] left-[10%] w-[80%] z-[2]"
                    :placeholder="'person-circle'"
                    :placeholderSize="'200px'"
                    loading="lazy"
                  />
                  <img
                    src="/img/artist_frame.png"
                    class="relative z-[2] w-full h-auto pointer-events-none"
                    alt="Artist Frame"
                    @error="imageError"
                  />
                </div>
                <div
                  v-else
                  class="w-full h-full flex items-center justify-center bg-gray-600"
                >
                  <i class="bi bi-person-circle text-white text-6xl"></i>
                </div>
                <!-- Play overlay on hover -->
                <div
                  class="absolute top-[44%] left-1/2 -translate-x-1/2 -translate-y-1/2 w-12 h-12 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center cursor-pointer z-[10]"
                  @click.stop="playArtist(artist)"
                >
                  <i
                    class="bi bi-play-circle-fill text-5xl text-white transition-colors hover:text-audinary drop-shadow-lg"
                  ></i>
                </div>
                <!-- Favorite icon (top right) -->
                <button
                  class="absolute top-2 right-2 w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 z-[10]"
                  @click.stop="toggleArtistFavorite(artist)"
                  :title="$t('common.favorite')"
                >
                  <i
                    class="bi text-xs"
                    :class="
                      artist.is_favorite
                        ? 'bi-heart-fill text-red-400'
                        : 'bi-heart text-white'
                    "
                  ></i>
                </button>
              </div>
              <!-- Artist details -->
              <div class="p-2">
                <p
                  class="text-center font-bold mb-1 text-audinary text-lg truncate"
                >
                  {{ artist.artistName }}
                </p>
                <p class="text-center text-white/80 text-sm truncate mb-1">
                  {{ artist.albumCount || 0 }} {{ $t("nav.albums") }}
                </p>
                <p class="text-center text-white/80 text-sm truncate">
                  {{ artist.songCount || 0 }} {{ $t("nav.songs") }}
                </p>
              </div>
            </div>
          </div>
        </template>

        <!-- List View Artists -->
        <template v-else>
          <div
            v-for="artist in artists"
            :key="artist.artist_id"
            class="group cursor-pointer"
            @click="viewArtistAlbums(artist)"
          >
            <div
              class="bg-white/10 backdrop-blur-lg rounded shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/20"
            >
              <div class="flex items-center">
                <!-- Artist image (left side) -->
                <div class="relative w-20 h-20 flex-shrink-0">
                  <!-- Gradient placeholder background -->
                  <div
                    v-if="artist.artistGradient && artist.artistGradient.colors"
                    class="absolute inset-0"
                    :style="{
                      background: `linear-gradient(${artist.artistGradient.angle || 135}deg, ${artist.artistGradient.colors.join(', ')})`,
                      filter: 'blur(10px)',
                      zIndex: 1,
                    }"
                  ></div>
                  <SimpleImage
                    :imageType="'artist'"
                    :imageId="artist.artist_id || 'default'"
                    :alt="artist.artistName"
                    class="w-20 h-20 object-cover rounded-l-lg relative z-[2]"
                    :placeholder="'person-circle'"
                    :placeholderSize="'40px'"
                  />
                  <!-- Play button overlay (appears on hover) -->
                  <div
                    class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 cursor-pointer rounded-l-lg z-3"
                    @click.stop="playArtist(artist)"
                    role="button"
                    tabindex="0"
                  >
                    <i
                      class="bi bi-play-circle-fill text-3xl text-white transition-colors hover:text-orange-400"
                    ></i>
                  </div>
                </div>
                <!-- Artist details (center) -->
                <div class="flex-1 min-w-0 px-4 py-2">
                  <div class="flex justify-between items-center">
                    <div class="flex-1 min-w-0">
                      <div class="p-2">
                        <p
                          class="font-bold mb-1 text-audinary text-lg truncate"
                        >
                          {{ artist.artistName }}
                        </p>
                        <p class="text-white/80 text-sm truncate mb-1">
                          {{ artist.albumCount || 0 }} {{ $t("nav.albums") }}
                        </p>
                        <p class="text-white/80 text-sm truncate">
                          {{ artist.songCount || 0 }} {{ $t("nav.songs") }}
                        </p>
                      </div>
                    </div>
                    <!-- Actions (right side) -->
                    <div class="flex gap-1 flex-shrink-0">
                      <button
                        class="w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                        @click.stop="playArtist(artist)"
                        :title="$t('player.play')"
                      >
                        <i class="bi bi-play-fill text-xs text-white"></i>
                      </button>
                      <button
                        class="w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                        @click.stop="toggleArtistFavorite(artist)"
                        :title="$t('common.favorite')"
                      >
                        <i
                          class="bi text-xs"
                          :class="
                            artist.is_favorite
                              ? 'bi-heart-fill text-red-400'
                              : 'bi-heart text-white'
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
      <div
        v-else-if="!loading && artists.length === 0"
        class="text-center py-8"
      >
        <i class="bi bi-person-circle text-5xl text-gray-400"></i>
        <h4 class="mt-3 text-gray-400 text-xl">
          {{ $t("library.noArtists") }}
        </h4>
        <p class="text-gray-400">{{ $t("library.noArtistsDescription") }}</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-8">
        <i class="bi bi-exclamation-triangle text-5xl text-red-500"></i>
        <h4 class="mt-3 text-red-500 text-xl">{{ $t("common.error") }}</h4>
        <p class="text-gray-400">{{ error }}</p>
        <button
          class="px-4 py-2 border border-green-600/90 text-green-500 hover:bg-green-600/90 hover:text-white rounded-lg transition-colors mobile-touch-target"
          @click="loadArtists"
        >
          {{ $t("common.retry") }}
        </button>
      </div>

      <!-- Load More Trigger (invisible) -->
      <div
        v-if="hasMore && !loading && artists.length > 0"
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
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed, nextTick, watch, onUnmounted } from "vue";
import { useI18n } from "vue-i18n";
import { usePlayerStore } from "@/stores/player";
import { useApiStore } from "@/stores/api";
import { useAlertStore } from "@/stores/alert";
import ContentHeader from "@/components/common/ContentHeader.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";

export default {
  name: "ArtistsComponent",
  components: {
    ContentHeader,
    SimpleImage,
  },
  emits: ["show-albums-by-artist"],
  setup(props, { emit }) {
    const { t } = useI18n();
    const playerStore = usePlayerStore();
    const apiStore = useApiStore();
    const alertStore = useAlertStore();

    const artists = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const showFavoritesOnly = ref(false);
    const currentFilter = ref("all-artist");
    const activeFilters = ref({
      sort: "name",
      sortDirection: "asc",
      genre: "",
      decade: "",
    });
    const hasMore = ref(true);
    const currentPage = ref(0);
    const pageSize = 50;
    const showFilters = ref(false);
    const viewMode = ref("grid");
    const availableGenres = ref([]);
    const availableDecades = ref([]);
    const loadMoreTrigger = ref(null);

    // Search functionality
    const searchQuery = ref("");
    const searchTimeout = ref(null);

    // Computed
    const hasActiveFilters = computed(() => {
      return (
        activeFilters.value.genre ||
        activeFilters.value.decade ||
        activeFilters.value.sort !== "name" ||
        activeFilters.value.sortDirection !== "asc"
      );
    });

    const activeFiltersCount = computed(() => {
      let count = 0;
      if (activeFilters.value.genre) count++;
      if (activeFilters.value.decade) count++;
      if (activeFilters.value.sort !== "name") count++;
      if (activeFilters.value.sortDirection !== "asc") count++;
      return count;
    });

    const loadArtists = async (reset = true) => {
      if (reset) {
        currentPage.value = 0;
        artists.value = [];
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

        const response = await apiStore.loadArtistsChunk(params);

        if (reset) {
          artists.value = response.data || response || [];
        } else {
          artists.value.push(...(response.data || response || []));
        }

        hasMore.value = (response.data || response || []).length === pageSize;
      } catch (err) {
        console.error("ArtistsComponent error loading artists:", err);
        error.value = err.message || "Failed to load artists";
      } finally {
        loading.value = false;
      }
    };

    const loadMore = async () => {
      currentPage.value++;
      await loadArtists(false);
    };

    const toggleFavorites = () => {
      showFavoritesOnly.value = !showFavoritesOnly.value;
      loadArtists(true);
    };

    const setFilter = (filter) => {
      currentFilter.value = filter;
      if (filter === "favorites") {
        showFavoritesOnly.value = true;
      }
      loadArtists(true);
    };

    const viewArtistAlbums = (artist) => {
      emit("show-albums-by-artist", artist.artistName);

      // Fallback: Use window event as backup
      window.dispatchEvent(
        new CustomEvent("show-albums-by-artist", {
          detail: { artist: artist.artistName },
        }),
      );
    };

    const playArtist = (artist) => {
      playerStore.playArtist(artist.artist_id);
    };

    const toggleArtistFavorite = async (artist) => {
      try {
        await apiStore.toggleFavorite({
          type: "artist",
          itemId: artist.artist_id,
          currentlyFav: artist.is_favorite,
        });
        artist.is_favorite = !artist.is_favorite;
      } catch (error) {
        console.error("Error toggling artist favorite:", error);
      }
    };

    const imageError = (event) => {
      // Hide broken image, show icon instead
      event.target.style.display = "none";
    };

    // Search functionality
    const onSearchInput = () => {
      if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
      }

      searchTimeout.value = setTimeout(() => {
        loadArtists(true);
      }, 300);
    };

    const clearSearch = () => {
      searchQuery.value = "";
      loadArtists(true);
    };

    const clearAll = () => {
      searchQuery.value = "";
      activeFilters.value = {
        sort: "name",
        sortDirection: "asc",
        genre: "",
        decade: "",
      };
      loadArtists(true);
    };

    const onFiltersChanged = () => {
      loadArtists(true);
    };

    const clearFilters = () => {
      activeFilters.value = {
        sort: "name",
        sortDirection: "asc",
        genre: "",
        decade: "",
      };
      loadArtists(true);
    };

    const toggleSortDirection = () => {
      activeFilters.value.sortDirection =
        activeFilters.value.sortDirection === "asc" ? "desc" : "asc";
      onFiltersChanged();
    };

    const toggleFilters = () => {
      showFilters.value = !showFilters.value;
    };

    const setViewMode = (mode) => {
      viewMode.value = mode;
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
      [hasMore, () => artists.value.length],
      () => {
        if (hasMore.value && artists.value.length > 0) {
          setupIntersectionObserver();
        }
      },
      { flush: "post" },
    );

    const playAllRandom = async () => {
      if (artists.value.length === 0) {
        alertStore.warning(t("artists.noArtistsToPlay"));
        return;
      }

      try {
        loading.value = true;
        alertStore.info(t("artists.loadingSongsForRandom"));

        // Take up to 250 artists from current view
        const artistsToPlay = artists.value.slice(0, 250);
        const allSongs = [];

        // Load songs for each artist
        for (const artist of artistsToPlay) {
          try {
            const artistSongs = await apiStore.loadArtistSongs(
              artist.artistName,
            );
            // Handle the response format - check if it's array directly
            let songs = [];
            if (Array.isArray(artistSongs)) {
              songs = artistSongs;
            } else if (artistSongs && Array.isArray(artistSongs.data)) {
              songs = artistSongs.data;
            }

            if (songs && songs.length > 0) {
              allSongs.push(...songs);
            }
          } catch (error) {
            console.error(
              `Error loading songs for artist ${artist.artistName}:`,
              error,
            );
            // Continue with other artists even if one fails
          }

          // Limit total songs to prevent memory issues
          if (allSongs.length >= 2000) {
            break;
          }
        }

        if (allSongs.length === 0) {
          alertStore.warning(t("artists.noSongsFound"));
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
          playerStore.addMultipleToQueue(songsToQueue.slice(1));

          const count = songsToQueue.length;
          alertStore.success(t("artists.playingRandomSongs", { count }));
        }
      } catch (error) {
        console.error("Error playing random artists:", error);
        alertStore.error(t("artists.errorPlayingRandom"));
      } finally {
        loading.value = false;
      }
    };

    onMounted(async () => {
      await loadArtists(true);
      await loadGenres();
      await loadDecades();
    });

    onUnmounted(() => {
      // Cleanup observer
      if (loadMoreTrigger.value?._observer) {
        loadMoreTrigger.value._observer.disconnect();
      }
    });

    return {
      artists,
      loading,
      error,
      showFavoritesOnly,
      currentFilter,
      hasMore,
      loadMoreTrigger,
      loadArtists,
      loadMore,
      toggleFavorites,
      setFilter,
      viewArtistAlbums,
      playArtist,
      toggleArtistFavorite,
      imageError,
      t,
      showFilters,
      viewMode,
      hasActiveFilters,
      activeFiltersCount,
      activeFilters,
      onFiltersChanged,
      clearFilters,
      toggleSortDirection,
      toggleFilters,
      setViewMode,
      availableGenres,
      availableDecades,
      // Search functionality
      searchQuery,
      onSearchInput,
      clearSearch,
      clearAll,
      playAllRandom,
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

/* Optimize hover effects */
.group:hover {
  /* Use transform instead of changing other properties for better performance */
  transform: translateZ(0);
}
</style>
