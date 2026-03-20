<template>
  <div class="flex flex-col h-full">
    <ContentHeader
      :title="$t('nav.dashboard')"
      :show-search="false"
      :show-filter="false"
      :show-view-toggle="false"
    >
      <template #actions>
        <button
          class="inline-flex items-center gap-2 px-3 py-1 border border-white/20 text-white hover:bg-white/10 rounded-lg"
          @click="refreshDashboard"
          :disabled="isRefreshing"
        >
          <i
            class="bi bi-arrow-clockwise"
            :class="{ 'animate-spin': isRefreshing }"
          ></i>
          <span class="hidden md:inline">{{ $t("common.refresh") }}</span>
        </button>
      </template>
    </ContentHeader>

    <div class="flex-1 overflow-y-auto py-4">
      <!-- Recent Albums -->
      <NewAlbumsSection
        ref="newAlbumsSection"
        @show-album-detail="handleShowAlbumDetail"
      />

      <!-- New Artists -->
      <NewArtistsSection
        ref="newArtistsSection"
        @show-artist-albums="handleShowArtistAlbums"
      />

      <!-- Recent Played Albums -->
      <RecentPlayedAlbumsSection
        ref="recentPlayedAlbumsSection"
        @show-album-detail="handleShowAlbumDetail"
      />

      <!-- Recent Played Songs -->
      <RecentPlayedSongsSection ref="recentPlayedSongsSection" />

      <!-- Album Detail Modal -->
      <AlbumDetailModal
        :album="selectedAlbum"
        ref="albumDetailModal"
        @close="closeAlbumDetail"
        @album-updated="handleAlbumUpdated"
      />
    </div>
  </div>
</template>

<script>
import { ref } from "vue";
import { useRouter } from "vue-router";
import ContentHeader from "@/components/common/ContentHeader.vue";
import NewAlbumsSection from "./dashboard/NewAlbumsSection.vue";
import RecentPlayedAlbumsSection from "./dashboard/RecentPlayedAlbumsSection.vue";
import NewArtistsSection from "./dashboard/NewArtistsSection.vue";
import RecentPlayedSongsSection from "./dashboard/RecentPlayedSongsSection.vue";
import AlbumDetailModal from "@/components/modals/AlbumDetailModal.vue";

export default {
  name: "DashboardComponent",
  components: {
    ContentHeader,
    NewAlbumsSection,
    RecentPlayedAlbumsSection,
    NewArtistsSection,
    RecentPlayedSongsSection,
    AlbumDetailModal,
  },
  setup() {
    const router = useRouter();
    const selectedAlbum = ref(null);
    const albumDetailModal = ref(null);
    const isRefreshing = ref(false);

    // Refs to section components
    const newAlbumsSection = ref(null);
    const newArtistsSection = ref(null);
    const recentPlayedAlbumsSection = ref(null);
    const recentPlayedSongsSection = ref(null);

    const handleShowAlbumDetail = (album) => {
      selectedAlbum.value = album;
      if (albumDetailModal.value) {
        albumDetailModal.value.show();
      }
    };

    const closeAlbumDetail = () => {
      selectedAlbum.value = null;
    };

    const handleAlbumUpdated = () => {
      // Handle album updates if needed
    };

    const handleShowArtistAlbums = (artistName) => {
      router.push({
        path: "/",
        query: {
          tab: "albums",
          artist: artistName,
        },
      });
    };

    const refreshDashboard = async () => {
      // Refresh all dashboard sections by calling their load methods
      isRefreshing.value = true;

      try {
        const refreshPromises = [];

        // Call loadRecentAlbums on each section if the method exists
        if (newAlbumsSection.value?.loadRecentAlbums) {
          refreshPromises.push(newAlbumsSection.value.loadRecentAlbums());
        }

        if (newArtistsSection.value?.loadRecentArtists) {
          refreshPromises.push(newArtistsSection.value.loadRecentArtists());
        }

        if (recentPlayedAlbumsSection.value?.loadRecentPlayedAlbums) {
          refreshPromises.push(
            recentPlayedAlbumsSection.value.loadRecentPlayedAlbums(),
          );
        }

        if (recentPlayedSongsSection.value?.loadRecentPlayedSongs) {
          refreshPromises.push(
            recentPlayedSongsSection.value.loadRecentPlayedSongs(),
          );
        }

        // Wait for all sections to refresh
        await Promise.all(refreshPromises);
      } catch (error) {
        console.error("Error refreshing dashboard:", error);
      } finally {
        isRefreshing.value = false;
      }
    };

    return {
      selectedAlbum,
      albumDetailModal,
      isRefreshing,
      newAlbumsSection,
      newArtistsSection,
      recentPlayedAlbumsSection,
      recentPlayedSongsSection,
      handleShowAlbumDetail,
      closeAlbumDetail,
      handleAlbumUpdated,
      handleShowArtistAlbums,
      refreshDashboard,
    };
  },
};
</script>
