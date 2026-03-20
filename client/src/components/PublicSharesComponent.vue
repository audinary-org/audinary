<template>
  <div class="flex flex-col h-full">
    <ContentHeader
      :title="$t('shares.manage_shares')"
      v-model:searchQuery="searchQuery"
      @update:searchQuery="onSearchInput"
      :show-search="true"
      :show-filter="true"
      :filters-open="showFilters"
      @toggle-filters="toggleFilters"
      :active-filters-count="activeFiltersCount"
      :show-view-toggle="false"
    >
      <template #actions>
        <!-- Refresh Button -->
        <button
          @click="loadShares"
          :disabled="loading"
          class="inline-flex items-center gap-2 px-3 py-1 border border-white/20 rounded text-white hover:bg-white/10"
        >
          <i
            class="bi bi-arrow-clockwise"
            :class="{ 'animate-spin': loading }"
          ></i>
        </button>
      </template>

      <template #filters>
        <div class="p-4 space-y-4">
          <!-- Type Filter -->
          <div>
            <label class="block text-sm font-medium text-white mb-2">
              {{ $t("shares.filter_by_type") }}
            </label>
            <select
              v-model="filters.type"
              @change="onFiltersChanged"
              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
            >
              <option value="">{{ $t("common.all") }}</option>
              <option value="song">{{ $t("shares.sharing_type_song") }}</option>
              <option value="album">
                {{ $t("shares.sharing_type_album") }}
              </option>
              <option value="playlist">
                {{ $t("shares.sharing_type_playlist") }}
              </option>
            </select>
          </div>

          <!-- Status Filter -->
          <div>
            <label class="block text-sm font-medium text-white mb-2">
              {{ $t("shares.filter_by_status") }}
            </label>
            <select
              v-model="filters.status"
              @change="onFiltersChanged"
              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
            >
              <option value="">{{ $t("common.all") }}</option>
              <option value="active">{{ $t("shares.status.active") }}</option>
              <option value="expired">{{ $t("shares.status.expired") }}</option>
              <option value="password_protected">
                {{ $t("shares.status.password_protected") }}
              </option>
            </select>
          </div>

          <!-- Admin: All users toggle -->
          <div v-if="authStore.isAdmin">
            <label class="flex items-center space-x-2">
              <input
                type="checkbox"
                v-model="showAllUsers"
                @change="onFiltersChanged"
                class="rounded border-white/20 bg-white/10 text-audinary focus:ring-audinary focus:ring-offset-0"
              />
              <span class="text-sm text-white">{{
                $t("shares.show_all_users")
              }}</span>
            </label>
          </div>

          <!-- Clear Filters -->
          <button
            @click="clearFilters"
            class="w-full px-3 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg transition-colors"
          >
            {{ $t("common.clear_filters") }}
          </button>
        </div>
      </template>
    </ContentHeader>

    <!-- Shares Content Area (scrollable) -->
    <div class="flex-1 overflow-y-auto py-4">
      <!-- Loading State -->
      <div
        v-if="loading && filteredShares.length === 0"
        class="text-center py-8"
      >
        <div
          class="animate-spin w-8 h-8 border-4 border-gray-600 border-t-audinary rounded-full mx-auto"
        >
          <span class="sr-only">{{ $t("common.loading") }}</span>
        </div>
        <p class="mt-3 text-gray-400">{{ $t("common.loading") }}...</p>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="!loading && filteredShares.length === 0"
        class="text-center py-8"
      >
        <i class="bi bi-share text-5xl text-gray-400 mb-4"></i>
        <h3 class="text-xl font-medium text-white mb-2">
          {{ $t("shares.no_shares") }}
        </h3>
        <p class="text-gray-400 mb-4">
          {{ $t("shares.no_shares_description") }}
        </p>
      </div>

      <!-- Shares List -->
      <div v-else class="space-y-2">
        <div
          v-for="share in filteredShares"
          :key="share.id"
          class="flex items-center p-2 rounded-md group bg-white/10 backdrop-blur-lg shadow-lg hover:bg-white/20 transition-all"
        >
          <!-- Type Icon -->
          <div
            class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full bg-white/5 mr-3"
          >
            <i
              class="text-lg text-gray-400"
              :class="getTypeIcon(share.type)"
            ></i>
          </div>

          <!-- Share Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="text-audinary font-medium truncate">
                {{ getShareTitle(share) }}
              </h3>
              <!-- Status Badges -->
              <span
                v-if="share.is_expired"
                class="px-2 py-0.5 text-xs rounded bg-white/10 text-gray-400"
              >
                {{ $t("shares.status.expired") }}
              </span>
              <span
                v-if="share.has_password"
                class="px-2 py-0.5 text-xs rounded bg-white/10 text-gray-400"
              >
                <i class="bi bi-lock text-xs"></i>
              </span>
              <span
                v-if="share.download_enabled"
                class="px-2 py-0.5 text-xs rounded bg-white/10 text-gray-400"
              >
                <i class="bi bi-download text-xs"></i>
              </span>
            </div>
            <div class="flex items-center gap-3 text-sm text-gray-400 mt-1">
              <span>{{ getTypeLabel(share.type) }}</span>
              <span class="flex items-center gap-1">
                <i class="bi bi-eye text-xs"></i>
                {{ share.access_count || 0 }}
              </span>
              <span>{{ formatDate(share.created_at) }}</span>
              <span v-if="share.expires_at" class="flex items-center gap-1">
                <i class="bi bi-clock text-xs"></i>
                {{ formatDate(share.expires_at) }}
              </span>
              <span
                v-if="
                  authStore.isAdmin &&
                  share.created_by !== authStore.user?.user_id
                "
                class="flex items-center gap-1"
              >
                <i class="bi bi-person text-xs"></i>
                {{ share.created_by }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex-shrink-0 flex items-center gap-2">
            <!-- Copy URL -->
            <button
              @click="copyShareUrl(share)"
              class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
              :title="$t('shares.copy_url')"
            >
              <i class="bi bi-link-45deg text-white"></i>
            </button>

            <!-- Edit -->
            <button
              @click="editShare(share)"
              class="w-8 h-8 bg-audinary/30 hover:bg-audinary/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
              :title="$t('common.edit')"
            >
              <i class="bi bi-pencil text-white"></i>
            </button>

            <!-- Delete -->
            <button
              @click="deleteShare(share)"
              class="w-8 h-8 bg-red-500/30 hover:bg-red-500/50 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110"
              :title="$t('common.delete')"
            >
              <i class="bi bi-trash text-white"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div
        v-if="pagination && pagination.total > pagination.limit"
        class="mt-6 flex items-center justify-between px-4"
      >
        <div class="text-sm text-gray-400">
          {{
            $t("common.showing_results", {
              start: pagination.offset + 1,
              end: Math.min(
                pagination.offset + pagination.limit,
                pagination.total,
              ),
              total: pagination.total,
            })
          }}
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="loadPreviousPage"
            :disabled="pagination.offset === 0"
            class="px-3 py-1 border border-white/20 rounded text-white hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {{ $t("common.previous") }}
          </button>
          <button
            @click="loadNextPage"
            :disabled="pagination.offset + pagination.limit >= pagination.total"
            class="px-3 py-1 border border-white/20 rounded text-white hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {{ $t("common.next") }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Share Modal -->
  <EditShareModal
    v-if="editingShare"
    :share="editingShare"
    @close="editingShare = null"
    @share-updated="onShareUpdated"
  />

  <!-- Delete Confirmation Modal -->
  <ConfirmationModal
    v-if="deletingShare"
    :title="$t('shares.delete_share')"
    :message="$t('shares.delete_confirmation')"
    :confirmText="$t('common.delete')"
    :cancelText="$t('common.cancel')"
    variant="danger"
    @confirm="confirmDelete"
    @cancel="deletingShare = null"
  />
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useAlertStore } from "@/stores/alert";
import { useI18n } from "vue-i18n";
import { useClipboard } from "@/composables/useClipboard";
import ContentHeader from "@/components/common/ContentHeader.vue";
import EditShareModal from "./modals/PublicSharesEditModal.vue";
import ConfirmationModal from "@/components/common/ConfirmationModal.vue";

const { t } = useI18n();
const authStore = useAuthStore();
const alertStore = useAlertStore();
const { copyToClipboard } = useClipboard();

// State
const shares = ref([]);
const stats = ref(null);
const loading = ref(false);
const pagination = ref(null);
const editingShare = ref(null);
const deletingShare = ref(null);
const searchQuery = ref("");
const showFilters = ref(false);

const filters = ref({
  type: "",
  status: "",
});

const showAllUsers = ref(false);

// Computed
const filteredShares = computed(() => {
  if (!searchQuery.value) return shares.value;

  const query = searchQuery.value.toLowerCase();
  return shares.value.filter(
    (share) =>
      getShareTitle(share).toLowerCase().includes(query) ||
      share.type?.toLowerCase().includes(query),
  );
});

const activeFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.type) count++;
  if (filters.value.status) count++;
  if (showAllUsers.value) count++;
  if (searchQuery.value) count++;
  return count;
});

// Methods
function toggleFilters() {
  showFilters.value = !showFilters.value;
}

function onFiltersChanged() {
  loadShares();
}

function clearFilters() {
  filters.value = {
    type: "",
    status: "",
  };
  showAllUsers.value = false;
  searchQuery.value = "";
  loadShares();
}

function onSearchInput() {
  // Search is reactive via computed
}

async function loadShares(offset = 0) {
  loading.value = true;

  try {
    const params = new URLSearchParams({
      offset: offset.toString(),
      limit: "50",
    });

    if (filters.value.type) {
      params.append("type", filters.value.type);
    }

    if (filters.value.status) {
      params.append("status", filters.value.status);
    }

    if (authStore.isAdmin && showAllUsers.value) {
      params.append("all", "true");
    }

    const response = await fetch(`/api/shares?${params}`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
      },
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      console.error("Error loading shares:", data.error);
      alertStore.addAlert(data.error || "Failed to load shares", "error", 5000);
      return;
    }

    shares.value = data.shares;
    pagination.value = data.pagination;
  } catch (error) {
    console.error("Error loading shares:", error);
    alertStore.addAlert("Failed to load shares", "error", 5000);
  } finally {
    loading.value = false;
  }
}

async function loadStats() {
  try {
    const response = await fetch("/api/shares/stats", {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
      },
    });

    const data = await response.json();

    if (response.ok && data.success) {
      stats.value = data.stats;
    }
  } catch (error) {
    console.error("Error loading share stats:", error);
  }
}

function loadPreviousPage() {
  if (pagination.value && pagination.value.offset > 0) {
    const newOffset = Math.max(
      0,
      pagination.value.offset - pagination.value.limit,
    );
    loadShares(newOffset);
  }
}

function loadNextPage() {
  if (
    pagination.value &&
    pagination.value.offset + pagination.value.limit < pagination.value.total
  ) {
    const newOffset = pagination.value.offset + pagination.value.limit;
    loadShares(newOffset);
  }
}

function getTypeIcon(type) {
  const icons = {
    song: "bi-music-note-beamed",
    album: "bi-disc",
    playlist: "bi-music-note-list",
  };
  return icons[type] || "bi-share";
}

function getShareTitle(share) {
  return share.name || `${share.type} - ${share.item_id}`;
}

async function copyShareUrl(share) {
  const url = `${window.location.origin}/share/${share.share_uuid}`;

  await copyToClipboard(url, {
    onSuccess: () => {
      alertStore.addAlert(t("shares.url_copied_success"), "success", 3000);
    },
    onError: (error) => {
      console.error("Failed to copy URL:", error);
      alertStore.addAlert(t("shares.url_copy_failed"), "error", 5000);
    },
  });
}

function editShare(share) {
  editingShare.value = share;
}

function deleteShare(share) {
  deletingShare.value = share;
}

async function confirmDelete() {
  if (!deletingShare.value) return;

  try {
    const response = await fetch(`/api/shares/${deletingShare.value.id}`, {
      method: "DELETE",
      headers: {
        Authorization: `Bearer ${authStore.token}`,
      },
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      console.error("Error deleting share:", data.error);
      alertStore.addAlert(
        data.error || "Failed to delete share",
        "error",
        5000,
      );
      return;
    }

    // Remove from local list
    shares.value = shares.value.filter((s) => s.id !== deletingShare.value.id);

    alertStore.addAlert("Share deleted successfully", "success", 3000);

    // Reload stats
    loadStats();
  } catch (error) {
    console.error("Error deleting share:", error);
    alertStore.addAlert("Failed to delete share", "error", 5000);
  } finally {
    deletingShare.value = null;
  }
}

function onShareUpdated(updatedShare) {
  // Update local share
  const index = shares.value.findIndex((s) => s.id === updatedShare.id);
  if (index !== -1) {
    shares.value[index] = updatedShare;
  }
  editingShare.value = null;
}

function formatDate(dateStr) {
  if (!dateStr) return "";

  try {
    return new Date(dateStr).toLocaleString();
  } catch {
    return dateStr;
  }
}

function getTypeLabel(type) {
  const typeKeys = {
    song: "shares.sharing_type_song",
    album: "shares.sharing_type_album",
    playlist: "shares.sharing_type_playlist",
  };
  return t(typeKeys[type]);
}

// Lifecycle
onMounted(() => {
  loadShares();
  loadStats();
});
</script>

<style scoped>
/* Performance optimizations for large lists */
.group {
  /* Browser automatically manages rendering for off-screen elements */
  content-visibility: auto;

  /* Reserve space to prevent layout shift */
  contain-intrinsic-size: 80px;

  /* Isolate this element's style recalculations */
  contain: layout style paint;

  /* Hint to browser that transforms will be animated */
  will-change: transform;
}

/* Optimize hover effects */
.group:hover {
  /* Use transform instead of changing other properties for better performance */
  transform: translateZ(0);
}
</style>
