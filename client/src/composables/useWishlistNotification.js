import { ref } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useAlertStore } from "@/stores/alert";
import { useConfigStore } from "@/stores/config";
import { useI18n } from "vue-i18n";

export function useWishlistNotification() {
  const authStore = useAuthStore();
  const alertStore = useAlertStore();
  const configStore = useConfigStore();
  const { t } = useI18n();

  const checkInterval = ref(null);

  const checkPendingWishlist = async () => {
    // Only check for admins and if wishlist is enabled
    if (
      !authStore.isAdmin ||
      !authStore.token ||
      !configStore.isWishlistEnabled
    ) {
      return;
    }

    try {
      const response = await fetch("/api/admin/wishlist?status=pending", {
        headers: {
          Authorization: `Bearer ${authStore.token}`,
        },
      });

      const data = await response.json();

      if (response.ok && data.success) {
        const pendingCount = data.items?.length || 0;

        if (pendingCount > 0) {
          // Show or update persistent notification
          const message = t("admin.wishlist.pending_notification", {
            count: pendingCount,
          });

          // Use addOrUpdateAlert to update existing or create new
          alertStore.addOrUpdateAlert(
            "wishlist-pending", // key
            message,
            "info",
            0, // 0 = persistent (doesn't auto-dismiss)
          );
        } else {
          // Remove notification if no pending items
          alertStore.removeAlertByKey("wishlist-pending");
        }
      }
    } catch (error) {
      console.error("Failed to check pending wishlist:", error);
    }
  };

  const initialize = () => {
    if (!authStore.isAdmin || !configStore.isWishlistEnabled) {
      return;
    }

    // Check immediately
    checkPendingWishlist();

    // Check every 5 minutes
    checkInterval.value = setInterval(checkPendingWishlist, 5 * 60 * 1000);
  };

  const cleanup = () => {
    if (checkInterval.value) {
      clearInterval(checkInterval.value);
      checkInterval.value = null;
    }

    // Remove notification on cleanup
    alertStore.removeAlertByKey("wishlist-pending");
  };

  return {
    initialize,
    cleanup,
    checkPendingWishlist,
  };
}
