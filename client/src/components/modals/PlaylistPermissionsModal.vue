<template>
  <div
    v-if="isVisible"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
    @click="handleBackdropClick"
  >
    <div
      class="w-full max-w-2xl max-h-[90vh] overflow-hidden rounded-xl shadow-2xl text-white border border-white/10"
      :class="themeStore.backgroundGradient"
      @click.stop
    >
      <div
        class="flex justify-between items-center p-4 border-b border-white/10"
      >
        <h5 class="text-lg font-semibold flex items-center">
          <i class="bi bi-people mr-2"></i>
          {{ $t("playlist.managePermissions") }}: {{ playlist?.name }}
        </h5>
        <button
          class="p-2 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center transition-all hover:scale-110 mobile-touch-target"
          @click="closeModal"
        >
          <i class="bi bi-x-lg text-lg text-white"></i>
        </button>
      </div>

      <div class="flex-1 p-4 overflow-y-auto max-h-[70vh]">
        <div v-if="playlist">
          <!-- Add User Section -->
          <div class="mb-6">
            <h6 class="font-semibold mb-3 text-white flex items-center">
              <i class="bi bi-person-plus mr-2"></i>
              {{ $t("playlist.addUser") }}
            </h6>

            <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
              <div class="flex gap-3 items-end">
                <div class="flex-1 relative">
                  <label class="block text-sm font-medium text-gray-300 mb-2">
                    {{ $t("playlist.username") }}
                  </label>
                  <div class="relative">
                    <input
                      v-model="newUserUsername"
                      type="text"
                      class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
                      :placeholder="$t('playlist.enterUsername')"
                      autocomplete="off"
                      autocapitalize="off"
                      autocorrect="off"
                      spellcheck="false"
                      @focus="showUserDropdown = true"
                      @blur="handleUsernameBlur"
                      @input="handleUsernameInput"
                    />
                    <div
                      v-if="
                        showUserDropdown &&
                        (availableUsers.length > 0 || searchResults.length > 0)
                      "
                      class="absolute top-full left-0 right-0 z-10 mt-1 max-h-60 overflow-y-auto bg-gray-700 border border-gray-600 rounded-lg shadow-lg"
                    >
                      <template
                        v-if="!isSearching && availableUsers.length > 0"
                      >
                        <div
                          v-for="user in availableUsers"
                          :key="user.user_id"
                          class="px-3 py-2 hover:bg-gray-600 cursor-pointer text-white flex items-center gap-2"
                          @mousedown.prevent="selectUser(user)"
                        >
                          <div
                            class="w-6 h-6 bg-audinary rounded-full flex items-center justify-center text-black font-medium text-xs"
                          >
                            {{
                              (user.display_name || user.username)
                                .charAt(0)
                                .toUpperCase()
                            }}
                          </div>
                          <div>
                            <div class="text-sm">
                              {{ user.display_name || user.username }}
                            </div>
                            <div class="text-xs text-gray-400">
                              @{{ user.username }}
                            </div>
                          </div>
                        </div>
                      </template>
                      <template v-if="isSearching && searchResults.length > 0">
                        <div
                          v-for="user in searchResults"
                          :key="user.user_id"
                          class="px-3 py-2 hover:bg-gray-600 cursor-pointer text-white flex items-center gap-2"
                          @mousedown.prevent="selectUser(user)"
                        >
                          <div
                            class="w-6 h-6 bg-audinary rounded-full flex items-center justify-center text-black font-medium text-xs"
                          >
                            {{
                              (user.display_name || user.username)
                                .charAt(0)
                                .toUpperCase()
                            }}
                          </div>
                          <div>
                            <div class="text-sm">
                              {{ user.display_name || user.username }}
                            </div>
                            <div class="text-xs text-gray-400">
                              @{{ user.username }}
                            </div>
                          </div>
                        </div>
                      </template>
                      <div
                        v-if="
                          isSearching &&
                          newUserUsername.length >= 2 &&
                          searchResults.length === 0
                        "
                        class="px-3 py-2 text-gray-400 text-sm"
                      >
                        {{ $t("playlist.noUsersFound") }}
                      </div>
                    </div>
                  </div>
                </div>
                <div class="w-32">
                  <label class="block text-sm font-medium text-gray-300 mb-2">
                    {{ $t("playlist.permission") }}
                  </label>
                  <select
                    v-model="newUserPermission"
                    class="w-full"
                  >
                    <option value="view">{{ $t("playlist.viewOnly") }}</option>
                    <option value="edit">{{ $t("playlist.canEdit") }}</option>
                  </select>
                </div>
                <button
                  @click="addUserPermission"
                  :disabled="!newUserUsername.trim() || isLoading"
                  class="px-4 py-2 bg-audinary hover:bg-audinary/90 text-black rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <i class="bi bi-plus-lg"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Current Permissions -->
          <div class="mb-6">
            <h6 class="font-semibold mb-3 text-white flex items-center">
              <i class="bi bi-people mr-2"></i>
              {{ $t("playlist.currentPermissions") }}
            </h6>

            <div v-if="isLoadingPermissions" class="text-center py-4">
              <div
                class="animate-spin w-6 h-6 border-4 border-white/30 border-t-white rounded-full mx-auto"
              >
                <span class="sr-only">{{ $t("common.loading") }}</span>
              </div>
            </div>

            <div
              v-else-if="permissions.length === 0"
              class="bg-gray-800 rounded-lg border border-gray-700 p-4 text-center text-gray-400"
            >
              {{ $t("playlist.noSharedUsers") }}
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="permission in permissions"
                :key="permission.user_id"
                class="bg-gray-800 rounded-lg border border-gray-700 p-4 flex items-center justify-between"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-8 h-8 bg-audinary rounded-full flex items-center justify-center text-black font-medium"
                  >
                    {{
                      (permission.username || permission.user_id)
                        .charAt(0)
                        .toUpperCase()
                    }}
                  </div>
                  <div>
                    <p class="text-white font-medium">
                      {{ permission.username || permission.user_id }}
                    </p>
                    <p class="text-sm text-gray-400">
                      {{
                        permission.permission_type === "edit"
                          ? $t("playlist.canEdit")
                          : $t("playlist.viewOnly")
                      }}
                    </p>
                  </div>
                </div>

                <div class="flex items-center gap-2">
                  <select
                    :value="permission.permission_type"
                    @change="
                      updateUserPermission(
                        permission.user_id,
                        $event.target.value,
                      )
                    "
                    class="text-sm"
                  >
                    <option value="view">{{ $t("playlist.viewOnly") }}</option>
                    <option value="edit">{{ $t("playlist.canEdit") }}</option>
                  </select>

                  <button
                    @click="removeUserPermission(permission.user_id)"
                    class="p-2 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded-lg transition-colors"
                    :title="$t('playlist.removeUser')"
                  >
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, watch, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import { useThemeStore } from "@/stores/theme";
import { usePlaylistStore } from "@/stores/playlist";
import { useAlertStore } from "@/stores/alert";

export default {
  name: "PlaylistPermissionsModal",

  props: {
    isVisible: {
      type: Boolean,
      default: false,
    },
    playlist: {
      type: Object,
      default: null,
    },
  },

  emits: ["close"],

  setup(props, { emit }) {
    const { t } = useI18n();
    const themeStore = useThemeStore();
    const playlistStore = usePlaylistStore();
    const alertStore = useAlertStore();

    // State
    const permissions = ref([]);
    const isLoading = ref(false);
    const isLoadingPermissions = ref(false);
    const newUserUsername = ref("");
    const newUserPermission = ref("view");
    const availableUsers = ref([]);
    const searchResults = ref([]);
    const showUserDropdown = ref(false);
    const isSearching = ref(false);
    const searchTimeout = ref(null);

    // Methods
    const closeModal = () => {
      emit("close");
    };

    const handleBackdropClick = (event) => {
      if (event.target === event.currentTarget) {
        closeModal();
      }
    };

    const loadPermissions = async () => {
      if (!props.playlist?.id) return;

      isLoadingPermissions.value = true;
      try {
        const result = await playlistStore.getPlaylistPermissions(
          props.playlist.id,
        );
        permissions.value = result.permissions || result || [];
      } catch (error) {
        console.error("Error loading permissions:", error);
        alertStore.error(t("playlist.loadPermissionsError"));
      } finally {
        isLoadingPermissions.value = false;
      }
    };

    const addUserPermission = async () => {
      if (!newUserUsername.value.trim() || !props.playlist?.id) return;

      isLoading.value = true;
      try {
        await playlistStore.grantPlaylistPermission(
          props.playlist.id,
          newUserUsername.value.trim(),
          newUserPermission.value,
        );

        alertStore.success(t("playlist.userAdded"));
        newUserUsername.value = "";
        newUserPermission.value = "view";
        await loadPermissions();
      } catch (error) {
        console.error("Error adding user permission:", error);
        alertStore.error(error.message || t("playlist.addUserError"));
      } finally {
        isLoading.value = false;
      }
    };

    const updateUserPermission = async (userId, permissionType) => {
      if (!props.playlist?.id) return;

      try {
        await playlistStore.updatePlaylistPermission(
          props.playlist.id,
          userId,
          permissionType,
        );
        alertStore.success(t("playlist.permissionUpdated"));
        await loadPermissions();
      } catch (error) {
        console.error("Error updating permission:", error);
        alertStore.error(error.message || t("playlist.updatePermissionError"));
      }
    };

    const removeUserPermission = async (userId) => {
      if (!props.playlist?.id) return;

      if (!confirm(t("playlist.confirmRemoveUser"))) return;

      try {
        await playlistStore.revokePlaylistPermission(props.playlist.id, userId);
        alertStore.success(t("playlist.userRemoved"));
        await loadPermissions();
      } catch (error) {
        console.error("Error removing user permission:", error);
        alertStore.error(error.message || t("playlist.removeUserError"));
      }
    };

    const loadAvailableUsers = async () => {
      try {
        const response = await playlistStore.getAvailableUsers();
        availableUsers.value = response.users || [];
      } catch (error) {
        console.error("Error loading available users:", error);
        availableUsers.value = [];
      }
    };

    const searchUsers = async (query) => {
      if (query.length < 2) {
        searchResults.value = [];
        isSearching.value = false;
        return;
      }

      try {
        isSearching.value = true;
        const response = await playlistStore.searchUsers(query);
        searchResults.value = response.users || [];
      } catch (error) {
        console.error("Error searching users:", error);
        searchResults.value = [];
      }
    };

    const handleUsernameInput = () => {
      const query = newUserUsername.value.trim();

      // Clear previous timeout
      if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
      }

      if (query.length >= 2) {
        // Start search with debounce
        searchTimeout.value = setTimeout(() => {
          searchUsers(query);
        }, 300);
      } else {
        searchResults.value = [];
        isSearching.value = false;
      }
    };

    const handleUsernameBlur = () => {
      // Use timeout to allow click on dropdown items
      setTimeout(() => {
        showUserDropdown.value = false;
        isSearching.value = false;
      }, 150);
    };

    const selectUser = (user) => {
      newUserUsername.value = user.username;
      showUserDropdown.value = false;
      isSearching.value = false;
    };

    // Watch for modal visibility changes
    watch(
      () => props.isVisible,
      (newVal) => {
        if (newVal && props.playlist) {
          loadPermissions();
          loadAvailableUsers();
        }
      },
    );

    return {
      t,
      themeStore,
      permissions,
      isLoading,
      isLoadingPermissions,
      newUserUsername,
      newUserPermission,
      availableUsers,
      searchResults,
      showUserDropdown,
      isSearching,
      closeModal,
      handleBackdropClick,
      addUserPermission,
      updateUserPermission,
      removeUserPermission,
      handleUsernameInput,
      handleUsernameBlur,
      selectUser,
    };
  },
};
</script>

<style scoped>
.mobile-touch-target {
  min-height: 44px;
  min-width: 44px;
}
</style>
