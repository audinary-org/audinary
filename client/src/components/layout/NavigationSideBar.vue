<template>
  <!-- Fixed Sidebar -->
  <aside
    :class="[
      'fixed left-0 top-0 h-full bg-white/20 border-r border-gray-700 z-30 flex flex-col shadow-lg transition-all duration-300',
      isCollapsed ? 'w-18' : 'w-64',
    ]"
  >
    <!-- Toggle Button -->
    <div class="absolute -right-3 top-[50%] z-40">
      <button
        class="bg-gray-700 hover:bg-gray-600 text-white rounded-full p-2 shadow-lg transition-colors"
        :title="isCollapsed ? 'Sidebar erweitern' : 'Sidebar einklappen'"
        @click="handleToggleSidebar"
      >
        <i
          :class="isCollapsed ? 'bi-chevron-right' : 'bi-chevron-left'"
          class="bi text-sm"
        />
      </button>
    </div>

    <!-- Logo Section / Back to Audinary -->
    <div class="flex items-center justify-center p-2 border-b border-gray-700">
      <a
        v-if="activeTab !== 'settings'"
        href="#"
        class="flex items-center"
        @click.prevent="navigateToTab('dashboard')"
      >
        <img
          v-if="!isCollapsed"
          src="/img/audinary-reverse-small-orange.png"
          alt="Audinary"
          class="h-10"
        />
        <img
          v-else
          src="/img/icon-96x96.png"
          alt="Audinary"
          class="h-12 w-12"
        />
      </a>
      <!-- Back button when in settings -->
      <button
        v-else
        @click="navigateToTab('dashboard')"
        class="flex items-center w-full px-4 py-3 text-white/80 hover:text-audinary hover:bg-white/10 rounded-lg transition-colors"
      >
        <i class="bi bi-arrow-left text-xl mr-3"></i>
        <span v-if="!isCollapsed">{{ $t("admin.back_to_app") }}</span>
      </button>
    </div>

    <!-- Main Navigation / Settings Navigation -->
    <nav
      class="flex-1 overflow-y-auto overflow-x-hidden px-2 py-2 space-y-2 scrollbar-thin scrollbar-thumb-gray-600 scrollbar-track-gray-800"
    >
      <!-- Settings Navigation -->
      <template v-if="activeTab === 'settings'">
        <!-- User Settings Section -->
        <div class="mb-4">
          <p
            v-if="!isCollapsed"
            class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-2"
          >
            {{ $t("settings.sections.user") }}
          </p>
          <a
            v-for="item in userSettingsItems"
            :key="item.id"
            href="#"
            :class="
              settingsSection === item.id
                ? 'bg-white/20 text-audinary'
                : 'text-white/80 hover:text-audinary hover:bg-white/20'
            "
            :title="isCollapsed ? item.label : ''"
            class="flex items-center px-4 py-3 rounded-lg font-medium transition-colors"
            @click="navigateToSettingsSection(item.id)"
          >
            <i :class="[item.icon, 'text-xl', isCollapsed ? '' : 'mr-3']" />
            <span v-if="!isCollapsed">{{ item.label }}</span>
          </a>
        </div>

        <!-- Admin Settings Section (only for admins) -->
        <div v-if="authStore.isAdmin" class="mb-4">
          <p
            v-if="!isCollapsed"
            class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-2"
          >
            {{ $t("settings.sections.admin") }}
          </p>
          <a
            v-for="item in adminSettingsItems"
            :key="item.id"
            href="#"
            :class="
              settingsSection === item.id
                ? 'bg-white/20 text-audinary'
                : 'text-white/80 hover:text-audinary hover:bg-white/20'
            "
            :title="isCollapsed ? item.label : ''"
            class="flex items-center px-4 py-3 rounded-lg font-medium transition-colors"
            @click="navigateToSettingsSection(item.id)"
          >
            <i :class="[item.icon, 'text-xl', isCollapsed ? '' : 'mr-3']" />
            <span v-if="!isCollapsed">{{ item.label }}</span>
          </a>
        </div>
      </template>

      <!-- Normal Navigation -->
      <template v-else>
        <!-- Dashboard -->
        <a
          href="#"
          :class="
            activeTab === 'dashboard'
              ? 'bg-white/20 text-audinary'
              : 'text-white/80 hover:text-audinary hover:bg-white/20'
          "
          :title="isCollapsed ? $t('nav.dashboard') : ''"
          class="flex items-center px-4 py-3 rounded-lg font-medium transition-colors"
          @click="navigateToTab('dashboard')"
        >
          <i
            class="bi bi-speedometer2 text-xl"
            :class="isCollapsed ? '' : 'mr-3'"
          />
          <span v-if="!isCollapsed">{{ $t("nav.dashboard") }}</span>
        </a>

        <!-- Playlists -->
        <a
          href="#"
          :class="
            activeTab === 'playlists'
              ? 'bg-white/20 text-audinary'
              : 'text-white/80 hover:text-audinary hover:bg-white/20'
          "
          :title="isCollapsed ? $t('nav.playlists') : ''"
          class="flex items-center px-4 py-3 rounded-lg font-medium transition-colors"
          @click="navigateToTab('playlists')"
        >
          <i
            class="bi bi-music-note-list text-xl"
            :class="isCollapsed ? '' : 'mr-3'"
          />
          <span v-if="!isCollapsed">{{ $t("nav.playlists") }}</span>
        </a>

        <!-- Albums -->
        <a
          href="#"
          :class="
            activeTab === 'albums'
              ? 'bg-white/20 text-audinary'
              : 'text-white/80 hover:text-audinary hover:bg-white/20'
          "
          :title="isCollapsed ? $t('nav.albums') : ''"
          class="flex items-center px-4 py-3 rounded-lg font-medium transition-colors"
          @click="navigateToTab('albums')"
        >
          <i class="bi bi-disc text-xl" :class="isCollapsed ? '' : 'mr-3'" />
          <span v-if="!isCollapsed">{{ $t("nav.albums") }}</span>
        </a>

        <!-- Artists -->
        <a
          href="#"
          :class="
            activeTab === 'artists'
              ? 'bg-white/20 text-audinary'
              : 'text-white/80 hover:text-audinary hover:bg-white/20'
          "
          :title="isCollapsed ? $t('nav.artists') : ''"
          class="flex items-center px-4 py-3 rounded-lg font-medium transition-colors"
          @click="navigateToTab('artists')"
        >
          <i class="bi bi-people text-xl" :class="isCollapsed ? '' : 'mr-3'" />
          <span v-if="!isCollapsed">{{ $t("nav.artists") }}</span>
        </a>

        <!-- Songs -->
        <a
          href="#"
          :class="
            activeTab === 'songs'
              ? 'bg-white/20 text-audinary'
              : 'text-white/80 hover:text-audinary hover:bg-white/20'
          "
          :title="isCollapsed ? $t('nav.songs') : ''"
          class="flex items-center px-4 py-3 rounded-lg font-medium transition-colors"
          @click="navigateToTab('songs')"
        >
          <i
            class="bi bi-music-note-list text-xl"
            :class="isCollapsed ? '' : 'mr-3'"
          />
          <span v-if="!isCollapsed">{{ $t("nav.songs") }}</span>
        </a>

        <!-- Search -->
        <a
          href="#"
          :class="
            activeTab === 'search'
              ? 'bg-white/20 text-audinary'
              : 'text-white/80 hover:text-audinary hover:bg-white/20'
          "
          :title="isCollapsed ? $t('nav.search') : ''"
          class="flex items-center px-4 py-3 rounded-lg font-medium transition-colors"
          @click="navigateToTab('search')"
        >
          <i class="bi bi-search text-xl" :class="isCollapsed ? '' : 'mr-3'" />
          <span v-if="!isCollapsed">{{ $t("nav.search") }}</span>
        </a>

        <!-- Shares (Admin or users with share permission) -->
        <a
          v-if="canAccessShares"
          href="#"
          :class="
            activeTab === 'shares'
              ? 'bg-white/20 text-audinary'
              : 'text-white/80 hover:text-audinary hover:bg-white/20'
          "
          :title="isCollapsed ? $t('nav.shares') : ''"
          class="flex items-center px-4 py-3 rounded-lg font-medium transition-colors"
          @click="navigateToTab('shares')"
        >
          <i class="bi bi-share text-xl" :class="isCollapsed ? '' : 'mr-3'" />
          <span v-if="!isCollapsed">{{ $t("nav.shares") }}</span>
        </a>

        <!-- Wishlist -->
        <a
          v-if="configStore.isWishlistEnabled"
          href="#"
          :class="
            activeTab === 'wishlist'
              ? 'bg-white/20 text-audinary'
              : 'text-white/80 hover:text-audinary hover:bg-white/20'
          "
          :title="isCollapsed ? $t('nav.wishlist') : ''"
          class="flex items-center px-4 py-3 rounded-lg font-medium transition-colors"
          @click="navigateToTab('wishlist')"
        >
          <i class="bi bi-heart text-xl" :class="isCollapsed ? '' : 'mr-3'" />
          <span v-if="!isCollapsed">{{ $t("nav.wishlist") }}</span>
        </a>
      </template>
    </nav>

    <!-- Bottom Section -->
    <div class="p-4 border-t border-gray-700">
      <!-- Expanded Bottom Section -->
      <div v-if="!isCollapsed" class="flex items-center justify-center gap-2">
        <!-- User Profile -->
        <div
          class="flex items-center justify-center p-2 text-white/80 rounded-lg transition-colors"
        >
          <SimpleImage
            image-type="profile"
            :image-id="
              authStore.user?.image_uuid || authStore.user?.id || 'default'
            "
            alt="User"
            class="w-8 h-8 rounded-full object-cover"
          />
        </div>

        <!-- Settings -->
        <button
          class="flex items-center justify-center p-2 text-white/80 hover:text-audinary hover:bg-gray-700 rounded-lg transition-colors"
          @click="navigateToTab('settings')"
          :title="$t('nav.settings')"
        >
          <i class="bi bi-gear text-xl" />
        </button>

        <!-- About -->
        <button
          class="flex items-center justify-center p-2 text-white/80 hover:text-audinary hover:bg-gray-700 rounded-lg transition-colors"
          @click="openAbout"
          :title="$t('nav.about')"
        >
          <i class="bi bi-info-circle text-xl" />
        </button>

        <!-- Logout -->
        <button
          class="flex items-center justify-center p-2 text-red-400 hover:text-red-300 hover:bg-gray-700 rounded-lg transition-colors"
          @click="logout"
          :title="$t('nav.logout')"
        >
          <i class="bi bi-box-arrow-right text-xl" />
        </button>
      </div>

      <!-- Collapsed Bottom Section -->
      <div v-else class="relative user-dropdown-container">
        <!-- User Avatar with Dropdown -->
        <button
          class="w-full flex items-center justify-center text-white/80 hover:text-audinary hover:bg-gray-700 rounded-lg transition-colors"
          @click="toggleUserDropdown"
        >
          <SimpleImage
            image-type="profile"
            :image-id="
              authStore.user?.image_uuid || authStore.user?.id || 'default'
            "
            alt="User"
            class="w-10 h-10 rounded-full object-cover"
          />
        </button>

        <!-- User Dropdown -->
        <div
          v-show="showUserDropdown"
          class="absolute bottom-full left-16 mb-2 bg-gray-700/90 border border-gray-600 rounded-lg shadow-xl min-w-48 z-[60] py-1"
        >
          <button
            class="w-full flex items-center px-4 py-3 text-white/80 hover:text-audinary hover:bg-gray-700 transition-colors"
            @click="
              navigateToTab('settings');
              showUserDropdown = false;
            "
          >
            <i class="bi bi-gear mr-3" /> {{ $t("nav.settings") }}
          </button>
          <button
            class="w-full flex items-center px-4 py-3 text-white/80 hover:text-audinary hover:bg-gray-700 transition-colors"
            @click="openAbout"
          >
            <i class="bi bi-info-circle mr-3" /> {{ $t("nav.about") }}
          </button>
          <div class="border-t border-gray-600 my-1" />
          <button
            class="w-full flex items-center px-4 py-3 text-red-400 hover:text-red-300 hover:bg-gray-700 transition-colors"
            @click="logout"
          >
            <i class="bi bi-box-arrow-right mr-3" /> {{ $t("nav.logout") }}
          </button>
        </div>
      </div>
    </div>
  </aside>

  <AboutModal v-if="showAbout" @close="showAbout = false" />
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { usePlaylistStore } from "@/stores/playlist";
import { useConfigStore } from "@/stores/config";
import { useRouter, useRoute } from "vue-router";
import AboutModal from "@/components/modals/AboutModal.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";
import { useSidebar } from "@/composables/useSidebar";
import { useI18n } from "vue-i18n";

const { t } = useI18n();
const authStore = useAuthStore();
const playlistStore = usePlaylistStore();
const configStore = useConfigStore();
const router = useRouter();
const route = useRoute();

// Sidebar state
const { isCollapsed, toggleSidebar } = useSidebar();

// Modal states
const showUserSettings = ref(false);
const showAbout = ref(false);
const showUserDropdown = ref(false);

// Computed
const activeTab = computed(() => {
  return route.query.tab || "dashboard";
});

const canAccessShares = computed(() => {
  return authStore.isAdmin || authStore.user?.can_create_public_share;
});

const settingsSection = computed(() => {
  return route.query.section || "profile";
});

// User settings navigation items
const userSettingsItems = computed(() => [
  { id: "profile", icon: "bi bi-person", label: t("settings.tabs.profile") },
  {
    id: "transcoding",
    icon: "bi bi-music-note-beamed",
    label: t("settings.tabs.transcoding"),
  },
  {
    id: "appearance",
    icon: "bi bi-palette",
    label: t("settings.tabs.appearance"),
  },
]);

// Admin settings navigation items
const adminSettingsItems = computed(() => {
  const items = [
    {
      id: "admin-dashboard",
      icon: "bi bi-speedometer2",
      label: t("admin.dashboard"),
    },
    {
      id: "admin-users",
      icon: "bi bi-people",
      label: t("admin.user_management"),
    },
    { id: "admin-config", icon: "bi bi-gear", label: t("admin.configuration") },
    {
      id: "admin-scan",
      icon: "bi bi-music-note-list",
      label: t("admin.music_scan"),
    },
    {
      id: "admin-playlists",
      icon: "bi bi-collection",
      label: t("admin.global_playlists"),
    },
  ];

  // Only show wishlist if enabled
  if (configStore.isWishlistEnabled) {
    items.push({
      id: "admin-wishlist",
      icon: "bi bi-heart",
      label: t("admin.wishlist.title"),
    });
  }

  items.push({
    id: "admin-backup",
    icon: "bi bi-archive",
    label: t("admin.backup_restore"),
  });

  return items;
});

// Click outside handler for user dropdown
function handleClickOutside(event) {
  const dropdownElement = event.target.closest(".user-dropdown-container");
  if (!dropdownElement && showUserDropdown.value) {
    showUserDropdown.value = false;
  }
}

onMounted(() => {
  // Load playlists for navigation only if authenticated and initialized
  if (
    authStore.isAuthenticated &&
    authStore.isInitialized &&
    !authStore.isLoading
  ) {
    playlistStore.loadPlaylists();
  } else {
    // Watch for authentication changes
    const unwatch = authStore.$subscribe(() => {
      if (
        authStore.isAuthenticated &&
        authStore.isInitialized &&
        !authStore.isLoading
      ) {
        playlistStore.loadPlaylists();
        unwatch(); // Stop watching once playlists are loaded
      }
    });
  }

  // Add click outside listener
  document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
  // Remove click outside listener
  document.removeEventListener("click", handleClickOutside);
});

// Methods
function navigateToTab(tab) {
  router.push({ path: "/", query: { tab } });
}

function navigateToSettingsSection(section) {
  router.push({ path: "/", query: { tab: "settings", section } });
}

async function logout() {
  try {
    await authStore.logout();
    setTimeout(() => {
      router.push("/auth");
    }, 100);
  } catch (error) {
    console.error("Error during logout:", error);
    router.push("/auth");
  }
}

function handleToggleSidebar() {
  toggleSidebar();
  showUserDropdown.value = false;
}

function toggleUserDropdown() {
  showUserDropdown.value = !showUserDropdown.value;
}

function openUserSettings() {
  showUserSettings.value = true;
  showUserDropdown.value = false;
}

function openAbout() {
  showAbout.value = true;
  showUserDropdown.value = false;
}
</script>

<style scoped>
/* Sidebar styling */
aside {
  backdrop-filter: blur(8px);
}

/* Scrollbar styling for playlists */
.space-y-1 {
  max-height: 200px;
  overflow-y: auto;
}

.space-y-1::-webkit-scrollbar {
  width: 4px;
}

.space-y-1::-webkit-scrollbar-track {
  background: transparent;
}

.space-y-1::-webkit-scrollbar-thumb {
  background: rgba(107, 114, 128, 0.5);
  border-radius: 2px;
}

.space-y-1::-webkit-scrollbar-thumb:hover {
  background: rgba(107, 114, 128, 0.7);
}

/* Main navigation scrollbar */
nav::-webkit-scrollbar {
  width: 4px;
}

nav::-webkit-scrollbar-track {
  background: transparent;
}

nav::-webkit-scrollbar-thumb {
  background: rgba(107, 114, 128, 0.5);
  border-radius: 2px;
}

nav::-webkit-scrollbar-thumb:hover {
  background: rgba(107, 114, 128, 0.7);
}
</style>
