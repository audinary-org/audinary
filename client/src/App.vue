<template>
  <div id="app" class="h-screen flex flex-col overflow-hidden">
    <!-- Main Content Area -->
    <div class="flex-1 min-h-0 overflow-hidden">
      <!-- Always show router view - let the router handle authentication -->
      <router-view />
    </div>

    <!-- Player Component - only show if authenticated, positioned as footer -->
    <PlayerComponent v-if="authStore.isAuthenticated" />

    <!-- Queue Modal - only show if authenticated -->
    <PlayerQueueModal
      v-if="authStore.isAuthenticated && showQueue"
      @close="showQueue = false"
    />

    <!-- Global Alert System -->
    <GlobalAlert />
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "@/stores/auth";
import { useApiStore } from "@/stores/api";
import { usePlayerStore } from "@/stores/player";
import { useConfigStore } from "@/stores/config";
import { useScanStatus } from "@/composables/useScanStatus";
import { useWishlistNotification } from "@/composables/useWishlistNotification";

// Components
import PlayerComponent from "@/components/player/PlayerComponent.vue";
import PlayerQueueModal from "@/components/modals/PlayerQueueModal.vue";
import GlobalAlert from "@/components/common/GlobalAlert.vue";

export default {
  name: "App",
  components: {
    PlayerComponent,
    PlayerQueueModal,
    GlobalAlert,
  },
  setup() {
    const { t } = useI18n();
    const authStore = useAuthStore();
    const apiStore = useApiStore();
    const playerStore = usePlayerStore();
    const configStore = useConfigStore();
    const scanStatus = useScanStatus();
    const wishlistNotification = useWishlistNotification();

    const showQueue = ref(false);

    onMounted(async () => {
      // Listen for auth errors
      const handleAuthError = (event) => {
        authStore.logout();
      };

      // Listen for session expiry events
      const handleSessionExpired = (event) => {
        authStore.logout();
        // Optional: Show a notification
        if (event.detail?.message) {
        }
      };

      window.addEventListener("auth-error", handleAuthError);
      window.addEventListener("session-expired", handleSessionExpired);

      // JWT authentication errors are now handled by the makeRequest function in the API store

      try {
        // Load public config first (before authentication)
        await configStore.loadConfig();

        // Check authentication status on app start (only if not already initialized)
        if (!authStore.isInitialized) {
          const isAuthenticated = await authStore.checkAuth();
        }

        // Initialize other stores only if authenticated (and wait for it to be fully ready)
        if (
          authStore.isAuthenticated &&
          authStore.isInitialized &&
          !authStore.isLoading
        ) {
          try {
            apiStore.initialize();
            playerStore.initialize();

            // Initialize global scan status monitoring for all authenticated users
            scanStatus.initialize();

            // Initialize wishlist notification for admins
            wishlistNotification.initialize();
          } catch (error) {
            // If store initialization fails, it might be due to invalid session
            if (error.response?.status === 401) {
              authStore.logout();
            }
          }
        } else {
        }
      } catch (error) {}

      // Cleanup listeners
      return () => {
        window.removeEventListener("auth-error", handleAuthError);
        window.removeEventListener("session-expired", handleSessionExpired);
      };
    });

    onUnmounted(() => {
      // Cleanup scan status monitoring
      scanStatus.cleanup();
      // Cleanup wishlist notification
      wishlistNotification.cleanup();
    });

    return {
      authStore,
      showQueue,
      t,
    };
  },
};
</script>

<style>
/* Global styles */
#app {
  font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  /* Mobile optimizations */
  position: relative;
  width: 100%;
  height: 100dvh;
  /* Dynamic viewport height for mobile */
  min-height: 100svh;
  /* Small viewport height fallback */
  overflow: hidden;
}

/* Loading spinner */
.spinner-border {
  width: 3rem;
  height: 3rem;
}

/* Ensure full height */
.vh-100 {
  min-height: 100dvh;
  /* Use dynamic viewport height */
  height: 100dvh;
}

/* Mobile-specific optimizations */
@media screen and (max-width: 768px) {
  #app {
    /* Prevent bounce scrolling on iOS */
    position: fixed;
    overflow: hidden;
    /* Disable text selection on mobile */
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    /* Prevent pull-to-refresh */
    overscroll-behavior-y: contain;
    /* Remove safe area padding on mobile */
    padding: 0;
  }
}

/* Apply safe area insets only for landscape or when needed */
@media screen and (orientation: landscape) {
  #app {
    padding-left: env(safe-area-inset-left);
    padding-right: env(safe-area-inset-right);
  }
}

/* Only apply top safe area for devices with notch in specific cases */
@media screen and (min-height: 812px) and (orientation: portrait) {
  .navbar {
    padding-top: env(safe-area-inset-top);
  }
}

/* Bottom safe area for player */
.audio-player {
  padding-bottom: env(safe-area-inset-bottom);
}

/* Landscape orientation on mobile */
@media screen and (orientation: landscape) and (max-height: 500px) {
  .vh-100 {
    height: 100vh;
    /* Use standard viewport height in landscape on small screens */
  }
}

/* High DPI displays */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
  #app {
    /* Optimize rendering on high DPI displays */
    backface-visibility: hidden;
    transform: translateZ(0);
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
</style>
