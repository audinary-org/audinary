<template>
  <div
    class="min-h-screen flex flex-col overflow-hidden"
    :class="themeStore.backgroundGradient"
  >
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center flex-1">
      <div class="text-center">
        <i class="bi bi-hourglass animate-spin text-4xl text-white/50 mb-4"></i>
        <p class="text-white/60">{{ $t("common.loading") }}...</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex items-center justify-center flex-1">
      <div class="text-center max-w-md mx-4">
        <i class="bi text-6xl text-red-400 mb-4" :class="getErrorIcon()"></i>
        <h1 class="text-2xl font-bold text-white mb-4">
          {{ getErrorTitle() }}
        </h1>
        <p class="text-white/70 mb-6">{{ error }}</p>
        <button
          v-if="canRetry"
          @click="loadShare"
          class="bg-audinary hover:bg-audinary/90 text-black font-semibold px-6 py-3 rounded-lg transition-all"
        >
          {{ $t("common.retry") }}
        </button>
      </div>
    </div>

    <!-- Password Required State -->
    <div
      v-else-if="needsPassword"
      class="flex items-center justify-center flex-1"
    >
      <PublicSharesPasswordModal
        :share-uuid="shareUuid"
        @password-verified="onPasswordVerified"
        @close="$router.push('/')"
      />
    </div>

    <!-- Content -->
    <div v-else-if="shareContent" class="flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header
        class="bg-black/20 backdrop-blur-md border-b border-white/10 sticky top-0 z-40"
      >
        <div class="max-w-7xl mx-auto px-4 py-4">
          <div class="flex items-center justify-between">
            <!-- Logo/Brand and Slogan -->
            <div class="flex items-center gap-3">
              <img src="/img/icon-96x96.png" alt="Audinary" class="h-12 w-12" />
              <div>
                <h1 class="text-xl font-bold text-white">Audinary</h1>
                <p class="text-sm text-audinary font-medium">
                  Hungry for music
                </p>
              </div>
            </div>

            <!-- Controls (Language, About & Theme) -->
            <div class="flex items-center gap-3">
              <!-- Language Switcher -->
              <div class="relative" ref="languageSelector">
                <button
                  class="mobile-touch-target bg-white/10 hover:bg-white/20 backdrop-blur-sm border border-white/20 rounded-lg flex items-center justify-center transition-colors w-10 h-10"
                  type="button"
                  @click="toggleLanguageDropdown"
                  :title="currentLocale.name"
                >
                  <FlagIcon
                    v-if="currentLocale.countryCode"
                    :countryCode="currentLocale.countryCode"
                    size="sm"
                    :shadow="false"
                  />
                  <span v-else class="text-sm text-white/80">{{
                    currentLocale.flag
                  }}</span>
                </button>
                <div
                  v-show="showLanguageDropdown"
                  class="absolute right-0 top-full mt-1 bg-black/90 backdrop-blur-md border border-white/10 rounded-lg min-w-[60px] z-50 overflow-hidden"
                >
                  <button
                    v-for="locale in availableLocales"
                    :key="locale.code"
                    type="button"
                    @click.stop="changeLanguage(locale.code)"
                    :class="{
                      'bg-white/10': locale.code === currentLanguageCode,
                    }"
                    class="block w-full text-center py-2 px-3 text-white/80 hover:text-white hover:bg-white/10 transition-colors mobile-touch-target flex items-center justify-center"
                    :title="locale.name"
                  >
                    <FlagIcon
                      v-if="locale.countryCode"
                      :countryCode="locale.countryCode"
                      size="sm"
                      :shadow="false"
                    />
                    <span v-else class="text-sm">{{ locale.flag }}</span>
                  </button>
                </div>
              </div>

              <!-- About Modal Toggle -->
              <button
                @click="showAbout = true"
                class="mobile-touch-target bg-white/10 hover:bg-white/20 backdrop-blur-sm border border-white/20 rounded-lg flex items-center justify-center transition-colors p-2"
                :title="$t('about.title')"
              >
                <i class="bi bi-info-circle text-white/80"></i>
              </button>

              <!-- Theme Switcher -->
              <div class="relative" ref="themeSelector">
                <button
                  class="mobile-touch-target bg-white/10 hover:bg-white/20 backdrop-blur-sm border border-white/20 rounded-lg flex items-center justify-center transition-colors p-2"
                  type="button"
                  @click="toggleThemeDropdown"
                  :title="$t('settings.theme.title')"
                >
                  <i class="bi bi-palette text-white/80"></i>
                </button>
                <div
                  v-show="showThemeDropdown"
                  class="absolute right-0 top-full mt-1 bg-black/90 backdrop-blur-md border border-white/10 rounded-lg min-w-[200px] z-50 overflow-hidden"
                >
                  <div class="p-3">
                    <div
                      class="text-xs text-gray-400 mb-2 uppercase font-semibold"
                    >
                      {{ $t("settings.theme.title") }}
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                      <div
                        v-for="theme in themeStore.availableThemes"
                        :key="theme.id"
                        class="relative cursor-pointer group"
                        @click="selectTheme(theme.id)"
                      >
                        <!-- Theme Preview -->
                        <div
                          :class="[
                            'w-full h-12 rounded-md border transition-all duration-200',
                            theme.preview,
                            currentTheme === theme.id
                              ? 'border-blue-400 ring-1 ring-blue-400/50'
                              : 'border-white/20 group-hover:border-white/40',
                          ]"
                        >
                          <!-- Selection indicator -->
                          <div
                            v-if="currentTheme === theme.id"
                            class="absolute top-1 right-1 w-4 h-4 bg-blue-500 rounded-full flex items-center justify-center"
                          >
                            <i class="bi bi-check text-white text-xs"></i>
                          </div>
                        </div>
                        <!-- Theme Name -->
                        <p
                          class="text-xs text-gray-300 mt-1 text-center truncate"
                        >
                          {{ theme.name }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Content -->
      <main
        class="flex-1 max-w-7xl mx-auto w-full px-4 py-6 flex flex-col overflow-y-auto"
      >
        <!-- Glass Action Bar -->
        <div
          v-if="
            shareContent.content.songs && shareContent.content.songs.length > 0
          "
          class="mb-6 flex-shrink-0"
        >
          <div
            class="bg-white/10 backdrop-blur-md border border-white/20 rounded-xl p-4"
          >
            <div class="flex flex-wrap items-center justify-between gap-4">
              <!-- Share name and song count info -->
              <div class="flex items-center gap-4">
                <h2
                  v-if="shareContent?.share?.name"
                  class="text-lg font-semibold text-white"
                >
                  {{ shareContent.share.name }}
                </h2>
                <div class="text-white/70 text-sm">
                  {{
                    $t("shares.song_count", {
                      count: shareContent.content.songs.length,
                    })
                  }}
                </div>
              </div>

              <!-- Play buttons -->
              <div class="flex items-center gap-3">
                <button
                  @click="playAll"
                  class="inline-flex items-center gap-2 px-3 py-1 bg-audinary hover:bg-audinary/90 text-black font-medium rounded-lg transition-colors"
                  :disabled="!shareContent.content.songs.length"
                >
                  <i class="bi bi-play-fill"></i>
                  {{ $t("player.play_all") }}
                </button>
                <button
                  @click="shufflePlay"
                  class="inline-flex items-center gap-2 px-3 py-1 border border-white/20 text-white hover:bg-white/10 rounded-lg transition-colors"
                  :disabled="!shareContent.content.songs.length"
                >
                  <i class="bi bi-shuffle"></i>
                  {{ $t("player.shuffle") }}
                </button>
                <button
                  v-if="shareContent.share.download_enabled"
                  @click="downloadAll"
                  class="inline-flex items-center gap-2 px-3 py-1 border border-white/20 text-white hover:bg-white/10 rounded-lg transition-colors"
                  :disabled="
                    downloadingAll || !shareContent.content.songs.length
                  "
                >
                  <i
                    class="bi"
                    :class="
                      downloadingAll
                        ? 'bi-hourglass animate-spin'
                        : 'bi-download'
                    "
                  ></i>
                  {{
                    downloadingAll
                      ? $t("common.loading")
                      : $t("common.download_all")
                  }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Songs List -->
        <div
          class="bg-white/10 backdrop-blur-md border border-white/20 rounded-xl overflow-hidden flex-1 flex flex-col min-h-0"
        >
          <div
            v-if="
              shareContent.content.songs &&
              shareContent.content.songs.length > 0
            "
            class="overflow-y-auto flex-1"
          >
            <div class="songs-list flex flex-col">
              <div
                v-for="(song, index) in displayedSongs"
                :key="song.song_id"
                class="flex items-center p-3 group bg-white/0 hover:bg-white/10 border-b border-white/5 last:border-b-0 transition-all duration-200 cursor-pointer"
                :class="{
                  'bg-audinary/20 border-audinary/40':
                    currentSongIndex === index,
                  'border-audinary/60': isPlaying && currentSongIndex === index,
                }"
                @click="playSong(index)"
              >
                <!-- Track Number / Play Indicator -->
                <div class="w-10 text-center text-white/70 mr-3">
                  <SoundWaveAnimation :song="song" :track-number="index + 1" />
                </div>

                <!-- Album Cover -->
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
                    :imageType="'album_thumbnail'"
                    :imageId="song.album_id || 'default'"
                    :alt="song.album"
                    class="rounded relative z-[2]"
                    style="width: 40px; height: 40px; object-fit: cover"
                    :placeholder="'disc'"
                    :placeholderSize="'20px'"
                  />
                </div>

                <!-- Song Info -->
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-lg text-audinary truncate">
                    {{ song.title }}
                  </div>
                  <div class="text-white/80 text-sm truncate">
                    {{ song.artist }} - {{ song.album }}
                  </div>
                  <div class="text-white/80 text-sm md:hidden truncate">
                    {{ song.year || "--" }} • {{ song.genre || "--" }}
                  </div>
                </div>

                <!-- Desktop Info -->
                <div class="hidden md:block text-white/80 mr-3 text-sm">
                  {{ song.year || "--" }}
                </div>
                <div class="hidden lg:block text-white/80 mr-3 text-sm">
                  {{ song.genre || "--" }}
                </div>
                <div class="text-white/80 mr-3">
                  {{ formatDuration(song.duration) }}
                </div>

                <!-- Actions -->
                <div
                  class="flex gap-1 opacity-0 group-hover:opacity-100 transition-all md:opacity-100"
                >
                  <button
                    class="w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
                    @click.stop="playSong(index)"
                    :title="$t('player.play')"
                  >
                    <i class="bi bi-play-fill text-xs text-white"></i>
                  </button>
                  <button
                    v-if="shareContent.share.download_enabled"
                    @click.stop="downloadSong(song)"
                    class="w-8 h-8 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 disabled:opacity-50"
                    :disabled="downloadingSong === song.song_id"
                    :title="`Download ${song.title}`"
                  >
                    <i
                      class="bi text-xs text-white"
                      :class="
                        downloadingSong === song.song_id
                          ? 'bi-hourglass animate-spin'
                          : 'bi-download'
                      "
                    ></i>
                  </button>
                </div>
              </div>

              <!-- Load More Trigger -->
              <div
                v-if="hasMore"
                ref="loadMoreTrigger"
                class="p-4 text-center text-white/60"
              >
                <div
                  v-if="loadingMore"
                  class="flex items-center justify-center"
                >
                  <i class="bi bi-hourglass animate-spin mr-2"></i>
                  {{ $t("common.loading") }}...
                </div>
                <div v-else class="text-sm">
                  {{ $t("common.scroll_for_more") }}
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="text-center py-20 p-6">
            <i class="bi bi-music-note text-gray-500 mb-3 text-3xl"></i>
            <h5 class="text-gray-400">{{ $t("common.no_songs") }}</h5>
          </div>
        </div>
      </main>

      <!-- Footer - only show when no song is playing -->
      <footer
        v-if="!playerStore.currentSong"
        class="bg-black/20 backdrop-blur-md border-t border-white/10 flex-shrink-0"
      >
        <div class="max-w-7xl mx-auto px-4 py-6 text-center">
          <div
            class="flex items-center justify-center gap-2 text-white/60 text-sm"
          >
            <span>{{ $t("shares.powered_by") }}</span>
            <img
              src="/img/audinary-reverse-small-orange.png"
              alt="Audinary"
              class="h-6"
            />
          </div>
        </div>
      </footer>
    </div>

    <!-- Player Component - as footer when song is playing -->
    <PlayerComponent v-if="playerStore.currentSong" />

    <!-- Modals -->
    <AboutModal v-if="showAbout" @close="showAbout = false" />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import {
  getAvailableLocales,
  setLocale,
  getCurrentLocalePreference,
} from "@/i18n";
import { usePlayerStore } from "@/stores/player";
import { useThemeStore } from "@/stores/theme";
import PublicSharesPasswordModal from "@/components/modals/PublicSharesPasswordModal.vue";
import PlayerComponent from "@/components/player/PlayerComponent.vue";
import AboutModal from "@/components/modals/AboutModal.vue";
import SoundWaveAnimation from "@/components/common/SoundWaveAnimation.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";
import FlagIcon from "@/components/common/FlagIcon.vue";

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const playerStore = usePlayerStore();
const themeStore = useThemeStore();

// State
const shareUuid = computed(() => route.params.uuid);
const shareContent = ref(null);
const loading = ref(false);
const error = ref("");
const needsPassword = ref(false);
const canRetry = ref(true);

// Language switching state
const availableLocales = getAvailableLocales();
const currentLanguageCode = ref(getCurrentLocalePreference());
const showLanguageDropdown = ref(false);
const languageSelector = ref(null);

// UI State
const showAbout = ref(false);
const showThemeDropdown = ref(false);
const themeSelector = ref(null);

// Responsive state for height calculation
const windowWidth = ref(window.innerWidth);

// Lazy Loading State
const loadMoreTrigger = ref(null);
const itemsPerPage = 50;
const currentPage = ref(1);
const loadingMore = ref(false);

// Computed for displayed songs with pagination
const displayedSongs = computed(() => {
  if (!shareContent.value?.content.songs) return [];

  const songs = shareContent.value.content.songs;
  const endIndex = currentPage.value * itemsPerPage;

  return songs.slice(0, endIndex);
});

// Computed to check if there are more songs to load
const hasMore = computed(() => {
  if (!shareContent.value?.content.songs) return false;

  const songs = shareContent.value.content.songs;
  const endIndex = currentPage.value * itemsPerPage;

  return endIndex < songs.length;
});

// Audio player state using playerStore
const currentSongIndex = computed(() => {
  if (!shareContent.value?.content.songs || !playerStore.currentSong) return -1;
  return shareContent.value.content.songs.findIndex(
    (song) =>
      (song.song_id || song.id) ===
      (playerStore.currentSong.song_id || playerStore.currentSong.id),
  );
});
const isPlaying = computed(() => playerStore.isPlaying);
const downloadingSong = ref(null);
const downloadingAll = ref(false);

// Theme
const currentTheme = computed(() => themeStore.currentTheme);

// Language
const currentLocale = computed(() => {
  return (
    availableLocales.find(
      (locale) => locale.code === currentLanguageCode.value,
    ) || availableLocales[1]
  ); // fallback to English
});

// Dynamic height calculation for songs list
const songsListHeight = computed(() => {
  // If player is active, reduce height to account for player overlay
  if (playerStore.currentSong) {
    // Check if we're on mobile/tablet (screen width < 768px)
    const isMobile = windowWidth.value < 768;

    if (isMobile) {
      return "calc(70vh - 1px)"; // Mobile player height
    } else {
      return "calc(70vh - 40px)"; // Desktop player height
    }
  }

  // If no player, use normal height - footer will appear naturally below
  return "70vh";
});

// Methods
async function loadShare(password = null) {
  loading.value = true;
  error.value = "";
  needsPassword.value = false;
  canRetry.value = true;

  try {
    const url = new URL(
      `/api/share/${shareUuid.value}`,
      window.location.origin,
    );
    if (password) {
      url.searchParams.set("password", password);
    }

    const response = await fetch(url);
    const data = await response.json();

    if (!response.ok || !data.success) {
      if (response.status === 401 && data.code === "PASSWORD_REQUIRED") {
        needsPassword.value = true;
        return;
      }

      if (response.status === 403 && data.code === "INVALID_PASSWORD") {
        needsPassword.value = true;
        return;
      }

      if (response.status === 404) {
        canRetry.value = false;
      }

      if (response.status === 410) {
        canRetry.value = false;
      }

      throw new Error(data.error || t("shares.load_error"));
    }

    shareContent.value = data.data;

    // Reset pagination when new content loads
    resetPagination();

    // Setup intersection observer for lazy loading after next tick
    setTimeout(() => {
      setupIntersectionObserver();
    }, 100);
  } catch (err) {
    console.error("Error loading share:", err);
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

function onPasswordVerified(password) {
  needsPassword.value = false;
  loadShare(password);
}

function getErrorIcon() {
  if (error.value.includes("not found")) return "bi-file-x";
  if (error.value.includes("expired")) return "bi-clock-history";
  return "bi-exclamation-triangle";
}

function getErrorTitle() {
  if (error.value.includes("not found")) return t("shares.not_found");
  if (error.value.includes("expired")) return t("shares.expired");
  return t("shares.error");
}

function formatDuration(seconds) {
  if (!seconds) return "--:--";
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins}:${secs.toString().padStart(2, "0")}`;
}

function formatDate(dateStr) {
  if (!dateStr) return "";

  try {
    return new Date(dateStr).toLocaleDateString();
  } catch {
    return dateStr;
  }
}

// Audio player methods using playerStore
function playSong(index) {
  if (shareContent.value?.content.songs?.[index]) {
    const song = {
      ...shareContent.value.content.songs[index],
      customStreamUrl: `/api/share/${shareUuid.value}/stream/${shareContent.value.content.songs[index].song_id}`,
    };
    playerStore.playSong(song, index);
  }
}

function playAll() {
  if (shareContent.value?.content.songs?.length > 0) {
    // Load songs with custom stream URLs for public shares
    const songsWithStreamUrls = shareContent.value.content.songs.map(
      (song) => ({
        ...song,
        customStreamUrl: `/api/share/${shareUuid.value}/stream/${song.song_id}`,
      }),
    );
    playerStore.playPlaylist(songsWithStreamUrls);
  }
}

function shufflePlay() {
  if (shareContent.value?.content.songs?.length > 0) {
    playAll();
    // Enable shuffle after loading playlist
    if (!playerStore.isShuffleEnabled) {
      playerStore.toggleShuffle();
    }
  }
}

async function downloadSong(song) {
  if (!shareContent.value?.share.download_enabled) return;

  downloadingSong.value = song.song_id;

  try {
    const url = `/api/share/${shareUuid.value}/download/${song.song_id}`;
    const response = await fetch(url);

    if (!response.ok) {
      throw new Error(`Download failed: ${response.statusText}`);
    }

    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);
    const filename = `${song.artist} - ${song.title}.${song.format || song.filetype || "unknown"}`;

    const link = document.createElement("a");
    link.href = downloadUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Clean up the blob URL
    window.URL.revokeObjectURL(downloadUrl);
  } catch (error) {
    console.error("Download error:", error);
    // Show user-friendly error if possible
    if (error.message.includes("404")) {
      alert("File not found");
    } else if (error.message.includes("403")) {
      alert("Downloads not allowed for this share");
    } else {
      alert("Download failed. Please try again.");
    }
  } finally {
    downloadingSong.value = null;
  }
}

async function downloadAll() {
  if (
    !shareContent.value?.share.download_enabled ||
    !shareContent.value?.content.songs?.length
  )
    return;

  downloadingAll.value = true;

  try {
    // Use ZIP download endpoint for all songs
    const url = `/api/share/${shareUuid.value}/download-all`;
    const response = await fetch(url);

    if (!response.ok) {
      throw new Error(`Download failed: ${response.statusText}`);
    }

    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);

    // Use share name or default filename for ZIP
    const filename = shareContent.value.share.name
      ? `${shareContent.value.share.name}.zip`
      : `${shareContent.value.content.type}_share.zip`;

    const link = document.createElement("a");
    link.href = downloadUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Clean up the blob URL
    window.URL.revokeObjectURL(downloadUrl);
  } catch (error) {
    console.error("Download all error:", error);
    // Show user-friendly error
    if (error.message.includes("404")) {
      alert("Share not found");
    } else if (error.message.includes("403")) {
      alert("Downloads not allowed for this share");
    } else {
      alert("Download failed. Please try again.");
    }
  } finally {
    downloadingAll.value = false;
  }
}

// Lazy Loading Functions
function loadMoreSongs() {
  if (loadingMore.value || !hasMore.value) return;

  loadingMore.value = true;

  // Simulate loading delay (in real app, this might be an API call)
  setTimeout(() => {
    currentPage.value++;
    loadingMore.value = false;
  }, 300);
}

function setupIntersectionObserver() {
  if (!loadMoreTrigger.value) return;

  const observer = new IntersectionObserver(
    (entries) => {
      const [entry] = entries;
      if (entry.isIntersecting && hasMore.value && !loadingMore.value) {
        loadMoreSongs();
      }
    },
    {
      threshold: 0.1,
      rootMargin: "50px",
    },
  );

  observer.observe(loadMoreTrigger.value);

  // Store observer for cleanup
  loadMoreTrigger.value._observer = observer;
}

function resetPagination() {
  currentPage.value = 1;
  hasMore.value = true;
  loadingMore.value = false;
}
function toggleThemeDropdown() {
  showThemeDropdown.value = !showThemeDropdown.value;
}

function selectTheme(themeId) {
  themeStore.setTheme(themeId);
  showThemeDropdown.value = false;
}

// Language Functions
function toggleLanguageDropdown() {
  showLanguageDropdown.value = !showLanguageDropdown.value;
}

function changeLanguage(languageCode) {
  currentLanguageCode.value = languageCode;
  setLocale(languageCode);
  showLanguageDropdown.value = false;
}

// Click outside handler for dropdowns
function handleClickOutside(event) {
  if (themeSelector.value && !themeSelector.value.contains(event.target)) {
    showThemeDropdown.value = false;
  }
  if (
    languageSelector.value &&
    !languageSelector.value.contains(event.target)
  ) {
    showLanguageDropdown.value = false;
  }
}

// Handle window resize for responsive player height
function handleResize() {
  windowWidth.value = window.innerWidth;
}

// Lifecycle
onMounted(() => {
  loadShare();
  document.addEventListener("click", handleClickOutside);
  window.addEventListener("resize", handleResize);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
  window.removeEventListener("resize", handleResize);

  // Cleanup intersection observer
  if (loadMoreTrigger.value?._observer) {
    loadMoreTrigger.value._observer.disconnect();
  }
});
</script>

<style scoped>
/* Custom scrollbar for the songs list */
.overflow-y-auto::-webkit-scrollbar {
  width: 8px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.04);
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.12);
  border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.2);
}

/* Smooth scrolling */
.overflow-y-auto {
  scroll-behavior: smooth;
}

/* Mobile touch target */
.mobile-touch-target {
  min-height: 44px;
  min-width: 44px;
}
</style>
