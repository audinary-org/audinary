import { createApp } from "vue";
import { createPinia } from "pinia";
import router from "./router";
import App from "./App.vue";
import i18n from "./i18n"; // Import the correct i18n instance

// Import Tailwind CSS
import "./styles/tailwind.css";

// Import Bootstrap Icons (keeping for icons)
import "bootstrap-icons/font/bootstrap-icons.css";

// Import Flag Icons
import "flag-icons/css/flag-icons.min.css";

// Import mobile utilities
import { initMobileImprovements } from "./utils/mobile.js";

// Import and initialize CD case utilities
import { checkAvailableCdCases } from "./utils/cdCases.js";

// Import theme store to initialize theme on app start
import { useThemeStore } from "./stores/theme.js";

// Create Pinia store
const pinia = createPinia();

// Disable Pinia dev tools in production
if (import.meta.env.PROD) {
  pinia._p.forEach((plugin) => {
    if (plugin && plugin.__pinia_dev_tools) {
      plugin.disabled = true;
    }
  });
}

// Create Vue app
const app = createApp(App);

// Use plugins
app.use(pinia);
app.use(router);
app.use(i18n);

// Global error handler
app.config.errorHandler = (err, vm, info) => {
  if (import.meta.env.DEV) {
    console.error("Vue Error:", err);
    console.error("Component:", vm);
    console.error("Info:", info);
  }

  // You could send this to an error reporting service
  // reportError(err, vm, info)
};

// Global properties
app.config.globalProperties.$formatTime = (seconds) => {
  if (!seconds || isNaN(seconds)) return "0:00";
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins}:${secs.toString().padStart(2, "0")}`;
};

app.config.globalProperties.$formatFileSize = (bytes) => {
  if (!bytes) return "0 B";
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
};

app.config.globalProperties.$formatDate = (date) => {
  if (!date) return "";
  return new Date(date).toLocaleDateString("de-DE", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

// Mount app
app.mount("#app");

// Initialize mobile improvements
initMobileImprovements();

// Initialize CD case checking
checkAvailableCdCases();

// Initialize theme store
const themeStore = useThemeStore();
themeStore.initTheme();

// Emit custom event when Vue app is ready
window.dispatchEvent(new CustomEvent("vue-app-ready"));

// Hot Module Replacement (HMR) support
if (import.meta.hot) {
  import.meta.hot.accept();
}

export default app;
