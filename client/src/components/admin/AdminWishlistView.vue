<template>
  <div class="admin-wishlist h-full flex flex-col">
    <ContentHeader
      :title="$t('admin.wishlist.title')"
      :show-search="false"
      :show-filter="false"
      :show-view-toggle="false"
    >
    </ContentHeader>

    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <div class="text-center">
        <div
          class="w-10 h-10 border-4 border-t-transparent border-blue-600 rounded-full animate-spin mx-auto"
        ></div>
        <p class="mt-3 text-white">{{ $t("admin.wishlist.loading") }}</p>
      </div>
    </div>

    <div v-else class="flex-1 overflow-y-auto p-6">
      <!-- Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div
          class="bg-yellow-900/20 border border-yellow-500/30 rounded-lg p-4"
        >
          <div class="text-yellow-300 text-sm font-medium">
            {{ $t("admin.wishlist.stats.pending") }}
          </div>
          <div class="text-3xl font-bold text-white mt-2">
            {{ stats.pending || 0 }}
          </div>
        </div>
        <div class="bg-blue-900/20 border border-blue-500/30 rounded-lg p-4">
          <div class="text-blue-300 text-sm font-medium">
            {{ $t("admin.wishlist.stats.in_progress") }}
          </div>
          <div class="text-3xl font-bold text-white mt-2">
            {{ stats.in_progress || 0 }}
          </div>
        </div>
        <div class="bg-green-900/20 border border-green-500/30 rounded-lg p-4">
          <div class="text-green-300 text-sm font-medium">
            {{ $t("admin.wishlist.stats.completed") }}
          </div>
          <div class="text-3xl font-bold text-white mt-2">
            {{ stats.completed || 0 }}
          </div>
        </div>
        <div class="bg-red-900/20 border border-red-500/30 rounded-lg p-4">
          <div class="text-red-300 text-sm font-medium">
            {{ $t("admin.wishlist.stats.rejected") }}
          </div>
          <div class="text-3xl font-bold text-white mt-2">
            {{ stats.rejected || 0 }}
          </div>
        </div>
      </div>

      <!-- Filter Tabs -->
      <div class="flex gap-2 mb-6 overflow-x-auto">
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

      <!-- Wishlist Items -->
      <div
        v-if="filteredItems.length === 0"
        class="text-center text-gray-400 py-12"
      >
        <i class="bi bi-inbox text-6xl mb-4"></i>
        <p>{{ $t("admin.wishlist.no_items") }}</p>
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="item in filteredItems"
          :key="item.id"
          class="bg-white/5 border border-white/10 rounded-lg p-4"
        >
          <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-2">
                <h3 class="text-lg font-semibold text-white">
                  {{ item.artist }}
                </h3>
                <span
                  class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                  :class="getStatusClass(item.status)"
                >
                  {{ getStatusLabel(item.status) }}
                </span>
              </div>

              <p v-if="item.album" class="text-gray-400">{{ item.album }}</p>

              <div class="text-sm text-gray-500 mt-2">
                <i class="bi bi-person mr-1"></i>
                {{ item.username }}
                <span class="mx-2">•</span>
                <i class="bi bi-calendar mr-1"></i>
                {{ formatDate(item.created_at) }}
              </div>

              <p v-if="item.user_comment" class="text-gray-300 text-sm mt-3">
                <i class="bi bi-chat-left-text mr-2"></i>
                {{ item.user_comment }}
              </p>

              <div
                v-if="item.admin_comment"
                class="mt-3 p-3 bg-blue-900/20 border border-blue-500/30 rounded-lg"
              >
                <p class="text-sm text-blue-300">
                  <i class="bi bi-shield-check mr-2"></i>
                  <strong>{{ $t("admin.wishlist.admin_comment") }}:</strong>
                  {{ item.admin_comment }}
                </p>
              </div>
            </div>

            <div class="flex gap-2 ml-4">
              <button
                @click="openEditModal(item)"
                class="p-2 text-blue-400 hover:bg-white/10 rounded transition-colors"
                :title="$t('admin.wishlist.edit_status')"
              >
                <i class="bi bi-pencil-square"></i>
              </button>
              <button
                @click="deleteItem(item.id)"
                class="p-2 text-red-400 hover:bg-white/10 rounded transition-colors"
                :title="$t('common.delete')"
              >
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Status Modal -->
    <div
      v-if="showEditModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="closeEditModal"
    >
      <div
        class="bg-gray-900 border border-white/20 rounded-lg max-w-xl w-full"
      >
        <div class="p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-white">
              {{ $t("admin.wishlist.update_status") }}
            </h2>
            <button
              @click="closeEditModal"
              class="text-gray-400 hover:text-white transition-colors"
            >
              <i class="bi bi-x-lg text-2xl"></i>
            </button>
          </div>

          <div class="mb-4">
            <div class="text-white font-semibold mb-1">
              {{ editingItem.artist }}
            </div>
            <div v-if="editingItem.album" class="text-gray-400 text-sm">
              {{ editingItem.album }}
            </div>
            <div class="text-gray-500 text-sm mt-1">
              {{ $t("admin.wishlist.requested_by") }}:
              {{ editingItem.username }}
            </div>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">
                {{ $t("admin.wishlist.status") }}
              </label>
              <select v-model="editForm.status" class="w-full">
                <option value="pending">
                  {{ $t("wishlist.status.pending") }}
                </option>
                <option value="in_progress">
                  {{ $t("wishlist.status.in_progress") }}
                </option>
                <option value="completed">
                  {{ $t("wishlist.status.completed") }}
                </option>
                <option value="rejected">
                  {{ $t("wishlist.status.rejected") }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">
                {{ $t("admin.wishlist.admin_comment") }}
              </label>
              <textarea
                v-model="editForm.admin_comment"
                rows="3"
                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary resize-none"
                :placeholder="$t('admin.wishlist.admin_comment_placeholder')"
              ></textarea>
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button
              @click="closeEditModal"
              class="px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg transition-colors"
            >
              {{ $t("common.cancel") }}
            </button>
            <button
              @click="updateStatus"
              :disabled="updating"
              class="px-4 py-2 bg-audinary hover:bg-audinary-dark text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i
                v-if="updating"
                class="bi bi-hourglass-split animate-spin mr-2"
              ></i>
              {{ $t("common.save") }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onActivated } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "@/stores/auth";
import { useAlertStore } from "@/stores/alert";
import ContentHeader from "../common/ContentHeader.vue";

const { t } = useI18n();
const authStore = useAuthStore();
const alertStore = useAlertStore();

const loading = ref(false);
const wishlistItems = ref([]);
const stats = ref({});
const filterStatus = ref("all");
const showEditModal = ref(false);
const editingItem = ref(null);
const updating = ref(false);

const editForm = ref({
  status: "",
  admin_comment: "",
});

const statusFilters = computed(() => [
  { value: "all", label: t("admin.wishlist.filter.all") },
  { value: "pending", label: t("wishlist.status.pending") },
  { value: "in_progress", label: t("wishlist.status.in_progress") },
  { value: "completed", label: t("wishlist.status.completed") },
  { value: "rejected", label: t("wishlist.status.rejected") },
]);

const filteredItems = computed(() => {
  if (filterStatus.value === "all") {
    return wishlistItems.value;
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

// Reload when component becomes visible again (for keep-alive components)
onActivated(() => {
  if (authStore.isAuthenticated && authStore.isInitialized) {
    loadWishlist();
  }
});

// Expose loadWishlist so parent can call it
defineExpose({
  loadWishlist,
});

async function loadWishlist() {
  if (!authStore.isAuthenticated || !authStore.token) {
    return;
  }

  loading.value = true;
  try {
    let url = "/api/admin/wishlist";
    if (filterStatus.value !== "all") {
      url += `?status=${filterStatus.value}`;
    }

    const response = await fetch(url, {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
      },
    });
    const data = await response.json();
    if (response.ok && data.success) {
      wishlistItems.value = data.items || [];
      stats.value = data.stats || {};
    } else {
      throw new Error(data.error || "Failed to load wishlist");
    }
  } catch (error) {
    console.error("Failed to load wishlist:", error);
    alertStore.addAlert(t("admin.wishlist.load_error"), "error", 5000);
  } finally {
    loading.value = false;
  }
}

function openEditModal(item) {
  editingItem.value = item;
  editForm.value = {
    status: item.status,
    admin_comment: item.admin_comment || "",
  };
  showEditModal.value = true;
}

function closeEditModal() {
  showEditModal.value = false;
  editingItem.value = null;
  editForm.value = {
    status: "",
    admin_comment: "",
  };
}

async function updateStatus() {
  if (!editingItem.value) return;

  updating.value = true;
  try {
    const response = await fetch(
      `/api/admin/wishlist/${editingItem.value.id}/status`,
      {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${authStore.token}`,
        },
        body: JSON.stringify(editForm.value),
      },
    );
    const data = await response.json();
    if (response.ok && data.success) {
      alertStore.addAlert(t("admin.wishlist.update_success"), "success", 3000);
      closeEditModal();
      loadWishlist();
    } else {
      throw new Error(data.error || "Failed to update");
    }
  } catch (error) {
    console.error("Failed to update status:", error);
    alertStore.addAlert(t("admin.wishlist.update_error"), "error", 5000);
  } finally {
    updating.value = false;
  }
}

async function deleteItem(id) {
  if (!confirm(t("admin.wishlist.delete_confirm"))) return;

  try {
    const response = await fetch(`/api/admin/wishlist/${id}`, {
      method: "DELETE",
      headers: {
        Authorization: `Bearer ${authStore.token}`,
      },
    });
    const data = await response.json();
    if (response.ok && data.success) {
      alertStore.addAlert(t("admin.wishlist.delete_success"), "success", 3000);
      loadWishlist();
    } else {
      throw new Error(data.error || "Failed to delete");
    }
  } catch (error) {
    console.error("Failed to delete item:", error);
    alertStore.addAlert(t("admin.wishlist.delete_error"), "error", 5000);
  }
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
