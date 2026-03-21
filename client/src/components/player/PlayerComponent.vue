<template>
  <!-- Mobile-First Player -->
  <div class="w-full md:hidden flex-shrink-0">
    <!-- Mobile Mini Player Bar -->
    <div
      v-if="!isExpanded && shouldShowPlayer"
      class="border-t border-white/20 text-white h-16 relative cursor-pointer backdrop-blur-lg shadow-2xl active:scale-[0.98] transition-transform duration-200"
      :class="themeStore.backgroundGradient"
      @click="expandPlayer"
      @touchstart="handleTouchStart"
      @touchmove="handleTouchMove"
      @touchend="handleTouchEnd"
    >
      <div class="grid grid-cols-12 items-center h-full px-4 gap-3">
        <!-- Cover and Info -->
        <div class="col-span-7 flex items-center min-w-0">
          <div class="relative w-12 h-12 flex-shrink-0">
            <!-- Gradient placeholder background -->
            <div
              v-if="
                currentSong?.coverGradient && currentSong.coverGradient.colors
              "
              class="absolute inset-0 rounded-full"
              :style="{
                background: `linear-gradient(${currentSong.coverGradient.angle || 135}deg, ${currentSong.coverGradient.colors.join(', ')})`,
                filter: 'blur(10px)',
                zIndex: 1,
              }"
            ></div>
            <SimpleImage
              :imageType="'album_thumbnail'"
              :imageId="displayAlbumId"
              alt="Cover"
              class="w-12 h-12 rounded-full shadow-lg relative z-[2]"
              :class="{ 'animate-slow-spin': isPlaying }"
              :style="'object-fit: cover;'"
              :placeholder="'disc'"
              :placeholderSize="'24px'"
            />
          </div>
          <div class="ml-3 min-w-0 flex-1">
            <div
              class="font-medium text-white text-sm truncate cursor-pointer active:text-blue-400 transition-colors"
              @click.stop="handleSongClick"
              :title="$t('player.clickToViewAlbum')"
            >
              {{ displayTitle }}
            </div>
            <div
              class="text-xs text-white/80 truncate cursor-pointer active:text-blue-400 transition-colors"
              @click.stop="handleArtistClick"
              :title="$t('player.clickToViewArtistAlbums')"
            >
              {{ displayArtist }}
            </div>
          </div>
        </div>

        <!-- VU Meters and Basic Controls -->
        <div class="col-span-5 flex items-center justify-end gap-2">
          <!-- Compact VU Meters for mobile -->
          <div class="flex flex-col gap-1 mr-1">
            <!-- Left Channel -->
            <div class="mobile-vu-meter">
              <div class="vu-channel-label-mobile">L</div>
              <div class="vu-bars-horizontal-mobile">
                <div
                  v-for="n in 12"
                  :key="`ml-${n}`"
                  class="vu-bar-h-mobile"
                  :class="{
                    active: vuMeterLeft >= n * 8.33,
                    danger: n > 10,
                    warning: n > 7 && n <= 10,
                  }"
                ></div>
              </div>
            </div>
            <!-- Right Channel -->
            <div class="mobile-vu-meter">
              <div class="vu-channel-label-mobile">R</div>
              <div class="vu-bars-horizontal-mobile">
                <div
                  v-for="n in 12"
                  :key="`mr-${n}`"
                  class="vu-bar-h-mobile"
                  :class="{
                    active: vuMeterRight >= n * 8.33,
                    danger: n > 10,
                    warning: n > 7 && n <= 10,
                  }"
                ></div>
              </div>
            </div>
          </div>

          <button
            class="w-9 h-9 flex items-center justify-center text-white active:scale-95 transition-transform disabled:opacity-40 disabled:cursor-not-allowed"
            @click.stop="previousSong"
            :disabled="!hasPrevious"
            aria-label="Previous"
          >
            <i class="bi bi-skip-backward-fill text-base"></i>
          </button>
          <button
            class="w-11 h-11 flex items-center justify-center bg-white/20 rounded-full text-white active:scale-95 transition-all backdrop-blur-sm border border-white/30"
            @click.stop="togglePlayPause"
            aria-label="Play/Pause"
          >
            <i :class="`${playPauseIcon} text-lg`"></i>
          </button>
          <button
            class="w-9 h-9 flex items-center justify-center text-white active:scale-95 transition-transform disabled:opacity-40 disabled:cursor-not-allowed"
            @click.stop="nextSong"
            :disabled="!hasNext"
            aria-label="Next"
          >
            <i class="bi bi-skip-forward-fill text-base"></i>
          </button>
        </div>
      </div>

      <!-- Progress Bar -->
      <div
        class="absolute bottom-0 left-0 w-full h-0.5 bg-white/30 cursor-pointer"
        @click.stop="seekTo"
      >
        <div
          class="h-full bg-blue-500 transition-all duration-300 ease-out"
          :style="{ width: progressPercentage + '%' }"
        ></div>
      </div>
    </div>

    <!-- Mobile Expanded Player -->
    <div
      v-if="isExpanded"
      class="fixed inset-0 text-white z-[1070] flex flex-col animate-in slide-in-from-bottom duration-300 backdrop-blur-2xl"
      :class="themeStore.backgroundGradient"
      @touchstart="handleExpandedTouchStart"
      @touchmove="handleExpandedTouchMove"
      @touchend="handleExpandedTouchEnd"
    >
      <!-- Header -->
      <div
        class="flex items-center justify-between px-6 pt-[calc(env(safe-area-inset-top)+20px)] pb-6 border-b border-white/10"
      >
        <button
          class="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 text-white active:scale-95 transition-all"
          @click="collapsePlayer"
          aria-label="Collapse"
        >
          <i class="bi bi-chevron-down text-xl"></i>
        </button>
        <span class="font-semibold text-lg text-center flex-1">
          {{
            showMobileQueue ? $t("player.queue.title") : $t("player.nowPlaying")
          }}
        </span>
        <button
          class="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 text-white active:scale-95 transition-all"
          :class="{ 'bg-blue-500/30 text-blue-300': showMobileQueue }"
          @click="toggleMobileQueue"
          aria-label="Toggle Queue"
        >
          <i
            :class="
              showMobileQueue ? 'bi bi-disc text-xl' : 'bi bi-list text text-xl'
            "
          ></i>
        </button>
      </div>

      <!-- Large Cover OR Queue -->
      <div
        v-if="!showMobileQueue"
        class="flex-1 flex items-center justify-center px-8 py-4 min-h-0"
      >
        <div
          class="relative w-full max-w-xs aspect-square"
          @touchstart="handleCoverTouchStart"
          @touchmove="handleCoverTouchMove"
          @touchend="handleCoverTouchEnd"
        >
          <!-- Gradient placeholder background -->
          <div
            v-if="
              currentSong?.coverGradient && currentSong.coverGradient.colors
            "
            class="absolute inset-0 rounded-2xl"
            :style="{
              background: `linear-gradient(${currentSong.coverGradient.angle || 135}deg, ${currentSong.coverGradient.colors.join(', ')})`,
              filter: 'blur(10px)',
              zIndex: 1,
            }"
          ></div>
          <SimpleImage
            :imageType="'album_thumbnail'"
            :imageId="displayAlbumId"
            alt="Cover"
            class="w-full h-full rounded-2xl shadow-2xl border border-white/20 transition-transform duration-300 relative z-[2]"
            :style="
              'object-fit: cover; transform: translateX(' +
              coverSwipeOffset +
              'px);'
            "
            :placeholder="'disc'"
            :placeholderSize="'min(120px, 25vw)'"
          />
        </div>
      </div>

      <!-- Mobile Queue List -->
      <div v-else class="flex-1 overflow-y-auto px-6 pb-6 space-y-6">
        <div class="space-y-4">
          <h6
            class="text-sm font-semibold uppercase tracking-wider text-gray-300 px-1"
          >
            {{ $t("player.queue.nowPlaying") }}
          </h6>
          <div
            v-if="currentSong"
            class="flex items-center p-4 bg-blue-500/10 rounded-2xl border border-blue-500/20 backdrop-blur-sm"
          >
            <div class="relative w-12 h-12 flex-shrink-0">
              <!-- Gradient placeholder background -->
              <div
                v-if="
                  currentSong.coverGradient && currentSong.coverGradient.colors
                "
                class="absolute inset-0 rounded-xl"
                :style="{
                  background: `linear-gradient(${currentSong.coverGradient.angle || 135}deg, ${currentSong.coverGradient.colors.join(', ')})`,
                  filter: 'blur(10px)',
                  zIndex: 1,
                }"
              ></div>
              <SimpleImage
                :imageType="'album_thumbnail'"
                :imageId="currentSong.album_id || 'default'"
                alt="Cover"
                class="w-12 h-12 rounded-xl shadow-lg relative z-[2]"
                :style="'object-fit: cover;'"
                :placeholder="'disc'"
                :placeholderSize="'24px'"
              />
            </div>
            <div class="flex-1 min-w-0 ml-4">
              <div class="font-semibold text-white truncate">
                {{ currentSong.title }}
              </div>
              <div class="text-sm text-blue-200 truncate">
                {{ currentSong.artist }}
              </div>
            </div>
            <div class="flex-shrink-0">
              <i class="bi bi-play-circle-fill text-blue-400 text-2xl"></i>
            </div>
          </div>
          <div
            v-else
            class="flex items-center p-4 bg-gray-500/10 rounded-2xl border border-gray-500/20 backdrop-blur-sm"
          >
            <SimpleImage
              :imageType="'album_thumbnail'"
              :imageId="'default'"
              alt="Cover"
              class="w-12 h-12 rounded-xl shadow-lg flex-shrink-0"
              :style="'object-fit: cover;'"
              :placeholder="'disc'"
              :placeholderSize="'24px'"
            />
            <div class="flex-1 min-w-0 ml-4">
              <div class="font-semibold text-gray-300 truncate">
                {{ $t("player.noSong") }}
              </div>
              <div class="text-sm text-gray-400 truncate">
                {{ $t("player.queueReady") }}
              </div>
            </div>
            <div class="flex-shrink-0">
              <i class="bi bi-music-note-list text-gray-400 text-2xl"></i>
            </div>
          </div>
        </div>

        <div v-if="playerStore.upcomingQueue.length > 0" class="space-y-4">
          <h6
            class="text-sm font-semibold uppercase tracking-wider text-gray-300 px-1"
          >
            {{ $t("player.queue.upNext") }} ({{
              playerStore.upcomingQueue.length
            }})
          </h6>
          <div class="space-y-2">
            <div
              v-for="(song, index) in playerStore.upcomingQueue.slice(0, 5)"
              :key="song.id + '-' + index"
              class="flex items-center p-3 bg-white/5 rounded-xl border border-white/10 cursor-pointer active:scale-[0.98] transition-all duration-200 active:bg-white/10"
              @click="playFromQueue(index)"
            >
              <div class="relative w-11 h-11 flex-shrink-0">
                <!-- Gradient placeholder background -->
                <div
                  v-if="song.coverGradient && song.coverGradient.colors"
                  class="absolute inset-0 rounded-lg"
                  :style="{
                    background: `linear-gradient(${song.coverGradient.angle || 135}deg, ${song.coverGradient.colors.join(', ')})`,
                    filter: 'blur(10px)',
                    zIndex: 1,
                  }"
                ></div>
                <SimpleImage
                  :imageType="'album_thumbnail'"
                  :imageId="song.album_id || 'default'"
                  alt="Cover"
                  class="w-11 h-11 rounded-lg shadow-md relative z-[2]"
                  :style="'object-fit: cover;'"
                  :placeholder="'disc'"
                  :placeholderSize="'22px'"
                />
              </div>
              <div class="flex-1 min-w-0 ml-3">
                <div class="font-medium text-audinary text-sm truncate">
                  {{ song.title }}
                </div>
                <div class="text-xs text-white/80 truncate">
                  {{ song.artist }}
                </div>
              </div>
              <button
                class="w-8 h-8 flex items-center justify-center text-white/80 active:scale-95 transition-all"
                @click.stop="removeFromQueue(index)"
                aria-label="Remove"
              >
                <i class="bi bi-x-circle text-lg"></i>
              </button>
            </div>
          </div>
        </div>

        <div v-if="playerStore.previousQueue.length > 0" class="space-y-4">
          <h6
            class="text-sm font-semibold uppercase tracking-wider text-gray-300 px-1"
          >
            {{ $t("player.queue.history") }} ({{
              playerStore.previousQueue.length
            }})
          </h6>
          <div class="space-y-2">
            <div
              v-for="(song, index) in playerStore.previousQueue
                .slice(-5)
                .reverse()"
              :key="song.id + '-prev-' + index"
              class="flex items-center p-3 bg-white/5 rounded-xl border border-white/10 cursor-pointer active:scale-[0.98] transition-all duration-200 active:bg-white/10"
              @click="
                playFromHistory(playerStore.previousQueue.length - 1 - index)
              "
            >
              <div class="relative w-11 h-11 flex-shrink-0 opacity-60">
                <!-- Gradient placeholder background -->
                <div
                  v-if="song.coverGradient && song.coverGradient.colors"
                  class="absolute inset-0 rounded-lg"
                  :style="{
                    background: `linear-gradient(${song.coverGradient.angle || 135}deg, ${song.coverGradient.colors.join(', ')})`,
                    filter: 'blur(10px)',
                    zIndex: 1,
                  }"
                ></div>
                <SimpleImage
                  :imageType="'album_thumbnail'"
                  :imageId="song.album_id || 'default'"
                  alt="Cover"
                  class="w-11 h-11 rounded-lg shadow-md relative z-[2]"
                  :style="'object-fit: cover;'"
                  :placeholder="'disc'"
                  :placeholderSize="'22px'"
                />
              </div>
              <div class="flex-1 min-w-0 ml-3">
                <div class="font-medium text-gray-300 text-sm truncate">
                  {{ song.title }}
                </div>
                <div class="text-xs text-white/70 truncate">
                  {{ song.artist }}
                </div>
              </div>
              <div class="flex-shrink-0">
                <i class="bi bi-clock-history text-white/70 text-lg"></i>
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="
            playerStore.upcomingQueue.length === 0 &&
            playerStore.previousQueue.length === 0
          "
          class="flex flex-col items-center justify-center text-center py-20"
        >
          <i class="bi bi-list text text-4xl mb-4 text-gray-600"></i>
          <div class="text-white/70">{{ $t("player.queue.empty") }}</div>
        </div>
      </div>

      <!-- Track Info (only show when not showing queue) -->
      <div v-if="!showMobileQueue" class="text-center px-8 pb-6">
        <div
          class="font-bold text-2xl text-white truncate cursor-pointer active:text-blue-400 transition-colors mb-2"
          @click="handleSongClick"
          :title="$t('player.clickToViewAlbum')"
        >
          {{ currentSong?.title || $t("player.noSong") }}
        </div>
        <div
          class="text-lg text-gray-300 truncate cursor-pointer active:text-blue-400 transition-colors mb-1"
          @click="handleArtistClick"
          :title="$t('player.clickToViewArtistAlbums')"
        >
          {{ currentSong?.artist || "-" }}
        </div>
        <div
          class="text-sm text-white/80 truncate cursor-pointer active:text-blue-400 transition-colors"
          @click="handleAlbumClick"
          :title="$t('player.clickToViewAlbum')"
        >
          {{ currentSong?.album || "-" }}
        </div>
      </div>

      <!-- Progress -->
      <div class="px-8 pb-6">
        <div
          class="h-1 bg-white/30 rounded-full mb-4 cursor-pointer group relative"
          @click="seekTo"
        >
          <div
            class="h-full bg-blue-500 rounded-full transition-all duration-300 ease-out relative"
            :style="{ width: progressPercentage + '%' }"
          >
            <div
              class="absolute right-0 top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 group-active:opacity-100 transition-opacity"
            ></div>
          </div>
        </div>
        <div class="flex justify-between text-sm text-white/80 font-mono">
          <span>{{ formatTime(currentTime) }}</span>
          <span>{{ formatTime(duration) }}</span>
        </div>
      </div>

      <!-- Main Controls -->
      <div class="flex items-center justify-center gap-8 px-8 py-6">
        <button
          class="w-12 h-12 flex items-center justify-center text-white/80 active:scale-95 transition-all"
          :class="{ 'text-blue-400': isShuffled }"
          @click="toggleShuffle"
          aria-label="Shuffle"
        >
          <i class="bi bi-shuffle text-2xl"></i>
        </button>
        <button
          class="w-14 h-14 flex items-center justify-center text-white active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          @click="previousSong"
          :disabled="!hasPrevious"
          aria-label="Previous"
        >
          <i class="bi bi-skip-backward-fill text-3xl"></i>
        </button>
        <button
          class="w-20 h-20 flex items-center justify-center bg-white text-black rounded-full shadow-xl active:scale-95 transition-all"
          @click="togglePlayPause"
          aria-label="Play/Pause"
        >
          <i :class="`${playPauseIcon} text-3xl`"></i>
        </button>
        <button
          class="w-14 h-14 flex items-center justify-center text-white active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          @click="nextSong"
          :disabled="!hasNext"
          aria-label="Next"
        >
          <i class="bi bi-skip-forward-fill text-3xl"></i>
        </button>
        <button
          class="w-12 h-12 flex items-center justify-center text-white/80 active:scale-95 transition-all"
          :class="{ 'text-blue-400': repeatMode !== 'none' }"
          @click="toggleRepeat"
          aria-label="Repeat"
        >
          <i :class="repeatIcon + ' text-2xl'"></i>
        </button>
      </div>

      <!-- Secondary Controls -->
      <div
        class="flex items-center justify-center gap-4 px-8 pb-[calc(env(safe-area-inset-bottom)+24px)]"
      >
        <button
          class="w-10 h-10 flex items-center justify-center text-white/80 active:scale-95 transition-all"
          @click="toggleMute"
          aria-label="Mute"
        >
          <i :class="volumeIcon + ' text-lg'"></i>
        </button>
        <input
          v-model="volume"
          type="range"
          class="flex-1 max-w-48 h-1 bg-gray-600 rounded-full appearance-none cursor-pointer"
          min="0"
          max="1"
          step="0.01"
          @input="updateVolume"
        />
        <button
          class="w-10 h-10 flex items-center justify-center text-white/80 active:scale-95 transition-all"
          :class="{ 'text-blue-400': showEqualizer }"
          @click="toggleEqualizer"
          aria-label="Equalizer"
        >
          <i class="bi bi-sliders text-lg"></i>
        </button>
      </div>

      <!-- Equalizer -->
      <div
        v-if="showEqualizer"
        class="bg-black/30 rounded-2xl mx-6 mb-6 p-6 backdrop-blur-xl border border-white/20"
      >
        <div class="flex justify-between items-center mb-6">
          <h6 class="text-lg font-semibold">7-Band EQ</h6>
          <div class="flex items-center gap-3">
            <button
              class="px-4 py-2 text-sm rounded-lg border border-white/30 text-white active:scale-95 transition-all"
              @click="resetEqualizer"
            >
              Reset
            </button>
            <button
              class="px-4 py-2 text-sm rounded-lg transition-all active:scale-95"
              :class="
                playerStore.equalizerEnabled
                  ? 'bg-blue-600 text-white'
                  : 'border border-blue-600 text-blue-400'
              "
              @click="toggleEqualizerEnabled"
            >
              {{ playerStore.equalizerEnabled ? "ON" : "OFF" }}
            </button>
          </div>
        </div>
        <div class="grid grid-cols-7 gap-3">
          <div
            v-for="(gain, freq) in equalizer"
            :key="freq"
            class="flex flex-col items-center"
          >
            <label class="text-xs text-white/80 mb-3 font-medium">{{
              formatFrequency(freq)
            }}</label>
            <div
              class="relative h-24 w-6 bg-gray-700 rounded-full flex items-end justify-center"
            >
              <input
                v-model="equalizer[freq]"
                type="range"
                class="eq-slider"
                min="-12"
                max="12"
                step="0.5"
                orient="vertical"
                @input="updateEqualizer"
                :disabled="!playerStore.equalizerEnabled"
              />
            </div>
            <span class="text-xs text-white/80 mt-3 font-mono"
              >{{ equalizer[freq] }}dB</span
            >
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Desktop Player (responsive from md and up) -->
  <div v-if="shouldShowPlayer" class="hidden md:block w-full flex-shrink-0">
    <!-- EQ collapse above footer -->
    <div
      v-if="showEqualizer"
      class="fixed bottom-32 left-1/2 transform -translate-x-1/2 w-full max-w-4xl z-50"
      :style="
        !isPublicShareView
          ? { marginLeft: sidebarCollapsed ? '2.25rem' : '8rem' }
          : {}
      "
    >
      <div
        class="text-white p-6 mx-4 rounded-lg border border-gray-700 shadow-2xl backdrop-blur-md"
        :class="themeStore.backgroundGradient"
      >
        <div class="flex justify-between items-center mb-4">
          <h6 class="text-lg font-semibold">7-Band Equalizer</h6>
          <div class="flex items-center gap-3">
            <button
              class="px-3 py-1.5 text-sm rounded-md border border-gray-600 text-gray-300 hover:bg-gray-700 transition-colors"
              @click="resetEqualizer"
            >
              Reset
            </button>
            <button
              class="px-3 py-1.5 text-sm rounded-md transition-colors"
              :class="
                playerStore.equalizerEnabled
                  ? 'bg-blue-600 text-white hover:bg-blue-700'
                  : 'border border-blue-600 text-blue-400 hover:bg-blue-600/10'
              "
              @click="toggleEqualizerEnabled"
            >
              {{ playerStore.equalizerEnabled ? "ON" : "OFF" }}
            </button>
          </div>
        </div>
        <div class="flex gap-4 justify-between">
          <div
            v-for="(gain, freq) in equalizer"
            :key="freq"
            class="flex-1 flex flex-col items-center"
          >
            <label class="text-xs text-white/80 mb-2 font-medium">{{
              formatFrequency(freq)
            }}</label>
            <div
              class="relative h-32 w-6 bg-gray-700 rounded-full flex items-end justify-center"
            >
              <input
                v-model="equalizer[freq]"
                type="range"
                class="eq-slider"
                min="-12"
                max="12"
                step="0.5"
                orient="vertical"
                @input="updateEqualizer"
                :disabled="!playerStore.equalizerEnabled"
              />
            </div>
            <span class="text-xs text-white/80 mt-2 font-mono"
              >{{ equalizer[freq] }}dB</span
            >
          </div>
        </div>
      </div>
    </div>

    <!-- Footer audio player -->
    <footer
      class="audio-player h-27 text-white backdrop-blur-md border-t border-white/10 transition-all duration-300"
      :class="[
        themeStore.backgroundGradient,
        !isPublicShareView && (sidebarCollapsed ? 'pl-18' : 'pl-64'),
      ]"
    >
      <div
        class="w-full h-full grid grid-cols-12 items-center gap-4"
        :class="isPublicShareView ? 'px-8 max-w-7xl mx-auto' : 'px-4'"
      >
        <!-- Track info -->
        <div class="col-span-3 flex items-center min-w-0">
          <div class="relative w-14 h-14 mr-3">
            <!-- Gradient placeholder background -->
            <div
              v-if="
                currentSong?.coverGradient && currentSong.coverGradient.colors
              "
              class="absolute inset-0 rounded-full"
              :style="{
                background: `linear-gradient(${currentSong.coverGradient.angle || 135}deg, ${currentSong.coverGradient.colors.join(', ')})`,
                filter: 'blur(10px)',
                zIndex: 1,
              }"
            ></div>
            <SimpleImage
              :imageType="'album_thumbnail'"
              :imageId="displayAlbumId"
              alt="Cover"
              class="w-14 h-14 rounded-full shadow-lg relative z-[2]"
              :class="{ 'animate-slow-spin': isPlaying }"
              :style="'object-fit: cover;'"
              :placeholder="'disc'"
              :placeholderSize="'28px'"
            />
          </div>
          <div class="track-info min-w-0 flex-1">
            <div
              class="clickable-text font-semibold text-audinary text-sm truncate cursor-pointer hover:text-blue-400 transition-colors"
              @click="handleSongClick"
              :title="$t('player.clickToViewAlbum')"
            >
              {{ displayTitle }}
            </div>
            <div class="text-xs text-white/80 truncate">
              <span
                class="clickable-text cursor-pointer hover:text-blue-400 transition-colors"
                @click="handleArtistClick"
                :title="$t('player.clickToViewArtistAlbums')"
                >{{ displayArtist }}</span
              >
            </div>
            <div class="text-xs text-white/70 truncate">
              <span
                class="clickable-text cursor-pointer hover:text-blue-400 transition-colors"
                @click="handleAlbumClick"
                :title="$t('player.clickToViewAlbum')"
                >{{ displayAlbum }}</span
              >
            </div>
          </div>
        </div>

        <!-- Controls -->
        <div class="col-span-6 flex flex-col items-center justify-center">
          <div class="flex items-center gap-4 mb-2">
            <button
              class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
              :class="{ 'text-blue-400': isShuffled }"
              @click="toggleShuffle"
              :title="$t('player.shuffle')"
            >
              <i class="bi bi-shuffle text-lg"></i>
            </button>
            <button
              class="w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              @click="previousSong"
              :disabled="!hasPrevious"
              :title="$t('player.previous')"
            >
              <i class="bi bi-skip-backward-fill text-xl"></i>
            </button>
            <button
              class="w-10 h-10 flex items-center justify-center bg-white text-black rounded-full hover:bg-gray-200 transition-colors shadow-lg"
              @click="togglePlayPause"
              :title="isPlaying ? $t('player.pause') : $t('player.play')"
            >
              <i :class="`${playPauseIcon} text-xl`"></i>
            </button>
            <button
              class="w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              @click="nextSong"
              :disabled="!hasNext"
              :title="$t('player.next')"
            >
              <i class="bi bi-skip-forward-fill text-xl"></i>
            </button>
            <button
              class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
              :class="{ 'text-blue-400': repeatMode !== 'none' }"
              @click="toggleRepeat"
              :title="$t('player.repeat')"
            >
              <i :class="repeatIcon + ' text-lg'"></i>
            </button>
          </div>
          <div class="flex items-center w-full max-w-md">
            <span
              class="text-xs text-white/80 mr-3 w-10 text-right font-mono"
              >{{ formatTime(currentTime) }}</span
            >
            <div
              class="relative flex-1 mx-2 h-1 bg-gray-600 rounded-full cursor-pointer group"
              @click="seekTo"
            >
              <div
                class="absolute left-0 top-0 h-full bg-audinary rounded-full transition-all duration-200 ease-linear"
                :style="{ width: progressPercentage + '%' }"
                role="progressbar"
                :aria-valuemin="0"
                :aria-valuemax="100"
                :aria-valuenow="progressPercentage"
              >
                <div
                  class="absolute right-0 top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                ></div>
              </div>
            </div>
            <span class="text-xs text-white/80 ml-3 w-10 text-left font-mono">{{
              formatTime(duration)
            }}</span>
          </div>
        </div>

        <!-- VU Meters and Controls -->
        <div class="col-span-3 flex flex-col items-end gap-2">
          <!-- VU Meters spanning full width -->
          <div class="vu-meters-full-width">
            <!-- Left Channel -->
            <div class="vu-meter-wide">
              <div class="vu-channel-label-wide">L</div>
              <div class="vu-bars-wide">
                <div
                  v-for="n in 27"
                  :key="`l-${n}`"
                  class="vu-led"
                  :class="{
                    active: vuMeterLeft >= n * 3.7,
                    danger: n > 22,
                    warning: n > 16 && n <= 22,
                  }"
                ></div>
              </div>
            </div>
            <!-- Right Channel -->
            <div class="vu-meter-wide">
              <div class="vu-channel-label-wide">R</div>
              <div class="vu-bars-wide">
                <div
                  v-for="n in 27"
                  :key="`r-${n}`"
                  class="vu-led"
                  :class="{
                    active: vuMeterRight >= n * 3.7,
                    danger: n > 22,
                    warning: n > 16 && n <= 22,
                  }"
                ></div>
              </div>
            </div>
          </div>

          <!-- Controls row -->
          <div class="controls-row">
            <button
              class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
              @click="toggleMute"
              :title="
                playerStore.isMuted
                  ? $t('player.volume.unmute')
                  : $t('player.volume.mute')
              "
            >
              <i :class="volumeIcon + ' text-base'"></i>
            </button>
            <input
              v-model="volume"
              type="range"
              class="w-20 h-1 bg-gray-600 rounded-lg appearance-none cursor-pointer slider"
              min="0"
              max="1"
              step="0.01"
              @input="updateVolume"
            />
            <button
              v-if="localModeAvailable"
              class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
              :class="{ 'text-blue-400': localModeEnabled }"
              @click="toggleLocalMode"
              :title="
                localModeEnabled
                  ? $t('player.modes.browserMode')
                  : $t('player.modes.localMode')
              "
            >
              <i class="bi bi-speaker text-base"></i>
            </button>
            <button
              class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors relative"
              @click="showQueue = true"
              :title="$t('player.queue.title')"
            >
              <i class="bi bi-list text-list text-base"></i>
              <span
                v-if="playerStore.upcomingQueue.length > 0"
                class="absolute -top-1 -right-1 bg-blue-600 text-[10px] text-white rounded-full w-4 h-4 flex items-center justify-center font-medium"
              >
                {{ playerStore.upcomingQueue.length }}
              </span>
            </button>
            <button
              class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
              :class="{ 'text-blue-400': showEqualizer }"
              @click="toggleEqualizer"
              :title="$t('player.equalizer')"
            >
              <i class="bi bi-sliders text-base"></i>
            </button>
            <button
              v-if="!showFullscreenPlayer"
              class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
              @click="toggleFullscreenPlayer"
              :title="$t('player.visualizer')"
            >
              <i class="bi bi-fullscreen text-base"></i>
            </button>
            <button
              v-else
              class="w-8 h-8 flex items-center justify-center text-blue-400 hover:text-white transition-colors"
              @click="toggleFullscreenPlayer"
              :title="$t('player.exitFullscreen')"
            >
              <i class="bi bi-fullscreen-exit text-base"></i>
            </button>
          </div>
        </div>
      </div>
    </footer>
  </div>

  <!-- Fullscreen Player -->
  <FullscreenPlayer
    v-if="showFullscreenPlayer"
    :isVisible="showFullscreenPlayer"
    :currentSong="currentSong"
    :isPlaying="isPlaying"
    :currentTime="currentTime"
    :duration="duration"
    :volume="volume"
    :isMuted="playerStore.isMuted"
    :isShuffled="isShuffled"
    :repeatMode="repeatMode"
    :hasPrevious="hasPrevious"
    :hasNext="hasNext"
    :upcomingQueue="playerStore.upcomingQueue"
    :audioAnalyser="getAudioAnalyser()"
    :logoUrl="'/img/icon.png'"
    @close="showFullscreenPlayer = false"
    @togglePlayPause="togglePlayPause"
    @previousSong="previousSong"
    @nextSong="nextSong"
    @toggleShuffle="toggleShuffle"
    @toggleRepeat="toggleRepeat"
    @toggleMute="toggleMute"
    @updateVolume="updateVolumeFromFullscreen"
    @seekTo="seekToTime"
    @playFromQueue="playFromQueue"
    @removeFromQueue="removeFromQueue"
  >
    <template #playerbar>
      <!-- Desktop Player Bar in Fullscreen -->
      <div v-if="shouldShowPlayer" class="w-full">
        <!-- EQ collapse above footer -->
        <div
          v-if="showEqualizer"
          class="fixed bottom-27 left-1/2 bottom-4 transform -translate-x-1/2 w-full max-w-4xl z-50"
        >
          <div
            class="text-white p-6 mx-4 rounded-lg border border-gray-700 shadow-2xl backdrop-blur-md"
            :class="themeStore.backgroundGradient"
          >
            <div class="flex justify-between items-center mb-4">
              <h6 class="text-lg font-semibold">7-Band Equalizer</h6>
              <div class="flex items-center gap-3">
                <button
                  class="px-3 py-1.5 text-sm rounded-md border border-gray-600 text-gray-300 hover:bg-gray-700 transition-colors"
                  @click="resetEqualizer"
                >
                  Reset
                </button>
                <button
                  class="px-3 py-1.5 text-sm rounded-md transition-colors"
                  :class="
                    playerStore.equalizerEnabled
                      ? 'bg-blue-600 text-white hover:bg-blue-700'
                      : 'border border-blue-600 text-blue-400 hover:bg-blue-600/10'
                  "
                  @click="toggleEqualizerEnabled"
                >
                  {{ playerStore.equalizerEnabled ? "ON" : "OFF" }}
                </button>
              </div>
            </div>
            <div class="flex gap-4 justify-between">
              <div
                v-for="(gain, freq) in equalizer"
                :key="freq"
                class="flex-1 flex flex-col items-center"
              >
                <label class="text-xs text-white/80 mb-2 font-medium">{{
                  formatFrequency(freq)
                }}</label>
                <div
                  class="relative h-32 w-6 bg-gray-700 rounded-full flex items-end justify-center"
                >
                  <input
                    v-model="equalizer[freq]"
                    type="range"
                    class="eq-slider"
                    min="-12"
                    max="12"
                    step="0.5"
                    orient="vertical"
                    @input="updateEqualizer"
                    :disabled="!playerStore.equalizerEnabled"
                  />
                </div>
                <span class="text-xs text-white/80 mt-2 font-mono"
                  >{{ equalizer[freq] }}dB</span
                >
              </div>
            </div>
          </div>
        </div>

        <!-- Fixed Footer audio player -->
        <footer
          class="audio-player h-27 text-white rounded-2xl px-4 shadow-2xl w-full max-w-4xl backdrop-blur-md mx-auto"
          :class="themeStore.backgroundGradient"
        >
          <div class="w-full px-4 h-full grid grid-cols-12 items-center gap-4">
            <!-- Track info -->
            <div class="col-span-3 flex items-center min-w-0">
              <div class="relative w-14 h-14 mr-3">
                <!-- Gradient placeholder background -->
                <div
                  v-if="
                    currentSong?.coverGradient &&
                    currentSong.coverGradient.colors
                  "
                  class="absolute inset-0 rounded-full"
                  :style="{
                    background: `linear-gradient(${currentSong.coverGradient.angle || 135}deg, ${currentSong.coverGradient.colors.join(', ')})`,
                    filter: 'blur(10px)',
                    zIndex: 1,
                  }"
                ></div>
                <SimpleImage
                  :imageType="'album_thumbnail'"
                  :imageId="displayAlbumId"
                  alt="Cover"
                  class="w-14 h-14 rounded-full shadow-lg relative z-[2]"
                  :class="{ 'animate-slow-spin': isPlaying }"
                  :style="'object-fit: cover;'"
                  :placeholder="'disc'"
                  :placeholderSize="'28px'"
                />
              </div>
              <div class="track-info min-w-0 flex-1">
                <div
                  class="clickable-text font-semibold text-audinary text-sm truncate cursor-pointer hover:text-blue-400 transition-colors"
                  @click="handleSongClick"
                  :title="$t('player.clickToViewAlbum')"
                >
                  {{ displayTitle }}
                </div>
                <div class="text-xs text-white/80 truncate">
                  <span
                    class="clickable-text cursor-pointer hover:text-blue-400 transition-colors"
                    @click="handleArtistClick"
                    :title="$t('player.clickToViewArtistAlbums')"
                    >{{ displayArtist }}</span
                  >
                </div>
                <div class="text-xs text-white/70 truncate">
                  <span
                    class="clickable-text cursor-pointer hover:text-blue-400 transition-colors"
                    @click="handleAlbumClick"
                    :title="$t('player.clickToViewAlbum')"
                    >{{ displayAlbum }}</span
                  >
                </div>
              </div>
            </div>

            <!-- Controls -->
            <div class="col-span-6 flex flex-col items-center justify-center">
              <div class="flex items-center gap-4 mb-2">
                <button
                  class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
                  :class="{ 'text-blue-400': isShuffled }"
                  @click="toggleShuffle"
                  :title="$t('player.shuffle')"
                >
                  <i class="bi bi-shuffle text-lg"></i>
                </button>
                <button
                  class="w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  @click="previousSong"
                  :disabled="!hasPrevious"
                  :title="$t('player.previous')"
                >
                  <i class="bi bi-skip-backward-fill text-xl"></i>
                </button>
                <button
                  class="w-10 h-10 flex items-center justify-center bg-white text-black rounded-full hover:bg-gray-200 transition-colors shadow-lg"
                  @click="togglePlayPause"
                  :title="isPlaying ? $t('player.pause') : $t('player.play')"
                >
                  <i :class="`${playPauseIcon} text-xl`"></i>
                </button>
                <button
                  class="w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  @click="nextSong"
                  :disabled="!hasNext"
                  :title="$t('player.next')"
                >
                  <i class="bi bi-skip-forward-fill text-xl"></i>
                </button>
                <button
                  class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
                  :class="{ 'text-blue-400': repeatMode !== 'none' }"
                  @click="toggleRepeat"
                  :title="$t('player.repeat')"
                >
                  <i :class="repeatIcon + ' text-lg'"></i>
                </button>
              </div>
              <div class="flex items-center w-full max-w-md">
                <span
                  class="text-xs text-white/80 mr-3 w-10 text-right font-mono"
                  >{{ formatTime(currentTime) }}</span
                >
                <div
                  class="relative flex-1 mx-2 h-1 bg-gray-600 rounded-full cursor-pointer group"
                  @click="seekTo"
                >
                  <div
                    class="absolute left-0 top-0 h-full bg-audinary rounded-full transition-all duration-200 ease-linear"
                    :style="{ width: progressPercentage + '%' }"
                    role="progressbar"
                    :aria-valuemin="0"
                    :aria-valuemax="100"
                    :aria-valuenow="progressPercentage"
                  >
                    <div
                      class="absolute right-0 top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                    ></div>
                  </div>
                </div>
                <span
                  class="text-xs text-white/80 ml-3 w-10 text-left font-mono"
                  >{{ formatTime(duration) }}</span
                >
              </div>
            </div>

            <!-- VU Meters and Controls -->
            <div class="col-span-3 flex flex-col items-end gap-2">
              <!-- VU Meters spanning full width -->
              <div class="vu-meters-full-width">
                <!-- Left Channel -->
                <div class="vu-meter-wide">
                  <div class="vu-channel-label-wide">L</div>
                  <div class="vu-bars-wide">
                    <div
                      v-for="n in 27"
                      :key="`l-${n}`"
                      class="vu-led"
                      :class="{
                        active: vuMeterLeft >= n * 3.7,
                        danger: n > 22,
                        warning: n > 16 && n <= 22,
                      }"
                    ></div>
                  </div>
                </div>
                <!-- Right Channel -->
                <div class="vu-meter-wide">
                  <div class="vu-channel-label-wide">R</div>
                  <div class="vu-bars-wide">
                    <div
                      v-for="n in 27"
                      :key="`r-${n}`"
                      class="vu-led"
                      :class="{
                        active: vuMeterRight >= n * 3.7,
                        danger: n > 22,
                        warning: n > 16 && n <= 22,
                      }"
                    ></div>
                  </div>
                </div>
              </div>

              <!-- Controls row -->
              <div class="controls-row">
                <button
                  class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
                  @click="toggleMute"
                  :title="
                    playerStore.isMuted
                      ? $t('player.volume.unmute')
                      : $t('player.volume.mute')
                  "
                >
                  <i :class="volumeIcon + ' text-base'"></i>
                </button>
                <input
                  v-model="volume"
                  type="range"
                  class="w-20 h-1 bg-gray-600 rounded-lg appearance-none cursor-pointer slider"
                  min="0"
                  max="1"
                  step="0.01"
                  @input="updateVolume"
                />
                <button
                  v-if="localModeAvailable"
                  class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
                  :class="{ 'text-blue-400': localModeEnabled }"
                  @click="toggleLocalMode"
                  :title="
                    localModeEnabled
                      ? $t('player.modes.browserMode')
                      : $t('player.modes.localMode')
                  "
                >
                  <i class="bi bi-speaker text-base"></i>
                </button>
                <button
                  class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors relative"
                  @click="showQueue = true"
                  :title="$t('player.queue.title')"
                >
                  <i class="bi bi-list text-list text-base"></i>
                  <span
                    v-if="playerStore.upcomingQueue.length > 0"
                    class="absolute -top-1 -right-1 bg-blue-600 text-[10px] text-white rounded-full w-4 h-4 flex items-center justify-center font-medium"
                  >
                    {{ playerStore.upcomingQueue.length }}
                  </span>
                </button>
                <button
                  class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white transition-colors"
                  :class="{ 'text-blue-400': showEqualizer }"
                  @click="toggleEqualizer"
                  :title="$t('player.equalizer')"
                >
                  <i class="bi bi-sliders text-base"></i>
                </button>
              </div>
            </div>
          </div>
        </footer>
      </div>
    </template>
  </FullscreenPlayer>

  <!-- Queue Modal -->
  <PlayerQueueModal v-if="showQueue" @close="showQueue = false" />

  <!-- Album Detail Modal -->
  <AlbumDetailModal
    :album="selectedAlbum"
    ref="albumDetailModal"
    @close="closeAlbumDetail"
    @album-updated="handleAlbumUpdated"
  />
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { usePlayerStore } from "@/stores/player";
import { useThemeStore } from "@/stores/theme";
import { useSidebar } from "@/composables/useSidebar";
import PlayerQueueModal from "@/components/modals/PlayerQueueModal.vue";
import AlbumDetailModal from "@/components/modals/AlbumDetailModal.vue";
import SimpleImage from "@/components/common/SimpleImage.vue";
import FullscreenPlayer from "./FullscreenPlayer.vue";

const router = useRouter();
const route = useRoute();
const playerStore = usePlayerStore();
const themeStore = useThemeStore();
const { isCollapsed: sidebarCollapsed } = useSidebar();

// Check if we're in a public share view (no sidebar)
const isPublicShareView = computed(() => route.name === "PublicShareView");

// Theme colors
const themeColors = computed(() => themeStore.getThemeColors);

// State
const showQueue = ref(false);
const showEqualizer = ref(false);
const showFullscreenPlayer = ref(false);
const selectedAlbum = ref(null);
const albumDetailModal = ref(null);
const volume = ref(0.5);
const equalizer = ref({
  60: 0,
  170: 0,
  310: 0,
  600: 0,
  1000: 0,
  3000: 0,
  12000: 0,
});

// Mobile specific state
const isMobile = ref(false);
const isExpanded = ref(false);
const showMobileQueue = ref(false);
const touchStartY = ref(0);
const touchStartTime = ref(0);
const swipeThreshold = 50;
const tapThreshold = 300;

// Cover swipe state
const coverSwipeOffset = ref(0);
const coverTouchStartX = ref(0);
const coverTouchStartY = ref(0);
const coverSwipeThreshold = 80;

// VU Meter state
const vuMeterLeft = ref(0);
const vuMeterRight = ref(0);
const vuSplitterNode = ref(null);
const vuLeftAnalyser = ref(null);
const vuRightAnalyser = ref(null);
const vuAnimationFrame = ref(null);
const vuSetupInProgress = ref(false);

// Computed
const currentSong = computed(() => playerStore.currentSong);
const isPlaying = computed(() => playerStore.isPlaying);
const currentTime = computed(() => playerStore.currentTime);
const duration = computed(() => playerStore.duration);
const isShuffled = computed(() => playerStore.isShuffleEnabled);
const repeatMode = computed(() => playerStore.repeatMode);
const hasPrevious = computed(() => playerStore.canPlayPrevious);
const hasNext = computed(() => playerStore.canPlayNext);
const localModeEnabled = computed(() => playerStore.isLocalMode);
const localModeAvailable = computed(() => playerStore.localModeEnabled);

// Player should be visible if there's a current song OR if the queue has songs
const shouldShowPlayer = computed(() => {
  return currentSong.value || playerStore.hasQueue;
});

// Computed for play/pause icon - shows play if no song is playing, pause if playing
const playPauseIcon = computed(() => {
  if (playerStore.isLoading) {
    return "bi bi-arrow-clockwise animate-spin";
  }
  return isPlaying.value ? "bi bi-pause-fill" : "bi bi-play-fill";
});

// Get album ID for cover image - from current song or first song in queue
const displayAlbumId = computed(() => {
  if (currentSong.value?.album_id) {
    return currentSong.value.album_id;
  }
  const next = playerStore.nextSongInQueue;
  return next?.album_id || "default";
});

// Get display title - from current song or first song in queue
const displayTitle = computed(() => {
  if (currentSong.value?.title) {
    return currentSong.value.title;
  }
  const next = playerStore.nextSongInQueue;
  return next?.title || "No song selected";
});

// Get display artist - from current song or first song in queue
const displayArtist = computed(() => {
  if (currentSong.value?.artist) {
    return currentSong.value.artist;
  }
  const next = playerStore.nextSongInQueue;
  return next?.artist || "-";
});

// Get display album - from current song or first song in queue
const displayAlbum = computed(() => {
  if (currentSong.value?.album) {
    return currentSong.value.album;
  }
  const next = playerStore.nextSongInQueue;
  return next?.album || "-";
});

const progressPercentage = computed(() => {
  if (!duration.value) return 0;
  return (currentTime.value / duration.value) * 100;
});

const volumeIcon = computed(() => {
  if (playerStore.isMuted || playerStore.volume === 0) {
    return "bi bi-volume-mute-fill fs-5";
  } else if (playerStore.volume < 0.5) {
    return "bi bi-volume-down-fill fs-5";
  } else {
    return "bi bi-volume-up-fill fs-5";
  }
});

const repeatIcon = computed(() => {
  switch (repeatMode.value) {
    case "one":
      return "bi bi-repeat-1 fs-5";
    case "all":
      return "bi bi-repeat fs-5";
    default:
      return "bi bi-repeat fs-5";
  }
});

// Methods
function checkIfMobile() {
  isMobile.value = window.innerWidth <= 768;
}

function togglePlayPause() {
  // If no song is currently playing but there are songs in the queue, start the first one
  if (!currentSong.value && playerStore.upcomingQueue.length > 0) {
    playerStore.playFromQueue(0);
  } else {
    playerStore.togglePlayPause();
  }
}

function previousSong() {
  playerStore.prevSong();
}

function nextSong() {
  playerStore.nextSong();
}

function toggleShuffle() {
  playerStore.toggleShuffle();
}

function toggleRepeat() {
  playerStore.toggleRepeat();
}

function seekTo(event) {
  const progressBar = event.currentTarget;
  const rect = progressBar.getBoundingClientRect();
  const clickX = event.clientX - rect.left;
  const percentage = clickX / rect.width;
  const seekTime = percentage * duration.value;
  playerStore.seek(seekTime);
}

function updateVolume() {
  playerStore.setVolume(volume.value);
}

function updateVolumeFromFullscreen(newVolume) {
  volume.value = parseFloat(newVolume);
  playerStore.setVolume(volume.value);
}

function toggleMute() {
  playerStore.toggleMute();
}

function toggleLocalMode() {
  playerStore.toggleLocalMode();
}

function toggleEqualizer() {
  showEqualizer.value = !showEqualizer.value;
}

function toggleFullscreenPlayer() {
  showFullscreenPlayer.value = !showFullscreenPlayer.value;
}

function seekToTime(time) {
  playerStore.seek(time);
}

function getAudioAnalyser() {
  // Return the audio analyser from the player store if available
  return playerStore.audioAnalyser || vuLeftAnalyser.value;
}

function updateEqualizer() {
  Object.keys(equalizer.value).forEach((frequency) => {
    playerStore.updateEqualizerBand(frequency, equalizer.value[frequency]);
  });
}

function resetEqualizer() {
  Object.keys(equalizer.value).forEach((freq) => {
    equalizer.value[freq] = 0;
  });
  playerStore.resetEqualizer();
}

function toggleEqualizerEnabled() {
  playerStore.toggleEqualizer();
}

function formatFrequency(freq) {
  const frequency = parseInt(freq);
  if (frequency >= 1000) {
    return `${frequency / 1000}kHz`;
  }
  return `${frequency}Hz`;
}

// VU Meter functions
function setupVUMeter() {
  // Prevent multiple simultaneous setups
  if (vuSetupInProgress.value) return;
  vuSetupInProgress.value = true;

  // Clean up previous setup first
  cleanupVUMeter();

  if (
    !playerStore.currentHowl ||
    !playerStore.audioContext ||
    playerStore.isLocalMode
  ) {
    vuSetupInProgress.value = false;
    return;
  }

  try {
    // Get the audio context from playerStore
    const audioContext = playerStore.audioContext;

    // Create analyser nodes for VU meter
    vuLeftAnalyser.value = audioContext.createAnalyser();
    vuRightAnalyser.value = audioContext.createAnalyser();

    // Create splitter to separate left/right channels
    vuSplitterNode.value = audioContext.createChannelSplitter(2);

    // Configure analysers for VU meter (lower fftSize for better performance)
    vuLeftAnalyser.value.fftSize = 512;
    vuRightAnalyser.value.fftSize = 512;
    vuLeftAnalyser.value.smoothingTimeConstant = 0.6;
    vuRightAnalyser.value.smoothingTimeConstant = 0.6;
    vuLeftAnalyser.value.minDecibels = -100;
    vuRightAnalyser.value.minDecibels = -100;
    vuLeftAnalyser.value.maxDecibels = -10;
    vuRightAnalyser.value.maxDecibels = -10;

    // Try different connection approaches
    let connected = false;

    // Approach 1: Connect via gainNode if available
    if (playerStore.gainNode) {
      try {
        playerStore.gainNode.connect(vuSplitterNode.value);
        vuSplitterNode.value.connect(vuLeftAnalyser.value, 0);
        vuSplitterNode.value.connect(vuRightAnalyser.value, 1);
        connected = true;
      } catch (err) {
        // Failed to connect via gainNode
      }
    }

    // Approach 2: Try to connect directly to Howler's audio node
    if (
      !connected &&
      playerStore.currentHowl._sounds &&
      playerStore.currentHowl._sounds[0]
    ) {
      try {
        const sound = playerStore.currentHowl._sounds[0];
        if (sound._node && sound._node._mediaElementSource) {
          sound._node._mediaElementSource.connect(vuSplitterNode.value);
          vuSplitterNode.value.connect(vuLeftAnalyser.value, 0);
          vuSplitterNode.value.connect(vuRightAnalyser.value, 1);
          connected = true;
        }
      } catch (err) {
        // Failed to connect via MediaElementSource
      }
    }

    // Approach 3: Create our own MediaElementSource
    if (
      !connected &&
      playerStore.currentHowl._sounds &&
      playerStore.currentHowl._sounds[0]
    ) {
      try {
        const sound = playerStore.currentHowl._sounds[0];
        if (sound._node) {
          const source = audioContext.createMediaElementSource(sound._node);
          source.connect(vuSplitterNode.value);
          source.connect(audioContext.destination); // Important: also connect to destination
          vuSplitterNode.value.connect(vuLeftAnalyser.value, 0);
          vuSplitterNode.value.connect(vuRightAnalyser.value, 1);
          connected = true;
        }
      } catch (err) {
        // Failed to create MediaElementSource
      }
    }

    if (connected) {
      startVUMeterAnimation();
    }
  } catch (error) {
    // VU Meter setup failed
  }

  vuSetupInProgress.value = false;
}

function updateVUMeter() {
  if (!vuLeftAnalyser.value || !vuRightAnalyser.value) return;

  try {
    const bufferLength = vuLeftAnalyser.value.frequencyBinCount;
    const leftDataArray = new Uint8Array(bufferLength);
    const rightDataArray = new Uint8Array(bufferLength);

    // Get time domain data for more accurate VU behavior
    vuLeftAnalyser.value.getByteTimeDomainData(leftDataArray);
    vuRightAnalyser.value.getByteTimeDomainData(rightDataArray);

    // Calculate RMS for more accurate VU meter readings
    let leftSum = 0;
    let rightSum = 0;

    for (let i = 0; i < bufferLength; i++) {
      const leftSample = (leftDataArray[i] - 128) / 128.0;
      const rightSample = (rightDataArray[i] - 128) / 128.0;
      leftSum += leftSample * leftSample;
      rightSum += rightSample * rightSample;
    }

    const leftRMS = Math.sqrt(leftSum / bufferLength);
    const rightRMS = Math.sqrt(rightSum / bufferLength);

    // Convert to percentage with better scaling and apply logarithmic curve
    let leftLevel = Math.min(100, Math.pow(leftRMS * 3, 0.7) * 100);
    let rightLevel = Math.min(100, Math.pow(rightRMS * 3, 0.7) * 100);

    // Apply stronger smoothing to prevent jittery movement
    const smoothingFactor = 0.85; // Higher value = more smoothing
    vuMeterLeft.value =
      vuMeterLeft.value * smoothingFactor + leftLevel * (1 - smoothingFactor);
    vuMeterRight.value =
      vuMeterRight.value * smoothingFactor + rightLevel * (1 - smoothingFactor);

    // Apply minimum threshold to reduce noise
    if (vuMeterLeft.value < 2) vuMeterLeft.value = 0;
    if (vuMeterRight.value < 2) vuMeterRight.value = 0;
  } catch (error) {
    // Silently fail to avoid console spam
  }
}

function startVUMeterAnimation() {
  if (vuAnimationFrame.value) return;

  let frameCount = 0;

  const animate = () => {
    frameCount++;

    // Update VU meter only every 3rd frame (20 FPS instead of 60 FPS)
    if (frameCount % 3 === 0) {
      if (isPlaying.value && !playerStore.isLocalMode) {
        updateVUMeter();
      } else {
        // Gradually decay to 0 when not playing
        vuMeterLeft.value = Math.max(0, vuMeterLeft.value * 0.95);
        vuMeterRight.value = Math.max(0, vuMeterRight.value * 0.95);
      }
    }

    vuAnimationFrame.value = requestAnimationFrame(animate);
  };

  animate();
}

function stopVUMeterAnimation() {
  if (vuAnimationFrame.value) {
    cancelAnimationFrame(vuAnimationFrame.value);
    vuAnimationFrame.value = null;
  }
  vuMeterLeft.value = 0;
  vuMeterRight.value = 0;
}

function cleanupVUMeter() {
  stopVUMeterAnimation();

  try {
    if (vuSplitterNode.value) {
      vuSplitterNode.value.disconnect();
      vuSplitterNode.value = null;
    }
    if (vuLeftAnalyser.value) {
      vuLeftAnalyser.value.disconnect();
      vuLeftAnalyser.value = null;
    }
    if (vuRightAnalyser.value) {
      vuRightAnalyser.value.disconnect();
      vuRightAnalyser.value = null;
    }
  } catch (error) {
    // Ignore cleanup errors
  }

  vuSetupInProgress.value = false;
}

function formatTime(seconds) {
  if (!seconds || isNaN(seconds)) return "0:00";

  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = Math.floor(seconds % 60);
  return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
}

// Click handlers for navigation
function handleSongClick() {
  if (!currentSong.value) return;

  // Check if we're in a public share context
  if (route.name === "PublicShareView") {
    // In public share - no navigation, maybe show toast
    return;
  }

  showAlbumDetail(currentSong.value);
}

function handleArtistClick() {
  if (!currentSong.value?.artist) return;

  // Check if we're in a public share context
  if (route.name === "PublicShareView") {
    // In public share - no navigation, maybe show toast
    return;
  }

  router.push({
    path: "/",
    query: {
      tab: "albums",
      artist: currentSong.value.artist,
    },
  });
}

function handleAlbumClick() {
  if (!currentSong.value) return;

  // Check if we're in a public share context
  if (route.name === "PublicShareView") {
    // In public share - no navigation, maybe show toast
    return;
  }

  showAlbumDetail(currentSong.value);
}

function showAlbumDetail(song) {
  if (!song || !song.album_id) {
    console.warn("Cannot show album detail: missing album_id", song);
    return;
  }

  // Create minimal album object - keep it simple to avoid performance issues
  selectedAlbum.value = {
    album_id: song.album_id,
    albumName: song.album,
    album_name: song.album,
    artistName: song.artist,
    album_artist: song.artist,
    albumYear: song.album_year || song.year || "",
    album_year: song.album_year || song.year || "",
    genre: song.album_genre || song.genre || "",
    albumGenre: song.album_genre || song.genre || "",
    albumDuration: song.album_duration || 0,
    album_duration: song.album_duration || 0,
  };

  if (albumDetailModal.value) {
    albumDetailModal.value.show();
  }
}

function closeAlbumDetail() {
  selectedAlbum.value = null;
}

function handleAlbumUpdated(updatedAlbum) {
  // Optional: Handle album updates if needed
}

// Mobile player methods
function expandPlayer() {
  if (isMobile.value) {
    isExpanded.value = true;
    // Prevent scrolling on body when expanded
    document.body.style.overflow = "hidden";
  }
}

function collapsePlayer() {
  isExpanded.value = false;
  showMobileQueue.value = false; // Reset queue view when collapsing
  // Restore body scrolling
  document.body.style.overflow = "";
}

function toggleMobileQueue() {
  showMobileQueue.value = !showMobileQueue.value;
}

function playFromQueue(index) {
  playerStore.playFromQueue(index);
}

function removeFromQueue(index) {
  playerStore.removeFromQueue(index);
}

function playFromHistory(index) {
  playerStore.playFromHistory(index);
}

// Touch handlers for mini player
function handleTouchStart(event) {
  touchStartY.value = event.touches[0].clientY;
  touchStartTime.value = Date.now();
}

function handleTouchMove(event) {
  if (!touchStartY.value) return;

  const currentY = event.touches[0].clientY;
  const deltaY = touchStartY.value - currentY;

  // Prevent scrolling when swiping up on player
  if (deltaY > 0) {
    event.preventDefault();
  }
}

function handleTouchEnd(event) {
  if (!touchStartY.value) return;

  const touchEndY = event.changedTouches[0].clientY;
  const deltaY = touchStartY.value - touchEndY;
  const deltaTime = Date.now() - touchStartTime.value;

  // Check for upward swipe to expand
  if (deltaY > swipeThreshold) {
    expandPlayer();
  }
  // Check for tap (short touch with minimal movement)
  else if (Math.abs(deltaY) < 10 && deltaTime < tapThreshold) {
    expandPlayer();
  }

  touchStartY.value = 0;
  touchStartTime.value = 0;
}

// Touch handlers for expanded player
function handleExpandedTouchStart(event) {
  touchStartY.value = event.touches[0].clientY;
  touchStartTime.value = Date.now();
}

function handleExpandedTouchMove(event) {
  if (!touchStartY.value) return;

  const currentY = event.touches[0].clientY;
  const deltaY = currentY - touchStartY.value;

  // Only allow downward swipes to close
  if (deltaY > 0 && event.touches[0].clientY < window.innerHeight * 0.3) {
    // Allow closing gesture only from top area
    event.preventDefault();
  }
}

function handleExpandedTouchEnd(event) {
  if (!touchStartY.value) return;

  const touchEndY = event.changedTouches[0].clientY;
  const deltaY = touchEndY - touchStartY.value;

  // Check for downward swipe to collapse (only from top area)
  if (deltaY > swipeThreshold && touchStartY.value < window.innerHeight * 0.3) {
    collapsePlayer();
  }

  touchStartY.value = 0;
  touchStartTime.value = 0;
}

// Cover swipe handlers for song navigation
function handleCoverTouchStart(event) {
  if (showMobileQueue.value) return; // Don't handle swipes when queue is shown

  coverTouchStartX.value = event.touches[0].clientX;
  coverTouchStartY.value = event.touches[0].clientY;
  coverSwipeOffset.value = 0;
}

function handleCoverTouchMove(event) {
  if (showMobileQueue.value || !coverTouchStartX.value) return;

  const currentX = event.touches[0].clientX;
  const currentY = event.touches[0].clientY;
  const deltaX = currentX - coverTouchStartX.value;
  const deltaY = Math.abs(currentY - coverTouchStartY.value);

  // Only handle horizontal swipes (prevent interference with vertical scrolling)
  if (Math.abs(deltaX) > deltaY && Math.abs(deltaX) > 10) {
    event.preventDefault();

    // Limit swipe offset for visual feedback
    const maxOffset = 100;
    coverSwipeOffset.value = Math.max(
      -maxOffset,
      Math.min(maxOffset, deltaX * 0.8),
    );
  }
}

function handleCoverTouchEnd(event) {
  if (showMobileQueue.value || !coverTouchStartX.value) return;

  const touchEndX = event.changedTouches[0].clientX;
  const deltaX = touchEndX - coverTouchStartX.value;

  // Check for swipe gestures
  if (Math.abs(deltaX) > coverSwipeThreshold) {
    if (deltaX > 0) {
      // Swipe right - previous song
      if (hasPrevious.value) {
        previousSong();
      }
    } else {
      // Swipe left - next song
      if (hasNext.value) {
        nextSong();
      }
    }
  }

  // Reset swipe state with smooth animation
  setTimeout(() => {
    coverSwipeOffset.value = 0;
  }, 100);

  coverTouchStartX.value = 0;
  coverTouchStartY.value = 0;
}

function checkTextOverflow() {
  document.querySelectorAll(".text-scroll").forEach((element) => {
    // Reset any transform to get true scroll width
    element.style.transform = "none";
    const scrollWidth = element.scrollWidth;
    const clientWidth = element.clientWidth;

    // Add or remove class based on whether text fits
    if (scrollWidth > clientWidth) {
      element.classList.remove("text-fits");
    } else {
      element.classList.add("text-fits");
    }
  });
}

// Watch for volume changes from store
watch(
  () => playerStore.volume,
  (newVolume) => {
    volume.value = newVolume;
  },
);

// Watch for equalizer changes from store
watch(
  () => playerStore.equalizerGains,
  (newGains) => {
    Object.keys(newGains).forEach((freq) => {
      if (equalizer.value.hasOwnProperty(freq)) {
        equalizer.value[freq] = newGains[freq];
      }
    });
  },
  { deep: true },
);

// Watch for current song changes to check text overflow
watch(currentSong, () => {
  setTimeout(checkTextOverflow, 100);
});

// Watch for playing state changes to manage VU meter
watch(isPlaying, (newValue) => {
  if (newValue && currentSong.value) {
    // Single setup attempt with reasonable delay
    setTimeout(() => setupVUMeter(), 1000);
  } else {
    stopVUMeterAnimation();
  }
});

// Watch for current song changes to reset VU meter
watch(currentSong, (newSong, oldSong) => {
  if (newSong?.id !== oldSong?.id) {
    cleanupVUMeter();
    vuMeterLeft.value = 0;
    vuMeterRight.value = 0;

    if (newSong && isPlaying.value) {
      // Single setup attempt when song changes
      setTimeout(() => setupVUMeter(), 1500);
    }
  }
});

// Watch for audio context and gainNode changes
watch(
  () => playerStore.gainNode,
  (newGainNode) => {
    if (
      newGainNode &&
      isPlaying.value &&
      currentSong.value &&
      !vuSetupInProgress.value
    ) {
      setTimeout(() => setupVUMeter(), 500);
    }
  },
);

// Watch for window resize to update mobile state
function handleResize() {
  checkIfMobile();
  checkTextOverflow();

  // Close expanded player if switching to desktop
  if (!isMobile.value && isExpanded.value) {
    collapsePlayer();
  }
}

// Lifecycle
onMounted(() => {
  // Initialize player store
  playerStore.initialize();

  // Initialize volume
  volume.value = playerStore.volume;

  // Initialize equalizer values
  Object.keys(playerStore.equalizerGains).forEach((freq) => {
    if (equalizer.value.hasOwnProperty(freq)) {
      equalizer.value[freq] = playerStore.equalizerGains[freq];
    }
  });

  // Check if mobile
  checkIfMobile();

  // Set up text overflow checking
  checkTextOverflow();
  window.addEventListener("resize", handleResize);

  // Set up mutation observer for text changes (temporarily disabled for performance testing)
  // const observer = new MutationObserver(checkTextOverflow)
  // document.querySelectorAll('.text-scroll').forEach(element => {
  //   observer.observe(element, { childList: true, characterData: true, subtree: true })
  // })

  // Handle escape key to close expanded player and fullscreen player
  const handleKeyDown = (event) => {
    if (event.key === "Escape") {
      if (showFullscreenPlayer.value) {
        showFullscreenPlayer.value = false;
      } else if (isExpanded.value) {
        collapsePlayer();
      }
    }
  };

  document.addEventListener("keydown", handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener("resize", handleResize);
  document.body.style.overflow = ""; // Restore scrolling on cleanup

  // Cleanup VU meter
  cleanupVUMeter();

  // Remove keydown listener
  document.removeEventListener("keydown", handleKeyDown);
});
</script>

<style scoped>
/* Desktop Player */
.audio-player {
  backdrop-filter: blur(10px);
}

#eqCollapse {
  position: fixed;
  bottom: var(--player-height);
  left: 0;
  width: 100%;
  z-index: 1060;
}

/* Mobile Player Styles */
.mobile-player-container {
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  z-index: 1065;
}

.mobile-mini-player {
  background: linear-gradient(135deg, #243b55 0%, #1a2d42 100%);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  color: white;
  height: 70px;
  position: relative;
  cursor: pointer;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  transition: all 0.3s ease;
}

.mobile-mini-player:active {
  transform: scale(0.98);
}

.mini-player-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  height: 100%;
}

.track-info-mobile {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
}

.mini-cover {
  width: 50px;
  height: 50px;
  border-radius: 8px;
  object-fit: cover;
  margin-right: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.track-text {
  min-width: 0;
  flex: 1;
}

.track-title {
  font-weight: 600;
  font-size: 14px;
  color: white;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 2px;
}

.track-artist {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mini-controls {
  display: flex;
  align-items: center;
  margin-left: 16px;
}

.mobile-progress-bar {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 2px;
  background: rgba(255, 255, 255, 0.2);
  cursor: pointer;
}

.mobile-progress-fill {
  height: 100%;
  background: #007aff;
  transition: width 0.2s linear;
}

/* Mobile Expanded Player */
.mobile-expanded-player {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100vh;
  background: linear-gradient(180deg, #1a2d42 0%, #243b55 50%, #2a4a65 100%);
  color: white;
  z-index: 1070;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s ease-out;
  backdrop-filter: blur(30px);
  -webkit-backdrop-filter: blur(30px);
}

@keyframes slideUp {
  from {
    transform: translateY(100%);
  }
  to {
    transform: translateY(0);
  }
}

.expanded-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  padding-top: calc(env(safe-area-inset-top) + 16px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.expanded-title {
  font-weight: 600;
  font-size: 16px;
}

.expanded-cover-container {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 40px 20px;
}

.expanded-cover {
  width: min(320px, 80vw);
  height: min(320px, 80vw);
  border-radius: 12px;
  object-fit: cover;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.5);
}

.expanded-track-info {
  text-align: center;
  padding: 20px 40px 30px;
}

.expanded-track-title {
  font-size: 22px;
  font-weight: 700;
  margin-bottom: 8px;
  color: white;
}

.expanded-track-artist {
  font-size: 18px;
  color: rgba(255, 255, 255, 0.8);
  margin-bottom: 4px;
}

.expanded-track-album {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
}

.expanded-progress-container {
  padding: 0 40px 30px;
}

.expanded-progress {
  height: 0.8rem;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 2px;
  margin-bottom: 12px;
  cursor: pointer;
}

.expanded-progress-fill {
  height: 100%;
  background: #007aff;
  border-radius: 2px;
  transition: width 0.2s linear;
}

.time-info {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
}

.expanded-main-controls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  padding: 20px 40px;
}

.play-pause-btn {
  background: rgba(255, 255, 255, 0.15);
  border-radius: 50%;
  width: 80px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  transition: all 0.2s ease;
}

.play-pause-btn:hover,
.play-pause-btn:focus {
  background: rgba(255, 255, 255, 0.25);
  transform: scale(1.05);
}

.expanded-secondary-controls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 20px 40px;
  padding-bottom: calc(env(safe-area-inset-bottom) + 20px);
}

.volume-slider {
  flex: 1;
  max-width: 200px;
  margin: 0 16px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 10px;
  outline: none;
  -webkit-appearance: none;
  appearance: none;
  height: 4px;
}

.volume-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #007aff;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 122, 255, 0.3);
}

.volume-slider::-moz-range-thumb {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #007aff;
  cursor: pointer;
  border: none;
  box-shadow: 0 2px 8px rgba(0, 122, 255, 0.3);
}

.mobile-equalizer {
  background: rgba(0, 0, 0, 0.2);
  margin: 0 20px 20px;
  border-radius: 12px;
  padding: 20px;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.mobile-eq-controls {
  display: flex;
  gap: 8px;
  justify-content: space-around;
  flex-wrap: wrap;
}

.mobile-eq-slider-group {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  min-width: 0;
}

.mobile-eq-label {
  font-size: 10px;
  color: rgba(255, 255, 255, 0.8);
  margin-bottom: 8px;
  font-weight: 500;
  text-align: center;
}

.mobile-eq-slider {
  writing-mode: vertical-lr;
  direction: rtl;
  width: 4px;
  height: 80px;
  background: rgba(255, 255, 255, 0.2);
  outline: none;
  border-radius: 2px;
  margin-bottom: 8px;
}

.mobile-eq-value {
  font-size: 9px;
  color: rgba(255, 255, 255, 0.6);
  text-align: center;
}

/* Desktop Equalizer */
.equalizer-bands {
  padding: 20px 0;
}

.eq-band {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.eq-slider {
  writing-mode: vertical-lr;
  direction: rtl;
  width: 25px;
  height: 128px;
  background: rgba(255, 255, 255, 0.2);
  outline: none;
  border-radius: 4px;
  cursor: pointer;
}

.eq-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--bs-primary);
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.eq-slider::-moz-range-thumb {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--bs-primary);
  cursor: pointer;
  border: none;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.eq-slider:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.eq-value {
  min-height: 20px;
  font-size: 11px;
}

.text-scroll {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  display: block;
}

@media screen and (any-hover: hover) {
  .text-scroll:hover {
    animation: none; /* Default: no animation */
  }

  /* Only apply animation if content is wider than container */
  .text-scroll:hover:not(.text-fits) {
    animation: scrollText 5s linear infinite;
    text-overflow: clip;
  }
}

@keyframes scrollText {
  0%,
  10% {
    transform: translateX(0);
  }
  90%,
  100% {
    transform: translateX(calc(-100% + 100%));
  }
}

.track-info-scroll {
  overflow: hidden;
  width: 100%;
}

.spinning {
  animation: spin 5s linear infinite;
  border-radius: 50% !important;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.progress-bar {
  transition: width 0.2s linear;
}

.btn:disabled {
  opacity: 0.5;
}

.text-primary {
  color: var(--bs-primary) !important;
}

.badge {
  font-size: 0.6rem;
  padding: 0.2em 0.4em;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .audio-player {
    display: none !important;
  }

  #eqCollapse {
    display: none !important;
  }
}

@media (min-width: 769px) {
  .mobile-player-container {
    display: none !important;
  }
}

/* Safe area adjustments for iOS */
@supports (padding: max(0px)) {
  .expanded-header {
    padding-top: max(16px, env(safe-area-inset-top));
  }

  .expanded-secondary-controls {
    padding-bottom: max(20px, env(safe-area-inset-bottom));
  }
}

/* Mobile Queue Styles */
.mobile-queue-container {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  max-height: calc(100vh - 200px);
}

.queue-section {
  margin-bottom: 30px;
}

.queue-section-title {
  font-size: 14px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.8);
  margin-bottom: 12px;
  padding: 0 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.queue-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.queue-item {
  display: flex;
  align-items: center;
  padding: 12px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  cursor: pointer;
  transition: all 0.2s ease;
}

.queue-item:hover,
.queue-item:active {
  background: rgba(255, 255, 255, 0.1);
  transform: scale(0.98);
}

.queue-item.current-song {
  background: rgba(0, 122, 255, 0.2);
  border-color: rgba(0, 122, 255, 0.3);
}

.queue-item-cover {
  width: 50px;
  height: 50px;
  border-radius: 8px;
  object-fit: cover;
  margin-right: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.queue-item-info {
  flex: 1;
  min-width: 0;
}

.queue-item-title {
  font-weight: 600;
  font-size: 15px;
  color: white;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 4px;
}

.queue-item-artist {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.queue-item-actions {
  margin-left: 12px;
  display: flex;
  align-items: center;
}

.queue-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 60px 20px;
  color: rgba(255, 255, 255, 0.5);
}

/* Scrollbar for queue */
.mobile-queue-container::-webkit-scrollbar {
  width: 4px;
}

.mobile-queue-container::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 2px;
}

.mobile-queue-container::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 2px;
}

.mobile-queue-container::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Clickable text styles */
.clickable-text {
  cursor: pointer;
  transition:
    opacity 0.2s ease,
    color 0.2s ease;
}

.clickable-text:hover {
  opacity: 0.8;
  text-decoration: underline;
  color: white;
}

.clickable-text:active {
  transform: scale(0.98);
}

/* Slow spin animation for album covers */
.animate-slow-spin {
  animation: slow-spin 8s linear infinite;
}

@keyframes slow-spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* Full Width VU Meters */
.vu-meters-full-width {
  display: flex;
  flex-direction: column;
  gap: 1px;
  width: 100%;
  max-width: 220px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 6px;
  padding: 4px;
  border: 1px solid rgba(55, 65, 81, 0.4);
  margin-left: auto;
}

.vu-meter-wide {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
}

.vu-channel-label-wide {
  font-size: 10px;
  font-weight: 700;
  color: #9ca3af;
  min-width: 12px;
  text-align: center;
  flex-shrink: 0;
}

.vu-bars-wide {
  display: flex;
  align-items: center;
  gap: 1px;
  height: 6px;
  flex: 1;
}

.vu-led {
  flex: 1;
  height: 6px;
  background: rgba(55, 65, 81, 0.3);
  border-radius: 1px;
  transition: all 0.08s ease-out;
  min-width: 2px;
  max-width: 6px;
}

.vu-led.active {
  background: linear-gradient(to right, #10b981, #34d399);
  box-shadow: 0 0 2px rgba(16, 185, 129, 0.4);
}

.vu-led.active.warning {
  background: linear-gradient(to right, #f59e0b, #fbbf24);
  box-shadow: 0 0 2px rgba(245, 158, 11, 0.4);
}

.vu-led.active.danger {
  background: linear-gradient(to right, #ef4444, #f87171);
  box-shadow: 0 0 3px rgba(239, 68, 68, 0.5);
  animation: pulse-led 0.3s ease-in-out infinite alternate;
}

@keyframes pulse-led {
  from {
    transform: scaleY(1);
    box-shadow: 0 0 3px rgba(239, 68, 68, 0.5);
  }
  to {
    transform: scaleY(1.3);
    box-shadow: 0 0 5px rgba(239, 68, 68, 0.8);
  }
}

/* Controls Row */
.controls-row {
  display: flex;
  align-items: center;
  gap: 2px;
  width: 100%;
  justify-content: flex-end;
}

/* Mobile VU Meters */
.mobile-vu-meter {
  display: flex;
  align-items: center;
  gap: 2px;
  background: rgba(0, 0, 0, 0.3);
  padding: 1px 4px;
  border-radius: 3px;
  border: 1px solid rgba(55, 65, 81, 0.4);
}

.vu-channel-label-mobile {
  font-size: 8px;
  font-weight: 600;
  color: #9ca3af;
  min-width: 6px;
  text-align: center;
}

.vu-bars-horizontal-mobile {
  display: flex;
  align-items: center;
  gap: 0.5px;
  height: 6px;
}

.vu-bar-h-mobile {
  width: 2px;
  height: 6px;
  background: rgba(55, 65, 81, 0.4);
  border-radius: 1px;
  transition: all 0.1s ease-out;
}

.vu-bar-h-mobile.active {
  background: linear-gradient(to top, #10b981, #34d399);
  box-shadow: 0 0 1px rgba(16, 185, 129, 0.3);
}

.vu-bar-h-mobile.active.warning {
  background: linear-gradient(to top, #f59e0b, #fbbf24);
  box-shadow: 0 0 1px rgba(245, 158, 11, 0.3);
}

.vu-bar-h-mobile.active.danger {
  background: linear-gradient(to top, #ef4444, #f87171);
  box-shadow: 0 0 2px rgba(239, 68, 68, 0.4);
  animation: pulse-danger-h 0.4s ease-in-out infinite alternate;
}
</style>
