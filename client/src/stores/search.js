import { defineStore } from "pinia";
import { ref } from "vue";
import { useAuthStore } from "./auth";
import { usePlayerStore } from "./player";

export const useSearchStore = defineStore("search", () => {
  // State
  const results = ref({
    songs: [],
    albums: [],
    artists: [],
    playlists: [],
  });
  const isLoading = ref(false);
  const error = ref(null);
  const lastQuery = ref("");

  // Actions
  async function search(query) {
    if (!query || query.trim().length < 2) {
      clearResults();
      return;
    }

    isLoading.value = true;
    error.value = null;
    lastQuery.value = query;

    try {
      const authStore = useAuthStore();
      const response = await fetch(
        `/api/media/search?q=${encodeURIComponent(query)}`,
        {
          headers: {
            Authorization: `Bearer ${authStore.token}`,
          },
        },
      );

      if (!response.ok) {
        throw new Error("Search failed");
      }

      const data = await response.json();

      // Store results in the format expected by SearchComponent
      results.value = {
        songs: data.songs || [],
        albums: data.albums || [],
        artists: data.artists || [],
        playlists: data.playlists || [],
      };
    } catch (err) {
      error.value = err.message;
      console.error("Search error:", err);
    } finally {
      isLoading.value = false;
    }
  }

  async function searchByType(query, type) {
    if (!query || query.trim().length < 2) {
      clearResults();
      return;
    }

    isLoading.value = true;
    error.value = null;
    lastQuery.value = query;

    try {
      const authStore = useAuthStore();
      const response = await fetch(
        `/api/media/search/${type}?q=${encodeURIComponent(query)}`,
        {
          headers: {
            Authorization: `Bearer ${authStore.token}`,
          },
        },
      );

      if (!response.ok) {
        throw new Error(`Search for ${type} failed`);
      }

      const data = await response.json();
      results.value = data.map((item) => ({
        ...item,
        type,
      }));
    } catch (err) {
      error.value = err.message;
      console.error(`Search ${type} error:`, err);
    } finally {
      isLoading.value = false;
    }
  }

  async function searchAlbums(query) {
    return searchByType(query, "album");
  }

  async function searchArtists(query) {
    return searchByType(query, "artist");
  }

  async function searchSongs(query) {
    return searchByType(query, "song");
  }

  async function searchPlaylists(query) {
    return searchByType(query, "playlist");
  }

  function clearResults() {
    results.value = {
      songs: [],
      albums: [],
      artists: [],
      playlists: [],
    };
    error.value = null;
    lastQuery.value = "";
  }

  function clearError() {
    error.value = null;
  }

  function setQuery(query) {
    lastQuery.value = query;
  }

  // Player integration
  function playSong(song) {
    const playerStore = usePlayerStore();
    playerStore.playSong(song);
  }

  function addSongToQueue(song) {
    const playerStore = usePlayerStore();
    playerStore.addToQueue(song);
  }

  function playAlbum(album) {
    const playerStore = usePlayerStore();
    playerStore.playAlbum(album);
  }

  function playArtist(artist) {
    const playerStore = usePlayerStore();
    playerStore.playArtistSongs(artist);
  }

  function playPlaylist(playlist) {
    const playerStore = usePlayerStore();
    playerStore.playPlaylist(playlist);
  }

  // Advanced search
  async function advancedSearch(filters) {
    isLoading.value = true;
    error.value = null;

    try {
      const authStore = useAuthStore();
      const queryParams = new URLSearchParams();

      Object.entries(filters).forEach(([key, value]) => {
        if (value && value.trim()) {
          queryParams.append(key, value.trim());
        }
      });

      const response = await fetch(
        `/api/media/search/advanced?${queryParams}`,
        {
          headers: {
            Authorization: `Bearer ${authStore.token}`,
          },
        },
      );

      if (!response.ok) {
        throw new Error("Advanced search failed");
      }

      const data = await response.json();

      // Process results similar to regular search
      const allResults = [];

      if (data.albums) {
        allResults.push(
          ...data.albums.map((album) => ({
            ...album,
            type: "album",
          })),
        );
      }

      if (data.artists) {
        allResults.push(
          ...data.artists.map((artist) => ({
            ...artist,
            type: "artist",
          })),
        );
      }

      if (data.songs) {
        allResults.push(
          ...data.songs.map((song) => ({
            ...song,
            type: "song",
          })),
        );
      }

      results.value = allResults;
      return allResults;
    } catch (err) {
      error.value = err.message;
      console.error("Advanced search error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  // Get suggestions for autocomplete
  async function getSuggestions(query) {
    if (!query || query.trim().length < 1) {
      return [];
    }

    try {
      const authStore = useAuthStore();
      const response = await fetch(
        `/api/media/search/suggestions?q=${encodeURIComponent(query)}`,
        {
          headers: {
            Authorization: `Bearer ${authStore.token}`,
          },
        },
      );

      if (!response.ok) {
        throw new Error("Failed to get suggestions");
      }

      const suggestions = await response.json();
      return suggestions;
    } catch (err) {
      console.error("Get suggestions error:", err);
      return [];
    }
  }

  return {
    // State
    results,
    isLoading,
    error,
    lastQuery,

    // Actions
    search,
    searchByType,
    searchAlbums,
    searchArtists,
    searchSongs,
    searchPlaylists,
    clearResults,
    clearError,
    setQuery,

    // Player integration
    playSong,
    addSongToQueue,
    playAlbum,
    playArtist,
    playPlaylist,

    // Advanced features
    advancedSearch,
    getSuggestions,
  };
});
