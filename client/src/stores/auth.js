import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { loadUserLanguagePreference } from "@/i18n";
import { getCsrfHeaders } from "@/utils/csrf";

export const useAuthStore = defineStore("auth", () => {
  // State
  const user = ref(null);
  const token = ref(localStorage.getItem("auth_token"));
  const isLoading = ref(false);
  const isInitialized = ref(false);
  const error = ref(null);

  // Computed
  const isAuthenticated = computed(
    () =>
      !!token.value && !!user.value && isInitialized.value && !isLoading.value,
  );
  const isAdmin = computed(() => user.value?.role === "admin");
  const canCreatePublicShare = computed(
    () => user.value?.can_create_public_share || false,
  );

  // Actions
  async function login(credentials) {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await fetch("/api/auth/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          ...getCsrfHeaders(),
        },
        body: JSON.stringify(credentials),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || "Login failed");
      }

      token.value = data.token;
      user.value = data.user;
      isInitialized.value = true;

      localStorage.setItem("auth_token", data.token);

      // Load user language preference after successful login
      await loadUserLanguagePreference();

      return data;
    } catch (err) {
      error.value = err.message;
      console.error("Login error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  function logout(reason = "user_action") {
    // JWT logout is purely client-side - just clear local state
    token.value = null;
    user.value = null;
    error.value = null;
    isInitialized.value = false;
    localStorage.removeItem("auth_token");

    // Notify other components
    window.dispatchEvent(
      new CustomEvent("user-logged-out", {
        detail: { reason },
      }),
    );
  }

  async function fetchUser() {
    if (!token.value) return;

    try {
      const response = await fetch("/api/user", {
        headers: {
          Authorization: `Bearer ${token.value}`,
        },
      });

      if (!response.ok) {
        if (response.status === 401) {
          console.log("Token expired or invalid, clearing auth state");
          // Clear invalid token
          token.value = null;
          user.value = null;
          localStorage.removeItem("auth_token");

          // Dispatch session expired event for UI handling
          window.dispatchEvent(
            new CustomEvent("session-expired", {
              detail: {
                code: "TOKEN_EXPIRED",
                message: "Your session has expired. Please log in again.",
              },
            }),
          );
        }
        throw new Error("Failed to fetch user");
      }

      const userData = await response.json();
      user.value = userData;

      // Load user language preference when user data is fetched
      await loadUserLanguagePreference();
    } catch (error) {
      console.error("Fetch user error:", error);
      // Clear invalid token on any error
      token.value = null;
      user.value = null;
      localStorage.removeItem("auth_token");
    }
  }

  async function register(credentials) {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await fetch("/api/auth/register", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          ...getCsrfHeaders(),
        },
        body: JSON.stringify(credentials),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || "Registration failed");
      }

      token.value = data.token;
      user.value = data.user;

      localStorage.setItem("auth_token", data.token);

      // Load user language preference after successful login
      await loadUserLanguagePreference();

      return data;
    } catch (err) {
      error.value = err.message;
      console.error("Registration error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateProfile(profileData) {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await fetch("/api/auth/profile", {
        method: "PUT",
        headers: {
          Authorization: `Bearer ${token.value}`,
          "Content-Type": "application/json",
          ...getCsrfHeaders(),
        },
        body: JSON.stringify(profileData),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || "Profile update failed");
      }

      user.value = data;

      return data;
    } catch (err) {
      error.value = err.message;
      console.error("Profile update error:", err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function checkAuthStatus() {
    try {
      const response = await fetch("/api/config");
      const data = await response.json();

      if (response.ok) {
        return data;
      }

      throw new Error("Failed to check auth status");
    } catch (err) {
      console.error("Check auth status error:", err);
      return null;
    }
  }

  // Check authentication and fetch user if token exists
  async function checkAuth() {
    if (!token.value) {
      isInitialized.value = true;
      return false;
    }

    isLoading.value = true;

    try {
      await fetchUser();
      // Only return true if both token exists and user was fetched successfully
      const authValid = !!(token.value && user.value);

      if (!authValid) {
        console.log("Auth check failed - invalid token or user data");
        // Ensure we're logged out completely
        await logout("invalid_session");
        return false;
      }

      return authValid;
    } catch (error) {
      console.error("Auth check failed:", error);

      // If auth check fails, ensure clean logout
      await logout("auth_check_failed");
      return false;
    } finally {
      isInitialized.value = true;
      isLoading.value = false;
    }
  }

  // Validate current session
  async function validateSession() {
    if (!token.value || !user.value) {
      return false;
    }

    try {
      // Quick validation by checking auth status
      const response = await fetch("/api/user", {
        headers: {
          Authorization: `Bearer ${token.value}`,
        },
      });

      if (response.status === 401) {
        console.warn("Session validation failed - 401 response");
        await logout("session_invalid");
        return false;
      }

      return response.ok;
    } catch (error) {
      console.error("Session validation error:", error);
      return false;
    }
  }

  // Initialize auth state
  function initialize() {
    if (token.value) {
      fetchUser();
    }
  }

  return {
    // State
    user,
    token,
    isLoading,
    isInitialized,
    error,

    // Computed
    isAuthenticated,
    isAdmin,
    canCreatePublicShare,

    // Actions
    login,
    register,
    logout,
    fetchUser,
    updateProfile,
    checkAuthStatus,
    checkAuth,
    validateSession,
    initialize,
  };
});
