import { createI18n } from "vue-i18n";
import { nextTick } from "vue";

// Import translation files
import de from "@/locales/de.json";
import en from "@/locales/en.json";
import fr from "@/locales/fr.json";
import ru from "@/locales/ru.json";

// Get browser language or default to English
function getDefaultLocale() {
  const browserLang = navigator.language.split("-")[0];
  const supportedLocales = ["de", "en", "fr", "ru"];

  if (supportedLocales.includes(browserLang)) {
    return browserLang;
  }

  return "en";
}

// Get initial locale from user preference, localStorage, or browser
function getInitialLocale() {
  // First check if user has a saved preference in localStorage
  const savedLocale = localStorage.getItem("locale");

  // If preference is 'auto' or not set, use browser language
  if (!savedLocale || savedLocale === "auto") {
    return getDefaultLocale();
  }

  // If a specific language is set, use it
  return savedLocale;
}

// Create i18n instance
const initialLocale = getInitialLocale();

const i18n = createI18n({
  legacy: false, // Use Composition API mode
  locale: initialLocale,
  fallbackLocale: "en",
  messages: {
    de,
    en,
    fr,
    ru,
  },
});

// Make i18n globally available
window.__VUE_I18N__ = { global: i18n.global };

// Force set the document language immediately
document.documentElement.lang = initialLocale;

// Function to change locale
export function setLocale(locale) {
  // If locale is 'auto', determine the actual locale from browser
  const actualLocale = locale === "auto" ? getDefaultLocale() : locale;

  // Set the locale
  i18n.global.locale.value = actualLocale;

  // Store the preference
  localStorage.setItem("locale", locale); // Store the preference (could be 'auto')

  // Set document language
  document.documentElement.lang = actualLocale;
}

// Function to get current locale preference (could be 'auto')
export function getCurrentLocalePreference() {
  return localStorage.getItem("locale") || "auto";
}

// Function to get current active locale
export function getCurrentLocale() {
  return i18n.global.locale.value;
}

// Function to get available locales
export function getAvailableLocales() {
  return [
    { code: "auto", name: "Auto (Browser)", flag: "🌐", countryCode: null },
    { code: "de", name: "Deutsch", flag: "🇩🇪", countryCode: "de" },
    { code: "en", name: "English", flag: "🇺🇸", countryCode: "us" },
    { code: "fr", name: "Français", flag: "🇫🇷", countryCode: "fr" },
    { code: "ru", name: "Русский", flag: "🇷🇺", countryCode: "ru" },
  ];
}

// Function to load user language preference from server
export async function loadUserLanguagePreference() {
  try {
    const token = localStorage.getItem("auth_token");
    if (!token) {
      return;
    }

    const response = await fetch("/api/user/settings", {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });

    if (response.ok) {
      const data = await response.json();
      if (data.language) {
        const currentPreference = getCurrentLocalePreference();

        // Only set language if it's different from current preference
        // This prevents overriding manual changes
        if (data.language !== currentPreference) {
          setLocale(data.language);
        }
      }
    }
  } catch (error) {
    console.error("Failed to load user language preference:", error);
  }
}

export default i18n;
