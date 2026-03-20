<template>
  <div class="admin-backup-view flex flex-col h-full p-0 m-0">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h4 class="text-white mb-1">{{ t("admin.backup.title") }}</h4>
        <p class="text-gray-400 mb-0">{{ t("admin.backup.description") }}</p>
      </div>
      <div class="flex gap-2">
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          @change="handleFileUpload"
          accept=".tar.gz,.tgz"
        />
        <button
          @click="$refs.fileInput.click()"
          :disabled="isLoading || isUploading"
          class="bg-gray-700 text-gray-100 px-3 py-2 rounded hover:bg-gray-600"
        >
          <i class="bi bi-upload" v-if="!isUploading"></i>
          <i class="bi bi-arrow-clockwise animate-spin" v-if="isUploading"></i>
          <span class="hidden md:inline">{{
            isUploading
              ? t("admin.backup.uploading")
              : t("admin.backup.upload_backup")
          }}</span>
        </button>
        <button
          @click="createBackup"
          :disabled="isLoading || isCreating"
          class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700"
        >
          <i class="bi bi-plus-lg" v-if="!isCreating"></i>
          <i class="bi bi-arrow-clockwise animate-spin" v-if="isCreating"></i>
          <span class="hidden md:inline">{{
            isCreating
              ? t("admin.backup.creating")
              : t("admin.backup.create_backup")
          }}</span>
        </button>
      </div>
    </div>

    <!-- Upload Info -->
    <div
      v-if="isUploading"
      class="bg-blue-800 text-white p-3 rounded mb-3 flex items-center justify-between"
    >
      <div class="flex items-center gap-3">
        <i class="bi bi-info-circle"></i>{{ t("admin.backup.upload_info") }}
      </div>
      <div
        v-if="uploadProgress > 0 && uploadProgress < 100"
        class="flex items-center gap-2"
      >
        <div
          class="w-28 bg-gray-700 rounded overflow-hidden"
          style="height: 8px"
        >
          <div
            class="bg-blue-500 h-full"
            :style="{ width: uploadProgress + '%' }"
          ></div>
        </div>
        <small class="text-gray-100">{{ uploadProgress }}%</small>
      </div>
    </div>

    <!-- Backups Table -->
    <div class="bg-white/5 border border-white/10 rounded-lg p-4">
      <div class="flex items-center justify-between mb-3">
        <h5 class="flex items-center gap-2">
          <i class="bi bi-archive"></i
          >{{ t("admin.backup.system_backups") }} ({{ backups.length }})
        </h5>
        <button
          class="text-sm border border-gray-600 text-gray-200 px-2 py-1 rounded"
          @click="refreshBackups"
          :disabled="isLoading"
        >
          <i
            class="bi bi-arrow-clockwise"
            :class="{ 'animate-spin': isLoading }"
          ></i>
          {{ t("admin.backup.refresh") }}
        </button>
      </div>

      <div v-if="isLoading && backups.length === 0" class="text-center py-4">
        <div
          class="w-10 h-10 border-4 border-t-transparent border-blue-600 rounded-full animate-spin mx-auto"
        ></div>
      </div>
      <div
        v-else-if="backups.length === 0"
        class="text-center py-4 text-gray-400"
      >
        <i class="bi bi-archive text-3xl"></i>
        <p class="mt-2">{{ t("admin.backup.no_backups") }}</p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="backup in backups"
          :key="backup.filename"
          class="flex items-center p-3 rounded bg-white/5 border border-white/10 rounded-lg"
        >
          <div class="mr-3 text-2xl text-blue-500">
            <i class="bi bi-archive"></i>
          </div>
          <div class="flex-1">
            <div class="font-medium text-white">{{ backup.filename }}</div>
            <div class="text-gray-400 text-sm md:hidden">
              {{ backup.sizeFormatted }} • {{ formatDate(backup.created) }}
            </div>
          </div>
          <div
            class="hidden md:block text-gray-400 text-sm mr-3"
            style="width: 120px; text-align: center"
          >
            <span class="bg-gray-700 px-2 py-1 rounded">{{
              backup.sizeFormatted
            }}</span>
          </div>
          <div
            class="hidden lg:block text-gray-400 text-sm mr-3"
            style="width: 150px"
          >
            {{ formatDate(backup.created) }}
          </div>

          <div
            class="hidden md:flex gap-2"
            style="width: 140px; justify-content: center"
          >
            <button
              class="border border-sky-500 text-sky-300 px-2 py-1 rounded hover:bg-sky-500/10"
              @click="downloadBackup(backup.filename)"
            >
              <i class="bi bi-download"></i>
            </button>
            <button
              class="border border-yellow-500 text-yellow-300 px-2 py-1 rounded hover:bg-yellow-500/10"
              @click="confirmRestore(backup)"
              :disabled="isRestoring"
            >
              <i class="bi bi-arrow-counterclockwise"></i>
            </button>
            <button
              class="border border-red-600 text-red-400 px-2 py-1 rounded hover:bg-red-600/10"
              @click="confirmDelete(backup)"
              :disabled="isDeleting === backup.filename"
            >
              <i class="bi bi-trash"></i>
            </button>
          </div>

          <div class="md:hidden relative">
            <button
              class="text-gray-200 bg-white/5 border border-white/10 rounded-lg px-2 py-1"
              @click="toggleDropdown(backup.filename)"
            >
              <i class="bi bi-three-dots-vertical"></i>
            </button>
            <div
              v-if="openDropdownId === backup.filename"
              class="absolute right-0 mt-2 bg-gray-800 border border-gray-700 rounded shadow-md w-44 z-40"
            >
              <button
                class="w-full text-left px-3 py-2 text-gray-100 hover:bg-gray-700"
                @click="
                  handleDropdownAction(() => downloadBackup(backup.filename))
                "
              >
                <i class="bi bi-download mr-2"></i
                >{{ t("admin.backup.download") }}
              </button>
              <button
                class="w-full text-left px-3 py-2 text-gray-100 hover:bg-gray-700"
                @click="handleDropdownAction(() => confirmRestore(backup))"
                :disabled="isRestoring"
              >
                <i class="bi bi-arrow-counterclockwise mr-2"></i
                >{{ t("admin.backup.restore") }}
              </button>
              <div class="border-t border-gray-700"></div>
              <button
                class="w-full text-left px-3 py-2 text-red-400 hover:bg-red-700/10"
                @click="handleDropdownAction(() => confirmDelete(backup))"
                :disabled="isDeleting === backup.filename"
              >
                <i class="bi bi-trash mr-2"></i>{{ t("admin.backup.delete") }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div
      v-if="showRestoreModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    >
      <div class="bg-gray-800 text-white rounded-lg max-w-2xl w-full p-6">
        <div class="text-center mb-4">
          <i class="bi bi-exclamation-triangle text-yellow-400 text-5xl"></i>
        </div>
        <h5 class="text-lg font-semibold mb-2">
          {{ t("admin.backup.confirm_restore") }}
        </h5>
        <p class="mb-4 text-gray-300">
          {{ t("admin.backup.restore_warning") }} <br /><strong
            class="text-sky-300"
            >{{ selectedBackup?.filename }}</strong
          >
        </p>
        <div class="flex justify-end gap-2">
          <button
            class="bg-gray-700 text-white px-3 py-2 rounded"
            @click="showRestoreModal = false"
          >
            {{ t("admin.backup.cancel") }}
          </button>
          <button
            class="bg-red-600 text-white px-3 py-2 rounded"
            @click="restoreBackup"
            :disabled="isRestoring"
          >
            {{
              isRestoring
                ? t("admin.backup.restoring")
                : t("admin.backup.restore_backup")
            }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    >
      <div class="bg-gray-800 text-white rounded-lg max-w-md w-full p-6">
        <h5 class="text-lg font-semibold mb-2">
          {{ t("admin.backup.confirm_delete") }}
        </h5>
        <p class="text-gray-300">
          {{ t("admin.backup.delete_confirm") }}<br /><strong
            class="text-sky-300"
            >{{ selectedBackup?.filename }}</strong
          >
        </p>
        <div class="flex justify-end gap-2 mt-4">
          <button
            class="bg-gray-700 text-white px-3 py-2 rounded"
            @click="showDeleteModal = false"
          >
            {{ t("admin.backup.cancel") }}
          </button>
          <button
            class="bg-red-600 text-white px-3 py-2 rounded"
            @click="deleteBackup"
            :disabled="isDeleting"
          >
            {{
              isDeleting
                ? t("admin.backup.deleting")
                : t("admin.backup.delete_backup")
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useApiStore } from "../../stores/api";
import { useAlertStore } from "../../stores/alert";

export default {
  name: "AdminBackupView",
  setup() {
    const router = useRouter();
    const { t } = useI18n();
    const apiStore = useApiStore();
    const alertStore = useAlertStore();
    const backups = ref([]);
    const isLoading = ref(false);
    const isCreating = ref(false);
    const isUploading = ref(false);
    const isRestoring = ref(false);
    const isDeleting = ref(false);
    const uploadProgress = ref(0);

    const showRestoreModal = ref(false);
    const showDeleteModal = ref(false);
    const selectedBackup = ref(null);

    // Dropdown management
    const openDropdownId = ref(null);

    // Dropdown functions
    const toggleDropdown = (backupId) => {
      if (openDropdownId.value === backupId) {
        openDropdownId.value = null;
      } else {
        openDropdownId.value = backupId;
      }
    };

    const closeDropdown = () => {
      openDropdownId.value = null;
    };

    const handleDropdownAction = (action) => {
      action();
      closeDropdown();
    };

    // Click outside to close dropdown
    const handleDocumentClick = (event) => {
      if (openDropdownId.value && !event.target.closest(".dropdown")) {
        closeDropdown();
      }
    };

    // Load backups list
    const loadBackups = async () => {
      try {
        isLoading.value = true;
        const response = await apiStore.getBackups();
        if (response.success) {
          backups.value = response.backups || [];
        } else {
          throw new Error(response.error || t("admin.backup.load_failed"));
        }
      } catch (error) {
        console.error("Error loading backups:", error);
        alertStore.error(error.message || t("admin.backup.load_failed"));
      } finally {
        isLoading.value = false;
      }
    };

    // Refresh backups
    const refreshBackups = () => {
      loadBackups();
    };

    // Create backup
    const createBackup = async () => {
      try {
        isCreating.value = true;
        const response = await apiStore.createBackup();

        if (response.success) {
          alertStore.success(
            response.message || t("admin.backup.create_success"),
          );
          await loadBackups();
        } else {
          throw new Error(response.message || t("admin.backup.create_failed"));
        }
      } catch (error) {
        console.error("Error creating backup:", error);
        alertStore.error(error.message || t("admin.backup.create_failed"));
      } finally {
        isCreating.value = false;
      }
    };

    // Handle file upload
    const handleFileUpload = async (event) => {
      const file = event.target.files[0];
      if (!file) return;

      // Validate file - must be a backup file, but will be renamed to avoid conflicts
      if (
        !file.name.match(
          /^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/,
        )
      ) {
        alertStore.error(t("admin.backup.invalid_format"));
        return;
      }

      // Check file size (2GB limit)
      if (file.size > 2 * 1024 * 1024 * 1024) {
        alertStore.error(t("admin.backup.file_too_large"));
        return;
      }

      try {
        isUploading.value = true;
        uploadProgress.value = 0;

        const formData = new FormData();
        formData.append("backup", file);

        const response = await apiStore.uploadBackup(formData, (progress) => {
          uploadProgress.value = progress;
        });

        if (response.success) {
          alertStore.success(
            response.message || t("admin.backup.upload_success"),
          );
          await loadBackups();
        } else {
          throw new Error(response.message || t("admin.backup.upload_failed"));
        }
      } catch (error) {
        console.error("Error uploading backup:", error);
        alertStore.error(error.message || t("admin.backup.upload_failed"));
      } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
        // Reset file input
        event.target.value = "";
      }
    };

    // Download backup
    const downloadBackup = (filename) => {
      const downloadUrl = `/api/admin/backup/download/${encodeURIComponent(filename)}`;
      const token = localStorage.getItem("auth_token");

      // Create temporary link to trigger download
      const link = document.createElement("a");
      link.href = downloadUrl;
      link.download = filename;

      // Add auth header via fetch for authenticated download
      fetch(downloadUrl, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
        },
      })
        .then((response) => {
          if (!response.ok) throw new Error("Download failed");
          return response.blob();
        })
        .then((blob) => {
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement("a");
          a.href = url;
          a.download = filename;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(url);
          alertStore.success(t("admin.backup.download_started"));
        })
        .catch((error) => {
          console.error("Download error:", error);
          alertStore.error(t("admin.backup.download_failed"));
        });
    };

    // Confirm restore
    const confirmRestore = (backup) => {
      selectedBackup.value = backup;
      showRestoreModal.value = true;
    };

    // Restore backup
    const restoreBackup = async () => {
      if (!selectedBackup.value) return;

      try {
        isRestoring.value = true;
        const response = await apiStore.restoreBackup(
          selectedBackup.value.filename,
        );

        if (response.success) {
          alertStore.success(
            response.message || t("admin.backup.restore_success"),
          );
          showRestoreModal.value = false;

          // Redirect to login after successful restore as session might be invalid
          setTimeout(() => {
            router.push("/login");
          }, 3000);
        } else {
          throw new Error(response.message || t("admin.backup.restore_failed"));
        }
      } catch (error) {
        console.error("Error restoring backup:", error);
        alertStore.error(error.message || t("admin.backup.restore_failed"));
      } finally {
        isRestoring.value = false;
      }
    };

    // Confirm delete
    const confirmDelete = (backup) => {
      selectedBackup.value = backup;
      showDeleteModal.value = true;
    };

    // Delete backup
    const deleteBackup = async () => {
      if (!selectedBackup.value) return;

      try {
        isDeleting.value = selectedBackup.value.filename;
        const response = await apiStore.deleteBackup(
          selectedBackup.value.filename,
        );

        if (response.success) {
          alertStore.success(
            response.message || t("admin.backup.delete_success"),
          );
          await loadBackups();
          showDeleteModal.value = false;
        } else {
          throw new Error(response.message || t("admin.backup.delete_failed"));
        }
      } catch (error) {
        console.error("Error deleting backup:", error);
        alertStore.error(error.message || t("admin.backup.delete_failed"));
      } finally {
        isDeleting.value = false;
      }
    };

    // Format date
    const formatDate = (dateString) => {
      const date = new Date(dateString);
      return date.toLocaleString();
    };

    // Initialize
    onMounted(() => {
      loadBackups();

      // Add document click listener for dropdown management
      document.addEventListener("click", handleDocumentClick);
    });

    // Cleanup on unmount
    onUnmounted(() => {
      document.removeEventListener("click", handleDocumentClick);
    });

    return {
      t,
      backups,
      isLoading,
      isCreating,
      isUploading,
      isRestoring,
      isDeleting,
      uploadProgress,
      showRestoreModal,
      showDeleteModal,
      selectedBackup,
      openDropdownId,
      refreshBackups,
      createBackup,
      handleFileUpload,
      downloadBackup,
      confirmRestore,
      restoreBackup,
      confirmDelete,
      deleteBackup,
      formatDate,
      toggleDropdown,
      closeDropdown,
      handleDropdownAction,
    };
  },
};
</script>
