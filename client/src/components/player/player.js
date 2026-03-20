import { createApp } from "vue";
import { createPinia } from "pinia";
import PlayerControls from "./PlayerControls.vue";
import { usePlayerStore } from "@/stores/player";
import { useApiStore } from "@/stores/api";

// Create Pinia instance
const pinia = createPinia();

// Initialize Player Component
function initializePlayerComponent() {
  const playerElement = document.getElementById("vue-player-controls");

  if (playerElement) {
    const app = createApp({
      components: {
        PlayerControls,
      },
      setup() {
        const playerStore = usePlayerStore();
        const apiStore = useApiStore();

        // Initialize stores
        apiStore.initialize();
        playerStore.initialize();

        // Bridge legacy player.js with Vue store
        bridgeLegacyPlayer(playerStore);

        return {
          playerStore,
        };
      },
      template: `
        <PlayerControls @toggle-queue="handleToggleQueue" />
      `,
      methods: {
        handleToggleQueue() {
          // Toggle queue visibility
          const queueElement = document.getElementById("queue-sidebar");
          if (queueElement) {
            queueElement.classList.toggle("show");
          }
        },
      },
    });

    app.use(pinia);
    app.mount("#vue-player-controls");
  }
}

// Bridge legacy player.js functionality with Vue store
function bridgeLegacyPlayer(playerStore) {
  // Export player functions to global scope for legacy compatibility
  window.vuePlayer = {
    playSong: playerStore.playSong,
    addToQueue: playerStore.addToQueue,
    playAlbum: playerStore.playAlbum,
    playPlaylist: playerStore.playPlaylist,
    togglePlayPause: playerStore.togglePlayPause,
    stop: playerStore.stop,
    nextSong: playerStore.nextSong,
    previousSong: playerStore.previousSong,
    setVolume: playerStore.setVolume,
    seek: playerStore.seek,

    // Getters
    get currentSong() {
      return playerStore.currentSong;
    },
    get isPlaying() {
      return playerStore.isPlaying;
    },
    get queue() {
      return playerStore.actualPlayQueue;
    },
    get volume() {
      return playerStore.volume;
    },
  };

  // Listen for legacy events and update store
  document.addEventListener("legacy-player-update", (event) => {
    const { type, data } = event.detail;

    switch (type) {
      case "song-changed":
        playerStore.currentSong = data;
        break;
      case "playing-state-changed":
        playerStore.isPlaying = data;
        break;
      case "volume-changed":
        playerStore.volume = data;
        break;
      case "queue-updated":
        playerStore.actualPlayQueue = data;
        break;
    }
  });

  // Sync Howler.js instances
  if (window.currentHowl) {
    playerStore.currentHowl = window.currentHowl;
  }

  // Watch for changes in store and update legacy code
  playerStore.$subscribe((mutation, state) => {
    // Dispatch events for legacy code to listen to
    document.dispatchEvent(
      new CustomEvent("vue-player-update", {
        detail: {
          type: mutation.type,
          payload: mutation.payload,
          state: state,
        },
      }),
    );
  });
}

// Initialize when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initializePlayerComponent);
} else {
  initializePlayerComponent();
}

// Export for manual initialization
export { initializePlayerComponent };
