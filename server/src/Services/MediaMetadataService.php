<?php

namespace App\Services;

use App\Models\MediaMetadata;
use Exception;
use InvalidArgumentException;

/**
 * Service for extracting and managing media metadata
 */
class MediaMetadataService
{
    private const BROWSER_SUPPORTED_FORMATS = [
        'mp3' => 'audio/mpeg',
        'mpeg' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'wav' => 'audio/wav',
        'aac' => 'audio/aac',
        'm4a' => 'audio/mp4',
        'mp4' => 'audio/mp4',
        'webm' => 'audio/webm',
        'flac' => 'audio/flac'
    ];

    /**
     * Extract metadata from a media file
     */
    public function extractMetadata(string $filePath): MediaMetadata
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("File not found or not readable: {$filePath}");
        }

        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            throw new \RuntimeException("Could not get file size for $filePath");
        }
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = $this->getMimeType($extension);
        $duration = $this->extractDuration($filePath);

        return new MediaMetadata(
            filePath: $filePath,
            mimeType: $mimeType,
            fileSize: $fileSize,
            duration: $duration,
            extension: $extension
        );
    }

    /**
     * Extract detailed metadata including audio properties
     */
    public function extractDetailedMetadata(string $filePath): MediaMetadata
    {
        $basicMetadata = $this->extractMetadata($filePath);

        // Extract additional metadata using ffprobe
        $audioInfo = $this->extractAudioInfo($filePath);

        return new MediaMetadata(
            filePath: $basicMetadata->getFilePath(),
            mimeType: $basicMetadata->getMimeType(),
            fileSize: $basicMetadata->getFileSize(),
            duration: $basicMetadata->getDuration(),
            extension: $basicMetadata->getExtension(),
            title: $audioInfo['title'] ?? null,
            artist: $audioInfo['artist'] ?? null,
            album: $audioInfo['album'] ?? null,
            bitrate: $audioInfo['bitrate'] ?? null,
            codec: $audioInfo['codec'] ?? null,
            sampleRate: $audioInfo['sample_rate'] ?? null,
            channels: $audioInfo['channels'] ?? null
        );
    }

    /**
     * Get MIME type for a file extension
     */
    private function getMimeType(string $extension): string
    {
        return self::BROWSER_SUPPORTED_FORMATS[$extension] ?? 'application/octet-stream';
    }

    /**
     * Extract duration from media file using ffprobe
     */
    private function extractDuration(string $filePath): float
    {
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($filePath);
        $output = shell_exec($cmd);

        if ($output === null || $output === false) {
            return 0.0;
        }

        $output = trim($output);

        if ($output === '' || $output === '0' || !is_numeric($output)) {
            return 0.0;
        }

        return round(floatval($output), 2);
    }

    /**
     * Extract detailed audio information using ffprobe
     */
    /** @return array<string, mixed> */
    private function extractAudioInfo(string $filePath): array
    {
        $cmd = "ffprobe -v error -select_streams a:0"
            . " -show_entries stream=codec_name,bit_rate,sample_rate,channels"
            . " -show_entries format=tags -of json "
            . escapeshellarg($filePath);
        $output = shell_exec($cmd);

        if ($output === '' || $output === '0' || $output === false || $output === null) {
            return [];
        }

        $data = json_decode($output, true);
        if (!$data) {
            return [];
        }

        $audioInfo = [];

        // Extract stream information
        if (isset($data['streams'][0])) {
            $stream = $data['streams'][0];
            $audioInfo['codec'] = $stream['codec_name'] ?? null;
            $audioInfo['bitrate'] = isset($stream['bit_rate']) ? (int)$stream['bit_rate'] : null;
            $audioInfo['sample_rate'] = isset($stream['sample_rate']) ? (int)$stream['sample_rate'] : null;
            $audioInfo['channels'] = isset($stream['channels']) ? (int)$stream['channels'] : null;
        }

        // Extract tags
        if (isset($data['format']['tags'])) {
            $tags = $data['format']['tags'];

            // Try different tag formats
            $audioInfo['title'] = $tags['title'] ?? $tags['TITLE'] ?? null;
            $audioInfo['artist'] = $tags['artist'] ?? $tags['ARTIST'] ?? null;
            $audioInfo['album'] = $tags['album'] ?? $tags['ALBUM'] ?? null;
        }

        return $audioInfo;
    }

    /**
     * Check if a file needs transcoding based on browser support
     */
    public function needsTranscoding(string $filePath, bool $forceTranscode = false): bool
    {
        $metadata = $this->extractMetadata($filePath);

        // Force transcoding if requested
        if ($forceTranscode) {
            return true;
        }

        // Check if format is problematic
        if ($metadata->isProblematic()) {
            return true;
        }

        // Check if format is browser supported
        return !$metadata->isBrowserSupported();
    }

    /**
     * Get optimal transcoding format for a file
     */
    public function getOptimalTranscodingFormat(string $filePath): string
    {
        $metadata = $this->extractMetadata($filePath);

        // If already in a good format, prefer AAC for quality
        if ($metadata->isBrowserSupported() && !$metadata->isProblematic()) {
            return 'aac';
        }

        // For problematic formats, transcode to AAC
        return 'aac';
    }

    /**
     * Validate media file
     */
    /** @return array<string> */
    public function validateMediaFile(string $filePath): array
    {
        $errors = [];

        if (!file_exists($filePath)) {
            $errors[] = 'File does not exist';
            return $errors;
        }

        if (!is_readable($filePath)) {
            $errors[] = 'File is not readable';
            return $errors;
        }

        try {
            $metadata = $this->extractMetadata($filePath);

            if ($metadata->getFileSize() === 0) {
                $errors[] = 'File is empty';
            }

            if ($metadata->getDuration() === 0.0) {
                $errors[] = 'Could not determine file duration';
            }
        } catch (Exception $e) {
            $errors[] = 'Error reading file metadata: ' . $e->getMessage();
        }

        return $errors;
    }
}
