<template>
  <!-- Fullscreen Player Overlay -->
  <div
    v-if="isVisible"
    class="fixed inset-0 z-[9999] bg-black text-white flex flex-col transition-all duration-500 ease-out"
    :class="{ 'animate-in fade-in duration-500': isVisible }"
    @mousemove="handleMouseMove"
  >
    <!-- Main Content Area - Full Screen -->
    <div class="flex-1 relative overflow-hidden">
      <!-- Audio Visualization - Full Width & Height -->
      <div
        class="w-full h-full bg-gradient-to-br from-purple-900/20 via-blue-900/20 to-pink-900/20"
      >
        <AudioVisualization
          :audioAnalyser="audioAnalyser"
          :isPlaying="isPlaying"
          :showControls="false"
          :logoUrl="logoUrl"
        />
      </div>
    </div>

    <!-- Embed Normal Player Bar with Auto-hide -->
    <div
      :class="[
        'fixed bottom-0 left-0 w-full transition-all duration-500 ease-in-out z-50',
        showPlayerBar
          ? 'translate-y-0 opacity-100'
          : 'translate-y-full opacity-0',
      ]"
      @mouseenter="showPlayerBar = true"
      @mouseleave="resetHideTimer"
    >
      <!-- Use the existing PlayerComponent but in fullscreen mode -->
      <div class="relative">
        <slot name="playerbar"></slot>
      </div>
    </div>

    <!-- Song Change Toast -->
    <div
      v-if="showSongToast"
      class="fixed top-8 left-1/2 -translate-x-1/2 bg-black/80 backdrop-blur-xl rounded-2xl p-4 border border-white/20 z-[60] max-w-md w-full mx-4 animate-in slide-in-from-top duration-300"
    >
      <div class="flex items-center gap-3">
        <div
          class="w-12 h-12 rounded-xl bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center flex-shrink-0"
        >
          <i
            :class="
              nextSongInfo?.isNewSong
                ? 'bi bi-music-note text-white text-lg'
                : 'bi bi-skip-forward-fill text-white text-lg'
            "
          ></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-white/90 text-sm font-medium mb-1">
            {{
              nextSongInfo?.isNewSong
                ? $t("player.nowPlaying")
                : $t("player.upNext")
            }}
          </div>
          <div class="text-white font-semibold text-sm truncate">
            {{ nextSongInfo?.title }}
          </div>
          <div class="text-white/70 text-xs truncate">
            {{ nextSongInfo?.artist }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import AudioVisualization from "./AudioVisualization.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";

const props = defineProps({
  isVisible: {
    type: Boolean,
    default: false,
  },
  currentSong: {
    type: Object,
    default: null,
  },
  isPlaying: {
    type: Boolean,
    default: false,
  },
  currentTime: {
    type: Number,
    default: 0,
  },
  duration: {
    type: Number,
    default: 0,
  },
  volume: {
    type: Number,
    default: 0.5,
  },
  isMuted: {
    type: Boolean,
    default: false,
  },
  isShuffled: {
    type: Boolean,
    default: false,
  },
  repeatMode: {
    type: String,
    default: "none",
  },
  hasPrevious: {
    type: Boolean,
    default: false,
  },
  hasNext: {
    type: Boolean,
    default: false,
  },
  upcomingQueue: {
    type: Array,
    default: () => [],
  },
  audioAnalyser: {
    type: Object,
    default: null,
  },
  logoUrl: {
    type: String,
    default: "/img/icon.png",
  },
});

const emit = defineEmits([
  "close",
  "togglePlayPause",
  "previousSong",
  "nextSong",
  "toggleShuffle",
  "toggleRepeat",
  "toggleMute",
  "updateVolume",
  "seekTo",
  "playFromQueue",
  "removeFromQueue",
]);

const showPlayerBar = ref(true);
const showSongToast = ref(false);
const hideTimer = ref(null);
const lastSongId = ref(null);
const nextSongInfo = ref(null);

const upcomingQueueLength = computed(() => props.upcomingQueue.length);

const progressPercentage = computed(() => {
  if (!props.duration) return 0;
  return (props.currentTime / props.duration) * 100;
});

const playPauseIcon = computed(() => {
  return props.isPlaying ? "bi bi-pause-fill" : "bi bi-play-fill";
});

const volumeIcon = computed(() => {
  if (props.isMuted || props.volume === 0) {
    return "bi bi-volume-mute-fill";
  } else if (props.volume < 0.5) {
    return "bi bi-volume-down-fill";
  } else {
    return "bi bi-volume-up-fill";
  }
});

const repeatIcon = computed(() => {
  switch (props.repeatMode) {
    case "one":
      return "bi bi-repeat-1";
    case "all":
      return "bi bi-repeat";
    default:
      return "bi bi-repeat";
  }
});

function exitFullscreen() {
  emit("close");
}

function seekTo(event) {
  const progressBar = event.currentTarget;
  const rect = progressBar.getBoundingClientRect();
  const clickX = event.clientX - rect.left;
  const percentage = clickX / rect.width;
  const seekTime = percentage * props.duration;
  emit("seekTo", seekTime);
}

function formatTime(seconds) {
  if (!seconds || isNaN(seconds)) return "0:00";

  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = Math.floor(seconds % 60);
  return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
}

// Auto-hide player bar functionality
function startHideTimer() {
  clearTimeout(hideTimer.value);
  hideTimer.value = setTimeout(() => {
    // Don't hide if queue modal is open
    const queueModalOpen =
      document.querySelector(".fixed.inset-0.z-\\[10000\\]") !== null;
    if (!queueModalOpen) {
      showPlayerBar.value = false;
    }
  }, 3000); // Hide after 3 seconds
}

function resetHideTimer() {
  showPlayerBar.value = true;
  startHideTimer();
}

function handleMouseMove() {
  showPlayerBar.value = true;
  startHideTimer();
}

// Song change toast functionality
function showNextSongToast() {
  if (props.upcomingQueue.length > 0) {
    nextSongInfo.value = props.upcomingQueue[0];
    showSongToast.value = true;

    // Hide toast after 4 seconds
    setTimeout(() => {
      showSongToast.value = false;
    }, 4000);
  }
}

function checkForSongChange() {
  // Track song changes and show toast when song actually changes
  if (props.currentSong?.id && props.currentSong.id !== lastSongId.value) {
    lastSongId.value = props.currentSong.id;

    // Show toast for new song (but not on initial load)
    if (lastSongId.value && props.currentSong) {
      showNewSongToast();
    }
  }

  // Also detect when song is about to end (last 5 seconds) and show next song toast
  const timeRemaining = props.duration - props.currentTime;

  if (
    timeRemaining <= 5 &&
    timeRemaining > 4.5 &&
    !showSongToast.value &&
    props.upcomingQueue.length > 0
  ) {
    showNextSongToast();
  }
}

function showNewSongToast() {
  if (props.currentSong) {
    nextSongInfo.value = {
      title: props.currentSong.title,
      artist: props.currentSong.artist,
      isNewSong: true,
    };
    showSongToast.value = true;

    // Hide toast after 3 seconds for current song
    setTimeout(() => {
      showSongToast.value = false;
    }, 3000);
  }
}

// Watchers
import { watch, onMounted, onUnmounted } from "vue";

// Watch for current time changes to trigger toast
watch(
  () => props.currentTime,
  () => {
    if (props.isPlaying) {
      checkForSongChange();
    }
  },
);

// Watch for song changes directly
watch(
  () => props.currentSong?.id,
  (newSongId) => {
    if (newSongId && newSongId !== lastSongId.value) {
      // Only show toast if this is not the initial load
      if (lastSongId.value) {
        showNewSongToast();
      }
      lastSongId.value = newSongId;
    }
  },
);

// Start hide timer when component mounts
onMounted(() => {
  startHideTimer();
});

// Cleanup timer on unmount
onUnmounted(() => {
  if (hideTimer.value) {
    clearTimeout(hideTimer.value);
  }
});
</script>

<style scoped>
.slider::-webkit-slider-thumb {
  appearance: none;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: linear-gradient(45deg, #3b82f6, #8b5cf6);
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.slider::-moz-range-thumb {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: linear-gradient(45deg, #3b82f6, #8b5cf6);
  cursor: pointer;
  border: none;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.animate-in {
  animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

/* Custom scrollbar for queue */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}
</style>
