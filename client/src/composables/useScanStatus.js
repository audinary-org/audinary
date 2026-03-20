import { ref, onMounted, onUnmounted, reactive } from "vue";
import { useAlertStore } from "@/stores/alert";
import { useApiStore } from "@/stores/api";
import { useAuthStore } from "@/stores/auth";

const SCAN_ALERT_KEY = "scan-status";

// Globaler State für Scan-Status
const scanInProgress = ref(false);
const scanData = ref("");
const scanPosition = ref(0);

export function useScanStatus() {
  const alertStore = useAlertStore();
  const apiStore = useApiStore();
  const authStore = useAuthStore();

  let scanStatusInterval = null;
  let globalScanAlertId = null;

  function showGlobalScanAlert(scanType = "normal", isFullScan = false) {
    const scanModeText = isFullScan ? "Vollständiger Scan" : "Normaler Scan";
    const message = `Achtung: Derzeit läuft ein ${scanModeText}...`;

    globalScanAlertId = alertStore.addOrUpdateAlert(
      SCAN_ALERT_KEY,
      message,
      "warning",
      0,
    );
  }

  function updateGlobalScanAlert(
    scanType = "normal",
    isFullScan = false,
    processedAlbums = 0,
    totalAlbums = 0,
    currentAlbum = "",
  ) {
    const scanModeText = isFullScan ? "Vollständiger Scan" : "Normaler Scan";
    let message = `Achtung: Derzeit läuft ein ${scanModeText}`;

    if (totalAlbums > 0) {
      message += ` - Album ${processedAlbums}/${totalAlbums}`;
    }

    if (currentAlbum) {
      message += ` (${currentAlbum})`;
    }

    globalScanAlertId = alertStore.addOrUpdateAlert(
      SCAN_ALERT_KEY,
      message,
      "warning",
      0,
    );
  }

  function showScanCompletionAlert() {
    hideGlobalScanAlert();
    alertStore.addAlert("✅ Scan abgeschlossen", "success", 10000);
  }

  function hideGlobalScanAlert() {
    alertStore.removeAlertByKey(SCAN_ALERT_KEY);
    globalScanAlertId = null;
  }

  const fetchScanStatus = async () => {
    try {
      const response = await apiStore.makeRequest("/api/scan-status");

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const status = await response.json();

      if (status.status === "running") {
        scanInProgress.value = true;
      } else {
        scanInProgress.value = false;
      }

      return status;
    } catch (error) {
      console.error("Error fetching scan status:", error);
      scanInProgress.value = false;
      return { status: "idle" };
    }
  };

  const fetchScanLogs = async () => {
    try {
      const response = await fetch(
        `/api/scan-logs?position=${scanPosition.value}`,
        {
          headers: {
            Authorization: `Bearer ${authStore.token}`,
            "Content-Type": "application/json",
          },
        },
      );

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (result.logs && result.logs.length > 0) {
        // Append new logs to existing content
        const newLogs = result.logs.join("\n");
        if (scanData.value) {
          scanData.value += "\n" + newLogs;
        } else {
          scanData.value = newLogs;
        }
      }

      // Update position for next fetch
      if (result.position) {
        scanPosition.value = result.position;
      }

      return result;
    } catch (error) {
      console.error("Error fetching scan logs:", error);
      return { logs: [], position: scanPosition.value };
    }
  };

  const resetScanData = () => {
    scanData.value = "";
    scanPosition.value = 0;
  };

  async function pollScanStatus() {
    try {
      const response = await apiStore.makeRequest("/api/scan-status");

      if (!response.ok) {
        console.warn(`Status API returned ${response.status}, retrying...`);
        return;
      }

      const status = await response.json();

      // Update scan data
      scanData.value = status;

      if (status.status === "running") {
        scanInProgress.value = true;

        // Update global alert with current progress
        updateGlobalScanAlert(
          status.option_name || "normal",
          status.full_scan || false,
          status.processed_albums || 0,
          status.total_albums || 0,
          status.current_album || "",
        );

        // Ensure we have the correct alert ID
        if (!globalScanAlertId) {
          const existingAlert = alertStore.findAlertByKey(SCAN_ALERT_KEY);
          if (existingAlert) {
            globalScanAlertId = existingAlert.id;
          }
        }
      } else if (status.status === "idle") {
        const wasRunning = scanInProgress.value;
        scanInProgress.value = false;

        // Show completion alert only if scan was running before
        if (wasRunning && status.statistics) {
          showScanCompletionAlert();
        } else {
          hideGlobalScanAlert();
        }

        // Stop polling when scan is idle
        stopStatusPolling();
      } else if (status.status === "error") {
        scanInProgress.value = false;
        hideGlobalScanAlert();

        // Stop polling on error
        stopStatusPolling();
      }
    } catch (error) {
      console.error("Error polling scan status:", error);

      // Only stop polling if we're not in a running state
      if (!scanInProgress.value) {
        stopStatusPolling();
      }
    }
  }

  function startStatusPolling() {
    if (scanStatusInterval) {
      clearInterval(scanStatusInterval);
      scanStatusInterval = null;
    }

    pollScanStatus();
    scanStatusInterval = setInterval(pollScanStatus, 1000);
  }

  function stopStatusPolling() {
    if (scanStatusInterval) {
      clearInterval(scanStatusInterval);
      scanStatusInterval = null;
    }
  }

  async function checkCurrentScanStatus() {
    try {
      const response = await apiStore.makeRequest("/api/scan-status");
      if (!response.ok) {
        console.warn(`Status API returned ${response.status}`);
        return;
      }

      const status = await response.json();

      // Update scan data
      scanData.value = status;

      // Check if a scan alert already exists
      const existingAlert = alertStore.findAlertByKey(SCAN_ALERT_KEY);
      if (existingAlert) {
        globalScanAlertId = existingAlert.id;
      }

      if (status.status === "running") {
        scanInProgress.value = true;

        // Show or update global alert with current scan info
        updateGlobalScanAlert(
          status.option_name || "normal",
          status.full_scan || false,
          status.processed_albums || 0,
          status.total_albums || 0,
          status.current_album || "",
        );

        // Start status polling
        startStatusPolling();
      } else if (status.status === "idle") {
        scanInProgress.value = false;
      } else if (status.status === "error") {
        scanInProgress.value = false;
      }
    } catch (error) {
      console.error("Error checking current scan status:", error);
    }
  }

  function initialize() {
    // Only check scan status if authenticated
    if (authStore.isAuthenticated) {
      checkCurrentScanStatus();
    }
  }

  function cleanup() {
    stopStatusPolling();
    hideGlobalScanAlert();
  }

  return {
    scanInProgress,
    scanData,
    scanPosition,
    initialize,
    cleanup,
    checkCurrentScanStatus,
    startStatusPolling,
    stopStatusPolling,
    fetchScanStatus,
    fetchScanLogs,
    resetScanData,
  };
}
