<template>
  <div class="search-component relative">
    <div class="flex">
      <span
        class="flex items-center px-3 bg-white/20 border border-r-0 border-white/20 rounded-l-lg"
      >
        <i class="bi bi-search text-gray-400"></i>
      </span>
      <input
        v-model="searchQuery"
        type="text"
        class="flex-1 px-3 py-2 bg-white/20 text-white border border-white/20 rounded-r-lg focus:outline-none focus:ring-2 focus:ring-audinary"
        :placeholder="$t('nav.search')"
        @input="handleSearch"
        @focus="showDropdown = true"
        @blur="hideDropdown"
      />
    </div>

    <!-- Search Results Dropdown -->
    <div
      v-if="showDropdown && (hasResults || isSearching)"
      class="search-dropdown absolute w-full mt-1 z-50"
    >
      <div
        class="bg-white/20 backdrop-blur-lg border border-white/20 rounded-lg shadow-xl"
      >
        <div class="px-4 py-2 border-b border-white/20">
          <h6 class="text-sm font-medium text-gray-300 mb-0">
            {{ $t("search.results.dropdown_title") }}
          </h6>
        </div>
        <div class="p-0 max-h-96 overflow-y-auto">
          <!-- Loading -->
          <div v-if="isSearching" class="text-center py-3">
            <div
              class="inline-block w-4 h-4 border-2 border-audinary border-t-transparent rounded-full animate-spin"
              role="status"
            ></div>
            <small class="text-gray-400 ml-2">{{ $t("search.loading") }}</small>
          </div>

          <!-- Results -->
          <div v-else-if="hasResults">
            <!-- Songs -->
            <div v-if="searchResults.songs?.length > 0">
              <div
                class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider bg-white/10"
              >
                {{ $t("nav.songs") }}
              </div>
              <a
                v-for="song in searchResults.songs.slice(0, 5)"
                :key="`song-${song.song_id}`"
                href="#"
                class="flex items-center px-4 py-3 hover:bg-white/10 transition-colors cursor-pointer"
                @click="playSong(song)"
              >
                <i class="bi bi-music-note mr-3 text-audinary"></i>
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-white truncate">
                    {{ song.title }}
                  </div>
                  <small class="text-gray-400 truncate"
                    >{{ song.artist }} -
                    {{ song.album_name || song.album }}</small
                  >
                </div>
              </a>
            </div>

            <!-- Albums -->
            <div v-if="searchResults.albums?.length > 0">
              <div
                class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider bg-white/10"
              >
                {{ $t("nav.albums") }}
              </div>
              <a
                v-for="album in searchResults.albums.slice(0, 4)"
                :key="`album-${album.album_id}`"
                href="#"
                class="flex items-center px-4 py-3 hover:bg-white/10 transition-colors cursor-pointer"
                @click="playAlbum(album)"
              >
                <i class="bi bi-disc mr-3 text-green-500"></i>
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-white truncate">
                    {{ album.album_name }}
                  </div>
                  <small class="text-gray-400 truncate"
                    >{{ album.album_artist }} ({{
                      album.album_year || "--"
                    }})</small
                  >
                </div>
              </a>
            </div>

            <!-- Artists -->
            <div v-if="searchResults.artists?.length > 0">
              <div
                class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider bg-white/10"
              >
                {{ $t("nav.artists") }}
              </div>
              <a
                v-for="artist in searchResults.artists.slice(0, 4)"
                :key="`artist-${artist.artist_id}`"
                href="#"
                class="flex items-center px-4 py-3 hover:bg-white/10 transition-colors cursor-pointer"
                @click="showArtist(artist)"
              >
                <i class="bi bi-person mr-3 text-yellow-500"></i>
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-white truncate">
                    {{ artist.artistName }}
                  </div>
                  <small class="text-gray-400 truncate"
                    >{{ artist.album_count || 0 }} {{ $t("nav.albums") }}</small
                  >
                </div>
              </a>
            </div>

            <!-- Show All Results -->
            <div class="border-t border-white/20"></div>
            <a
              href="#"
              class="flex items-center justify-center px-4 py-3 hover:bg-white/10 transition-colors cursor-pointer text-audinary"
              @click="showAllResults"
            >
              {{ $t("search.show_all") }}
            </a>
          </div>

          <!-- No Results -->
          <div v-else class="text-center py-6">
            <i class="bi bi-search text-gray-400 text-2xl"></i>
            <p class="text-gray-400 mb-0 mt-2">{{ $t("search.no_results") }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useApiStore } from "@/stores/api";
import { usePlayerStore } from "@/stores/player";

export default {
  name: "SearchComponent",
  setup() {
    const router = useRouter();
    const { t } = useI18n();
    const apiStore = useApiStore();
    const playerStore = usePlayerStore();

    const searchQuery = ref("");
    const showDropdown = ref(false);
    const searchTimeout = ref(null);
    const isSearching = ref(false);
    const searchResults = ref({
      songs: [],
      albums: [],
      artists: [],
    });

    const hasResults = computed(() => {
      const results = searchResults.value;
      return (
        results.songs?.length > 0 ||
        results.albums?.length > 0 ||
        results.artists?.length > 0
      );
    });

    const handleSearch = async () => {
      if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
      }

      if (searchQuery.value.trim().length < 2) {
        searchResults.value = { songs: [], albums: [], artists: [] };
        return;
      }

      searchTimeout.value = setTimeout(async () => {
        isSearching.value = true;
        try {
          const results = await apiStore.search(searchQuery.value.trim(), 5);

          searchResults.value = {
            songs: results.songs || [],
            albums: results.albums || [],
            artists: results.artists || [],
          };
        } catch (error) {
          console.error("Search error:", error);
          searchResults.value = { songs: [], albums: [], artists: [] };
        } finally {
          isSearching.value = false;
        }
      }, 300);
    };

    const hideDropdown = () => {
      setTimeout(() => {
        showDropdown.value = false;
      }, 200);
    };

    const playSong = (song) => {
      playerStore.playSong(song);
      showDropdown.value = false;
      searchQuery.value = "";
    };

    const playAlbum = (album) => {
      playerStore.playAlbum(album.album_id);
      showDropdown.value = false;
      searchQuery.value = "";
    };

    const showArtist = (artist) => {
      router.push(`/?tab=artists&artist=${artist.artistName}`);
      showDropdown.value = false;
      searchQuery.value = "";
    };

    const showAllResults = () => {
      router.push(`/?tab=search&q=${encodeURIComponent(searchQuery.value)}`);
      showDropdown.value = false;
      searchQuery.value = "";
    };

    return {
      searchQuery,
      showDropdown,
      searchResults,
      isSearching,
      hasResults,
      handleSearch,
      hideDropdown,
      playSong,
      playAlbum,
      showArtist,
      showAllResults,
      t,
    };
  },
};
</script>

<style scoped>
.search-component {
  z-index: 1040;
}

.search-dropdown {
  z-index: 1050;
}

.form-control:focus {
  background-color: #2d3748;
  border-color: var(--bs-primary);
  color: white;
  box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
}

.input-group-text {
  color: rgba(255, 255, 255, 0.7);
}

.dropdown-item {
  color: rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
}

.dropdown-item:hover {
  background-color: rgba(255, 255, 255, 0.1);
  color: white;
}

.dropdown-header {
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 0.5rem 1rem 0.25rem;
}

.card {
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
</style>
