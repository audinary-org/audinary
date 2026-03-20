<template>
  <div
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="$emit('close')"
  >
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4 shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-white flex items-center gap-2">
          <i class="bi bi-pencil"></i>
          {{ $t("shares.edit_share") }}
        </h2>
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-white transition-colors"
        >
          <i class="bi bi-x-lg text-xl"></i>
        </button>
      </div>

      <!-- Item Info -->
      <div class="mb-6 p-4 bg-gray-700/50 rounded-lg">
        <div class="flex items-center gap-3">
          <i class="bi text-audinary text-xl" :class="getTypeIcon()"></i>
          <div class="flex-1 min-w-0">
            <div class="font-medium text-white truncate">
              {{ getItemTitle() }}
            </div>
            <div class="text-sm text-gray-400">{{ getTypeLabel() }}</div>
          </div>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="updateShare" class="space-y-4">
        <!-- Share Name -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">
            {{ $t("shares.name") }}
          </label>
          <input
            v-model="shareData.name"
            type="text"
            :placeholder="$t('shares.name_placeholder')"
            class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:border-audinary focus:ring-1 focus:ring-audinary placeholder-gray-400"
          />
          <p class="text-xs text-gray-400 mt-1">
            {{ $t("shares.name_description") }}
          </p>
        </div>

        <!-- Expiration -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">
            {{ $t("shares.expires") }}
          </label>
          <select
            v-model="shareData.expires_at"
            class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:border-audinary focus:ring-1 focus:ring-audinary"
          >
            <option :value="null">{{ $t("shares.expiration_never") }}</option>
            <option value="+1 hour">
              {{ $t("shares.expiration_1_hour") }}
            </option>
            <option value="+1 day">
              {{ $t("shares.expiration_24_hours") }}
            </option>
            <option value="+1 week">
              {{ $t("shares.expiration_7_days") }}
            </option>
            <option value="+1 month">
              {{ $t("shares.expiration_30_days") }}
            </option>
            <option value="+3 months">
              {{ $t("shares.expiration_3_months") }}
            </option>
            <option value="+6 months">
              {{ $t("shares.expiration_6_months") }}
            </option>
            <option value="+1 year">
              {{ $t("shares.expiration_1_year") }}
            </option>
          </select>
        </div>

        <!-- Password Protection -->
        <div>
          <label class="flex items-center space-x-3">
            <input
              type="checkbox"
              v-model="usePassword"
              class="rounded border-gray-600 bg-gray-700 text-audinary focus:ring-audinary focus:ring-offset-0"
            />
            <span class="text-sm font-medium text-gray-300">
              {{ $t("shares.password_protection") }}
            </span>
          </label>
        </div>

        <!-- Password Input -->
        <div v-if="usePassword">
          <input
            v-model="shareData.password"
            type="password"
            :placeholder="$t('shares.enter_new_password')"
            class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:border-audinary focus:ring-1 focus:ring-audinary"
            minlength="4"
          />
          <p class="text-xs text-gray-400 mt-1">
            {{ $t("shares.password_change_hint") }}
          </p>
        </div>

        <!-- Download Permission (Admin only) -->
        <div v-if="authStore.isAdmin" class="border-t border-gray-600 pt-4">
          <label class="flex items-center space-x-3">
            <input
              type="checkbox"
              v-model="shareData.download_enabled"
              class="rounded border-gray-600 bg-gray-700 text-audinary focus:ring-audinary focus:ring-offset-0"
            />
            <span class="text-sm font-medium text-gray-300">
              {{ $t("shares.allow_downloads") }}
            </span>
          </label>
        </div>

        <!-- Error Message -->
        <div
          v-if="errorMessage"
          class="text-red-400 text-sm bg-red-900/20 border border-red-700/30 rounded-lg p-3"
        >
          {{ errorMessage }}
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 pt-4">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-gray-300 hover:text-white transition-colors"
          >
            {{ $t("common.cancel") }}
          </button>
          <button
            type="submit"
            :disabled="isLoading"
            class="bg-audinary hover:bg-audinary/90 disabled:opacity-50 disabled:cursor-not-allowed text-black font-semibold px-6 py-2 rounded-lg transition-all flex items-center gap-2"
          >
            <i v-if="isLoading" class="bi bi-hourglass animate-spin"></i>
            <i v-else class="bi bi-check"></i>
            {{ isLoading ? $t("common.saving") : $t("common.save") }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useI18n } from "vue-i18n";

const { t } = useI18n();
const authStore = useAuthStore();

const emit = defineEmits(["close", "share-updated"]);

const props = defineProps({
  share: {
    type: Object,
    required: true,
  },
});

// Form Data
const shareData = ref({
  name: props.share.name || "",
  expires_at: props.share.expires_at,
  password: "",
  download_enabled: props.share.download_enabled,
});

const usePassword = ref(!!props.share.has_password);
const isLoading = ref(false);
const errorMessage = ref("");

const expirationOptions = ref([
  { label: "never", value: null },
  { label: "1_hour", value: "+1 hour" },
  { label: "24_hours", value: "+1 day" },
  { label: "7_days", value: "+1 week" },
  { label: "30_days", value: "+1 month" },
  { label: "3_months", value: "+3 months" },
  { label: "6_months", value: "+6 months" },
  { label: "1_year", value: "+1 year" },
]);

function getTypeIcon() {
  const icons = {
    song: "bi-music-note",
    album: "bi-disc",
    playlist: "bi-music-note-list",
  };
  return icons[props.share.type] || "bi-share";
}

function getTypeLabel() {
  const typeKeys = {
    song: "shares.sharing_type_song",
    album: "shares.sharing_type_album",
    playlist: "shares.sharing_type_playlist",
  };
  return t(typeKeys[props.share.type]);
}

function getItemTitle() {
  // This would ideally come from the backend with item details
  return `${props.share.type} - ${props.share.item_id}`;
}

function parseTimeString(timeStr) {
  const timeMap = {
    "+1 hour": 60 * 60 * 1000,
    "+1 day": 24 * 60 * 60 * 1000,
    "+1 week": 7 * 24 * 60 * 60 * 1000,
    "+1 month": 30 * 24 * 60 * 60 * 1000,
    "+3 months": 3 * 30 * 24 * 60 * 60 * 1000,
    "+6 months": 6 * 30 * 24 * 60 * 60 * 1000,
    "+1 year": 365 * 24 * 60 * 60 * 1000,
  };

  return timeMap[timeStr] || 0;
}

async function updateShare() {
  if (isLoading.value) return;

  isLoading.value = true;
  errorMessage.value = "";

  try {
    const payload = {};

    // Handle name
    payload.name =
      shareData.value.name && shareData.value.name.trim() !== ""
        ? shareData.value.name.trim()
        : null;

    // Handle expiration
    if (shareData.value.expires_at) {
      payload.expires_at = new Date(
        Date.now() + parseTimeString(shareData.value.expires_at),
      ).toISOString();
    } else {
      payload.expires_at = null;
    }

    // Handle password
    if (usePassword.value && shareData.value.password) {
      payload.password = shareData.value.password;
    } else if (!usePassword.value) {
      payload.password = ""; // Clear password
    }

    // Handle download permission (admin only)
    if (authStore.isAdmin) {
      payload.download_enabled = shareData.value.download_enabled;
    }

    const response = await fetch(`/api/shares/${props.share.id}`, {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${authStore.token}`,
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.error || "Failed to update share");
    }

    emit("share-updated", data.share);
  } catch (error) {
    console.error("Error updating share:", error);
    errorMessage.value = error.message;
  } finally {
    isLoading.value = false;
  }
}

// Initialize form with current share data
onMounted(() => {
  if (props.share.expires_at) {
    // Try to map current expiration to one of the options
    const expirationDate = new Date(props.share.expires_at);
    const now = new Date();
    const diffMs = expirationDate.getTime() - now.getTime();

    // Find closest matching option
    if (diffMs > 0) {
      const diffHours = Math.round(diffMs / (60 * 60 * 1000));

      if (diffHours <= 2) {
        shareData.value.expires_at = "+1 hour";
      } else if (diffHours <= 36) {
        shareData.value.expires_at = "+1 day";
      } else if (diffHours <= 10 * 24) {
        shareData.value.expires_at = "+1 week";
      } else if (diffHours <= 45 * 24) {
        shareData.value.expires_at = "+1 month";
      } else if (diffHours <= 4 * 30 * 24) {
        shareData.value.expires_at = "+3 months";
      } else if (diffHours <= 8 * 30 * 24) {
        shareData.value.expires_at = "+6 months";
      } else {
        shareData.value.expires_at = "+1 year";
      }
    }
  }
});
</script>
