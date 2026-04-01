<template>
  <div class="admin-playlists flex flex-col h-full p-0 m-0">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-white mb-1">
          <i class="bi bi-lightning-charge mr-2"></i
          >{{ $t("admin.smartPlaylist.title") }}
        </h3>
        <p class="text-gray-400 mb-0">
          {{ $t("admin.smartPlaylist.subtitle") }}
        </p>
      </div>
      <button
        v-if="!showForm"
        class="inline-flex items-center gap-2 px-4 py-2 bg-audinary hover:bg-audinary/90 text-black rounded-lg transition-colors"
        @click="openCreateForm"
      >
        <i class="bi bi-plus-lg"></i>
        {{ $t("admin.smartPlaylist.new") }}
      </button>
    </div>

    <!-- Create/Edit Form -->
    <div
      v-if="showForm"
      class="bg-white/5 border border-white/10 rounded-lg mb-4"
    >
      <div
        class="px-4 py-3 border-b border-white/10 flex items-center justify-between"
      >
        <h5 class="mb-0">
          <i class="bi bi-lightning-charge mr-2"></i>
          {{
            editingId
              ? $t("admin.smartPlaylist.edit")
              : $t("admin.smartPlaylist.new")
          }}
        </h5>
        <button
          class="text-gray-400 hover:text-white transition-colors"
          @click="closeForm"
        >
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="p-4">
        <!-- Name -->
        <div class="mb-4">
          <label class="block text-sm text-gray-300 mb-2">{{
            $t("admin.smartPlaylist.name")
          }}</label>
          <input
            type="text"
            class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
            v-model="form.name"
            :placeholder="$t('admin.smartPlaylist.namePlaceholder')"
          />
        </div>

        <!-- Description -->
        <div class="mb-4">
          <label class="block text-sm text-gray-300 mb-2">{{
            $t("admin.smartPlaylist.description")
          }}</label>
          <input
            type="text"
            class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
            v-model="form.description"
            :placeholder="$t('admin.smartPlaylist.descriptionPlaceholder')"
          />
        </div>

        <!-- Match Type -->
        <div class="mb-4">
          <label class="block text-sm text-gray-300 mb-2">{{
            $t("admin.smartPlaylist.matchType")
          }}</label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                v-model="form.match"
                value="all"
                class="form-radio text-audinary"
              />
              <span class="text-white">{{
                $t("admin.smartPlaylist.matchAll")
              }}</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                v-model="form.match"
                value="any"
                class="form-radio text-audinary"
              />
              <span class="text-white">{{
                $t("admin.smartPlaylist.matchAny")
              }}</span>
            </label>
          </div>
        </div>

        <!-- Rules -->
        <div class="mb-4">
          <label class="block text-sm text-gray-300 mb-2">{{
            $t("admin.smartPlaylist.rules")
          }}</label>
          <div class="space-y-2">
            <div
              v-for="(rule, index) in form.conditions"
              :key="index"
              class="flex items-center gap-2 bg-white/5 border border-white/10 rounded p-3"
            >
              <!-- Field -->
              <select
                v-model="rule.field"
                class="text-sm min-w-[130px]"
                @change="onFieldChange(index)"
              >
                <option
                  v-for="f in fieldOptions"
                  :key="f.value"
                  :value="f.value"
                >
                  {{ $t("admin.smartPlaylist.fields." + f.value) }}
                </option>
              </select>

              <!-- Operator -->
              <select
                v-if="getOperators(rule.field).length > 1"
                v-model="rule.operator"
                class="text-sm min-w-[120px]"
              >
                <option
                  v-for="op in getOperators(rule.field)"
                  :key="op.value"
                  :value="op.value"
                >
                  {{ $t("admin.smartPlaylist.operators." + op.value) }}
                </option>
              </select>

              <!-- Value Input -->
              <template v-if="rule.field === 'genre'">
                <select v-model="rule.value" class="text-sm flex-1">
                  <option value="" disabled>
                    {{ $t("admin.smartPlaylist.values.selectGenre") }}
                  </option>
                  <option v-for="g in genres" :key="g.name" :value="g.name">
                    {{ g.name }} ({{ g.track_count }})
                  </option>
                </select>
              </template>

              <template v-else-if="rule.field === 'decade'">
                <select v-model="rule.value" class="text-sm flex-1">
                  <option value="" disabled>
                    {{ $t("admin.smartPlaylist.values.selectDecade") }}
                  </option>
                  <option
                    v-for="d in decades"
                    :key="d.start_year"
                    :value="d.start_year"
                  >
                    {{ d.decade }} ({{ d.album_count }}
                    {{ $t("admin.smartPlaylist.values.albums") }})
                  </option>
                </select>
              </template>

              <template
                v-else-if="rule.field === 'year' && rule.operator === 'between'"
              >
                <input
                  type="number"
                  v-model.number="rule.value[0]"
                  class="bg-white/10 text-white border border-white/20 rounded px-2 py-1.5 text-sm w-24"
                  :placeholder="$t('admin.smartPlaylist.values.from')"
                />
                <span class="text-gray-400">–</span>
                <input
                  type="number"
                  v-model.number="rule.value[1]"
                  class="bg-white/10 text-white border border-white/20 rounded px-2 py-1.5 text-sm w-24"
                  :placeholder="$t('admin.smartPlaylist.values.to')"
                />
              </template>

              <template v-else-if="rule.field === 'year'">
                <input
                  type="number"
                  v-model.number="rule.value"
                  class="bg-white/10 text-white border border-white/20 rounded px-2 py-1.5 text-sm flex-1"
                  :placeholder="
                    $t('admin.smartPlaylist.values.yearPlaceholder')
                  "
                />
              </template>

              <template v-else-if="rule.field === 'artist'">
                <input
                  type="text"
                  v-model="rule.value"
                  class="bg-white/10 text-white border border-white/20 rounded px-2 py-1.5 text-sm flex-1"
                  :placeholder="
                    $t('admin.smartPlaylist.values.artistPlaceholder')
                  "
                />
              </template>

              <template v-else-if="rule.field === 'is_favorite'">
                <span class="text-audinary text-sm">{{
                  $t("admin.smartPlaylist.values.onlyFavorites")
                }}</span>
              </template>

              <template v-else-if="rule.field === 'last_played'">
                <input
                  v-if="rule.operator !== 'never'"
                  type="number"
                  v-model.number="rule.value"
                  class="bg-white/10 text-white border border-white/20 rounded px-2 py-1.5 text-sm w-24"
                  placeholder="30"
                  min="1"
                />
                <span
                  v-if="rule.operator !== 'never'"
                  class="text-gray-400 text-sm"
                  >{{ $t("admin.smartPlaylist.values.days") }}</span
                >
              </template>

              <template v-else-if="rule.field === 'recently_added'">
                <input
                  type="number"
                  v-model.number="rule.value"
                  class="bg-white/10 text-white border border-white/20 rounded px-2 py-1.5 text-sm w-24"
                  placeholder="30"
                  min="1"
                />
                <span class="text-gray-400 text-sm">{{
                  $t("admin.smartPlaylist.values.days")
                }}</span>
              </template>

              <template v-else-if="rule.field === 'duration'">
                <input
                  type="number"
                  v-model.number="rule.value"
                  class="bg-white/10 text-white border border-white/20 rounded px-2 py-1.5 text-sm w-24"
                  placeholder="300"
                  min="1"
                />
                <span class="text-gray-400 text-sm">{{
                  $t("admin.smartPlaylist.values.seconds")
                }}</span>
              </template>

              <!-- Remove Rule Button -->
              <button
                class="text-red-400 hover:text-red-300 p-1 transition-colors"
                @click="removeRule(index)"
                :disabled="form.conditions.length <= 1"
              >
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>

          <button
            class="mt-2 inline-flex items-center gap-1 text-sm text-audinary hover:text-audinary/80 transition-colors"
            @click="addRule"
          >
            <i class="bi bi-plus-circle"></i>
            {{ $t("admin.smartPlaylist.addRule") }}
          </button>
        </div>

        <!-- Sort & Limit -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-sm text-gray-300 mb-2">{{
              $t("admin.smartPlaylist.sorting")
            }}</label>
            <select v-model="form.smart_sort_by" class="text-sm w-full">
              <option value="">
                {{ $t("admin.smartPlaylist.sortDefault") }}
              </option>
              <option v-for="s in sortOptions" :key="s" :value="s">
                {{ $t("admin.smartPlaylist.fields." + s) }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-300 mb-2">{{
              $t("admin.smartPlaylist.sortDirection")
            }}</label>
            <select v-model="form.smart_sort_direction" class="text-sm w-full">
              <option value="asc">{{ $t("common.ascending") }}</option>
              <option value="desc">{{ $t("common.descending") }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-300 mb-2">{{
              $t("admin.smartPlaylist.maxSongs")
            }}</label>
            <input
              type="number"
              v-model.number="form.smart_limit"
              class="bg-white/10 text-white border border-white/20 rounded px-2 py-1.5 text-sm w-full"
              placeholder="250"
              min="1"
              max="250"
            />
          </div>
        </div>

        <!-- Preview -->
        <div
          v-if="previewStats !== null"
          class="mb-4 bg-white/5 border border-white/10 rounded p-3 text-sm"
        >
          <i class="bi bi-eye mr-1 text-audinary"></i>
          <span class="text-white">{{
            $t("admin.smartPlaylist.previewLabel")
          }}</span>
          <span class="text-audinary font-semibold ml-1">{{
            previewStats.song_count
          }}</span>
          <span class="text-gray-400"> {{ $t("common.songs") }}</span>
          <span class="text-gray-400 ml-2"
            >({{ formatDuration(previewStats.duration) }})</span
          >
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3">
          <button
            class="px-4 py-2 bg-audinary hover:bg-audinary/90 text-black rounded-lg transition-colors disabled:opacity-50"
            @click="savePlaylist"
            :disabled="!canSave || isSaving"
          >
            <span
              v-if="isSaving"
              class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"
            ></span>
            {{
              editingId
                ? $t("admin.smartPlaylist.save")
                : $t("admin.smartPlaylist.create")
            }}
          </button>
          <button
            class="px-4 py-2 text-sm text-audinary hover:bg-white/10 rounded-lg transition-colors"
            @click="loadPreview"
            :disabled="!hasValidRules"
          >
            <i class="bi bi-eye mr-1"></i>
            {{ $t("admin.smartPlaylist.preview") }}
          </button>
          <button
            class="px-4 py-2 text-sm text-gray-400 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
            @click="closeForm"
          >
            {{ $t("admin.smartPlaylist.cancel") }}
          </button>
        </div>
      </div>
    </div>

    <!-- Playlists List -->
    <div v-if="!showForm" class="space-y-3">
      <div v-if="loading" class="text-center py-8">
        <div
          class="w-8 h-8 border-4 border-t-transparent border-audinary rounded-full animate-spin mx-auto"
        ></div>
        <p class="mt-3 text-gray-400">
          {{ $t("admin.smartPlaylist.loading") }}
        </p>
      </div>

      <div
        v-else-if="playlists.length === 0"
        class="bg-white/5 border border-white/10 rounded-lg p-8 text-center"
      >
        <i class="bi bi-lightning-charge text-gray-500 text-6xl mb-3"></i>
        <h4 class="text-white mb-2">
          {{ $t("admin.smartPlaylist.noPlaylists") }}
        </h4>
        <p class="text-gray-400 mb-4">
          {{ $t("admin.smartPlaylist.noPlaylistsDesc") }}
        </p>
        <button
          class="px-4 py-2 bg-audinary hover:bg-audinary/90 text-black rounded-lg transition-colors"
          @click="openCreateForm"
        >
          <i class="bi bi-plus-lg mr-1"></i>
          {{ $t("admin.smartPlaylist.createFirst") }}
        </button>
      </div>

      <div
        v-for="playlist in playlists"
        :key="playlist.id"
        class="bg-white/5 border border-white/10 rounded-lg p-4 hover:bg-white/8 transition-colors"
      >
        <div class="flex items-center justify-between">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <i class="bi bi-lightning-charge text-audinary"></i>
              <h5 class="text-white font-semibold truncate mb-0">
                {{ playlist.name }}
              </h5>
              <span
                class="text-xs bg-audinary/20 text-audinary px-2 py-0.5 rounded-full"
                >Smart</span
              >
            </div>
            <p
              v-if="playlist.description"
              class="text-gray-400 text-sm mb-1 truncate"
            >
              {{ playlist.description }}
            </p>
            <div class="flex items-center gap-4 text-sm text-gray-400">
              <span>
                <i class="bi bi-music-note mr-1"></i>
                {{ playlist.song_count || 0 }} {{ $t("common.songs") }}
              </span>
              <span v-if="playlist.duration">
                <i class="bi bi-clock mr-1"></i>
                {{ formatDuration(playlist.duration) }}
              </span>
              <span v-if="playlist.rules && playlist.rules.conditions">
                <i class="bi bi-funnel mr-1"></i>
                {{ playlist.rules.conditions.length }}
                {{
                  playlist.rules.conditions.length === 1
                    ? $t("admin.smartPlaylist.rules")
                    : $t("admin.smartPlaylist.rules")
                }}
                ({{
                  playlist.rules.match === "any"
                    ? $t("admin.smartPlaylist.summary.or")
                    : $t("admin.smartPlaylist.summary.and")
                }})
              </span>
            </div>
            <!-- Rules Summary -->
            <div
              v-if="playlist.rules && playlist.rules.conditions"
              class="flex flex-wrap gap-1 mt-2"
            >
              <span
                v-for="(cond, i) in playlist.rules.conditions"
                :key="i"
                class="text-xs bg-white/5 border border-white/10 text-gray-300 px-2 py-0.5 rounded-full"
              >
                {{ formatCondition(cond) }}
              </span>
            </div>
          </div>
          <div class="flex items-center gap-2 ml-4">
            <button
              class="p-2 text-gray-400 hover:text-white hover:bg-white/10 rounded transition-colors"
              @click="editPlaylist(playlist)"
              :title="$t('common.edit')"
            >
              <i class="bi bi-pencil"></i>
            </button>
            <button
              class="p-2 text-gray-400 hover:text-red-400 hover:bg-white/10 rounded transition-colors"
              @click="confirmDelete(playlist)"
              :title="$t('common.delete')"
            >
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
      @click="showDeleteConfirm = false"
    >
      <div
        class="w-full max-w-md bg-gray-900 border border-white/10 rounded-lg p-6"
        @click.stop
      >
        <h5 class="text-white mb-3">
          {{ $t("admin.smartPlaylist.deleteConfirm") }}
        </h5>
        <p class="text-gray-400 mb-4">
          {{
            $t("admin.smartPlaylist.deleteMessage", {
              name: playlistToDelete?.name,
            })
          }}
        </p>
        <div class="flex justify-end gap-3">
          <button
            class="px-4 py-2 text-sm text-gray-400 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
            @click="showDeleteConfirm = false"
          >
            {{ $t("admin.smartPlaylist.cancel") }}
          </button>
          <button
            class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors"
            @click="deletePlaylist"
          >
            {{ $t("common.delete") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "@/stores/auth";
import { useAlertStore } from "@/stores/alert";
import { useApiStore } from "@/stores/api";

const { t } = useI18n();
const authStore = useAuthStore();
const alertStore = useAlertStore();
const apiStore = useApiStore();

const loading = ref(false);
const isSaving = ref(false);
const showForm = ref(false);
const editingId = ref(null);
const playlists = ref([]);
const genres = ref([]);
const decades = ref([]);
const previewStats = ref(null);
const showDeleteConfirm = ref(false);
const playlistToDelete = ref(null);

const fieldOptions = [
  { value: "genre" },
  { value: "year" },
  { value: "decade" },
  { value: "artist" },
  { value: "is_favorite" },
  { value: "last_played" },
  { value: "recently_added" },
  { value: "duration" },
];

const sortOptions = [
  "title",
  "artist",
  "album",
  "year",
  "added",
  "last_played",
  "duration",
  "random",
];

const defaultRule = () => ({ field: "genre", operator: "equals", value: "" });

const form = ref({
  name: "",
  description: "",
  match: "all",
  conditions: [defaultRule()],
  smart_sort_by: "",
  smart_sort_direction: "asc",
  smart_limit: null,
});

const operatorMap = {
  genre: ["equals", "contains"],
  year: ["equals", "between", "greater_than", "less_than"],
  decade: ["equals"],
  artist: ["contains", "equals"],
  is_favorite: ["equals"],
  last_played: ["within_days", "never"],
  recently_added: ["within_days"],
  duration: ["less_than", "greater_than"],
};

function getOperators(field) {
  const ops = operatorMap[field] || ["equals"];
  return ops.map((value) => ({ value }));
}

function onFieldChange(index) {
  const rule = form.value.conditions[index];
  const ops = getOperators(rule.field);
  rule.operator = ops[0].value;

  if (rule.field === "is_favorite") {
    rule.value = true;
  } else if (rule.field === "year" && rule.operator === "between") {
    rule.value = [1980, 2000];
  } else {
    rule.value = "";
  }
}

function addRule() {
  form.value.conditions.push(defaultRule());
}

function removeRule(index) {
  if (form.value.conditions.length > 1) {
    form.value.conditions.splice(index, 1);
  }
}

const hasValidRules = computed(() => {
  return form.value.conditions.some((r) => {
    if (r.field === "is_favorite") return true;
    if (r.field === "last_played" && r.operator === "never") return true;
    if (r.field === "year" && r.operator === "between") {
      return Array.isArray(r.value) && r.value[0] && r.value[1];
    }
    return r.value !== "" && r.value !== null && r.value !== undefined;
  });
});

const canSave = computed(() => {
  return form.value.name.trim() && hasValidRules.value;
});

function formatDuration(seconds) {
  if (!seconds) return "0:00";
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  if (h > 0)
    return `${h}:${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
  return `${m}:${String(s).padStart(2, "0")}`;
}

function formatCondition(cond) {
  const field = t("admin.smartPlaylist.fields." + cond.field);
  if (cond.field === "is_favorite")
    return t("admin.smartPlaylist.summary.onlyFavorites");
  if (cond.field === "last_played" && cond.operator === "never")
    return t("admin.smartPlaylist.summary.neverPlayed");
  if (cond.field === "last_played")
    return t("admin.smartPlaylist.summary.playedInDays", { days: cond.value });
  if (cond.field === "recently_added")
    return t("admin.smartPlaylist.summary.addedInDays", { days: cond.value });
  if (cond.field === "decade") return `${cond.value}er`;
  if (
    cond.field === "year" &&
    cond.operator === "between" &&
    Array.isArray(cond.value)
  ) {
    return `${cond.value[0]}–${cond.value[1]}`;
  }
  if (cond.field === "duration") {
    const op = cond.operator === "less_than" ? "<" : ">";
    return t("admin.smartPlaylist.summary.durationOp", {
      op,
      min: Math.floor(cond.value / 60),
    });
  }
  const op = t("admin.smartPlaylist.operators." + cond.operator);
  return `${field} ${op} "${cond.value}"`;
}

function openCreateForm() {
  editingId.value = null;
  form.value = {
    name: "",
    description: "",
    match: "all",
    conditions: [defaultRule()],
    smart_sort_by: "",
    smart_sort_direction: "asc",
    smart_limit: null,
  };
  previewStats.value = null;
  showForm.value = true;
}

function editPlaylist(playlist) {
  editingId.value = playlist.id;
  const rules = playlist.rules || { match: "all", conditions: [defaultRule()] };

  // Deep clone conditions to prevent reactivity issues
  const conditions = (rules.conditions || []).map((c) => {
    const clone = { ...c };
    if (Array.isArray(c.value)) clone.value = [...c.value];
    return clone;
  });

  form.value = {
    name: playlist.name,
    description: playlist.description || "",
    match: rules.match || "all",
    conditions: conditions.length > 0 ? conditions : [defaultRule()],
    smart_sort_by: playlist.smart_sort_by || "",
    smart_sort_direction: playlist.smart_sort_direction || "asc",
    smart_limit: playlist.smart_limit || null,
  };
  previewStats.value = null;
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
  editingId.value = null;
  previewStats.value = null;
}

function buildRulesPayload() {
  return {
    match: form.value.match,
    conditions: form.value.conditions.filter((c) => {
      if (c.field === "is_favorite") return true;
      if (c.field === "last_played" && c.operator === "never") return true;
      return c.value !== "" && c.value !== null && c.value !== undefined;
    }),
  };
}

async function loadPreview() {
  try {
    const rules = buildRulesPayload();
    const res = await fetch("/api/admin/smart-playlists/preview", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ rules, smart_limit: form.value.smart_limit }),
    });
    const data = await res.json();
    if (data.success) {
      previewStats.value = {
        song_count: data.song_count,
        duration: data.duration,
      };
    }
  } catch (e) {
    console.error("Preview error:", e);
  }
}

async function savePlaylist() {
  if (!canSave.value || isSaving.value) return;

  isSaving.value = true;
  try {
    const rules = buildRulesPayload();
    const payload = {
      name: form.value.name.trim(),
      description: form.value.description.trim() || null,
      rules,
      smart_sort_by: form.value.smart_sort_by || null,
      smart_sort_direction: form.value.smart_sort_direction,
      smart_limit: form.value.smart_limit
        ? Math.min(form.value.smart_limit, 250)
        : null,
    };

    let res;
    if (editingId.value) {
      res = await fetch(`/api/admin/smart-playlists/${editingId.value}`, {
        method: "PUT",
        headers: {
          Authorization: `Bearer ${authStore.token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });
    } else {
      res = await fetch("/api/admin/smart-playlists", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${authStore.token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });
    }

    const data = await res.json();
    if (data.success) {
      alertStore.success(
        editingId.value
          ? t("admin.smartPlaylist.updated")
          : t("admin.smartPlaylist.created"),
      );
      closeForm();
      await loadPlaylists();
    } else {
      alertStore.error(data.error || t("admin.smartPlaylist.saveError"));
    }
  } catch (e) {
    console.error("Save error:", e);
    alertStore.error(t("admin.smartPlaylist.saveError"));
  } finally {
    isSaving.value = false;
  }
}

function confirmDelete(playlist) {
  playlistToDelete.value = playlist;
  showDeleteConfirm.value = true;
}

async function deletePlaylist() {
  if (!playlistToDelete.value) return;

  try {
    const res = await fetch(
      `/api/admin/smart-playlists/${playlistToDelete.value.id}`,
      {
        method: "DELETE",
        headers: {
          Authorization: `Bearer ${authStore.token}`,
          "Content-Type": "application/json",
        },
      },
    );
    const data = await res.json();
    if (data.success) {
      alertStore.success(t("admin.smartPlaylist.deleted"));
      showDeleteConfirm.value = false;
      playlistToDelete.value = null;
      await loadPlaylists();
    } else {
      alertStore.error(data.error || t("admin.smartPlaylist.deleteError"));
    }
  } catch (e) {
    console.error("Delete error:", e);
    alertStore.error(t("admin.smartPlaylist.deleteError"));
  }
}

async function loadPlaylists() {
  loading.value = true;
  try {
    const res = await fetch("/api/admin/smart-playlists", {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        "Content-Type": "application/json",
      },
    });
    const data = await res.json();
    if (data.success) {
      playlists.value = data.playlists || [];
    }
  } catch (e) {
    console.error("Load error:", e);
  } finally {
    loading.value = false;
  }
}

async function loadGenresAndDecades() {
  try {
    const [genreData, decadeData] = await Promise.all([
      apiStore.loadAllGenres(),
      apiStore.loadAllDecades(),
    ]);
    genres.value = genreData || [];
    decades.value = decadeData || [];
  } catch (e) {
    console.error("Load genres/decades error:", e);
  }
}

onMounted(async () => {
  await Promise.all([loadPlaylists(), loadGenresAndDecades()]);
});
</script>
