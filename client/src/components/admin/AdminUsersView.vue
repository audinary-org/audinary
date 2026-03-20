<template>
  <div class="admin-users flex flex-col h-full p-0 m-0">
    <!-- Header with Actions -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h4 class="text-white mb-1">{{ $t("admin.users.user_management") }}</h4>
        <p class="text-gray-400 mb-0">{{ $t("admin.users.manage_desc") }}</p>
      </div>
      <button
        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-md flex items-center"
        @click="showCreateUser"
      >
        <i class="bi bi-person-plus mr-2"></i>
        {{ $t("admin.users.new_user") }}
      </button>
    </div>

    <!-- Users Card -->
    <div class="bg-white/5 border border-white/10 rounded-lg">
      <div
        class="px-4 py-3 border-b border-white/10 flex items-center justify-between"
      >
        <h5 class="mb-0 flex items-center gap-2">
          <i class="bi bi-people"></i>
          <span>{{ $t("admin.users.all_users") }} ({{ users.length }})</span>
        </h5>
        <button
          class="text-sm bg-white/5 border border-white/10 text-gray-200 px-2 py-1 rounded hover:bg-white/10"
          @click="loadUsers"
        >
          <i class="bi bi-arrow-clockwise mr-1"></i>
          {{ $t("admin.refresh") }}
        </button>
      </div>
      <div class="p-4">
        <div v-if="loading" class="text-center py-4">
          <div
            class="w-10 h-10 border-4 border-t-transparent border-blue-600 rounded-full animate-spin mx-auto"
          ></div>
        </div>

        <div
          v-else-if="users.length === 0"
          class="text-center py-4 text-gray-400"
        >
          <i class="bi bi-people text-3xl"></i>
          <p class="mt-2">{{ $t("admin.users.no_users") }}</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-white/10">
            <thead>
              <tr class="text-left text-sm text-gray-300">
                <th class="px-3 py-2">{{ $t("admin.users.avatar") }}</th>
                <th class="px-3 py-2">{{ $t("common.username") }}</th>
                <th class="px-3 py-2">{{ $t("admin.users.display_name") }}</th>
                <th class="px-3 py-2">{{ $t("admin.users.email") }}</th>
                <th class="px-3 py-2">{{ $t("admin.users.role") }}</th>
                <th class="px-3 py-2">{{ $t("admin.users.last_login") }}</th>
                <th class="px-3 py-2">{{ $t("admin.users.created") }}</th>
                <th class="px-3 py-2">{{ $t("common.actions") }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/10 text-sm">
              <tr
                v-for="user in users"
                :key="user.user_id"
                class="align-middle"
              >
                <td class="px-3 py-2">
                  <div class="w-10 h-10">
                    <SimpleImage
                      image-type="profile"
                      :image-id="user.image_uuid || 'default'"
                      :alt="user.username"
                      class="rounded-full object-cover w-10 h-10"
                    />
                  </div>
                </td>
                <td class="px-3 py-2">
                  <div class="flex items-center">
                    <strong>{{ user.username }}</strong>
                  </div>
                </td>
                <td class="px-3 py-2">{{ user.display_name || "-" }}</td>
                <td class="px-3 py-2">{{ user.email || "-" }}</td>
                <td class="px-3 py-2">
                  <span
                    :class="
                      user.is_admin
                        ? 'bg-white/5 border border-white/10 text-yellow-400 px-2 py-1 rounded'
                        : 'bg-white/5 border border-white/10 px-2 py-1 rounded'
                    "
                  >
                    {{
                      user.is_admin
                        ? $t("admin.users.administrator")
                        : $t("admin.users.user")
                    }}
                  </span>
                </td>
                <td class="px-3 py-2 text-sm text-gray-400">
                  {{
                    user.last_login
                      ? formatDate(user.last_login)
                      : $t("admin.never")
                  }}
                </td>
                <td class="px-3 py-2 text-sm text-gray-400">
                  {{ formatDate(user.created_at) }}
                </td>
                <td class="px-3 py-2">
                  <div class="flex gap-2">
                    <button
                      class="bg-white/5 border border-white/10 text-gray-200 px-2 py-1 rounded hover:bg-white/10"
                      @click="editUser(user)"
                      :title="$t('admin.users.edit')"
                    >
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button
                      class="bg-white/5 border border-white/10 text-red-400 px-2 py-1 rounded hover:bg-white/10"
                      @click="deleteUser(user)"
                      :title="$t('admin.users.delete')"
                      :disabled="user.user_id === currentUserId"
                    >
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create/Edit User Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-start justify-center bg-black/60 p-4"
    >
      <div
        class="w-full max-w-3xl bg-gray-800 text-gray-100 rounded-lg overflow-hidden"
      >
        <div
          class="px-4 py-3 border-b border-gray-700 flex items-center justify-between"
        >
          <h5 class="flex items-center gap-2">
            <i
              class="bi"
              :class="editingUser.user_id ? 'bi-person-gear' : 'bi-person-plus'"
            ></i>
            <span>{{
              editingUser.user_id
                ? $t("admin.users.edit_user")
                : $t("admin.users.new_user")
            }}</span>
          </h5>
          <button class="text-gray-300 hover:text-white" @click="closeModal">
            ✕
          </button>
        </div>
        <div class="p-4">
          <form @submit.prevent="saveUser">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <!-- Image upload and fields preserved as in original (structure simplified for Tailwind) -->
              <div class="col-span-1 md:col-span-2">
                <label class="block text-sm text-gray-300 mb-1">{{
                  $t("admin.users.profile_image")
                }}</label>
                <div class="text-center mb-3">
                  <div class="relative inline-block">
                    <div
                      class="rounded-full overflow-hidden border border-gray-600 w-30 h-30"
                      style="width: 120px; height: 120px"
                    >
                      <img
                        v-if="imagePreview"
                        :src="imagePreview"
                        :alt="editingUser.username"
                        class="w-full h-full object-cover"
                      />
                      <SimpleImage
                        v-else
                        image-type="profile"
                        :image-id="editingUser.image_uuid || 'default'"
                        :alt="editingUser.username"
                        class="w-full h-full object-cover"
                      />
                    </div>
                    <div
                      class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-2"
                    >
                      <button
                        type="button"
                        class="bg-sky-600 text-white rounded-full p-2"
                        @click="editProfilePic"
                        :disabled="saving"
                        :title="$t('admin.users.change_image')"
                      >
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button
                        v-if="editingUser.image_uuid || imagePreview"
                        type="button"
                        class="bg-red-600 text-white rounded-full p-2"
                        @click="removeProfileImage"
                        :disabled="saving"
                        :title="$t('admin.users.remove_image')"
                      >
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </div>
                  <div class="mt-2">
                    <small class="text-gray-400">{{
                      $t("admin.users.image_formats")
                    }}</small>
                  </div>
                </div>
                <input
                  ref="profileImageFile"
                  type="file"
                  class="hidden"
                  accept="image/jpeg,image/jpg,image/png,image/webp"
                  @change="handleImageSelect"
                />
              </div>

              <div>
                <label
                  for="username"
                  class="block text-sm text-gray-300 mb-2"
                  >{{ $t("admin.users.username_required") }}</label
                >
                <input
                  id="username"
                  type="text"
                  class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                  v-model="editingUser.username"
                  required
                  :disabled="saving"
                />
              </div>

              <div>
                <label
                  for="displayName"
                  class="block text-sm text-gray-300 mb-2"
                  >{{ $t("admin.users.display_name") }}</label
                >
                <input
                  id="displayName"
                  type="text"
                  class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                  v-model="editingUser.display_name"
                  :disabled="saving"
                />
              </div>

              <div>
                <label for="email" class="block text-sm text-gray-300 mb-2">{{
                  $t("admin.users.email")
                }}</label>
                <input
                  id="email"
                  type="email"
                  class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                  v-model="editingUser.email"
                  :disabled="saving"
                />
              </div>

              <div>
                <label
                  for="password"
                  class="block text-sm text-gray-300 mb-2"
                  >{{
                    editingUser.user_id
                      ? $t("admin.users.new_password")
                      : $t("admin.users.password_required")
                  }}</label
                >
                <div class="flex">
                  <input
                    :type="showPassword ? 'text' : 'password'"
                    id="password"
                    class="bg-white/10 text-white border border-white/20 rounded-l px-3 py-2 w-full"
                    v-model="editingUser.password"
                    :required="!editingUser.user_id"
                    :disabled="saving"
                    minlength="6"
                    :placeholder="
                      editingUser.user_id
                        ? 'Leave empty to keep current password'
                        : 'Minimum 6 characters'
                    "
                  />
                  <button
                    type="button"
                    class="bg-white/5 border border-white/10 text-gray-200 px-3 py-2 rounded-r hover:bg-white/10"
                    @click="showPassword = !showPassword"
                  >
                    <i
                      :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"
                    ></i>
                  </button>
                  <button
                    type="button"
                    class="bg-white/5 border border-white/10 text-emerald-400 px-3 py-2 ml-2 rounded hover:bg-white/10"
                    @click="generatePassword"
                    :title="$t('admin.users.generate_password')"
                  >
                    <i class="bi bi-shuffle"></i>
                  </button>
                </div>
              </div>

              <div class="md:col-span-2">
                <label class="flex items-center gap-3">
                  <input
                    type="checkbox"
                    class="form-checkbox"
                    id="isAdmin"
                    v-model="editingUser.is_admin"
                    :disabled="saving || isEditingSelf"
                    @click="handleAdminToggle"
                  />
                  <span class="text-gray-300">{{
                    $t("admin.users.admin_rights")
                  }}</span>
                </label>
                <div class="text-sm text-gray-400">
                  {{ $t("admin.users.admin_rights_desc") }}
                </div>
                <div
                  v-if="isEditingSelf"
                  class="text-yellow-400 text-sm mt-1 flex items-center gap-2"
                >
                  <i class="bi bi-shield-lock"></i
                  >{{ $t("admin.users.cannot_remove_self") }}
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="px-4 py-3 border-t border-gray-700 flex justify-end gap-2">
          <button
            class="bg-gray-700 text-gray-200 px-3 py-2 rounded hover:bg-gray-600"
            @click="closeModal"
            :disabled="saving"
          >
            {{ $t("common.cancel") }}
          </button>
          <button
            class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700"
            @click="saveUser"
            :disabled="saving"
          >
            <span
              v-if="saving"
              class="w-4 h-4 border-2 border-t-transparent border-white rounded-full animate-spin inline-block mr-2"
            ></span>
            <i
              v-else
              class="bi"
              :class="editingUser.user_id ? 'bi-save' : 'bi-person-plus'"
            ></i>
            {{ editingUser.user_id ? $t("common.save") : $t("common.create") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useApiStore } from "@/stores/api";
import { useAuthStore } from "@/stores/auth";
import { useAlertStore } from "@/stores/alert";
import { useI18n } from "vue-i18n";
import SimpleImage from "@/components/common/SimpleImage.vue";

const apiStore = useApiStore();
const authStore = useAuthStore();
const alertStore = useAlertStore();
const { t } = useI18n();

// State
const users = ref([]);
const loading = ref(true);
const saving = ref(false);
const showPassword = ref(false);

const showModal = ref(false);

const currentUserId = computed(() => {
  return authStore.user?.user_id || authStore.user?.id;
});

const editingUser = ref({
  user_id: null,
  username: "",
  display_name: "",
  email: "",
  password: "",
  is_admin: false,
  image_uuid: null,
});

const imagePreview = ref(null);
const profileImageFile = ref(null);
const imageRemoved = ref(false);

// Protection check
const isEditingSelf = computed(() => {
  const editingId = String(editingUser.value.user_id);
  const currentId = String(currentUserId.value);
  return editingId === currentId && editingUser.value.user_id !== null;
});

// Watch to prevent admin status changes when editing self
watch(
  () => editingUser.value.is_admin,
  (newValue, oldValue) => {
    if (isEditingSelf.value && !newValue && oldValue) {
      // Prevent removing admin status from self
      editingUser.value.is_admin = true;
      alertStore.addAlert("warning", t("admin.users.cannot_remove_admin"));
    }
  },
);

// Methods
const loadUsers = async () => {
  try {
    loading.value = true;
    const response = await apiStore.get("/api/admin/users");
    users.value = response || [];
  } catch (error) {
    console.error("Error loading users:", error);
    showMessage("error", t("common.error"));
    users.value = [];
  } finally {
    loading.value = false;
  }
};

const showCreateUser = () => {
  editingUser.value = {
    user_id: null,
    username: "",
    display_name: "",
    email: "",
    password: "",
    is_admin: false,
    image_uuid: null,
  };
  showPassword.value = false;
  imagePreview.value = null;
  imageRemoved.value = false;
  if (profileImageFile.value) {
    profileImageFile.value.value = "";
  }
  openModal();
};

const editUser = (user) => {
  editingUser.value = {
    user_id: user.user_id,
    username: user.username,
    display_name: user.display_name || "",
    email: user.email || "",
    password: "",
    is_admin: !!user.is_admin,
    image_uuid: user.image_uuid || null,
  };
  showPassword.value = false;
  imagePreview.value = null;
  imageRemoved.value = false;
  if (profileImageFile.value) {
    profileImageFile.value.value = "";
  }
  openModal();
};

const saveUser = async () => {
  try {
    saving.value = true;

    // Frontend validation
    if (!editingUser.value.username.trim()) {
      showMessage("error", t("admin.users.username_required"));
      return;
    }

    // Password validation
    if (editingUser.value.password) {
      if (editingUser.value.password.length < 6) {
        showMessage("error", "Password must be at least 6 characters");
        return;
      }
    } else if (!editingUser.value.user_id) {
      // New user needs password
      showMessage("error", "Password is required for new users");
      return;
    }

    // Always use FormData
    const formData = new FormData();
    formData.append("username", editingUser.value.username);
    formData.append("display_name", editingUser.value.display_name);
    formData.append("email", editingUser.value.email);
    formData.append("is_admin", editingUser.value.is_admin ? "1" : "0");

    if (editingUser.value.password) {
      formData.append("password", editingUser.value.password);
    }

    // Add user_id for updates
    if (editingUser.value.user_id) {
      formData.append("user_id", editingUser.value.user_id);
    }

    // Handle profile image upload
    if (profileImageFile.value?.files[0]) {
      formData.append("profileImage", profileImageFile.value.files[0]);
    }

    // Handle image removal
    if (imageRemoved.value) {
      formData.append("removeProfilePic", "1");
    }

    // Always use POST to /api/admin/user/save (like user settings)
    const response = await apiStore.makeRequest("/api/admin/user/save", {
      method: "POST",
      body: formData,
    });

    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.error || "Failed to save user");
    }

    showMessage("success", t("common.success"));
    closeModal();
    await loadUsers();
  } catch (error) {
    console.error("Error saving user:", error);
    showMessage("error", error.message || t("common.error"));
  } finally {
    saving.value = false;
  }
};

const deleteUser = async (user) => {
  if (!confirm(t("admin.users.confirm_delete", { username: user.username }))) {
    return;
  }

  try {
    await apiStore.delete(`/api/admin/user/${user.user_id}`);
    showMessage("success", t("common.success"));
    await loadUsers();
  } catch (error) {
    console.error("Error deleting user:", error);
    showMessage("error", t("common.error"));
  }
};

const generatePassword = () => {
  const charset =
    "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
  let password = "";
  for (let i = 0; i < 12; i++) {
    password += charset.charAt(Math.floor(Math.random() * charset.length));
  }
  editingUser.value.password = password;
  showPassword.value = true;
};

const openModal = () => {
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const showMessage = (type, text) => {
  alertStore.addAlert(type, text);
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleString("de-DE");
};

const editProfilePic = () => {
  profileImageFile.value?.click();
};

const handleImageSelect = (event) => {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      imagePreview.value = e.target.result;
      imageRemoved.value = false; // Reset removal flag when new image is selected
    };
    reader.readAsDataURL(file);
  }
};

const removeProfileImage = () => {
  imagePreview.value = null;
  editingUser.value.image_uuid = null;
  imageRemoved.value = true;
  if (profileImageFile.value) {
    profileImageFile.value.value = "";
  }
};

const handleAdminToggle = (event) => {
  if (isEditingSelf.value) {
    // Prevent the toggle completely
    event.preventDefault();
    event.stopPropagation();
    alertStore.addAlert("warning", t("admin.users.cannot_remove_admin"));
    return false;
  }
};

onMounted(() => {
  loadUsers();
});
</script>
