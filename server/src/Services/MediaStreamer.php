<?php

namespace App\Services;

use App\Interfaces\MediaStreamingServiceInterface;
use App\Repository\SongRepository;
use App\Services\AuthenticationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Refactored MediaStreamer - Now acts as a facade to the new architecture
 *
 * This class maintains the original MediaStreamer interface while delegating
 * work to specialized services following SOLID principles.
 */
class MediaStreamer implements MediaStreamingServiceInterface
{
    private MediaStreamingService $streamingService;
    private SongRepository $songRepository;
    private AuthenticationService $authService;
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->streamingService = new MediaStreamingService($config);
        $this->songRepository = new SongRepository();

        // Use AuthenticationService for user settings functionality
        $jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this-in-production';
        $this->authService = new AuthenticationService($jwtSecret);
    }

    /**
     * Get song details by ID (compatibility method)
     *
     * @return array<string, mixed>|null
     */
    public function getSongById(string $songId): ?array
    {
        $song = $this->songRepository->findById($songId);

        if (!$song instanceof \App\Models\Song) {
            return null;
        }

        return [
            'song_id' => $song->getSongId(),
            'title' => $song->getTitle(),
            'artist' => $song->getArtist(),
            'file_path' => $song->getFilePath(),
            'size' => $song->getSize(),
            'duration' => $song->getDuration(),
            'album_id' => $song->getAlbumId(),
            'album_name' => $song->getAlbumName()
        ];
    }

    /**
     * Stream a song
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function streamSong(string $songId, array $options = []): array
    {
        return $this->streamingService->streamSong($songId, $options);
    }

    /**
     * Handle range requests for streaming
     */
    public function handleRangeRequest(Request $request, Response $response, string $filePath, string $mimeType): Response
    {
        return $this->streamingService->handleRangeRequest($request, $response, $filePath, $mimeType);
    }

    /**
     * Load user settings (compatibility method)
     */
    /** @return array<string, mixed> */
    public function loadUserSettings(string $userId): array
    {
        return $this->authService->getTranscodingOptions($userId);
    }

    /**
     * Try server acceleration (compatibility method)
     */
    /** @return array<string, mixed>|null */
    public function tryServerAcceleration(string $filePath, string $mimeType, int $fileSize, ?float $duration = null): ?array
    {
        // Delegate to the main streaming service
        return $this->streamingService->tryServerAcceleration($filePath, $mimeType, $fileSize, $duration);
    }

    /**
     * Check if codec is available (compatibility method)
     */
    public function isCodecAvailable(string $codec): bool
    {
        $codecService = new CodecValidationService();
        return $codecService->isCodecAvailable($codec);
    }

    /**
     * Stream file in chunks for immediate playback
     * @param resource $fileHandle
     */
    public function streamFileInChunks(Response $response, $fileHandle, int $fileSize): Response
    {
        // Set chunked transfer encoding
        $response = $response->withHeader('Transfer-Encoding', 'chunked');
        $response = $response->withoutHeader('Content-Length');

        // Create custom streaming body
        $streamBody = new class ($fileHandle, $fileSize) implements StreamInterface {
            /** @var resource|closed-resource|null */
            private $fileHandle;
            private int $fileSize;
            private int $position = 0;
            private int $chunkSize = 8192; // 8KB chunks for immediate start

            /** @param resource $fileHandle */
            public function __construct($fileHandle, int $fileSize)
            {
                $this->fileHandle = $fileHandle;
                $this->fileSize = $fileSize;
            }

            public function __toString(): string
            {
                $content = '';
                $this->rewind();
                while (!$this->eof()) {
                    $content .= $this->read($this->chunkSize);
                }
                return $content;
            }

            public function close(): void
            {
                if (is_resource($this->fileHandle)) {
                    fclose($this->fileHandle);
                }
            }

            public function detach()
            {
                $handle = $this->fileHandle;
                $this->fileHandle = null;
                return $handle;
            }

            public function getSize(): int
            {
                return $this->fileSize;
            }

            public function tell(): int
            {
                return $this->position;
            }

            public function eof(): bool
            {
                return $this->position >= $this->fileSize || feof($this->fileHandle);
            }

            public function isSeekable(): bool
            {
                return true;
            }

            public function seek(int $offset, int $whence = SEEK_SET): void
            {
                if (fseek($this->fileHandle, $offset, $whence) === 0) {
                    $position = ftell($this->fileHandle);
                    $this->position = $position !== false ? $position : 0;
                }
            }

            public function rewind(): void
            {
                $this->seek(0);
            }

            public function isWritable(): bool
            {
                return false;
            }

            public function write(string $string): int
            {
                throw new RuntimeException('Stream is not writable');
            }

            public function isReadable(): bool
            {
                return true;
            }

            public function read(int $length): string
            {
                if ($this->eof()) {
                    return '';
                }

                $data = fread($this->fileHandle, max(1, $length));
                if ($data === false) {
                    return '';
                }

                $this->position += strlen($data);
                return $data;
            }

            public function getContents(): string
            {
                $contents = '';
                while (!$this->eof()) {
                    $contents .= $this->read($this->chunkSize);
                }
                return $contents;
            }

            public function getMetadata(?string $key = null)
            {
                $meta = stream_get_meta_data($this->fileHandle);
                return $key !== null && $key !== '' && $key !== '0' ? ($meta[$key] ?? null) : $meta;
            }
        };

        return $response->withBody($streamBody);
    }

    /**
     * Stream transcoded audio progressively (FFmpeg pipe to browser)
     * @param array<string, mixed> $transcodingParams
     */
    public function streamTranscodedAudio(Response $response, string $filePath, string $format, array $transcodingParams, ?float $duration = null): Response
    {
        // FFmpeg command for streaming transcoding
        $codec = $this->getCodecForFormat($format);
        $outputFormat = $this->getFFmpegFormat($format);

        // Build base command
        $command = [
            'ffmpeg',
            '-i', escapeshellarg($filePath),
            '-f', $outputFormat,
            '-c:a', $codec
        ];

        // Add quality/bitrate parameters based on format and mode
        if ($format === 'flac') {
            // FLAC - no additional parameters needed (lossless)
            $command[] = '-compression_level';
            $command[] = '5';
            // Balanced compression
        } elseif (isset($transcodingParams['vbr_quality'])) {
            // AAC - add bitrate or VBR parameters
            // VBR mode
            $command[] = '-vbr';
            $command[] = $transcodingParams['vbr_quality'];
        } else {
            // CBR mode (fallback)
            $command[] = '-b:a';
            $command[] = ($transcodingParams['bitrate'] ?? 192) . 'k';
        }

        // Add streaming optimizations
        $command = array_merge($command, [
            '-avoid_negative_ts', 'make_zero',
            '-fflags', '+genpts',
            '-movflags', '+faststart',
            '-' // Output to stdout for streaming
        ]);


        // Set chunked transfer headers
        $response = $response->withHeader('Transfer-Encoding', 'chunked');
        $response = $response->withHeader('Content-Type', $this->getMimeTypeForFormat($format));
        $response = $response->withoutHeader('Content-Length');
        $response = $response->withHeader('Cache-Control', 'no-cache'); // Don't cache transcoding

        // Add duration header for player if available
        if ($duration !== null) {
            $response = $response->withHeader('X-Content-Duration', (string)$duration);
            $response = $response->withHeader('X-Media-Duration', (string)$duration);
        }

        // Create streaming body that pipes FFmpeg output
        $streamBody = new class ($command) implements StreamInterface {
            /** @var array<int|string, string> */
            private array $command;
            /** @var resource|closed-resource|null */
            private $process;
            /** @var array<int, resource>|null */
            private $pipes;
            private string $buffer = '';
            private int $position = 0;
            private int $chunkSize = 8192; // 8KB chunks

            /** @param array<int|string, string> $command */
            public function __construct(array $command)
            {
                $this->command = $command;
                $this->startFFmpeg();
            }

            private function startFFmpeg(): void
            {
                $descriptorspec = [
                    0 => ['pipe', 'r'], // stdin
                    1 => ['pipe', 'w'], // stdout
                    2 => ['pipe', 'w']  // stderr
                ];

                $pipes = [];
                $process = proc_open(
                    implode(' ', $this->command),
                    $descriptorspec,
                    $pipes
                );

                if (!is_resource($process)) {
                    throw new RuntimeException('Failed to start FFmpeg process');
                }

                $this->process = $process;
                $this->pipes = $pipes;

                // Close stdin
                fclose($this->pipes[0]);

                // Set stdout to non-blocking
                stream_set_blocking($this->pipes[1], false);
            }

            public function __toString(): string
            {
                $content = '';
                while (!$this->eof()) {
                    $content .= $this->read($this->chunkSize);
                }
                return $content;
            }

            public function close(): void
            {
                if ($this->pipes !== null) {
                    foreach ($this->pipes as $pipe) {
                        if (is_resource($pipe)) {
                            fclose($pipe);
                        }
                    }
                }
                if (is_resource($this->process)) {
                    proc_close($this->process);
                }
            }

            public function detach()
            {
                $process = $this->process;
                $this->process = null;
                return $process;
            }

            public function getSize(): ?int
            {
                return null; // Unknown size for streaming
            }

            public function tell(): int
            {
                return $this->position;
            }

            public function eof(): bool
            {
                if (!is_resource($this->process)) {
                    return true;
                }

                $status = proc_get_status($this->process);
                return !$status['running'] && empty($this->buffer);
            }

            public function isSeekable(): bool
            {
                return false; // Streaming is not seekable
            }

            public function seek(int $offset, int $whence = SEEK_SET): void
            {
                throw new RuntimeException('Stream is not seekable');
            }

            public function rewind(): void
            {
                throw new RuntimeException('Stream is not seekable');
            }

            public function isWritable(): bool
            {
                return false;
            }

            public function write(string $string): int
            {
                throw new RuntimeException('Stream is not writable');
            }

            public function isReadable(): bool
            {
                return true;
            }

            public function read(int $length): string
            {
                if ($this->eof()) {
                    return '';
                }

                // Read from FFmpeg stdout
                $data = fread($this->pipes[1], max(1, $length));
                if ($data === false) {
                    $data = '';
                }

                $this->position += strlen($data);
                return $data;
            }

            public function getContents(): string
            {
                $contents = '';
                while (!$this->eof()) {
                    $contents .= $this->read($this->chunkSize);
                }
                return $contents;
            }

            public function getMetadata(?string $key = null)
            {
                return $key !== null && $key !== '' && $key !== '0' ? null : [];
            }
        };

        return $response->withBody($streamBody);
    }

    /**
     * Get FFmpeg codec for format
     */
    private function getCodecForFormat(string $format): string
    {
        return match ($format) {
            'mp3' => 'libmp3lame',
            'aac' => 'aac',
            'ogg' => 'libvorbis',
            'opus' => 'libopus',
            default => 'libmp3lame'
        };
    }

    /**
     * Get FFmpeg output format
     */
    private function getFFmpegFormat(string $format): string
    {
        return match ($format) {
            'mp3' => 'mp3',
            'aac' => 'adts', // AAC stream format
            'ogg' => 'ogg',
            'opus' => 'opus',
            default => 'mp3'
        };
    }

    /**
     * Get MIME type for format
     */
    private function getMimeTypeForFormat(string $format): string
    {
        return match ($format) {
            'mp3' => 'audio/mpeg',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/opus',
            default => 'audio/mpeg'
        };
    }

    /**
     * Get streaming statistics
     * @return array<string, mixed>
     */
    public function getStreamingStats(): array
    {
        return $this->streamingService->getStreamingStats();
    }

    /**
     * Clean up old transcoded files
     */
    public function cleanupCache(int $maxAge = 86400): int
    {
        return $this->streamingService->cleanupCache($maxAge);
    }

    /**
     * Get configuration
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Validate configuration
     * @return array<string>
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        // Validate required directories
        $requiredDirs = ['musicDir', 'transcodeCache'];
        foreach ($requiredDirs as $dir) {
            if (empty($this->config[$dir])) {
                $errors[] = "Configuration missing: {$dir}";
            } elseif (!is_dir($this->config[$dir])) {
                $errors[] = "Directory does not exist: {$this->config[$dir]}";
            }
        }

        // Validate Nginx acceleration settings
        if (empty($this->config['musicDir'])) {
            $errors[] = 'Music directory not configured';
        } elseif (!is_dir($this->config['musicDir'])) {
            $errors[] = 'Music directory does not exist: ' . $this->config['musicDir'];
        }

        if (empty($this->config['nginxInternalPath'])) {
            $errors[] = 'Nginx internal path not configured';
        }

        return $errors;
    }

    /**
     * Get service health status
     * @return array<string, mixed>
     */
    public function getHealthStatus(): array
    {
        $configErrors = $this->validateConfiguration();

        // Get basic streaming stats since getCacheStatistics doesn't exist
        $streamingStats = $this->streamingService->getStreamingStats();
        $cacheStats = [
            'total_size' => 0,
            'file_count' => 0,
            'enabled' => true
        ];

        return [
            'healthy' => $configErrors === [],
            'errors' => $configErrors,
            'cache_stats' => $cacheStats,
            'acceleration' => $streamingStats['acceleration'] ?? false,
            'config' => [
                'music_dir' => $this->config['musicDir'] ?? null,
                'cache_dir' => $this->config['transcodeCache'] ?? null,
                'buffer_size' => $this->config['bufferSize'] ?? 8192
            ]
        ];
    }
}
