// Mobile utility functions for iOS PWA

/**
 * Detect if running on iOS
 */
export function isIOS() {
  return (
    /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1)
  );
}

/**
 * Detect if running in standalone mode (PWA)
 */
export function isStandalone() {
  return (
    window.navigator.standalone ||
    window.matchMedia("(display-mode: standalone)").matches ||
    window.matchMedia("(display-mode: fullscreen)").matches
  );
}

/**
 * Detect if device has Dynamic Island (iPhone 14 Pro series)
 */
export function hasDynamicIsland() {
  if (!isIOS()) return false;

  // Check for specific iPhone 14 Pro models
  const userAgent = navigator.userAgent;
  const hasPro = /iPhone15,2|iPhone15,3/.test(userAgent); // iPhone 14 Pro/Pro Max

  // Alternative: Check for specific safe area values
  const topInset =
    parseInt(
      getComputedStyle(document.documentElement)
        .getPropertyValue("--safe-area-inset-top")
        .replace("px", ""),
    ) || 0;

  return hasPro || topInset > 47; // Dynamic Island typically creates >47px top inset
}

/**
 * Get safe area insets
 */
export function getSafeAreaInsets() {
  const computedStyle = getComputedStyle(document.documentElement);
  return {
    top:
      parseInt(
        computedStyle
          .getPropertyValue("--safe-area-inset-top")
          .replace("px", ""),
      ) || 0,
    right:
      parseInt(
        computedStyle
          .getPropertyValue("--safe-area-inset-right")
          .replace("px", ""),
      ) || 0,
    bottom:
      parseInt(
        computedStyle
          .getPropertyValue("--safe-area-inset-bottom")
          .replace("px", ""),
      ) || 0,
    left:
      parseInt(
        computedStyle
          .getPropertyValue("--safe-area-inset-left")
          .replace("px", ""),
      ) || 0,
  };
}

/**
 * Apply iOS-specific fixes
 */
export function applyIOSFixes() {
  if (!isIOS()) return;

  // Prevent viewport scaling on orientation change
  const viewportMeta = document.querySelector('meta[name="viewport"]');
  if (viewportMeta) {
    viewportMeta.content =
      "width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover";
  }

  // Prevent bounce scrolling
  document.body.addEventListener(
    "touchmove",
    (e) => {
      if (
        e.target.closest(
          ".modal-body, .overflow-auto, .queue-list, .playlist-list",
        )
      ) {
        return; // Allow scrolling in these containers
      }
      e.preventDefault();
    },
    { passive: false },
  );

  // Fix iOS input zoom
  const inputs = document.querySelectorAll("input, textarea, select");
  inputs.forEach((input) => {
    input.style.fontSize = "16px";
  });

  // Handle orientation change
  window.addEventListener("orientationchange", () => {
    // Force viewport recalculation
    setTimeout(() => {
      const vh = window.innerHeight * 0.01;
      document.documentElement.style.setProperty("--vh", `${vh}px`);
    }, 100);
  });
}

/**
 * Setup modal fixes for iOS
 */
export function setupModalFixes() {
  // Better modal backdrop handling on iOS
  document.addEventListener("show.bs.modal", (event) => {
    if (isIOS()) {
      // Prevent background scrolling
      document.body.style.overflow = "hidden";
      document.body.style.position = "fixed";
      document.body.style.width = "100%";
    }
  });

  document.addEventListener("hidden.bs.modal", (event) => {
    if (isIOS()) {
      // Restore scrolling
      document.body.style.overflow = "";
      document.body.style.position = "";
      document.body.style.width = "";
    }
  });
}

/**
 * Setup touch improvements
 */
export function setupTouchImprovements() {
  // Improve touch responsiveness
  document.addEventListener("touchstart", () => {}, { passive: true });

  // Prevent accidental zooming on double tap
  let lastTouchEnd = 0;
  document.addEventListener(
    "touchend",
    (event) => {
      const now = new Date().getTime();
      if (now - lastTouchEnd <= 300) {
        event.preventDefault();
      }
      lastTouchEnd = now;
    },
    false,
  );

  // Better button feedback
  document.addEventListener(
    "touchstart",
    (event) => {
      if (event.target.closest(".btn")) {
        event.target.closest(".btn").style.transform = "scale(0.98)";
      }
    },
    { passive: true },
  );

  document.addEventListener(
    "touchend",
    (event) => {
      if (event.target.closest(".btn")) {
        setTimeout(() => {
          event.target.closest(".btn").style.transform = "";
        }, 100);
      }
    },
    { passive: true },
  );
}

/**
 * Initialize all mobile improvements
 */
export function initMobileImprovements() {
  if (typeof window === "undefined") return;

  applyIOSFixes();
  setupModalFixes();
  setupTouchImprovements();
}

/**
 * Utility to get device info for debugging
 */
export function getDeviceInfo() {
  return {
    userAgent: navigator.userAgent,
    platform: navigator.platform,
    isIOS: isIOS(),
    isStandalone: isStandalone(),
    hasDynamicIsland: hasDynamicIsland(),
    safeAreaInsets: getSafeAreaInsets(),
    viewportSize: {
      width: window.innerWidth,
      height: window.innerHeight,
      devicePixelRatio: window.devicePixelRatio,
    },
  };
}
