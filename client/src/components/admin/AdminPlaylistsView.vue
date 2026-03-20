<template>
  <div class="admin-playlists flex flex-col h-full p-0 m-0">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-white mb-1">
          {{ $t("admin.playlists.global_playlists") }}
        </h3>
        <p class="text-gray-400 mb-0">
          {{ $t("admin.playlists.manage_desc") }}
        </p>
      </div>
    </div>

    <!-- Coming Soon Card -->
    <div class="flex justify-center">
      <div class="w-full max-w-3xl">
        <div
          class="bg-white/5 border border-white/10 rounded-lg p-8 text-center hover:shadow-md transition-shadow"
        >
          <div class="mb-4">
            <i class="bi bi-collection-play text-muted text-6xl"></i>
          </div>
          <h4 class="mb-3">{{ $t("admin.playlists.global_management") }}</h4>
          <p class="text-gray-400 mb-4">
            {{ $t("admin.playlists.feature_desc") }}
          </p>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left">
            <div>
              <h6 class="text-sky-400 mb-2">
                <i class="bi bi-globe mr-2"></i
                >{{ $t("admin.playlists.planned_features") }}
              </h6>
              <ul class="text-gray-400 space-y-2">
                <li>
                  <i class="bi bi-arrow-right mr-2"></i
                  >{{ $t("admin.playlists.create_system_playlists") }}
                </li>
                <li>
                  <i class="bi bi-arrow-right mr-2"></i
                  >{{ $t("admin.playlists.manage_public_playlists") }}
                </li>
                <li>
                  <i class="bi bi-arrow-right mr-2"></i
                  >{{ $t("admin.playlists.curate_featured") }}
                </li>
                <li>
                  <i class="bi bi-arrow-right mr-2"></i
                  >{{ $t("admin.playlists.define_categories") }}
                </li>
              </ul>
            </div>
            <div>
              <h6 class="text-emerald-400 mb-2">
                <i class="bi bi-shield-check mr-2"></i
                >{{ $t("admin.playlists.admin_functions") }}
              </h6>
              <ul class="text-gray-400 space-y-2">
                <li>
                  <i class="bi bi-arrow-right mr-2"></i
                  >{{ $t("admin.playlists.playlist_moderation") }}
                </li>
                <li>
                  <i class="bi bi-arrow-right mr-2"></i
                  >{{ $t("admin.playlists.global_settings") }}
                </li>
                <li>
                  <i class="bi bi-arrow-right mr-2"></i
                  >{{ $t("admin.playlists.playlist_statistics") }}
                </li>
                <li>
                  <i class="bi bi-arrow-right mr-2"></i
                  >{{ $t("admin.playlists.bulk_operations") }}
                </li>
              </ul>
            </div>
          </div>

          <div
            class="mt-4 bg-white/5 border border-white/10 text-blue-400 rounded p-3"
          >
            <i class="bi bi-info-circle mr-2"></i>
            <strong>{{ $t("admin.playlists.note") }}</strong>
            {{ $t("admin.playlists.user_playlists_note") }}
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-4">
      <div
        class="bg-white/5 border border-white/10 rounded-lg p-4 text-center hover:shadow-md hover:-translate-y-1 transition-transform"
      >
        <i class="bi bi-collection-play text-blue-500 text-4xl mb-2"></i>
        <h5 class="text-xl">0</h5>
        <p class="text-gray-400">
          {{ $t("admin.playlists.global_playlists_count") }}
        </p>
      </div>
      <div
        class="bg-white/5 border border-white/10 rounded-lg p-4 text-center hover:shadow-md hover:-translate-y-1 transition-transform"
      >
        <i class="bi bi-eye text-emerald-400 text-4xl mb-2"></i>
        <h5 class="text-xl">0</h5>
        <p class="text-gray-400">
          {{ $t("admin.playlists.public_playlists") }}
        </p>
      </div>
      <div
        class="bg-white/5 border border-white/10 rounded-lg p-4 text-center hover:shadow-md hover:-translate-y-1 transition-transform"
      >
        <i class="bi bi-star text-yellow-400 text-4xl mb-2"></i>
        <h5 class="text-xl">0</h5>
        <p class="text-gray-400">
          {{ $t("admin.playlists.featured_playlists") }}
        </p>
      </div>
      <div
        class="bg-white/5 border border-white/10 rounded-lg p-4 text-center hover:shadow-md hover:-translate-y-1 transition-transform"
      >
        <i class="bi bi-tags text-sky-400 text-4xl mb-2"></i>
        <h5 class="text-xl">0</h5>
        <p class="text-gray-400">{{ $t("admin.playlists.categories") }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useI18n } from "vue-i18n";

const { t } = useI18n();
// Placeholder for future global playlist management logic
</script>
