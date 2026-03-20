<template>
  <div
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="$emit('close')"
  >
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-sm mx-4 shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-white flex items-center gap-2">
          <i class="bi bi-shield-lock"></i>
          {{ $t("shares.password_required") }}
        </h2>
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-white transition-colors"
        >
          <i class="bi bi-x-lg text-xl"></i>
        </button>
      </div>

      <!-- Info -->
      <div class="mb-6 text-center">
        <div class="mb-4">
          <i class="bi bi-lock text-4xl text-white/40"></i>
        </div>
        <p class="text-white/80 text-sm">
          {{ $t("shares.password_description") }}
        </p>
      </div>

      <!-- Form -->
      <form @submit.prevent="verifyPassword" class="space-y-4">
        <!-- Password Input -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">
            {{ $t("shares.password") }}
          </label>
          <input
            ref="passwordInput"
            v-model="password"
            type="password"
            :placeholder="$t('shares.enter_password')"
            class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:border-audinary focus:ring-1 focus:ring-audinary"
            required
            :class="{ 'border-red-500': errorMessage }"
          />
        </div>

        <!-- Error Message -->
        <div
          v-if="errorMessage"
          class="text-red-400 text-sm bg-red-900/20 border border-red-700/30 rounded-lg p-3 flex items-center gap-2"
        >
          <i class="bi bi-exclamation-triangle"></i>
          {{ errorMessage }}
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 pt-4">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-gray-300 hover:text-white transition-colors"
          >
            {{ $t("common.cancel") }}
          </button>
          <button
            type="submit"
            :disabled="isLoading || !password.trim()"
            class="bg-audinary hover:bg-audinary/90 disabled:opacity-50 disabled:cursor-not-allowed text-black font-semibold px-6 py-2 rounded-lg transition-all flex items-center gap-2"
          >
            <i v-if="isLoading" class="bi bi-hourglass animate-spin"></i>
            <i v-else class="bi bi-unlock"></i>
            {{ isLoading ? $t("common.verifying") : $t("shares.unlock") }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const emit = defineEmits(["close", "password-verified"]);

const props = defineProps({
  shareUuid: {
    type: String,
    required: true,
  },
});

// State
const password = ref("");
const isLoading = ref(false);
const errorMessage = ref("");
const passwordInput = ref(null);

async function verifyPassword() {
  if (isLoading.value || !password.value.trim()) return;

  isLoading.value = true;
  errorMessage.value = "";

  try {
    const response = await fetch(`/api/share/${props.shareUuid}/verify`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        password: password.value,
      }),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      if (response.status === 403) {
        errorMessage.value = t("shares.invalid_password");
      } else {
        errorMessage.value = data.error || t("shares.verification_failed");
      }

      // Clear password on error
      password.value = "";

      // Refocus input
      await nextTick();
      if (passwordInput.value) {
        passwordInput.value.focus();
      }

      return;
    }

    // Password verified successfully
    emit("password-verified", password.value);
  } catch (error) {
    console.error("Error verifying password:", error);
    errorMessage.value = t("shares.verification_error");
  } finally {
    isLoading.value = false;
  }
}

// Auto-focus password input when modal opens
onMounted(async () => {
  await nextTick();
  if (passwordInput.value) {
    passwordInput.value.focus();
  }
});
</script>
