<template>
  <div
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="$emit('close')"
  >
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4 shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-white flex items-center gap-2">
          <i class="bi bi-share"></i>
          {{ $t("shares.create_share") }}
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
            <div class="text-sm text-gray-400">{{ shareTypeLabel }}</div>
          </div>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="createShare" class="space-y-4">
        <!-- Share Name -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">
            {{ $t("shares.share_name") }}
          </label>
          <input
            v-model="shareData.name"
            type="text"
            :placeholder="$t('shares.share_name_placeholder')"
            class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:border-audinary focus:ring-1 focus:ring-audinary"
            maxlength="100"
          />
          <p class="text-xs text-gray-400 mt-1">
            {{ $t("shares.share_name_help") }}
          </p>
        </div>

        <!-- Expiration -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">
            {{ $t("shares.expires") }}
          </label>
          <select v-model="shareData.expires_at" class="w-full">
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
            :placeholder="$t('shares.enter_password')"
            class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:border-audinary focus:ring-1 focus:ring-audinary"
            minlength="4"
            required
          />
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
            <i v-else class="bi bi-share"></i>
            {{ isLoading ? $t("common.creating") : $t("shares.create_share") }}
          </button>
        </div>
      </form>

      <!-- Share URL (after creation) -->
      <div
        v-if="shareUrl"
        class="mt-6 p-4 bg-green-900/20 border border-green-700/30 rounded-lg"
      >
        <div class="flex items-center justify-between gap-3">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-green-400 mb-1">
              {{ $t("shares.share_created") }}
            </div>
            <input
              ref="shareUrlInput"
              :value="shareUrl"
              readonly
              class="w-full bg-gray-700 border border-gray-600 text-white text-sm rounded px-2 py-1 focus:border-audinary"
            />
          </div>
          <button
            @click="copyShareUrl"
            class="bg-audinary hover:bg-audinary/90 text-black px-3 py-1 rounded text-sm font-medium transition-colors flex items-center gap-1"
          >
            <i class="bi bi-clipboard"></i>
            {{ $t("common.copy") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useAlertStore } from "@/stores/alert";
import { useI18n } from "vue-i18n";
import { useClipboard } from "@/composables/useClipboard";

const { t } = useI18n();
const authStore = useAuthStore();
const alertStore = useAlertStore();
const { copyToClipboard } = useClipboard();

const emit = defineEmits(["close", "share-created"]);

const props = defineProps({
  type: {
    type: String,
    required: true,
    validator: (value) => ["song", "album", "playlist"].includes(value),
  },
  itemId: {
    type: String,
    required: true,
  },
  itemData: {
    type: Object,
    required: true,
  },
});

// Form Data
const shareData = ref({
  type: props.type,
  item_id: props.itemId,
  name: "",
  expires_at: null,
  password: "",
  download_enabled: false,
});

const usePassword = ref(false);
const isLoading = ref(false);
const errorMessage = ref("");
const shareUrl = ref("");
const shareUrlInput = ref(null);

const shareTypeLabel = computed(() => {
  const typeKeys = {
    song: "shares.sharing_type_song",
    album: "shares.sharing_type_album",
    playlist: "shares.sharing_type_playlist",
  };
  return t(typeKeys[shareData.value.type]);
});

function getTypeIcon() {
  const icons = {
    song: "bi-music-note",
    album: "bi-disc",
    playlist: "bi-music-note-list",
  };
  return icons[props.type] || "bi-share";
}

function getItemTitle() {
  if (props.type === "song") {
    return `${props.itemData.artist} - ${props.itemData.title}`;
  } else if (props.type === "album") {
    return `${props.itemData.artist || props.itemData.album_artist} - ${props.itemData.album_name}`;
  } else if (props.type === "playlist") {
    return props.itemData.name || props.itemData.playlist_name;
  }
  return "Unknown Item";
}

async function createShare() {
  if (isLoading.value) return;

  isLoading.value = true;
  errorMessage.value = "";

  try {
    const payload = {
      type: shareData.value.type,
      item_id: shareData.value.item_id,
      name: shareData.value.name || null,
      expires_at: shareData.value.expires_at
        ? new Date(
            Date.now() + parseTimeString(shareData.value.expires_at),
          ).toISOString()
        : null,
      download_enabled: shareData.value.download_enabled,
    };

    if (usePassword.value && shareData.value.password) {
      payload.password = shareData.value.password;
    }

    const response = await fetch("/api/shares", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${authStore.token}`,
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.error || "Failed to create share");
    }

    // Generate share URL
    shareUrl.value = `${window.location.origin}/share/${data.share.share_uuid}`;

    emit("share-created", data.share);

    // Auto-focus and select the URL for easy copying
    await nextTick();
    if (shareUrlInput.value) {
      shareUrlInput.value.focus();
      shareUrlInput.value.select();
    }
  } catch (error) {
    console.error("Error creating share:", error);
    errorMessage.value = error.message;
  } finally {
    isLoading.value = false;
  }
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

async function copyShareUrl() {
  await copyToClipboard(shareUrl.value, {
    onSuccess: () => {
      alertStore.addAlert(t("shares.url_copied_success"), "success", 3000);
    },
    onError: (error) => {
      console.error("Failed to copy URL:", error);
      alertStore.addAlert(t("shares.url_copy_failed"), "error", 5000);
      // Additional fallback: select the input if available
      if (shareUrlInput.value) {
        shareUrlInput.value.focus();
        shareUrlInput.value.select();
      }
    },
  });
}
</script>
