<template>
  <div class="admin-config h-full flex flex-col">
    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <div class="text-center">
        <div
          class="w-10 h-10 border-4 border-t-transparent border-blue-600 rounded-full animate-spin mx-auto"
        ></div>
        <p class="mt-3 text-white">{{ $t("admin.config.loading_config") }}</p>
      </div>
    </div>

    <div v-else class="flex-1 overflow-y-auto p-1">
      <div class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1 lg:flex-grow-2">
          <div class="bg-white/5 border border-white/10 rounded-lg mb-4">
            <div class="px-4 py-3 border-b border-white/10 flex items-center">
              <h5 class="mb-0">
                <i class="bi bi-person-plus mr-2"></i>Benutzer-Registrierung
              </h5>
            </div>
            <div class="p-4">
              <div class="form-switch mb-4">
                <label class="flex items-center gap-3">
                  <input
                    type="checkbox"
                    class="form-checkbox"
                    id="userRegistrationEnabled"
                    v-model="settings.user_registration_enabled"
                  />
                  <span class="text-white"
                    >Benutzer-Registrierung aktiviert</span
                  >
                </label>
                <div class="text-sm text-gray-400">
                  Erlaubt neuen Benutzern, sich selbst zu registrieren.
                </div>
              </div>
            </div>
          </div>

          <!-- Wishlist & Last.fm Configuration -->
          <div class="bg-white/5 border border-white/10 rounded-lg mb-4">
            <div class="px-4 py-3 border-b border-white/10 flex items-center">
              <h5 class="mb-0">
                <i class="bi bi-heart mr-2"></i>Wunschliste & Last.fm
              </h5>
            </div>
            <div class="p-4">
              <div class="form-switch mb-4">
                <label class="flex items-center gap-3">
                  <input
                    type="checkbox"
                    class="form-checkbox"
                    id="wishlistEnabled"
                    v-model="settings.wishlist_enabled"
                  />
                  <span class="text-white">Wunschlisten-Feature aktiviert</span>
                </label>
                <div class="text-sm text-gray-400">
                  Erlaubt Benutzern, Musikwünsche zu erstellen und zu verwalten.
                </div>
              </div>

              <div class="mb-4">
                <label
                  for="lastfmApiKey"
                  class="block text-sm text-gray-300 mb-2"
                  >Last.fm API Key</label
                >
                <input
                  type="text"
                  id="lastfmApiKey"
                  class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-audinary focus:border-transparent"
                  v-model="settings.lastfm_api_key"
                  placeholder="Last.fm API Schlüssel"
                />
                <div class="text-sm text-gray-400 mt-1">
                  API-Schlüssel für Last.fm-Integration. Wird für die Suche nach
                  Künstlern und Alben in der Wünschliste benötigt.
                  <a
                    href="https://www.last.fm/api/account/create"
                    target="_blank"
                    class="text-blue-400 hover:text-blue-300"
                    >API-Schlüssel erstellen</a
                  >
                </div>
              </div>
            </div>
          </div>

          <!-- Music Scanning Configuration card simplified -->
          <div
            class="bg-white/5 border border-white/10 rounded-lg mb-4 hover:shadow-md transition-shadow"
          >
            <div class="px-4 py-3 border-b border-white/10">
              <h5 class="mb-0">
                <i class="bi bi-music-note-list mr-2"></i>Musik Scan
                Konfiguration
              </h5>
            </div>
            <div class="p-4">
              <!-- Tag First Mode -->
              <div class="mb-4">
                <label class="flex items-center gap-3"
                  ><input
                    type="checkbox"
                    class="form-checkbox"
                    id="tagfirstmode"
                    v-model="settings.tag_first_mode"
                  /><span class="text-white"
                    >Tag First Mode aktiviert</span
                  ></label
                >
                <div class="text-sm text-gray-400">
                  Bevorzugt Metadaten-Tags aus der Datei gegenüber
                  Ordnerstrukturen bei der Musikerkennung.
                </div>
              </div>

              <!-- Allowed Extensions -->
              <div class="mb-4">
                <label class="block text-sm text-gray-300 mb-2"
                  >Erlaubte Dateierweiterungen</label
                >
                <div class="flex flex-wrap gap-2 mb-2">
                  <span
                    v-for="(ext, index) in settings.allowed_extensions"
                    :key="`ext-${index}`"
                    class="bg-white/5 border border-white/10 text-blue-400 px-2 py-1 rounded-full text-sm"
                    >{{ ext }}
                    <button
                      type="button"
                      class="ml-2 text-white/80"
                      @click="removeArrayItem('allowed_extensions', index)"
                    >
                      ✕
                    </button></span
                  >
                </div>
                <div class="flex gap-2">
                  <input
                    type="text"
                    class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 flex-1"
                    v-model="newAllowedExtension"
                    placeholder="Neue Erweiterung hinzufügen (z.B. mp3)"
                    @keyup.enter="addAllowedExtension"
                  />
                  <button
                    class="bg-white/5 border border-white/10 text-blue-400 px-3 py-2 rounded hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @click="addAllowedExtension"
                    :disabled="!newAllowedExtension.trim()"
                  >
                    <i class="bi bi-plus"></i>
                  </button>
                </div>
              </div>

              <!-- Cover Names -->
              <div class="mb-4">
                <label class="block text-sm text-gray-300 mb-2"
                  >Cover Dateinamen</label
                >
                <div class="flex flex-wrap gap-2 mb-2">
                  <span
                    v-for="(name, index) in settings.cover_names"
                    :key="`cover-${index}`"
                    class="bg-white/5 border border-white/10 text-emerald-400 px-2 py-1 rounded-full text-sm"
                    >{{ name }}
                    <button
                      type="button"
                      class="ml-2 text-white/80"
                      @click="removeArrayItem('cover_names', index)"
                    >
                      ✕
                    </button></span
                  >
                </div>
                <div class="flex gap-2">
                  <input
                    type="text"
                    class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 flex-1"
                    v-model="newCoverName"
                    placeholder="Neuer Cover-Name (z.B. folder)"
                    @keyup.enter="addCoverName"
                  />
                  <button
                    class="bg-white/5 border border-white/10 text-emerald-400 px-3 py-2 rounded hover:bg-white/10"
                    @click="addCoverName"
                    :disabled="!newCoverName.trim()"
                  >
                    <i class="bi bi-plus"></i>
                  </button>
                </div>
              </div>

              <!-- Cover Extensions -->
              <div class="mb-4">
                <label class="block text-sm text-gray-300 mb-2"
                  >Cover Dateierweiterungen</label
                >
                <div class="flex flex-wrap gap-2 mb-2">
                  <span
                    v-for="(ext, index) in settings.cover_extensions"
                    :key="`coverext-${index}`"
                    class="bg-white/5 border border-white/10 text-sky-400 px-2 py-1 rounded-full text-sm"
                    >{{ ext }}
                    <button
                      type="button"
                      class="ml-2 text-white/80"
                      @click="removeArrayItem('cover_extensions', index)"
                    >
                      ✕
                    </button></span
                  >
                </div>
                <div class="flex gap-2">
                  <input
                    type="text"
                    class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 flex-1"
                    v-model="newCoverExtension"
                    placeholder="Neue Erweiterung (z.B. jpg)"
                    @keyup.enter="addCoverExtension"
                  />
                  <button
                    class="bg-white/5 border border-white/10 text-sky-400 px-3 py-2 rounded hover:bg-white/10"
                    @click="addCoverExtension"
                    :disabled="!newCoverExtension.trim()"
                  >
                    <i class="bi bi-plus"></i>
                  </button>
                </div>
              </div>

              <!-- Artist Image Names -->
              <div class="mb-4">
                <label class="block text-sm text-gray-300 mb-2"
                  >Künstler Bild Dateinamen</label
                >
                <div class="flex flex-wrap gap-2 mb-2">
                  <span
                    v-for="(name, index) in settings.artist_image_names"
                    :key="`artist-${index}`"
                    class="bg-white/5 border border-white/10 text-yellow-400 px-2 py-1 rounded-full text-sm"
                    >{{ name }}
                    <button
                      type="button"
                      class="ml-2 text-white/80"
                      @click="removeArrayItem('artist_image_names', index)"
                    >
                      ✕
                    </button></span
                  >
                </div>
                <div class="flex gap-2">
                  <input
                    type="text"
                    class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 flex-1"
                    v-model="newArtistImageName"
                    placeholder="Neuer Künstler Bild-Name (z.B. artist)"
                    @keyup.enter="addArtistImageName"
                  />
                  <button
                    class="bg-white/5 border border-white/10 text-yellow-400 px-3 py-2 rounded hover:bg-white/10"
                    @click="addArtistImageName"
                    :disabled="!newArtistImageName.trim()"
                  >
                    <i class="bi bi-plus"></i>
                  </button>
                </div>
              </div>

              <!-- Artist Image Extensions -->
              <div class="mb-4">
                <label class="block text-sm text-gray-300 mb-2"
                  >Künstler Bild Erweiterungen</label
                >
                <div class="flex flex-wrap gap-2 mb-2">
                  <span
                    v-for="(ext, index) in settings.artist_image_extensions"
                    :key="`artistext-${index}`"
                    class="bg-white/5 border border-white/10 text-gray-300 px-2 py-1 rounded-full text-sm"
                    >{{ ext }}
                    <button
                      type="button"
                      class="ml-2 text-white/80"
                      @click="removeArrayItem('artist_image_extensions', index)"
                    >
                      ✕
                    </button></span
                  >
                </div>
                <div class="flex gap-2">
                  <input
                    type="text"
                    class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 flex-1"
                    v-model="newArtistImageExtension"
                    placeholder="Neue Erweiterung (z.B. png)"
                    @keyup.enter="addArtistImageExtension"
                  />
                  <button
                    class="bg-white/5 border border-white/10 text-gray-300 px-3 py-2 rounded hover:bg-white/10"
                    @click="addArtistImageExtension"
                    :disabled="!newArtistImageExtension.trim()"
                  >
                    <i class="bi bi-plus"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- SMTP Configuration -->
          <div class="bg-white/5 border border-white/10 rounded-lg mb-4">
            <div class="px-4 py-3 border-b border-white/10">
              <h5 class="mb-0">
                <i class="bi bi-envelope mr-2"></i>SMTP Konfiguration
              </h5>
            </div>
            <div class="p-4">
              <div class="space-y-4">
                <div class="w-full">
                  <label class="flex items-center gap-3">
                    <input
                      class="form-checkbox"
                      type="checkbox"
                      id="smtpEnabled"
                      v-model="settings.smtp_enabled"
                    />
                    <span class="text-white">SMTP aktiviert</span>
                  </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label
                      for="smtpHost"
                      class="block text-sm text-gray-300 mb-2"
                      >SMTP Host</label
                    >
                    <input
                      type="text"
                      id="smtpHost"
                      class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                      v-model="settings.smtp_host"
                      :disabled="!settings.smtp_enabled"
                      placeholder="smtp.example.com"
                    />
                  </div>
                  <div>
                    <label
                      for="smtpPort"
                      class="block text-sm text-gray-300 mb-2"
                      >SMTP Port</label
                    >
                    <input
                      type="number"
                      id="smtpPort"
                      class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                      v-model="settings.smtp_port"
                      :disabled="!settings.smtp_enabled"
                      placeholder="465"
                    />
                  </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label
                      for="smtpEncryption"
                      class="block text-sm text-gray-300 mb-2"
                      >Verschlüsselung</label
                    >
                    <select
                      id="smtpEncryption"
                      class="w-full"
                      v-model="settings.smtp_encryption"
                      :disabled="!settings.smtp_enabled"
                    >
                      <option value="ssl">SSL</option>
                      <option value="tls">TLS</option>
                      <option value="">Keine</option>
                    </select>
                  </div>
                  <div>
                    <label
                      for="smtpUsername"
                      class="block text-sm text-gray-300 mb-2"
                      >Benutzername</label
                    >
                    <input
                      type="text"
                      id="smtpUsername"
                      class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                      v-model="settings.smtp_username"
                      :disabled="!settings.smtp_enabled"
                      placeholder="user@example.com"
                    />
                  </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label
                      for="smtpPassword"
                      class="block text-sm text-gray-300 mb-2"
                      >Passwort</label
                    >
                    <input
                      type="password"
                      id="smtpPassword"
                      class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                      v-model="settings.smtp_password"
                      :disabled="!settings.smtp_enabled"
                      placeholder="Passwort"
                    />
                  </div>
                  <div>
                    <label
                      for="smtpFromEmail"
                      class="block text-sm text-gray-300 mb-2"
                      >Absender E-Mail</label
                    >
                    <input
                      type="email"
                      id="smtpFromEmail"
                      class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                      v-model="settings.smtp_from_email"
                      :disabled="!settings.smtp_enabled"
                      placeholder="noreply@example.com"
                    />
                  </div>
                </div>
                <div>
                  <label
                    for="smtpFromName"
                    class="block text-sm text-gray-300 mb-2"
                    >Absender Name</label
                  >
                  <input
                    type="text"
                    id="smtpFromName"
                    class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                    v-model="settings.smtp_from_name"
                    :disabled="!settings.smtp_enabled"
                    placeholder="Audinary"
                  />
                </div>
                <div>
                  <label class="flex items-center gap-3">
                    <input
                      class="form-checkbox"
                      type="checkbox"
                      id="smtpDebug"
                      v-model="settings.smtp_debug"
                      :disabled="!settings.smtp_enabled"
                    />
                    <span class="text-white">Debug-Modus aktiviert</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- MPD Configuration -->
          <div class="bg-white/5 border border-white/10 rounded-lg mb-4">
            <div class="px-4 py-3 border-b border-white/10">
              <h5 class="mb-0">
                <i class="bi bi-speaker mr-2"></i>MPD Konfiguration
              </h5>
            </div>
            <div class="p-4">
              <div class="space-y-4">
                <div class="w-full">
                  <label class="flex items-center gap-3">
                    <input
                      class="form-checkbox"
                      type="checkbox"
                      id="mpdEnabled"
                      v-model="settings.mpd_enabled"
                    />
                    <span class="text-white">MPD aktiviert</span>
                  </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label
                      for="mpdHost"
                      class="block text-sm text-gray-300 mb-2"
                      >MPD Host</label
                    >
                    <input
                      type="text"
                      id="mpdHost"
                      class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                      v-model="settings.mpd_host"
                      :disabled="!settings.mpd_enabled"
                      placeholder="10.10.10.22"
                    />
                  </div>
                  <div>
                    <label
                      for="mpdPort"
                      class="block text-sm text-gray-300 mb-2"
                      >MPD Port</label
                    >
                    <input
                      type="number"
                      id="mpdPort"
                      class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                      v-model="settings.mpd_port"
                      :disabled="!settings.mpd_enabled"
                      placeholder="6600"
                    />
                  </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label
                      for="mpdPassword"
                      class="block text-sm text-gray-300 mb-2"
                      >MPD Passwort</label
                    >
                    <input
                      type="password"
                      id="mpdPassword"
                      class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                      v-model="settings.mpd_password"
                      :disabled="!settings.mpd_enabled"
                      placeholder="Passwort (optional)"
                    />
                  </div>
                  <div>
                    <label
                      for="mpdDefaultVolume"
                      class="block text-sm text-gray-300 mb-2"
                      >Standard Lautstärke</label
                    >
                    <input
                      type="number"
                      id="mpdDefaultVolume"
                      class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                      v-model="settings.mpd_default_volume"
                      :disabled="!settings.mpd_enabled"
                      min="0"
                      max="100"
                      placeholder="80"
                    />
                  </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label
                      for="mpdReplaygain"
                      class="block text-sm text-gray-300 mb-2"
                      >Replaygain</label
                    >
                    <select
                      id="mpdReplaygain"
                      class="w-full"
                      v-model="settings.mpd_replaygain"
                      :disabled="!settings.mpd_enabled"
                    >
                      <option value="off">Off</option>
                      <option value="track">Track</option>
                      <option value="album">Album</option>
                      <option value="auto">Auto</option>
                    </select>
                  </div>
                  <div>
                    <label
                      for="mpdOutputDevice"
                      class="block text-sm text-gray-300 mb-2"
                      >Output Device</label
                    >
                    <input
                      type="number"
                      id="mpdOutputDevice"
                      class="bg-white/10 text-white border border-white/20 rounded px-3 py-2 w-full"
                      v-model="settings.mpd_output_device"
                      :disabled="!settings.mpd_enabled"
                      min="0"
                      placeholder="0"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Stats Sharing Configuration -->
          <div class="bg-white/5 border border-white/10 rounded-lg mb-4">
            <div class="px-4 py-3 border-b border-white/10">
              <h5 class="mb-0">
                <i class="bi bi-graph-up mr-2"></i>{{ $t("admin.stats.title") }}
              </h5>
            </div>
            <div class="p-4">
              <div class="space-y-4">
                <div
                  class="bg-white/5 border border-white/10 rounded-lg p-3 mb-4"
                >
                  <div class="flex items-start space-x-2">
                    <i class="bi bi-info-circle text-blue-400 mt-0.5"></i>
                    <div class="text-sm text-blue-200">
                      <p class="font-semibold mb-1">
                        🚫 {{ $t("admin.stats.privacy_notice_title") }}
                      </p>
                      <ul class="text-xs space-y-1">
                        <li>
                          ✅ {{ $t("admin.stats.privacy_notice_item_1") }}
                        </li>
                        <li>
                          ✅ {{ $t("admin.stats.privacy_notice_item_2") }}
                        </li>
                        <li>
                          ✅ {{ $t("admin.stats.privacy_notice_item_3") }}
                        </li>
                        <li>
                          ✅ {{ $t("admin.stats.privacy_notice_item_4") }}
                        </li>
                        <li>
                          ✅ {{ $t("admin.stats.privacy_notice_item_5") }}
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div
                  class="bg-white/5 border border-white/10 rounded-lg p-3 mb-4"
                >
                  <div class="flex items-start space-x-2">
                    <i class="bi bi-rocket text-orange-400 mt-0.5"></i>
                    <div class="text-sm text-orange-200">
                      <p class="font-semibold mb-1">
                        🚀 {{ $t("admin.stats.why_stats_title") }}
                      </p>
                      <p class="text-xs">
                        {{
                          $t("admin.stats.why_stats_description", {
                            homepageLink: `https://${$t("admin.stats.homepage_link")}`,
                          })
                        }}
                      </p>
                      <a
                        :href="`https://${$t('admin.stats.homepage_link')}`"
                        target="_blank"
                        class="text-orange-300 hover:text-orange-100 underline text-xs mt-1 inline-block"
                      >
                        🔗 {{ $t("admin.stats.homepage_link") }} ↗
                      </a>
                    </div>
                  </div>
                </div>

                <div class="w-full">
                  <label class="flex items-center gap-3">
                    <input
                      class="form-checkbox"
                      type="checkbox"
                      id="statsEnabled"
                      v-model="settings.stats_enabled"
                    />
                    <span class="text-white">{{
                      $t("admin.stats.enabled")
                    }}</span>
                  </label>
                  <div class="text-sm text-gray-400 mt-1">
                    {{ $t("admin.stats.enabled_description") }}
                  </div>
                </div>

                <div class="pt-2">
                  <div class="flex gap-2">
                    <button
                      class="bg-white/5 border border-white/10 text-green-400 px-3 py-2 rounded text-sm hover:bg-white/10"
                      @click="previewStats"
                      :disabled="statsLoading"
                    >
                      <i class="bi bi-eye mr-1"></i
                      >{{ $t("admin.stats.preview_button") }}
                    </button>
                    <button
                      v-if="settings.stats_enabled"
                      class="bg-white/5 border border-white/10 text-orange-400 px-3 py-2 rounded text-sm hover:bg-white/10"
                      @click="sendStats"
                      :disabled="statsLoading"
                    >
                      <i class="bi bi-send mr-1"></i
                      >{{ $t("admin.stats.send_button") }}
                    </button>
                  </div>
                </div>

                <div
                  v-if="statsPreview"
                  class="bg-gray-900 rounded-lg p-3 mt-3"
                >
                  <h6 class="text-green-400 mb-2">
                    <i class="bi bi-eye mr-1"></i
                    >{{ $t("admin.stats.preview_title") }}
                  </h6>
                  <pre class="text-xs text-gray-300 overflow-x-auto">{{
                    JSON.stringify(statsPreview, null, 2)
                  }}</pre>
                  <div class="text-xs text-gray-400 mt-2">
                    <strong>{{ $t("admin.stats.next_send_allowed") }}</strong>
                    {{
                      statsPreview.nextSendAllowed ||
                      $t("admin.stats.never_sent")
                    }}
                  </div>
                </div>

                <div
                  v-if="statsMessage"
                  class="bg-white/5 border border-white/10 p-2 rounded text-sm"
                  :class="{
                    'text-green-400': statsMessage.type === 'success',
                    'text-red-400': statsMessage.type === 'error',
                    'text-yellow-400': statsMessage.type === 'warning',
                  }"
                >
                  {{ statsMessage.text }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar with Actions -->
        <div class="lg:w-80 flex-shrink-0">
          <div class="space-y-4">
            <div
              class="bg-white/5 border border-white/10 rounded-lg p-4 hover:shadow-md transition-shadow"
            >
              <h6 class="mb-2"><i class="bi bi-save mr-2"></i>Aktionen</h6>
              <div class="grid gap-2">
                <button
                  class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @click="saveConfig"
                  :disabled="saving"
                >
                  {{
                    saving
                      ? $t("admin.config.saving")
                      : "Konfiguration speichern"
                  }}
                </button>
                <button
                  class="bg-white/5 border border-white/10 text-gray-200 px-3 py-2 rounded hover:bg-white/10"
                  @click="resetConfig"
                  :disabled="saving"
                >
                  Zurücksetzen
                </button>
                <button
                  class="bg-white/5 border border-white/10 text-yellow-400 px-3 py-2 rounded hover:bg-white/10"
                  @click="resetToDefaults"
                  :disabled="saving"
                >
                  Standardwerte
                </button>
                <button
                  class="bg-white/5 border border-white/10 text-gray-200 px-3 py-2 rounded hover:bg-white/10"
                  @click="loadConfig"
                  :disabled="saving"
                >
                  Neu laden
                </button>
              </div>
            </div>

            <!-- Info Card -->
            <div class="bg-white/5 border border-white/10 rounded-lg p-4">
              <h6 class="mb-2">
                <i class="bi bi-info-circle mr-2"></i>Hinweise
              </h6>
              <ul class="text-sm text-gray-400 space-y-2">
                <li>
                  <i class="bi bi-check-circle text-green-400 mr-1"></i
                  >Änderungen werden direkt in die config.php geschrieben
                </li>
                <li>
                  <i class="bi bi-exclamation-triangle text-yellow-400 mr-1"></i
                  >Einige Änderungen erfordern einen Server-Neustart
                </li>
                <li>
                  <i class="bi bi-shield-check text-sky-400 mr-1"></i>Stellen
                  Sie sicher, dass Verzeichnisse existieren
                </li>
                <li>
                  <i class="bi bi-database text-blue-400 mr-1"></i
                  >Datenbankänderungen werden sofort aktiv
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import { useApiStore } from "@/stores/api";
import { useAlertStore } from "@/stores/alert";

const { t } = useI18n();
const apiStore = useApiStore();
const alertStore = useAlertStore();

// State
const loading = ref(true);
const saving = ref(false);

// Database settings (stored in global_settings table)
const settings = ref({
  // MPD Configuration
  mpd_enabled: false,
  mpd_host: "",
  mpd_port: 6600,
  mpd_password: "",
  mpd_default_volume: 80,
  mpd_replaygain: "auto",
  mpd_output_device: 0,

  // SMTP Configuration
  smtp_enabled: false,
  smtp_host: "",
  smtp_port: 465,
  smtp_encryption: "ssl",
  smtp_username: "",
  smtp_password: "",
  smtp_from_email: "",
  smtp_from_name: "Audinary",
  smtp_debug: false,

  // Music scanning
  allowed_extensions: [],
  cover_names: [],
  cover_extensions: [],
  artist_image_names: [],
  artist_image_extensions: [],

  // User registration
  user_registration_enabled: false,

  // Music scanning options
  tag_first_mode: false,

  // Password reset
  password_reset_token_validity_minutes: 15,
  password_reset_max_requests_per_hour: 10,
  password_reset_cleanup_interval_hours: 24,

  // Backup
  backup_retention_days: 30,
  backup_max_backups: 10,
  backup_compression: "gzip",
  backup_exclude_patterns: [],

  // Stats Sharing
  stats_enabled: false,
  stats_instance_id: "",
});

// Stats related state
const statsLoading = ref(false);
const statsPreview = ref(null);
const statsMessage = ref(null);

// New item inputs
const newAllowedExtension = ref("");
const newCoverName = ref("");
const newCoverExtension = ref("");
const newArtistImageName = ref("");
const newArtistImageExtension = ref("");

// Original settings for reset
let originalSettings = null;

// Methods
const loadConfig = async () => {
  try {
    loading.value = true;

    let settingsData = null;
    try {
      const settingsResponse = await apiStore.get("/api/settings");

      // The apiStore returns data directly, not in a .data property
      settingsData = settingsResponse.data || settingsResponse;
    } catch (error) {
      // Fallback: direct fetch
      const response = await fetch("/api/settings", {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token") || ""}`,
        },
      });
      settingsData = await response.json();
    }

    // Process settings data - handle JSON parsing for arrays and type conversion
    if (settingsData && typeof settingsData === "object") {
      Object.keys(settingsData).forEach((key) => {
        const value = settingsData[key];

        if (typeof value === "string") {
          // Try JSON parsing for arrays and objects
          if (value.startsWith("[") || value.startsWith("{")) {
            try {
              const parsed = JSON.parse(value);
              settings.value[key] = parsed;
              return;
            } catch (e) {
              settings.value[key] = value;
              return;
            }
          }

          // Handle boolean strings
          if (value === "true") {
            settings.value[key] = true;
            return;
          }
          if (value === "false") {
            settings.value[key] = false;
            return;
          }

          // Handle numeric strings
          if (value !== "" && !isNaN(Number(value))) {
            settings.value[key] = Number(value);
            return;
          }

          // Default to string
          settings.value[key] = value;
        } else {
          // Non-string values (shouldn't happen with current API but just in case)
          settings.value[key] = value;
        }
      });
    }

    // Ensure arrays are arrays
    const arrayFields = [
      "allowed_extensions",
      "cover_names",
      "cover_extensions",
      "artist_image_names",
      "artist_image_extensions",
    ];
    arrayFields.forEach((field) => {
      if (!Array.isArray(settings.value[field])) {
        settings.value[field] = [];
      }
    });

    originalSettings = JSON.parse(JSON.stringify(settings.value));
  } catch (error) {
    alertStore.error("Fehler beim Laden der Konfiguration");
  } finally {
    loading.value = false;
  }
};

const saveConfig = async () => {
  try {
    saving.value = true;

    // Prepare settings data for API (convert to strings as expected by backend)
    const preparedSettings = {};

    Object.keys(settings.value).forEach((key) => {
      const value = settings.value[key];

      if (Array.isArray(value)) {
        // Convert arrays to JSON strings
        preparedSettings[key] = JSON.stringify(value);
      } else if (typeof value === "boolean") {
        // Convert booleans to string representation
        preparedSettings[key] = value ? "true" : "false";
      } else if (typeof value === "number") {
        // Convert numbers to strings
        preparedSettings[key] = value.toString();
      } else {
        // Keep strings as they are
        preparedSettings[key] = value || "";
      }
    });

    await apiStore.put("/api/settings", preparedSettings);

    originalSettings = JSON.parse(JSON.stringify(settings.value));
    alertStore.success("Konfiguration erfolgreich gespeichert");
  } catch (error) {
    alertStore.error(
      "Fehler beim Speichern der Konfiguration: " +
        (error.response?.data?.error || error.message),
    );
  } finally {
    saving.value = false;
  }
};

const resetConfig = () => {
  if (originalSettings) {
    settings.value = JSON.parse(JSON.stringify(originalSettings));
    alertStore.success("Konfiguration zurückgesetzt");
  }
};

const resetToDefaults = () => {
  // Set default values for all settings
  settings.value = {
    // MPD Configuration - defaults to disabled
    mpd_enabled: false,
    mpd_host: "",
    mpd_port: 6600,
    mpd_password: "",
    mpd_default_volume: 80,
    mpd_replaygain: "auto",
    mpd_output_device: 0,

    // SMTP Configuration - defaults to disabled
    smtp_enabled: false,
    smtp_host: "smtp.example.com",
    smtp_port: 465,
    smtp_encryption: "ssl",
    smtp_username: "example@example.com",
    smtp_password: "",
    smtp_from_email: "example@example.com",
    smtp_from_name: "Audinary",
    smtp_debug: false,

    // Music scanning defaults
    allowed_extensions: [
      "mp3",
      "wav",
      "flac",
      "ogg",
      "m4a",
      "aac",
      "wma",
      "aiff",
      "aif",
      "ape",
      "wv",
      "mpc",
      "opus",
      "ra",
      "rm",
      "mka",
    ],
    cover_names: ["cover", "folder", "front"],
    cover_extensions: ["jpg", "jpeg", "png"],
    artist_image_names: ["artist", "band", "photo", "folder"],
    artist_image_extensions: ["jpg", "jpeg", "png"],

    // User registration
    user_registration_enabled: false,

    // Music scanning options
    tag_first_mode: false,

    // Password reset
    password_reset_token_validity_minutes: 15,
    password_reset_max_requests_per_hour: 10,
    password_reset_cleanup_interval_hours: 24,

    // Backup
    backup_retention_days: 30,
    backup_max_backups: 10,
    backup_compression: "gzip",
    backup_exclude_patterns: ["*.tmp", "*.log", "Thumbs.db", ".DS_Store"],

    // Stats Sharing
    stats_enabled: false,
    stats_instance_id: "",
  };

  alertStore.success("Konfiguration auf Standardwerte zurückgesetzt");
};

const removeArrayItem = (arrayName, index) => {
  settings.value[arrayName].splice(index, 1);
};

const addAllowedExtension = () => {
  const ext = newAllowedExtension.value.trim().toLowerCase();
  if (ext && !settings.value.allowed_extensions.includes(ext)) {
    settings.value.allowed_extensions.push(ext);
    newAllowedExtension.value = "";
  }
};

const addCoverName = () => {
  const name = newCoverName.value.trim().toLowerCase();
  if (name && !settings.value.cover_names.includes(name)) {
    settings.value.cover_names.push(name);
    newCoverName.value = "";
  }
};

const addCoverExtension = () => {
  const ext = newCoverExtension.value.trim().toLowerCase();
  if (ext && !settings.value.cover_extensions.includes(ext)) {
    settings.value.cover_extensions.push(ext);
    newCoverExtension.value = "";
  }
};

const addArtistImageName = () => {
  const name = newArtistImageName.value.trim().toLowerCase();
  if (name && !settings.value.artist_image_names.includes(name)) {
    settings.value.artist_image_names.push(name);
    newArtistImageName.value = "";
  }
};

const addArtistImageExtension = () => {
  const ext = newArtistImageExtension.value.trim().toLowerCase();
  if (ext && !settings.value.artist_image_extensions.includes(ext)) {
    settings.value.artist_image_extensions.push(ext);
    newArtistImageExtension.value = "";
  }
};

// Stats sharing methods
const clearStatsMessage = () => {
  setTimeout(() => {
    statsMessage.value = null;
  }, 5000);
};

const previewStats = async () => {
  try {
    statsLoading.value = true;
    statsMessage.value = null;
    statsPreview.value = null;

    const response = await apiStore.get("/api/admin/stats-sharing/preview");

    if (response.success) {
      statsPreview.value = response.stats;
      statsMessage.value = {
        type: "success",
        text: t("admin.stats.preview_success"),
      };
    } else {
      statsMessage.value = {
        type: "error",
        text: response.message || t("admin.stats.preview_error"),
      };
    }
  } catch (error) {
    statsMessage.value = {
      type: "error",
      text: t("admin.stats.network_error_preview"),
    };
    console.error("Stats preview error:", error);
  } finally {
    statsLoading.value = false;
    clearStatsMessage();
  }
};

const sendStats = async () => {
  try {
    statsLoading.value = true;
    statsMessage.value = null;

    const response = await apiStore.post("/api/admin/stats-sharing/send", {});

    if (response.success) {
      statsMessage.value = {
        type: "success",
        text: t("admin.stats.send_success"),
      };
      // Refresh preview to show updated last sent time
      setTimeout(() => {
        previewStats();
      }, 1000);
    } else {
      statsMessage.value = {
        type: "warning",
        text: response.message || t("admin.stats.send_error"),
      };
    }
  } catch (error) {
    statsMessage.value = {
      type: "error",
      text: t("admin.stats.network_error_send"),
    };
    console.error("Stats send error:", error);
  } finally {
    statsLoading.value = false;
    clearStatsMessage();
  }
};

onMounted(() => {
  loadConfig();
});
</script>
