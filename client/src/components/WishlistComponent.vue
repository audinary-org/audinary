<template>
  <div class="flex flex-col h-full">
    <ContentHeader
      :title="$t('wishlist.title')"
      :show-search="false"
      :show-filter="false"
      :show-view-toggle="false"
    >
      <template #actions>
        <button
          @click="showNewWishModal = true"
          class="px-4 py-2 bg-audinary hover:bg-audinary-dark text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <i class="bi bi-plus-lg"></i>
          {{ $t("wishlist.new_wish") }}
        </button>
      </template>
    </ContentHeader>

    <!-- Content Area -->
    <div class="flex-1 overflow-y-auto p-6">
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center h-64">
        <div class="text-center">
          <div
            class="w-10 h-10 border-4 border-t-transparent border-blue-600 rounded-full animate-spin mx-auto"
          ></div>
          <p class="mt-3 text-white">{{ $t("wishlist.loading") }}</p>
        </div>
      </div>

      <template v-else>
        <!-- Filter Tabs -->
        <div class="max-w-4xl mx-auto mb-6">
          <div class="flex gap-2 overflow-x-auto">
            <button
              v-for="status in statusFilters"
              :key="status.value"
              @click="filterStatus = status.value"
              class="px-4 py-2 rounded-lg whitespace-nowrap transition-colors"
              :class="
                filterStatus === status.value
                  ? 'bg-audinary text-white'
                  : 'bg-white/5 text-gray-400 hover:bg-white/10'
              "
            >
              {{ status.label }}
            </button>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-if="filteredItems.length === 0"
          class="flex flex-col items-center justify-center h-64 text-gray-400"
        >
          <i class="bi bi-heart text-6xl mb-4"></i>
          <p class="text-xl">{{ $t("wishlist.empty") }}</p>
          <p class="text-sm mt-2">{{ $t("wishlist.empty_hint") }}</p>
        </div>

        <!-- Wishlist Items -->
        <div v-else class="max-w-4xl mx-auto space-y-4">
          <div
            v-for="item in filteredItems"
            :key="item.id"
            class="bg-white/5 border border-white/10 rounded-lg p-4 hover:bg-white/10 transition-colors"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-white">
                  {{ item.artist }}
                </h3>
                <p v-if="item.album" class="text-gray-400 mt-1">
                  {{ item.album }}
                </p>
                <p v-if="item.user_comment" class="text-gray-300 text-sm mt-2">
                  {{ item.user_comment }}
                </p>

                <!-- Status Badge -->
                <div class="mt-3">
                  <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                    :class="getStatusClass(item.status)"
                  >
                    <i :class="getStatusIcon(item.status)" class="mr-1"></i>
                    {{ getStatusLabel(item.status) }}
                  </span>
                </div>

                <!-- Admin Comment -->
                <div
                  v-if="item.admin_comment"
                  class="mt-3 p-3 bg-blue-900/20 border border-blue-500/30 rounded-lg"
                >
                  <p class="text-sm text-blue-300">
                    <i class="bi bi-info-circle mr-2"></i>
                    <strong>{{ $t("wishlist.admin_note") }}:</strong>
                    {{ item.admin_comment }}
                  </p>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex gap-2 ml-4">
                <button
                  @click="editItem(item)"
                  class="p-2 text-blue-400 hover:bg-white/10 rounded transition-colors"
                  :title="$t('wishlist.edit')"
                >
                  <i class="bi bi-pencil"></i>
                </button>
                <button
                  @click="deleteItem(item.id)"
                  class="p-2 text-red-400 hover:bg-white/10 rounded transition-colors"
                  :title="$t('wishlist.delete')"
                >
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </div>

            <div class="text-xs text-gray-500 mt-3">
              {{ $t("wishlist.created") }}: {{ formatDate(item.created_at) }}
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- New/Edit Wish Modal -->
    <div
      v-if="showNewWishModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="closeModal"
    >
      <div
        class="bg-gray-900 border border-white/20 rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto"
      >
        <div class="p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-white">
              {{
                editingItem ? $t("wishlist.edit_wish") : $t("wishlist.new_wish")
              }}
            </h2>
            <button
              @click="closeModal"
              class="text-gray-400 hover:text-white transition-colors"
            >
              <i class="bi bi-x-lg text-2xl"></i>
            </button>
          </div>

          <!-- Search Toggle -->
          <div v-if="configStore.isLastfmConfigured" class="mb-6">
            <div class="flex gap-2 mb-4">
              <button
                @click="searchMode = true"
                class="flex-1 px-4 py-2 rounded-lg transition-colors"
                :class="
                  searchMode
                    ? 'bg-audinary text-white'
                    : 'bg-white/5 text-gray-400 hover:bg-white/10'
                "
              >
                <i class="bi bi-search mr-2"></i>
                {{ $t("wishlist.search_lastfm") }}
              </button>
              <button
                @click="searchMode = false"
                class="flex-1 px-4 py-2 rounded-lg transition-colors"
                :class="
                  !searchMode
                    ? 'bg-audinary text-white'
                    : 'bg-white/5 text-gray-400 hover:bg-white/10'
                "
              >
                <i class="bi bi-pencil mr-2"></i>
                {{ $t("wishlist.manual_entry") }}
              </button>
            </div>
          </div>

          <!-- Last.fm Search -->
          <div v-if="searchMode" class="mb-6">
            <!-- Search Type Toggle -->
            <div class="flex gap-2 mb-3">
              <button
                @click="searchType = 'artist'"
                class="flex-1 px-3 py-2 text-sm rounded-lg transition-colors"
                :class="
                  searchType === 'artist'
                    ? 'bg-audinary/20 text-audinary border border-audinary/50'
                    : 'bg-white/5 text-gray-400 border border-white/10 hover:bg-white/10'
                "
              >
                <i class="bi bi-person mr-1"></i>
                {{ $t("wishlist.search_artist") }}
              </button>
              <button
                @click="searchType = 'album'"
                class="flex-1 px-3 py-2 text-sm rounded-lg transition-colors"
                :class="
                  searchType === 'album'
                    ? 'bg-audinary/20 text-audinary border border-audinary/50'
                    : 'bg-white/5 text-gray-400 border border-white/10 hover:bg-white/10'
                "
              >
                <i class="bi bi-disc mr-1"></i>
                {{ $t("wishlist.search_album") }}
              </button>
            </div>

            <div class="flex gap-2 mb-4">
              <input
                v-model="searchQuery"
                type="text"
                class="flex-1 px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary"
                :placeholder="
                  searchType === 'artist'
                    ? $t('wishlist.search_artist_placeholder')
                    : $t('wishlist.search_album_placeholder')
                "
                @keyup.enter="searchLastfm"
              />
              <button
                @click="searchLastfm"
                :disabled="!searchQuery || searching"
                class="px-4 py-2 bg-audinary hover:bg-audinary-dark text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="bi bi-search"></i>
              </button>
            </div>

            <!-- Search Results -->
            <div
              v-if="searchResults.length > 0"
              class="space-y-2 max-h-64 overflow-y-auto"
            >
              <div
                v-for="(result, index) in searchResults"
                :key="index"
                @click="selectSearchResult(result)"
                class="p-3 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg cursor-pointer transition-colors"
              >
                <div class="font-medium text-white">{{ result.artist }}</div>
                <div v-if="result.album" class="text-sm text-gray-400">
                  {{ result.album }}
                </div>
              </div>
            </div>

            <div v-else-if="searching" class="text-center text-gray-400 py-8">
              <i class="bi bi-hourglass-split animate-spin"></i>
              {{ $t("wishlist.searching") }}
            </div>
          </div>

          <!-- Manual Entry / Edit Form -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">
                {{ $t("wishlist.artist") }}
                <span class="text-red-400">*</span>
              </label>
              <input
                v-model="formData.artist"
                type="text"
                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary"
                :placeholder="$t('wishlist.artist_placeholder')"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">
                {{ $t("wishlist.album") }}
              </label>
              <input
                v-model="formData.album"
                type="text"
                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary"
                :placeholder="$t('wishlist.album_placeholder')"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">
                {{ $t("wishlist.comment") }}
              </label>
              <textarea
                v-model="formData.user_comment"
                rows="3"
                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary resize-none"
                :placeholder="$t('wishlist.comment_placeholder')"
              ></textarea>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-3 mt-6">
            <button
              @click="closeModal"
              class="px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg transition-colors"
            >
              {{ $t("common.cancel") }}
            </button>
            <button
              @click="saveWish"
              :disabled="!formData.artist || saving"
              class="px-4 py-2 bg-audinary hover:bg-audinary-dark text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i
                v-if="saving"
                class="bi bi-hourglass-split animate-spin mr-2"
              ></i>
              {{ editingItem ? $t("common.save") : $t("wishlist.add_wish") }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "@/stores/auth";
import { useAlertStore } from "@/stores/alert";
import { useConfigStore } from "@/stores/config";
import ContentHeader from "./common/ContentHeader.vue";

const { t } = useI18n();
const authStore = useAuthStore();
const alertStore = useAlertStore();
const configStore = useConfigStore();

const loading = ref(false);
const wishlistItems = ref([]);
const filterStatus = ref("active"); // Default: show only pending and in_progress
const showNewWishModal = ref(false);
const searchMode = ref(true);
const searchType = ref("artist");
const searchQuery = ref("");
const searching = ref(false);
const searchResults = ref([]);
const saving = ref(false);
const editingItem = ref(null);

const formData = ref({
  artist: "",
  album: "",
  user_comment: "",
  lastfm_artist_mbid: null,
  lastfm_album_mbid: null,
});

const statusFilters = computed(() => [
  { value: "active", label: t("wishlist.filter.active") },
  { value: "all", label: t("wishlist.filter.all") },
  { value: "pending", label: t("wishlist.status.pending") },
  { value: "in_progress", label: t("wishlist.status.in_progress") },
  { value: "completed", label: t("wishlist.status.completed") },
  { value: "rejected", label: t("wishlist.status.rejected") },
]);

const filteredItems = computed(() => {
  if (filterStatus.value === "all") {
    return wishlistItems.value;
  }
  if (filterStatus.value === "active") {
    // Show only pending and in_progress (exclude completed and rejected)
    return wishlistItems.value.filter(
      (item) => item.status === "pending" || item.status === "in_progress",
    );
  }
  return wishlistItems.value.filter(
    (item) => item.status === filterStatus.value,
  );
});

onMounted(() => {
  // Only load if authenticated
  if (authStore.isAuthenticated && authStore.isInitialized) {
    loadWishlist();
  }
});

async function loadWishlist() {
  if (!authStore.isAuthenticated || !authStore.token) {
    return;
  }

  loading.value = true;
  try {
    const response = await fetch("/api/wishlist", {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
      },
    });
    const data = await response.json();
    if (response.ok && data.success) {
      wishlistItems.value = data.items || [];
    } else {
      throw new Error(data.error || "Failed to load wishlist");
    }
  } catch (error) {
    console.error("Failed to load wishlist:", error);
    alertStore.addAlert(t("wishlist.load_error"), "error", 5000);
  } finally {
    loading.value = false;
  }
}

async function searchLastfm() {
  if (!searchQuery.value) return;

  searching.value = true;
  searchResults.value = [];

  try {
    const url = `/api/wishlist/search/lastfm?q=${encodeURIComponent(searchQuery.value)}&type=${searchType.value}`;
    const response = await fetch(url, {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
      },
    });
    const data = await response.json();

    if (response.ok && data.success && data.results) {
      if (
        searchType.value === "artist" &&
        data.results.results &&
        data.results.results.artistmatches
      ) {
        searchResults.value = data.results.results.artistmatches.artist.map(
          (artist) => ({
            artist: artist.name,
            album: null,
            mbid: artist.mbid,
            listeners: artist.listeners,
          }),
        );
      } else if (
        searchType.value === "album" &&
        data.results.results &&
        data.results.results.albummatches
      ) {
        searchResults.value = data.results.results.albummatches.album.map(
          (album) => ({
            artist: album.artist,
            album: album.name,
            mbid: album.mbid,
            listeners: album.listeners,
          }),
        );
      }
    }
  } catch (error) {
    console.error("Last.fm search failed:", error);
    alertStore.addAlert(t("wishlist.search_error"), "error", 5000);
  } finally {
    searching.value = false;
  }
}

function selectSearchResult(result) {
  formData.value.artist = result.artist || "";
  formData.value.album = result.album || "";

  // For artist search, mbid goes to artist_mbid
  // For album search, mbid goes to album_mbid and artist stays in artist field
  if (searchType.value === "artist") {
    formData.value.lastfm_artist_mbid = result.mbid || null;
    formData.value.lastfm_album_mbid = null;
  } else if (searchType.value === "album") {
    formData.value.lastfm_album_mbid = result.mbid || null;
    // Artist MBID not available in album search results
    formData.value.lastfm_artist_mbid = null;
  }

  searchMode.value = false;
  searchResults.value = [];
}

async function saveWish() {
  if (!formData.value.artist) return;

  saving.value = true;
  try {
    let response;
    if (editingItem.value) {
      response = await fetch(`/api/wishlist/${editingItem.value.id}`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${authStore.token}`,
        },
        body: JSON.stringify(formData.value),
      });
      const data = await response.json();
      if (response.ok && data.success) {
        alertStore.addAlert(t("wishlist.update_success"), "success", 3000);
      } else {
        throw new Error(data.error || "Failed to update");
      }
    } else {
      response = await fetch("/api/wishlist", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${authStore.token}`,
        },
        body: JSON.stringify(formData.value),
      });
      const data = await response.json();
      if (response.ok && data.success) {
        alertStore.addAlert(t("wishlist.add_success"), "success", 3000);
      } else {
        throw new Error(data.error || "Failed to add");
      }
    }

    closeModal();
    loadWishlist();
  } catch (error) {
    console.error("Failed to save wish:", error);
    alertStore.addAlert(t("wishlist.save_error"), "error", 5000);
  } finally {
    saving.value = false;
  }
}

function editItem(item) {
  editingItem.value = item;
  formData.value = {
    artist: item.artist,
    album: item.album || "",
    user_comment: item.user_comment || "",
    lastfm_artist_mbid: item.lastfm_artist_mbid,
    lastfm_album_mbid: item.lastfm_album_mbid,
  };
  searchMode.value = false;
  showNewWishModal.value = true;
}

async function deleteItem(id) {
  if (!confirm(t("wishlist.delete_confirm"))) return;

  try {
    const response = await fetch(`/api/wishlist/${id}`, {
      method: "DELETE",
      headers: {
        Authorization: `Bearer ${authStore.token}`,
      },
    });
    const data = await response.json();
    if (response.ok && data.success) {
      alertStore.addAlert(t("wishlist.delete_success"), "success", 3000);
      loadWishlist();
    } else {
      throw new Error(data.error || "Failed to delete");
    }
  } catch (error) {
    console.error("Failed to delete wish:", error);
    alertStore.addAlert(t("wishlist.delete_error"), "error", 5000);
  }
}

function closeModal() {
  showNewWishModal.value = false;
  editingItem.value = null;
  formData.value = {
    artist: "",
    album: "",
    user_comment: "",
    lastfm_artist_mbid: null,
    lastfm_album_mbid: null,
  };
  searchQuery.value = "";
  searchResults.value = [];
  searchMode.value = true;
}

function getStatusClass(status) {
  const classes = {
    pending: "bg-yellow-900/30 text-yellow-300 border border-yellow-500/30",
    in_progress: "bg-blue-900/30 text-blue-300 border border-blue-500/30",
    completed: "bg-green-900/30 text-green-300 border border-green-500/30",
    rejected: "bg-red-900/30 text-red-300 border border-red-500/30",
  };
  return classes[status] || classes.pending;
}

function getStatusIcon(status) {
  const icons = {
    pending: "bi bi-clock",
    in_progress: "bi bi-hourglass-split",
    completed: "bi bi-check-circle",
    rejected: "bi bi-x-circle",
  };
  return icons[status] || icons.pending;
}

function getStatusLabel(status) {
  const labels = {
    pending: t("wishlist.status.pending"),
    in_progress: t("wishlist.status.in_progress"),
    completed: t("wishlist.status.completed"),
    rejected: t("wishlist.status.rejected"),
  };
  return labels[status] || status;
}

function formatDate(dateString) {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleDateString() + " " + date.toLocaleTimeString();
}
</script>
