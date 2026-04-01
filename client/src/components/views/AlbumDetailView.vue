<template>
  <div class="h-full flex flex-col">
    <!-- Album Cover Background -->
    <div class="relative flex-shrink-0">
      <div class="relative h-48 md:h-64 overflow-hidden rounded-2xl mx-2 mt-2">
        <!-- Gradient placeholder background -->
        <div
          v-if="albumData?.coverGradient && albumData.coverGradient.colors"
          class="absolute inset-0"
          :style="{
            background: `linear-gradient(${albumData.coverGradient.angle || 135}deg, ${albumData.coverGradient.colors.join(', ')})`,
            filter: 'blur(10px)',
            zIndex: 1,
          }"
        ></div>
        <SimpleImage
          v-if="albumData?.album_id"
          :imageType="'album'"
          :imageId="albumData.album_id"
          :alt="albumTitle"
          class="w-full h-full object-cover relative z-[2]"
          :placeholder="'disc'"
          :placeholderSize="'500px'"
        />
        <!-- Dark Overlay for text readability -->
        <div
          class="absolute inset-0 bg-black/50 backdrop-blur-[2px] z-[3]"
        ></div>

        <!-- Content over the cover -->
        <div class="absolute inset-0 z-10 flex flex-col justify-end p-6">
          <div class="flex gap-4 items-end">
            <!-- Album Metadata -->
            <div class="flex-1">
              <h1 class="text-2xl md:text-3xl font-bold text-white mb-1">
                {{ albumTitle }}
              </h1>
              <p class="text-lg text-white/80 mb-2">{{ albumArtist }}</p>
              <div class="flex flex-wrap gap-4 text-sm text-white/70">
                <span>{{ albumYear }}</span>
                <span>&#8226;</span>
                <span>{{ trackCount }} {{ $t("common.songs") }}</span>
                <span>&#8226;</span>
                <span>{{ albumGenre }}</span>
                <span>&#8226;</span>
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
                <i class="bi bi-list text-lg text-white"></i>
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
                    albumData?.albumIsFavorite
                      ? 'bi-heart-fill text-red-400'
                      : 'bi-heart text-white'
                  "
                ></i>
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

    <!-- Track List -->
    <div class="flex-1 overflow-y-auto px-2 py-4">
      <!-- Loading State -->
      <div v-if="loading" class="h-full flex items-center justify-center">
        <div
          class="animate-spin w-8 h-8 border-4 border-white/30 border-t-white rounded-full"
        >
          <span class="sr-only">{{ $t("common.loading") }}</span>
        </div>
      </div>

      <!-- Tracks List -->
      <template v-else-if="tracks.length > 0">
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
                >({{ disc.tracks.length }} {{ $t("common.songs") }})</small
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
      </template>

      <!-- Empty State -->
      <div v-else class="h-full flex items-center justify-center">
        <div class="text-center text-white/60">
          <i class="bi bi-music-note-list text-5xl mb-4"></i>
          <p>{{ $t("album.noTracks") }}</p>
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
  </div>
</template>

<script>
import { ref, shallowRef, computed, watch, onMounted, onUnmounted } from "vue";
import { useI18n } from "vue-i18n";
import { usePlayerStore } from "@/stores/player";
import { useApiStore } from "@/stores/api";
import { useAlertStore } from "@/stores/alert";
import { useDetailView } from "@/composables/useDetailView";
import PlaylistAddToModal from "@/components/modals/PlaylistAddToModal.vue";
import SoundWaveAnimation from "@/components/common/SoundWaveAnimation.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";

export default {
  name: "AlbumDetailView",
  components: {
    PlaylistAddToModal,
    SoundWaveAnimation,
    SimpleImage,
  },
  props: {
    albumId: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const { t } = useI18n();
    const playerStore = usePlayerStore();
    const apiStore = useApiStore();
    const alertStore = useAlertStore();
    const { closeDetail } = useDetailView();

    const albumData = ref(null);
    const tracks = shallowRef([]);
    const loading = ref(false);

    // Playlist modal state
    const showPlaylistModal = ref(false);
    const selectedTrack = ref(null);
    const showAlbumPlaylistModal = ref(false);

    // Computed properties
    const albumTitle = computed(
      () =>
        albumData.value?.albumName ||
        albumData.value?.album_name ||
        "Album Title",
    );
    const albumArtist = computed(
      () =>
        albumData.value?.albumArtist || albumData.value?.album_artist || "--",
    );
    const albumYear = computed(
      () => albumData.value?.albumYear || albumData.value?.year || "--",
    );
    const albumGenre = computed(
      () =>
        albumData.value?.albumGenre ||
        albumData.value?.album_genre ||
        albumData.value?.genre ||
        "--",
    );
    const albumDuration = computed(() =>
      formatDuration(
        albumData.value?.albumDuration ||
          albumData.value?.album_duration ||
          albumData.value?.total_duration,
      ),
    );
    const trackCount = computed(
      () => tracks.value.length || albumData.value?.track_count || 0,
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

    const loadAlbum = async () => {
      if (!props.albumId) return;

      loading.value = true;
      try {
        // Load album tracks (this is the only available API)
        const response = await apiStore.loadAlbumSongs(props.albumId);
        const loadedTracks = response.tracks || response.data || response || [];
        tracks.value = loadedTracks;

        // Derive album metadata from the first track if we don't have it yet
        if (!albumData.value && loadedTracks.length > 0) {
          const firstTrack = loadedTracks[0];
          const totalDuration = loadedTracks.reduce(
            (sum, t) => sum + (t.duration || 0),
            0,
          );
          albumData.value = {
            album_id: props.albumId,
            albumName: firstTrack.album || firstTrack.album_name || "Album",
            albumArtist: firstTrack.artist || firstTrack.artist_name || "--",
            albumYear: firstTrack.year || firstTrack.album_year || "--",
            albumGenre: firstTrack.genre || firstTrack.album_genre || "--",
            albumDuration: totalDuration,
            track_count: loadedTracks.length,
            coverGradient: firstTrack.coverGradient || null,
          };
        }
      } catch (error) {
        console.error("Error loading album:", error);
        tracks.value = [];
      } finally {
        loading.value = false;
      }
    };

    const goBack = () => {
      closeDetail();
    };

    const playAlbum = () => {
      if (props.albumId) {
        playerStore.playAlbum(props.albumId);
      }
    };

    const playTrack = (track) => {
      playerStore.playSong(track);
      addRemainingTracksToQueue(track);
    };

    const addRemainingTracksToQueue = (selectedTrack) => {
      let allTracks = [];

      if (isMultiDisc.value) {
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

      const selectedIndex = allTracks.findIndex(
        (track) => track.song_id === selectedTrack.song_id,
      );

      if (selectedIndex !== -1 && selectedIndex < allTracks.length - 1) {
        const remainingTracks = allTracks.slice(selectedIndex + 1);
        playerStore.addMultipleToQueue(remainingTracks);

        if (remainingTracks.length > 0) {
          alertStore.info(
            `${remainingTracks.length} Songs zur Warteschlange hinzugefügt`,
          );
        }
      }
    };

    const addToQueue = () => {
      if (props.albumId) {
        playerStore.addAlbumToQueue(props.albumId);
      }
    };

    const addTrackToQueue = (track) => {
      playerStore.addToQueue(track);
    };

    const toggleFavorite = async () => {
      if (!albumData.value?.album_id) return;

      try {
        await apiStore.toggleFavorite({
          type: "album",
          itemId: albumData.value.album_id,
          currentlyFav: albumData.value.albumIsFavorite,
        });
        albumData.value = {
          ...albumData.value,
          albumIsFavorite: !albumData.value.albumIsFavorite,
        };
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
      if (data.tracks) {
        const successCount = data.result?.successCount || 0;
        if (successCount > 0) {
          alertStore.success(`${successCount} Songs zum Playlist hinzugefügt`);
        }
      }
    };

    const createNewPlaylist = () => {
      closePlaylistModal();
      alertStore.info(
        "Bitte verwenden Sie die Navigation um eine neue Playlist zu erstellen",
      );
    };

    // Watch for albumId changes
    watch(
      () => props.albumId,
      (newId) => {
        if (newId) {
          loadAlbum();
        }
      },
      { immediate: true },
    );

    return {
      albumData,
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
      goBack,
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
      playerStore,
      t,
    };
  },
};
</script>

<style scoped>
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
</style>
