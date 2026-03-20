<template>
  <div
    v-if="isVisible"
    class="fixed inset-0 z-51 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
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
        <h5 class="text-lg font-semibold">
          {{ $t("playlist.deleteConfirm") }}
        </h5>
        <button
          type="button"
          class="rounded-full p-1 text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
          @click="closeModal"
        >
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="flex-1 p-6 overflow-y-auto">
        <p class="text-gray-300">
          {{ $t("playlist.deleteMessage", { name: playlistToDelete?.name }) }}
        </p>
      </div>
      <div class="flex justify-end gap-3 border-t border-white/10 p-4">
        <button
          type="button"
          class="px-4 py-2 text-sm border border-white/20 rounded-lg text-gray-300 hover:bg-white/10 transition-colors"
          @click="closeModal"
        >
          {{ $t("common.cancel") }}
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 rounded-lg text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          @click="deletePlaylist"
          :disabled="isLoading"
        >
          <span
            v-if="isLoading"
            class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"
          ></span>
          {{ $t("common.delete") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { useI18n } from "vue-i18n";
import { useThemeStore } from "@/stores/theme";

export default {
  name: "PlaylistDeleteModal",
  emits: ["close", "delete-playlist"],
  props: {
    isVisible: {
      type: Boolean,
      default: false,
    },
    playlistToDelete: {
      type: Object,
      default: null,
    },
    isLoading: {
      type: Boolean,
      default: false,
    },
  },
  setup(props, { emit }) {
    const { t } = useI18n();
    const themeStore = useThemeStore();

    const closeModal = () => {
      emit("close");
    };

    const handleBackdropClick = () => {
      closeModal();
    };

    const deletePlaylist = () => {
      emit("delete-playlist");
    };

    return {
      t,
      closeModal,
      handleBackdropClick,
      deletePlaylist,
      themeStore,
    };
  },
};
</script>
