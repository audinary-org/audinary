<?php

namespace App\Services;

/**
 * Service for validating and managing codec availability
 */
class CodecValidationService
{
    /** @var array<int, string>|null */
    private static ?array $availableCodecs = null;
    /** @var array<int, string>|null */
    private static ?array $availableFormats = null;

    /**
     * Check if a codec is available in the FFmpeg installation
     */
    public function isCodecAvailable(string $codec): bool
    {
        if (self::$availableCodecs === null) {
            $this->loadAvailableCodecs();
        }

        return in_array($codec, self::$availableCodecs);
    }

    /**
     * Check if a format is available in the FFmpeg installation
     */
    public function isFormatAvailable(string $format): bool
    {
        if (self::$availableFormats === null) {
            $this->loadAvailableFormats();
        }

        return in_array($format, self::$availableFormats);
    }

    /**
     * Get the best available codec for a format
     */
    public function getBestCodecForFormat(string $format): string
    {
        $codecPreferences = [
            'aac' => ['libfdk_aac', 'aac'],
            'mp3' => ['libmp3lame', 'mp3'],
            'ogg' => ['libvorbis', 'vorbis'],
            'wav' => ['pcm_s16le', 'pcm_s24le', 'pcm_s32le'],
            'flac' => ['flac']
        ];

        $candidates = $codecPreferences[$format] ?? [];

        foreach ($candidates as $codec) {
            if ($this->isCodecAvailable($codec)) {
                return $codec;
            }
        }

        // Fallback to format name if no specific codec found
        return $format;
    }

    /**
     * Get all available codecs
     */
    /** @return array<int, string> */
    public function getAvailableCodecs(): array
    {
        if (self::$availableCodecs === null) {
            $this->loadAvailableCodecs();
        }

        return self::$availableCodecs;
    }

    /**
     * Get all available formats
     */
    /** @return array<int, string> */
    public function getAvailableFormats(): array
    {
        if (self::$availableFormats === null) {
            $this->loadAvailableFormats();
        }

        return self::$availableFormats;
    }

    /**
     * Validate transcoding parameters
     */
    /** @return array<int, string> */
    public function validateTranscodingParams(string $format, string $codec, int $bitrate, string $bitrateMode): array
    {
        $errors = [];

        // Validate format
        $validFormats = ['aac', 'mp3', 'ogg', 'wav', 'flac'];
        if (!in_array($format, $validFormats)) {
            $errors[] = "Invalid format: {$format}";
        }

        // Validate codec availability
        if (!$this->isCodecAvailable($codec)) {
            $errors[] = "Codec not available: {$codec}";
        }

        // Validate bitrate
        if ($bitrate < 32 || $bitrate > 320) {
            $errors[] = "Bitrate must be between 32 and 320 kbps";
        }

        // Validate bitrate mode
        if (!in_array($bitrateMode, ['cbr', 'vbr'])) {
            $errors[] = "Invalid bitrate mode: {$bitrateMode}";
        }

        return $errors;
    }

    /**
     * Load available codecs from FFmpeg
     */
    private function loadAvailableCodecs(): void
    {
        self::$availableCodecs = [];

        $cmd = "ffmpeg -hide_banner -encoders 2>/dev/null";
        $output = shell_exec($cmd);

        if ($output) {
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (preg_match('/\s+([A-Z.]+)\s+(\w+)\s+(.+)/', $line, $matches)) {
                    self::$availableCodecs[] = trim($matches[2]);
                }
            }
        }

        // Add some common codecs as fallback
        $fallbackCodecs = ['aac', 'mp3', 'libmp3lame', 'libvorbis', 'pcm_s16le', 'flac'];
        self::$availableCodecs = array_unique(array_merge(self::$availableCodecs, $fallbackCodecs));
    }

    /**
     * Load available formats from FFmpeg
     */
    private function loadAvailableFormats(): void
    {
        self::$availableFormats = [];

        $cmd = "ffmpeg -hide_banner -formats 2>/dev/null";
        $output = shell_exec($cmd);

        if ($output) {
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (preg_match('/\s+([DE]+)\s+(\w+)\s+(.+)/', $line, $matches) !== 1) {
                    continue;
                }
                if (strpos($matches[1], 'E') === false) {
                    continue;
                }
                // Can encode
                self::$availableFormats[] = trim($matches[2]);
            }
        }

        // Add some common formats as fallback
        $fallbackFormats = ['aac', 'mp3', 'ogg', 'wav', 'flac', 'adts'];
        self::$availableFormats = array_unique(array_merge(self::$availableFormats, $fallbackFormats));
    }

    /**
     * Clear cached codec and format information
     */
    public function clearCache(): void
    {
        self::$availableCodecs = null;
        self::$availableFormats = null;
    }
}
