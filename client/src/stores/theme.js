import { defineStore } from "pinia";
import { ref, computed } from "vue";

export const useThemeStore = defineStore("theme", () => {
  // Available theme gradients
  const availableThemes = ref([
    // Default Theme
    {
      id: "default",
      name: "Audinary",
      gradient: "from-[#243B55] to-[#141E30]",
      preview: "bg-gradient-to-r from-[#243B55] to-[#141E30]",
    },

    // Black & Grey Themes
    {
      id: "obsidian",
      name: "Obsidian Night",
      gradient: "from-[#0c0c0c] to-[#1a1a2e]",
      preview: "bg-gradient-to-r from-[#0c0c0c] to-[#1a1a2e]",
    },
    {
      id: "charcoal",
      name: "Charcoal Dream",
      gradient: "from-[#2c2c2c] to-[#0c0c0c]",
      preview: "bg-gradient-to-r from-[#2c2c2c] to-[#0c0c0c]",
    },
    {
      id: "storm",
      name: "Storm Grey",
      gradient: "from-[#2c3e50] to-[#34495e]",
      preview: "bg-gradient-to-r from-[#2c3e50] to-[#34495e]",
    },

    // Blue Themes
    {
      id: "deepspace",
      name: "Deep Space",
      gradient: "from-[#0f0f23] to-[#16213e]",
      preview: "bg-gradient-to-r from-[#0f0f23] to-[#16213e]",
    },
    {
      id: "midnight-blue",
      name: "Midnight Blue",
      gradient: "from-[#1e1e2e] to-[#16537e]",
      preview: "bg-gradient-to-r from-[#1e1e2e] to-[#16537e]",
    },
    {
      id: "dark-forest",
      name: "Dark Forest",
      gradient: "from-[#0d1b2a] to-[#1b263b]",
      preview: "bg-gradient-to-r from-[#0d1b2a] to-[#1b263b]",
    },
    {
      id: "arctic",
      name: "Arctic Night",
      gradient: "from-[#1e3c72] to-[#2a5298]",
      preview: "bg-gradient-to-r from-[#1e3c72] to-[#2a5298]",
    },

    // Green Themes
    {
      id: "emerald-night",
      name: "Emerald Night",
      gradient: "from-[#0d2818] to-[#1a4d33]",
      preview: "bg-gradient-to-r from-[#0d2818] to-[#1a4d33]",
    },
    {
      id: "forest-depths",
      name: "Forest Depths",
      gradient: "from-[#0a1f0a] to-[#1b3b1b]",
      preview: "bg-gradient-to-r from-[#0a1f0a] to-[#1b3b1b]",
    },
    {
      id: "jade-shadow",
      name: "Jade Shadow",
      gradient: "from-[#1a2f1a] to-[#2d4a2d]",
      preview: "bg-gradient-to-r from-[#1a2f1a] to-[#2d4a2d]",
    },

    // Purple Themes
    {
      id: "gothic",
      name: "Gothic Purple",
      gradient: "from-[#1a0033] to-[#330066]",
      preview: "bg-gradient-to-r from-[#1a0033] to-[#330066]",
    },
    {
      id: "ember",
      name: "Dark Ember",
      gradient: "from-[#2d1b2f] to-[#5c2751]",
      preview: "bg-gradient-to-r from-[#2d1b2f] to-[#5c2751]",
    },

    // Red & Orange Themes
    {
      id: "crimson-dusk",
      name: "Crimson Dusk",
      gradient: "from-[#2d0a0a] to-[#4d1a1a]",
      preview: "bg-gradient-to-r from-[#2d0a0a] to-[#4d1a1a]",
    },
    {
      id: "ember-glow",
      name: "Ember Glow",
      gradient: "from-[#331a00] to-[#663300]",
      preview: "bg-gradient-to-r from-[#331a00] to-[#663300]",
    },
    {
      id: "flame-shadow",
      name: "Flame Shadow",
      gradient: "from-[#1a0d00] to-[#4d2600]",
      preview: "bg-gradient-to-r from-[#1a0d00] to-[#4d2600]",
    },
    {
      id: "copper-night",
      name: "Copper Night",
      gradient: "from-[#2d1f0d] to-[#5c3e1a]",
      preview: "bg-gradient-to-r from-[#2d1f0d] to-[#5c3e1a]",
    },
    {
      id: "wine",
      name: "Dark Wine",
      gradient: "from-[#2c1810] to-[#5d2f1a]",
      preview: "bg-gradient-to-r from-[#2c1810] to-[#5d2f1a]",
    },
  ]);

  // Current selected theme
  const currentTheme = ref("default");

  // Load theme from localStorage on init
  function initTheme() {
    const savedTheme = localStorage.getItem("selectedTheme");
    if (savedTheme && availableThemes.value.find((t) => t.id === savedTheme)) {
      currentTheme.value = savedTheme;
    }
  }

  // Set new theme
  function setTheme(themeId) {
    const theme = availableThemes.value.find((t) => t.id === themeId);
    if (theme) {
      currentTheme.value = themeId;
      localStorage.setItem("selectedTheme", themeId);

      // Apply theme to document root for CSS variables if needed
      applyThemeToDocument(theme);
    }
  }

  // Apply theme to document (for potential CSS variable usage)
  function applyThemeToDocument(theme) {
    // This could be extended for more complex theming if needed
    document.documentElement.setAttribute("data-theme", theme.id);
  }

  // Get current theme object
  const getCurrentTheme = computed(() => {
    return (
      availableThemes.value.find((t) => t.id === currentTheme.value) ||
      availableThemes.value[0]
    );
  });

  // Get background gradient class
  const backgroundGradient = computed(() => {
    return `bg-gradient-to-r ${getCurrentTheme.value.gradient}`;
  });

  // Initialize theme on store creation
  initTheme();

  return {
    availableThemes,
    currentTheme,
    getCurrentTheme,
    backgroundGradient,
    setTheme,
    initTheme,
  };
});
