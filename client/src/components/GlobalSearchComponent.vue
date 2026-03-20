<template>
  <div class="flex flex-col h-full">
    <!-- Sticky Header with Search (outside scrollable area) -->
    <div
      class="sticky top-0 z-10 bg-white/20 backdrop-blur-lg flex-shrink-0 rounded-2xl shadow-lg"
    >
      <div class="px-4">
        <div class="pt-3 pb-3">
          <div class="flex justify-between items-center flex-wrap gap-3">
            <h2 class="text-audinary text-2xl font-semibold">
              {{ $t("nav.search") }}
            </h2>

            <!-- Search Input (right aligned) -->
            <div class="flex flex-wrap gap-2 items-center">
              <div class="relative">
                <input
                  v-model="searchInput"
                  type="text"
                  :placeholder="$t('search.placeholder')"
                  class="search-input bg-white/20 text-white placeholder-white/70 rounded-lg px-3 py-2 pl-10 pr-10 focus:outline-none focus:ring-2 focus:ring-audinary"
                  style="min-width: 200px"
                  @input="onSearchInput"
                  @keyup.enter="performSearchFromInput"
                />
                <i
                  class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-white/60 pointer-events-none z-10"
                ></i>
                <button
                  v-if="searchInput"
                  @click="clearSearch"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-white/60 hover:text-white z-10"
                  type="button"
                  aria-label="Clear search"
                >
                  <i class="bi bi-x"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Content area (scrollable) -->
    <div class="flex-1 overflow-y-auto py-4">
      <div class="px-4">
        <!-- Loading State -->
        <div v-if="isLoading" class="text-center py-12">
          <div
            class="inline-block w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"
            role="status"
          >
            <span class="sr-only">{{ $t("search.loading") }}</span>
          </div>
          <p class="mt-4 text-gray-400">{{ $t("search.loading") }}</p>
        </div>

        <!-- No Results -->
        <div
          v-else-if="!hasAnyResults && searchQuery"
          class="text-center py-12"
        >
          <i class="bi bi-search text-6xl text-gray-400" />
          <h4 class="mt-4 text-xl text-gray-400">
            {{ $t("search.no_results") }}
          </h4>
          <p class="text-gray-400">{{ $t("search.try_different_term") }}</p>
        </div>

        <!-- Results -->
        <div v-else-if="searchQuery">
          <!-- Albums Section -->
          <div v-if="results.albums?.length > 0" class="search-section mb-8">
            <h3
              class="border-b border-gray-600 pb-3 mb-6 text-xl font-semibold text-audinary text-center"
            >
              {{ $t("nav.albums") }}
            </h3>
            <div class="flex gap-4 overflow-x-auto pb-2">
              <div
                v-for="album in results.albums"
                :key="album.album_id"
                class="flex-shrink-0 w-60 group bg-white/10 backdrop-blur-lg rounded drop-shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/20"
                @click="openAlbumDetail(album)"
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
                        :alt="album.albumName || album.album_name"
                        class="absolute top-[2%] left-[10%] w-[87%] h-auto z-[2] object-cover"
                        :placeholder="'disc'"
                        :placeholderSize="'80px'"
                        loading="lazy"
                      />
                      <img
                        :src="
                          getCdCaseImageLocal(album.albumFiletype || 'default')
                        "
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
                      class="absolute top-2 right-2 w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 z-[10]"
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
                      class="absolute bottom-8 left-6 w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                      @click.stop="addAlbumToQueue(album)"
                      :title="$t('songs.add-to-queue')"
                    >
                      <i class="bi bi-list text-xs text-white"></i>
                    </button>
                    <!-- Add to playlist button (bottom right) -->
                    <button
                      class="absolute bottom-8 right-2 w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 opacity-0 group-hover:opacity-100 z-[10]"
                      @click.stop="showAddAlbumToPlaylistModal(album)"
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
                    {{ album.albumName || album.album_name }}
                  </p>
                  <p class="text-center text-white/80 text-xs truncate mb-1">
                    {{ album.albumArtist || album.album_artist }}
                  </p>
                  <p class="text-center text-white/80 text-xs truncate">
                    {{ album.albumYear || album.album_year }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Artists Section -->
          <div v-if="results.artists?.length > 0" class="search-section mb-8">
            <h3
              class="border-b border-gray-600 pb-3 mb-6 text-xl font-semibold text-audinary text-center"
            >
              {{ $t("nav.artists") }}
            </h3>
            <div class="flex gap-4 overflow-x-auto pb-2">
              <div
                v-for="artist in results.artists"
                :key="artist.artist_id"
                class="flex-shrink-0 w-60 group bg-white/10 backdrop-blur-lg rounded drop-shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/20"
                @click="showArtist(artist)"
              >
                <div class="relative">
                  <div class="relative overflow-hidden mx-auto aspect-square">
                    <!-- Artist image with frame overlay -->
                    <div class="relative w-full h-full" v-if="artist.artist_id">
                      <!-- Gradient placeholder background -->
                      <div
                        v-if="
                          artist.artistGradient && artist.artistGradient.colors
                        "
                        class="absolute top-[10%] left-[10%] w-[80%]"
                        :style="{
                          height: '80%',
                          background: `linear-gradient(${artist.artistGradient.angle || 135}deg, ${artist.artistGradient.colors.join(', ')})`,
                          filter: 'blur(10px)',
                          zIndex: 1,
                        }"
                      ></div>
                      <SimpleImage
                        image-type="artist"
                        :image-id="artist.artist_id?.toString() || 'default'"
                        :alt="artist.artistName"
                        class="absolute inset-0 top-[10%] left-[10%] w-[80%] z-[2]"
                        :placeholder="'person-circle'"
                        :placeholderSize="'80px'"
                      />
                      <img
                        src="/img/artist_frame.png"
                        class="relative z-[2] w-full h-auto pointer-events-none"
                        alt="Artist Frame"
                      />
                    </div>
                    <div
                      v-else
                      class="flex items-center justify-center bg-gray-600 h-40"
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
                </div>
                <div class="p-2 pt-0">
                  <p
                    class="text-center font-semibold mb-1 text-audinary text-lg truncate"
                  >
                    {{ artist.artistName }}
                  </p>
                  <p class="text-center text-white/80 text-xs truncate">
                    {{ artist.album_count || 0 }} {{ $t("library.albums") }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Songs Section -->
          <div v-if="results.songs?.length > 0" class="search-section mb-8">
            <h3
              class="border-b border-gray-600 pb-3 mb-6 text-xl font-semibold text-audinary text-center"
            >
              {{ $t("nav.songs") }}
            </h3>
            <div class="max-h-96 overflow-y-auto">
              <div
                v-for="(song, index) in results.songs"
                :key="song.song_id"
                class="flex items-center p-3 rounded-lg mb-2 cursor-pointer bg-white/10 backdrop-blur-lg drop-shadow-lg transition-all duration-200 hover:bg-white/20"
                @click="playSong(song)"
              >
                <div class="w-8 text-center text-sm text-white/80 mr-3">
                  {{ index + 1 }}
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
                    image-type="album"
                    :image-id="song.album_id ? song.album_id.toString() : ''"
                    :alt="song.album || song.album_name"
                    class="rounded relative z-[2]"
                    style="width: 40px; height: 40px; object-fit: cover"
                  />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-audinary text-lg truncate">
                    {{ song.title }}
                  </div>
                  <div class="text-white/80 text-xs truncate">
                    {{ song.artist }} - {{ song.album || song.album_name }}
                  </div>
                  <div class="text-white/80 text-xs md:hidden">
                    {{ song.year || "--" }} • {{ song.genre || "--" }}
                  </div>
                </div>
                <div
                  class="text-white/80 mr-3 hidden md:block text-xs min-w-0 text-center"
                  style="width: 60px"
                >
                  {{ song.year || "--" }}
                </div>
                <div
                  class="text-white/80 mr-3 hidden lg:block text-xs min-w-0 truncate"
                  style="width: 100px"
                >
                  {{ song.genre || "--" }}
                </div>
                <div class="text-white/80 mr-3 text-xs" style="width: 50px">
                  {{ formatDuration(song.duration) }}
                </div>

                <div class="flex gap-1">
                  <button
                    class="w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                    @click.stop="playSong(song)"
                    :title="$t('player.play')"
                  >
                    <i class="bi bi-play-fill text-xs text-white"></i>
                  </button>
                  <button
                    class="w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                    @click.stop="addToQueue(song)"
                    :title="$t('songs.add-to-queue')"
                  >
                    <i class="bi bi-list text-xs text-white"></i>
                  </button>
                  <button
                    class="w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                    @click.stop="showPlaylistAddToModal(song)"
                    :title="$t('songs.add_to_playlist')"
                  >
                    <i class="bi bi-music-note-list text-xs text-white"></i>
                  </button>
                  <button
                    class="w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                    @click.stop="toggleFavorite(song)"
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

          <!-- Playlists Section -->
          <div v-if="results.playlists?.length > 0" class="search-section mb-8">
            <h3
              class="border-b border-gray-600 pb-3 mb-6 text-xl font-semibold text-audinary text-center"
            >
              {{ $t("nav.playlists") }}
            </h3>
            <div
              class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4"
            >
              <div
                v-for="playlist in results.playlists"
                :key="playlist.id"
                class="flex justify-center"
              >
                <div
                  class="bg-gray-800 text-white h-full w-full cursor-pointer border border-white/10 hover:bg-white/5 hover:shadow-xl transition-all duration-200 rounded-lg"
                  @click="playPlaylist(playlist)"
                >
                  <div
                    class="flex flex-col items-center justify-center p-6 h-32"
                  >
                    <i
                      class="bi bi-music-note-list text-4xl mb-2 text-gray-300"
                    />
                    <h5 class="text-center mb-1 font-medium text-white text-sm">
                      {{ playlist.name }}
                    </h5>
                    <small class="text-gray-400 text-xs"
                      >{{ playlist.song_count || 0 }}
                      {{ $t("nav.songs") }}</small
                    >
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Initial State -->
        <div v-else class="text-center py-12">
          <i class="bi bi-search text-6xl text-gray-400" />
          <h4 class="mt-4 text-xl text-gray-400">
            {{ $t("search.enter_term") }}
          </h4>
          <p class="text-gray-400">{{ $t("search.start_typing") }}</p>
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
        :selectedTracks="
          selectedTracksForPlaylist.length > 0
            ? selectedTracksForPlaylist
            : selectedAlbumTracks
        "
        :albumTitle="
          selectedAlbumForPlaylist?.albumName ||
          selectedAlbumForPlaylist?.album_name ||
          ''
        "
        @close="closePlaylistModal"
        @added="handlePlaylistAdded"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useSearchStore } from "@/stores/search";
import { usePlayerStore } from "@/stores/player";
import { useAlertStore } from "@/stores/alert";
import { useApiStore } from "@/stores/api";
import SimpleImage from "@/components/common/SimpleImage.vue";
import AlbumDetailModal from "@/components/modals/AlbumDetailModal.vue";
import PlaylistAddToModal from "@/components/modals/PlaylistAddToModal.vue";
import { getCdCaseImage } from "@/utils/cdCases.js";

// Local wrapper to ensure template can call the function reliably
const getCdCaseImageLocal = getCdCaseImage;

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const searchStore = useSearchStore();
const playerStore = usePlayerStore();
const alertStore = useAlertStore();
const apiStore = useApiStore();

// State
const searchInput = ref("");
const searchTimeout = ref(null);
const selectedAlbum = ref(null);
const albumDetailModal = ref(null);
const showPlaylistModal = ref(false);
const selectedTracksForPlaylist = ref([]);
const selectedAlbumForPlaylist = ref(null);
const selectedAlbumTracks = ref([]);

// Computed - use search store state
const isLoading = computed(() => searchStore.isLoading);
const searchQuery = computed(() => searchStore.lastQuery);
const results = computed(() => searchStore.results);

// Computed
const hasAnyResults = computed(() => {
  return (
    results.value.albums?.length > 0 ||
    results.value.artists?.length > 0 ||
    results.value.songs?.length > 0 ||
    results.value.playlists?.length > 0
  );
});

// Methods
async function performSearch(query) {
  if (!query || query.trim().length < 2) {
    searchStore.clearResults();
    return;
  }

  await searchStore.search(query.trim());
}

function formatDuration(seconds) {
  if (!seconds) return "--:--";
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${mins}:${secs.toString().padStart(2, "0")}`;
}

function playSong(song) {
  searchStore.playSong(song);
}

function playAlbum(album) {
  if (album.album_id) {
    playerStore.playAlbum(album.album_id);
  }
}

function playArtist(artist) {
  if (artist.artist_id) {
    playerStore.playArtist(artist.artist_id);
  }
}

function addToQueue(song) {
  searchStore.addSongToQueue(song);
}

function playPlaylist(playlist) {
  searchStore.playPlaylist(playlist);
}

async function toggleFavorite(song) {
  try {
    // Hier müssen wir den playerStore oder einen separaten API-Aufruf verwenden
    await playerStore.toggleFavorite({
      type: "song",
      itemId: song.song_id,
      currentlyFav: song.is_favorite,
    });
    song.is_favorite = !song.is_favorite;
  } catch (error) {
    console.error("Error toggling favorite:", error);
  }
}

function onSearchInput() {
  // Debounce search input - führe direkte Suche durch
  clearTimeout(searchTimeout.value);
  searchTimeout.value = setTimeout(() => {
    if (searchInput.value.trim()) {
      // Direkte Suche ohne URL-Änderung
      performSearch(searchInput.value.trim());
    } else {
      // Wenn leer, lösche Ergebnisse
      searchStore.clearResults();
    }
  }, 300);
}

function performSearchFromInput() {
  if (searchInput.value.trim()) {
    // Direkte Suche ohne Navigation, da wir bereits im Search-Tab sind
    performSearch(searchInput.value.trim());
  }
}

function clearSearch() {
  searchInput.value = "";
  searchStore.clearResults();
}

function showArtist(artist) {
  router.push({
    path: "/",
    query: {
      tab: "albums",
      artist: artist.artistName,
    },
  });
}

function openAlbumDetail(album) {
  selectedAlbum.value = album;
  if (albumDetailModal.value) {
    albumDetailModal.value.show();
  }
}

function closeAlbumDetail() {
  selectedAlbum.value = null;
}

function handleAlbumUpdated(updatedAlbum) {
  // Update album in search results if needed
  const albumIndex = results.value.albums?.findIndex(
    (a) => a.album_id === updatedAlbum.album_id,
  );
  if (albumIndex !== -1) {
    results.value.albums[albumIndex] = updatedAlbum;
  }
}

function showPlaylistAddToModal(song) {
  selectedTracksForPlaylist.value = [song];
  showPlaylistModal.value = true;
}

function closePlaylistModal() {
  showPlaylistModal.value = false;
  selectedTracksForPlaylist.value = [];
  selectedAlbumForPlaylist.value = null;
  selectedAlbumTracks.value = [];
}

function handlePlaylistAdded(result) {
  alertStore.success(
    t("songs.addedToPlaylist", { playlist: result.playlist.name }),
  );
}

async function toggleAlbumFavorite(album) {
  try {
    await playerStore.toggleFavorite({
      type: "album",
      itemId: album.album_id,
      currentlyFav: album.albumIsFavorite,
    });
    album.albumIsFavorite = !album.albumIsFavorite;
    alertStore.success(
      album.albumIsFavorite
        ? t("albums.addedToFavorites", {
            album: album.albumName || album.album_name,
          })
        : t("albums.removedFromFavorites", {
            album: album.albumName || album.album_name,
          }),
    );
  } catch (error) {
    console.error("Error toggling album favorite:", error);
    alertStore.error(t("albums.errorTogglingFavorite"));
  }
}

async function addAlbumToQueue(album) {
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
}

async function showAddAlbumToPlaylistModal(album) {
  try {
    selectedAlbumForPlaylist.value = album;
    selectedTracksForPlaylist.value = [];
    // Load album tracks for playlist modal
    const albumResponse = await apiStore.loadAlbumSongs(album.album_id);
    selectedAlbumTracks.value =
      albumResponse.tracks || albumResponse.data || albumResponse || [];
    showPlaylistModal.value = true;
  } catch (error) {
    console.error("Error loading album tracks for playlist:", error);
    alertStore.error(t("albums.errorLoadingTracks"));
  }
}

async function toggleArtistFavorite(artist) {
  try {
    await playerStore.toggleFavorite({
      type: "artist",
      itemId: artist.artist_id,
      currentlyFav: artist.is_favorite,
    });
    artist.is_favorite = !artist.is_favorite;
    alertStore.success(
      artist.is_favorite
        ? t("artists.addedToFavorites", { artist: artist.artistName })
        : t("artists.removedFromFavorites", { artist: artist.artistName }),
    );
  } catch (error) {
    console.error("Error toggling artist favorite:", error);
    alertStore.error(t("artists.errorTogglingFavorite"));
  }
}

// Watch for external search queries (falls noch von Navbar oder anderen Komponenten gesetzt)
watch(
  () => route.query.q,
  (newQuery) => {
    if (newQuery && route.query.tab === "search") {
      searchInput.value = newQuery;
      searchStore.setQuery(newQuery);
      performSearch(newQuery);
    }
  },
  { immediate: true },
);

// Initial load - prüfe ob externe Suchanfrage vorhanden
onMounted(() => {
  const query = route.query.q;
  const tab = route.query.tab;
  if (query && tab === "search") {
    searchInput.value = query;
    searchStore.setQuery(query);
    performSearch(query);
  }
});
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

.search-section {
  margin-bottom: 2rem;
}

.search-section h3 {
  color: rgba(255, 255, 255, 0.9);
  font-weight: 300;
}

.table th {
  border-top: none;
  color: rgba(255, 255, 255, 0.8);
  font-weight: 500;
}

.table td {
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  vertical-align: middle;
}
</style>
