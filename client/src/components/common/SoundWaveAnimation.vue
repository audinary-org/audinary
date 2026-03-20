<template>
  <div class="min-w-[30px] h-5 flex items-center justify-center">
    <!-- Wave animation for currently playing track -->
    <div v-if="isPlaying" class="sound-wave" ref="soundWaveRef">
      <div class="bar" v-for="i in 10" :key="i"></div>
    </div>
    <!-- Track number when not playing -->
    <span v-else class="text-sm">{{ trackNumber }}</span>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
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
    const soundWaveRef = ref(null);
    let animationTimeout = null;

    const isPlaying = computed(() => {
      return (
        playerStore?.currentSong &&
        playerStore.currentSong.song_id ===
          (props.song.song_id || props.song.id)
      );
    });

    const initializeAnimation = () => {
      if (animationTimeout) {
        clearTimeout(animationTimeout);
      }

      animationTimeout = setTimeout(() => {
        // scope to this component to avoid touching other instances
        const container = soundWaveRef.value;
        if (!container) return;
        const bars = container.querySelectorAll(".bar");
        bars.forEach((bar) => {
          // Random animation duration between 0.2s and 0.7s
          const duration = Math.random() * (0.7 - 0.2) + 0.2;
          bar.style.animationDuration = `${duration}s`;
        });
      }, 100);
    };

    watch(
      () => playerStore?.currentSong,
      () => {
        setTimeout(() => {
          initializeAnimation();
        }, 200);
      },
    );

    watch(
      () => playerStore?.isPlaying,
      () => {
        setTimeout(() => {
          initializeAnimation();
        }, 200);
      },
    );

    onMounted(() => {
      initializeAnimation();
    });

    onUnmounted(() => {
      if (animationTimeout) {
        clearTimeout(animationTimeout);
      }
    });

    return {
      isPlaying,
      soundWaveRef,
      initializeAnimation,
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
