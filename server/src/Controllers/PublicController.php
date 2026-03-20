<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;
use InvalidArgumentException;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Repository\PublicPlaylistRepository;
use App\Services\PublicShareService;
use App\Database\Connection;

final class PublicController
{
    private PublicPlaylistRepository $publicRepo;
    private PublicShareService $shareService;

    public function __construct()
    {
        $this->publicRepo = new PublicPlaylistRepository();
        $this->shareService = new PublicShareService();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createJsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON response');
        }
        $response->getBody()->write($json);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Stream a song from a public playlist
     * @param array<string, mixed> $args
     */
    public function streamSong(Request $request, Response $response, array $args): Response
    {
        $uuid = $args['uuid'];
        $songId = $args['songId'];

        try {
            $db = Connection::getPDO();

            // Validate public access
            if (!$this->publicRepo->validatePublicAccess($uuid)) {
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Get song data
            $stmt = $db->prepare("
                SELECT s.file_path, s.title, s.artist, s.filetype as format, s.size as file_size
                FROM playlist_public_links ppl
                JOIN playlists p ON ppl.playlist_id = p.playlist_id
                JOIN playlist_entries pe ON pe.playlist_id = p.playlist_id
                JOIN songs s ON pe.song_id = s.song_id
                WHERE ppl.link_uuid = :uuid
                AND s.song_id = :song_id
                AND ppl.is_active = 1
                AND (ppl.expires_at IS NULL OR ppl.expires_at > NOW())
            ");

            $stmt->execute([
                ':uuid' => $uuid,
                ':song_id' => $songId
            ]);

            $song = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$song) {
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $filePath = $song['file_path'];

            if (!file_exists($filePath)) {
                error_log("Public stream: File not found: $filePath");
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Increment play count
            $this->publicRepo->incrementPlayCount($uuid);

            // Set appropriate headers for streaming
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo === false) {
                throw new Exception("Failed to initialize finfo");
            }
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            $response = $response
                ->withHeader('Content-Type', $mimeType ?: 'audio/mpeg')
                ->withHeader('Content-Length', (string)filesize($filePath))
                ->withHeader('Accept-Ranges', 'bytes')
                ->withHeader('Cache-Control', 'public, max-age=3600')
                ->withHeader('Content-Disposition', 'inline; filename="' . basename($filePath) . '"');

            // Handle range requests for seeking
            $range = $request->getHeaderLine('Range');
            if ($range !== '' && $range !== '0') {
                return $this->handleRangeRequest($response, $filePath, $range);
            }

            // Stream the entire file
            $stream = fopen($filePath, 'rb');
            if ($stream) {
                $response->getBody()->write(stream_get_contents($stream));
                fclose($stream);
            }

            return $response;
        } catch (Exception $e) {
            error_log("Error streaming public song $uuid/$songId: " . $e->getMessage());
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Download a song from a public playlist
     *
     * @param array<string, mixed> $args
     */
    public function downloadSong(Request $request, Response $response, array $args): Response
    {
        $uuid = $args['uuid'];
        $songId = $args['songId'];

        try {
            $db = Connection::getPDO();

            // Validate public access and download permission
            if (!$this->publicRepo->validatePublicAccess($uuid)) {
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            if (!$this->publicRepo->isDownloadEnabled($uuid)) {
                return $this->createJsonResponse($response, [
                    'error' => 'Downloads are not enabled for this playlist.',
                    'code' => 'DOWNLOAD_DISABLED'
                ], 403);
            }

            // Get song data
            $stmt = $db->prepare("
                SELECT s.file_path, s.title, s.artist, a.album_name as album, s.filetype as format, s.size as file_size
                FROM playlist_public_links ppl
                JOIN playlists p ON ppl.playlist_id = p.playlist_id
                JOIN playlist_entries pe ON pe.playlist_id = p.playlist_id
                JOIN songs s ON pe.song_id = s.song_id
                LEFT JOIN albums a ON s.album_id = a.album_id
                WHERE ppl.link_uuid = :uuid
                AND s.song_id = :song_id
                AND ppl.is_active = 1
                AND (ppl.expires_at IS NULL OR ppl.expires_at > NOW())
            ");

            $stmt->execute([
                ':uuid' => $uuid,
                ':song_id' => $songId
            ]);

            $song = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$song) {
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $filePath = $song['file_path'];

            if (!file_exists($filePath)) {
                error_log("Public download: File not found: $filePath");
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Create a clean filename for download
            $filename = $this->sanitizeFilename($song['artist'] . ' - ' . $song['title']) . '.' . strtolower($song['format']);

            $response = $response
                ->withHeader('Content-Type', 'application/octet-stream')
                ->withHeader('Content-Length', (string)filesize($filePath))
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Cache-Control', 'no-cache, must-revalidate')
                ->withHeader('Pragma', 'no-cache');

            // Stream the file for download
            $stream = fopen($filePath, 'rb');
            if ($stream) {
                $response->getBody()->write(stream_get_contents($stream));
                fclose($stream);
            }

            return $response;
        } catch (Exception $e) {
            error_log("Error downloading public song $uuid/$songId: " . $e->getMessage());
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Access public share by UUID
     *
     * @param array<string, mixed> $args
     */
    public function getShareByUuid(Request $request, Response $response, array $args): Response
    {
        $uuid = $args['uuid'];

        try {
            $queryParams = $request->getQueryParams();
            $password = $queryParams['password'] ?? null;

            $content = $this->shareService->getShareContent($uuid, $password);

            return $this->createJsonResponse($response, [
                'success' => true,
                'data' => $content
            ]);
        } catch (InvalidArgumentException $e) {
            $statusCode = match ($e->getMessage()) {
                'Share not found' => 404,
                'Share has expired' => 410,
                'Password required' => 401,
                'Invalid password' => 403,
                default => 400
            };

            return $this->createJsonResponse($response, [
                'error' => $e->getMessage(),
                'code' => strtoupper(str_replace(' ', '_', $e->getMessage()))
            ], $statusCode);
        } catch (Exception $e) {
            error_log("Error accessing public share $uuid: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Handle HTTP Range requests for audio streaming
     */
    private function handleRangeRequest(Response $response, string $filePath, string $rangeHeader): Response
    {
        $fileSize = filesize($filePath);

        // Parse range header (e.g., "bytes=0-1023")
        if (preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
            $start = (int)$matches[1];
            $end = empty($matches[2]) ? $fileSize - 1 : (int)$matches[2];

            // Validate range
            if ($start >= $fileSize || $end >= $fileSize || $start > $end) {
                return $response->withStatus(416); // Range Not Satisfiable
            }

            $length = $end - $start + 1;

            $response = $response
                ->withStatus(206) // Partial Content
                ->withHeader('Content-Range', "bytes $start-$end/$fileSize")
                ->withHeader('Content-Length', (string)$length);

            // Stream the requested range
            $stream = fopen($filePath, 'rb');
            if ($stream) {
                fseek($stream, $start);
                if ($length > 0) {
                    $data = fread($stream, $length);
                    if ($data !== false) {
                        $response->getBody()->write($data);
                    }
                }
                fclose($stream);
            }

            return $response;
        }

        return $response->withStatus(400); // Bad Request
    }

    /**
     * Sanitize filename for safe downloads
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
