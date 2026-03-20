<template>
  <div class="admin-dashboard flex flex-col h-full p-0 m-0">
    <!-- Quick Actions -->
    <div class="mb-3">
      <div class="bg-white/5 border border-white/10 rounded-lg">
        <div class="px-4 py-3 border-b border-white/10 flex items-center">
          <h5 class="mb-0 flex items-center gap-2">
            <i class="bi bi-lightning"></i>{{ $t("admin.quick_actions") }}
          </h5>
        </div>
        <div class="p-4">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
            <router-link
              to="/admin/scan"
              class="bg-white/5 border border-white/10 text-blue-400 text-center px-4 py-2 rounded hover:bg-white/10 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <i class="bi bi-music-note-list mr-2"></i
              >{{ $t("admin.scan_music") }}</router-link
            >
            <router-link
              to="/admin/users"
              class="bg-white/5 border border-white/10 text-emerald-400 text-center px-4 py-2 rounded hover:bg-white/10 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
              <i class="bi bi-person-plus mr-2"></i
              >{{ $t("admin.add_user") }}</router-link
            >
            <router-link
              to="/admin/config"
              class="bg-white/5 border border-white/10 text-sky-400 text-center px-4 py-2 rounded hover:bg-white/10 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-300"
            >
              <i class="bi bi-gear mr-2"></i
              >{{ $t("admin.settings") }}</router-link
            >
            <button
              class="bg-white/5 border border-white/10 text-yellow-400 px-4 py-2 rounded hover:bg-white/10 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-yellow-400"
              @click="refreshDashboard"
            >
              <i class="bi bi-arrow-clockwise mr-2"></i
              >{{ $t("admin.refresh") }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
      <div
        class="bg-white/5 border border-white/10 text-white rounded-lg p-4 hover:shadow-lg hover:-translate-y-1 transition-transform"
      >
        <div class="flex justify-between">
          <div>
            <h4 class="mb-0">{{ formatNumber(stats.totalSongs) || 0 }}</h4>
            <p class="text-sm">{{ $t("admin.songs") }}</p>
            <small v-if="stats.totalDuration" class="opacity-80">{{
              formatDuration(stats.totalDuration)
            }}</small>
          </div>
          <div class="self-center">
            <i class="bi bi-music-note text-2xl text-blue-500"></i>
          </div>
        </div>
      </div>

      <div
        class="bg-white/5 border border-white/10 text-white rounded-lg p-4 hover:shadow-lg hover:-translate-y-1 transition-transform"
      >
        <div class="flex justify-between">
          <div>
            <h4 class="mb-0">{{ formatNumber(stats.totalAlbums) || 0 }}</h4>
            <p class="text-sm">{{ $t("admin.albums") }}</p>
          </div>
          <div class="self-center">
            <i class="bi bi-collection-play text-2xl text-emerald-500"></i>
          </div>
        </div>
      </div>

      <div
        class="bg-white/5 border border-white/10 text-white rounded-lg p-4 hover:shadow-lg hover:-translate-y-1 transition-transform"
      >
        <div class="flex justify-between">
          <div>
            <h4 class="mb-0">{{ formatNumber(stats.totalArtists) || 0 }}</h4>
            <p class="text-sm">{{ $t("admin.artists") }}</p>
          </div>
          <div class="self-center">
            <i class="bi bi-people text-2xl text-sky-400"></i>
          </div>
        </div>
      </div>

      <div
        class="bg-white/5 border border-white/10 text-white rounded-lg p-4 hover:shadow-lg hover:-translate-y-1 transition-transform"
      >
        <div class="flex justify-between">
          <div>
            <h4 class="mb-0">{{ formatNumber(stats.totalUsers) || 0 }}</h4>
            <p class="text-sm">{{ $t("admin.user") }}</p>
            <small v-if="stats.adminUsers" class="opacity-80"
              >{{ stats.adminUsers }} {{ $t("admin.admins")
              }}{{ stats.adminUsers !== 1 ? "s" : "" }}</small
            >
          </div>
          <div class="self-center">
            <i class="bi bi-person-check text-2xl text-yellow-400"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Secondary Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
      <div class="bg-white/5 border border-white/10 rounded-lg p-4">
        <div class="text-center">
          <h6 class="mb-0">{{ formatNumber(stats.totalPlaylists) || 0 }}</h6>
          <small class="text-gray-400">{{ $t("admin.playlist") }}</small>
        </div>
      </div>
      <div class="bg-white/5 border border-white/10 rounded-lg p-4">
        <div class="text-center">
          <h6 class="mb-0">{{ formatBytes(stats.musicDirSize) || "N/A" }}</h6>
          <small class="text-gray-400">{{ $t("admin.music_library") }}</small>
        </div>
      </div>
      <div class="bg-white/5 border border-white/10 rounded-lg p-4">
        <div class="text-center">
          <h6 class="mb-0">{{ formatNumber(stats.newUsersThisWeek) || 0 }}</h6>
          <small class="text-gray-400">{{ $t("admin.new_users_week") }}</small>
        </div>
      </div>
      <div class="bg-white/5 border border-white/10 rounded-lg p-4">
        <div class="text-center">
          <h6 class="mb-0">
            {{
              stats.lastScan ? formatDate(stats.lastScan) : $t("admin.never")
            }}
          </h6>
          <small class="text-gray-400">{{ $t("admin.last_scan") }}</small>
        </div>
      </div>
    </div>

    <!-- Charts and lists simplified to Tailwind containers -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-3">
      <div
        class="lg:col-span-2 bg-white/5 border border-white/10 rounded-lg p-4 h-full"
      >
        <div class="mb-2 flex items-center">
          <h5 class="mb-0">
            <i class="bi bi-graph-up mr-2"></i>{{ $t("admin.listening_stats") }}
          </h5>
        </div>
        <div v-if="chartLoading" class="text-center py-5">
          <div
            class="w-10 h-10 border-4 border-t-transparent border-blue-600 rounded-full animate-spin mx-auto"
          ></div>
        </div>
        <div class="chart-container relative h-48">
          <canvas
            ref="listeningChart"
            :style="{ display: chartLoading ? 'none' : 'block' }"
          ></canvas>
        </div>
      </div>

      <div class="bg-white/5 border border-white/10 rounded-lg p-4 h-full">
        <div class="mb-2 flex items-center">
          <h5 class="mb-0">
            <i class="bi bi-music-note-list mr-2"></i
            >{{ $t("admin.top_genres") }}
          </h5>
        </div>
        <div v-if="loadingGenres" class="text-center py-3">
          <div
            class="w-8 h-8 border-4 border-t-transparent border-blue-600 rounded-full animate-spin mx-auto"
          ></div>
        </div>
        <div
          v-else-if="topGenres.length === 0"
          class="text-center py-3 text-gray-400"
        >
          <i class="bi bi-music-note-list text-3xl"></i>
          <p class="mt-2">{{ $t("admin.no_data_available") }}</p>
        </div>
        <div v-else class="overflow-y-auto max-h-48">
          <div
            v-for="(genre, index) in topGenres"
            :key="genre.name"
            class="flex justify-between items-center px-3 py-2 border-b border-white/10"
          >
            <div class="flex items-center gap-2">
              <span
                class="bg-white/5 border border-white/10 text-blue-400 rounded-full w-6 h-6 flex items-center justify-center"
                >{{ index + 1 }}</span
              ><span>{{ genre.name }}</span>
            </div>
            <span class="text-gray-400">{{ genre.count }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- System Info & Activity simplified -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-3">
      <div class="bg-white/5 border border-white/10 rounded-lg p-4">
        <h5 class="mb-3">
          <i class="bi bi-cpu mr-2"></i>{{ $t("admin.system_information") }}
        </h5>
        <div v-if="loadingSystemInfo" class="text-center py-3">
          <div
            class="w-8 h-8 border-4 border-t-transparent border-blue-600 rounded-full animate-spin mx-auto"
          ></div>
        </div>
        <div v-else>
          <div class="grid grid-cols-2 gap-2 text-sm text-gray-400">
            <div>
              <strong>{{ $t("admin.php_version") }}:</strong><br /><span
                class="text-gray-300"
                >{{ systemInfo.phpVersion || "N/A" }}</span
              >
            </div>
            <div>
              <strong>{{ $t("admin.server_uptime") }}:</strong><br /><span
                class="text-gray-300"
                >{{ systemInfo.uptime || "N/A" }}</span
              >
            </div>
            <div>
              <strong>{{ $t("admin.memory") }}:</strong><br /><span
                class="text-gray-300"
                >{{ systemInfo.memoryUsage || "N/A" }}</span
              >
            </div>
            <div>
              <strong>{{ $t("admin.disk") }}:</strong><br /><span
                class="text-gray-300"
                >{{ systemInfo.diskSpace || "N/A" }}</span
              >
            </div>
            <div class="col-span-2">
              <strong>{{ $t("admin.last_scan") }}:</strong><br /><span
                class="text-gray-300"
                >{{ formatDate(stats.lastScan) || $t("admin.never") }}</span
              >
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white/5 border border-white/10 rounded-lg p-4">
        <h5 class="mb-3">
          <i class="bi bi-clock-history mr-2"></i
          >{{ $t("admin.recent_activity") }}
        </h5>
        <div v-if="loadingActivity" class="text-center py-3">
          <div
            class="w-8 h-8 border-4 border-t-transparent border-blue-600 rounded-full animate-spin mx-auto"
          ></div>
        </div>
        <div
          v-else-if="recentActivity.length === 0"
          class="text-center py-3 text-gray-400"
        >
          <i class="bi bi-clock-history text-3xl"></i>
          <p class="mt-2">{{ $t("admin.no_activity") }}</p>
        </div>
        <div v-else class="overflow-y-auto max-h-48">
          <div
            v-for="activity in recentActivity"
            :key="activity.id"
            class="flex items-center gap-3 px-3 py-2 border-b border-white/10"
          >
            <div class="text-xl">
              <i class="bi" :class="getActivityIcon(activity.type)"></i>
            </div>
            <div class="flex-1 text-sm">
              <div>{{ activity.description }}</div>
              <small class="text-gray-400">{{
                formatDate(activity.timestamp)
              }}</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useApiStore } from "@/stores/api";
import { useI18n } from "vue-i18n";

const apiStore = useApiStore();
const { t } = useI18n();

// State
const stats = ref({
  totalSongs: 0,
  totalAlbums: 0,
  totalArtists: 0,
  totalUsers: 0,
  totalPlaylists: 0,
  adminUsers: 0,
  totalDuration: 0,
  musicDirSize: 0,
  lastScan: null,
  newUsersThisWeek: 0,
});

const topGenres = ref([]);
const recentActivity = ref([]);
const systemInfo = ref({
  phpVersion: "",
  uptime: "",
  memoryUsage: "",
  diskSpace: "",
});

const chartLoading = ref(true);
const loadingGenres = ref(true);
const loadingActivity = ref(true);
const loadingSystemInfo = ref(true);

const listeningChart = ref(null);
let chartInstance = null;

// Load dashboard data
const loadDashboardStats = async () => {
  try {
    const response = await apiStore.get("/api/admin/stats");

    // The API store already returns the data directly, not nested in .data
    const data = response;

    // Explicitly assign each field to ensure reactivity
    stats.value.totalSongs = data.totalSongs || 0;
    stats.value.totalAlbums = data.totalAlbums || 0;
    stats.value.totalArtists = data.totalArtists || 0;
    stats.value.totalUsers = data.totalUsers || 0;
    stats.value.totalPlaylists = data.totalPlaylists || 0;
    stats.value.adminUsers = data.adminUsers || 0;
    stats.value.totalDuration = data.totalDuration || 0;
    stats.value.musicDirSize = data.musicDirSize || 0;
    stats.value.lastScan = data.lastScan;
    stats.value.newUsersThisWeek = data.newUsersThisWeek || 0;
  } catch (error) {
    console.error("Error loading dashboard stats:", error);
    // Keep default values on error
  }
};

const loadTopGenres = async () => {
  try {
    loadingGenres.value = true;
    const response = await apiStore.get("/api/admin/top-genres");
    topGenres.value = response || [];
  } catch (error) {
    console.error("Error loading top genres:", error);
    topGenres.value = [];
  } finally {
    loadingGenres.value = false;
  }
};

const loadRecentActivity = async () => {
  try {
    loadingActivity.value = true;
    const response = await apiStore.get("/api/admin/recent-activity");
    recentActivity.value = response || [];
  } catch (error) {
    console.error("Error loading recent activity:", error);
    recentActivity.value = [];
  } finally {
    loadingActivity.value = false;
  }
};

const loadSystemInfo = async () => {
  try {
    loadingSystemInfo.value = true;
    const response = await apiStore.get("/api/admin/system-info");

    // The API store already returns the data directly
    const data = response;

    // Explicitly assign each field
    systemInfo.value.phpVersion = data.phpVersion || "N/A";
    systemInfo.value.uptime = data.uptime || "N/A";
    systemInfo.value.memoryUsage = data.memoryUsage || "N/A";
    systemInfo.value.diskSpace = data.diskSpace || "N/A";
  } catch (error) {
    console.error("Error loading system info:", error);
    // Keep default values on error
  } finally {
    loadingSystemInfo.value = false;
  }
};

const loadListeningChart = async () => {
  try {
    chartLoading.value = true;

    // Lade Chart.js dynamisch
    const Chart = (await import("chart.js/auto")).default;

    const response = await apiStore.get("/api/admin/listening-stats");
    let chartData = response.data;

    // Handle different API response formats
    if (Array.isArray(chartData)) {
      // API returned array, create proper chart data object
      chartData = {
        labels: ["Sa", "So", "Mo", "Di", "Mi", "Do", "Fr"],
        data: chartData,
      };
    } else if (!chartData || !chartData.labels || !chartData.data) {
      console.warn("No chart data available, using fallback");
      // Fallback data when no chart data is available
      chartData = {
        labels: ["Sa", "So", "Mo", "Di", "Mi", "Do", "Fr"],
        data: [0, 0, 0, 0, 0, 0, 0],
      };
    }

    // Set loading to false first so canvas becomes visible
    chartLoading.value = false;

    // Wait for DOM update
    await nextTick();
    await nextTick(); // Double wait for safety

    // Small delay to ensure canvas is rendered
    setTimeout(() => {
      if (listeningChart.value) {
        createChart(Chart, chartData);
      } else {
        console.error("Canvas still not available");
      }
    }, 100);
  } catch (error) {
    console.error("Error loading listening chart:", error);
    chartLoading.value = false;
  }
};

const createChart = (Chart, chartData) => {
  try {
    if (!listeningChart.value) {
      console.error("Chart canvas not available in createChart");
      return;
    }

    const ctx = listeningChart.value.getContext("2d");

    // Destroy existing chart if it exists
    if (chartInstance) {
      chartInstance.destroy();
      chartInstance = null;
    }

    chartInstance = new Chart(ctx, {
      type: "line",
      data: {
        labels: chartData.labels,
        datasets: [
          {
            label: "Songs gehört",
            data: chartData.data,
            borderColor: "#0d6efd",
            backgroundColor: "rgba(13, 110, 253, 0.1)",
            tension: 0.4,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: {
              color: "#ffffff",
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: "rgba(255, 255, 255, 0.1)",
            },
            ticks: {
              color: "#ffffff",
            },
          },
          x: {
            grid: {
              color: "rgba(255, 255, 255, 0.1)",
            },
            ticks: {
              color: "#ffffff",
            },
          },
        },
      },
    });
  } catch (error) {
    console.error("Error creating chart:", error);
  }
};

const refreshDashboard = async () => {
  // Destroy existing chart first
  if (chartInstance) {
    chartInstance.destroy();
    chartInstance = null;
  }

  // Load basic data first
  await Promise.all([
    loadDashboardStats(),
    loadTopGenres(),
    loadRecentActivity(),
    loadSystemInfo(),
  ]);

  // Wait for DOM update
  await nextTick();

  // Load chart after everything else is ready
  setTimeout(() => {
    loadListeningChart();
  }, 500);
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleString("de-DE");
};

const formatNumber = (number) => {
  if (!number) return "0";
  return new Intl.NumberFormat("de-DE").format(number);
};

const formatBytes = (bytes) => {
  if (!bytes) return "0 B";
  const units = ["B", "KB", "MB", "GB", "TB"];
  let i = 0;
  while (bytes >= 1024 && i < units.length - 1) {
    bytes /= 1024;
    i++;
  }
  return `${Math.round(bytes * 10) / 10} ${units[i]}`;
};

const formatDuration = (seconds) => {
  if (!seconds) return "0s";

  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);

  if (hours > 0) {
    return `${hours}h ${minutes}m`;
  } else if (minutes > 0) {
    return `${minutes}m`;
  } else {
    return `${seconds}s`;
  }
};

const getActivityIcon = (type) => {
  const icons = {
    user_login: "bi-person-check-fill text-success",
    user_created: "bi-person-plus-fill text-primary",
    scan_completed: "bi-music-note-list text-info",
    scan_running: "bi-arrow-clockwise text-warning",
    scan_error: "bi-exclamation-triangle-fill text-danger",
    playlist_created: "bi-collection-play text-success",
    error: "bi-exclamation-triangle-fill text-warning",
    default: "bi-info-circle text-muted",
  };
  return icons[type] || icons.default;
};

onMounted(async () => {
  // Load basic dashboard data first
  await Promise.all([
    loadDashboardStats(),
    loadTopGenres(),
    loadRecentActivity(),
    loadSystemInfo(),
  ]);

  // Wait for the next DOM update cycle
  await nextTick();

  // Additional wait to ensure canvas is fully rendered
  setTimeout(() => {
    loadListeningChart();
  }, 500);
});
</script>
