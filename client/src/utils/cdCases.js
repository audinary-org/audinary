import { ref } from "vue";

// Global cache for available CD case images
const availableCdCases = ref(new Set(["default"])); // default is always available
let cdCaseCheckPromise = null;

/**
 * Check which CD case images are available
 * This is called once globally and cached
 */
export const checkAvailableCdCases = async () => {
  if (cdCaseCheckPromise) {
    return cdCaseCheckPromise;
  }

  cdCaseCheckPromise = (async () => {
    const commonFiletypes = [
      "mp3",
      "flac",
      "wav",
      "ogg",
      "m4a",
      "aac",
      "wma",
      "aiff",
      "ape",
      "mpc",
      "opus",
    ];
    const available = new Set(["default"]); // default is always available

    const checkPromises = commonFiletypes.map(async (filetype) => {
      try {
        const response = await fetch(`/img/cdcases/${filetype}.webp`, {
          method: "HEAD",
        });
        if (response.ok) {
          available.add(filetype);
        }
      } catch (error) {
        // Image doesn't exist, ignore
      }
    });

    await Promise.all(checkPromises);
    availableCdCases.value = available;
    return available;
  })();

  return cdCaseCheckPromise;
};

/**
 * Get CD case image based on filetype with instant fallback
 * @param {string|number} filetype - The file type (e.g., 'mp3', 'flac')
 * @returns {string} - URL to the CD case image
 */
export const getCdCaseImage = (filetype) => {
  if (!filetype || filetype === "0" || filetype === 0) {
    return "/img/cdcases/default.webp";
  }

  // Check if we have this filetype available in our cache
  if (availableCdCases.value.has(filetype)) {
    return `/img/cdcases/${filetype}.webp`;
  }

  // Fallback to default immediately
  return "/img/cdcases/default.webp";
};

/**
 * Get the reactive reference to available CD cases
 * Useful for components that need to watch for changes
 */
export const getAvailableCdCases = () => {
  return availableCdCases;
};
