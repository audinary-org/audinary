<template>
  <div class="min-w-[30px] h-5 flex items-center justify-center">
    <!-- Wave animation for currently playing track -->
    <div v-if="isPlaying" class="sound-wave">
      <div class="bar" v-for="i in 10" :key="i"></div>
    </div>
    <!-- Track number when not playing -->
    <span v-else class="text-sm">{{ trackNumber }}</span>
  </div>
</template>

<script>
import { computed } from "vue";
import { usePlayerStore } from "@/stores/player";

export default {
  name: "SoundWaveAnimation",
  props: {
    song: {
      type: Object,
      required: true,
    },
    trackNumber: {
      type: [String, Number],
      required: true,
    },
  },
  setup(props) {
    const playerStore = usePlayerStore();

    // Single computed - no watchers, no timeouts, no DOM queries
    const isPlaying = computed(() => {
      return (
        playerStore?.currentSong &&
        playerStore.currentSong.song_id ===
          (props.song.song_id || props.song.id)
      );
    });

    return {
      isPlaying,
    };
  },
};
</script>

<style scoped>
.sound-wave {
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1.5px;
}

.sound-wave .bar {
  animation-name: wave-lg;
  animation-iteration-count: infinite;
  animation-timing-function: ease-in-out;
  animation-direction: alternate;
  background: white;
  margin: 0 0.5px;
  height: 5px;
  width: 1px;
  border-radius: 0.5px;
}

/* Stagger animation durations via CSS instead of JS */
.sound-wave .bar:nth-child(1) {
  animation-duration: 0.45s;
}

.sound-wave .bar:nth-child(2) {
  animation-duration: 0.32s;
}

.sound-wave .bar:nth-child(3) {
  animation-duration: 0.55s;
}

.sound-wave .bar:nth-child(4) {
  animation-duration: 0.38s;
}

.sound-wave .bar:nth-child(5) {
  animation-duration: 0.62s;
}

.sound-wave .bar:nth-child(6) {
  animation-duration: 0.28s;
}

.sound-wave .bar:nth-child(7) {
  animation-duration: 0.5s;
}

.sound-wave .bar:nth-child(8) {
  animation-duration: 0.35s;
}

.sound-wave .bar:nth-child(9) {
  animation-duration: 0.58s;
}

.sound-wave .bar:nth-child(10) {
  animation-duration: 0.42s;
}

.sound-wave .bar:nth-child(-n + 7),
.sound-wave .bar:nth-last-child(-n + 7) {
  animation-name: wave-md;
}

.sound-wave .bar:nth-child(-n + 3),
.sound-wave .bar:nth-last-child(-n + 3) {
  animation-name: wave-sm;
}

@keyframes wave-sm {
  0% {
    opacity: 0.35;
    height: 4px;
  }

  100% {
    opacity: 1;
    height: 10px;
  }
}

@keyframes wave-md {
  0% {
    opacity: 0.35;
    height: 6px;
  }

  100% {
    opacity: 1;
    height: 16px;
  }
}

@keyframes wave-lg {
  0% {
    opacity: 0.35;
    height: 6px;
  }

  100% {
    opacity: 1;
    height: 20px;
  }
}
</style>
