import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";

export function useDetailView() {
  const route = useRoute();
  const router = useRouter();

  const detailType = computed(() => route.query.detail || null);
  const detailId = computed(() => route.query.detailId || null);
  const isShowingDetail = computed(() => !!route.query.detail);

  function openAlbumDetail(albumOrId) {
    const id = typeof albumOrId === "object" ? albumOrId.album_id : albumOrId;
    router.push({
      query: { ...route.query, detail: "album", detailId: id },
    });
  }

  function openPlaylistDetail(playlistId) {
    router.push({
      query: { ...route.query, detail: "playlist", detailId: playlistId },
    });
  }

  function closeDetail() {
    const { detail, detailId, ...rest } = route.query;
    router.push({ query: rest });
  }

  return {
    detailType,
    detailId,
    isShowingDetail,
    openAlbumDetail,
    openPlaylistDetail,
    closeDetail,
  };
}
