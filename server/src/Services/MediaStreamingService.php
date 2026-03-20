<?php

namespace App\Services;

use App\Models\MediaMetadata;
use App\Repository\SongRepository;
use App\Services\AuthenticationService;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * Main service for handling media streaming operations
 */
class MediaStreamingService
{
    private SongRepository $songRepository;
    private AuthenticationService $authService;
    private MediaMetadataService $metadataService;
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->songRepository = new SongRepository();

        // Use AuthenticationService for user settings functionality
        $jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this-in-production';
        $this->authService = new AuthenticationService($jwtSecret);

        $this->metadataService = new MediaMetadataService();
    }

    /**
     * Stream a song
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function streamSong(string $songId, array $options = []): array
    {
        // Get song from database
        $song = $this->songRepository->findById($songId);
        if (!$song instanceof \App\Models\Song) {
            return [
                'success' => false,
                'error' => 'Song not found',
                'code' => 404
            ];
        }

        $filePath = $song->getFilePath();

        // Validate file exists and is readable
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [
                'success' => false,
                'error' => 'File not found or not readable',
                'code' => 404
            ];
        }

        // Extract metadata
        try {
            $metadata = $this->metadataService->extractMetadata($filePath);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to read file metadata: ' . $e->getMessage(),
                'code' => 500
            ];
        }

        // Get user settings if user ID provided
        $userId = $options['user_id'] ?? null;
        $userOptions = [];

        if ($userId) {
            $userOptions = $this->authService->getTranscodingOptions($userId);
        }

        // Merge user options with request options
        $streamingOptions = array_merge($userOptions, $options);

        // Determine if transcoding is needed
        $needsTranscoding = $this->shouldTranscode($metadata, $streamingOptions);

        if ($needsTranscoding) {
            return $this->handleTranscodedStream($song, $metadata, $streamingOptions);
        }
        return $this->handleDirectStream($song, $metadata);
    }

    /**
     * Handle range requests for streaming
     */
    public function handleRangeRequest(Request $request, Response $response, string $filePath, string $mimeType): Response
    {
        $fileSize = filesize($filePath);
        $rangeHeader = $request->getHeaderLine('Range');

        if ($rangeHeader === '' || $rangeHeader === '0') {
            return $response->withHeader('Content-Type', $mimeType)
                           ->withHeader('Content-Length', (string)$fileSize)
                           ->withHeader('Accept-Ranges', 'bytes');
        }

        if (in_array(preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $matches), [0, false], true)) {
            return $response->withStatus(400);
        }

        $start = empty($matches[1]) ? 0 : (int)$matches[1];
        $end = empty($matches[2]) ? ($fileSize - 1) : (int)$matches[2];

        if ($start >= $fileSize || $end >= $fileSize || $start > $end) {
            return $response->withStatus(416)
                           ->withHeader('Content-Range', "bytes */{$fileSize}");
        }

        $length = $end - $start + 1;

        try {
            $file = fopen($filePath, 'rb');
            if (!$file) {
                throw new RuntimeException('Failed to open file');
            }

            fseek($file, $start);
            if ($length > 0) {
                $data = fread($file, $length);
            } else {
                $data = '';
            }
            fclose($file);

            if ($data === false) {
                throw new RuntimeException('Failed to read file data');
            }

            $response->getBody()->write($data);

            return $response->withStatus(206)
                           ->withHeader('Content-Type', $mimeType)
                           ->withHeader('Content-Length', (string)$length)
                           ->withHeader('Content-Range', "bytes {$start}-{$end}/{$fileSize}")
                           ->withHeader('Accept-Ranges', 'bytes');
        } catch (Exception $e) {
            return $response->withStatus(500);
        }
    }

    /**
     * Determine if transcoding is needed
     *
     * @param array<string, mixed> $options
     */
    private function shouldTranscode(MediaMetadata $metadata, array $options): bool
    {
        $fileFormat = $metadata->getExtension();
        $fileBitrate = $metadata->getBitrate();
        $isProblematic = $metadata->isProblematic();
        $isBrowserSupported = $metadata->isBrowserSupported();

        // Force transcoding if explicitly requested
        if ($options['forceTranscode'] ?? false) {
            return true;
        }

        // Check if format is problematic (always transcode these)
        if ($isProblematic) {
            return true;
        }

        // Check if format is not browser supported (always transcode these)
        if (!$isBrowserSupported) {
            return true;
        }

        // Check if user has disabled transcoding
        $transcodingDisabled = isset($options['enabled']) && $options['enabled'] === false;

        // Check if different format is requested
        $requestedFormat = $options['format'] ?? null;
        if ($requestedFormat && $requestedFormat !== $fileFormat) {
            // Special case: Don't transcode to FLAC unless source is unsupported
            if ($requestedFormat === 'flac') {
                // FLAC target only for unsupported source formats
                // At this point, we already know the source IS browser-supported (line 169-171)
                return false;
            }

            // Only transcode for format change if transcoding is not explicitly disabled
            if (!$transcodingDisabled) {
                return true;
            }
        }

        // Check if bitrate limit is requested and needed
        $maxBitrate = $options['maxBitRate'] ?? null;
        // Only transcode if file bitrate is higher than the max bitrate setting
        // and transcoding is not explicitly disabled
        return $maxBitrate && $maxBitrate > 0 && (!$transcodingDisabled && $fileBitrate && $fileBitrate > $maxBitrate);
    }

    /**
     * Handle direct streaming without transcoding
     *
     * @return array<string, mixed>
     */
    private function handleDirectStream(\App\Models\Song $song, MediaMetadata $metadata): array
    {
        $filePath = $metadata->getFilePath();

        // Always use Nginx X-Accel-Redirect acceleration
        $acceleration = $this->getNginxXAccelHeaders(
            $filePath,
            $metadata->getMimeType(),
            $metadata->getFileSize(),
            $metadata->getDuration()  // Add duration parameter
        );

        if ($acceleration !== null && $acceleration !== []) {
            return array_merge([
                'success' => true,
                'file_path' => $filePath,
                'mime_type' => $metadata->getMimeType(),
                'size' => $metadata->getFileSize(),
                'title' => $song->getTitle(),
                'artist' => $song->getArtist(),
                'album' => $song->getAlbumName(),
                'duration' => $metadata->getDuration(),
                'transcode' => false,
                'acceleration' => 'nginx_xaccel'
            ], $acceleration);
        }

        // Standard streaming headers
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => $metadata->getMimeType(),
            'Content-Length' => (string)$metadata->getFileSize(),
            'X-Content-Duration' => (string)$metadata->getDuration(),
            'X-Media-Duration' => (string)$metadata->getDuration(),
            'Cache-Control' => 'public, max-age=31536000'
        ];

        return [
            'success' => true,
            'file_path' => $filePath,
            'mime_type' => $metadata->getMimeType(),
            'size' => $metadata->getFileSize(),
            'title' => $song->getTitle(),
            'artist' => $song->getArtist(),
            'album' => $song->getAlbumName(),
            'duration' => $metadata->getDuration(),
            'transcode' => false,
            'headers' => $headers
        ];
    }

    /**
     * Handle transcoded streaming
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function handleTranscodedStream(\App\Models\Song $song, MediaMetadata $metadata, array $options): array
    {
        // Setup transcoding parameters for progressive streaming
        $format = $options['format'] ?? 'aac'; // Only AAC or FLAC now
        $mode = $options['mode'] ?? 'cbr';
        $quality = $options['quality'] ?? 'medium';

        // Convert quality level to bitrate/settings
        $transcodingParams = $this->getTranscodingParams($format, $mode, $quality);

        // For progressive streaming, we don't pre-transcode - just return stream parameters

        // Return stream configuration for progressive transcoding
        return [
            'success' => true,
            'original_file_path' => $metadata->getFilePath(), // Original file for FFmpeg
            'transcode' => true, // Flag for route to use progressive streaming
            'transcode_format' => $format,
            'transcode_params' => $transcodingParams, // Complete params including VBR
            'title' => $song->getTitle(),
            'artist' => $song->getArtist(),
            'album' => $song->getAlbumName(),
            'duration' => $metadata->getDuration(),
            'original_format' => $metadata->getExtension(),
            'mime_type' => $this->getTranscodedMimeType($format)
        ];
    }

    /**
     * Get transcoding parameters based on format, mode and quality
     *
     * @return array<string, mixed>
     */
    private function getTranscodingParams(string $format, string $mode, string $quality): array
    {
        if ($format === 'flac') {
            // FLAC is always lossless, mode/quality ignored
            return [
                'bitrate' => 0, // No bitrate for FLAC
                'mode' => 'lossless',
                'ffmpeg_params' => []
            ];
        }

        // AAC quality mapping
        $qualityMap = [
            'low' => 128,
            'medium' => 192,
            'high' => 256,
            'very_high' => 320
        ];

        $bitrate = $qualityMap[$quality] ?? 192;

        if ($mode === 'vbr') {
            // VBR quality mapping (q values for AAC)
            $vbrQualityMap = [
                'low' => '0.1',      // ~128kbps avg
                'medium' => '0.3',   // ~192kbps avg
                'high' => '0.5',     // ~256kbps avg
                'very_high' => '0.7' // ~320kbps avg
            ];

            return [
                'bitrate' => $bitrate, // For display/fallback
                'mode' => 'vbr',
                'vbr_quality' => $vbrQualityMap[$quality] ?? '0.3',
                'ffmpeg_params' => ['-vbr', $vbrQualityMap[$quality] ?? '0.3']
            ];
        }

        // CBR mode
        return [
            'bitrate' => $bitrate,
            'mode' => 'cbr',
            'ffmpeg_params' => ['-b:a', $bitrate . 'k']
        ];
    }

    /**
     * Get MIME type for transcoded format
     */
    private function getTranscodedMimeType(string $format): string
    {
        return match ($format) {
            'aac' => 'audio/aac',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            default => 'audio/aac'
        };
    }

    /**
     * Get Nginx X-Accel-Redirect headers for acceleration
     *
     * @return array<string, mixed>|null
     */
    private function getNginxXAccelHeaders(string $filePath, string $mimeType, int $fileSize, ?float $duration = null): ?array
    {
        $musicDir = $this->config['musicDir'] ?? '/var/www/html/var/music';
        $internalPath = $this->config['nginxInternalPath'] ?? '/protected_music';

        // Only allow files within the music directory
        if (strpos($filePath, (string) $musicDir) !== 0) {
            return null;
        }

        // Convert file path to internal path
        $relativePath = str_replace($musicDir, '', $filePath);
        $nginxPath = $internalPath . $relativePath;

        // Add duration as URL parameter for nginx to read
        if ($duration !== null && $duration > 0) {
            $nginxPath .= '?duration=' . urlencode((string)$duration);
        }

        $headers = [
            'X-Accel-Redirect' => $nginxPath,
            'Content-Type' => $mimeType,
            'Content-Length' => (string)$fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000',
            // Set duration headers in the PHP response - nginx should preserve them
            'X-Content-Duration' => (string)($duration ?? 0),
            'X-Media-Duration' => (string)($duration ?? 0)
        ];

        return [
            'success' => true,
            'headers' => $headers
        ];
    }

    /**
     * Try server acceleration (compatibility method for MediaStreamer)
     *
     * @return array<string, mixed>|null
     */
    public function tryServerAcceleration(string $filePath, string $mimeType, int $fileSize, ?float $duration = null): ?array
    {
        return $this->getNginxXAccelHeaders($filePath, $mimeType, $fileSize, $duration);
    }

    /**
     * Get streaming statistics
     *
     * @return array<string, mixed>
     */
    public function getStreamingStats(): array
    {
        return [
            'acceleration' => [
                'nginx_xaccel' => true,
                'php_streaming' => true
            ],
            'supported_formats' => ['aac', 'flac'],
            'supported_bitrate_modes' => ['cbr', 'vbr']
        ];
    }

    /**
     * Clean up old transcoded files
     */
    public function cleanupCache(int $maxAge = 86400): int
    {
        // Cache cleanup not needed for progressive streaming
        return 0;
    }
}
