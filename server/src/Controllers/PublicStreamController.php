<?php

namespace App\Controllers;

use App\Services\MediaStreamer;
use App\Services\PublicShareService;
use Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;
use Slim\Psr7\Stream;
use ZipArchive;

/**
 * Public Stream Controller for handling streaming and downloads in public shares
 * Uses the same MediaStreamer service as the main application for transcoding support
 */
class PublicStreamController
{
    private MediaStreamer $streamer;
    private PublicShareService $shareService;

    public function __construct()
    {
        // Initialize with streaming configuration
        $config = [
            'chunk_size' => 8192,
            'timeout' => 0,
            'max_execution_time' => 0
        ];

        $this->streamer = new MediaStreamer($config);
        $this->shareService = new PublicShareService();
    }

    /**
     * Stream a song from a public share
     */
    public function streamSong(Request $request, Response $response, string $shareUuid, string $songId): Response
    {
        try {
            // Optimize server for streaming
            ini_set('output_buffering', 'off');
            ini_set('zlib.output_compression', 'off');
            ob_implicit_flush(true);
            while (ob_get_level()) {
                ob_end_flush();
            }

            set_time_limit(0);
            ignore_user_abort(true);

            // Validate public share access
            $shareData = $this->validateShareAccess($shareUuid, $songId);
            if (!$shareData['success']) {
                return $this->createErrorResponse($response, $shareData['error'], $shareData['code']);
            }

            // Use MediaStreamer with public share options
            $options = [
                'public_share' => true,
                'share_uuid' => $shareUuid,
                // Default transcoding options for public shares
                'transcoding_enabled' => '1',
                'transcoding_quality' => '192',
                'transcoding_format' => 'aac',
                'transcoding_mode' => 'cbr'
            ];

            // Stream the song using the normal MediaStreamer
            $result = $this->streamer->streamSong($songId, $options);

            if (!$result['success']) {
                return $this->createErrorResponse($response, $result['error'], $result['code']);
            }

            // Check if this is a transcoded stream request
            if ($result['transcode'] ?? false) {
                // Use progressive streaming transcoding
                return $this->streamer->streamTranscodedAudio(
                    $response,
                    $result['original_file_path'],
                    $result['transcode_format'] ?? 'aac',
                    $result['transcode_params'] ?? ['bitrate' => 192, 'mode' => 'cbr'],
                    $result['duration'] ?? null
                );
            }

            // Try server acceleration if available
            $acceleration = $this->streamer->tryServerAcceleration(
                $result['file_path'],
                $result['mime_type'],
                $result['size'],
                $result['duration'] ?? null
            );

            if ($acceleration && $acceleration['success']) {
                // Apply headers for server acceleration
                foreach ($acceleration['headers'] as $name => $value) {
                    $response = $response->withHeader($name, $value);
                }
                return $response;
            }

            // Set response headers
            foreach ($result['headers'] as $name => $value) {
                $response = $response->withHeader($name, $value);
            }

            // Add headers to support media streaming
            $response = $response->withHeader('Accept-Ranges', 'bytes');
            $response = $response->withHeader('Cache-Control', 'public, max-age=3600');

            // Add duration header for ALL streams
            if (isset($result['duration']) && $result['duration'] > 0) {
                $response = $response->withHeader('X-Content-Duration', (string)$result['duration']);
                $response = $response->withHeader('X-Media-Duration', (string)$result['duration']);
            }

            // Handle range requests
            $range = $request->getHeaderLine('Range');
            if ($range !== '' && $range !== '0') {
                $rangeResponse = $this->streamer->handleRangeRequest($request, $response, $result['file_path'], $result['mime_type']);

                // Add duration headers to range response if available
                if (isset($result['duration']) && $result['duration'] > 0) {
                    $rangeResponse = $rangeResponse->withHeader('X-Content-Duration', (string)$result['duration']);
                    $rangeResponse = $rangeResponse->withHeader('X-Media-Duration', (string)$result['duration']);
                }

                return $rangeResponse;
            }

            // For media files, use chunked streaming for immediate playback
            $fileSize = $result['size'];

            // Open file handle and create stream
            $fileHandle = fopen($result['file_path'], 'rb');
            if (!$fileHandle) {
                return $this->createErrorResponse($response, "Could not open file: {$result['file_path']}", 500);
            }

            // Use chunked streaming for immediate playback
            return $this->streamer->streamFileInChunks($response, $fileHandle, $fileSize);
        } catch (Exception $e) {
            error_log("Public streaming error for share $shareUuid, song $songId: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error', 500);
        }
    }

    /**
     * Download a song from a public share
     */
    public function downloadSong(Request $request, Response $response, string $shareUuid, string $songId): Response
    {
        try {
            // Validate public share access and download permission
            $shareData = $this->validateShareAccess($shareUuid, $songId, true);
            if (!$shareData['success']) {
                return $this->createErrorResponse($response, $shareData['error'], $shareData['code']);
            }

            $song = $shareData['song'];
            $filePath = $song['file_path'];

            if (!file_exists($filePath)) {
                error_log("Public download: File not found: $filePath");
                return $this->createErrorResponse($response, 'File not found', 404);
            }

            // Create a clean filename for download
            $filename = $this->sanitizeFilename($song['artist'] . ' - ' . $song['title']) . '.' . strtolower($song['format']);
            $fileSize = filesize($filePath);

            // Set appropriate headers for download
            $response = $response
                ->withHeader('Content-Type', 'application/octet-stream')
                ->withHeader('Content-Length', (string)$fileSize)
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', '0')
                ->withHeader('Accept-Ranges', 'bytes');

            // Read and output file directly for better compatibility
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                return $this->createErrorResponse($response, 'Could not read file for download', 500);
            }

            $response->getBody()->write($fileContent);
            return $response;
        } catch (Exception $e) {
            error_log("Public download error for share $shareUuid, song $songId: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error', 500);
        }
    }

    /**
     * Download all songs from a public share as ZIP file
     */
    public function downloadAllSongs(Request $request, Response $response, string $shareUuid): Response
    {
        try {
            // Validate public share access and download permission
            $shareService = $this->shareService;
            $shareContent = $shareService->getShareContent($shareUuid);

            $share = $shareContent['share'];

            // Check if download is enabled
            $downloadEnabled = $share['download_enabled'] ?? false;

            if (!$downloadEnabled) {
                return $this->createErrorResponse($response, 'Downloads are not enabled for this share', 403);
            }

            $songs = $shareContent['content']['songs'] ?? [];
            if (empty($songs)) {
                return $this->createErrorResponse($response, 'No songs found in this share', 404);
            }

            // Create ZIP filename based on share name or type
            $shareName = $share['name'] ?? null;
            $shareType = $shareContent['content']['type'] ?? 'share';

            $zipFilename = $this->sanitizeFilename($shareName ?: ($shareType . '_share')) . '.zip';

            // Create ZIP file directly
            $zipContent = $this->createZipContent($songs);

            if ($zipContent === '' || $zipContent === '0') {
                return $this->createErrorResponse($response, 'Failed to create ZIP file', 500);
            }

            // Set headers for ZIP download
            $response = $response
                ->withHeader('Content-Type', 'application/zip')
                ->withHeader('Content-Length', (string)strlen($zipContent))
                ->withHeader('Content-Disposition', 'attachment; filename="' . $zipFilename . '"')
                ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', '0');

            $response->getBody()->write($zipContent);
            return $response;
        } catch (Exception $e) {
            error_log("Public ZIP download error for share $shareUuid: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create ZIP content as string from song files
     * @param array<int, array<string, mixed>> $songs
     * @return string
     */
    private function createZipContent(array $songs): string
    {
        // Create a temporary file to build the ZIP
        $tempFile = tempnam(sys_get_temp_dir(), 'audinary_share_');

        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Cannot create ZIP file');
        }

        foreach ($songs as $song) {
            // Normalize file_path field (handle both 'file_path' and 'file')
            $filePath = $song['file_path'] ?? $song['file'] ?? '';

            if (!file_exists($filePath)) {
                error_log("ZIP: File not found, skipping: $filePath");
                continue;
            }

            // Normalize fields for filename creation
            $artist = $song['artist'] ?? $song['track_artist_name'] ?? 'Unknown Artist';
            $title = $song['title'] ?? 'Unknown Title';
            $format = $song['format'] ?? $song['filetype'] ?? 'mp3';

            // Create safe filename for ZIP entry
            $filename = $this->sanitizeFilename($artist . ' - ' . $title) . '.' . strtolower($format);

            // Add file to ZIP
            $zip->addFile($filePath, $filename);
        }

        $zip->close();

        // Read ZIP content
        $zipContent = file_get_contents($tempFile);

        // Clean up temp file
        unlink($tempFile);

        return $zipContent ?: '';
    }


    /**
     * Validate share access and get song data
     * @param string $shareUuid
     * @param string $songId
     * @param bool $requireDownload
     * @return array<string, mixed>
     */
    private function validateShareAccess(string $shareUuid, string $songId, bool $requireDownload = false): array
    {
        try {
            // Get share content to validate access
            $shareContent = $this->shareService->getShareContent($shareUuid);

            // Check if downloads are required and enabled
            if ($requireDownload && !$shareContent['share']['download_enabled']) {
                return ['success' => false, 'error' => 'Downloads not enabled for this share', 'code' => 403];
            }

            // Find the song in the share content
            $songs = $shareContent['content']['songs'] ?? [];
            $song = null;

            foreach ($songs as $sharesong) {
                // Handle both song_id formats (direct and nested in arrays)
                $currentSongId = $sharesong['song_id'] ?? null;

                if ($currentSongId == $songId) {
                    $song = $sharesong;
                    break;
                }
            }

            if (!$song) {
                return ['success' => false, 'error' => 'Song not found in share', 'code' => 404];
            }

            // Ensure required fields exist for downloads
            if ($requireDownload) {
                // Normalize file_path field
                if (!isset($song['file_path']) && isset($song['file'])) {
                    $song['file_path'] = $song['file'];
                }

                // Normalize format/filetype field
                if (!isset($song['format']) && isset($song['filetype'])) {
                    $song['format'] = $song['filetype'];
                }

                // Ensure artist field exists
                if (!isset($song['artist']) && isset($song['track_artist_name'])) {
                    $song['artist'] = $song['track_artist_name'];
                }
            }

            return ['success' => true, 'song' => $song, 'share' => $shareContent['share']];
        } catch (InvalidArgumentException $e) {
            $code = match ($e->getMessage()) {
                'Share not found' => 404,
                'Share has expired' => 410,
                'Password required' => 401,
                'Invalid password' => 403,
                default => 400
            };

            return ['success' => false, 'error' => $e->getMessage(), 'code' => $code];
        }
    }

    /**
     * Create error response
     * @param Response $response
     * @param string $message
     * @param int $code
     * @return Response
     */
    private function createErrorResponse(Response $response, string $message, int $code): Response
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open temp stream');
        }
        $errorStream = new Stream($handle);
        $json = json_encode(['error' => $message]);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON');
        }
        $errorStream->write($json);

        return $response->withStatus($code)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($errorStream);
    }

    /**
     * Sanitize filename for safe downloads
     * @param string $filename
     * @return string
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove or replace unsafe characters
        $filename = preg_replace('/[^a-zA-Z0-9\s\-_\.]/', '', $filename);
        $filename = preg_replace('/\s+/', ' ', $filename);
        $filename = trim($filename);

        // Limit length
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }

        return $filename !== '' && $filename !== '0' ? $filename : 'download';
    }
}
