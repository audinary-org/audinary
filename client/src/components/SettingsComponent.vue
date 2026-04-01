<template>
  <div class="flex flex-col h-full">
    <ContentHeader
      :title="$t('settings.title')"
      :show-search="false"
      :show-filter="false"
      :show-view-toggle="false"
    >
    </ContentHeader>

    <!-- Content Area (scrollable) -->
    <div class="flex-1 overflow-y-auto p-6">
      <!-- User Profile Settings -->
      <div v-show="props.activeSection === 'profile'" class="max-w-3xl">
        <h2 class="text-2xl font-bold text-white mb-6">
          {{ $t("settings.tabs.profile") }}
        </h2>

        <!-- Alert Box -->
        <div
          v-if="alertMessage"
          class="mb-4 p-4 rounded-lg border"
          :class="
            alertClass === 'alert-danger'
              ? 'bg-red-900/20 border-red-500/50 text-red-300'
              : 'bg-green-900/20 border-green-500/50 text-green-300'
          "
        >
          {{ alertMessage }}
        </div>

        <form @submit.prevent="saveSettings">
          <!-- Hidden file input -->
          <input
            ref="profileImageFile"
            type="file"
            class="hidden"
            accept="image/*"
            @change="handleFileChange"
          />

          <div class="bg-white/5 border border-white/10 rounded-lg p-6">
            <!-- Profile Image Preview -->
            <div class="text-center mb-6">
              <div class="relative inline-block">
                <div class="w-36 h-36 rounded-full overflow-hidden">
                  <img
                    v-if="profileImagePreview"
                    :src="profileImagePreview"
                    alt="Profile Picture"
                    class="w-full h-full object-cover"
                  />
                  <SimpleImage
                    v-else-if="currentProfileImageId"
                    image-type="profile"
                    :image-id="currentProfileImageId"
                    alt="Profile Picture"
                    class="w-full h-full object-cover"
                  />
                  <img
                    v-else
                    src="/img/placeholder_audinary.png"
                    alt="Profile Picture"
                    class="w-full h-full object-cover"
                  />
                </div>
                <!-- Edit/Remove buttons -->
                <div
                  class="absolute bottom-0 left-1/2 transform -translate-x-1/2 mb-2 flex gap-2"
                >
                  <button
                    type="button"
                    class="w-8 h-8 bg-white/5 border border-white/10 hover:bg-white/10 text-blue-400 rounded-full flex items-center justify-center transition-colors"
                    @click="editProfilePic"
                    :title="$t('settings.profileImage.change')"
                  >
                    <i class="bi bi-pencil text-sm"></i>
                  </button>
                  <button
                    type="button"
                    class="w-8 h-8 bg-white/5 border border-white/10 hover:bg-white/10 text-red-400 rounded-full flex items-center justify-center transition-colors"
                    @click="removeProfilePic"
                    :title="$t('settings.profileImage.remove')"
                  >
                    <i class="bi bi-trash text-sm"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Username -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-300 mb-2">{{
                $t("settings.user.username")
              }}</label>
              <div class="flex">
                <input
                  v-model="formData.username"
                  type="text"
                  class="flex-1 px-3 py-2 bg-white/10 border border-white/20 rounded-l-lg text-white focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
                  autocomplete="off"
                  :readonly="!editableFields.username"
                />
                <button
                  class="px-3 py-2 border border-l-0 border-white/20 rounded-r-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors"
                  type="button"
                  @click="toggleEdit('username')"
                  :title="
                    editableFields.username
                      ? $t('settings.user.saveUsername')
                      : $t('settings.user.editUsername')
                  "
                >
                  <i
                    class="bi"
                    :class="
                      editableFields.username ? 'bi-check-lg' : 'bi-pencil'
                    "
                  ></i>
                </button>
              </div>
            </div>

            <!-- Display Name -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-300 mb-2">{{
                $t("settings.user.displayName")
              }}</label>
              <div class="flex">
                <input
                  v-model="formData.displayName"
                  type="text"
                  class="flex-1 px-3 py-2 bg-white/10 border border-white/20 rounded-l-lg text-white focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
                  autocomplete="off"
                  :readonly="!editableFields.displayName"
                />
                <button
                  class="px-3 py-2 border border-l-0 border-white/20 rounded-r-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors"
                  type="button"
                  @click="toggleEdit('displayName')"
                  :title="
                    editableFields.displayName
                      ? $t('settings.user.saveDisplayName')
                      : $t('settings.user.editDisplayName')
                  "
                >
                  <i
                    class="bi"
                    :class="
                      editableFields.displayName ? 'bi-check-lg' : 'bi-pencil'
                    "
                  ></i>
                </button>
              </div>
            </div>

            <!-- Password Change Section -->
            <div class="mb-6">
              <div class="flex justify-between items-center mb-3">
                <label class="block text-sm font-medium text-gray-300">{{
                  $t("settings.user.password")
                }}</label>
                <button
                  type="button"
                  class="px-3 py-1 text-sm bg-white/5 border border-white/10 text-blue-400 hover:bg-white/10 rounded transition-colors"
                  @click="togglePasswordChange"
                >
                  {{ $t("settings.user.changePassword") }}
                </button>
              </div>

              <div
                v-show="showPasswordFields"
                class="space-y-3 transition-all duration-300"
              >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <input
                      v-model="formData.newPassword"
                      type="password"
                      class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
                      :placeholder="$t('settings.user.newPassword')"
                      autocomplete="off"
                      minlength="6"
                      @input="onPasswordInput"
                    />
                  </div>
                  <div v-show="showConfirmPassword">
                    <input
                      v-model="formData.confirmPassword"
                      type="password"
                      class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
                      :placeholder="$t('settings.user.confirmPassword')"
                      autocomplete="off"
                      minlength="6"
                      @input="checkPasswordMatch"
                    />
                  </div>
                </div>
                <div
                  v-if="passwordMatchStatus"
                  class="text-sm"
                  :class="passwordMatchClass"
                >
                  {{ passwordMatchStatus }}
                </div>
              </div>
            </div>

            <!-- Session Timeout -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-white mb-2">{{
                $t("settings.session.timeout")
              }}</label>
              <select v-model="formData.sessionTimeout" class="w-full">
                <option value="31536000">
                  {{ $t("settings.session.timeouts.off") }}
                </option>
                <option value="3600">
                  {{ $t("settings.session.timeouts.1h") }}
                </option>
                <option value="10800">
                  {{ $t("settings.session.timeouts.3h") }}
                </option>
                <option value="43200">
                  {{ $t("settings.session.timeouts.12h") }}
                </option>
                <option value="86400">
                  {{ $t("settings.session.timeouts.24h") }}
                </option>
              </select>
              <div class="text-xs text-gray-400 mt-1">
                {{ $t("settings.session.timeoutDescription") }}
              </div>
            </div>

            <!-- Language Settings -->
            <div class="mb-0">
              <LanguageSelector
                v-model="formData.language"
                :label="$t('settings.language.interface_language')"
                :description="$t('settings.language.language_description')"
                @change="onLanguageChange"
              />
            </div>
          </div>

          <div class="w-full mt-6">
            <button
              class="w-full py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-white/5 disabled:cursor-not-allowed rounded-lg text-white font-medium transition-colors"
              type="submit"
              :disabled="loading"
            >
              {{
                loading
                  ? $t("settings.user.saving")
                  : $t("settings.user.saveSettings")
              }}
            </button>
          </div>
        </form>
      </div>

      <!-- Transcoding Settings -->
      <div v-show="props.activeSection === 'transcoding'" class="max-w-3xl">
        <h2 class="text-2xl font-bold text-white mb-6">
          {{ $t("settings.tabs.transcoding") }}
        </h2>

        <form @submit.prevent="saveSettings">
          <div class="bg-white/5 border border-white/10 rounded-lg">
            <div class="p-6">
              <!-- Transcoding Toggle -->
              <div class="mb-4">
                <div class="flex items-center">
                  <input
                    v-model="formData.transcodingEnabled"
                    type="checkbox"
                    class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 rounded focus:ring-audinary focus:ring-2"
                    id="transcodingSwitch"
                  />
                  <label
                    class="ml-2 text-sm text-white"
                    for="transcodingSwitch"
                  >
                    {{ $t("settings.transcoding.enable") }}
                  </label>
                </div>
                <div class="text-xs text-gray-400 mt-1 space-y-1">
                  <div>{{ $t("settings.transcoding.enableDescription") }}</div>
                  <div>{{ $t("settings.transcoding.aacRecommendation") }}</div>
                  <div>{{ $t("settings.transcoding.unsupportedFormats") }}</div>
                </div>
              </div>

              <!-- Transcoding Format -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-white mb-2">{{
                  $t("settings.transcoding.format")
                }}</label>
                <select
                  v-model="formData.transcodingFormat"
                  class="w-full"
                  @change="updateQualityOptions"
                >
                  <option value="aac">
                    {{ $t("settings.transcoding.formats.aac") }}
                  </option>
                  <option value="flac">
                    {{ $t("settings.transcoding.formats.flac") }}
                  </option>
                </select>
                <div class="text-xs text-gray-400 mt-1">
                  {{ $t("settings.transcoding.formatDescription") }}
                </div>
              </div>

              <!-- Encoding Mode (only for AAC) -->
              <div v-if="formData.transcodingFormat === 'aac'" class="mb-4">
                <label class="block text-sm font-medium text-white mb-2">{{
                  $t("settings.transcoding.mode")
                }}</label>
                <select
                  v-model="formData.transcodingMode"
                  class="w-full"
                  @change="updateQualityOptions"
                >
                  <option value="cbr">
                    {{ $t("settings.transcoding.modes.cbr") }}
                  </option>
                  <option value="vbr">
                    {{ $t("settings.transcoding.modes.vbr") }}
                  </option>
                </select>
                <div class="text-xs text-gray-400 mt-1">
                  {{ $t("settings.transcoding.modeDescription") }}
                </div>
              </div>

              <!-- Transcoding Quality -->
              <div class="mb-0">
                <label class="block text-sm font-medium text-white mb-2">{{
                  $t("settings.transcoding.quality")
                }}</label>
                <select v-model="formData.transcodingQuality" class="w-full">
                  <option
                    v-for="option in qualityOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
                <div class="text-xs text-gray-400 mt-1">
                  {{ $t("settings.transcoding.qualityDescription") }}
                </div>
              </div>
            </div>
          </div>

          <div class="w-full mt-6">
            <button
              class="w-full py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-white/5 disabled:cursor-not-allowed rounded-lg text-white font-medium transition-colors"
              type="submit"
              :disabled="loading"
            >
              {{
                loading
                  ? $t("settings.user.saving")
                  : $t("settings.user.saveSettings")
              }}
            </button>
          </div>
        </form>
      </div>

      <!-- Appearance Settings -->
      <div v-show="props.activeSection === 'appearance'" class="max-w-3xl">
        <h2 class="text-2xl font-bold text-white mb-6">
          {{ $t("settings.tabs.appearance") }}
        </h2>

        <form @submit.prevent="saveSettings">
          <div class="bg-white/5 border border-white/10 rounded-lg">
            <div class="p-6">
              <div class="mb-4">
                <label class="block text-sm font-medium text-white mb-3">{{
                  $t("settings.theme.backgroundGradient")
                }}</label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                  <div
                    v-for="theme in themeStore.availableThemes"
                    :key="theme.id"
                    class="relative cursor-pointer group"
                    @click="selectTheme(theme.id)"
                  >
                    <!-- Theme Preview -->
                    <div
                      :class="[
                        'w-full h-20 rounded-lg border-2 transition-all duration-200',
                        theme.preview,
                        formData.selectedTheme === theme.id
                          ? 'border-blue-400 ring-2 ring-blue-400/50'
                          : 'border-white/20 group-hover:border-white/40',
                      ]"
                    >
                      <!-- Selection indicator -->
                      <div
                        v-if="formData.selectedTheme === theme.id"
                        class="absolute top-2 right-2 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center"
                      >
                        <i class="bi bi-check text-white text-sm"></i>
                      </div>
                    </div>
                    <!-- Theme Name -->
                    <p
                      class="text-sm text-gray-300 mt-2 text-center group-hover:text-white transition-colors"
                    >
                      {{ theme.name }}
                    </p>
                  </div>
                </div>
                <div class="text-xs text-gray-400 mt-3">
                  {{ $t("settings.theme.themeDescription") }}
                </div>
              </div>
            </div>
          </div>

          <div class="w-full mt-6">
            <button
              class="w-full py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-white/5 disabled:cursor-not-allowed rounded-lg text-white font-medium transition-colors"
              type="submit"
              :disabled="loading"
            >
              {{
                loading
                  ? $t("settings.user.saving")
                  : $t("settings.user.saveSettings")
              }}
            </button>
          </div>
        </form>
      </div>

      <!-- Admin Dashboard -->
      <div v-show="props.activeSection === 'admin-dashboard'" class="max-w-6xl">
        <AdminDashboardView />
      </div>

      <!-- Admin Users -->
      <div v-show="props.activeSection === 'admin-users'" class="max-w-6xl">
        <AdminUsersView />
      </div>

      <!-- Admin Configuration -->
      <div v-show="props.activeSection === 'admin-config'" class="max-w-6xl">
        <AdminConfigView />
      </div>

      <!-- Admin Music Scan -->
      <div v-show="props.activeSection === 'admin-scan'" class="max-w-6xl">
        <AdminScanView />
      </div>

      <!-- Admin Playlists -->
      <div v-show="props.activeSection === 'admin-playlists'" class="max-w-6xl">
        <AdminPlaylistsView />
      </div>

      <!-- Admin Wishlist -->
      <div
        v-if="configStore.isWishlistEnabled"
        v-show="props.activeSection === 'admin-wishlist'"
        class="max-w-6xl"
      >
        <AdminWishlistView ref="adminWishlistViewRef" />
      </div>

      <!-- Admin Backup -->
      <div v-show="props.activeSection === 'admin-backup'" class="max-w-6xl">
        <AdminBackupView />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "@/stores/auth";
import { useApiStore } from "@/stores/api";
import { useAlertStore } from "@/stores/alert";
import { useThemeStore } from "@/stores/theme";
import { useConfigStore } from "@/stores/config";
import { setLocale, getCurrentLocalePreference } from "@/i18n";
import ContentHeader from "@/components/common/ContentHeader.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";
import LanguageSelector from "@/components/common/LanguageSelector.vue";

// Admin components
import AdminDashboardView from "@/components/admin/AdminDashboardView.vue";
import AdminUsersView from "@/components/admin/AdminUsersView.vue";
import AdminConfigView from "@/components/admin/AdminConfigView.vue";
import AdminScanView from "@/components/admin/AdminScanView.vue";
import AdminPlaylistsView from "@/components/admin/AdminPlaylistsView.vue";
import AdminWishlistView from "@/components/admin/AdminWishlistView.vue";
import AdminBackupView from "@/components/admin/AdminBackupView.vue";

// Props to receive active section from parent
const props = defineProps({
  activeSection: {
    type: String,
    default: "profile",
  },
});

const { t } = useI18n();
const authStore = useAuthStore();
const apiStore = useApiStore();
const alertStore = useAlertStore();
const themeStore = useThemeStore();
const configStore = useConfigStore();

// Ref to AdminWishlistView
const adminWishlistViewRef = ref(null);

// Watch for activeSection changes and reload wishlist when tab is opened
watch(
  () => props.activeSection,
  (newSection) => {
    if (newSection === "admin-wishlist" && adminWishlistViewRef.value) {
      adminWishlistViewRef.value.loadWishlist();
    }
  },
);

// Reactive data
const loading = ref(false);
const profileImagePreview = ref(null);
const currentProfileImageId = ref(null);
const profileImageFile = ref(null);
const removeProfilePicFlag = ref(false);
const showPasswordFields = ref(false);
const showConfirmPassword = ref(false);
const passwordMatchStatus = ref("");
const passwordMatchClass = ref("");
const alertMessage = ref("");
const alertClass = ref("");

// Form data
const formData = reactive({
  username: "",
  displayName: "",
  newPassword: "",
  confirmPassword: "",
  transcodingEnabled: false,
  transcodingFormat: "aac",
  transcodingMode: "cbr",
  transcodingQuality: "medium",
  sessionTimeout: "31536000",
  language: "auto",
  selectedTheme: "default",
});

// Editable fields state
const editableFields = reactive({
  username: false,
  displayName: false,
});

// Quality options based on format
const qualityOptions = ref([
  { value: "low", label: "Niedrig (96 kbps AAC)" },
  { value: "mid", label: "Standard (192 kbps AAC)" },
  { value: "high", label: "Hoch (256 kbps AAC)" },
  { value: "very_high", label: "Sehr Hoch (320 kbps AAC)" },
]);

// Methods
function editProfilePic() {
  profileImageFile.value.click();
}

function removeProfilePic() {
  removeProfilePicFlag.value = true;
  currentProfileImageId.value = null;
  profileImagePreview.value = null;
}

function handleFileChange(event) {
  const file = event.target.files[0];
  if (file) {
    removeProfilePicFlag.value = false;
    currentProfileImageId.value = null;
    const reader = new FileReader();
    reader.onload = (e) => {
      profileImagePreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

function selectTheme(themeId) {
  formData.selectedTheme = themeId;
  themeStore.setTheme(themeId);
}

function toggleEdit(field) {
  if (editableFields[field]) {
    saveIndividualField(field);
  } else {
    editableFields[field] = !editableFields[field];
  }
}

async function saveIndividualField(field) {
  loading.value = true;

  try {
    const formDataToSend = new FormData();

    if (field === "username") {
      if (!formData.username || formData.username.trim() === "") {
        alertMessage.value = t("settings.user.usernameCannotBeEmpty");
        alertClass.value = "alert-danger";
        loading.value = false;
        return;
      }
      formDataToSend.append("username", formData.username);
      formDataToSend.append("displayName", formData.displayName);
    } else if (field === "displayName") {
      formDataToSend.append("username", formData.username);
      formDataToSend.append("displayName", formData.displayName);
    }

    const response = await apiStore.makeRequest("/api/user/settings", {
      method: "POST",
      body: formDataToSend,
    });

    const data = await response.json();

    if (data.success) {
      alertStore.success(
        data.message || t("settings.user.settingsSaveSuccess"),
      );
      editableFields[field] = false;

      if (data.logout) {
        setTimeout(() => {
          authStore.logout();
        }, 2000);
      }
    } else {
      throw new Error(data.error || t("settings.user.failedToSaveSettings"));
    }
  } catch (error) {
    console.error("Error saving field:", error);
    alertStore.error(t("settings.user.settingsSaveError") + error.message);
  } finally {
    loading.value = false;
  }
}

function togglePasswordChange() {
  showPasswordFields.value = !showPasswordFields.value;
  if (!showPasswordFields.value) {
    formData.newPassword = "";
    formData.confirmPassword = "";
    showConfirmPassword.value = false;
    passwordMatchStatus.value = "";
  }
}

function onPasswordInput() {
  if (formData.newPassword.length > 0) {
    showConfirmPassword.value = true;
  } else {
    showConfirmPassword.value = false;
    formData.confirmPassword = "";
  }
  checkPasswordMatch();
}

function checkPasswordMatch() {
  if (formData.confirmPassword.length > 0) {
    if (formData.newPassword === formData.confirmPassword) {
      passwordMatchStatus.value = t("settings.user.passwordsMatch");
      passwordMatchClass.value = "text-success";
    } else {
      passwordMatchStatus.value = t("settings.user.passwordsDoNotMatch");
      passwordMatchClass.value = "text-danger";
    }
  } else {
    passwordMatchStatus.value = "";
  }
}

function updateQualityOptions() {
  const format = formData.transcodingFormat;
  const mode = formData.transcodingMode;

  if (format === "flac") {
    qualityOptions.value = [
      {
        value: "lossless",
        label: t("settings.transcoding.qualityLevels.lossless") + " (FLAC)",
      },
    ];
    formData.transcodingQuality = "lossless";
    return;
  }

  if (mode === "cbr") {
    qualityOptions.value = [
      {
        value: "low",
        label: t("settings.transcoding.qualityLevels.low") + " (128 kbps CBR)",
      },
      {
        value: "medium",
        label:
          t("settings.transcoding.qualityLevels.medium") + " (192 kbps CBR)",
      },
      {
        value: "high",
        label: t("settings.transcoding.qualityLevels.high") + " (256 kbps CBR)",
      },
      {
        value: "very_high",
        label:
          t("settings.transcoding.qualityLevels.very_high") + " (320 kbps CBR)",
      },
    ];
  } else {
    qualityOptions.value = [
      {
        value: "low",
        label: t("settings.transcoding.qualityLevels.low") + " (~128 kbps VBR)",
      },
      {
        value: "medium",
        label:
          t("settings.transcoding.qualityLevels.medium") + " (~192 kbps VBR)",
      },
      {
        value: "high",
        label:
          t("settings.transcoding.qualityLevels.high") + " (~256 kbps VBR)",
      },
      {
        value: "very_high",
        label:
          t("settings.transcoding.qualityLevels.very_high") +
          " (~320 kbps VBR)",
      },
    ];
  }

  const currentQuality = formData.transcodingQuality;
  if (!qualityOptions.value.some((opt) => opt.value === currentQuality)) {
    formData.transcodingQuality = "medium";
  }
}

function onLanguageChange() {
  setLocale(formData.language);
}

async function loadUserSettings() {
  try {
    const response = await apiStore.makeRequest("/api/user/settings");
    const data = await response.json();

    if (data.error) {
      throw new Error(data.error);
    }

    if (data.profileImage) {
      currentProfileImageId.value = data.profileImage;
      profileImagePreview.value = null;
    } else {
      currentProfileImageId.value = null;
      profileImagePreview.value = null;
    }

    formData.username = data.username || "";
    formData.displayName = data.displayName || "";
    formData.transcodingEnabled = data.transcoding_enabled === "1";
    formData.transcodingFormat = data.transcoding_format || "aac";
    formData.transcodingMode = data.transcoding_mode || "cbr";
    formData.transcodingQuality = data.transcoding_quality || "medium";
    formData.sessionTimeout = data.sessionTimeout || "31536000";
    formData.language = data.language || getCurrentLocalePreference();
    formData.selectedTheme = data.selectedTheme || themeStore.currentTheme;

    updateQualityOptions();
  } catch (error) {
    console.error("Error loading user settings:", error);
    alertStore.error(t("settings.user.settingsLoadError") + error.message);
  }
}

async function saveSettings() {
  if (
    formData.newPassword &&
    formData.newPassword !== formData.confirmPassword
  ) {
    alertStore.error(t("settings.user.passwordsDoNotMatchError"));
    return;
  }

  if (!formData.username || formData.username.trim() === "") {
    alertMessage.value = t("settings.user.usernameCannotBeEmpty");
    alertClass.value = "alert-danger";
    return;
  }

  loading.value = true;

  try {
    const formDataToSend = new FormData();

    if (profileImageFile.value?.files[0]) {
      formDataToSend.append("profileImage", profileImageFile.value.files[0]);
    }
    formDataToSend.append(
      "removeProfilePic",
      removeProfilePicFlag.value ? "1" : "0",
    );

    formDataToSend.append("username", formData.username);
    formDataToSend.append("displayName", formData.displayName);
    if (formData.newPassword) {
      formDataToSend.append("newPassword", formData.newPassword);
    }

    formDataToSend.append(
      "transcoding_enabled",
      formData.transcodingEnabled ? "1" : "0",
    );
    formDataToSend.append("transcoding_format", formData.transcodingFormat);
    formDataToSend.append("transcoding_mode", formData.transcodingMode);
    formDataToSend.append("transcoding_quality", formData.transcodingQuality);
    formDataToSend.append("sessionTimeout", formData.sessionTimeout);
    formDataToSend.append("language", formData.language);
    formDataToSend.append("selectedTheme", formData.selectedTheme);

    const response = await apiStore.makeRequest("/api/user/settings", {
      method: "POST",
      body: formDataToSend,
    });

    const data = await response.json();

    if (data.success) {
      alertStore.success(
        data.message || t("settings.user.settingsSaveSuccess"),
      );

      if (formData.newPassword) {
        formData.newPassword = "";
        formData.confirmPassword = "";
        showPasswordFields.value = false;
        showConfirmPassword.value = false;
        passwordMatchStatus.value = "";
      }

      Object.keys(editableFields).forEach((key) => {
        editableFields[key] = false;
      });

      if (data.logout) {
        setTimeout(() => {
          authStore.logout();
        }, 2000);
      }
    } else {
      throw new Error(data.error || t("settings.user.failedToSaveSettings"));
    }
  } catch (error) {
    console.error("Error saving settings:", error);
    alertStore.error(t("settings.user.settingsSaveError") + error.message);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadUserSettings();
});
</script>

<style scoped>
select option {
  background-color: rgb(17 24 39);
  color: white;
}
</style>
