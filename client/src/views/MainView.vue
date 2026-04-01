<template>
  <BaseLayout>
    <div ref="scrollContainer" class="h-full overflow-y-auto p-2">
      <!-- Detail Sub-Views (rendered on top of tabs, hiding them) -->
      <AlbumDetailView
        v-if="detailType === 'album' && detailId"
        :album-id="detailId"
        class="min-h-full"
      />

      <PlaylistDetailView
        v-if="detailType === 'playlist' && detailId"
        :playlist-id="detailId"
        class="min-h-full"
      />

      <!-- Tab Views (hidden when a detail view is open) -->
      <div v-show="!isShowingDetail">
        <!-- Dashboard View -->
        <div
          v-show="activeTab === 'dashboard'"
          id="view-dashboard"
          class="min-h-full"
        >
          <DashboardComponent :key="dashboardKey" ref="dashboardComponent" />
        </div>

        <!-- Search Results View -->
        <div
          v-show="activeTab === 'search'"
          id="view-search"
          class="min-h-full"
        >
          <GlobalSearchComponent />
        </div>

        <!-- Library View (Albums/Artists/Songs/Playlists) -->
        <div
          v-show="
            activeTab === 'albums' ||
            activeTab === 'artists' ||
            activeTab === 'songs' ||
            activeTab === 'playlists'
          "
          id="view-library"
          class="min-h-full"
        >
          <!-- Library Content -->
          <div class="space-y-4">
            <!-- Albums Tab -->
            <div v-if="activeTab === 'albums'" class="w-full">
              <AlbumsComponent
                :filtered-by-artist="filteredByArtist"
                :filtered-by-genre="filteredByGenre"
                @go-back="goBackFromAlbums"
              />
            </div>

            <!-- Artists Tab -->
            <div v-if="activeTab === 'artists'" class="w-full">
              <ArtistsComponent @show-albums-by-artist="showAlbumsByArtist" />
            </div>

            <!-- Songs Tab -->
            <div v-if="activeTab === 'songs'" class="w-full">
              <SongsComponent />
            </div>

            <!-- Playlists Tab -->
            <div v-if="activeTab === 'playlists'" class="w-full">
              <PlaylistsComponent />
            </div>
          </div>
        </div>

        <!-- Shares View -->
        <div
          v-show="activeTab === 'shares'"
          id="view-shares"
          class="min-h-full"
        >
          <PublicSharesComponent />
        </div>

        <!-- Wishlist View -->
        <div
          v-if="configStore.isWishlistEnabled"
          v-show="activeTab === 'wishlist'"
          id="view-wishlist"
          class="min-h-full"
        >
          <WishlistComponent />
        </div>

        <!-- Settings View -->
        <div
          v-show="activeTab === 'settings'"
          id="view-settings"
          class="min-h-full"
        >
          <SettingsComponent :active-section="settingsSection" />
        </div>
      </div>
    </div>
  </BaseLayout>
</template>

<script>
import { computed, ref, nextTick } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";

// Import components
import BaseLayout from "@/components/layout/BaseLayout.vue";
import DashboardComponent from "@/components/DashboardComponent.vue";
import GlobalSearchComponent from "@/components/GlobalSearchComponent.vue";
import AlbumsComponent from "@/components/AlbumsComponent.vue";
import ArtistsComponent from "@/components/ArtistsComponent.vue";
import SongsComponent from "@/components/SongsComponent.vue";
import PlaylistsComponent from "@/components/PlaylistsComponent.vue";
import PublicSharesComponent from "@/components/PublicSharesComponent.vue";
import WishlistComponent from "@/components/WishlistComponent.vue";
import SettingsComponent from "@/components/SettingsComponent.vue";
import AlbumDetailView from "@/components/views/AlbumDetailView.vue";
import PlaylistDetailView from "@/components/views/PlaylistDetailView.vue";
import { usePlayerStore } from "@/stores/player";
import { useAuthStore } from "@/stores/auth";
import { useConfigStore } from "@/stores/config";
import { useDetailView } from "@/composables/useDetailView";
import { watch } from "vue";

export default {
  name: "MainView",
  components: {
    BaseLayout,
    DashboardComponent,
    GlobalSearchComponent,
    AlbumsComponent,
    ArtistsComponent,
    SongsComponent,
    PlaylistsComponent,
    PublicSharesComponent,
    WishlistComponent,
    SettingsComponent,
    AlbumDetailView,
    PlaylistDetailView,
  },
  setup() {
    const { t } = useI18n();
    const route = useRoute();
    const router = useRouter();
    const playerStore = usePlayerStore();
    const authStore = useAuthStore();
    const configStore = useConfigStore();
    const { detailType, detailId, isShowingDetail } = useDetailView();

    // Scroll container ref and saved scroll position
    const scrollContainer = ref(null);
    let savedScrollTop = 0;

    // Save/restore scroll position when entering/leaving detail views
    watch(isShowingDetail, (showing, wasShowing) => {
      if (showing && !wasShowing) {
        // Entering detail view — save scroll position
        if (scrollContainer.value) {
          savedScrollTop = scrollContainer.value.scrollTop;
        }
      } else if (!showing && wasShowing) {
        // Leaving detail view — restore scroll position
        nextTick(() => {
          if (scrollContainer.value) {
            scrollContainer.value.scrollTop = savedScrollTop;
          }
        });
      }
    });

    // Reactive key to force dashboard re-render
    const dashboardKey = ref(0);
    const dashboardComponent = ref(null);

    // Navigation state for filtered views (from LibraryView)
    const filteredByArtist = ref(null);
    const filteredByGenre = ref(null);

    // Active tab based on route
    const activeTab = computed(() => {
      const tab = route.query.tab || "dashboard";
      return tab;
    });

    // Settings section based on route (for sidebar integration)
    const settingsSection = computed(() => {
      return route.query.section || "profile";
    });

    const refreshDashboard = () => {
      // Force re-render of dashboard component by incrementing key
      dashboardKey.value += 1;
    };

    // Library navigation functions (from LibraryView)
    const checkRouteParameters = () => {
      if (route.query.artist) {
        filteredByArtist.value = route.query.artist;
        filteredByGenre.value = null;
      } else if (route.query.genre) {
        filteredByGenre.value = route.query.genre;
        filteredByArtist.value = null;
      } else {
        if (filteredByArtist.value || filteredByGenre.value) {
          filteredByArtist.value = null;
          filteredByGenre.value = null;
        }
      }
    };

    const showAlbumsByArtist = (artistName) => {
      filteredByArtist.value = artistName;
      filteredByGenre.value = null;

      router.replace({
        path: "/",
        query: {
          ...route.query,
          tab: "albums",
          artist: artistName,
          genre: undefined,
        },
      });
    };

    const showAlbumsByGenre = (genreName) => {
      filteredByGenre.value = genreName;
      filteredByArtist.value = null;

      router.replace({
        path: "/",
        query: {
          ...route.query,
          tab: "albums",
          genre: genreName,
          artist: undefined,
        },
      });
    };

    const goBackFromAlbums = () => {
      if (filteredByArtist.value) {
        filteredByArtist.value = null;
        router.replace({
          path: "/",
          query: {
            ...route.query,
            tab: "artists",
            artist: undefined,
          },
        });
      } else if (filteredByGenre.value) {
        filteredByGenre.value = null;
        router.replace({
          path: "/",
          query: {
            ...route.query,
            tab: "genres",
            genre: undefined,
          },
        });
      }
    };

    // Watch for route changes to handle library navigation
    watch(
      () => route.query,
      () => {
        checkRouteParameters();
      },
      { immediate: true },
    );

    return {
      activeTab,
      settingsSection,
      refreshDashboard,
      scrollContainer,
      dashboardKey,
      dashboardComponent,
      configStore,
      detailType,
      detailId,
      isShowingDetail,

      // Library navigation state and functions
      filteredByArtist,
      filteredByGenre,
      showAlbumsByArtist,
      showAlbumsByGenre,
      goBackFromAlbums,

      t,
    };
  },
};
</script>
