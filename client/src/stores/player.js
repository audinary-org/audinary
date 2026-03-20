import { defineStore } from "pinia";
import { ref, computed, watch } from "vue";
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

export const usePlayerStore = defineStore("player", {
  state: () => ({
    // Core playback state
    currentHowl: null,
    nextHowl: null,
    currentSong: null,

    // --- Überarbeitete Queue-Struktur ---
    actualPlayQueue: [], // Die Liste, die abgespielt und angezeigt wird (kann original oder geshuffelt sein)
    originalOrderQueue: [], // Enthält immer die Songs in ihrer ursprünglichen Ladereihenfolge
    currentSongIndexInActualPlayQueue: -1, // Index bezogen auf actualPlayQueue
    // --- Ende überarbeitete Queue-Struktur ---

    previousQueue: [], // History of played songs - bleibt vorerst, Interaktion prüfen
    isPlaying: false,
    isPaused: false,
    isLoading: false,
    currentSongLogged: false,
    isTransitioningSong: false,

    // Erweiterter Shuffle-Zustand - Diese werden entfernt oder gehen in neuer Logik auf
    // originalQueueSnapshot: [],
    // shuffledPlaybackOrder: [],
    // currentShuffledPlaybackIndex: -1,

    // Audio properties
    volume: 0.5,
    lastVolume: 0.5,
    isMuted: false,
    muteAlertId: null, // ID des aktiven Mute-Alerts
    duration: 0,
    currentTime: 0,

    // Playback modes
    isShuffleEnabled: false,
    repeatMode: "none", // 'none', 'one', 'all'

    // Local mode (MPD)
    isLocalMode: false,
    localModeEnabled: false,
    mpdEnabled: false,
    serverDeviceInfo: null,

    // Progress tracking
    progressInterval: null,
    mpdUpdateTimer: null,

    // Audio processing
    audioProcessingEnabled: true,
    bassFilter: null,
    midFilter: null,
    trebleFilter: null,

    // 7-Band Equalizer
    equalizerEnabled: true,
    equalizerGains: {
      60: 0, // 60Hz - Sub Bass
      170: 0, // 170Hz - Bass
      310: 0, // 310Hz - Low Mid
      600: 0, // 600Hz - Mid
      1000: 0, // 1kHz - Upper Mid
      3000: 0, // 3kHz - Presence
      12000: 0, // 12kHz - Brilliance
    },
    equalizerFilters: {},
    audioContext: null,
    gainNode: null,

    // UI state
    showFullscreen: false,
    showQueue: false,
    showEqualizer: false,
    logoutHandler: null,
  }),

  getters: {
    hasQueue: (state) => state.actualPlayQueue.length > 0,

    // Nur die kommenden Songs in der Queue (ohne den aktuell spielenden)
    upcomingQueue: (state) => {
      if (
        state.currentSongIndexInActualPlayQueue === -1 ||
        state.actualPlayQueue.length === 0
      ) {
        return state.actualPlayQueue;
      }
      return state.actualPlayQueue.slice(
        state.currentSongIndexInActualPlayQueue + 1,
      );
    },

    canPlayPrevious: (state) => {
      if (state.repeatMode === "all" && state.actualPlayQueue.length > 0)
        return true;
      return state.currentSongIndexInActualPlayQueue > 0;
    },

    canPlayNext: (state) => {
      if (state.repeatMode === "all" && state.actualPlayQueue.length > 0)
        return true;
      if (state.repeatMode === "one") return true;
      return (
        state.currentSongIndexInActualPlayQueue <
        state.actualPlayQueue.length - 1
      );
    },

    currentSongProgress: (state) => {
      if (!state.duration) return 0;
      return (state.currentTime / state.duration) * 100;
    },

    formattedCurrentTime: (state) => {
      return formatTime(state.currentTime);
    },

    formattedDuration: (state) => {
      return formatTime(state.duration);
    },

    nextSongInQueue: (state) => {
      const nextIndex = getNextSongIndex(state);
      return nextIndex !== -1 ? state.actualPlayQueue[nextIndex] : null;
    },
  },

  actions: {
    // Initialize player
    initialize() {
      // Listen for logout events to clean up player
      if (typeof window !== "undefined") {
        this.logoutHandler = () => this.handleUserLogout();
        window.addEventListener("user-logged-out", this.logoutHandler);
      }
      this.setupEventListeners();
      this.checkLocalModeAvailability();
      this.loadPlayerState();
    },

    // Error handling
    handleError(context, error, fallbackAction = null) {
      console.error(`Player Error in ${context}:`, error);

      if (typeof fallbackAction === "function") {
        try {
          fallbackAction();
        } catch (fallbackError) {
          console.error(`Error in fallback for ${context}:`, fallbackError);
        }
      }
    },

    // Interval management
    clearAllIntervals() {
      if (this.progressInterval) {
        clearInterval(this.progressInterval);
        this.progressInterval = null;
      }

      if (this.mpdUpdateTimer) {
        clearInterval(this.mpdUpdateTimer);
        this.mpdUpdateTimer = null;
      }
    },

    startProgressInterval() {
      this.clearAllIntervals();
      this.progressInterval = setInterval(() => {
        this.updateProgress();
      }, 100);
    },

    // Progress tracking
    updateProgress() {
      if (this.isTransitioningSong) return; // Nicht loggen während eines manuellen Übergangs

      if (this.isLocalMode) {
        // MPD mode - get progress from server
        this.updateMpdProgress();
      } else if (this.currentHowl && this.isPlaying) {
        // Howler mode - get progress from Howler
        this.currentTime = this.currentHowl.seek() || 0;

        // Duration is always from header - no need to check Howler

        // Log played song if > 60% and not already logged
        if (
          this.currentSong &&
          !this.currentSongLogged &&
          this.duration > 0 &&
          this.currentTime / this.duration >= 0.6
        ) {
          // Check if we're in a public/shared playlist context - don't log plays there
          const currentPath = window.location.pathname;
          const isPublicContext =
            currentPath.includes("/shared/") ||
            currentPath.includes("/public/") ||
            currentPath.startsWith("/shared") ||
            currentPath.startsWith("/public");

          if (!isPublicContext) {
            const progressPercent = (this.currentTime / this.duration) * 100;
            const apiStore = useApiStore();
            apiStore.logPlayedSong(
              this.currentSong.song_id || this.currentSong.id,
            );
          }
          this.currentSongLogged = true;
        }
      }
    },

    // Volume control
    setVolume(value) {
      const previousVolume = this.volume;
      this.volume = Math.max(0, Math.min(1, value));

      // Automatisch unmuten wenn Lautstärke über 0 gesetzt wird
      if (this.volume > 0 && this.isMuted) {
        this.isMuted = false;

        // Entferne Mute-Alert falls vorhanden
        if (this.muteAlertId) {
          const alertStore = useAlertStore();
          alertStore.removeAlert(this.muteAlertId);
          this.muteAlertId = null;
        }
      }
      // Zeige Alert wenn Lautstärke auf 0 gesetzt wird (egal ob via Slider oder Mute-Button)
      else if (this.volume === 0 && previousVolume > 0 && !this.muteAlertId) {
        this.isMuted = true;
        this.lastVolume = previousVolume;

        const alertStore = useAlertStore();
        this.muteAlertId = alertStore.warning(
          "Die Lautstärke ist stumm geschaltet. Klicken Sie auf das Lautstärke-Symbol oder bewegen Sie den Regler, um die Wiedergabe zu hören.",
          0, // 0 = persistent (kein Auto-Hide)
        );
      }

      if (this.isLocalMode) {
        this.setMpdVolume(this.volume * 100);
      } else if (this.currentHowl) {
        this.currentHowl.volume(this.volume);
      }

      this.updateVolumeIcon();
      this.savePlayerState();
    },

    toggleMute() {
      if (this.isMuted) {
        // Unmute - restore previous volume
        this.setVolume(this.lastVolume);
        this.isMuted = false;

        // Entferne Mute-Alert falls vorhanden
        if (this.muteAlertId) {
          const alertStore = useAlertStore();
          alertStore.removeAlert(this.muteAlertId);
          this.muteAlertId = null;
        }
      } else {
        // Mute - save current volume and set to 0
        this.lastVolume = this.volume > 0 ? this.volume : 0.5;
        this.setVolume(0);
        this.isMuted = true;
      }
    },

    updateVolumeIcon() {
      // This would be handled by the UI component
      // Emit event for UI updates if needed
    },

    // Equalizer methods
    initializeEqualizer() {
      if (!this.currentHowl || this.isLocalMode) return;

      try {
        // Create AudioContext if not exists
        if (!this.audioContext) {
          const AudioContextClass =
            window.AudioContext ||
            window.webkitAudioContext ||
            window.mozAudioContext;
          this.audioContext = new AudioContextClass();
        }

        // Create gain node
        if (!this.gainNode) {
          this.gainNode = this.audioContext.createGain();
        }

        // Create equalizer filters
        const frequencies = Object.keys(this.equalizerGains).map((f) =>
          parseInt(f),
        );

        frequencies.forEach((frequency) => {
          if (!this.equalizerFilters[frequency]) {
            const filter = this.audioContext.createBiquadFilter();
            filter.type = "peaking";
            filter.frequency.value = frequency;
            filter.Q.value = 1;
            filter.gain.value = this.equalizerGains[frequency.toString()];
            this.equalizerFilters[frequency] = filter;
          }
        });

        // Connect filters in series
        let previousNode = this.gainNode;
        frequencies.forEach((frequency) => {
          const filter = this.equalizerFilters[frequency];
          previousNode.connect(filter);
          previousNode = filter;
        });

        // Connect final filter to destination
        previousNode.connect(this.audioContext.destination);

        // Try to connect Howler audio to our filter chain
        this.connectHowlerToEqualizer();
      } catch (error) {
        console.warn("Equalizer initialization failed:", error);
        this.equalizerEnabled = false;
      }
    },

    connectHowlerToEqualizer() {
      if (!this.currentHowl || !this.audioContext || !this.gainNode) return;

      try {
        // This is tricky with Howler - we need to access the HTML5 audio element
        const audioNode = this.currentHowl._sounds[0]._node;
        if (audioNode && audioNode.tagName === "AUDIO") {
          // Check if this audio element already has a source node
          if (!audioNode._mediaElementSource) {
            audioNode._mediaElementSource =
              this.audioContext.createMediaElementSource(audioNode);
          }

          // Disconnect any existing connections before reconnecting
          audioNode._mediaElementSource.disconnect();
          audioNode._mediaElementSource.connect(this.gainNode);
        }
      } catch (error) {
        console.warn("Failed to connect Howler to equalizer:", error);
      }
    },

    updateEqualizerBand(frequency, gain) {
      const freq = frequency.toString();
      if (this.equalizerGains.hasOwnProperty(freq)) {
        this.equalizerGains[freq] = parseFloat(gain);

        // Update the actual filter
        if (
          this.equalizerFilters[parseInt(frequency)] &&
          this.equalizerEnabled
        ) {
          this.equalizerFilters[parseInt(frequency)].gain.value = gain;
        }

        this.savePlayerState();
      }
    },

    resetEqualizer() {
      Object.keys(this.equalizerGains).forEach((freq) => {
        this.equalizerGains[freq] = 0;
        if (this.equalizerFilters[parseInt(freq)]) {
          this.equalizerFilters[parseInt(freq)].gain.value = 0;
        }
      });
      this.savePlayerState();
    },

    toggleEqualizer() {
      this.equalizerEnabled = !this.equalizerEnabled;

      if (this.equalizerEnabled) {
        this.initializeEqualizer();
      } else {
        this.disconnectEqualizer();
      }

      this.savePlayerState();
    },

    disconnectEqualizer() {
      try {
        // Reset all filter gains to 0
        Object.keys(this.equalizerFilters).forEach((freq) => {
          if (this.equalizerFilters[freq]) {
            this.equalizerFilters[freq].gain.value = 0;
          }
        });
      } catch (error) {
        console.warn("Error disconnecting equalizer:", error);
      }
    },

    // Playback control
    async playSong(song, index = null) {
      // 1. Vorab prüfen, ob wir von einem anderen Song wechseln und dessen Log-Status versiegeln
      if (
        this.currentSong &&
        (this.currentSong.song_id || this.currentSong.id) !==
          (song.song_id || song.id)
      ) {
        if (this.isPlaying || this.isPaused) {
          // Nur wenn der alte Song aktiv war (gespielt oder pausiert)
          this.currentSongLogged = true; // Verhindert Last-Minute-Logging des ausgehenden Songs
        }
      }

      this.isTransitioningSong = true; // Übergang beginnt jetzt offiziell

      try {
        this.isLoading = true;

        // Move current song to previous queue if changing to a different song
        if (
          this.currentSong &&
          (this.currentSong.song_id || this.currentSong.id) !==
            (song.song_id || song.id)
        ) {
          this.moveCurrentToPrevious();
        }

        // Wenn kein Index angegeben, prüfe ob Song bereits in Queue ist
        if (index === null) {
          const existingIndex = this.actualPlayQueue.findIndex(
            (q) => (q.song_id || q.id) === (song.song_id || song.id),
          );
          if (existingIndex !== -1) {
            // Song ist bereits in Queue, spiele ihn direkt
            index = existingIndex;
          } else {
            // Song ist nicht in Queue, füge ihn hinzu
            this.addToQueue(song);
            index = this.actualPlayQueue.length - 1;
          }
        }

        // Set current song and index
        this.currentSongIndexInActualPlayQueue = index;
        this.currentSong = { ...song };
        this.currentSongLogged = false; // Reset logged status for new song

        // Update MediaSession
        this.updateMediaSession(this.currentSong);

        // Start progress tracking
        this.startProgressInterval();

        if (this.isLocalMode) {
          await this.playMpdSong(song.song_id || song.id);
        } else {
          await this.playHowlerSong(song);
        }
      } catch (error) {
        this.handleError("playing song", error);
        this.isTransitioningSong = false; // Wichtig: Flag im Fehlerfall zurücksetzen
        this.isLoading = false; // Auch isLoading im Fehlerfall zurücksetzen
      } finally {
        // isLoading wird meist schon in playHowlerSong/playMpdSong oder im Catch-Block oben gehandhabt.
        // isTransitioningSong wird bei Erfolg durch onplay (Howler) oder updateMpdProgress (MPD) zurückgesetzt.
      }
    },

    async playHowlerSong(song) {
      try {
        const apiStore = useApiStore();
        // Use custom stream URL for public playlists or standard API for regular playback
        const streamUrl =
          song.customStreamUrl ||
          (await apiStore.playSongSrc(song.song_id || song.id));

        // Stop current howl if exists
        if (this.currentHowl) {
          this.currentHowl.stop();
          this.currentHowl.unload();
        }

        // Get duration from HTTP headers (single source of truth)
        try {
          const headResponse = await fetch(streamUrl, { method: "HEAD" });
          const durationHeader =
            headResponse.headers.get("x-content-duration") ||
            headResponse.headers.get("x-media-duration");

          if (durationHeader) {
            this.duration = parseFloat(durationHeader);
          } else {
            this.duration = 0;
          }
        } catch (error) {
          console.error("Error fetching duration:", error);
          this.duration = 0;
        }

        this.currentHowl = new Howl({
          src: [streamUrl],
          format: ["flac", "mp3", "ogg", "wav", "aac", "m4a"],
          html5: true,
          volume: this.volume,
          onload: () => {
            this.isLoading = false;
          },
          onplay: () => {
            this.isPlaying = true;
            this.isPaused = false;
            this.isLoading = false;
            this.isTransitioningSong = false; // Übergang beendet, Song spielt

            // Initialize equalizer when song starts playing
            if (this.equalizerEnabled) {
              setTimeout(() => this.initializeEqualizer(), 100);
            }
          },
          onpause: () => {
            this.isPlaying = false;
            this.isPaused = true;
          },
          onend: () => {
            this.handleTrackEnded();
          },
          onerror: (id, error) => {
            this.isLoading = false;
            this.handleError("Howler playback", new Error(error));
          },
        });

        this.currentHowl.play();
      } catch (error) {
        this.isLoading = false;
        this.handleError("creating Howler instance", error);
      }
    },

    togglePlayPause() {
      if (this.isLocalMode) {
        this.toggleMpdPlayback();
      } else if (this.currentHowl) {
        if (this.isPlaying) {
          this.currentHowl.pause();
        } else {
          this.currentHowl.play();
          // Ensure progress tracking is running when resuming
          if (!this.progressInterval) {
            this.startProgressInterval();
          }
        }
      } else if (this.actualPlayQueue.length > 0) {
        this.playSong(
          this.actualPlayQueue[this.currentSongIndexInActualPlayQueue || 0],
          this.currentSongIndexInActualPlayQueue || 0,
        );
      }
    },

    stop() {
      if (this.isLocalMode) {
        this.stopMpdPlayback();
      } else if (this.currentHowl) {
        this.currentHowl.stop();
      }

      this.isPlaying = false;
      this.isPaused = false;
      this.currentTime = 0;
      this.clearAllIntervals();
    },

    seek(position) {
      if (this.isLocalMode) {
        this.seekMpdPosition(position);
      } else if (this.currentHowl) {
        this.currentHowl.seek(position);
      }
    },

    seekToPercent(percent) {
      const position = (percent / 100) * this.duration;
      this.seek(position);
    },

    addToQueue(song) {
      const songId = song.song_id || song.id;
      // Prüfe gegen originalOrderQueue, da dies die "Master"-Liste der einzigartigen Songs sein soll.
      if (
        !this.originalOrderQueue.find((q) => (q.song_id || q.id) === songId)
      ) {
        this.originalOrderQueue.push(song); // Füge zur Master-Liste hinzu

        // Aktualisiere actualPlayQueue basierend auf Shuffle-Status
        if (this.isShuffleEnabled) {
          // Behalte den aktuellen Song bei, wenn möglich
          const songToMaintain = this.currentSong;
          this.actualPlayQueue = shuffleArrayInternal([
            ...this.originalOrderQueue,
          ]);
          if (songToMaintain) {
            const newIndexOfMaintainedSong = this.actualPlayQueue.findIndex(
              (s) =>
                (s.song_id || s.id) ===
                (songToMaintain.song_id || songToMaintain.id),
            );
            if (newIndexOfMaintainedSong !== -1) {
              this.currentSongIndexInActualPlayQueue = newIndexOfMaintainedSong;
            } else {
              // Aktueller Song wurde durch Hinzufügen entfernt (sollte nicht passieren) oder war nicht in original
              this.currentSongIndexInActualPlayQueue =
                this.actualPlayQueue.length > 0 ? 0 : -1; // Fallback
            }
          } else {
            // Wenn kein Song spielte, currentSongIndexInActualPlayQueue bleibt (oder wird -1 wenn actualPlayQueue leer)
            // Es wird nicht automatisch ein Song gestartet, nur weil einer hinzugefügt wurde.
            if (
              this.actualPlayQueue.length > 0 &&
              this.currentSongIndexInActualPlayQueue === -1
            ) {
              // Spezialfall: Queue war leer, erster Song hinzugefügt, Shuffle an -> Index auf 0
              // Aber nicht automatisch spielen. Das macht playSong, wenn es explizit aufgerufen wird.
              this.currentSongIndexInActualPlayQueue = 0;
            }
          }
        } else {
          // actualPlayQueue sollte eine Referenz auf (oder Kopie von) originalOrderQueue sein.
          // Da originalOrderQueue bereits aktualisiert wurde, aktualisiere actualPlayQueue.
          this.actualPlayQueue = [...this.originalOrderQueue];
          // currentSongIndexInActualPlayQueue muss nicht angepasst werden, wenn hinten angefügt wird
          // und der aktuelle Song nicht betroffen ist.
        }
      } else {
      }
    },

    removeFromQueue(index) {
      // Der Index bezieht sich auf die angezeigte Liste, also actualPlayQueue.
      if (index < 0 || index >= this.actualPlayQueue.length) return;

      const removedSongFromActual = this.actualPlayQueue[index];

      // Entferne den Song zuerst aus der originalOrderQueue, um Konsistenz zu wahren.
      const originalIndexToRemove = this.originalOrderQueue.findIndex(
        (s) =>
          (s.song_id || s.id) ===
          (removedSongFromActual.song_id || removedSongFromActual.id),
      );
      if (originalIndexToRemove !== -1) {
        this.originalOrderQueue.splice(originalIndexToRemove, 1);
      } else {
        // Sollte nicht passieren, wenn actualPlayQueue aus originalOrderQueue abgeleitet ist.
        // Trotzdem aus actualPlayQueue entfernen, um UI-Konsistenz zu haben:
        this.actualPlayQueue.splice(index, 1);
        // Hier ist eine Neubewertung des Zustands nötig, aber der Fehlerfall ist kritisch.
        return;
      }

      const isRemovingCurrentSong = this.currentSong
        ? (this.currentSong.song_id || this.currentSong.id) ===
          (removedSongFromActual.song_id || removedSongFromActual.id)
        : false;
      let songToMaintainAfterRemove = isRemovingCurrentSong
        ? null
        : this.currentSong;

      if (this.isShuffleEnabled) {
        this.actualPlayQueue = shuffleArrayInternal([
          ...this.originalOrderQueue,
        ]);

        if (songToMaintainAfterRemove) {
          const newIndexOfMaintained = this.actualPlayQueue.findIndex(
            (s) =>
              (s.song_id || s.id) ===
              (songToMaintainAfterRemove.song_id ||
                songToMaintainAfterRemove.id),
          );
          if (newIndexOfMaintained !== -1) {
            this.currentSongIndexInActualPlayQueue = newIndexOfMaintained;
          } else {
            // Beibehaltener Song ist nicht mehr in der neuen Shuffle-Liste (sollte nicht sein, wenn er nicht der entfernte war)
            this.currentSongIndexInActualPlayQueue =
              this.actualPlayQueue.length > 0 ? 0 : -1;
          }
        } else if (this.actualPlayQueue.length > 0) {
          // Aktueller Song wurde entfernt ODER es gab keinen -> setze auf Anfang der neuen Shuffle-Liste
          this.currentSongIndexInActualPlayQueue = 0;
          // Wenn der aktuelle Song entfernt wurde und die Liste noch Elemente hat, spiele den neuen ersten Song.
          if (isRemovingCurrentSong) {
            this.playSong(
              this.actualPlayQueue[this.currentSongIndexInActualPlayQueue],
              this.currentSongIndexInActualPlayQueue,
            );
          }
        } else {
          // actualPlayQueue ist jetzt leer
          this.currentSongIndexInActualPlayQueue = -1;
          if (isRemovingCurrentSong) this.resetPlayer();
        }
      } else {
        // Shuffle ist AUS: actualPlayQueue direkt anpassen.
        // Da originalOrderQueue schon angepasst wurde, hier actualPlayQueue neu setzen.
        this.actualPlayQueue = [...this.originalOrderQueue];

        if (isRemovingCurrentSong) {
          this.stop();
          if (this.actualPlayQueue.length === 0) {
            this.resetPlayer();
          } else {
            // Setze auf den Index des entfernten Songs, wenn noch Elemente da sind, sonst eins weniger.
            // playSong wird dann den Song an diesem Index spielen oder den Player resetten.
            let nextIndexToPlay =
              index < this.actualPlayQueue.length
                ? index
                : this.actualPlayQueue.length - 1;
            if (nextIndexToPlay >= 0) {
              this.playSong(
                this.actualPlayQueue[nextIndexToPlay],
                nextIndexToPlay,
              );
            } else {
              this.resetPlayer();
            }
          }
        } else {
          // Wenn ein Song vor dem currentSong entfernt wurde, muss der Index angepasst werden.
          // Finde den currentSong in der neuen actualPlayQueue, um den korrekten Index zu haben.
          if (this.currentSong) {
            const newCurrentIdx = this.actualPlayQueue.findIndex(
              (s) =>
                (s.song_id || s.id) ===
                (this.currentSong.song_id || this.currentSong.id),
            );
            if (newCurrentIdx !== -1) {
              this.currentSongIndexInActualPlayQueue = newCurrentIdx;
            } else {
              // CurrentSong nicht mehr da (sollte nicht sein, wenn nicht isRemovingCurrentSong)
              this.currentSongIndexInActualPlayQueue =
                this.actualPlayQueue.length > 0 ? 0 : -1;
              if (this.currentSongIndexInActualPlayQueue === -1)
                this.resetPlayer();
            }
          } else {
            this.currentSongIndexInActualPlayQueue = -1; // Kein aktueller Song
          }
        }
      }
    },

    clearQueue() {
      this.stop(); // Stoppe aktuelle Wiedergabe

      this.actualPlayQueue = [];
      this.originalOrderQueue = []; // Auch die Master-Liste leeren
      this.previousQueue = [];
      this.currentSongIndexInActualPlayQueue = -1;

      // Die alten, spezifischen Shuffle-Variablen sind bereits entfernt/auskommentiert.
      // originalQueueSnapshot: [],
      // shuffledPlaybackOrder: [],
      // currentShuffledPlaybackIndex: -1,

      this.resetPlayer(); // Führt weitere Resets durch (currentSong, currentTime etc.)
    },

    playFromQueue(index) {
      if (index >= 0 && index < this.upcomingQueue.length) {
        // Calculate actual queue index
        const actualIndex = this.currentSongIndexInActualPlayQueue + 1 + index;
        const songToPlay = this.actualPlayQueue[actualIndex];

        this.isTransitioningSong = true;

        // Move current song to previous queue (history) if there is one
        if (this.currentSong) {
          this.moveCurrentToPrevious();
        }

        // Move songs that were above the clicked song to the front of the upcoming queue
        // This preserves the order: songs 1-4 will be played after the clicked song (song 5)
        const songsToMoveToFront = this.actualPlayQueue.slice(
          this.currentSongIndexInActualPlayQueue + 1,
          actualIndex,
        );
        const songsAfterClicked = this.actualPlayQueue.slice(actualIndex + 1);

        // Rebuild the queue: songs before current + clicked song + songs that were above + remaining songs
        const newQueue = [
          ...this.actualPlayQueue.slice(
            0,
            this.currentSongIndexInActualPlayQueue + 1,
          ),
          songToPlay,
          ...songsToMoveToFront,
          ...songsAfterClicked,
        ];

        this.actualPlayQueue = newQueue;
        this.currentSongIndexInActualPlayQueue =
          this.currentSongIndexInActualPlayQueue + 1;

        // Update originalOrderQueue if shuffle is disabled
        if (!this.isShuffleEnabled) {
          this.originalOrderQueue = [...this.actualPlayQueue];
        }

        this.currentSong = { ...songToPlay };
        this.currentSongLogged = false; // Reset logged status for new song

        // Update MediaSession
        this.updateMediaSession(this.currentSong);

        // Start progress tracking
        this.startProgressInterval();

        // Play the song
        if (this.isLocalMode) {
          this.playMpdSong(songToPlay.song_id || songToPlay.id);
        } else {
          this.playHowlerSong(songToPlay);
        }
      }
    },

    playFromHistory(index) {
      if (index >= 0 && index < this.previousQueue.length) {
        const song = this.previousQueue[index];
        this.isTransitioningSong = true; // Übergang beginnt
        // Add current song to queue if there is one
        if (this.currentSong) {
          this.actualPlayQueue.unshift(this.currentSong);
          this.currentSongIndexInActualPlayQueue++;
        }
        // Remove songs from history and add to queue
        const removedSongs = this.previousQueue.splice(index);
        this.actualPlayQueue.unshift(...removedSongs.reverse());
        this.currentSongIndexInActualPlayQueue = index;
        this.playSong(song, this.currentSongIndexInActualPlayQueue);
      }
    },

    // Update playSong method to manage previous queue
    moveCurrentToPrevious() {
      if (this.currentSong && this.currentSongIndexInActualPlayQueue >= 0) {
        // Add current song to previous queue
        this.previousQueue.push(this.currentSong);

        // Limit previous queue size (e.g., last 50 songs)
        if (this.previousQueue.length > 50) {
          this.previousQueue.shift();
        }
      }
    },

    playPlaylist(songs) {
      this.isTransitioningSong = true;
      this.originalOrderQueue = [...songs];
      this.previousQueue = [];

      if (this.isShuffleEnabled) {
        this.actualPlayQueue = shuffleArrayInternal([
          ...this.originalOrderQueue,
        ]);
        this.currentSongIndexInActualPlayQueue =
          this.actualPlayQueue.length > 0 ? 0 : -1;

        if (this.currentSongIndexInActualPlayQueue !== -1) {
          this.playSong(
            this.actualPlayQueue[this.currentSongIndexInActualPlayQueue],
            this.currentSongIndexInActualPlayQueue,
          );
        } else {
          this.resetPlayer();
        }
      } else {
        this.actualPlayQueue = [...this.originalOrderQueue];
        this.currentSongIndexInActualPlayQueue =
          this.actualPlayQueue.length > 0 ? 0 : -1;

        if (this.currentSongIndexInActualPlayQueue !== -1) {
          this.playSong(
            this.actualPlayQueue[this.currentSongIndexInActualPlayQueue],
            this.currentSongIndexInActualPlayQueue,
          );
        } else {
          this.resetPlayer();
        }
      }
    },

    async playAlbum(albumId) {
      try {
        const apiStore = useApiStore();
        const response = await apiStore.getAlbumTracks(albumId);
        if (response.success) {
          this.isTransitioningSong = true; // Übergang beginnt, nachdem Tracks geladen wurden
          this.playPlaylist(response.tracks);
        }
      } catch (error) {
        this.handleError("playing album", error);
      }
    },

    async playArtist(artistId) {
      try {
        const apiStore = useApiStore();
        const response = await apiStore.getArtistTracks(artistId);
        if (response.success) {
          this.isTransitioningSong = true; // Übergang beginnt, nachdem Tracks geladen wurden
          this.playPlaylist(response.tracks);
        } else if (response.tracks) {
          // Fallback if response structure is different
          this.isTransitioningSong = true;
          this.playPlaylist(response.tracks);
        } else if (Array.isArray(response)) {
          // Direct array response
          this.isTransitioningSong = true;
          this.playPlaylist(response);
        }
      } catch (error) {
        this.handleError("playing artist", error);
      }
    },

    // Handle track ended
    handleTrackEnded() {
      if (this.currentSong && this.repeatMode !== "one") {
        this.currentSongLogged = true;
      }

      if (this.repeatMode === "one") {
        if (this.currentHowl && this.currentSong) {
          this.currentSongLogged = false;
          this.isTransitioningSong = true;
          this.currentHowl.seek(0);
          this.currentHowl.play();
          this.showNotification(
            getI18nMessage("player.repeatCurrentSong"),
            2000,
          );
        }
        return;
      }

      this.nextSong();
    },

    // Get next song index based on shuffle and repeat modes
    getNextSongIndex() {
      if (this.actualPlayQueue.length === 0) return -1;

      if (this.repeatMode === "one") {
        return this.currentSongIndexInActualPlayQueue;
      }

      let nextIndex = this.currentSongIndexInActualPlayQueue + 1;

      if (nextIndex >= this.actualPlayQueue.length) {
        if (this.repeatMode === "all") {
          nextIndex = 0;
        } else {
          return -1;
        }
      }
      return nextIndex;
    },

    nextSong() {
      if (this.isLocalMode) {
        this.isTransitioningSong = true;
        this.navigateMpdPlayback("next")
          .then((success) => {
            if (success) {
              this.startProgressInterval();
              this.getMpdStatus()
                .then((status) => {
                  if (status && status.success && status.currentSong) {
                    this.updateCurrentFromMpd(status);
                  }
                })
                .catch((err) =>
                  this.handleError("getting MPD status after next", err),
                );
            }
          })
          .catch((err) =>
            this.handleError("navigating to next MPD track", err),
          );
        return;
      }

      if (this.actualPlayQueue.length === 0) {
        console.warn("[nextSong] actualPlayQueue is empty.");
        this.resetPlayer();
        return;
      }

      let nextIndex = this.currentSongIndexInActualPlayQueue + 1;

      if (this.isShuffleEnabled) {
        if (nextIndex >= this.actualPlayQueue.length) {
          if (this.repeatMode === "all") {
            this.actualPlayQueue = shuffleArrayInternal([
              ...this.originalOrderQueue,
            ]);
            nextIndex = this.actualPlayQueue.length > 0 ? 0 : -1;
            if (nextIndex === -1) {
              this.resetPlayer();
              return;
            }
          } else {
            this.resetPlayer();
            return;
          }
        }
      } else {
        if (nextIndex >= this.actualPlayQueue.length) {
          if (this.repeatMode === "all") {
            nextIndex = 0;
          } else if (this.repeatMode === "one") {
            nextIndex = this.currentSongIndexInActualPlayQueue;
          } else {
            this.resetPlayer();
            return;
          }
        } else if (this.repeatMode === "one") {
          nextIndex = this.currentSongIndexInActualPlayQueue;
        }
      }

      if (nextIndex !== -1 && nextIndex < this.actualPlayQueue.length) {
        this.isTransitioningSong = true;
        this.playSong(this.actualPlayQueue[nextIndex], nextIndex);
      } else if (
        nextIndex === -1 &&
        this.actualPlayQueue.length > 0 &&
        this.repeatMode === "one"
      ) {
        if (this.currentSongIndexInActualPlayQueue !== -1) {
          this.isTransitioningSong = true;
          this.playSong(
            this.actualPlayQueue[this.currentSongIndexInActualPlayQueue],
            this.currentSongIndexInActualPlayQueue,
          );
        }
      } else {
        console.warn(
          "[nextSong] No valid next song found or index out of bounds after logic. Queue Length:",
          this.actualPlayQueue.length,
          "Target Index:",
          nextIndex,
        );
      }
    },

    // Previous song
    prevSong() {
      if (this.isLocalMode) {
        this.isTransitioningSong = true;
        this.navigateMpdPlayback("prev")
          .then((success) => {
            if (success) {
              this.startProgressInterval();
              this.getMpdStatus()
                .then((status) => {
                  if (status && status.success && status.currentSong) {
                    this.updateCurrentFromMpd(status);
                  }
                })
                .catch((err) =>
                  this.handleError("getting MPD status after prev", err),
                );
            }
          })
          .catch((err) =>
            this.handleError("navigating to previous MPD track", err),
          );
        return; // MPD handelt anders
      }

      if (
        this.actualPlayQueue.length === 0 &&
        this.previousQueue.length === 0
      ) {
        console.warn("[prevSong] actualPlayQueue and previousQueue are empty.");
        this.showNotification(
          getI18nMessage("player.noPreviousSongAvailable"),
          2000,
        );
        return;
      }

      let prevIndex = this.currentSongIndexInActualPlayQueue - 1;

      if (this.isShuffleEnabled) {
        if (prevIndex < 0) {
          if (this.repeatMode === "all") {
            // Optional: Neu mischen oder zum Ende der aktuellen Mischung. Für Konsistenz mit nextSong: neu mischen.
            this.actualPlayQueue = shuffleArrayInternal([
              ...this.originalOrderQueue,
            ]);
            prevIndex = this.actualPlayQueue.length - 1; // Gehe zum letzten Song der neuen Shuffle-Liste
            if (this.actualPlayQueue.length === 0) {
              // Sollte nach Shuffle nicht passieren, wenn original nicht leer war
              this.resetPlayer();
              return;
            }
          } else {
            this.showNotification(
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
          if (this.previousQueue.length > 0) {
            const songFromHistory = this.previousQueue.pop(); // Nimm letzten aus History
            // Füge aktuellen Song (falls vorhanden) an den Anfang der actualPlayQueue (wird zu History für nächsten prev)
            if (this.currentSong) {
              this.actualPlayQueue.unshift(this.currentSong);
              // Da wir an den Anfang von actualPlayQueue unshift-en und dann songFromHistory spielen (der dann auch wieder in die actualPlayQueue kommt),
              // müssen wir aufpassen, dass der Index aktuell bleibt.
              // Besser: aktuellen in History, songFromHistory an Anfang der originalOrderQueue und actualPlayQueue neu bilden.
            }
            this.originalOrderQueue.unshift(songFromHistory); // Füge ihn an den Anfang der Master-Liste
            this.actualPlayQueue = [...this.originalOrderQueue]; // Aktualisiere Play-Queue
            prevIndex = 0; // Der Song aus der History ist jetzt der erste
            this.currentSongIndexInActualPlayQueue = -1; // Erzwinge, dass playSong den neuen Index setzt und currentSong aktualisiert
          } else if (this.repeatMode === "all") {
            prevIndex = this.actualPlayQueue.length - 1;
          } else {
            this.showNotification(
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

      if (prevIndex !== -1 && prevIndex < this.actualPlayQueue.length) {
        this.isTransitioningSong = true;
        this.playSong(this.actualPlayQueue[prevIndex], prevIndex);
      } else {
      }
    },

    // Toggle shuffle mode
    toggleShuffle() {
      this.isShuffleEnabled = !this.isShuffleEnabled;
      let songToMaintain = this.currentSong; // Song, den wir versuchen beizubehalten
      let newPlayIndex = -1;

      if (this.isShuffleEnabled) {
        // originalOrderQueue sollte bereits die Songs in ihrer Hinzufüge-/Albumreihenfolge enthalten.
        // Wenn sie leer ist (z.B. erster Start und Queue wurde direkt befüllt), kopiere actualPlayQueue als Basis.
        if (
          this.originalOrderQueue.length === 0 &&
          this.actualPlayQueue.length > 0
        ) {
          this.originalOrderQueue = [...this.actualPlayQueue];
        }

        // Mische die originalOrderQueue, um die neue actualPlayQueue zu erstellen.
        this.actualPlayQueue = shuffleArrayInternal([
          ...this.originalOrderQueue,
        ]);

        if (songToMaintain) {
          newPlayIndex = this.actualPlayQueue.findIndex(
            (s) =>
              (s.song_id || s.id) ===
              (songToMaintain.song_id || songToMaintain.id),
          );
        } else if (this.actualPlayQueue.length > 0) {
          newPlayIndex = 0; // Kein Song spielte, starte mit dem ersten der neuen Shuffle-Liste
        }
        this.currentSongIndexInActualPlayQueue = newPlayIndex;
        // Wenn ein Index gefunden wurde und es der erste Song ist oder ein anderer als vorher, oder kein Song spielte, starte ihn ggf.
        // Vorsicht: Nicht automatisch starten, wenn vorher schon ein Song lief und nur die Reihenfolge geändert wurde.
        // playSong wird später durch next/prev oder direkten Klick aufgerufen.
        // Ein Spezialfall ist, wenn vorher nichts lief und jetzt Shuffle aktiviert wird -> starte ersten Song.
        if (
          newPlayIndex !== -1 &&
          !songToMaintain &&
          this.actualPlayQueue.length > 0
        ) {
          this.playSong(this.actualPlayQueue[newPlayIndex], newPlayIndex);
        } else {
          // Update MediaSession falls songToMaintain existiert und gefunden wurde.
          if (songToMaintain && newPlayIndex !== -1)
            this.updateMediaSession(this.actualPlayQueue[newPlayIndex]);
        }
      } else {
        // Kehre zur originalOrderQueue zurück.
        // songToMaintain ist immer noch this.currentSong (aus der vorher geshuffelten actualPlayQueue)

        this.actualPlayQueue = [...this.originalOrderQueue]; // Stelle die ursprüngliche Reihenfolge wieder her.

        if (songToMaintain) {
          newPlayIndex = this.actualPlayQueue.findIndex(
            (s) =>
              (s.song_id || s.id) ===
              (songToMaintain.song_id || songToMaintain.id),
          );
        } else if (this.actualPlayQueue.length > 0) {
          newPlayIndex = 0; // Fallback, sollte nicht oft nötig sein
        }
        this.currentSongIndexInActualPlayQueue = newPlayIndex;
        // MediaSession sollte auch hier aktualisiert werden, wenn ein Song beibehalten wurde.
        if (songToMaintain && newPlayIndex !== -1)
          this.updateMediaSession(this.actualPlayQueue[newPlayIndex]);
      }

      const message = this.isShuffleEnabled
        ? getI18nMessage("player.shuffleEnabled")
        : getI18nMessage("player.shuffleDisabled");
      this.showNotification(message, 2000);
      this.savePlayerState(); // Speichere isShuffleEnabled
    },

    // Toggle repeat mode
    toggleRepeat() {
      switch (this.repeatMode) {
        case "none":
          this.repeatMode = "one";
          this.showNotification(
            getI18nMessage("player.singleRepeatEnabled"),
            2000,
          );
          break;
        case "one":
          this.repeatMode = "all";
          this.showNotification(
            getI18nMessage("player.playlistRepeatEnabled"),
            2000,
          );
          break;
        case "all":
          this.repeatMode = "none";
          this.showNotification(getI18nMessage("player.repeatDisabled"), 2000);
          break;
      }

      this.savePlayerState();
    },

    // Show notification
    showNotification(message, duration = 3000) {
      // Use the global alert store
      const alertStore = useAlertStore();
      alertStore.info(message);

      // Also emit event for any components listening
      document.dispatchEvent(
        new CustomEvent("player-notification", {
          detail: { message, duration },
        }),
      );
    },

    // Reset player - vollständiger Reset aller UI-Elemente
    resetPlayer() {
      // Stop playback
      this.isPlaying = false;
      this.isPaused = false;
      this.isLoading = false;

      // Reset time and duration
      this.currentTime = 0;
      this.duration = 0;

      // Reset current song and queue state
      this.currentSong = null;
      this.currentSongIndexInActualPlayQueue = -1;
      this.currentSongLogged = false; // Reset logged status

      // Reset mute state and remove alerts
      if (this.muteAlertId) {
        const alertStore = useAlertStore();
        alertStore.removeAlert(this.muteAlertId);
        this.muteAlertId = null;
      }

      // Stop and cleanup Howler
      if (this.currentHowl) {
        this.currentHowl.stop();
        this.currentHowl.unload();
        this.currentHowl = null;
      }

      // Clear intervals
      this.clearAllIntervals();

      // Reset MediaSession
      if ("mediaSession" in navigator) {
        navigator.mediaSession.playbackState = "none";
        navigator.mediaSession.metadata = null;
      }
    },

    // Local Mode / MPD Functions
    async checkLocalModeAvailability() {
      try {
        const apiStore = useApiStore();
        const response = await apiStore.makeRequest("/api/config");
        if (response.ok) {
          const config = await response.json();
          this.mpdEnabled = config.mpd?.enabled === true;

          if (this.mpdEnabled) {
            const mpdResponse = await apiStore.makeRequest("/api/mpd/status");
            if (mpdResponse.ok) {
              const data = await mpdResponse.json();
              this.localModeEnabled = data.success || false;
            }
          }
        }
      } catch (error) {
        this.handleError("checking local mode availability", error);
        this.mpdEnabled = false;
        this.localModeEnabled = false;
      }
    },

    async toggleLocalMode() {
      if (!this.mpdEnabled || !this.localModeEnabled) {
        this.showNotification(getI18nMessage("player.mpdNotAvailable"), 2000);
        return false;
      }

      try {
        this.isTransitioningSong = true; // Übergang zwischen Modi beginnt
        this.isLocalMode = !this.isLocalMode;

        if (this.isLocalMode) {
          this.showNotification(getI18nMessage("player.playingOnServer"), 2000);
          if (this.currentSong) {
            await this.playMpdSong(
              this.currentSong.song_id || this.currentSong.id,
            );
          }
        } else {
          this.showNotification(
            getI18nMessage("player.playingInBrowser"),
            2000,
          );
          await this.stopMpdPlayback();
          if (this.currentSong) {
            await this.playHowlerSong(this.currentSong);
          }
        }

        // isTransitioningSong wird in playHowlerSong (onplay) oder updateMpdProgress (isPlaying) zurückgesetzt
        return true;
      } catch (error) {
        this.handleError("toggling local mode", error);
        this.isTransitioningSong = false; // Fehlerfall, Übergang abbrechen
        return false;
      }
    },

    async playMpdSong(songId, position = 0) {
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
        this.handleError("playing MPD song", error);
        return false;
      }
    },

    async toggleMpdPlayback() {
      try {
        const apiStore = useApiStore();
        const response = await apiStore.makeRequest("/api/mpd/pause", {
          method: "POST",
          body: JSON.stringify({ pause: this.isPlaying }),
        });

        if (response.ok) {
          const result = await response.json();
          return result.success;
        }
        return false;
      } catch (error) {
        this.handleError("toggling MPD playback", error);
        return false;
      }
    },

    async stopMpdPlayback() {
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
        this.handleError("stopping MPD playback", error);
        return false;
      }
    },

    async setMpdVolume(volume) {
      try {
        const apiStore = useApiStore();
        const response = await apiStore.makeRequest("/api/mpd/volume", {
          method: "POST",
          body: JSON.stringify({ volume }),
        });

        if (response.ok) {
          const result = await response.json();
          return result.success;
        }
        return false;
      } catch (error) {
        this.handleError("setting MPD volume", error);
        return false;
      }
    },

    async seekMpdPosition(position) {
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
        this.handleError("seeking MPD position", error);
        return false;
      }
    },

    async navigateMpdPlayback(direction) {
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
        this.handleError("navigating MPD playback", error);
        return false;
      }
    },

    async getMpdStatus() {
      try {
        const apiStore = useApiStore();
        const response = await apiStore.makeRequest("/api/mpd/status");
        if (response.ok) {
          return await response.json();
        }
        return null;
      } catch (error) {
        this.handleError("getting MPD status", error);
        return null;
      }
    },

    async updateMpdProgress() {
      const status = await this.getMpdStatus();
      if (status && status.success && status.status) {
        this.currentTime = parseFloat(status.status.elapsed) || 0;
        this.duration = parseFloat(status.status.duration) || 0;
        this.isPlaying = status.status.state === "play";

        // Synchronize volume from MPD (convert from 0-100 to 0-1)
        if (status.status.volume !== undefined) {
          const mpdVolume = parseFloat(status.status.volume) / 100;
          if (Math.abs(this.volume - mpdVolume) > 0.01) {
            // Only update if significantly different
            this.volume = mpdVolume;
          }
        }

        if (this.isTransitioningSong && this.isPlaying) {
          this.isTransitioningSong = false; // Übergang beendet, da MPD spielt
        }
        if (this.isTransitioningSong) return; // Nicht loggen während eines manuellen Übergangs

        // Log played song if > 60% and not already logged (for MPD mode)
        if (
          this.currentSong &&
          !this.currentSongLogged &&
          this.duration > 0 &&
          this.currentTime / this.duration >= 0.6
        ) {
          // Check if we're in a public/shared playlist context - don't log plays there
          const currentPath = window.location.pathname;
          const isPublicContext =
            currentPath.includes("/shared/") ||
            currentPath.includes("/public/") ||
            currentPath.startsWith("/shared") ||
            currentPath.startsWith("/public");

          if (!isPublicContext) {
            const progressPercent = (this.currentTime / this.duration) * 100;
            const apiStore = useApiStore();
            apiStore.logPlayedSong(
              this.currentSong.song_id || this.currentSong.id,
            );
          }
          this.currentSongLogged = true;
        }
      }
    },

    updateCurrentFromMpd(status) {
      if (status && status.success && status.currentSong) {
        const mpdSongInfo = status.currentSong; // Enthält z.B. .file und .Id
        let matchedIndex = -1;

        // Versuche, den Song in der lokalen Queue über ID oder Dateipfad zu finden
        if (mpdSongInfo.Id) {
          matchedIndex = this.actualPlayQueue.findIndex(
            (s) => (s.song_id || s.id) === mpdSongInfo.Id,
          );
        }
        if (matchedIndex === -1 && mpdSongInfo.file) {
          matchedIndex = this.actualPlayQueue.findIndex(
            (s) => s.file_path === mpdSongInfo.file,
          );
        }

        if (matchedIndex !== -1) {
          const newCurrentSongCandidate = this.actualPlayQueue[matchedIndex];
          const newActualSongId =
            newCurrentSongCandidate.song_id || newCurrentSongCandidate.id;
          const oldActualSongId = this.currentSong
            ? this.currentSong.song_id || this.currentSong.id
            : null;

          // Wenn sich der Song tatsächlich geändert hat oder vorher kein Song aktiv war
          if (oldActualSongId !== newActualSongId) {
            this.currentSongLogged = false; // Wichtig: Log-Status für den neuen Song zurücksetzen
          }

          this.currentSongIndexInActualPlayQueue = matchedIndex;
          this.currentSong = newCurrentSongCandidate;
        } else {
          // Fall: MPD spielt einen Song, der nicht (oder nicht mehr) in der lokalen Queue ist.
          // Hier könnte man optional this.currentSong zurücksetzen oder eine Warnung ausgeben.
          // Fürs Erste belassen wir es dabei, dass currentSong nicht aktualisiert wird,
          // wenn keine Übereinstimmung gefunden wird.
          console.warn(
            "MPD is playing a song not found in the local queue:",
            mpdSongInfo,
          );
        }
      }
    },

    // MediaSession for mobile devices
    updateMediaSession(song) {
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
          this.togglePlayPause(),
        );
        navigator.mediaSession.setActionHandler("pause", () =>
          this.togglePlayPause(),
        );
        navigator.mediaSession.setActionHandler("previoustrack", () =>
          this.prevSong(),
        );
        navigator.mediaSession.setActionHandler("nexttrack", () =>
          this.nextSong(),
        );

        try {
          navigator.mediaSession.setActionHandler("seekto", (details) => {
            if (details.seekTime !== undefined) {
              this.seek(details.seekTime);
            }
          });
        } catch (error) {
          console.warn("Seekto is not supported.");
        }
      } catch (error) {
        this.handleError("setting up MediaSession", error);
      }
    },

    // Event listeners
    setupEventListeners() {
      // Listen for keyboard shortcuts
      document.addEventListener("keydown", (event) => {
        if (
          event.target.tagName === "INPUT" ||
          event.target.tagName === "TEXTAREA"
        )
          return;

        switch (event.code) {
          case "Space":
            event.preventDefault();
            this.togglePlayPause();
            break;
          case "ArrowLeft":
            if (event.ctrlKey) {
              event.preventDefault();
              this.prevSong();
            }
            break;
          case "ArrowRight":
            if (event.ctrlKey) {
              event.preventDefault();
              this.nextSong();
            }
            break;
        }
      });
    },

    // State persistence
    savePlayerState() {
      try {
        const state = {
          volume: this.volume,
          isShuffleEnabled: this.isShuffleEnabled,
          repeatMode: this.repeatMode,
          isLocalMode: this.isLocalMode,
          equalizerEnabled: this.equalizerEnabled,
          equalizerGains: this.equalizerGains,
        };
        localStorage.setItem("playerState", JSON.stringify(state));
      } catch (error) {
        console.warn("Failed to save player state:", error);
      }
    },

    loadPlayerState() {
      try {
        const saved = localStorage.getItem("playerState");
        if (saved) {
          const state = JSON.parse(saved);
          this.volume = state.volume !== undefined ? state.volume : 0.5;
          this.isShuffleEnabled = state.isShuffleEnabled || false;
          this.repeatMode = state.repeatMode || "none";
          this.isLocalMode = state.isLocalMode || false;
          this.equalizerEnabled =
            state.equalizerEnabled !== undefined
              ? state.equalizerEnabled
              : true;
          if (state.equalizerGains) {
            this.equalizerGains = {
              ...this.equalizerGains,
              ...state.equalizerGains,
            };
          }
        } else {
          // Set default volume to 50%
          this.volume = 0.5;
        }

        // Ensure lastVolume is set
        if (this.lastVolume === 0 || this.lastVolume === 1) {
          this.lastVolume = 0.5;
        }
      } catch (error) {
        console.warn("Failed to load player state:", error);
        // Set default values
        this.volume = 0.5;
        this.lastVolume = 0.5;
      }
    },

    // Neue Queue-Reordering Methoden
    moveToNext(queueIndex) {
      // queueIndex bezieht sich auf die upcomingQueue (ohne currentSong)
      // Konvertiere zu actualPlayQueue Index
      const actualQueueIndex =
        this.currentSongIndexInActualPlayQueue + 1 + queueIndex;

      if (
        actualQueueIndex < 0 ||
        actualQueueIndex >= this.actualPlayQueue.length
      ) {
        return;
      }

      const currentSongInActualQueue = this.currentSongIndexInActualPlayQueue;
      if (currentSongInActualQueue === -1) {
        return;
      }

      // Song nach currentSongInActualPlayQueue + 1 verschieben
      const song = this.actualPlayQueue[actualQueueIndex];
      const targetIndex = currentSongInActualQueue + 1;

      // Song aus der aktuellen Position entfernen
      this.actualPlayQueue.splice(actualQueueIndex, 1);

      // Anpassung der Indices nach dem Entfernen
      let adjustedTargetIndex = targetIndex;
      if (actualQueueIndex < targetIndex) {
        adjustedTargetIndex = targetIndex - 1;
      }

      // Song an neuer Position einfügen
      this.actualPlayQueue.splice(adjustedTargetIndex, 0, song);

      // CurrentSongIndex anpassen falls nötig
      if (actualQueueIndex < currentSongInActualQueue) {
        this.currentSongIndexInActualPlayQueue = currentSongInActualQueue - 1;
      } else if (actualQueueIndex === currentSongInActualQueue) {
        this.currentSongIndexInActualPlayQueue = adjustedTargetIndex;
      }

      // Auch originalOrderQueue synchronisieren wenn Shuffle aus ist
      if (!this.isShuffleEnabled) {
        this.originalOrderQueue = [...this.actualPlayQueue];
      }
    },

    reorderQueue(newOrder) {
      if (newOrder.length !== this.actualPlayQueue.length) {
        return;
      }

      // Finde aktuellen Song in der neuen Reihenfolge
      const currentSong = this.currentSong;
      let newCurrentIndex = -1;

      if (currentSong) {
        newCurrentIndex = newOrder.findIndex(
          (song) =>
            (song.song_id || song.id) ===
            (currentSong.song_id || currentSong.id),
        );
      }

      // Aktualisiere die actualPlayQueue
      this.actualPlayQueue = [...newOrder];

      // Aktualisiere currentSongIndex
      if (newCurrentIndex !== -1) {
        this.currentSongIndexInActualPlayQueue = newCurrentIndex;
      }

      // Auch originalOrderQueue synchronisieren wenn Shuffle aus ist
      if (!this.isShuffleEnabled) {
        this.originalOrderQueue = [...this.actualPlayQueue];
      }
    },

    handleUserLogout() {
      try {
        // Stop playback and clear all player state when user logs out
        this.stop();
        this.clearQueue();
        this.resetPlayer();
        this.clearAllIntervals();

        // Clear media session
        if (typeof window !== "undefined" && "mediaSession" in navigator) {
          navigator.mediaSession.metadata = null;
        }
      } catch (error) {
        console.error("Error during player cleanup on logout:", error);
      }
    },

    destroy() {
      // Clean up event listeners when store is destroyed
      if (typeof window !== "undefined" && this.logoutHandler) {
        window.removeEventListener("user-logged-out", this.logoutHandler);
        this.logoutHandler = null;
      }
      this.clearAllIntervals();
      this.stop();
    },
  },
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
