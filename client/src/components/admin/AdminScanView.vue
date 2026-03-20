<template>
  <div class="admin-scan">
    <!-- Header mit Hauptaktionen -->
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-white mb-2">
        {{ t("admin.scan.library_scan") }}
      </h2>
      <p class="text-gray-400">
        {{
          t("admin.scan.scan_description") ||
          "Verwalte und aktualisiere deine Musikbibliothek"
        }}
      </p>
    </div>

    <!-- Schnellaktionen Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <!-- Full Scan Card -->
      <div
        class="bg-white/5 border border-white/10 rounded-lg p-6 hover:bg-white/10 transition-all group"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center group-hover:bg-blue-500/30 transition-colors"
            >
              <i class="bi bi-search text-2xl text-blue-400"></i>
            </div>
            <div>
              <h5 class="text-white font-semibold mb-1">
                {{ t("admin.scan.full_scan") }}
              </h5>
              <p class="text-sm text-gray-400">
                Komplette Bibliothek neu scannen
              </p>
            </div>
          </div>
        </div>
        <button
          class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-medium transition-colors flex items-center justify-center gap-2"
          @click="startScan('normal', true)"
          :disabled="scanInProgress"
        >
          <i class="bi bi-search" v-if="!scanInProgress"></i>
          <span
            v-else
            class="w-4 h-4 border-2 border-t-transparent border-white rounded-full animate-spin inline-block"
          ></span>
          {{
            scanInProgress
              ? t("admin.scan.scan_running")
              : t("admin.scan.start_full_scan") || "Vollständigen Scan starten"
          }}
        </button>
      </div>

      <!-- Quick Scan Card -->
      <div
        class="bg-white/5 border border-white/10 rounded-lg p-6 hover:bg-white/10 transition-all group"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 bg-emerald-500/20 rounded-lg flex items-center justify-center group-hover:bg-emerald-500/30 transition-colors"
            >
              <i class="bi bi-lightning text-2xl text-emerald-400"></i>
            </div>
            <div>
              <h5 class="text-white font-semibold mb-1">
                {{ t("admin.scan.normal_scan") }}
              </h5>
              <p class="text-sm text-gray-400">Nur neue Änderungen scannen</p>
            </div>
          </div>
        </div>
        <button
          class="w-full bg-white/5 border border-white/10 hover:bg-white/10 text-emerald-400 px-4 py-3 rounded-lg font-medium transition-colors flex items-center justify-center gap-2"
          @click="startScan('normal', false)"
          :disabled="scanInProgress"
        >
          <i class="bi bi-lightning"></i>
          {{ t("admin.scan.start_quick_scan") || "Schnellen Scan starten" }}
        </button>
      </div>
    </div>

    <!-- Scan Progress -->
    <div
      v-if="scanInProgress"
      class="bg-white/5 border border-white/10 rounded-lg p-6 mb-6"
    >
      <div class="flex items-center justify-between mb-4">
        <h5 class="text-white font-semibold flex items-center gap-2">
          <div class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></div>
          Scan läuft
        </h5>
        <span
          class="text-sm text-gray-400"
          v-if="scanData && scanData.total_albums"
        >
          {{ scanData.processed_albums || 0 }} / {{ scanData.total_albums }}
          {{ t("admin.scan.albums_processed") }}
        </span>
      </div>

      <div
        class="w-full bg-white/5 rounded-full overflow-hidden mb-3"
        style="height: 10px"
      >
        <div
          class="bg-blue-600 h-full transition-all duration-300"
          :style="{ width: scanProgress + '%' }"
        ></div>
      </div>

      <div class="flex flex-col gap-2 text-sm">
        <div v-if="scanStatus" class="text-gray-300 flex items-center gap-2">
          <i class="bi bi-info-circle text-blue-400"></i>
          {{ scanStatus }}
        </div>
        <div
          v-if="scanData && scanData.current_album"
          class="text-gray-400 flex items-center gap-2"
        >
          <i class="bi bi-disc"></i>
          {{ scanData.current_album }}
        </div>
        <div
          v-if="scanData && scanData.current_step"
          class="text-sky-400 flex items-center gap-2"
        >
          <i class="bi bi-gear"></i>
          {{ formatCurrentStep(scanData.current_step) }}
        </div>
      </div>
    </div>

    <!-- Scan Results -->
    <div
      v-if="scanData && !scanInProgress && scanData.statistics"
      class="bg-white/5 border border-white/10 rounded-lg p-6 mb-6"
    >
      <h5 class="text-white font-semibold mb-4 flex items-center gap-2">
        <i class="bi bi-check-circle text-emerald-400"></i>
        Scan abgeschlossen
      </h5>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div
          class="text-center p-4 bg-white/5 rounded-lg border border-white/10"
        >
          <div class="text-3xl font-bold text-emerald-400 mb-1">
            {{ scanData.statistics.albums_created || 0 }}
          </div>
          <div class="text-sm text-gray-400">
            {{ t("admin.scan.new_albums") }}
          </div>
        </div>
        <div
          class="text-center p-4 bg-white/5 rounded-lg border border-white/10"
        >
          <div class="text-3xl font-bold text-sky-400 mb-1">
            {{ scanData.statistics.albums_updated || 0 }}
          </div>
          <div class="text-sm text-gray-400">
            {{ t("admin.scan.updated_albums") }}
          </div>
        </div>
        <div
          class="text-center p-4 bg-white/5 rounded-lg border border-white/10"
        >
          <div class="text-3xl font-bold text-emerald-400 mb-1">
            {{ scanData.statistics.songs_created || 0 }}
          </div>
          <div class="text-sm text-gray-400">
            {{ t("admin.scan.new_songs") }}
          </div>
        </div>
        <div
          class="text-center p-4 bg-white/5 rounded-lg border border-white/10"
        >
          <div class="text-3xl font-bold text-sky-400 mb-1">
            {{ scanData.statistics.songs_updated || 0 }}
          </div>
          <div class="text-sm text-gray-400">
            {{ t("admin.scan.updated_songs") }}
          </div>
        </div>
      </div>
      <div class="mt-4 text-center text-gray-400 text-sm">
        <i class="bi bi-clock mr-2"></i>{{ t("admin.scan.duration") }}:
        {{ formatDuration(scanData.duration) }}
      </div>
    </div>

    <div class="space-y-6">
      <!-- Image Operations -->
      <div class="bg-white/5 border border-white/10 rounded-lg p-6">
        <h5 class="text-white font-semibold mb-4 flex items-center gap-2">
          <i class="bi bi-images"></i>
          {{ t("admin.scan.image_operations") || "Bild-Operationen" }}
        </h5>

        <!-- Update Operations -->
        <div class="mb-4">
          <p
            class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3"
          >
            Bilder aktualisieren
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button
              class="bg-white/5 border border-white/10 text-sky-400 px-4 py-3 rounded-lg hover:bg-white/10 transition-all text-left flex items-center gap-3 group"
              @click="updateArtistImages"
              :disabled="scanInProgress"
            >
              <div
                class="w-10 h-10 bg-sky-500/20 rounded-lg flex items-center justify-center group-hover:bg-sky-500/30 transition-colors"
              >
                <i class="bi bi-person text-lg"></i>
              </div>
              <div class="flex-1">
                <div class="font-medium">
                  {{ t("admin.scan.update_artist_images") }}
                </div>
                <div class="text-xs text-gray-400">
                  Künstlerbilder aktualisieren
                </div>
              </div>
            </button>

            <button
              class="bg-white/5 border border-white/10 text-sky-400 px-4 py-3 rounded-lg hover:bg-white/10 transition-all text-left flex items-center gap-3 group"
              @click="updateCoverImages"
              :disabled="scanInProgress"
            >
              <div
                class="w-10 h-10 bg-sky-500/20 rounded-lg flex items-center justify-center group-hover:bg-sky-500/30 transition-colors"
              >
                <i class="bi bi-disc text-lg"></i>
              </div>
              <div class="flex-1">
                <div class="font-medium">
                  {{ t("admin.scan.update_cover_images") }}
                </div>
                <div class="text-xs text-gray-400">
                  Cover-Bilder aktualisieren
                </div>
              </div>
            </button>
          </div>
        </div>

        <!-- Check Operations -->
        <div>
          <p
            class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3"
          >
            Fehlende Bilder prüfen
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button
              class="bg-white/5 border border-white/10 text-yellow-400 px-4 py-3 rounded-lg hover:bg-white/10 transition-all text-left flex items-center gap-3 group"
              @click="listMissingArtistImages"
              :disabled="scanInProgress"
            >
              <div
                class="w-10 h-10 bg-yellow-500/20 rounded-lg flex items-center justify-center group-hover:bg-yellow-500/30 transition-colors"
              >
                <i class="bi bi-person-x text-lg"></i>
              </div>
              <div class="flex-1">
                <div class="font-medium">
                  {{
                    t("admin.scan.list_missing_artist_images") ||
                    "Fehlende Künstlerbilder"
                  }}
                </div>
                <div class="text-xs text-gray-400">
                  Künstler ohne Bilder auflisten
                </div>
              </div>
            </button>

            <button
              class="bg-white/5 border border-white/10 text-yellow-400 px-4 py-3 rounded-lg hover:bg-white/10 transition-all text-left flex items-center gap-3 group"
              @click="listMissingCoverImages"
              :disabled="scanInProgress"
            >
              <div
                class="w-10 h-10 bg-yellow-500/20 rounded-lg flex items-center justify-center group-hover:bg-yellow-500/30 transition-colors"
              >
                <i class="bi bi-image-fill text-lg"></i>
              </div>
              <div class="flex-1">
                <div class="font-medium">
                  {{
                    t("admin.scan.list_missing_cover_images") ||
                    "Fehlende Cover-Bilder"
                  }}
                </div>
                <div class="text-xs text-gray-400">
                  Alben ohne Cover auflisten
                </div>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Scan Logs -->
      <div class="bg-white/5 border border-white/10 rounded-lg">
        <div
          class="px-6 py-4 border-b border-white/10 flex items-center justify-between"
        >
          <h5 class="text-white font-semibold flex items-center gap-2">
            <i class="bi bi-terminal"></i>
            {{ t("admin.scan.scan_logs") }}
          </h5>
          <div class="flex items-center gap-2">
            <button
              v-if="scanInProgress"
              class="text-sm bg-white/5 border border-white/10 text-gray-300 px-3 py-1.5 rounded-lg hover:bg-white/10 transition-colors"
              @click="pollScanLogs"
              :title="t('admin.refresh')"
            >
              <i class="bi bi-arrow-clockwise mr-1"></i>
              <span class="hidden sm:inline">Aktualisieren</span>
            </button>
            <button
              class="text-sm bg-white/5 border border-white/10 text-red-400 px-3 py-1.5 rounded-lg hover:bg-white/10 transition-colors"
              @click="clearLogs"
              :title="t('admin.scan.clear_logs')"
            >
              <i class="bi bi-trash mr-1"></i>
              <span class="hidden sm:inline">Löschen</span>
            </button>
          </div>
        </div>
        <div class="p-6">
          <div
            id="scanLogContainer"
            class="bg-black/30 border border-white/10 rounded-lg p-4 relative max-h-96 overflow-auto"
          >
            <pre
              class="text-gray-300 text-sm mb-0 font-mono"
              ref="logContent"
              >{{
                logs || t("admin.scan.no_logs") || "Keine Logs verfügbar"
              }}</pre
            >
            <div v-if="scanInProgress" class="absolute top-3 right-3">
              <div
                class="bg-red-600 text-white px-2 py-1 rounded-full text-xs flex items-center gap-1.5"
              >
                <span
                  class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"
                ></span>
                LIVE
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from "vue";
import { useApiStore } from "@/stores/api";
import { useScanStatus } from "@/composables/useScanStatus";
import { useAlertStore } from "@/stores/alert";
import { useI18n } from "vue-i18n";

const apiStore = useApiStore();
const alertStore = useAlertStore();
const globalScanStatus = useScanStatus();
const { t } = useI18n();

// State
const scanStatus = ref("");
const scanError = ref("");
const logContent = ref(null);

// Local state for logs
const logs = ref("");
const logsPosition = ref(0);
let logPollInterval = null;

// Use global scan status
const scanInProgress = computed(() => globalScanStatus.scanInProgress.value);
const scanData = computed(() => globalScanStatus.scanData.value);

// Computed for scan progress
const scanProgress = computed(() => {
  if (
    !scanData.value ||
    typeof scanData.value !== "object" ||
    !scanData.value.total_albums
  )
    return 0;
  const processed = scanData.value.processed_albums || 0;
  const total = scanData.value.total_albums || 1;
  return Math.round((processed / total) * 100);
});

// Methods
const startScan = async (option = "normal", fullScan = false) => {
  try {
    scanError.value = "";
    const queryParams = new URLSearchParams();
    if (option !== "normal") {
      queryParams.append("option", option);
    }
    if (fullScan) {
      queryParams.append("full", "true");
    }

    const response = await apiStore.makeRequest(
      `/api/scan-music?${queryParams}`,
      {
        method: "POST",
      },
    );

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    alertStore.addAlert("success", result.message || "Scan wurde gestartet");

    // Reset logs for new scan
    logs.value = "";
    logsPosition.value = 0;

    // Start global scan status monitoring and log polling
    setTimeout(() => {
      globalScanStatus.checkCurrentScanStatus();
      startLogPolling();
    }, 1000);
  } catch (error) {
    console.error("Fehler beim Starten des Scans:", error);
    scanError.value = error.message;
    alertStore.addAlert(
      "error",
      "Fehler beim Starten des Scans: " + error.message,
    );
  }
};

const updateArtistImages = () => startScanOption("update-artist-image");
const updateCoverImages = () => startScanOption("update-cover-images");
const listMissingArtistImages = () =>
  startScanOption("list-missing-artist-images");
const listMissingCoverImages = () =>
  startScanOption("list-missing-cover-images");

const startScanOption = async (option) => {
  scanError.value = "";

  try {
    const response = await apiStore.makeRequest(
      `/api/scan-music?option=${option}`,
      {
        method: "POST",
      },
    );

    if (!response.ok) throw new Error("Fehler beim Starten der Scan-Option");

    const data = await response.json();
    alertStore.addAlert("success", data.message || "Operation gestartet");

    // Reset logs for new operation
    logs.value = "";
    logsPosition.value = 0;

    // Start global scan status monitoring and log polling
    setTimeout(() => {
      globalScanStatus.checkCurrentScanStatus();
      startLogPolling();
    }, 1000);
  } catch (error) {
    console.error("Error starting scan option:", error);
    scanError.value = "Fehler: " + error.message;
    alertStore.addAlert("error", "Fehler: " + error.message);
  }
};

const pollScanLogs = async () => {
  try {
    const logsResponse = await apiStore.makeRequest(
      `/api/scan-logs?position=${logsPosition.value}`,
    );
    if (logsResponse.ok) {
      const logsData = await logsResponse.json();

      if (logsData.logs && logsData.logs.length > 0) {
        // Append new logs
        const newLogs = logsData.logs.join("\n");
        if (logs.value) {
          logs.value += "\n" + newLogs;
        } else {
          logs.value = newLogs;
        }

        // Update position for next poll
        if (logsData.position) {
          logsPosition.value = logsData.position;
        }

        // Auto-scroll to bottom
        setTimeout(() => {
          const logContainer = document.querySelector("#scanLogContainer");
          if (logContainer) {
            logContainer.scrollTop = logContainer.scrollHeight;
          }
        }, 50);
      } else if (!logsData.file_exists && !logs.value) {
        logs.value =
          "Keine Log-Datei gefunden. Scan wurde möglicherweise noch nicht gestartet.";
      }
    } else {
      throw new Error("Failed to fetch logs");
    }
  } catch (error) {
    console.error("Error fetching logs manually:", error);
    if (!logs.value) {
      logs.value = `Fehler beim Laden der Logs: ${error.message}`;
    }
  }
};

const startLogPolling = () => {
  if (logPollInterval) {
    clearInterval(logPollInterval);
  }
  pollScanLogs();
  logPollInterval = setInterval(pollScanLogs, 2000);
};

const stopLogPolling = () => {
  if (logPollInterval) {
    clearInterval(logPollInterval);
    logPollInterval = null;
  }
};

const clearLogs = () => {
  if (confirm("Möchten Sie die Scan-Logs wirklich löschen?")) {
    logs.value = "";
    logsPosition.value = 0;
  }
};

const formatCurrentStep = (step) => {
  const steps = {
    cleaning_database: "Datenbank bereinigen...",
    processing_albums: "Alben verarbeiten...",
    updating_statistics: "Statistiken aktualisieren...",
  };
  return steps[step] || step;
};

const formatDuration = (seconds) => {
  if (!seconds) return "0s";

  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = Math.round(seconds % 60);

  if (hours > 0) {
    return `${hours}h ${minutes}m ${secs}s`;
  } else if (minutes > 0) {
    return `${minutes}m ${secs}s`;
  } else {
    return `${secs}s`;
  }
};

onMounted(async () => {
  // Check current scan status
  await globalScanStatus.checkCurrentScanStatus();

  // If scan is already running, start log polling
  if (scanInProgress.value) {
    startLogPolling();
  }
});

onUnmounted(() => {
  // Cleanup
  stopLogPolling();
});
</script>

<style scoped>
#scanLogContainer {
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}

#scanLogContainer::-webkit-scrollbar {
  width: 6px;
}

#scanLogContainer::-webkit-scrollbar-track {
  background: transparent;
}

#scanLogContainer::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 3px;
}

#scanLogContainer::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.3);
}
</style>
