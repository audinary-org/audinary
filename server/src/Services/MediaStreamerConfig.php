<?php

namespace App\Services;

/**
 * Static helper class for MediaStreamer configuration
 * Uses global loadConfig() function for consistent configuration
 */
class MediaStreamerConfig
{
    /**
     * Get MediaStreamer configuration from global config
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function getConfig(array $overrides = []): array
    {
        // Load global configuration
        require_once __DIR__ . '/../configHelper.php';
        $globalConfig = loadConfig();

        // Extract MediaStreamer-relevant configuration
        $mediaConfig = [
            'musicDir' => $globalConfig['musicDir'],
            'bufferSize' => $globalConfig['bufferSize'] ?? 8192,
            'transcodeCache' => $globalConfig['transcodeCache'],
            'nginxInternalPath' => $globalConfig['nginxInternalPath'] ?? '/protected_music/',
            'allowedExtensions' => $globalConfig['allowedExtensions'] ?? ['mp3', 'wav', 'flac', 'ogg'],
            'supportedMimes' => [
                'audio/wav', 'audio/mpeg', 'audio/mp3', 'audio/mp4',
                'audio/aac', 'audio/aacp', 'audio/ogg', 'audio/webm',
                'audio/flac'
            ],
            'qualityPresets' => [
                'low' => 64,
                'mid' => 128,
                'high' => 256,
                'very_high' => 320
            ]
        ];

        // Apply any overrides
        return array_merge($mediaConfig, $overrides);
    }

    /**
     * Get configuration with environment variable overrides
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function getConfigWithEnvironment(array $overrides = []): array
    {
        $config = self::getConfig($overrides);

        // Override with environment variables if available
        if ($envMusicDir = $_ENV['MUSIC_DIR'] ?? null) {
            $config['musicDir'] = $envMusicDir;
        }

        if ($envTranscodeCache = $_ENV['TRANSCODE_CACHE'] ?? null) {
            $config['transcodeCache'] = $envTranscodeCache;
        }

        return $config;
    }
}
