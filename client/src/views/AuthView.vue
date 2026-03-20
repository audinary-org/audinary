<template>
  <div
    class="min-h-screen flex flex-col"
    :class="themeStore.backgroundGradient"
  >
    <!-- Language Selector -->
    <div class="absolute top-4 right-4 z-50" data-language-selector>
      <div class="relative">
        <button
          class="mobile-touch-target bg-black/30 backdrop-blur-md border border-white/20 hover:bg-black/50 transition-all duration-200 rounded-lg flex items-center justify-center w-12 h-10"
          type="button"
          @click="toggleLanguageDropdown"
          :title="currentLocale.name"
        >
          <FlagIcon
            v-if="currentLocale.countryCode"
            :countryCode="currentLocale.countryCode"
            size="sm"
            :shadow="false"
          />
          <span v-else class="text-xl text-white/80">{{
            currentLocale.flag
          }}</span>
        </button>
        <div
          v-show="showLanguageDropdown"
          class="absolute right-0 top-full mt-1 bg-black/80 backdrop-blur-md border border-white/10 rounded-lg min-w-[60px] z-50 overflow-hidden"
        >
          <button
            v-for="locale in availableLocales"
            :key="locale.code"
            type="button"
            @click.stop="changeLanguage(locale.code)"
            :class="{ 'bg-white/10': locale.code === currentLanguageCode }"
            class="block w-full text-center py-2 px-3 text-white/80 hover:text-white hover:bg-white/10 transition-colors mobile-touch-target flex items-center justify-center"
            :title="locale.name"
          >
            <FlagIcon
              v-if="locale.countryCode"
              :countryCode="locale.countryCode"
              size="sm"
              :shadow="false"
            />
            <span v-else class="text-xl">{{ locale.flag }}</span>
          </button>
        </div>
      </div>
    </div>

    <div class="flex-1 flex min-h-0">
      <!-- Left side - Background image (hidden on mobile) -->
      <div
        class="hidden lg:flex flex-1 bg-cover bg-center bg-no-repeat relative items-center justify-center overflow-hidden"
        :style="{
          backgroundImage: backgroundImage.startsWith('linear-gradient')
            ? backgroundImage
            : `url('${backgroundImage}')`,
        }"
      ></div>

      <!-- Right side - Auth form -->
      <div
        class="w-full lg:w-96 xl:w-[400px] bg-black/40 backdrop-blur-lg border-l border-white/10 flex items-center justify-center p-6 lg:p-8"
      >
        <div class="w-full max-w-sm">
          <!-- Logo -->
          <div class="flex justify-center mb-8 items-center">
            <div class="flex items-center justify-center" style="min-width: 0">
              <img
                src="/img/audinary-inverse-orange-white.png"
                alt="Audinary Logo"
                class="max-w-full h-auto max-h-[100px] sm:max-h-[150px] md:max-h-[250px] rounded-xl drop-shadow-lg object-contain"
                @error="handleLogoError"
                @load="logoLoaded = true"
              />
              <i
                v-if="!logoLoaded"
                class="bi bi-music-note-beamed text-3xl sm:text-4xl md:text-6xl text-blue-600 drop-shadow-lg"
              ></i>
            </div>
          </div>

          <!-- Title -->
          <h3 class="text-lg text-white/80 mb-8 text-center font-normal">
            {{
              showPasswordReset
                ? $t("auth.resetPassword")
                : showForgotPassword
                  ? $t("auth.forgot_password")
                  : activeTab === "login"
                    ? $t("auth.login")
                    : $t("auth.register")
            }}
          </h3>

          <!-- Error Alert -->
          <div
            v-if="error"
            class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center text-sm"
          >
            <i class="bi bi-exclamation-triangle mr-2"></i>
            {{ error }}
          </div>

          <!-- Auth Status Info -->
          <div
            v-if="authStatus && authStatus.allowAdminRegistration"
            class="bg-cyan-50 border border-cyan-200 text-cyan-700 px-4 py-3 rounded-lg mb-6 flex items-center text-sm"
          >
            <i class="bi bi-info-circle mr-2"></i>
            {{ $t("auth.first_user_admin") }}
          </div>

          <!-- Tab Navigation -->
          <div
            class="flex mb-8 bg-white/10 backdrop-blur-sm rounded-lg p-1"
            v-if="showMainForms && canLogin && canRegister"
          >
            <button
              class="flex-1 py-2.5 px-4 text-sm font-medium rounded-md transition-all duration-200 mobile-touch-target"
              :class="
                activeTab === 'login'
                  ? 'bg-audinary text-black shadow-sm'
                  : 'text-white/80 hover:text-white'
              "
              @click="activeTab = 'login'"
              type="button"
            >
              {{ $t("auth.login") }}
            </button>
            <button
              class="flex-1 py-2.5 px-4 text-sm font-medium rounded-md transition-all duration-200 mobile-touch-target"
              :class="
                activeTab === 'register'
                  ? 'bg-audinary text-black shadow-sm'
                  : 'text-white/80 hover:text-white'
              "
              @click="activeTab = 'register'"
              type="button"
            >
              {{ $t("auth.register") }}
            </button>
          </div>

          <!-- Login Form -->
          <form
            v-if="showMainForms && activeTab === 'login' && canLogin"
            @submit.prevent="handleLogin"
            class="space-y-6"
          >
            <div>
              <label class="block text-sm font-medium text-white/80 mb-2">{{
                $t("auth.username")
              }}</label>
              <input
                v-model="loginForm.username"
                type="text"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-blue-500 transition-colors disabled:bg-gray-50 disabled:opacity-60 text-gray-900 bg-white"
                :placeholder="$t('auth.username_placeholder')"
                required
                :disabled="isLoading"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-white/80 mb-2">{{
                $t("auth.password")
              }}</label>
              <input
                v-model="loginForm.password"
                type="password"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-blue-500 transition-colors disabled:bg-gray-50 disabled:opacity-60 text-gray-900 bg-white"
                :placeholder="$t('auth.password_placeholder')"
                required
                :disabled="isLoading"
              />
            </div>
            <button
              type="submit"
              class="w-full bg-audinary hover:bg-audinary/90 disabled:bg-audinary/60 text-black py-2.5 px-4 rounded-lg font-semibold transition-all duration-200 disabled:cursor-not-allowed hover:shadow-lg mobile-touch-target flex items-center justify-center"
              :disabled="isLoading"
            >
              <span
                v-if="isLoading"
                class="inline-block w-4 h-4 border-2 border-black/30 border-t-black rounded-full animate-spin mr-2"
              ></span>
              {{ $t("auth.login") }}
            </button>

            <!-- Forgot Password Button -->
            <div
              v-if="canShowForgotPassword && !showForgotPassword"
              class="text-center mt-4"
            >
              <button
                type="button"
                class="text-sm text-white/80 hover:text-audinary transition-colors mobile-touch-target py-2"
                @click="showForgotPasswordForm"
              >
                {{ $t("auth.forgot_password") }}
              </button>
            </div>
          </form>

          <!-- Forgot Password Form -->
          <form
            v-if="showForgotPassword"
            @submit.prevent="handleForgotPassword"
            class="space-y-6"
          >
            <div>
              <label class="block text-sm font-medium text-white/80 mb-2">{{
                $t("auth.username_or_email")
              }}</label>
              <input
                v-model="forgotPasswordForm.username_or_email"
                type="text"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-blue-500 transition-colors disabled:bg-gray-50 disabled:opacity-60 text-gray-900 bg-white"
                :placeholder="$t('auth.username_or_email_placeholder')"
                required
                :disabled="forgotPasswordLoading"
              />
            </div>

            <!-- Success Message -->
            <div
              v-if="forgotPasswordMessage"
              class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center text-sm"
            >
              <i class="bi bi-check-circle mr-2"></i>
              {{ forgotPasswordMessage }}
            </div>

            <button
              type="submit"
              class="w-full bg-audinary hover:bg-audinary/90 disabled:bg-audinary/60 text-black py-2.5 px-4 rounded-lg font-semibold transition-all duration-200 disabled:cursor-not-allowed hover:shadow-lg mobile-touch-target flex items-center justify-center"
              :disabled="forgotPasswordLoading"
            >
              <span
                v-if="forgotPasswordLoading"
                class="inline-block w-4 h-4 border-2 border-black/30 border-t-black rounded-full animate-spin mr-2"
              ></span>
              {{ $t("auth.send_reset_email") }}
            </button>

            <button
              type="button"
              class="w-full bg-transparent border border-gray-300 text-white/80 hover:bg-white/20 hover:border-gray-400 py-2.5 px-4 rounded-lg font-medium transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed mobile-touch-target flex items-center justify-center"
              @click="hideForgotPasswordForm"
              :disabled="forgotPasswordLoading"
            >
              {{ $t("auth.back_to_login") }}
            </button>
          </form>

          <!-- Password Reset Form -->
          <div v-if="showPasswordReset">
            <!-- Loading State -->
            <div v-if="isValidatingToken" class="text-center py-8">
              <div
                class="w-10 h-10 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-4"
              ></div>
              <p class="text-white/80">{{ $t("auth.validatingToken") }}</p>
            </div>

            <!-- Invalid Token -->
            <div
              v-else-if="!isValidToken"
              class="bg-red-50 border border-red-200 text-red-700 px-4 py-4 rounded-lg text-sm"
            >
              <i class="bi bi-exclamation-triangle mr-2"></i>
              <div>
                <strong>{{ $t("auth.invalidToken") }}</strong>
                <p class="mt-2 mb-4">{{ $t("auth.tokenExpiredOrInvalid") }}</p>
                <button
                  @click="backToLogin"
                  class="inline-flex items-center px-4 py-2 bg-audinary hover:bg-audinary/90 text-black text-sm font-medium rounded-lg transition-colors mobile-touch-target"
                >
                  {{ $t("auth.backToLogin") }}
                </button>
              </div>
            </div>

            <!-- Reset Password Form -->
            <form
              v-else
              @submit.prevent="handlePasswordReset"
              class="space-y-6"
            >
              <!-- New Password -->
              <div>
                <label
                  for="newPassword"
                  class="block text-sm font-medium text-white/80 mb-2"
                >
                  {{ $t("auth.newPassword") }}
                </label>
                <div class="relative">
                  <input
                    id="newPassword"
                    v-model="passwordResetForm.newPassword"
                    :type="showNewPassword ? 'text' : 'password'"
                    class="w-full px-3 py-2.5 pr-10 border rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-blue-500 transition-colors disabled:bg-gray-50 disabled:opacity-60 text-gray-900 bg-white"
                    :class="
                      passwordResetError
                        ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                        : 'border-gray-300'
                    "
                    :placeholder="$t('auth.newPasswordPlaceholder')"
                    required
                    minlength="8"
                    :disabled="passwordResetLoading || passwordResetSuccess"
                  />
                  <button
                    type="button"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center mobile-touch-target text-gray-400 hover:text-white/80 transition-colors"
                    @click="showNewPassword = !showNewPassword"
                    :disabled="passwordResetLoading || passwordResetSuccess"
                  >
                    <i
                      :class="showNewPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"
                    ></i>
                  </button>
                </div>
              </div>

              <!-- Confirm Password -->
              <div>
                <label
                  for="confirmPassword"
                  class="block text-sm font-medium text-white/80 mb-2"
                >
                  {{ $t("auth.confirmPassword") }}
                </label>
                <input
                  id="confirmPassword"
                  v-model="passwordResetForm.confirmPassword"
                  :type="showNewPassword ? 'text' : 'password'"
                  class="w-full px-3 py-2.5 border rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-blue-500 transition-colors disabled:bg-gray-50 disabled:opacity-60 text-gray-900 bg-white"
                  :class="
                    passwordResetError
                      ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                      : 'border-gray-300'
                  "
                  :placeholder="$t('auth.confirmPasswordPlaceholder')"
                  required
                  :disabled="passwordResetLoading || passwordResetSuccess"
                />
              </div>

              <!-- Error Alert -->
              <div
                v-if="passwordResetError"
                class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center text-sm"
              >
                <i class="bi bi-exclamation-triangle mr-2"></i>
                {{ passwordResetError }}
              </div>

              <!-- Success Alert -->
              <div
                v-if="passwordResetSuccess"
                class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center text-sm"
              >
                <i class="bi bi-check-circle mr-2"></i>
                {{ $t("auth.passwordResetSuccess") }}
              </div>

              <!-- Submit Button -->
              <button
                type="submit"
                class="w-full bg-audinary hover:bg-audinary/90 disabled:bg-audinary/60 text-black py-2.5 px-4 rounded-lg font-semibold transition-all duration-200 disabled:cursor-not-allowed hover:shadow-lg mobile-touch-target flex items-center justify-center"
                :disabled="passwordResetLoading || passwordResetSuccess"
              >
                <span
                  v-if="passwordResetLoading"
                  class="inline-block w-4 h-4 border-2 border-black/30 border-t-black rounded-full animate-spin mr-2"
                ></span>
                {{
                  passwordResetLoading
                    ? $t("common.loading")
                    : $t("auth.resetPassword")
                }}
              </button>

              <!-- Back to Login -->
              <button
                type="button"
                class="w-full bg-transparent border border-gray-300 text-white/80 hover:bg-white/20 hover:border-gray-400 py-2.5 px-4 rounded-lg font-medium transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed mobile-touch-target flex items-center justify-center"
                @click="backToLogin"
                :disabled="passwordResetLoading"
              >
                <i class="bi bi-arrow-left mr-2"></i>
                {{ $t("auth.backToLogin") }}
              </button>
            </form>
          </div>

          <!-- Register Form -->
          <form
            v-if="showMainForms && activeTab === 'register' && canRegister"
            @submit.prevent="handleRegister"
            class="space-y-6"
          >
            <div>
              <label class="block text-sm font-medium text-white/80 mb-2">{{
                $t("auth.username")
              }}</label>
              <input
                v-model="registerForm.username"
                type="text"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-blue-500 transition-colors disabled:bg-gray-50 disabled:opacity-60 text-gray-900 bg-white"
                :placeholder="$t('auth.username_placeholder')"
                required
                :disabled="isLoading"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-white/80 mb-2">{{
                $t("auth.display_name")
              }}</label>
              <input
                v-model="registerForm.display_name"
                type="text"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-blue-500 transition-colors disabled:bg-gray-50 disabled:opacity-60 text-gray-900 bg-white"
                :placeholder="$t('auth.display_name_placeholder')"
                :disabled="isLoading"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-white/80 mb-2">{{
                $t("auth.email")
              }}</label>
              <input
                v-model="registerForm.email"
                type="email"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-blue-500 transition-colors disabled:bg-gray-50 disabled:opacity-60 text-gray-900 bg-white"
                :placeholder="$t('auth.email_placeholder')"
                :disabled="isLoading"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-white/80 mb-2">{{
                $t("auth.password")
              }}</label>
              <input
                v-model="registerForm.password"
                type="password"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-audinary focus:border-blue-500 transition-colors disabled:bg-gray-50 disabled:opacity-60 text-gray-900 bg-white"
                :placeholder="$t('auth.password_placeholder')"
                required
                minlength="6"
                :disabled="isLoading"
              />
              <p class="text-sm text-white/70 mt-1">Minimum 6 characters</p>
            </div>
            <button
              type="submit"
              class="w-full bg-audinary hover:bg-audinary/90 disabled:bg-audinary/60 text-black py-2.5 px-4 rounded-lg font-semibold transition-all duration-200 disabled:cursor-not-allowed hover:shadow-lg mobile-touch-target flex items-center justify-center"
              :disabled="isLoading"
            >
              <span
                v-if="isLoading"
                class="inline-block w-4 h-4 border-2 border-black/30 border-t-black rounded-full animate-spin mr-2"
              ></span>
              {{ $t("auth.register") }}
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <footer
      class="bg-black/30 backdrop-blur-md border-t border-white/10 py-4 mt-auto"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div
          class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0"
        >
          <div class="text-center sm:text-left">
            <small class="text-white/70"> Version {{ appVersion }} </small>
          </div>
          <div class="text-center">
            <small class="text-white/70">
              <i class="bi bi-c-circle mr-1"></i>
              {{ new Date().getFullYear() }} Audinary
            </small>
          </div>
          <div class="flex space-x-4">
            <a
              href="https://github.com/audinary-org/audinary"
              class="mobile-touch-target w-9 h-9 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center text-gray-400 hover:text-white transition-all duration-200"
              target="_blank"
              rel="noopener"
              :title="$t('about.links.github')"
            >
              <i class="bi bi-github text-lg"></i>
            </a>
            <a
              href="https://t.me/audinary_app"
              class="mobile-touch-target w-9 h-9 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center text-gray-400 hover:text-white transition-all duration-200"
              target="_blank"
              rel="noopener"
              :title="$t('about.links.telegram')"
            >
              <i class="bi bi-telegram text-lg"></i>
            </a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "@/stores/auth";
import { useApiStore } from "@/stores/api";
import { useThemeStore } from "@/stores/theme";
import {
  getAvailableLocales,
  setLocale,
  getCurrentLocalePreference,
  getCurrentLocale,
} from "@/i18n";
import FlagIcon from "@/components/common/FlagIcon.vue";

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const apiStore = useApiStore();
const themeStore = useThemeStore();

// State
const activeTab = ref("login");
const authStatus = ref(null);
const error = ref(null);
const availableLocales = getAvailableLocales();
const currentLanguageCode = ref(getCurrentLocalePreference());
const logoLoaded = ref(false);
const showLanguageDropdown = ref(false);
const versionInfo = ref(null);
const backgroundImage = ref("");
const showForgotPassword = ref(false);
const forgotPasswordForm = ref({
  username_or_email: "",
});
const forgotPasswordLoading = ref(false);
const forgotPasswordMessage = ref("");
const appConfig = ref(null);

// Password Reset States
const showPasswordReset = ref(false);
const isValidatingToken = ref(false);
const isValidToken = ref(false);
const resetToken = ref("");
const passwordResetForm = ref({
  newPassword: "",
  confirmPassword: "",
});
const passwordResetLoading = ref(false);
const passwordResetSuccess = ref(false);
const passwordResetError = ref("");
const showNewPassword = ref(false);

const loginForm = ref({
  username: "",
  password: "",
});

const registerForm = ref({
  username: "",
  display_name: "",
  email: "",
  password: "",
});

// Computed
const isLoading = computed(() => authStore.isLoading);
const canRegister = computed(() => {
  return (
    authStatus.value?.registrationAllowed ||
    authStatus.value?.allowAdminRegistration
  );
});

const canLogin = computed(() => {
  // Login nur anzeigen wenn Benutzer vorhanden sind
  return authStatus.value?.allowAdminRegistration === false;
});

const canShowForgotPassword = computed(() => {
  return (
    appConfig.value?.smtp_enabled === true &&
    canLogin.value &&
    !showPasswordReset.value
  );
});

const showMainForms = computed(() => {
  return !showForgotPassword.value && !showPasswordReset.value;
});

const currentLocale = computed(() => {
  return (
    availableLocales.find(
      (locale) => locale.code === currentLanguageCode.value,
    ) || availableLocales[1]
  ); // fallback to English
});

const appVersion = computed(() => {
  // Priorität: API Version > AuthStatus Version > Frontend Version
  if (versionInfo.value?.version) {
    return versionInfo.value.version;
  }

  const backendVersion = authStatus.value?.app_version;
  if (backendVersion && backendVersion !== "unknown") {
    return backendVersion;
  }

  return `Frontend v${__APP_VERSION__}`;
});

// Methods
async function handleLogin() {
  error.value = null;

  try {
    await authStore.login(loginForm.value);

    // Wait for authentication to be fully established before navigating
    if (
      authStore.isAuthenticated &&
      authStore.isInitialized &&
      !authStore.isLoading
    ) {
      router.push("/");
    } else {
      console.error("Login succeeded but authentication state not ready");
      error.value = "Authentication state error. Please try again.";
    }
  } catch (err) {
    error.value = err.message;
  }
}

async function handleRegister() {
  error.value = null;

  try {
    await authStore.register(registerForm.value);

    // Wait for authentication to be fully established before navigating
    if (
      authStore.isAuthenticated &&
      authStore.isInitialized &&
      !authStore.isLoading
    ) {
      router.push("/");
    } else {
      console.error(
        "Registration succeeded but authentication state not ready",
      );
      error.value = "Authentication state error. Please try again.";
    }
  } catch (err) {
    error.value = err.message;
  }
}

async function handleForgotPassword() {
  if (!forgotPasswordForm.value.username_or_email.trim()) {
    error.value = "Please enter your username or email address";
    return;
  }

  forgotPasswordLoading.value = true;
  forgotPasswordMessage.value = "";
  error.value = null;

  try {
    const response = await apiStore.post("/api/auth/forgot-password", {
      username_or_email: forgotPasswordForm.value.username_or_email.trim(),
    });

    // Handle successful response - check both response.message and response.data.message
    if (response.success) {
      forgotPasswordMessage.value =
        response.message ||
        response.data?.message ||
        "If an account with this username or email exists, you will receive a password reset email.";
      forgotPasswordForm.value.username_or_email = "";
    } else {
      error.value =
        response.message ||
        response.data?.message ||
        "An error occurred. Please try again later.";
    }
  } catch (err) {
    if (err.response?.status === 429) {
      error.value = "Too many password reset requests. Please try again later.";
    } else {
      error.value =
        err.response?.data?.message ||
        "An error occurred. Please try again later.";
    }
  } finally {
    forgotPasswordLoading.value = false;
  }
}

function showForgotPasswordForm() {
  showForgotPassword.value = true;
  error.value = null;
  forgotPasswordMessage.value = "";
}

function hideForgotPasswordForm() {
  showForgotPassword.value = false;
  forgotPasswordForm.value.username_or_email = "";
  forgotPasswordMessage.value = "";
  error.value = null;
}

// Password Reset Functions
function showPasswordResetForm(token) {
  showPasswordReset.value = true;
  showForgotPassword.value = false;
  resetToken.value = token;
  isValidatingToken.value = true;
  validateResetToken();
}

function backToLogin() {
  showPasswordReset.value = false;
  showForgotPassword.value = false;
  isValidToken.value = false;
  passwordResetSuccess.value = false;
  passwordResetError.value = "";
  passwordResetForm.value.newPassword = "";
  passwordResetForm.value.confirmPassword = "";
  resetToken.value = "";
  activeTab.value = "login";
}

async function validateResetToken() {
  if (!resetToken.value) {
    isValidToken.value = false;
    isValidatingToken.value = false;
    return;
  }

  try {
    const response = await apiStore.get(
      `/api/auth/validate-reset-token?token=${encodeURIComponent(resetToken.value)}`,
    );

    if (response.success) {
      isValidToken.value = true;
    } else {
      isValidToken.value = false;
      passwordResetError.value = response.message || t("auth.invalidToken");
    }
  } catch (err) {
    console.error("Token validation error:", err);
    isValidToken.value = false;
    passwordResetError.value = t("auth.tokenValidationFailed");
  } finally {
    isValidatingToken.value = false;
  }
}

function validatePasswordResetForm() {
  passwordResetError.value = "";

  if (!passwordResetForm.value.newPassword) {
    passwordResetError.value = t("auth.newPassword") + " ist erforderlich";
    return false;
  }

  if (passwordResetForm.value.newPassword.length < 8) {
    passwordResetError.value =
      t("auth.newPassword") + " muss mindestens 8 Zeichen lang sein";
    return false;
  }

  if (!passwordResetForm.value.confirmPassword) {
    passwordResetError.value = t("auth.confirmPassword") + " ist erforderlich";
    return false;
  }

  if (
    passwordResetForm.value.newPassword !==
    passwordResetForm.value.confirmPassword
  ) {
    passwordResetError.value = t("auth.passwordsDontMatch");
    return false;
  }

  return true;
}

async function handlePasswordReset() {
  if (!validatePasswordResetForm()) {
    return;
  }

  passwordResetLoading.value = true;
  passwordResetError.value = "";

  try {
    const response = await apiStore.post("/api/auth/reset-password", {
      token: resetToken.value,
      new_password: passwordResetForm.value.newPassword,
    });

    if (response.success) {
      passwordResetSuccess.value = true;
      // Redirect to login after 3 seconds
      setTimeout(() => {
        backToLogin();
      }, 3000);
    } else {
      passwordResetError.value =
        response.message || t("auth.resetPasswordFailed");
    }
  } catch (err) {
    console.error("Password reset error:", err);
    passwordResetError.value = t("auth.resetPasswordFailed");
  } finally {
    passwordResetLoading.value = false;
  }
}

async function loadAuthStatus() {
  try {
    authStatus.value = await authStore.checkAuthStatus();

    // Bestimme den Standard-Tab basierend auf verfügbaren Optionen
    if (authStatus.value?.allowAdminRegistration) {
      // Wenn keine Benutzer vorhanden sind, zeige nur Register
      activeTab.value = "register";
    } else if (authStatus.value?.registrationAllowed) {
      // Wenn Registrierung erlaubt ist, standardmäßig Login anzeigen
      activeTab.value = "login";
    } else {
      // Nur Login verfügbar
      activeTab.value = "login";
    }
  } catch (err) {
    console.error("Failed to load auth status:", err);
  }
}

function changeLanguage(languageCode) {
  currentLanguageCode.value = languageCode;
  setLocale(languageCode);
  showLanguageDropdown.value = false; // Dropdown schließen nach Auswahl
}

function handleLogoError() {
  logoLoaded.value = false;
  console.warn("Logo could not be loaded, falling back to icon");
}

function toggleLanguageDropdown() {
  showLanguageDropdown.value = !showLanguageDropdown.value;
}

// Click outside handler
function handleClickOutside(event) {
  const languageSelector =
    event.target.closest(".language-selector") ||
    event.target.closest("[data-language-selector]");
  if (!languageSelector) {
    showLanguageDropdown.value = false;
  }
}

// Load version from API
async function loadVersion() {
  try {
    const response = await apiStore.get("/api/version");
    versionInfo.value = response.data || response;
  } catch (error) {
    console.warn("Could not load version from API, using fallback:", error);
    // Fallback wird über computed property gehandhabt
  }
}

// Load app configuration
async function loadAppConfig() {
  try {
    const response = await apiStore.get("/api/config");
    appConfig.value = response.data || response;
  } catch (error) {
    console.warn("Could not load app config from API:", error);

    // Fallback: Direct fetch
    try {
      const directResponse = await fetch("/api/config");
      const data = await directResponse.json();
      appConfig.value = data;
    } catch (fetchError) {
      console.warn("Direct fetch also failed:", fetchError);
    }
  }
}

// Load random background image
async function loadRandomBackground() {
  try {
    // Get random background image directly from API
    const response = await fetch("/api/background-images");

    if (response.ok) {
      // Create blob URL for the image
      const blob = await response.blob();
      backgroundImage.value = URL.createObjectURL(blob);
    } else {
      // API returned error (404 = no images, 500 = server error)
      console.warn("No background images available, using gradient fallback");
      backgroundImage.value =
        "linear-gradient(135deg, #667eea 0%, #764ba2 100%)";
    }
  } catch (error) {
    console.warn("Could not load background image from API:", error);
    backgroundImage.value = "linear-gradient(135deg, #667eea 0%, #764ba2 100%)";
  }
}

// Lifecycle
onMounted(() => {
  // Redirect if already authenticated
  if (authStore.isAuthenticated) {
    router.push("/");
    return;
  }

  // Check for reset token in URL
  const token = route.query.token;
  if (token) {
    showPasswordResetForm(token);
  }

  loadAuthStatus();
  loadVersion();
  loadAppConfig();
  loadRandomBackground();

  // Add click outside listener
  document.addEventListener("mousedown", handleClickOutside);
});

onUnmounted(() => {
  // Remove event listeners when component is unmounted
  document.removeEventListener("mousedown", handleClickOutside);
});
</script>

<style scoped>
/* Mobile responsive adjustments for the left side background */
@media (max-width: 1023px) {
  .lg\:flex {
    display: none !important;
  }
}

/* Ensure proper mobile keyboard behavior */
@supports (-webkit-touch-callout: none) {
  .mobile-keyboard-aware {
    height: 100vh;
    height: -webkit-fill-available;
  }
}
</style>
