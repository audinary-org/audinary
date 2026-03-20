<template>
  <div class="dashboard-section">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-semibold text-audinary m-0">
        {{ $t("dashboard.new_artists") }}
      </h3>
      <a
        href="#"
        class="text-white/80 hover:text-audinary transition-colors mobile-touch-target flex items-center text-sm"
        @click.prevent="showAll"
      >
        {{ $t("common.show_all") }} <i class="bi bi-chevron-right ml-1"></i>
      </a>
    </div>

    <div class="relative">
      <button
        v-if="canScrollLeft"
        class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-10 h-10 bg-black/70 hover:bg-black/90 border-none rounded-full text-white flex items-center justify-center transition-colors mobile-touch-target"
        @click="scrollLeft"
        :aria-label="$t('common.scroll_left')"
      >
        <i class="bi bi-chevron-left"></i>
      </button>

      <div
        class="overflow-x-auto overflow-y-hidden scrollbar-hide px-5"
        ref="scrollContent"
        style="scroll-behavior: smooth"
      >
        <div v-if="loading" class="flex justify-center py-8">
          <div
            class="animate-spin w-8 h-8 border-4 border-gray-600 border-t-gray-300 rounded-full"
          >
            <span class="sr-only">{{ $t("common.loading") }}</span>
          </div>
        </div>

        <div v-else class="flex gap-4 pb-2">
          <div
            v-for="artist in artists"
            :key="artist.artist_id"
            class="flex-shrink-0 w-60 group bg-white/15 rounded shadow-lg p-2 h-full transition-all duration-200 hover:bg-white/25"
            @click="showArtistAlbums(artist)"
          >
            <div class="relative">
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
                  class="absolute top-2 right-2 w-8 h-8 bg-black/40 hover:bg-black/60 rounded-full flex items-center justify-center transition-all hover:scale-110 z-[10]"
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
                {{ artist.albumCount || 0 }} {{ $t("library.albums") }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <button
        v-if="canScrollRight"
        class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-10 h-10 bg-black/70 hover:bg-black/90 border-none rounded-full text-white flex items-center justify-center transition-colors mobile-touch-target"
        @click="scrollRight"
        :aria-label="$t('common.scroll_right')"
      >
        <i class="bi bi-chevron-right"></i>
      </button>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import { usePlayerStore } from "@/stores/player";
import { useApiStore } from "@/stores/api";
import { useAuthStore } from "@/stores/auth";
import SimpleImage from "@/components/common/SimpleImage.vue";

export default {
  name: "NewArtistsSection",
  components: {
    SimpleImage,
  },
  emits: ["show-artist-albums"],
  setup(props, { emit }) {
    const { t } = useI18n();
    const router = useRouter();
    const playerStore = usePlayerStore();
    const apiStore = useApiStore();
    const authStore = useAuthStore();

    const artists = ref([]);
    const loading = ref(true);
    const scrollContent = ref(null);
    const scrollPosition = ref(0);

    const canScrollLeft = computed(() => scrollPosition.value > 0);
    const canScrollRight = computed(() => {
      if (!scrollContent.value) return false;
      return (
        scrollPosition.value <
        scrollContent.value.scrollWidth - scrollContent.value.clientWidth
      );
    });

    const loadNewArtists = async () => {
      // Don't load data if not authenticated
      if (!authStore.isAuthenticated) {
        loading.value = false;
        return;
      }

      try {
        loading.value = true;
        const response = await apiStore.loadNewArtists(15);
        artists.value = response || [];
      } catch (error) {
        console.error("Error loading new artists:", error);
        artists.value = [];
      } finally {
        loading.value = false;
      }
    };

    const showAll = () => {
      router.push("/?tab=artists");
    };

    const showArtistAlbums = (artist) => {
      emit("show-artist-albums", artist.artistName);
    };

    const playArtist = (artist) => {
      playerStore.playArtist(artist.artist_id);
    };

    const toggleArtistFavorite = async (artist) => {
      try {
        const response = await apiStore.post(
          `/api/artists/${artist.artist_id}/favorite`,
          {
            is_favorite: !artist.is_favorite,
          },
        );

        if (response.success) {
          artist.is_favorite = !artist.is_favorite;
        }
      } catch (error) {
        console.error("Error toggling artist favorite:", error);
      }
    };

    const scrollLeft = () => {
      if (scrollContent.value) {
        scrollContent.value.scrollBy({ left: -200, behavior: "smooth" });
        updateScrollPosition();
      }
    };

    const scrollRight = () => {
      if (scrollContent.value) {
        scrollContent.value.scrollBy({ left: 200, behavior: "smooth" });
        updateScrollPosition();
      }
    };

    const updateScrollPosition = () => {
      if (scrollContent.value) {
        scrollPosition.value = scrollContent.value.scrollLeft;
      }
    };

    onMounted(async () => {
      // Wait for authentication to be confirmed before loading data
      if (authStore.isAuthenticated) {
        await loadNewArtists();
      } else {
        // Watch for authentication changes
        const unwatch = authStore.$subscribe(() => {
          if (authStore.isAuthenticated && artists.value.length === 0) {
            loadNewArtists();
            unwatch(); // Stop watching once data is loaded
          }
        });
      }

      if (scrollContent.value) {
        scrollContent.value.addEventListener("scroll", updateScrollPosition);
      }
    });

    return {
      artists,
      loading,
      scrollContent,
      canScrollLeft,
      canScrollRight,
      showAll,
      showArtistAlbums,
      playArtist,
      toggleArtistFavorite,
      scrollLeft,
      scrollRight,
      loadRecentArtists: loadNewArtists, // Expose for parent component refresh (alias for consistency)
      t,
    };
  },
};
</script>

<style scoped>
/* Custom scrollbar hiding for dashboard sections */
.scrollbar-hide {
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

/* Mobile touch target utility */
.mobile-touch-target {
  min-height: 44px;
  min-width: 44px;
}
</style>
