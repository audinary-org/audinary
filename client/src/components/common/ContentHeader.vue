<template>
  <div
    class="sticky top-0 z-10 bg-white/20 backdrop-blur-lg flex-shrink-0 rounded-2xl shadow-lg"
  >
    <div class="px-4">
      <div class="pt-3">
        <div class="mb-3">
          <div class="flex justify-between items-center flex-wrap gap-3">
            <!-- Title -->
            <h2 class="text-audinary text-2xl font-semibold">
              {{ title }}
            </h2>

            <!-- Actions Container -->
            <div class="flex flex-wrap gap-2 items-center">
              <!-- Search Input - Desktop -->
              <div v-if="showSearch" class="relative hidden md:block">
                <input
                  type="text"
                  :value="searchQuery"
                  @input="$emit('update:searchQuery', $event.target.value)"
                  class="search-input bg-white/20 text-white placeholder-white/70 rounded-lg px-3 py-2 pl-10 pr-10 focus:outline-none focus:ring-2 focus:ring-audinary"
                  :placeholder="searchPlaceholder"
                  style="min-width: 200px"
                />
                <i
                  class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-white/60 pointer-events-none z-10"
                ></i>
                <button
                  v-if="searchQuery"
                  @click="$emit('update:searchQuery', '')"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-white/60 hover:text-white z-10"
                  type="button"
                  aria-label="Clear search"
                >
                  <i class="bi bi-x"></i>
                </button>
              </div>

              <!-- Search Icon - Mobile -->
              <button
                v-if="showSearch"
                class="md:hidden inline-flex items-center px-2 py-1 border border-white/20 rounded text-white hover:bg-white/10"
                @click="toggleMobileSearch"
                :class="{ 'bg-white/20': showMobileSearchInput }"
              >
                <i class="bi bi-search"></i>
              </button>

              <!-- Filter Toggle Button -->
              <button
                v-if="showFilter"
                class="inline-flex items-center gap-2 px-3 py-1 border border-white/20 rounded text-white hover:bg-white/10"
                @click="$emit('toggle-filters')"
                :class="{ 'bg-white/20': filtersOpen }"
              >
                <i class="bi bi-funnel"></i>
                <span class="hidden md:inline">{{ filterLabel }}</span>
                <span
                  v-if="activeFiltersCount > 0"
                  class="ml-1 inline-block bg-audinary text-black text-xs px-2 py-0.5 rounded"
                  >{{ activeFiltersCount }}</span
                >
              </button>

              <!-- Custom Action Buttons Slot -->
              <slot name="actions"></slot>

              <!-- View Toggle -->
              <div
                v-if="showViewToggle"
                class="inline-flex border border-white/20 rounded overflow-hidden"
              >
                <button
                  class="px-3 py-1 text-white hover:bg-white/10"
                  :class="{ 'bg-white/20': viewMode === 'grid' }"
                  @click="$emit('update:viewMode', 'grid')"
                >
                  <i class="bi bi-grid"></i>
                </button>
                <button
                  class="px-3 py-1 text-white hover:bg-white/10"
                  :class="{ 'bg-white/20': viewMode === 'list' }"
                  @click="$emit('update:viewMode', 'list')"
                >
                  <i class="bi bi-list"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Mobile Search Bar (expandable) -->
          <div
            v-if="showSearch && showMobileSearchInput"
            class="mt-3 md:hidden"
          >
            <div class="relative">
              <input
                type="text"
                :value="searchQuery"
                @input="$emit('update:searchQuery', $event.target.value)"
                ref="mobileSearchInput"
                class="search-input bg-white/20 text-white placeholder-white/70 rounded-lg pl-10 pr-10 py-2 w-full focus:outline-none focus:ring-2 focus:ring-audinary"
                :placeholder="searchPlaceholder"
              />
              <i
                class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-white/60 pointer-events-none z-10"
              ></i>
              <button
                v-if="searchQuery"
                @click="$emit('update:searchQuery', '')"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-white/60 hover:text-white z-10"
                type="button"
                aria-label="Clear search"
              >
                <i class="bi bi-x"></i>
              </button>
            </div>
          </div>

          <!-- Filter Panel Slot -->
          <div v-if="filtersOpen && showFilter" class="mt-3">
            <slot name="filters"></slot>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  showSearch: {
    type: Boolean,
    default: true,
  },
  searchQuery: {
    type: String,
    default: "",
  },
  searchPlaceholder: {
    type: String,
    default: "",
  },
  showFilter: {
    type: Boolean,
    default: true,
  },
  filtersOpen: {
    type: Boolean,
    default: false,
  },
  filterLabel: {
    type: String,
    default: "",
  },
  activeFiltersCount: {
    type: Number,
    default: 0,
  },
  showViewToggle: {
    type: Boolean,
    default: true,
  },
  viewMode: {
    type: String,
    default: "grid",
    validator: (value) => ["grid", "list"].includes(value),
  },
});

// Computed values for i18n defaults
const searchPlaceholder = computed(
  () => props.searchPlaceholder || t("common.search") + "...",
);

const filterLabel = computed(() => props.filterLabel || t("common.filter"));

defineEmits(["update:searchQuery", "update:viewMode", "toggle-filters"]);

const showMobileSearchInput = ref(false);
const mobileSearchInput = ref(null);

const toggleMobileSearch = async () => {
  showMobileSearchInput.value = !showMobileSearchInput.value;
  if (showMobileSearchInput.value) {
    await nextTick();
    mobileSearchInput.value?.focus();
  }
};
</script>

<style scoped>
.search-input::placeholder {
  color: rgba(255, 255, 255, 0.7);
}
</style>
