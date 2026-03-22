import { defineStore } from "pinia";
import { ref, shallowRef, computed } from "vue";
import { useApiStore } from "./api";
import { useAuthStore } from "./auth";
import { useAlertStore } from "./alert";
import { useI18n } from "vue-i18n";
import { Howl } from "howler";

// Helper function für i18n - funktioniert auch wenn Store nicht in Setup-Kontext aufgerufen wird
function getI18nMessage(key, fallback = key) {
  try {
    // Versuche globale i18n-Instanz zu verwenden
    if (window.__VUE_I18N__ && window.__VUE_I18N__.global) {
      return window.__VUE_I18N__.global.t(key);
    }
    return fallback;
  } catch {
    return fallback;
  }
}

export const usePlayerStore = defineStore("player", () => {
  // ── Plain variables (NOT reactive) ──
  let currentHowl = null;
  let nextHowl = null;
  let audioContext = null;
  let currentStreamUrl = null;
  let isTranscodedStream = false;
  let transcodedSeekOffset = 0; // Offset in seconds for transcoded stream seeking
  let bassFilter = null;
  let midFilter = null;
  let trebleFilter = null;
  let equalizerFilters = {};
  let progressInterval = null;
  let mpdUpdateTimer = null;
  let logoutHandler = null;
  let _keydownHandler = null;

  // ── shallowRef (arrays) ──
  const actualPlayQueue = shallowRef([]);
  const originalOrderQueue = shallowRef([]);
  const previousQueue = shallowRef([]);

  // ── ref (gainNode — components watch this) ──
  const gainNode = ref(null);

  // ── ref (everything else) ──
  const currentSong = ref(null);
  const currentSongIndexInActualPlayQueue = ref(-1);
  const isPlaying = ref(false);
  const isPaused = ref(false);
  const isLoading = ref(false);
  const currentSongLogged = ref(false);
  const isTransitioningSong = ref(false);
  const volume = ref(0.5);
  const lastVolume = ref(0.5);
  const isMuted = ref(false);
  const muteAlertId = ref(null);
  const duration = ref(0);
  const currentTime = ref(0);
  const isShuffleEnabled = ref(false);
  const repeatMode = ref("none"); // 'none', 'one', 'all'
  const isLocalMode = ref(false);
  const localModeEnabled = ref(false);
  const mpdEnabled = ref(false);
  const serverDeviceInfo = ref(null);
  const audioProcessingEnabled = ref(true);
  const equalizerEnabled = ref(true);
  const equalizerGains = ref({
    60: 0, // 60Hz - Sub Bass
    170: 0, // 170Hz - Bass
    310: 0, // 310Hz - Low Mid
    600: 0, // 600Hz - Mid
    1000: 0, // 1kHz - Upper Mid
    3000: 0, // 3kHz - Presence
    12000: 0, // 12kHz - Brilliance
  });
  const showFullscreen = ref(false);
  const showQueue = ref(false);
  const showEqualizer = ref(false);

  // ── Computed (getters) ──
  const hasQueue = computed(() => actualPlayQueue.value.length > 0);

  // Length of upcoming queue — cheap, no array allocation
  const upcomingQueueLength = computed(() => {
    if (
      currentSongIndexInActualPlayQueue.value === -1 ||
      actualPlayQueue.value.length === 0
    ) {
      return actualPlayQueue.value.length;
    }
    return actualPlayQueue.value.length - currentSongIndexInActualPlayQueue.value - 1;
  });

  // First 5 upcoming songs — use for previews without allocating the full array
  const upcomingQueuePreview = computed(() => {
    const start = currentSongIndexInActualPlayQueue.value === -1
      ? 0
      : currentSongIndexInActualPlayQueue.value + 1;
    return actualPlayQueue.value.slice(start, start + 5);
  });

  // Nur die kommenden Songs in der Queue (ohne den aktuell spielenden)
  // IMPORTANT: Only use when you actually need the full array (queue modal, reordering).
  // For length checks use upcomingQueueLength, for previews use upcomingQueuePreview.
  const upcomingQueue = computed(() => {
    if (
      currentSongIndexInActualPlayQueue.value === -1 ||
      actualPlayQueue.value.length === 0
    ) {
      return actualPlayQueue.value;
    }
    return actualPlayQueue.value.slice(
      currentSongIndexInActualPlayQueue.value + 1,
    );
  });

  const canPlayPrevious = computed(() => {
    if (repeatMode.value === "all" && actualPlayQueue.value.length > 0)
      return true;
    return currentSongIndexInActualPlayQueue.value > 0;
  });

  const canPlayNext = computed(() => {
    if (repeatMode.value === "all" && actualPlayQueue.value.length > 0)
      return true;
    if (repeatMode.value === "one") return true;
    return (
      currentSongIndexInActualPlayQueue.value <
      actualPlayQueue.value.length - 1
    );
  });

  const currentSongProgress = computed(() => {
    if (!duration.value) return 0;
    return (currentTime.value / duration.value) * 100;
  });

  const formattedCurrentTime = computed(() => {
    return formatTime(currentTime.value);
  });

  const formattedDuration = computed(() => {
    return formatTime(duration.value);
  });

  const nextSongInQueue = computed(() => {
    const nextIndex = getNextSongIndex({
      actualPlayQueue: actualPlayQueue.value,
      currentSongIndexInActualPlayQueue: currentSongIndexInActualPlayQueue.value,
      repeatMode: repeatMode.value,
    });
    return nextIndex !== -1 ? actualPlayQueue.value[nextIndex] : null;
  });

  // ── Functions (actions) ──

  // Initialize player
  function initialize() {
    // Listen for logout events to clean up player
    if (typeof window !== "undefined") {
      logoutHandler = () => handleUserLogout();
      window.addEventListener("user-logged-out", logoutHandler);
    }
    setupEventListeners();
    checkLocalModeAvailability();
    loadPlayerState();
  }

  // Error handling
  function handleError(context, error, fallbackAction = null) {
    console.error(`Player Error in ${context}:`, error);

    if (typeof fallbackAction === "function") {
      try {
        fallbackAction();
      } catch (fallbackError) {
        console.error(`Error in fallback for ${context}:`, fallbackError);
      }
    }
  }

  // Interval management
  function clearAllIntervals() {
    if (progressInterval) {
      clearInterval(progressInterval);
      progressInterval = null;
    }

    if (mpdUpdateTimer) {
      clearInterval(mpdUpdateTimer);
      mpdUpdateTimer = null;
    }
  }

  function startProgressInterval() {
    clearAllIntervals();
    progressInterval = setInterval(() => {
      updateProgress();
    }, 250);
  }

  // Progress tracking
  function updateProgress() {
    if (isTransitioningSong.value) return; // Nicht loggen während eines manuellen Übergangs

    if (isLocalMode.value) {
      // MPD mode - get progress from server
      updateMpdProgress();
    } else if (currentHowl && isPlaying.value) {
      // Howler mode - get progress from Howler
      currentTime.value = (currentHowl.seek() || 0) + transcodedSeekOffset;

      // Fallback: get duration from Howler if still missing
      if (!duration.value) {
        const howlDuration = currentHowl.duration();
        if (howlDuration && howlDuration > 0 && isFinite(howlDuration)) {
          duration.value = howlDuration;
        }
      }

      // Log played song if > 60% and not already logged
      if (
        currentSong.value &&
        !currentSongLogged.value &&
        duration.value > 0 &&
        currentTime.value / duration.value >= 0.6
      ) {
        // Check if we're in a public/shared playlist context - don't log plays there
        const currentPath = window.location.pathname;
        const isPublicContext =
          currentPath.includes("/shared/") ||
          currentPath.includes("/public/") ||
          currentPath.startsWith("/shared") ||
          currentPath.startsWith("/public");

        if (!isPublicContext) {
          const progressPercent = (currentTime.value / duration.value) * 100;
          const apiStore = useApiStore();
          apiStore.logPlayedSong(
            currentSong.value.song_id || currentSong.value.id,
          );
        }
        currentSongLogged.value = true;
      }
    }
  }

  // Volume control
  function setVolume(value) {
    const previousVolume = volume.value;
    volume.value = Math.max(0, Math.min(1, value));

    // Automatisch unmuten wenn Lautstärke über 0 gesetzt wird
    if (volume.value > 0 && isMuted.value) {
      isMuted.value = false;

      // Entferne Mute-Alert falls vorhanden
      if (muteAlertId.value) {
        const alertStore = useAlertStore();
        alertStore.removeAlert(muteAlertId.value);
        muteAlertId.value = null;
      }
    }
    // Zeige Alert wenn Lautstärke auf 0 gesetzt wird (egal ob via Slider oder Mute-Button)
    else if (volume.value === 0 && previousVolume > 0 && !muteAlertId.value) {
      isMuted.value = true;
      lastVolume.value = previousVolume;

      const alertStore = useAlertStore();
      muteAlertId.value = alertStore.warning(
        "Die Lautstärke ist stumm geschaltet. Klicken Sie auf das Lautstärke-Symbol oder bewegen Sie den Regler, um die Wiedergabe zu hören.",
        0, // 0 = persistent (kein Auto-Hide)
      );
    }

    if (isLocalMode.value) {
      setMpdVolume(volume.value * 100);
    } else if (currentHowl) {
      currentHowl.volume(volume.value);
    }

    updateVolumeIcon();
    savePlayerState();
  }

  function toggleMute() {
    if (isMuted.value) {
      // Unmute - restore previous volume
      setVolume(lastVolume.value);
      isMuted.value = false;

      // Entferne Mute-Alert falls vorhanden
      if (muteAlertId.value) {
        const alertStore = useAlertStore();
        alertStore.removeAlert(muteAlertId.value);
        muteAlertId.value = null;
      }
    } else {
      // Mute - save current volume and set to 0
      lastVolume.value = volume.value > 0 ? volume.value : 0.5;
      setVolume(0);
      isMuted.value = true;
    }
  }

  function updateVolumeIcon() {
    // This would be handled by the UI component
    // Emit event for UI updates if needed
  }

  // Equalizer methods
  function initializeEqualizer() {
    if (!currentHowl || isLocalMode.value) return;

    try {
      // Create AudioContext if not exists
      if (!audioContext) {
        const AudioContextClass =
          window.AudioContext ||
          window.webkitAudioContext ||
          window.mozAudioContext;
        audioContext = new AudioContextClass();
      }

      // Create gain node
      if (!gainNode.value) {
        gainNode.value = audioContext.createGain();
      }

      // Create equalizer filters
      const frequencies = Object.keys(equalizerGains.value).map((f) =>
        parseInt(f),
      );

      frequencies.forEach((frequency) => {
        if (!equalizerFilters[frequency]) {
          const filter = audioContext.createBiquadFilter();
          filter.type = "peaking";
          filter.frequency.value = frequency;
          filter.Q.value = 1;
          filter.gain.value = equalizerGains.value[frequency.toString()];
          equalizerFilters[frequency] = filter;
        }
      });

      // Connect filters in series
      let previousNode = gainNode.value;
      frequencies.forEach((frequency) => {
        const filter = equalizerFilters[frequency];
        previousNode.connect(filter);
        previousNode = filter;
      });

      // Connect final filter to destination
      previousNode.connect(audioContext.destination);

      // Try to connect Howler audio to our filter chain
      connectHowlerToEqualizer();
    } catch (error) {
      console.warn("Equalizer initialization failed:", error);
      equalizerEnabled.value = false;
    }
  }

  function connectHowlerToEqualizer() {
    if (!currentHowl || !audioContext || !gainNode.value) return;

    try {
      // This is tricky with Howler - we need to access the HTML5 audio element
      const audioNode = currentHowl._sounds[0]._node;
      if (audioNode && audioNode.tagName === "AUDIO") {
        // Check if this audio element already has a source node
        if (!audioNode._mediaElementSource) {
          audioNode._mediaElementSource =
            audioContext.createMediaElementSource(audioNode);
        }

        // Disconnect any existing connections before reconnecting
        audioNode._mediaElementSource.disconnect();
        audioNode._mediaElementSource.connect(gainNode.value);
      }
    } catch (error) {
      console.warn("Failed to connect Howler to equalizer:", error);
    }
  }

  function updateEqualizerBand(frequency, gain) {
    const freq = frequency.toString();
    if (equalizerGains.value.hasOwnProperty(freq)) {
      equalizerGains.value[freq] = parseFloat(gain);

      // Update the actual filter
      if (
        equalizerFilters[parseInt(frequency)] &&
        equalizerEnabled.value
      ) {
        equalizerFilters[parseInt(frequency)].gain.value = gain;
      }

      savePlayerState();
    }
  }

  function resetEqualizer() {
    Object.keys(equalizerGains.value).forEach((freq) => {
      equalizerGains.value[freq] = 0;
      if (equalizerFilters[parseInt(freq)]) {
        equalizerFilters[parseInt(freq)].gain.value = 0;
      }
    });
    savePlayerState();
  }

  function toggleEqualizer() {
    equalizerEnabled.value = !equalizerEnabled.value;

    if (equalizerEnabled.value) {
      initializeEqualizer();
    } else {
      disconnectEqualizer();
    }

    savePlayerState();
  }

  function disconnectEqualizer() {
    try {
      // Reset all filter gains to 0
      Object.keys(equalizerFilters).forEach((freq) => {
        if (equalizerFilters[freq]) {
          equalizerFilters[freq].gain.value = 0;
        }
      });
    } catch (error) {
      console.warn("Error disconnecting equalizer:", error);
    }
  }

  // Playback control
  async function playSong(song, index = null) {
    // 1. Vorab prüfen, ob wir von einem anderen Song wechseln und dessen Log-Status versiegeln
    if (
      currentSong.value &&
      (currentSong.value.song_id || currentSong.value.id) !==
        (song.song_id || song.id)
    ) {
      if (isPlaying.value || isPaused.value) {
        // Nur wenn der alte Song aktiv war (gespielt oder pausiert)
        currentSongLogged.value = true; // Verhindert Last-Minute-Logging des ausgehenden Songs
      }
    }

    isTransitioningSong.value = true; // Übergang beginnt jetzt offiziell

    try {
      isLoading.value = true;

      // Move current song to previous queue if changing to a different song
      if (
        currentSong.value &&
        (currentSong.value.song_id || currentSong.value.id) !==
          (song.song_id || song.id)
      ) {
        moveCurrentToPrevious();
      }

      // Wenn kein Index angegeben, prüfe ob Song bereits in Queue ist
      if (index === null) {
        const existingIndex = actualPlayQueue.value.findIndex(
          (q) => (q.song_id || q.id) === (song.song_id || song.id),
        );
        if (existingIndex !== -1) {
          // Song ist bereits in Queue, spiele ihn direkt
          index = existingIndex;
        } else {
          // Song ist nicht in Queue, füge ihn hinzu
          addToQueue(song);
          index = actualPlayQueue.value.length - 1;
        }
      }

      // Set current song and index
      currentSongIndexInActualPlayQueue.value = index;
      currentSong.value = { ...song };
      currentSongLogged.value = false; // Reset logged status for new song

      // Update MediaSession
      updateMediaSession(currentSong.value);

      // Start progress tracking
      startProgressInterval();

      if (isLocalMode.value) {
        await playMpdSong(song.song_id || song.id);
      } else {
        await playHowlerSong(song);
      }
    } catch (error) {
      handleError("playing song", error);
      isTransitioningSong.value = false; // Wichtig: Flag im Fehlerfall zurücksetzen
      isLoading.value = false; // Auch isLoading im Fehlerfall zurücksetzen
    } finally {
      // isLoading wird meist schon in playHowlerSong/playMpdSong oder im Catch-Block oben gehandhabt.
      // isTransitioningSong wird bei Erfolg durch onplay (Howler) oder updateMpdProgress (MPD) zurückgesetzt.
    }
  }

  async function playHowlerSong(song) {
    try {
      const apiStore = useApiStore();
      // Use custom stream URL for public playlists or standard API for regular playback
      const streamUrl =
        song.customStreamUrl ||
        (await apiStore.playSongSrc(song.song_id || song.id));

      // Stop current howl if exists
      if (currentHowl) {
        currentHowl.stop();
        currentHowl.unload();
      }

      // Store base stream URL (without seek params) for seek-via-restart
      currentStreamUrl = streamUrl;
      isTranscodedStream = false;
      transcodedSeekOffset = 0;

      // Get duration from HTTP headers (single source of truth)
      try {
        const headResponse = await fetch(streamUrl, { method: "HEAD" });
        const durationHeader =
          headResponse.headers.get("x-content-duration") ||
          headResponse.headers.get("x-media-duration");

        if (durationHeader) {
          duration.value = parseFloat(durationHeader);
        } else {
          duration.value = 0;
        }

        // Detect transcoded stream by checking if Transfer-Encoding is chunked
        // (transcoded streams use chunked encoding, direct streams use Content-Length)
        const transferEncoding = headResponse.headers.get("transfer-encoding");
        const contentLength = headResponse.headers.get("content-length");
        isTranscodedStream = transferEncoding === "chunked" || !contentLength;
      } catch (error) {
        console.error("Error fetching duration:", error);
        duration.value = 0;
      }

      currentHowl = new Howl({
        src: [streamUrl],
        format: ["flac", "mp3", "ogg", "wav", "aac", "m4a"],
        html5: true,
        volume: volume.value,
        onload: () => {
          isLoading.value = false;
          // Fallback: get duration from Howler if HEAD request didn't provide it
          if (!duration.value && currentHowl) {
            const howlDuration = currentHowl.duration();
            if (howlDuration && howlDuration > 0 && isFinite(howlDuration)) {
              duration.value = howlDuration;
            }
          }
        },
        onplay: () => {
          isPlaying.value = true;
          isPaused.value = false;
          isLoading.value = false;
          isTransitioningSong.value = false; // Übergang beendet, Song spielt

          // Ensure AudioContext exists (needed for VU meter and visualizer)
          if (!audioContext) {
            try {
              const AudioContextClass =
                window.AudioContext ||
                window.webkitAudioContext ||
                window.mozAudioContext;
              audioContext = new AudioContextClass();
            } catch (e) {
              console.warn("Failed to create AudioContext:", e);
            }
          }

          // Initialize equalizer when song starts playing
          if (equalizerEnabled.value) {
            setTimeout(() => initializeEqualizer(), 100);
          }
        },
        onpause: () => {
          isPlaying.value = false;
          isPaused.value = true;
        },
        onend: () => {
          handleTrackEnded();
        },
        onerror: (id, error) => {
          isLoading.value = false;
          handleError("Howler playback", new Error(error));
        },
      });

      currentHowl.play();
    } catch (error) {
      isLoading.value = false;
      handleError("creating Howler instance", error);
    }
  }

  function togglePlayPause() {
    if (isLocalMode.value) {
      toggleMpdPlayback();
    } else if (currentHowl) {
      if (isPlaying.value) {
        currentHowl.pause();
      } else {
        currentHowl.play();
        // Ensure progress tracking is running when resuming
        if (!progressInterval) {
          startProgressInterval();
        }
      }
    } else if (actualPlayQueue.value.length > 0) {
      playSong(
        actualPlayQueue.value[currentSongIndexInActualPlayQueue.value || 0],
        currentSongIndexInActualPlayQueue.value || 0,
      );
    }
  }

  function stop() {
    if (isLocalMode.value) {
      stopMpdPlayback();
    } else if (currentHowl) {
      currentHowl.stop();
    }

    isPlaying.value = false;
    isPaused.value = false;
    currentTime.value = 0;
    clearAllIntervals();
  }

  function seek(position) {
    if (isLocalMode.value) {
      seekMpdPosition(position);
    } else if (currentHowl) {
      if (isTranscodedStream && currentStreamUrl) {
        // Transcoded streams can't seek via HTML5 audio — restart stream at position
        seekTranscodedStream(position);
      } else {
        currentHowl.seek(position);
      }
    }
  }

  function seekTranscodedStream(position) {
    if (!currentStreamUrl || !currentHowl) return;

    const wasPlaying = isPlaying.value;
    const savedVolume = volume.value;
    const savedDuration = duration.value;

    // Stop current playback
    currentHowl.stop();
    currentHowl.unload();

    // Store seek offset so progress tracking adds it to Howler's position
    transcodedSeekOffset = position;

    // Update currentTime immediately for responsive UI
    currentTime.value = position;

    // Build URL with start parameter
    const separator = currentStreamUrl.includes("?") ? "&" : "?";
    const seekUrl = `${currentStreamUrl}${separator}start=${position}`;

    currentHowl = new Howl({
      src: [seekUrl],
      format: ["flac", "mp3", "ogg", "wav", "aac", "m4a"],
      html5: true,
      volume: savedVolume,
      onload: () => {
        isLoading.value = false;
      },
      onplay: () => {
        isPlaying.value = true;
        isPaused.value = false;
        isLoading.value = false;
        // Restore duration (HEAD won't be called again)
        duration.value = savedDuration;
        startProgressInterval();

        // Reconnect AudioContext if available
        if (audioContext && equalizerEnabled.value) {
          setTimeout(() => initializeEqualizer(), 100);
        }
      },
      onpause: () => {
        isPaused.value = true;
        isPlaying.value = false;
      },
      onend: () => {
        handleTrackEnded();
      },
      onerror: (id, error) => {
        console.error("Seek stream error:", error);
        isLoading.value = false;
      },
    });

    // Start playback if it was playing
    if (wasPlaying) {
      isLoading.value = true;
      currentHowl.play();
    }
  }

  function seekToPercent(percent) {
    const position = (percent / 100) * duration.value;
    seek(position);
  }

  function addToQueue(song) {
    addMultipleToQueue([song]);
  }

  // Batch-add songs to queue — triggers reactivity only ONCE
  function addMultipleToQueue(songs) {
    const existingIds = new Set(
      originalOrderQueue.value.map((q) => q.song_id || q.id),
    );
    const newSongs = songs.filter(
      (s) => !existingIds.has(s.song_id || s.id),
    );
    if (newSongs.length === 0) return;

    // Add all new songs at once
    originalOrderQueue.value = [...originalOrderQueue.value, ...newSongs];

    // Update actualPlayQueue once
    if (isShuffleEnabled.value) {
      const songToMaintain = currentSong.value;
      actualPlayQueue.value = shuffleArrayInternal([
        ...originalOrderQueue.value,
      ]);
      if (songToMaintain) {
        const newIndex = actualPlayQueue.value.findIndex(
          (s) =>
            (s.song_id || s.id) ===
            (songToMaintain.song_id || songToMaintain.id),
        );
        currentSongIndexInActualPlayQueue.value =
          newIndex !== -1 ? newIndex : actualPlayQueue.value.length > 0 ? 0 : -1;
      } else if (
        actualPlayQueue.value.length > 0 &&
        currentSongIndexInActualPlayQueue.value === -1
      ) {
        currentSongIndexInActualPlayQueue.value = 0;
      }
    } else {
      actualPlayQueue.value = [...originalOrderQueue.value];
    }
  }

  function removeFromQueue(index) {
    // Der Index bezieht sich auf die angezeigte Liste, also actualPlayQueue.
    if (index < 0 || index >= actualPlayQueue.value.length) return;

    const removedSongFromActual = actualPlayQueue.value[index];

    // Entferne den Song zuerst aus der originalOrderQueue, um Konsistenz zu wahren.
    const originalIndexToRemove = originalOrderQueue.value.findIndex(
      (s) =>
        (s.song_id || s.id) ===
        (removedSongFromActual.song_id || removedSongFromActual.id),
    );
    if (originalIndexToRemove !== -1) {
      originalOrderQueue.value = [
        ...originalOrderQueue.value.slice(0, originalIndexToRemove),
        ...originalOrderQueue.value.slice(originalIndexToRemove + 1),
      ];
    } else {
      // Sollte nicht passieren, wenn actualPlayQueue aus originalOrderQueue abgeleitet ist.
      // Trotzdem aus actualPlayQueue entfernen, um UI-Konsistenz zu haben:
      actualPlayQueue.value = [
        ...actualPlayQueue.value.slice(0, index),
        ...actualPlayQueue.value.slice(index + 1),
      ];
      // Hier ist eine Neubewertung des Zustands nötig, aber der Fehlerfall ist kritisch.
      return;
    }

    const isRemovingCurrentSong = currentSong.value
      ? (currentSong.value.song_id || currentSong.value.id) ===
        (removedSongFromActual.song_id || removedSongFromActual.id)
      : false;
    let songToMaintainAfterRemove = isRemovingCurrentSong
      ? null
      : currentSong.value;

    if (isShuffleEnabled.value) {
      actualPlayQueue.value = shuffleArrayInternal([
        ...originalOrderQueue.value,
      ]);

      if (songToMaintainAfterRemove) {
        const newIndexOfMaintained = actualPlayQueue.value.findIndex(
          (s) =>
            (s.song_id || s.id) ===
            (songToMaintainAfterRemove.song_id ||
              songToMaintainAfterRemove.id),
        );
        if (newIndexOfMaintained !== -1) {
          currentSongIndexInActualPlayQueue.value = newIndexOfMaintained;
        } else {
          // Beibehaltener Song ist nicht mehr in der neuen Shuffle-Liste (sollte nicht sein, wenn er nicht der entfernte war)
          currentSongIndexInActualPlayQueue.value =
            actualPlayQueue.value.length > 0 ? 0 : -1;
        }
      } else if (actualPlayQueue.value.length > 0) {
        // Aktueller Song wurde entfernt ODER es gab keinen -> setze auf Anfang der neuen Shuffle-Liste
        currentSongIndexInActualPlayQueue.value = 0;
        // Wenn der aktuelle Song entfernt wurde und die Liste noch Elemente hat, spiele den neuen ersten Song.
        if (isRemovingCurrentSong) {
          playSong(
            actualPlayQueue.value[currentSongIndexInActualPlayQueue.value],
            currentSongIndexInActualPlayQueue.value,
          );
        }
      } else {
        // actualPlayQueue ist jetzt leer
        currentSongIndexInActualPlayQueue.value = -1;
        if (isRemovingCurrentSong) resetPlayer();
      }
    } else {
      // Shuffle ist AUS: actualPlayQueue direkt anpassen.
      // Da originalOrderQueue schon angepasst wurde, hier actualPlayQueue neu setzen.
      actualPlayQueue.value = [...originalOrderQueue.value];

      if (isRemovingCurrentSong) {
        stop();
        if (actualPlayQueue.value.length === 0) {
          resetPlayer();
        } else {
          // Setze auf den Index des entfernten Songs, wenn noch Elemente da sind, sonst eins weniger.
          // playSong wird dann den Song an diesem Index spielen oder den Player resetten.
          let nextIndexToPlay =
            index < actualPlayQueue.value.length
              ? index
              : actualPlayQueue.value.length - 1;
          if (nextIndexToPlay >= 0) {
            playSong(
              actualPlayQueue.value[nextIndexToPlay],
              nextIndexToPlay,
            );
          } else {
            resetPlayer();
          }
        }
      } else {
        // Wenn ein Song vor dem currentSong entfernt wurde, muss der Index angepasst werden.
        // Finde den currentSong in der neuen actualPlayQueue, um den korrekten Index zu haben.
        if (currentSong.value) {
          const newCurrentIdx = actualPlayQueue.value.findIndex(
            (s) =>
              (s.song_id || s.id) ===
              (currentSong.value.song_id || currentSong.value.id),
          );
          if (newCurrentIdx !== -1) {
            currentSongIndexInActualPlayQueue.value = newCurrentIdx;
          } else {
            // CurrentSong nicht mehr da (sollte nicht sein, wenn nicht isRemovingCurrentSong)
            currentSongIndexInActualPlayQueue.value =
              actualPlayQueue.value.length > 0 ? 0 : -1;
            if (currentSongIndexInActualPlayQueue.value === -1)
              resetPlayer();
          }
        } else {
          currentSongIndexInActualPlayQueue.value = -1; // Kein aktueller Song
        }
      }
    }
  }

  function clearQueue() {
    stop(); // Stoppe aktuelle Wiedergabe

    actualPlayQueue.value = [];
    originalOrderQueue.value = []; // Auch die Master-Liste leeren
    previousQueue.value = [];
    currentSongIndexInActualPlayQueue.value = -1;

    resetPlayer(); // Führt weitere Resets durch (currentSong, currentTime etc.)
  }

  function playFromQueue(index) {
    if (index >= 0 && index < upcomingQueue.value.length) {
      // Calculate actual queue index
      const actualIndex = currentSongIndexInActualPlayQueue.value + 1 + index;
      const songToPlay = actualPlayQueue.value[actualIndex];

      isTransitioningSong.value = true;

      // Move current song to previous queue (history) if there is one
      if (currentSong.value) {
        moveCurrentToPrevious();
      }

      // Move songs that were above the clicked song to the front of the upcoming queue
      // This preserves the order: songs 1-4 will be played after the clicked song (song 5)
      const songsToMoveToFront = actualPlayQueue.value.slice(
        currentSongIndexInActualPlayQueue.value + 1,
        actualIndex,
      );
      const songsAfterClicked = actualPlayQueue.value.slice(actualIndex + 1);

      // Rebuild the queue: songs before current + clicked song + songs that were above + remaining songs
      const newQueue = [
        ...actualPlayQueue.value.slice(
          0,
          currentSongIndexInActualPlayQueue.value + 1,
        ),
        songToPlay,
        ...songsToMoveToFront,
        ...songsAfterClicked,
      ];

      actualPlayQueue.value = newQueue;
      currentSongIndexInActualPlayQueue.value =
        currentSongIndexInActualPlayQueue.value + 1;

      // Update originalOrderQueue if shuffle is disabled
      if (!isShuffleEnabled.value) {
        originalOrderQueue.value = [...actualPlayQueue.value];
      }

      currentSong.value = { ...songToPlay };
      currentSongLogged.value = false; // Reset logged status for new song

      // Update MediaSession
      updateMediaSession(currentSong.value);

      // Start progress tracking
      startProgressInterval();

      // Play the song
      if (isLocalMode.value) {
        playMpdSong(songToPlay.song_id || songToPlay.id);
      } else {
        playHowlerSong(songToPlay);
      }
    }
  }

  function playFromHistory(index) {
    if (index >= 0 && index < previousQueue.value.length) {
      const song = previousQueue.value[index];
      isTransitioningSong.value = true; // Übergang beginnt
      // Add current song to queue if there is one
      if (currentSong.value) {
        actualPlayQueue.value = [currentSong.value, ...actualPlayQueue.value];
        currentSongIndexInActualPlayQueue.value++;
      }
      // Remove songs from history and add to queue
      const removedSongs = previousQueue.value.slice(index);
      previousQueue.value = previousQueue.value.slice(0, index);
      actualPlayQueue.value = [...removedSongs.reverse(), ...actualPlayQueue.value];
      currentSongIndexInActualPlayQueue.value = index;
      playSong(song, currentSongIndexInActualPlayQueue.value);
    }
  }

  // Update playSong method to manage previous queue
  function moveCurrentToPrevious() {
    if (currentSong.value && currentSongIndexInActualPlayQueue.value >= 0) {
      // Add current song to previous queue
      previousQueue.value = [...previousQueue.value, currentSong.value];

      // Limit previous queue size (e.g., last 50 songs)
      if (previousQueue.value.length > 50) {
        previousQueue.value = previousQueue.value.slice(1);
      }
    }
  }

  function playPlaylist(songs) {
    isTransitioningSong.value = true;
    originalOrderQueue.value = [...songs];
    previousQueue.value = [];

    if (isShuffleEnabled.value) {
      actualPlayQueue.value = shuffleArrayInternal([
        ...originalOrderQueue.value,
      ]);
      currentSongIndexInActualPlayQueue.value =
        actualPlayQueue.value.length > 0 ? 0 : -1;

      if (currentSongIndexInActualPlayQueue.value !== -1) {
        playSong(
          actualPlayQueue.value[currentSongIndexInActualPlayQueue.value],
          currentSongIndexInActualPlayQueue.value,
        );
      } else {
        resetPlayer();
      }
    } else {
      actualPlayQueue.value = [...originalOrderQueue.value];
      currentSongIndexInActualPlayQueue.value =
        actualPlayQueue.value.length > 0 ? 0 : -1;

      if (currentSongIndexInActualPlayQueue.value !== -1) {
        playSong(
          actualPlayQueue.value[currentSongIndexInActualPlayQueue.value],
          currentSongIndexInActualPlayQueue.value,
        );
      } else {
        resetPlayer();
      }
    }
  }

  async function playAlbum(albumId) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.getAlbumTracks(albumId);
      if (response.success) {
        isTransitioningSong.value = true; // Übergang beginnt, nachdem Tracks geladen wurden
        playPlaylist(response.tracks);
      }
    } catch (error) {
      handleError("playing album", error);
    }
  }

  async function playArtist(artistId) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.getArtistTracks(artistId);
      if (response.success) {
        isTransitioningSong.value = true; // Übergang beginnt, nachdem Tracks geladen wurden
        playPlaylist(response.tracks);
      } else if (response.tracks) {
        // Fallback if response structure is different
        isTransitioningSong.value = true;
        playPlaylist(response.tracks);
      } else if (Array.isArray(response)) {
        // Direct array response
        isTransitioningSong.value = true;
        playPlaylist(response);
      }
    } catch (error) {
      handleError("playing artist", error);
    }
  }

  // Handle track ended
  function handleTrackEnded() {
    if (currentSong.value && repeatMode.value !== "one") {
      currentSongLogged.value = true;
    }

    if (repeatMode.value === "one") {
      if (currentHowl && currentSong.value) {
        currentSongLogged.value = false;
        isTransitioningSong.value = true;
        currentHowl.seek(0);
        currentHowl.play();
        showNotification(
          getI18nMessage("player.repeatCurrentSong"),
          2000,
        );
      }
      return;
    }

    nextSong();
  }

  // Get next song index based on shuffle and repeat modes
  // Renamed to avoid collision with the utility function getNextSongIndex
  function getNextSongIndexAction() {
    if (actualPlayQueue.value.length === 0) return -1;

    if (repeatMode.value === "one") {
      return currentSongIndexInActualPlayQueue.value;
    }

    let nextIndex = currentSongIndexInActualPlayQueue.value + 1;

    if (nextIndex >= actualPlayQueue.value.length) {
      if (repeatMode.value === "all") {
        nextIndex = 0;
      } else {
        return -1;
      }
    }
    return nextIndex;
  }

  function nextSong() {
    if (isLocalMode.value) {
      isTransitioningSong.value = true;
      navigateMpdPlayback("next")
        .then((success) => {
          if (success) {
            startProgressInterval();
            getMpdStatus()
              .then((status) => {
                if (status && status.success && status.currentSong) {
                  updateCurrentFromMpd(status);
                }
              })
              .catch((err) =>
                handleError("getting MPD status after next", err),
              );
          }
        })
        .catch((err) =>
          handleError("navigating to next MPD track", err),
        );
      return;
    }

    if (actualPlayQueue.value.length === 0) {
      console.warn("[nextSong] actualPlayQueue is empty.");
      resetPlayer();
      return;
    }

    let nextIndex = currentSongIndexInActualPlayQueue.value + 1;

    if (isShuffleEnabled.value) {
      if (nextIndex >= actualPlayQueue.value.length) {
        if (repeatMode.value === "all") {
          actualPlayQueue.value = shuffleArrayInternal([
            ...originalOrderQueue.value,
          ]);
          nextIndex = actualPlayQueue.value.length > 0 ? 0 : -1;
          if (nextIndex === -1) {
            resetPlayer();
            return;
          }
        } else {
          resetPlayer();
          return;
        }
      }
    } else {
      if (nextIndex >= actualPlayQueue.value.length) {
        if (repeatMode.value === "all") {
          nextIndex = 0;
        } else if (repeatMode.value === "one") {
          nextIndex = currentSongIndexInActualPlayQueue.value;
        } else {
          resetPlayer();
          return;
        }
      } else if (repeatMode.value === "one") {
        nextIndex = currentSongIndexInActualPlayQueue.value;
      }
    }

    if (nextIndex !== -1 && nextIndex < actualPlayQueue.value.length) {
      isTransitioningSong.value = true;
      playSong(actualPlayQueue.value[nextIndex], nextIndex);
    } else if (
      nextIndex === -1 &&
      actualPlayQueue.value.length > 0 &&
      repeatMode.value === "one"
    ) {
      if (currentSongIndexInActualPlayQueue.value !== -1) {
        isTransitioningSong.value = true;
        playSong(
          actualPlayQueue.value[currentSongIndexInActualPlayQueue.value],
          currentSongIndexInActualPlayQueue.value,
        );
      }
    } else {
      console.warn(
        "[nextSong] No valid next song found or index out of bounds after logic. Queue Length:",
        actualPlayQueue.value.length,
        "Target Index:",
        nextIndex,
      );
    }
  }

  // Previous song
  function prevSong() {
    if (isLocalMode.value) {
      isTransitioningSong.value = true;
      navigateMpdPlayback("prev")
        .then((success) => {
          if (success) {
            startProgressInterval();
            getMpdStatus()
              .then((status) => {
                if (status && status.success && status.currentSong) {
                  updateCurrentFromMpd(status);
                }
              })
              .catch((err) =>
                handleError("getting MPD status after prev", err),
              );
          }
        })
        .catch((err) =>
          handleError("navigating to previous MPD track", err),
        );
      return; // MPD handelt anders
    }

    if (
      actualPlayQueue.value.length === 0 &&
      previousQueue.value.length === 0
    ) {
      console.warn("[prevSong] actualPlayQueue and previousQueue are empty.");
      showNotification(
        getI18nMessage("player.noPreviousSongAvailable"),
        2000,
      );
      return;
    }

    let prevIndex = currentSongIndexInActualPlayQueue.value - 1;

    if (isShuffleEnabled.value) {
      if (prevIndex < 0) {
        if (repeatMode.value === "all") {
          // Optional: Neu mischen oder zum Ende der aktuellen Mischung. Für Konsistenz mit nextSong: neu mischen.
          actualPlayQueue.value = shuffleArrayInternal([
            ...originalOrderQueue.value,
          ]);
          prevIndex = actualPlayQueue.value.length - 1; // Gehe zum letzten Song der neuen Shuffle-Liste
          if (actualPlayQueue.value.length === 0) {
            // Sollte nach Shuffle nicht passieren, wenn original nicht leer war
            resetPlayer();
            return;
          }
        } else {
          showNotification(
            getI18nMessage("player.noPreviousSongAvailable"),
            2000,
          );
          return;
        }
      }
    } else {
      // Standard (Nicht-Shuffle) Logik
      if (prevIndex < 0) {
        // Wenn wir am Anfang der actualPlayQueue sind, prüfe previousQueue (History)
        if (previousQueue.value.length > 0) {
          const songFromHistory = previousQueue.value[previousQueue.value.length - 1]; // Nimm letzten aus History
          previousQueue.value = previousQueue.value.slice(0, -1);
          // Füge aktuellen Song (falls vorhanden) an den Anfang der actualPlayQueue (wird zu History für nächsten prev)
          if (currentSong.value) {
            actualPlayQueue.value = [currentSong.value, ...actualPlayQueue.value];
            // Da wir an den Anfang von actualPlayQueue unshift-en und dann songFromHistory spielen (der dann auch wieder in die actualPlayQueue kommt),
            // müssen wir aufpassen, dass der Index aktuell bleibt.
            // Besser: aktuellen in History, songFromHistory an Anfang der originalOrderQueue und actualPlayQueue neu bilden.
          }
          originalOrderQueue.value = [songFromHistory, ...originalOrderQueue.value]; // Füge ihn an den Anfang der Master-Liste
          actualPlayQueue.value = [...originalOrderQueue.value]; // Aktualisiere Play-Queue
          prevIndex = 0; // Der Song aus der History ist jetzt der erste
          currentSongIndexInActualPlayQueue.value = -1; // Erzwinge, dass playSong den neuen Index setzt und currentSong aktualisiert
        } else if (repeatMode.value === "all") {
          prevIndex = actualPlayQueue.value.length - 1;
        } else {
          showNotification(
            getI18nMessage("player.noPreviousSongAvailable"),
            2000,
          );
          return;
        }
      }
      // `repeatMode === 'one'` wird von `playSong` oder `handleTrackEnded` behandelt,
      // indem der aktuelle Song einfach neu gestartet wird.
      // prevSong sollte bei 'one' einfach den vorherigen oder, wenn am Anfang, den letzten (bei repeat all) spielen.
    }

    if (prevIndex !== -1 && prevIndex < actualPlayQueue.value.length) {
      isTransitioningSong.value = true;
      playSong(actualPlayQueue.value[prevIndex], prevIndex);
    } else {
    }
  }

  // Toggle shuffle mode
  function toggleShuffle() {
    isShuffleEnabled.value = !isShuffleEnabled.value;
    let songToMaintain = currentSong.value; // Song, den wir versuchen beizubehalten
    let newPlayIndex = -1;

    if (isShuffleEnabled.value) {
      // originalOrderQueue sollte bereits die Songs in ihrer Hinzufüge-/Albumreihenfolge enthalten.
      // Wenn sie leer ist (z.B. erster Start und Queue wurde direkt befüllt), kopiere actualPlayQueue als Basis.
      if (
        originalOrderQueue.value.length === 0 &&
        actualPlayQueue.value.length > 0
      ) {
        originalOrderQueue.value = [...actualPlayQueue.value];
      }

      // Mische die originalOrderQueue, um die neue actualPlayQueue zu erstellen.
      actualPlayQueue.value = shuffleArrayInternal([
        ...originalOrderQueue.value,
      ]);

      if (songToMaintain) {
        newPlayIndex = actualPlayQueue.value.findIndex(
          (s) =>
            (s.song_id || s.id) ===
            (songToMaintain.song_id || songToMaintain.id),
        );
      } else if (actualPlayQueue.value.length > 0) {
        newPlayIndex = 0; // Kein Song spielte, starte mit dem ersten der neuen Shuffle-Liste
      }
      currentSongIndexInActualPlayQueue.value = newPlayIndex;
      // Wenn ein Index gefunden wurde und es der erste Song ist oder ein anderer als vorher, oder kein Song spielte, starte ihn ggf.
      // Vorsicht: Nicht automatisch starten, wenn vorher schon ein Song lief und nur die Reihenfolge geändert wurde.
      // playSong wird später durch next/prev oder direkten Klick aufgerufen.
      // Ein Spezialfall ist, wenn vorher nichts lief und jetzt Shuffle aktiviert wird -> starte ersten Song.
      if (
        newPlayIndex !== -1 &&
        !songToMaintain &&
        actualPlayQueue.value.length > 0
      ) {
        playSong(actualPlayQueue.value[newPlayIndex], newPlayIndex);
      } else {
        // Update MediaSession falls songToMaintain existiert und gefunden wurde.
        if (songToMaintain && newPlayIndex !== -1)
          updateMediaSession(actualPlayQueue.value[newPlayIndex]);
      }
    } else {
      // Kehre zur originalOrderQueue zurück.
      // songToMaintain ist immer noch currentSong.value (aus der vorher geshuffelten actualPlayQueue)

      actualPlayQueue.value = [...originalOrderQueue.value]; // Stelle die ursprüngliche Reihenfolge wieder her.

      if (songToMaintain) {
        newPlayIndex = actualPlayQueue.value.findIndex(
          (s) =>
            (s.song_id || s.id) ===
            (songToMaintain.song_id || songToMaintain.id),
        );
      } else if (actualPlayQueue.value.length > 0) {
        newPlayIndex = 0; // Fallback, sollte nicht oft nötig sein
      }
      currentSongIndexInActualPlayQueue.value = newPlayIndex;
      // MediaSession sollte auch hier aktualisiert werden, wenn ein Song beibehalten wurde.
      if (songToMaintain && newPlayIndex !== -1)
        updateMediaSession(actualPlayQueue.value[newPlayIndex]);
    }

    const message = isShuffleEnabled.value
      ? getI18nMessage("player.shuffleEnabled")
      : getI18nMessage("player.shuffleDisabled");
    showNotification(message, 2000);
    savePlayerState(); // Speichere isShuffleEnabled
  }

  // Toggle repeat mode
  function toggleRepeat() {
    switch (repeatMode.value) {
      case "none":
        repeatMode.value = "one";
        showNotification(
          getI18nMessage("player.singleRepeatEnabled"),
          2000,
        );
        break;
      case "one":
        repeatMode.value = "all";
        showNotification(
          getI18nMessage("player.playlistRepeatEnabled"),
          2000,
        );
        break;
      case "all":
        repeatMode.value = "none";
        showNotification(getI18nMessage("player.repeatDisabled"), 2000);
        break;
    }

    savePlayerState();
  }

  // Show notification
  function showNotification(message, duration = 3000) {
    // Use the global alert store
    const alertStore = useAlertStore();
    alertStore.info(message);

    // Also emit event for any components listening
    document.dispatchEvent(
      new CustomEvent("player-notification", {
        detail: { message, duration },
      }),
    );
  }

  // Reset player - vollständiger Reset aller UI-Elemente
  function resetPlayer() {
    // Stop playback
    isPlaying.value = false;
    isPaused.value = false;
    isLoading.value = false;

    // Reset time and duration
    currentTime.value = 0;
    duration.value = 0;

    // Reset current song and queue state
    currentSong.value = null;
    currentSongIndexInActualPlayQueue.value = -1;
    currentSongLogged.value = false; // Reset logged status

    // Reset mute state and remove alerts
    if (muteAlertId.value) {
      const alertStore = useAlertStore();
      alertStore.removeAlert(muteAlertId.value);
      muteAlertId.value = null;
    }

    // Stop and cleanup Howler
    if (currentHowl) {
      currentHowl.stop();
      currentHowl.unload();
      currentHowl = null;
    }

    // Clear intervals
    clearAllIntervals();

    // Reset MediaSession
    if ("mediaSession" in navigator) {
      navigator.mediaSession.playbackState = "none";
      navigator.mediaSession.metadata = null;
    }
  }

  // Local Mode / MPD Functions
  async function checkLocalModeAvailability() {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/config");
      if (response.ok) {
        const config = await response.json();
        mpdEnabled.value = config.mpd?.enabled === true;

        if (mpdEnabled.value) {
          const mpdResponse = await apiStore.makeRequest("/api/mpd/status");
          if (mpdResponse.ok) {
            const data = await mpdResponse.json();
            localModeEnabled.value = data.success || false;
          }
        }
      }
    } catch (error) {
      handleError("checking local mode availability", error);
      mpdEnabled.value = false;
      localModeEnabled.value = false;
    }
  }

  async function toggleLocalMode() {
    if (!mpdEnabled.value || !localModeEnabled.value) {
      showNotification(getI18nMessage("player.mpdNotAvailable"), 2000);
      return false;
    }

    try {
      isTransitioningSong.value = true; // Übergang zwischen Modi beginnt
      isLocalMode.value = !isLocalMode.value;

      if (isLocalMode.value) {
        showNotification(getI18nMessage("player.playingOnServer"), 2000);
        if (currentSong.value) {
          await playMpdSong(
            currentSong.value.song_id || currentSong.value.id,
          );
        }
      } else {
        showNotification(
          getI18nMessage("player.playingInBrowser"),
          2000,
        );
        await stopMpdPlayback();
        if (currentSong.value) {
          await playHowlerSong(currentSong.value);
        }
      }

      // isTransitioningSong wird in playHowlerSong (onplay) oder updateMpdProgress (isPlaying) zurückgesetzt
      return true;
    } catch (error) {
      handleError("toggling local mode", error);
      isTransitioningSong.value = false; // Fehlerfall, Übergang abbrechen
      return false;
    }
  }

  async function playMpdSong(songId, position = 0) {
    // isTransitioningSong wird typischerweise vom Aufrufer (z.B. playSong, toggleLocalMode) gesetzt
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/mpd/play", {
        method: "POST",
        body: JSON.stringify({ songId, position }),
      });

      if (response.ok) {
        const result = await response.json();
        return result.success;
      }
      return false;
    } catch (error) {
      handleError("playing MPD song", error);
      return false;
    }
  }

  async function toggleMpdPlayback() {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/mpd/pause", {
        method: "POST",
        body: JSON.stringify({ pause: isPlaying.value }),
      });

      if (response.ok) {
        const result = await response.json();
        return result.success;
      }
      return false;
    } catch (error) {
      handleError("toggling MPD playback", error);
      return false;
    }
  }

  async function stopMpdPlayback() {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/mpd/stop", {
        method: "POST",
      });
      if (response.ok) {
        const result = await response.json();
        return result.success;
      }
      return false;
    } catch (error) {
      handleError("stopping MPD playback", error);
      return false;
    }
  }

  async function setMpdVolume(vol) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/mpd/volume", {
        method: "POST",
        body: JSON.stringify({ volume: vol }),
      });

      if (response.ok) {
        const result = await response.json();
        return result.success;
      }
      return false;
    } catch (error) {
      handleError("setting MPD volume", error);
      return false;
    }
  }

  async function seekMpdPosition(position) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/mpd/seek", {
        method: "POST",
        body: JSON.stringify({ position }),
      });

      if (response.ok) {
        const result = await response.json();
        return result.success;
      }
      return false;
    } catch (error) {
      handleError("seeking MPD position", error);
      return false;
    }
  }

  async function navigateMpdPlayback(direction) {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/mpd/navigate", {
        method: "POST",
        body: JSON.stringify({ direction }),
      });

      if (response.ok) {
        const result = await response.json();
        return result.success;
      }
      return false;
    } catch (error) {
      handleError("navigating MPD playback", error);
      return false;
    }
  }

  async function getMpdStatus() {
    try {
      const apiStore = useApiStore();
      const response = await apiStore.makeRequest("/api/mpd/status");
      if (response.ok) {
        return await response.json();
      }
      return null;
    } catch (error) {
      handleError("getting MPD status", error);
      return null;
    }
  }

  async function updateMpdProgress() {
    const status = await getMpdStatus();
    if (status && status.success && status.status) {
      currentTime.value = parseFloat(status.status.elapsed) || 0;
      duration.value = parseFloat(status.status.duration) || 0;
      isPlaying.value = status.status.state === "play";

      // Synchronize volume from MPD (convert from 0-100 to 0-1)
      if (status.status.volume !== undefined) {
        const mpdVolume = parseFloat(status.status.volume) / 100;
        if (Math.abs(volume.value - mpdVolume) > 0.01) {
          // Only update if significantly different
          volume.value = mpdVolume;
        }
      }

      if (isTransitioningSong.value && isPlaying.value) {
        isTransitioningSong.value = false; // Übergang beendet, da MPD spielt
      }
      if (isTransitioningSong.value) return; // Nicht loggen während eines manuellen Übergangs

      // Log played song if > 60% and not already logged (for MPD mode)
      if (
        currentSong.value &&
        !currentSongLogged.value &&
        duration.value > 0 &&
        currentTime.value / duration.value >= 0.6
      ) {
        // Check if we're in a public/shared playlist context - don't log plays there
        const currentPath = window.location.pathname;
        const isPublicContext =
          currentPath.includes("/shared/") ||
          currentPath.includes("/public/") ||
          currentPath.startsWith("/shared") ||
          currentPath.startsWith("/public");

        if (!isPublicContext) {
          const progressPercent = (currentTime.value / duration.value) * 100;
          const apiStore = useApiStore();
          apiStore.logPlayedSong(
            currentSong.value.song_id || currentSong.value.id,
          );
        }
        currentSongLogged.value = true;
      }
    }
  }

  function updateCurrentFromMpd(status) {
    if (status && status.success && status.currentSong) {
      const mpdSongInfo = status.currentSong; // Enthält z.B. .file und .Id
      let matchedIndex = -1;

      // Versuche, den Song in der lokalen Queue über ID oder Dateipfad zu finden
      if (mpdSongInfo.Id) {
        matchedIndex = actualPlayQueue.value.findIndex(
          (s) => (s.song_id || s.id) === mpdSongInfo.Id,
        );
      }
      if (matchedIndex === -1 && mpdSongInfo.file) {
        matchedIndex = actualPlayQueue.value.findIndex(
          (s) => s.file_path === mpdSongInfo.file,
        );
      }

      if (matchedIndex !== -1) {
        const newCurrentSongCandidate = actualPlayQueue.value[matchedIndex];
        const newActualSongId =
          newCurrentSongCandidate.song_id || newCurrentSongCandidate.id;
        const oldActualSongId = currentSong.value
          ? currentSong.value.song_id || currentSong.value.id
          : null;

        // Wenn sich der Song tatsächlich geändert hat oder vorher kein Song aktiv war
        if (oldActualSongId !== newActualSongId) {
          currentSongLogged.value = false; // Wichtig: Log-Status für den neuen Song zurücksetzen
        }

        currentSongIndexInActualPlayQueue.value = matchedIndex;
        currentSong.value = newCurrentSongCandidate;
      } else {
        // Fall: MPD spielt einen Song, der nicht (oder nicht mehr) in der lokalen Queue ist.
        // Hier könnte man optional currentSong zurücksetzen oder eine Warnung ausgeben.
        // Fürs Erste belassen wir es dabei, dass currentSong nicht aktualisiert wird,
        // wenn keine Übereinstimmung gefunden wird.
        console.warn(
          "MPD is playing a song not found in the local queue:",
          mpdSongInfo,
        );
      }
    }
  }

  // MediaSession for mobile devices
  function updateMediaSession(song) {
    if (!("mediaSession" in navigator) || !song) return;

    try {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: song.title || "Unknown Title",
        artist: song.artist || "Unknown Artist",
        album: song.album || "Unknown Album",
        artwork: [
          {
            src: song.cover_url || "/img/placeholder_audinary.png",
            sizes: "512x512",
            type: "image/webp",
          },
        ],
      });

      // Set action handlers
      navigator.mediaSession.setActionHandler("play", () =>
        togglePlayPause(),
      );
      navigator.mediaSession.setActionHandler("pause", () =>
        togglePlayPause(),
      );
      navigator.mediaSession.setActionHandler("previoustrack", () =>
        prevSong(),
      );
      navigator.mediaSession.setActionHandler("nexttrack", () =>
        nextSong(),
      );

      try {
        navigator.mediaSession.setActionHandler("seekto", (details) => {
          if (details.seekTime !== undefined) {
            seek(details.seekTime);
          }
        });
      } catch (error) {
        console.warn("Seekto is not supported.");
      }
    } catch (error) {
      handleError("setting up MediaSession", error);
    }
  }

  // Event listeners
  function setupEventListeners() {
    // Remove previous keydown listener if it exists (prevent stacking)
    if (_keydownHandler) {
      document.removeEventListener("keydown", _keydownHandler);
    }

    // Listen for keyboard shortcuts
    _keydownHandler = (event) => {
      if (
        event.target.tagName === "INPUT" ||
        event.target.tagName === "TEXTAREA"
      )
        return;

      switch (event.code) {
        case "Space":
          event.preventDefault();
          togglePlayPause();
          break;
        case "ArrowLeft":
          if (event.ctrlKey) {
            event.preventDefault();
            prevSong();
          }
          break;
        case "ArrowRight":
          if (event.ctrlKey) {
            event.preventDefault();
            nextSong();
          }
          break;
      }
    };
    document.addEventListener("keydown", _keydownHandler);
  }

  // State persistence
  function savePlayerState() {
    try {
      const state = {
        volume: volume.value,
        isShuffleEnabled: isShuffleEnabled.value,
        repeatMode: repeatMode.value,
        isLocalMode: isLocalMode.value,
        equalizerEnabled: equalizerEnabled.value,
        equalizerGains: equalizerGains.value,
      };
      localStorage.setItem("playerState", JSON.stringify(state));
    } catch (error) {
      console.warn("Failed to save player state:", error);
    }
  }

  function loadPlayerState() {
    try {
      const saved = localStorage.getItem("playerState");
      if (saved) {
        const state = JSON.parse(saved);
        volume.value = state.volume !== undefined ? state.volume : 0.5;
        isShuffleEnabled.value = state.isShuffleEnabled || false;
        repeatMode.value = state.repeatMode || "none";
        isLocalMode.value = state.isLocalMode || false;
        equalizerEnabled.value =
          state.equalizerEnabled !== undefined
            ? state.equalizerEnabled
            : true;
        if (state.equalizerGains) {
          equalizerGains.value = {
            ...equalizerGains.value,
            ...state.equalizerGains,
          };
        }
      } else {
        // Set default volume to 50%
        volume.value = 0.5;
      }

      // Ensure lastVolume is set
      if (lastVolume.value === 0 || lastVolume.value === 1) {
        lastVolume.value = 0.5;
      }
    } catch (error) {
      console.warn("Failed to load player state:", error);
      // Set default values
      volume.value = 0.5;
      lastVolume.value = 0.5;
    }
  }

  // Neue Queue-Reordering Methoden
  function moveToNext(queueIndex) {
    // queueIndex bezieht sich auf die upcomingQueue (ohne currentSong)
    // Konvertiere zu actualPlayQueue Index
    const actualQueueIndex =
      currentSongIndexInActualPlayQueue.value + 1 + queueIndex;

    if (
      actualQueueIndex < 0 ||
      actualQueueIndex >= actualPlayQueue.value.length
    ) {
      return;
    }

    const currentSongInActualQueue = currentSongIndexInActualPlayQueue.value;
    if (currentSongInActualQueue === -1) {
      return;
    }

    // Song nach currentSongInActualPlayQueue + 1 verschieben
    const song = actualPlayQueue.value[actualQueueIndex];
    const targetIndex = currentSongInActualQueue + 1;

    // Build new array without the song at actualQueueIndex
    const withoutSong = [
      ...actualPlayQueue.value.slice(0, actualQueueIndex),
      ...actualPlayQueue.value.slice(actualQueueIndex + 1),
    ];

    // Anpassung der Indices nach dem Entfernen
    let adjustedTargetIndex = targetIndex;
    if (actualQueueIndex < targetIndex) {
      adjustedTargetIndex = targetIndex - 1;
    }

    // Song an neuer Position einfügen
    actualPlayQueue.value = [
      ...withoutSong.slice(0, adjustedTargetIndex),
      song,
      ...withoutSong.slice(adjustedTargetIndex),
    ];

    // CurrentSongIndex anpassen falls nötig
    if (actualQueueIndex < currentSongInActualQueue) {
      currentSongIndexInActualPlayQueue.value = currentSongInActualQueue - 1;
    } else if (actualQueueIndex === currentSongInActualQueue) {
      currentSongIndexInActualPlayQueue.value = adjustedTargetIndex;
    }

    // Auch originalOrderQueue synchronisieren wenn Shuffle aus ist
    if (!isShuffleEnabled.value) {
      originalOrderQueue.value = [...actualPlayQueue.value];
    }
  }

  function reorderQueue(newOrder) {
    if (newOrder.length !== actualPlayQueue.value.length) {
      return;
    }

    // Finde aktuellen Song in der neuen Reihenfolge
    const currentSongVal = currentSong.value;
    let newCurrentIndex = -1;

    if (currentSongVal) {
      newCurrentIndex = newOrder.findIndex(
        (song) =>
          (song.song_id || song.id) ===
          (currentSongVal.song_id || currentSongVal.id),
      );
    }

    // Aktualisiere die actualPlayQueue
    actualPlayQueue.value = [...newOrder];

    // Aktualisiere currentSongIndex
    if (newCurrentIndex !== -1) {
      currentSongIndexInActualPlayQueue.value = newCurrentIndex;
    }

    // Auch originalOrderQueue synchronisieren wenn Shuffle aus ist
    if (!isShuffleEnabled.value) {
      originalOrderQueue.value = [...actualPlayQueue.value];
    }
  }

  function handleUserLogout() {
    try {
      // Stop playback and clear all player state when user logs out
      stop();
      clearQueue();
      resetPlayer();
      clearAllIntervals();

      // Clear media session
      if (typeof window !== "undefined" && "mediaSession" in navigator) {
        navigator.mediaSession.metadata = null;
      }
    } catch (error) {
      console.error("Error during player cleanup on logout:", error);
    }
  }

  function destroy() {
    // Clean up event listeners when store is destroyed
    if (typeof window !== "undefined") {
      if (logoutHandler) {
        window.removeEventListener("user-logged-out", logoutHandler);
        logoutHandler = null;
      }
      if (_keydownHandler) {
        document.removeEventListener("keydown", _keydownHandler);
        _keydownHandler = null;
      }
    }
    clearAllIntervals();
    stop();
  }

  // ── New helper functions ──
  function clearHistory() {
    previousQueue.value = [];
  }

  function setCurrentHowl(howl) {
    currentHowl = howl;
  }

  function getCurrentHowl() {
    return currentHowl;
  }

  function getAudioContext() {
    return audioContext;
  }

  function getGainNode() {
    return gainNode.value;
  }

  // ── Return all public state, computed, and functions ──
  return {
    // shallowRef state (arrays)
    actualPlayQueue,
    originalOrderQueue,
    previousQueue,

    // ref state
    currentSong,
    currentSongIndexInActualPlayQueue,
    isPlaying,
    isPaused,
    isLoading,
    currentSongLogged,
    isTransitioningSong,
    volume,
    lastVolume,
    isMuted,
    muteAlertId,
    duration,
    currentTime,
    isShuffleEnabled,
    repeatMode,
    isLocalMode,
    localModeEnabled,
    mpdEnabled,
    serverDeviceInfo,
    audioProcessingEnabled,
    equalizerEnabled,
    equalizerGains,
    gainNode,
    showFullscreen,
    showQueue,
    showEqualizer,

    // computed
    hasQueue,
    upcomingQueueLength,
    upcomingQueuePreview,
    upcomingQueue,
    canPlayPrevious,
    canPlayNext,
    currentSongProgress,
    formattedCurrentTime,
    formattedDuration,
    nextSongInQueue,

    // functions
    initialize,
    handleError,
    clearAllIntervals,
    startProgressInterval,
    updateProgress,
    setVolume,
    toggleMute,
    updateVolumeIcon,
    initializeEqualizer,
    connectHowlerToEqualizer,
    updateEqualizerBand,
    resetEqualizer,
    toggleEqualizer,
    disconnectEqualizer,
    playSong,
    playHowlerSong,
    togglePlayPause,
    stop,
    seek,
    seekToPercent,
    addToQueue,
    addMultipleToQueue,
    removeFromQueue,
    clearQueue,
    playFromQueue,
    playFromHistory,
    moveCurrentToPrevious,
    playPlaylist,
    playAlbum,
    playArtist,
    handleTrackEnded,
    getNextSongIndexAction,
    nextSong,
    prevSong,
    toggleShuffle,
    toggleRepeat,
    showNotification,
    resetPlayer,
    checkLocalModeAvailability,
    toggleLocalMode,
    playMpdSong,
    toggleMpdPlayback,
    stopMpdPlayback,
    setMpdVolume,
    seekMpdPosition,
    navigateMpdPlayback,
    getMpdStatus,
    updateMpdProgress,
    updateCurrentFromMpd,
    updateMediaSession,
    setupEventListeners,
    savePlayerState,
    loadPlayerState,
    moveToNext,
    reorderQueue,
    handleUserLogout,
    destroy,
    clearHistory,
    setCurrentHowl,
    getCurrentHowl,
    getAudioContext,
    getGainNode,
  };
});

// Utility functions
function formatTime(seconds) {
  if (!seconds || isNaN(seconds) || seconds <= 0) return "0:00";
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins}:${secs.toString().padStart(2, "0")}`;
}

function getNextSongIndex(state) {
  // Diese Funktion ist jetzt NUR für den NICHT-SHUFFLE Fall oder für allgemeine Abfragen gedacht,
  // die nicht die spezifische Shuffle-Abspiellogik von nextSong() widerspiegeln.
  // nextSong() und prevSong() haben ihre eigene, detailliertere Logik für Shuffle.

  if (state.actualPlayQueue.length === 0) return -1;

  if (state.repeatMode === "one") {
    // Wenn 'repeat one', ist der nächste Song derselbe.
    return state.currentSongIndexInActualPlayQueue;
  }

  let nextIndex = state.currentSongIndexInActualPlayQueue + 1;

  // Nicht-Shuffle Logik:
  if (nextIndex >= state.actualPlayQueue.length) {
    if (state.repeatMode === "all") {
      nextIndex = 0; // Zurück zum Anfang
    } else {
      return -1; // Ende der Queue, kein Repeat All oder One
    }
  }
  return nextIndex;
}

// Hilfsfunktion für Shuffle (Fisher-Yates)
function shuffleArrayInternal(array) {
  for (let i = array.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [array[i], array[j]] = [array[j], array[i]];
  }
  return array;
}
