<template>
  <div class="player-controls">
    <!-- Current Song Info -->
    <div class="grid grid-cols-1 md:grid-cols-12 items-center gap-4">
      <div class="md:col-span-3 mb-2 md:mb-0">
        <div
          v-if="playerStore.currentSong"
          class="current-song flex items-center"
        >
          <div class="song-cover mr-3">
            <img
              :src="
                playerStore.currentSong.cover_url || '/images/default-album.png'
              "
              :alt="playerStore.currentSong.album"
              class="rounded w-12 h-12 object-cover"
            />
          </div>
          <div class="song-info flex-1 min-w-0">
            <h6 class="text-sm font-medium truncate text-white mb-0">
              {{ playerStore.currentSong.title }}
            </h6>
            <p class="text-xs text-gray-400 truncate mb-0">
              {{ playerStore.currentSong.artist }} -
              {{ playerStore.currentSong.album }}
            </p>
          </div>
        </div>
        <div v-else class="current-song flex items-center">
          <div class="song-cover mr-3">
            <div
              class="placeholder-cover rounded flex items-center justify-center"
            >
              <i class="bi bi-music-note text-gray-500"></i>
            </div>
          </div>
          <div class="song-info">
            <h6 class="text-sm font-medium text-gray-500 mb-0">
              {{ $t("player.noSong") }}
            </h6>
            <p class="text-xs text-gray-500 mb-0">
              {{ $t("player.selectSong") }}
            </p>
          </div>
        </div>
      </div>

      <!-- Main Controls -->
      <div class="md:col-span-6 mb-2 md:mb-0">
        <div class="main-controls text-center">
          <!-- Control Buttons -->
          <div
            class="control-buttons mb-2 flex items-center justify-center gap-2"
          >
            <button
              class="w-10 h-10 rounded-full border border-white/30 text-white hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center transition-colors"
              :disabled="!playerStore.canPlayPrevious"
              @click="playerStore.prevSong()"
              :title="$t('player.previous')"
            >
              <i class="bi bi-skip-backward-fill"></i>
            </button>

            <button
              class="w-12 h-12 rounded-full bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center transition-colors"
              :disabled="!playerStore.currentSong"
              @click="playerStore.togglePlayPause()"
              :title="
                playerStore.isPlaying ? $t('player.pause') : $t('player.play')
              "
            >
              <i
                v-if="playerStore.isLoading"
                class="bi bi-arrow-clockwise spin"
              ></i>
              <i v-else-if="playerStore.isPlaying" class="bi bi-pause-fill"></i>
              <i v-else class="bi bi-play-fill"></i>
            </button>

            <button
              class="w-10 h-10 rounded-full border border-white/30 text-white hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center transition-colors"
              :disabled="!playerStore.canPlayNext"
              @click="playerStore.nextSong()"
              :title="$t('player.next')"
            >
              <i class="bi bi-skip-forward-fill"></i>
            </button>

            <button
              class="w-10 h-10 rounded-full border border-white/30 text-white hover:bg-white/20 flex items-center justify-center transition-colors"
              :class="
                playerStore.isShuffleEnabled
                  ? 'bg-white/20 border-white/40'
                  : ''
              "
              @click="playerStore.toggleShuffle()"
              :title="$t('player.shuffle')"
            >
              <i class="bi bi-shuffle"></i>
            </button>

            <button
              class="w-10 h-10 rounded-full border border-white/30 text-white hover:bg-white/20 flex items-center justify-center transition-colors"
              :class="
                playerStore.repeatMode !== 'none'
                  ? 'bg-white/20 border-white/40'
                  : ''
              "
              @click="playerStore.toggleRepeat()"
              :title="getRepeatTitle()"
            >
              <i
                v-if="playerStore.repeatMode === 'one'"
                class="bi bi-repeat-1"
              ></i>
              <i v-else class="bi bi-repeat"></i>
            </button>

            <!-- Local Mode Button -->
            <button
              v-if="playerStore.localModeEnabled"
              class="w-10 h-10 rounded-full border border-white/30 text-white hover:bg-white/20 flex items-center justify-center transition-colors"
              :class="
                playerStore.isLocalMode ? 'bg-white/20 border-white/40' : ''
              "
              @click="playerStore.toggleLocalMode()"
              :title="
                playerStore.isLocalMode
                  ? $t('player.modes.browserMode')
                  : $t('player.modes.localMode')
              "
            >
              <i
                :class="
                  playerStore.isLocalMode
                    ? 'bi bi-speaker-fill'
                    : 'bi bi-speaker'
                "
              ></i>
            </button>
          </div>

          <!-- Progress Bar -->
          <div class="progress-container">
            <div class="flex items-center">
              <span class="time-display mr-2">{{
                playerStore.formattedCurrentTime
              }}</span>

              <div
                class="progress-bar-container flex-1 mx-2"
                @click="handleProgressClick"
              >
                <div
                  class="progress-bar-fill"
                  :style="{ width: playerStore.currentSongProgress + '%' }"
                ></div>
              </div>

              <span class="time-display ml-2">{{
                playerStore.formattedDuration
              }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Volume & Queue Controls -->
      <div class="md:col-span-3">
        <div class="secondary-controls flex items-center justify-end">
          <!-- Volume Control -->
          <div class="volume-control flex items-center mr-3">
            <button
              class="w-8 h-8 rounded-full border border-white/30 text-white hover:bg-white/20 flex items-center justify-center transition-colors mr-2"
              @click="playerStore.toggleMute()"
              :title="
                playerStore.isMuted
                  ? $t('player.volume.unmute')
                  : $t('player.volume.mute')
              "
            >
              <i v-if="playerStore.isMuted" class="bi bi-volume-mute"></i>
              <i
                v-else-if="playerStore.volume > 0.5"
                class="bi bi-volume-up"
              ></i>
              <i
                v-else-if="playerStore.volume > 0"
                class="bi bi-volume-down"
              ></i>
              <i v-else class="bi bi-volume-off"></i>
            </button>

            <input
              type="range"
              class="w-20 accent-blue-600"
              min="0"
              max="1"
              step="0.01"
              :value="playerStore.volume"
              @input="handleVolumeChange"
            />
          </div>

          <!-- Queue Button -->
          <button
            class="w-8 h-8 rounded-full border border-white/30 text-white hover:bg-white/20 flex items-center justify-center transition-colors mr-2 relative"
            :class="showQueue ? 'bg-white/20 border-white/40' : ''"
            @click="toggleQueue"
            :title="`${$t('player.queue.title')} (${playerStore.upcomingQueueLength})`"
          >
            <i class="bi bi-list-ul"></i>
            <span
              v-if="playerStore.upcomingQueueLength > 0"
              class="absolute -top-1 -right-1 bg-blue-600 text-white text-xs rounded-full px-1.5 py-0.5 min-w-[1.25rem] text-center"
            >
              {{ playerStore.upcomingQueueLength }}
            </span>
          </button>

          <!-- Fullscreen Button -->
          <button
            class="w-8 h-8 rounded-full border border-white/30 text-white hover:bg-white/20 flex items-center justify-center transition-colors"
            @click="toggleFullscreen"
            :title="$t('player.fullscreenPlayer')"
          >
            <i class="bi bi-arrows-fullscreen"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Queue Modal -->
    <div v-if="showQueue" class="queue-modal">
      <div class="queue-overlay" @click="showQueue = false"></div>
      <div class="queue-content">
        <div class="queue-header flex justify-between items-center mb-3">
          <h5 class="text-lg font-medium text-white mb-0">
            <i class="bi bi-list-ul mr-2"></i>
            {{ $t("player.queue.title") }} ({{
              playerStore.upcomingQueueLength
            }})
          </h5>
          <button
            class="w-8 h-8 rounded-full border border-white/30 text-white hover:bg-white/20 flex items-center justify-center transition-colors"
            @click="showQueue = false"
          >
            <i class="bi bi-x"></i>
          </button>
        </div>

        <div class="queue-list">
          <div
            v-if="playerStore.upcomingQueueLength === 0"
            class="text-center text-gray-500 py-4"
          >
            <i class="bi bi-music-note text-6xl mb-3"></i>
            <p>{{ $t("player.queue.empty") }}</p>
          </div>

          <div
            v-for="(song, index) in playerStore.upcomingQueue"
            :key="song.song_id"
            class="queue-item flex items-center mb-2 p-3 rounded-md cursor-pointer hover:bg-white/10 transition-colors"
            @click="playSongFromQueue(song, index)"
          >
            <div class="queue-index mr-3 min-w-[30px] text-center">
              <span class="text-gray-400">{{ index + 1 }}</span>
            </div>

            <div class="song-cover mr-3">
              <img
                :src="song.cover_url || '/images/default-album.png'"
                :alt="song.album"
                class="rounded w-10 h-10 object-cover"
              />
            </div>

            <div class="song-info flex-1 min-w-0">
              <h6 class="text-sm font-medium text-white truncate mb-0">
                {{ song.title }}
              </h6>
              <p class="text-xs text-gray-400 truncate mb-0">
                {{ song.artist }} - {{ song.album }}
              </p>
            </div>

            <div class="song-actions">
              <button
                class="text-red-400 hover:text-red-300 hover:bg-red-400/20 p-2 rounded transition-colors"
                @click.stop="removeFromQueue(index)"
                :title="$t('player.queue.remove')"
              >
                <i class="bi bi-trash text-sm"></i>
              </button>
            </div>
          </div>
        </div>

        <div
          v-if="playerStore.upcomingQueueLength > 0"
          class="queue-actions mt-3"
        >
          <button
            class="px-4 py-2 border border-red-400 text-red-400 hover:bg-red-400/20 rounded transition-colors"
            @click="clearQueue"
          >
            <i class="bi bi-trash mr-2"></i>
            {{ $t("player.queue.clear") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { usePlayerStore } from "@/stores/player";
import { useI18n } from "vue-i18n";

// Stores
const playerStore = usePlayerStore();
const { t } = useI18n();

// State
const showQueue = ref(false);

// Methods
function handleProgressClick(event) {
  const progressBar = event.currentTarget;
  const rect = progressBar.getBoundingClientRect();
  const percent = ((event.clientX - rect.left) / rect.width) * 100;
  playerStore.seekToPercent(percent);
}

function handleVolumeChange(event) {
  const volume = parseFloat(event.target.value);
  playerStore.setVolume(volume);
}

function toggleQueue() {
  showQueue.value = !showQueue.value;
}

function toggleFullscreen() {
  // Emit event for parent component to handle
  // or implement fullscreen logic here
}

function playSongFromQueue(song, index) {
  // index refers to the index in playerStore.upcomingQueue
  // Use the store's action to play the song from its current position in upcomingQueue
  playerStore.playFromQueue(index);
  showQueue.value = false; // Close the queue modal
}

function removeFromQueue(index) {
  // index is the index in playerStore.upcomingQueue
  // Calculate the actual index in actualPlayQueue for the store action
  const actualIndexInActualPlayQueue =
    playerStore.currentSongIndexInActualPlayQueue + 1 + index;
  playerStore.removeFromQueue(actualIndexInActualPlayQueue);
}

function clearQueue() {
  playerStore.clearQueue();
  showQueue.value = false;
}

function getRepeatTitle() {
  switch (playerStore.repeatMode) {
    case "none":
      return t("player.repeatModes.none");
    case "one":
      return t("player.repeatModes.one");
    case "all":
      return t("player.repeatModes.all");
    default:
      return t("player.repeat");
  }
}

// Emit events for parent components
const emit = defineEmits(["toggle-queue", "toggle-fullscreen"]);

// Watch for queue toggle
function emitToggleQueue() {
  emit("toggle-queue", showQueue.value);
}
</script>

<style scoped>
@reference "tailwindcss";
.player-controls {
  background: rgba(0, 0, 0, 0.8);
  backdrop-filter: blur(10px);
  border-radius: 8px;
  padding: 1rem;
  color: white;
}

.placeholder-cover {
  @apply w-12 h-12 bg-white/10;
}

/* Progress Bar */
.progress-bar-container {
  @apply h-1.5 cursor-pointer bg-white/20 rounded-full overflow-hidden;
}

.progress-bar-fill {
  @apply h-full bg-blue-600 transition-all duration-100 ease-linear;
}

.time-display {
  @apply text-sm font-mono min-w-[40px] text-center text-white;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* Queue Modal */
.queue-modal {
  @apply fixed inset-0 z-50 flex items-center justify-center;
}

.queue-overlay {
  @apply absolute inset-0 bg-black/50;
}

.queue-content {
  @apply relative bg-gray-800 rounded-lg p-6 max-w-2xl max-h-[80vh] w-[90%] text-white overflow-hidden flex flex-col;
}

.queue-list {
  @apply flex-1 overflow-y-auto -mx-2 px-2;
}

/* Responsive */
@media (max-width: 768px) {
  .player-controls {
    @apply p-3;
  }

  .current-song .song-info h6 {
    @apply text-sm;
  }

  .control-buttons button {
    @apply w-9 h-9 mx-1;
  }

  .control-buttons button:nth-child(2) {
    @apply w-11 h-11;
  }

  .volume-control {
    @apply hidden;
  }

  .queue-content {
    @apply w-[95%] max-h-[90vh];
  }
}

@media (max-width: 576px) {
  .secondary-controls {
    @apply justify-center mt-2;
  }

  .time-display {
    @apply text-xs min-w-[35px];
  }
}
</style>
