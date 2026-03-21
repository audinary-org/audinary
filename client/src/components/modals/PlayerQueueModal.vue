<template>
  <div
    class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
    @click="handleBackdropClick"
  >
    <div class="w-full max-w-4xl overflow-hidden" @click.stop>
      <div
        class="text-white rounded-lg shadow-xl border border-white/10 h-[80vh] flex flex-col"
        :class="themeStore.backgroundGradient"
      >
        <div
          class="flex-shrink-0 flex justify-between items-center p-4 border-b border-white/10"
        >
          <h5 class="text-xl font-medium">
            <i class="bi bi-music-note-list mr-2"></i>
            {{ $t("player.queue.title") }}
          </h5>
          <button
            type="button"
            class="text-white hover:text-gray-300 text-xl leading-none"
            @click="$emit('close')"
          >
            <i class="bi bi-x"></i>
          </button>
        </div>

        <div class="flex-1 p-6 overflow-hidden flex flex-col">
          <!-- Tabs -->
          <div class="flex-shrink-0 flex border-b border-white/10 mb-4">
            <button
              class="flex-1 py-3 px-4 text-center transition-colors border-b-2"
              :class="
                activeTab === 'queue'
                  ? 'border-audinary text-white bg-audinary/20'
                  : 'border-transparent text-gray-400 hover:text-gray-200 hover:bg-white/10'
              "
              @click="activeTab = 'queue'"
              type="button"
            >
              <i class="bi bi-skip-forward mr-2"></i>
              {{ $t("player.queue.upNext") }}
              <span
                v-if="queue.length > 0"
                class="inline-block bg-audinary text-black text-xs rounded-full px-2 py-1 ml-2"
                >{{ queue.length }}</span
              >
            </button>
            <button
              class="flex-1 py-3 px-4 text-center transition-colors border-b-2"
              :class="
                activeTab === 'history'
                  ? 'border-audinary text-white bg-audinary/20'
                  : 'border-transparent text-gray-400 hover:text-gray-200 hover:bg-white/10'
              "
              @click="activeTab = 'history'"
              type="button"
            >
              <i class="bi bi-clock-history mr-2"></i>
              {{ $t("player.queue.history") }}
              <span
                v-if="previousQueue.length > 0"
                class="inline-block bg-gray-600 text-white text-xs rounded-full px-2 py-1 ml-2"
                >{{ previousQueue.length }}</span
              >
            </button>
          </div>

          <!-- Now Playing Section (Always Visible) -->
          <div
            class="flex-shrink-0 now-playing-section mb-6 p-4 rounded-lg bg-white/5 border border-white/10"
          >
            <h6 class="text-base font-medium mb-3">
              <i class="bi bi-play-circle mr-2"></i>
              {{ $t("player.queue.nowPlaying") }}
            </h6>
            <div v-if="currentSong" class="flex items-center">
              <div class="relative mr-3" style="width: 60px; height: 60px">
                <!-- Gradient placeholder background -->
                <div
                  v-if="
                    currentSong.coverGradient &&
                    currentSong.coverGradient.colors
                  "
                  class="absolute inset-0 rounded"
                  :style="{
                    background: `linear-gradient(${currentSong.coverGradient.angle || 135}deg, ${currentSong.coverGradient.colors.join(', ')})`,
                    filter: 'blur(10px)',
                    zIndex: 1,
                  }"
                ></div>
                <SimpleImage
                  :imageType="'album_thumbnail'"
                  :imageId="currentSong?.album_id || 'default'"
                  :alt="currentSong.title"
                  class="rounded relative z-[2]"
                  style="width: 60px; height: 60px; object-fit: cover"
                  :placeholder="'disc'"
                  :placeholderSize="'30px'"
                />
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-bold mb-1 text-white">
                  {{ currentSong.title }}
                </div>
                <div class="text-gray-400">{{ currentSong.artist }}</div>
                <div class="text-gray-400 text-sm">{{ currentSong.album }}</div>
              </div>
              <div class="text-gray-400 text-sm">
                {{ formatTime(currentSong.duration) }}
              </div>
            </div>
            <div v-else class="text-center py-3">
              <i class="bi bi-music-note text-gray-500 mr-2"></i>
              <span class="text-gray-500">{{ $t("player.noSong") }}</span>
            </div>
          </div>

          <!-- Tab Content -->
          <div class="flex-1 tab-content overflow-hidden">
            <!-- Queue Tab -->
            <div
              v-if="activeTab === 'queue'"
              class="tab-pane h-full flex flex-col"
            >
              <div class="flex-shrink-0 flex justify-between items-center mb-4">
                <h6 class="text-lg font-medium text-white mb-0">
                  {{ $t("player.queue.upNext") }}
                </h6>
                <div class="flex gap-2">
                  <!-- Edit Mode Toggle -->
                  <button
                    v-if="queue.length > 0"
                    class="px-3 py-2 text-sm rounded transition-colors"
                    :class="
                      isEditMode
                        ? 'bg-audinary text-black'
                        : 'bg-white/10 text-gray-300 hover:bg-white/20'
                    "
                    @click="toggleEditMode"
                    :title="
                      isEditMode ? 'Bearbeitung beenden' : 'Queue bearbeiten'
                    "
                  >
                    <i
                      class="bi"
                      :class="isEditMode ? 'bi-check-lg' : 'bi-pencil-square'"
                    ></i>
                    <span class="hidden md:inline ml-1">
                      {{ isEditMode ? "Fertig" : "Bearbeiten" }}
                    </span>
                  </button>

                  <!-- Clear Queue -->
                  <button
                    v-if="queue.length > 0"
                    class="px-3 py-2 text-sm border border-red-500 text-red-500 hover:bg-red-500/20 rounded transition-colors disabled:opacity-50"
                    @click="clearQueue"
                    :disabled="isEditMode"
                  >
                    <i class="bi bi-trash mr-1"></i>
                    <span class="hidden md:inline">{{
                      $t("player.queue.clear")
                    }}</span>
                  </button>
                </div>
              </div>

              <div
                v-if="queue.length === 0"
                class="flex-1 flex items-center justify-center"
              >
                <div class="text-center py-8">
                  <i class="bi bi-music-note-list text-6xl text-gray-500"></i>
                  <h5 class="mt-4 text-xl text-gray-500">
                    {{ $t("player.queue.empty") }}
                  </h5>
                  <p class="text-gray-500 mt-2">
                    {{ $t("player.queue.emptyDescription") }}
                  </p>
                </div>
              </div>

              <!-- Queue List -->
              <div
                v-else
                class="flex-1 queue-list overflow-y-auto custom-scrollbar"
              >
                <!-- Draggable Queue List (Edit Mode) -->
                <draggable
                  v-if="isEditMode"
                  v-model="editableQueue"
                  @end="onDragEnd"
                  :animation="200"
                  :touch-start-threshold="20"
                  class="draggable-list"
                  ghost-class="drag-ghost"
                  chosen-class="drag-chosen"
                  drag-class="drag-active"
                  item-key="id"
                >
                  <template #item="{ element: song, index }">
                    <div
                      :key="`edit-${song.id || song.song_id}-${index}`"
                      class="flex items-center p-3 rounded-lg queue-item draggable-item"
                    >
                      <!-- Drag Handle -->
                      <div class="drag-handle mr-3">
                        <i class="bi bi-grip-vertical text-gray-500"></i>
                      </div>

                      <div
                        class="relative mr-3"
                        style="width: 40px; height: 40px"
                      >
                        <!-- Gradient placeholder background -->
                        <div
                          v-if="song.coverGradient && song.coverGradient.colors"
                          class="absolute inset-0 rounded"
                          :style="{
                            background: `linear-gradient(${song.coverGradient.angle || 135}deg, ${song.coverGradient.colors.join(', ')})`,
                            filter: 'blur(10px)',
                            zIndex: 1,
                          }"
                        ></div>
                        <SimpleImage
                          :imageType="'album_thumbnail'"
                          :imageId="song?.album_id || 'default'"
                          :alt="song.title"
                          class="rounded relative z-[2]"
                          style="width: 40px; height: 40px; object-fit: cover"
                          :placeholder="'disc'"
                          :placeholderSize="'20px'"
                        />
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="font-medium text-white">
                          {{ song.title }}
                        </div>
                        <div class="text-gray-400 text-sm">
                          {{ song.artist }} - {{ song.album }}
                        </div>
                      </div>
                      <div class="text-gray-400 text-sm mr-3">
                        {{ formatTime(song.duration) }}
                      </div>

                      <!-- Edit Mode Actions -->
                      <div class="edit-actions flex gap-2">
                        <!-- Move to Next Button -->
                        <button
                          class="p-2 text-green-500 border border-green-500 rounded hover:bg-green-500/20 transition-colors"
                          @click="moveToNext(index)"
                          :title="'Als nächstes'"
                        >
                          <i class="bi bi-arrow-up-short"></i>
                        </button>

                        <!-- Remove Button -->
                        <button
                          class="p-2 text-red-500 border border-red-500 rounded hover:bg-red-500/20 transition-colors"
                          @click="removeFromQueue(index)"
                          :title="'Entfernen'"
                        >
                          <i class="bi bi-x"></i>
                        </button>
                      </div>
                    </div>
                  </template>
                </draggable>

                <!-- Normal Queue List (View Mode) -->
                <div v-else>
                  <div
                    v-for="(song, index) in queue"
                    :key="`queue-${song.id || song.song_id}-${index}`"
                    class="flex items-center p-3 rounded-lg queue-item cursor-pointer hover:bg-white/5 transition-colors"
                    @click="playFromQueue(index)"
                  >
                    <div
                      class="queue-number w-8 text-center text-gray-400 text-sm mr-3"
                    >
                      {{ index + 1 }}
                    </div>
                    <div
                      class="relative mr-3"
                      style="width: 40px; height: 40px"
                    >
                      <!-- Gradient placeholder background -->
                      <div
                        v-if="song.coverGradient && song.coverGradient.colors"
                        class="absolute inset-0 rounded"
                        :style="{
                          background: `linear-gradient(${song.coverGradient.angle || 135}deg, ${song.coverGradient.colors.join(', ')})`,
                          filter: 'blur(10px)',
                          zIndex: 1,
                        }"
                      ></div>
                      <SimpleImage
                        :imageType="'album_thumbnail'"
                        :imageId="song?.album_id || 'default'"
                        :alt="song.title"
                        class="rounded relative z-[2]"
                        style="width: 40px; height: 40px; object-fit: cover"
                        :placeholder="'disc'"
                        :placeholderSize="'20px'"
                      />
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="font-medium text-white">{{ song.title }}</div>
                      <div class="text-gray-400 text-sm">
                        {{ song.artist }} - {{ song.album }}
                      </div>
                    </div>
                    <div class="text-gray-400 text-sm mr-3">
                      {{ formatTime(song.duration) }}
                    </div>

                    <!-- Quick Actions -->
                    <div
                      class="quick-actions flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity"
                    >
                      <button
                        class="p-2 text-green-500 border border-green-500 rounded hover:bg-green-500/20 transition-colors"
                        @click.stop="moveToNext(index)"
                        :title="'Als nächstes'"
                      >
                        <i class="bi bi-arrow-up-short"></i>
                      </button>
                      <button
                        class="p-2 text-gray-400 border border-gray-600 rounded hover:bg-gray-600/20 transition-colors"
                        @click.stop="removeFromQueue(index)"
                        :title="'Entfernen'"
                      >
                        <i class="bi bi-x"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- History Tab -->
            <div
              v-if="activeTab === 'history'"
              class="tab-pane h-full flex flex-col"
            >
              <div class="flex-shrink-0 flex justify-between items-center mb-4">
                <h6 class="text-lg font-medium text-white mb-0">
                  {{ $t("player.queue.history") }}
                </h6>
                <button
                  v-if="previousQueue.length > 0"
                  class="px-3 py-2 text-sm border border-gray-600 text-gray-400 hover:bg-gray-600/20 rounded transition-colors"
                  @click="clearHistory"
                >
                  <i class="bi bi-clock-history mr-1"></i>
                  Clear History
                </button>
              </div>

              <div
                v-if="previousQueue.length === 0"
                class="flex-1 flex items-center justify-center"
              >
                <div class="text-center py-8">
                  <i class="bi bi-clock-history text-6xl text-gray-500"></i>
                  <h5 class="mt-4 text-xl text-gray-500">No History</h5>
                  <p class="text-gray-500 mt-2">
                    Previously played songs will appear here
                  </p>
                </div>
              </div>

              <div
                v-else
                class="flex-1 queue-list overflow-y-auto custom-scrollbar"
              >
                <div
                  v-for="(song, index) in reversedPreviousQueue"
                  :key="`history-${song.id || song.song_id}-${index}`"
                  class="flex items-center p-3 rounded-lg queue-item cursor-pointer hover:bg-white/5 transition-colors"
                  @click="playFromHistory(previousQueue.length - 1 - index)"
                >
                  <div class="queue-number w-8 text-center text-gray-400 mr-3">
                    <i class="bi bi-clock-history"></i>
                  </div>
                  <div class="relative mr-3" style="width: 40px; height: 40px">
                    <!-- Gradient placeholder background -->
                    <div
                      v-if="song.coverGradient && song.coverGradient.colors"
                      class="absolute inset-0 rounded"
                      :style="{
                        background: `linear-gradient(${song.coverGradient.angle || 135}deg, ${song.coverGradient.colors.join(', ')})`,
                        filter: 'blur(10px)',
                        zIndex: 1,
                      }"
                    ></div>
                    <SimpleImage
                      :imageType="'album_thumbnail'"
                      :imageId="song?.album_id || 'default'"
                      :alt="song.title"
                      class="rounded relative z-[2]"
                      style="width: 40px; height: 40px; object-fit: cover"
                      :placeholder="'disc'"
                      :placeholderSize="'20px'"
                    />
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="font-medium text-white">{{ song.title }}</div>
                    <div class="text-gray-400 text-sm">
                      {{ song.artist }} - {{ song.album }}
                    </div>
                  </div>
                  <div class="text-gray-400 text-sm mr-3">
                    {{ formatTime(song.duration) }}
                  </div>
                  <div class="text-gray-400">
                    <i class="bi bi-play-circle"></i>
                  </div>
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
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import { usePlayerStore } from "@/stores/player";
import { useThemeStore } from "@/stores/theme";
import draggable from "vuedraggable";
import SimpleImage from "@/components/common/SimpleImage.vue";

export default {
  name: "PlayerQueueModal",
  emits: ["close"],
  components: {
    draggable,
    SimpleImage,
  },
  setup(props, { emit }) {
    const { t } = useI18n();
    const playerStore = usePlayerStore();
    const themeStore = useThemeStore();

    const currentSong = computed(() => playerStore.currentSong);
    const queue = computed(() => playerStore.upcomingQueue);
    const previousQueue = computed(() => playerStore.previousQueue);

    const activeTab = ref("queue");
    const isEditMode = ref(false);
    const editableQueue = ref([]);

    // editableQueue is populated when entering edit mode (toggleEditMode)
    // No deep watcher needed - avoids expensive reactivity traversal of 100+ songs

    const playFromQueue = (index) => {
      if (isEditMode.value) return; // Prevent play while editing

      const actualUpcomingIndex = index;
      playerStore.playFromQueue(actualUpcomingIndex);
    };

    const removeFromQueue = (index) => {
      // Index bezieht sich auf die upcoming queue (ohne currentSong)
      const actualIndexInActualPlayQueue =
        playerStore.currentSongIndexInActualPlayQueue + 1 + index;
      playerStore.removeFromQueue(actualIndexInActualPlayQueue);

      // Update editable queue if in edit mode
      if (isEditMode.value) {
        editableQueue.value.splice(index, 1);
      }
    };

    const clearQueue = () => {
      if (confirm(t("player.queue.confirmClear"))) {
        playerStore.clearQueue();
        isEditMode.value = false;
      }
    };

    const playFromHistory = (index) => {
      playerStore.playFromHistory(index);
      emit("close");
    };

    const clearHistory = () => {
      if (
        confirm(
          t("player.queue.confirmClearHistory") || "Clear playback history?",
        )
      ) {
        playerStore.previousQueue.splice(0, playerStore.previousQueue.length);
      }
    };

    const reversedPreviousQueue = computed(() => {
      return previousQueue.value.slice().reverse();
    });

    const toggleEditMode = () => {
      if (isEditMode.value) {
        // Exiting edit mode - apply changes
        if (editableQueue.value.length !== queue.value.length) {
          console.warn("Queue length mismatch, not applying changes");
        } else {
          // Apply the new order to the player store
          const actualCurrentIndex =
            playerStore.currentSongIndexInActualPlayQueue;
          const newFullQueue = [
            ...playerStore.actualPlayQueue.slice(0, actualCurrentIndex + 1),
            ...editableQueue.value,
          ];
          playerStore.reorderQueue(newFullQueue);
        }
        isEditMode.value = false;
      } else {
        // Entering edit mode
        isEditMode.value = true;
        editableQueue.value = queue.value.map((song) => ({ ...song }));
      }
    };

    const moveToNext = (index) => {
      if (isEditMode.value) {
        // In edit mode: move within editable queue
        if (index > 0) {
          const song = editableQueue.value.splice(index, 1)[0];
          editableQueue.value.unshift(song);
        }
      } else {
        // In view mode: apply immediately to player store
        const actualQueueIndex =
          playerStore.currentSongIndexInActualPlayQueue + 1 + index;
        playerStore.moveToNext(actualQueueIndex);
      }
    };

    const onDragEnd = (event) => {
      // The editableQueue is already updated by v-model
      // We'll apply changes when exiting edit mode
    };

    const formatTime = (seconds) => {
      if (!seconds || isNaN(seconds)) return "0:00";
      const mins = Math.floor(seconds / 60);
      const secs = Math.floor(seconds % 60);
      return `${mins}:${secs.toString().padStart(2, "0")}`;
    };

    const handleBackdropClick = () => {
      // Exit edit mode if active
      if (isEditMode.value) {
        isEditMode.value = false;
        return;
      }

      // Close modal
      emit("close");
    };

    return {
      currentSong,
      queue,
      previousQueue,
      activeTab,
      isEditMode,
      editableQueue,
      playFromQueue,
      removeFromQueue,
      clearQueue,
      formatTime,
      t,
      handleBackdropClick,
      playFromHistory,
      clearHistory,
      reversedPreviousQueue,
      toggleEditMode,
      moveToNext,
      onDragEnd,
      themeStore,
    };
  },
};
</script>

<style scoped>
@reference "tailwindcss";
/* Queue Item Styles */

.queue-item:hover .quick-actions {
  @apply opacity-100;
}

.queue-list {
  @apply overflow-y-auto;
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Improved scrollbar for dark theme */
.queue-list::-webkit-scrollbar {
  @apply w-1.5;
}

.queue-list::-webkit-scrollbar-track {
  @apply bg-white/10 rounded;
}

.queue-list::-webkit-scrollbar-thumb {
  @apply bg-white/30 rounded;
}

.queue-list::-webkit-scrollbar-thumb:hover {
  @apply bg-white/50;
}

/* Drag & Drop Styles */
.draggable-list {
  @apply min-h-[50px];
}

.draggable-item {
  @apply transition-all duration-300 bg-white/5 border border-transparent;
}

.draggable-item:hover {
  @apply bg-white/10 border-white/10;
}

.drag-handle {
  @apply cursor-grab text-white/50 min-w-[20px] flex items-center justify-center;
}

.drag-handle:active {
  @apply cursor-grabbing;
}

.drag-handle:hover {
  @apply text-white/80;
}

/* Edit Mode Styles */
.edit-actions {
  @apply opacity-0 transition-opacity duration-200;
}

.queue-item:hover .edit-actions {
  @apply opacity-100;
}

.edit-actions button,
.quick-actions button {
  @apply min-w-[32px] h-8 flex items-center justify-center p-0;
}

/* Mobile optimizations */
@media (max-width: 768px) {
  .modal-dialog {
    @apply max-w-full m-0 h-screen;
  }

  .modal-content {
    @apply h-screen rounded-none max-h-screen;
  }

  /* Mobile: Adjust height to fit properly */
  .text-white.rounded-lg {
    height: 90vh !important;
  }

  /* Always show actions on mobile */
  .edit-actions,
  .quick-actions {
    @apply opacity-100;
  }

  .edit-actions button,
  .quick-actions button {
    @apply min-w-[40px] h-10;
  }

  /* Improve touch targets */
  .drag-handle {
    @apply min-w-[40px] min-h-[40px];
  }

  .queue-item {
    @apply py-3 px-2 min-h-[60px];
  }
}

/* Now Playing Section Styles */
.now-playing-section {
  @apply transition-all duration-200 hover:bg-white/10;
}

/* Touch device optimizations */
@media (hover: none) and (pointer: coarse) {
  .queue-item:hover {
    @apply bg-white/5;
  }

  .edit-actions,
  .quick-actions {
    @apply opacity-100;
  }

  .drag-handle {
    @apply text-white/70;
  }
}

/* Animation for edit mode toggle */
.draggable-item {
  animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-10px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}
</style>
