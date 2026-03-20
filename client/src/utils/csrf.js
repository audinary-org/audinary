/**
 * CSRF Protection Utilities
 *
 * Provides helper functions for CSRF token management
 */

/**
 * Get CSRF token from cookie
 * @returns {string|null} The CSRF token or null if not found
 */
export function getCsrfToken() {
  const value = `; ${document.cookie}`;
  const parts = value.split("; csrf_token=");

  if (parts.length === 2) {
    const token = parts.pop().split(";").shift();
    return token || null;
  }

  return null;
}

/**
 * Check if CSRF token exists
 * @returns {boolean}
 */
export function hasCsrfToken() {
  return getCsrfToken() !== null;
}

/**
 * Get CSRF header object for fetch requests
 * @returns {Object} Headers object with CSRF token
 */
export function getCsrfHeaders() {
  const token = getCsrfToken();

  if (!token) {
    return {};
  }

  return {
    "X-CSRF-Token": token,
  };
}
