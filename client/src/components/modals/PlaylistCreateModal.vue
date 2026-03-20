<template>
  <div
    v-if="isVisible"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
    @click="handleBackdropClick"
  >
    <div
      class="w-full max-w-md max-h-[90vh] overflow-hidden rounded-lg text-white shadow-xl border border-white/10"
      :class="themeStore.backgroundGradient"
      @click.stop
    >
      <div
        class="flex items-center justify-between border-b border-white/10 p-4"
      >
        <h5 class="text-lg font-semibold">{{ $t("playlist.create") }}</h5>
        <button
          type="button"
          class="rounded-full p-1 text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
          @click="closeModal"
        >
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="flex-1 p-6 overflow-y-auto">
        <form @submit.prevent="createPlaylist">
          <!-- Playlist Name -->
          <div class="mb-4">
            <label
              for="playlistName"
              class="block text-sm font-medium text-gray-300 mb-2"
              >{{ $t("playlist.name") }}</label
            >
            <input
              type="text"
              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
              id="playlistName"
              v-model="playlistData.name"
              :placeholder="$t('playlist.namePlaceholder')"
              required
              ref="nameInput"
            />
          </div>

          <!-- Playlist Description -->
          <div class="mb-4">
            <label
              for="playlistDescription"
              class="block text-sm font-medium text-gray-300 mb-2"
              >{{ $t("playlist.description") }}</label
            >
            <textarea
              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent resize-none"
              id="playlistDescription"
              v-model="playlistData.description"
              :placeholder="$t('playlist.descriptionPlaceholder')"
              rows="3"
            ></textarea>
          </div>
        </form>
      </div>
      <div class="flex justify-end gap-3 border-t border-white/10 p-4">
        <button
          type="button"
          class="px-4 py-2 text-sm border border-white/20 rounded-lg text-gray-300 hover:bg-white/10 transition-colors"
          @click="closeModal"
          :disabled="isCreating"
        >
          {{ $t("common.cancel") }}
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm bg-audinary hover:bg-audinary/90 rounded-lg text-black transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          @click="createPlaylist"
          :disabled="!canCreate || isCreating"
        >
          <span
            v-if="isCreating"
            class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"
          ></span>
          {{ $t("playlist.create") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, nextTick } from "vue";
import { useI18n } from "vue-i18n";
import { usePlaylistStore } from "@/stores/playlist";
import { useAlertStore } from "@/stores/alert";
import { useAuthStore } from "@/stores/auth";
import { useThemeStore } from "@/stores/theme";

export default {
  name: "PlaylistCreateModal",
  emits: ["close", "created"],
  props: {
    isVisible: {
      type: Boolean,
      default: false,
    },
  },
  setup(props, { emit }) {
    const { t } = useI18n();
    const playlistStore = usePlaylistStore();
    const alertStore = useAlertStore();
    const authStore = useAuthStore();
    const themeStore = useThemeStore();

    const nameInput = ref(null);
    const isCreating = ref(false);

    const playlistData = ref({
      name: "",
      description: "",
    });

    const canCreate = computed(() => {
      return playlistData.value.name.trim().length > 0;
    });

    // Focus input when modal becomes visible
    watch(
      () => props.isVisible,
      (newVal) => {
        if (newVal) {
          nextTick(() => {
            if (nameInput.value) {
              nameInput.value.focus();
            }
          });
        } else {
          // Reset form when modal is closed
          resetForm();
        }
      },
    );

    const resetForm = () => {
      playlistData.value = {
        name: "",
        description: "",
      };
    };

    const closeModal = () => {
      emit("close");
    };

    const handleBackdropClick = () => {
      closeModal();
    };

    const createPlaylist = async () => {
      if (!canCreate.value || isCreating.value) return;

      try {
        isCreating.value = true;

        const newPlaylist = await playlistStore.createPlaylist({
          name: playlistData.value.name.trim(),
          description: playlistData.value.description.trim(),
          user_id: authStore.user?.user_id || authStore.user?.id,
        });

        alertStore.success(t("playlist.created", { name: newPlaylist.name }));
        emit("created", newPlaylist);
        closeModal();
      } catch (error) {
        console.error("Error creating playlist:", error);
        alertStore.error(error.message || t("playlist.createError"));
      } finally {
        isCreating.value = false;
      }
    };

    return {
      t,
      nameInput,
      isCreating,
      playlistData,
      canCreate,
      closeModal,
      handleBackdropClick,
      createPlaylist,
      themeStore,
    };
  },
};
</script>

<style scoped>
/* TailwindCSS handles most styling, minimal custom styles needed */
</style>
