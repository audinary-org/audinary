import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "@/stores/auth";

// Views
import AuthView from "@/views/AuthView.vue";
import MainView from "@/views/MainView.vue";
import PublicShareView from "@/views/PublicShareView.vue";

const routes = [
  {
    path: "/auth",
    name: "Auth",
    component: AuthView,
    meta: {
      requiresGuest: true,
      title: "Login - Audinary",
    },
  },
  {
    path: "/reset-password",
    name: "ResetPassword",
    component: AuthView,
    meta: {
      requiresGuest: true,
      title: "Reset Password - Audinary",
    },
  },
  {
    path: "/",
    name: "Main",
    component: MainView,
    meta: {
      requiresAuth: true,
      title: "Audinary",
    },
  },
  {
    path: "/admin",
    redirect: "/?tab=settings",
  },
  {
    path: "/share/:uuid",
    name: "PublicShareView",
    component: PublicShareView,
    meta: {
      title: "Public Share - Audinary",
      public: true,
    },
  },
  {
    path: "/:pathMatch(.*)*",
    redirect: "/",
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Navigation Guards
router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore();

  // Set page title
  if (to.meta.title) {
    document.title = to.meta.title;
  }

  // Special handling for public routes - allow access regardless of auth status
  if (to.meta.public || to.name === "ResetPassword") {
    next();
    return;
  }

  // Wait for authentication to be initialized
  if (!authStore.isInitialized) {
    try {
      await authStore.checkAuth();
    } catch (error) {
      // Continue with unauthenticated state
    }
  }

  // Handle routes that require authentication
  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated) {
      next("/auth");
      return;
    }

    // Check admin requirement
    if (to.meta.requiresAdmin && !authStore.user?.is_admin) {
      next("/");
      return;
    }
  }

  // Handle routes that require guest (not authenticated)
  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    next("/");
    return;
  }

  next();
});

export default router;
