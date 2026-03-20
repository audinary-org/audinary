<template>
  <div class="dashboard-section">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-semibold text-audinary m-0">
        {{ $t("dashboard.recent_played_songs") }}
      </h3>
      <a
        href="#"
        class="text-white/80 hover:text-audinary transition-colors mobile-touch-target flex items-center text-sm"
        @click.prevent="showAll"
      >
        {{ $t("common.show_all") }} <i class="bi bi-chevron-right ml-1"></i>
      </a>
    </div>

    <div v-if="loading" class="flex justify-center py-8">
      <div
        class="animate-spin w-8 h-8 border-4 border-gray-600 border-t-gray-300 rounded-full"
      >
        <span class="sr-only">{{ $t("common.loading") }}</span>
      </div>
    </div>

    <div v-else-if="songs.length === 0" class="text-center py-8 text-white/80">
      <i class="bi bi-music-note-list text-5xl"></i>
      <p class="mt-2">{{ $t("songs.no_recent_played") }}</p>
    </div>

    <div v-else class="max-h-96 overflow-y-auto">
      <div
        v-for="(song, index) in songs"
        :key="song.song_id"
        class="flex items-center p-3 rounded-lg mb-2 cursor-pointer bg-white/10 backdrop-blur-lg rounded drop-shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/20"
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
            :alt="song.album"
            class="rounded relative z-[2]"
            style="width: 40px; height: 40px; object-fit: cover"
          />
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-medium text-audinary text-lg truncate">
            {{ song.title }}
          </div>
          <div class="text-white/80 text-xs truncate">
            {{ song.artist }} - {{ song.album }}
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

  <!-- Add to Playlist Modal -->
  <PlaylistAddToModal
    :isVisible="showPlaylistModal"
    :selectedTracks="selectedSongForPlaylist ? [selectedSongForPlaylist] : []"
    @close="closePlaylistModal"
    @added="handlePlaylistAdded"
  />
</template>

<script>
import { ref, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import { usePlayerStore } from "@/stores/player";
import { useApiStore } from "@/stores/api";
import { useAlertStore } from "@/stores/alert";
import { useAuthStore } from "@/stores/auth";
import SimpleImage from "@/components/common/SimpleImage.vue";
import PlaylistAddToModal from "@/components/modals/PlaylistAddToModal.vue";

export default {
  name: "RecentPlayedSongsSection",
  components: {
    SimpleImage,
    PlaylistAddToModal,
  },
  setup() {
    const { t } = useI18n();
    const router = useRouter();
    const playerStore = usePlayerStore();
    const apiStore = useApiStore();
    const alertStore = useAlertStore();
    const authStore = useAuthStore();

    const songs = ref([]);
    const loading = ref(true);
    const showPlaylistModal = ref(false);
    const selectedSongForPlaylist = ref(null);

    const loadRecentPlayedSongs = async () => {
      // Don't load data if not authenticated
      if (!authStore.isAuthenticated) {
        loading.value = false;
        return;
      }

      try {
        loading.value = true;
        // Use the new consolidated API method for recently played songs
        const response = await apiStore.loadRecentlyPlayedSongs(10);

        const songsData = Array.isArray(response)
          ? response
          : response.data || [];

        songs.value = songsData.map((song) => {
          const mappedSong = {
            song_id: song.song_id,
            album_id: song.album_id,
            title: song.title || song.songTitle,
            artist: song.artist || song.artistName,
            album: song.album || song.albumName,
            year: song.year || song.songYear,
            genre: song.genre || song.songGenre,
            duration: song.duration || song.songDuration,
            is_favorite: song.is_favorite || false,
            cover_image: song.cover_image,
            coverArtUrl: song.coverArtUrl,
          };

          // Set the correct cover URL for the player
          if (song.coverArtUrl) {
            mappedSong.coverArtUrl = song.coverArtUrl;
          } else if (song.album_id) {
            mappedSong.coverArtUrl = `/api/album-cover?albumId=${song.album_id}`;
          } else if (song.cover_image) {
            mappedSong.coverArtUrl = apiStore.getAssetUrl(
              `covers/${song.cover_image}.jpg`,
            );
          } else {
            mappedSong.coverArtUrl = "/img/placeholder_audinary.png";
          }

          return mappedSong;
        });
      } catch (error) {
        console.error("Error loading recent played songs:", error);
        // Fallback: use recent songs for now
        try {
          const response = await apiStore.loadSongsChunk({
            start: 0,
            limit: 10,
          });
          const songsData = Array.isArray(response)
            ? response
            : response.data || [];

          songs.value = songsData.slice(0, 10).map((song) => {
            const mappedSong = {
              song_id: song.song_id,
              album_id: song.album_id,
              title: song.title || song.songTitle,
              artist: song.artist || song.artistName,
              album: song.album || song.albumName,
              year: song.year || song.songYear,
              genre: song.genre || song.songGenre,
              duration: song.duration || song.songDuration,
              is_favorite: song.is_favorite || false,
              cover_image: song.cover_image,
              coverArtUrl: song.coverArtUrl,
            };

            // Set the correct cover URL for the player
            if (song.coverArtUrl) {
              mappedSong.coverArtUrl = song.coverArtUrl;
            } else if (song.album_id) {
              mappedSong.coverArtUrl = `/api/album-cover?albumId=${song.album_id}`;
            } else if (song.cover_image) {
              mappedSong.coverArtUrl = apiStore.getAssetUrl(
                `covers/${song.cover_image}.jpg`,
              );
            } else {
              mappedSong.coverArtUrl = "/img/placeholder_audinary.png";
            }

            return mappedSong;
          });
        } catch (fallbackError) {
          console.error("Error loading fallback songs:", fallbackError);
          songs.value = [];
        }
      } finally {
        loading.value = false;
      }
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
      alertStore.success(
        t("songs.addedToQueue", { song: song.songTitle || song.title }),
      );
    };

    const toggleSongFavorite = async (song) => {
      try {
        await apiStore.toggleFavorite({
          type: "song",
          itemId: song.song_id,
          currentlyFav: song.is_favorite,
        });
        if (song.is_favorite) {
          alertStore.success(
            t("songs.removedFromFavorites", { song: song.title }),
          );
        } else {
          alertStore.success(t("songs.addedToFavorites", { song: song.title }));
        }
        song.is_favorite = !song.is_favorite;
      } catch (error) {
        console.error("Error toggling song favorite:", error);
        alertStore.error(
          t("songs.errorTogglingFavorite", { song: song.title }),
        );
      }
    };

    const showAll = () => {
      router.push("/?tab=songs");
    };

    const showPlaylistAddToModal = (song) => {
      selectedSongForPlaylist.value = song;
      showPlaylistModal.value = true;
    };

    const closePlaylistModal = () => {
      showPlaylistModal.value = false;
      selectedSongForPlaylist.value = null;
    };

    const handlePlaylistAdded = (result) => {
      alertStore.success(
        t("songs.addedToPlaylist", { playlist: result.playlist.name }),
      );
    };

    onMounted(async () => {
      // Wait for authentication to be confirmed before loading data
      if (authStore.isAuthenticated) {
        await loadRecentPlayedSongs();
      } else {
        // Watch for authentication changes
        const unwatch = authStore.$subscribe(() => {
          if (authStore.isAuthenticated && songs.value.length === 0) {
            loadRecentPlayedSongs();
            unwatch(); // Stop watching once data is loaded
          }
        });
      }
    });

    return {
      songs,
      loading,
      formatDuration,
      playSong,
      addToQueue,
      toggleSongFavorite,
      showAll,
      showPlaylistAddToModal,
      closePlaylistModal,
      handlePlaylistAdded,
      showPlaylistModal,
      selectedSongForPlaylist,
      loadRecentPlayedSongs, // Expose for parent component refresh
      t,
    };
  },
};
</script>

<style scoped>
/* Custom scrollbar hiding for dashboard sections */
.mobile-touch-target {
  min-height: 44px;
  min-width: 44px;
}

/* Improved scrollbar for dark theme */
.max-h-96::-webkit-scrollbar {
  width: 6px;
}

.max-h-96::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.max-h-96::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
}

.max-h-96::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}
</style>
