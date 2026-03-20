<template>
  <div
    class="h-full bg-gray-800 text-white flex overflow-hidden"
    :class="themeStore.backgroundGradient"
  >
    <!-- Navigation Sidebar -->
    <NavigationSideBar />

    <!-- Main Content -->
    <main
      :class="[
        'flex-1 h-full pt-0 transition-all duration-300 w-0 min-w-0 overflow-y-auto',
        sidebarCollapsed ? 'ml-18' : 'ml-64',
      ]"
    >
      <slot />
    </main>

    <!-- Global Alerts -->
    <GlobalAlert />
  </div>
</template>

<script setup>
import NavigationSideBar from "./NavigationSideBar.vue";
import GlobalAlert from "@/components/common/GlobalAlert.vue";
import { useSidebar } from "@/composables/useSidebar";
import { useThemeStore } from "@/stores/theme";

const { isCollapsed: sidebarCollapsed } = useSidebar();
const themeStore = useThemeStore();
</script>

<style scoped>
/* Ensure smooth scrolling on iOS */
@supports (-webkit-touch-callout: none) {
  main {
    -webkit-overflow-scrolling: touch;
  }
}
</style>
