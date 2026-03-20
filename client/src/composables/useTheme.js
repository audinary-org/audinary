import { useThemeStore } from "@/stores/theme";
import { computed, onMounted } from "vue";

export function useTheme() {
  const themeStore = useThemeStore();

  // Initialize theme when composable is used
  onMounted(() => {
    themeStore.initTheme();
  });

  // Computed properties for easy access
  const currentTheme = computed(() => themeStore.getCurrentTheme);
  const backgroundGradient = computed(() => themeStore.backgroundGradient);
  const availableThemes = computed(() => themeStore.availableThemes);

  // Methods
  const setTheme = (themeId) => {
    themeStore.setTheme(themeId);
  };

  return {
    currentTheme,
    backgroundGradient,
    availableThemes,
    setTheme,
  };
}
