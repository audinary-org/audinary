import { defineStore } from "pinia";
import { ref } from "vue";
import { useAuthStore } from "./auth";
import { getCsrfHeaders, getCsrfToken } from "@/utils/csrf";

export const useApiStore = defineStore("api", () => {
  // State
  const baseUrl = ref("/api");
  const isLoading = ref(false);
  const error = ref(null);
  const cache = ref(new Map());

  // Helper function to make authenticated requests
  async function makeRequest(url, options = {}) {
    const authStore = useAuthStore();

    // Check if this is a protected endpoint (not version or public endpoints)
    const isProtectedEndpoint =
      !url.includes("/api/version") &&
      !url.includes("/api/auth/login") &&
      !url.includes("/api/auth/register") &&
      !url.includes("/api/auth/forgot-password") &&
      !url.includes("/api/auth/reset-password") &&
      !url.includes("/api/auth/validate-reset-token") &&
      !url.includes("/api/auth/status") &&
      !url.includes("/api/config") &&
      !url.includes("/api/background-images");

    // For protected endpoints, require authentication to be fully established
    if (isProtectedEndpoint) {
      if (
        !authStore.token ||
        !authStore.user ||
        !authStore.isInitialized ||
        authStore.isLoading
      ) {
        console.warn(
          "Attempted to access protected endpoint before authentication is ready:",
          url,
          {
            hasToken: !!authStore.token,
            hasUser: !!authStore.user,
            isInitialized: authStore.isInitialized,
            isLoading: authStore.isLoading,
          },
        );
        throw new Error("Authentication not ready");
      }
    }

    const defaultOptions = {
      headers: {
        ...getCsrfHeaders(),
        ...options.headers,
      },
    };

    // Only set Content-Type to JSON if we're not sending FormData
    // FormData automatically sets the correct Content-Type with boundary
    if (!(options.body instanceof FormData)) {
      defaultOptions.headers["Content-Type"] = "application/json";
    }

    // Add auth header if token exists
    if (authStore.token) {
      defaultOptions.headers["Authorization"] = `Bearer ${authStore.token}`;
    } else if (isProtectedEndpoint) {
      console.error("Protected endpoint called without token:", url);
      throw new Error("Authentication token missing");
    }

    const finalOptions = {
      ...defaultOptions,
      ...options,
      headers: {
        ...defaultOptions.headers,
        ...options.headers,
      },
    };

    try {
      const response = await fetch(url, finalOptions);

      // Handle 401 Unauthorized
      if (response.status === 401) {
        console.warn(
          "Request failed with 401, token might be expired. URL:",
          url,
        );
        // Don't automatically logout, just warn and throw
        throw new Error("Unauthorized");
      }

      return response;
    } catch (err) {
      console.error("API Request failed:", err, "URL:", url);
      throw err;
    }
  }

  // Get song play URL
  function getPlaySongUrl(songId) {
    // For media streaming, we still need query parameters due to browser/audio player limitations
    // HTML5 audio and Howl.js cannot send Authorization headers
    const authStore = useAuthStore();
    if (authStore.token) {
      return `/api/media/play/${songId}?token=${authStore.token}`;
    }

    // Return basic URL if no token (will fail on server but graceful degradation)
    return `/api/media/play/${songId}`;
  }

  // Get song stream URL for playing
  async function playSongSrc(songId) {
    return getPlaySongUrl(songId);
  }

  // Log played song for statistics
  async function logPlayedSong(songId) {
    try {
      const response = await makeRequest(`/api/media/played`, {
        method: "POST",
        body: JSON.stringify({ song_id: songId }),
      });

      if (!response.ok) {
        throw new Error("Failed to log played song");
      }

      const result = await response.json();
      return result;
    } catch (err) {
      // Don't throw error for logging failures, just log them
      return null;
    }
  }

  // Get app configuration
  async function getConfig() {
    try {
      const response = await makeRequest(`/api/config`);

      if (!response.ok) {
        throw new Error("Failed to get config");
      }

      return await response.json();
    } catch (err) {
      console.error("Get config error:", err);
      throw err;
    }
  }

  // Generic HTTP methods
  async function get(url, options = {}) {
    try {
      // Handle query parameters
      let finalUrl = url;
      if (options.params) {
        const searchParams = new URLSearchParams();
        Object.entries(options.params).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            searchParams.append(key, value.toString());
          }
        });
        const queryString = searchParams.toString();
        if (queryString) {
          finalUrl += (url.includes("?") ? "&" : "?") + queryString;
        }
      }

      const response = await makeRequest(finalUrl, {
        method: "GET",
        ...options,
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      return data;
    } catch (err) {
      console.error("GET request failed:", err);
      throw err;
    }
  }

  async function post(url, data = null, options = {}) {
    try {
      const response = await makeRequest(url, {
        method: "POST",
        body: data ? JSON.stringify(data) : null,
        ...options,
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return await response.json();
    } catch (err) {
      console.error("POST request failed:", err);
      throw err;
    }
  }

  async function put(url, data = null, options = {}) {
    try {
      const response = await makeRequest(url, {
        method: "PUT",
        body: data ? JSON.stringify(data) : null,
        ...options,
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return await response.json();
    } catch (err) {
      console.error("PUT request failed:", err);
      throw err;
    }
  }

  async function del(url, data = null, options = {}) {
    try {
      const response = await makeRequest(url, {
        method: "DELETE",
        body: data ? JSON.stringify(data) : null,
        ...options,
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return await response.json();
    } catch (err) {
      console.error("DELETE request failed:", err);
      throw err;
    }
  }

  // Get asset URL helper
  function getAssetUrl(path) {
    return `${baseUrl.value}/${path}`;
  }

  // Error handling
  function handleError(errorObj) {
    console.error("API Error:", errorObj);
    error.value = errorObj.message || "Ein Fehler ist aufgetreten";

    // If it's an auth error, emit event for UI to handle
    if (errorObj.message === "Unauthorized") {
      window.dispatchEvent(
        new CustomEvent("auth-error", {
          detail: { error: errorObj.message },
        }),
      );
    }

    throw errorObj;
  }

  // Cache management
  const MAX_CACHE_SIZE = 50;

  function getCached(key) {
    return cache.value.get(key);
  }

  function setCached(key, data, ttl = 300000) {
    // Evict expired entries before adding new ones
    if (cache.value.size >= MAX_CACHE_SIZE) {
      evictExpiredCache();
    }
    // If still at limit, remove oldest entry
    if (cache.value.size >= MAX_CACHE_SIZE) {
      const firstKey = cache.value.keys().next().value;
      cache.value.delete(firstKey);
    }
    // 5 minutes default
    cache.value.set(key, {
      data,
      expires: Date.now() + ttl,
    });
  }

  function isCacheValid(key) {
    const cached = cache.value.get(key);
    if (cached && cached.expires <= Date.now()) {
      cache.value.delete(key);
      return false;
    }
    return !!cached;
  }

  function evictExpiredCache() {
    const now = Date.now();
    for (const [key, entry] of cache.value) {
      if (entry.expires <= now) {
        cache.value.delete(key);
      }
    }
  }

  function clearCache() {
    cache.value.clear();
  }

  return {
    // State
    baseUrl,
    isLoading,
    error,
    cache,

    // HTTP Methods
    get,
    post,
    put,
    delete: del,
    makeRequest,

    // Asset helpers
    getAssetUrl,
    getPlaySongUrl,
    playSongSrc,

    // Cache
    clearCache,

    // Other methods
    logPlayedSong,
    getConfig,

    // Initialize store
    initialize() {
      // JWT authentication is now handled by makeRequest function
    },

    // Songs
    async loadSongsChunk({
      favorite = 0,
      start = 0,
      limit = 30,
      genre = "",
      decade = "",
      sort = "",
      sortDirection = "",
      search = "",
    } = {}) {
      try {
        isLoading.value = true;
        const cacheKey = `songs-${favorite}-${start}-${limit}-${genre}-${decade}-${sort}-${sortDirection}-${search}`;

        if (isCacheValid(cacheKey)) {
          return getCached(cacheKey).data;
        }

        const params = { start, limit };
        if (favorite) params.favorite = 1;
        if (genre) params.genre = genre;
        if (decade) params.decade = decade;
        if (sort) params.sort = sort;
        if (sortDirection) params.sortDirection = sortDirection;
        if (search) params.search = search;

        const response = await get("/api/media/songs", { params });

        // Backend now returns { songs: [...] } instead of just [...]
        const data = response.songs || response;
        setCached(cacheKey, data);
        return data;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Albums
    async loadAlbumsChunk({
      favorite = 0,
      start = 0,
      limit = 30,
      artist = "",
      genre = "",
      filter = "",
      sort = "",
      sortDirection = "",
      decade = "",
      search = "",
    } = {}) {
      try {
        isLoading.value = true;
        const cacheKey = `albums-${favorite}-${start}-${limit}-${artist}-${genre}-${filter}-${sort}-${sortDirection}-${decade}-${search}`;

        if (isCacheValid(cacheKey)) {
          return getCached(cacheKey).data;
        }

        const params = { start, limit };
        if (favorite) params.favorite = 1;
        if (artist) params.artist = artist;
        if (genre) params.genre = genre;
        if (filter) params.filter = filter;
        if (sort) params.sort = sort;
        if (sortDirection) params.sortDirection = sortDirection;
        if (decade) params.decade = decade;
        if (search) params.search = search;

        const response = await get("/api/media/albums", { params });

        // Backend now returns { albums: [...] } instead of just [...]
        const data = response.albums || response;
        setCached(cacheKey, data);
        return data;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Load album songs/tracks
    async loadAlbumSongs(albumId) {
      try {
        isLoading.value = true;
        const response = await get("/api/media/album-songs", {
          params: { albumId },
        });
        // Backend may return { songs: [...] } or just [...]
        return response.songs || response;
      } catch (error) {
        handleError(error);
        throw error;
      } finally {
        isLoading.value = false;
      }
    },

    // Alias for loadAlbumSongs for player compatibility
    async getAlbumTracks(albumId) {
      return this.loadAlbumSongs(albumId);
    },

    async getArtistTracks(artistId) {
      try {
        // Use existing artist-songs route with artistId (UUID) and random=1
        const response = await this.get(`/api/media/artist-songs`, {
          params: {
            artist: artistId, // Backend detects if it's UUID or name
            random: 1, // Always use random for play artist
          },
        });

        // Backend may return { songs: [...] } or just [...]
        const tracks = response.songs || response || [];

        // Return in format expected by player store
        return {
          success: true,
          tracks: tracks,
        };
      } catch (error) {
        this.handleError(error);
        throw error;
      }
    },

    async loadRecentAlbums(limit = 10) {
      try {
        isLoading.value = true;
        // Use generic albums-chunk route with sort=added instead of specialized route
        const response = await this.loadAlbumsChunk({
          start: 0,
          limit: limit,
          sort: "added",
          sortDirection: "desc",
        });
        return response;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Artists
    async loadArtistsChunk({
      favorite = 0,
      start = 0,
      limit = 30,
      genre = "",
      decade = "",
      sort = "",
      sortDirection = "",
      search = "",
    } = {}) {
      try {
        isLoading.value = true;
        const cacheKey = `artists-${favorite}-${start}-${limit}-${genre}-${decade}-${sort}-${sortDirection}-${search}`;

        if (isCacheValid(cacheKey)) {
          return getCached(cacheKey).data;
        }

        const params = { start, limit };
        if (favorite) params.favorite = 1;
        if (genre) params.genre = genre;
        if (decade) params.decade = decade;
        if (sort) params.sort = sort;
        if (sortDirection) params.sortDirection = sortDirection;
        if (search) params.search = search;

        const response = await get("/api/media/artists", { params });

        // Backend now returns { artists: [...] } instead of just [...]
        const data = response.artists || response;
        setCached(cacheKey, data);
        return data;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async loadArtistSongs(artistName) {
      try {
        isLoading.value = true;
        const response = await makeRequest(
          `/api/media/artist-songs?artist=${encodeURIComponent(artistName)}`,
        );
        const data = await response.json();
        // Backend may return { songs: [...] } or just [...]
        return data.songs || data;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async loadRandomArtistSongs(artistName) {
      try {
        isLoading.value = true;
        const response = await makeRequest(
          `/api/media/artist-songs?artist=${encodeURIComponent(artistName)}&random=1`,
        );
        const data = await response.json();
        // Backend may return { songs: [...] } or just [...]
        return data.songs || data;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async loadTopArtists(limit = 10) {
      try {
        isLoading.value = true;
        // Use generic artists-chunk route with sort=play_count instead of specialized route
        const response = await this.loadArtistsChunk({
          start: 0,
          limit: limit,
          sort: "play_count",
          sortDirection: "desc",
        });
        return response;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async loadNewArtists(limit = 10) {
      try {
        isLoading.value = true;
        // Use generic artists-chunk route with sort=added instead of specialized route
        const response = await this.loadArtistsChunk({
          start: 0,
          limit: limit,
          sort: "added",
          sortDirection: "desc",
        });
        return response;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async loadRecentlyPlayedAlbums(limit = 10) {
      try {
        isLoading.value = true;
        // Use generic albums-chunk route with sort=last_played instead of specialized route
        const response = await this.loadAlbumsChunk({
          start: 0,
          limit: limit,
          sort: "last_played",
          sortDirection: "desc",
        });
        return response;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Search
    async search(query, limit = 5) {
      try {
        isLoading.value = true;
        const response = await makeRequest(
          `/api/media/search?q=${encodeURIComponent(query)}&limit=${limit}`,
        );
        return await response.json();
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Favorites
    async toggleFavorite({ type, itemId, currentlyFav }) {
      try {
        isLoading.value = true;
        const payload = { favorite_type: type };

        if (type === "song") payload.song_id = itemId;
        if (type === "album") payload.album_id = itemId;
        if (type === "artist") payload.artist_id = itemId;
        if (type === "playlist") payload.playlist_id = itemId;
        if (type === "playlist") payload.playlist_id = itemId;

        let response;
        if (currentlyFav) {
          // DELETE request - send data in request body
          response = await makeRequest("/api/media/fav", {
            method: "DELETE",
            body: JSON.stringify(payload),
          });
        } else {
          // POST request
          response = await makeRequest("/api/media/fav", {
            method: "POST",
            body: JSON.stringify(payload),
          });
        }

        return await response.json();
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async loadFavorites(type = null) {
      try {
        isLoading.value = true;
        const params = new URLSearchParams();
        if (type) params.append("type", type);

        const url = `/api/media/fav${params.toString() ? "?" + params.toString() : ""}`;
        const response = await makeRequest(url);
        const data = await response.json();
        // Backend returns { favorites: [...] }
        return data.favorites || data;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Dashboard
    async loadDashboardData() {
      try {
        isLoading.value = true;
        const response = await makeRequest("/api/dashboard");
        return await response.json();
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async loadRecentlyPlayedSongs(limit = 20) {
      try {
        isLoading.value = true;
        // Use generic songs-chunk route with sort=last_played instead of specialized route
        const response = await this.loadSongsChunk({
          start: 0,
          limit: limit,
          sort: "last_played",
          sortDirection: "desc",
        });
        return response;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Settings
    async loadUserSettings() {
      try {
        isLoading.value = true;
        const response = await makeRequest("/api/user/settings");
        return await response.json();
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async saveUserSettings(settings) {
      try {
        isLoading.value = true;
        const response = await makeRequest("/api/user/settings", {
          method: "POST",
          body: JSON.stringify(settings),
        });
        return await response.json();
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Genres
    async loadAllGenres() {
      try {
        isLoading.value = true;
        const cacheKey = "all-genres";

        if (isCacheValid(cacheKey)) {
          return getCached(cacheKey).data;
        }

        const response = await get("/api/genres");

        setCached(cacheKey, response);
        return response;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async loadAlbumsByGenre(genre, { start = 0, limit = 30 } = {}) {
      try {
        isLoading.value = true;
        const response = await makeRequest(
          `/api/albums/genre?genre=${encodeURIComponent(genre)}&start=${start}&limit=${limit}`,
        );
        return await response.json();
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Decades
    async loadAllDecades() {
      try {
        isLoading.value = true;
        const cacheKey = "all-decades";

        if (isCacheValid(cacheKey)) {
          return getCached(cacheKey).data;
        }

        const response = await get("/api/decades");

        setCached(cacheKey, response);
        return response;
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async loadAlbumsByDecade(startYear, { start = 0, limit = 30 } = {}) {
      try {
        isLoading.value = true;
        const response = await makeRequest(
          `/api/albums/decade?startYear=${startYear}&start=${start}&limit=${limit}`,
        );
        return await response.json();
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Admin Settings
    async loadAdminSettings() {
      try {
        isLoading.value = true;
        const response = await makeRequest("/api/admin/settings");
        return await response.json();
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    async saveAdminSettings(settings) {
      try {
        isLoading.value = true;
        const response = await makeRequest("/api/admin/settings", {
          method: "POST",
          body: JSON.stringify(settings),
        });
        return await response.json();
      } catch (error) {
        handleError(error);
      } finally {
        isLoading.value = false;
      }
    },

    // Backup & Restore functions
    async getBackups() {
      try {
        isLoading.value = true;
        const response = await makeRequest("/api/admin/backups");
        return await response.json();
      } catch (error) {
        handleError(error);
        throw error;
      } finally {
        isLoading.value = false;
      }
    },

    async createBackup() {
      try {
        isLoading.value = true;
        const response = await makeRequest("/api/admin/backup", {
          method: "POST",
        });
        return await response.json();
      } catch (error) {
        handleError(error);
        throw error;
      } finally {
        isLoading.value = false;
      }
    },

    async uploadBackup(formData, progressCallback = null) {
      try {
        isLoading.value = true;

        // Create XMLHttpRequest to support progress tracking
        return new Promise((resolve, reject) => {
          const xhr = new XMLHttpRequest();
          const authStore = useAuthStore();

          xhr.upload.onprogress = (event) => {
            if (event.lengthComputable && progressCallback) {
              const progress = Math.round((event.loaded / event.total) * 100);
              progressCallback(progress);
            }
          };

          xhr.onload = () => {
            try {
              const response = JSON.parse(xhr.responseText);
              if (xhr.status >= 200 && xhr.status < 300) {
                resolve(response);
              } else {
                reject(new Error(response.message || "Upload failed"));
              }
            } catch (e) {
              reject(new Error("Invalid response format"));
            }
          };

          xhr.onerror = () => {
            reject(new Error("Upload failed"));
          };

          xhr.open("POST", "/api/admin/backup/upload");
          xhr.setRequestHeader("Authorization", `Bearer ${authStore.token}`);
          const csrfToken = getCsrfToken();
          if (csrfToken) {
            xhr.setRequestHeader("X-CSRF-Token", csrfToken);
          }
          xhr.send(formData);
        });
      } catch (error) {
        handleError(error);
        throw error;
      } finally {
        isLoading.value = false;
      }
    },

    async restoreBackup(filename) {
      try {
        isLoading.value = true;
        const response = await makeRequest("/api/admin/restore", {
          method: "POST",
          body: JSON.stringify({ filename }),
        });
        return await response.json();
      } catch (error) {
        handleError(error);
        throw error;
      } finally {
        isLoading.value = false;
      }
    },

    async deleteBackup(filename) {
      try {
        isLoading.value = true;
        const response = await makeRequest(
          `/api/admin/backup/${encodeURIComponent(filename)}`,
          {
            method: "DELETE",
          },
        );
        return await response.json();
      } catch (error) {
        handleError(error);
        throw error;
      } finally {
        isLoading.value = false;
      }
    },
  };
});
