<?php

namespace App\Services;

use App\Models\ImageRequest;
use App\Models\ImageResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Consolidated Image Service - Handles all image operations
 * Combines functionality from ImagePathService, ImageCacheService, ImageStreamingService
 */
class ImageService
{
    /** @var array<string, mixed> */
    private array $config;
    /** @var array<string> */
    private array $supportedExtensions = ['webp', 'jpg', 'jpeg', 'png'];
    private int $bufferSize = 8192;
    private int $cacheTTL = 604800; // 7 days

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->bufferSize = $config['bufferSize'] ?? 8192;
        $this->cacheTTL = $config['cacheTTL'] ?? 604800;
    }

    /**
     * Serve album cover image
     */
    public function serveAlbumCover(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        if (empty($params['albumId'])) {
            return $this->createErrorResponse($response, 'No albumId specified', 400);
        }

        $imageRequest = $this->createImageRequest('album', $params);
        return $this->serveImage($request, $response, $imageRequest);
    }

    /**
     * Serve artist image
     */
    public function serveArtistImage(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        if (empty($params['artistId'])) {
            return $this->createErrorResponse($response, 'No artistId specified', 400);
        }

        $imageRequest = $this->createImageRequest('artist', $params);
        return $this->serveImage($request, $response, $imageRequest);
    }

    /**
     * Serve profile image
     */
    public function serveProfileImage(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        if (empty($params['uuid'])) {
            return $this->createErrorResponse($response, 'No profile image UUID specified', 400);
        }

        $imageRequest = $this->createImageRequest('profile', $params);

        if (!$imageRequest->isValidUuid()) {
            return $this->createErrorResponse($response, 'Invalid profile image UUID format', 400);
        }

        return $this->serveImage($request, $response, $imageRequest);
    }

    /**
     * Serve playlist image
     */
    public function servePlaylistImage(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        if (empty($params['uuid'])) {
            return $this->createErrorResponse($response, 'No playlist image UUID specified', 400);
        }

        $imageRequest = $this->createImageRequest('playlist', $params);

        if (!$imageRequest->isValidUuid()) {
            return $this->createErrorResponse($response, 'Invalid playlist image UUID format', 400);
        }

        return $this->serveImage($request, $response, $imageRequest);
    }

    /**
     * Serve image based on request
     */
    private function serveImage(Request $request, Response $response, ImageRequest $imageRequest): Response
    {
        // Try to find the image file
        $filePath = $this->resolveImagePath($imageRequest);

        // Fallback to placeholder if not found
        if ($filePath === null || $filePath === '' || $filePath === '0') {
            $filePath = $this->getPlaceholderPath($imageRequest->getType());
        }

        // If no placeholder available, return 404
        if ($filePath === null || $filePath === '' || $filePath === '0') {
            return $this->createErrorResponse($response, ucfirst($imageRequest->getType()) . ' image not found', 404);
        }

        // Validate image file
        $validationErrors = $this->validateImageFile($filePath);
        if ($validationErrors !== []) {
            return $this->createErrorResponse($response, 'Image validation failed: ' . implode(', ', $validationErrors), 500);
        }

        // Create image response
        $imageResponse = $this->createImageResponse($filePath);

        // Add cache headers
        $this->addCacheHeaders($imageResponse);

        // Check if client cache is valid
        if ($this->isNotModified($request, $imageResponse)) {
            return $response->withStatus(304);
        }

        // Stream the image
        return $this->streamImageToResponse($imageResponse, $response);
    }

    // ========================================
    // IMAGE PATH RESOLUTION (from ImagePathService)
    // ========================================

    /**
     * Resolve image file path based on request
     */
    private function resolveImagePath(ImageRequest $request): ?string
    {
        return match ($request->getType()) {
            'album' => $this->resolveAlbumCoverPath($request),
            'artist' => $this->resolveArtistImagePath($request),
            'profile' => $this->resolveProfileImagePath($request),
            'playlist' => $this->resolvePlaylistImagePath($request),
            default => null
        };
    }

    private function resolveAlbumCoverPath(ImageRequest $request): ?string
    {
        $albumId = $request->getId();
        $baseDir = $this->config['coverDir'] ?? '';

        if (!$baseDir || !is_dir($baseDir)) {
            return null;
        }

        // Try with prefix first (current format), then without (future format)
        $withPrefix = $this->findImageFile($baseDir, 'album_' . $albumId);
        if ($withPrefix !== null && $withPrefix !== '' && $withPrefix !== '0') {
            return $withPrefix;
        }

        // Fallback to files without prefix
        return $this->findImageFile($baseDir, $albumId);
    }

    private function resolveArtistImagePath(ImageRequest $request): ?string
    {
        $artistId = $request->getId();
        $baseDir = $this->config['artistImagesDir'] ?? '';

        if (!$baseDir || !is_dir($baseDir)) {
            return null;
        }

        // Try with prefix first (current format), then without (future format)
        $withPrefix = $this->findImageFile($baseDir, 'artist_' . $artistId);
        if ($withPrefix !== null && $withPrefix !== '' && $withPrefix !== '0') {
            return $withPrefix;
        }

        // Fallback to files without prefix
        return $this->findImageFile($baseDir, $artistId);
    }

    private function resolveProfileImagePath(ImageRequest $request): ?string
    {
        $uuid = $request->getId();
        $baseDir = $this->config['profileDir'] ?? '';

        if (!$baseDir || !is_dir($baseDir)) {
            return null;
        }

        // Try with prefix first, then without for backward compatibility
        $withPrefix = $this->findImageFile($baseDir, 'profile_' . $uuid);
        if ($withPrefix !== null && $withPrefix !== '' && $withPrefix !== '0') {
            return $withPrefix;
        }

        // Fallback to files without prefix
        return $this->findImageFile($baseDir, $uuid);
    }

    private function resolvePlaylistImagePath(ImageRequest $request): ?string
    {
        $uuid = $request->getId();
        $baseDir = $this->config['playlistCoversDir'] ?? '';

        if (!$baseDir || !is_dir($baseDir)) {
            return null;
        }

        // Playlist files have 'playlist_' prefix
        return $this->findImageFile($baseDir, 'playlist_' . $uuid);
    }

    private function findImageFile(string $baseDir, string $filename): ?string
    {
        foreach ($this->supportedExtensions as $ext) {
            $filePath = rtrim($baseDir, '/') . '/' . $filename . '.' . $ext;
            if (file_exists($filePath) && is_readable($filePath)) {
                return $filePath;
            }
        }
        return null;
    }

    private function getPlaceholderPath(string $type): ?string
    {
        $placeholder = match ($type) {
            'album' => $this->config['albumPlaceholder'] ?? null,
            'artist' => $this->config['artistPlaceholder'] ?? null,
            'profile' => $this->config['defaultProfileImage'] ?? null,
            'playlist' => $this->config['albumPlaceholder'] ?? null, // Use album placeholder for playlists
            default => null
        };

        return ($placeholder && file_exists($placeholder)) ? $placeholder : null;
    }

    // ========================================
    // IMAGE CACHING (from ImageCacheService)
    // ========================================

    /**
     * Add cache headers to image response
     */
    private function addCacheHeaders(ImageResponse $response): void
    {
        if (!$response->isSuccess()) {
            return;
        }

        $filePath = $response->getFilePath();
        $fileMTime = filemtime($filePath);

        if ($fileMTime) {
            $response->addHeader('Last-Modified', gmdate('D, d M Y H:i:s', $fileMTime) . ' GMT');
            $response->addHeader('ETag', '"' . md5($filePath . $fileMTime) . '"');
        }

        $response->addHeader('Cache-Control', 'public, max-age=' . $this->cacheTTL);
        $response->addHeader('Expires', gmdate('D, d M Y H:i:s', time() + $this->cacheTTL) . ' GMT');
    }

    /**
     * Check if client's cached version is still valid
     */
    private function isNotModified(Request $request, ImageResponse $imageResponse): bool
    {
        if (!$imageResponse->isSuccess()) {
            return false;
        }

        $filePath = $imageResponse->getFilePath();
        $fileMTime = filemtime($filePath);

        if ($fileMTime === 0 || $fileMTime === false) {
            return false;
        }

        // Check If-Modified-Since header
        $ifModifiedSince = $request->getHeaderLine('If-Modified-Since');
        if ($ifModifiedSince !== '' && $ifModifiedSince !== '0') {
            $ifModifiedSinceTime = strtotime($ifModifiedSince);
            if ($ifModifiedSinceTime && $fileMTime <= $ifModifiedSinceTime) {
                return true;
            }
        }

        // Check If-None-Match header (ETag)
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if ($ifNoneMatch !== '' && $ifNoneMatch !== '0') {
            $etag = '"' . md5($filePath . $fileMTime) . '"';
            if ($ifNoneMatch === $etag) {
                return true;
            }
        }

        return false;
    }

    // ========================================
    // IMAGE STREAMING (from ImageStreamingService)
    // ========================================

    /**
     * Stream image file to response
     */
    private function streamImageToResponse(ImageResponse $imageResponse, Response $response): Response
    {
        if (!$imageResponse->isSuccess()) {
            $response->getBody()->write(in_array($imageResponse->getError(), [null, '', '0'], true) ? 'Image not found' : $imageResponse->getError());
            return $response->withStatus($imageResponse->getStatusCode());
        }

        $filePath = $imageResponse->getFilePath();

        // Set headers
        $response = $response->withHeader('Content-Type', $imageResponse->getMimeType());
        $response = $response->withHeader('Content-Length', (string)$imageResponse->getFileSize());

        // Add cache headers from ImageResponse
        foreach ($imageResponse->getHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        // Stream file content
        $fileHandle = fopen($filePath, 'rb');
        if (!$fileHandle) {
            $response->getBody()->write('Error reading image file');
            return $response->withStatus(500);
        }

        while (!feof($fileHandle)) {
            $bufferSize = max(1, $this->bufferSize);
            $chunk = fread($fileHandle, $bufferSize);
            if ($chunk !== false) {
                $response->getBody()->write($chunk);
            }
        }

        fclose($fileHandle);
        return $response;
    }

    /**
     * Validate image file
     */
    /** @return array<string> */
    private function validateImageFile(string $filePath): array
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

        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize === 0) {
            $errors[] = 'File is empty or unreadable';
            return $errors;
        }

        $mimeType = $this->getMimeType($filePath);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'])) {
            $errors[] = 'Unsupported image format: ' . $mimeType;
        }

        return $errors;
    }

    /**
     * Get MIME type of image file
     */
    private function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => mime_content_type($filePath) ?: 'application/octet-stream'
        };
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Create image request from parameters
     */
    /** @param array<string, mixed> $params */
    private function createImageRequest(string $type, array $params): ImageRequest
    {
        $id = match ($type) {
            'album' => $params['albumId'] ?? '',
            'artist' => $params['artistId'] ?? '',
            'profile', 'playlist' => $params['uuid'] ?? '',
            default => ''
        };

        $size = $this->determineImageSize($params);
        $thumbnail = !empty($params['thumbnail']) && $params['thumbnail'] === '1';

        return new ImageRequest($type, $id, $size, $thumbnail, $params);
    }

    /**
     * Determine image size from parameters
     */
    /** @param array<string, mixed> $params */
    private function determineImageSize(array $params): string
    {
        if (!empty($params['size'])) {
            return match (strtolower($params['size'])) {
                'small' => 'small',
                'tiny' => 'tiny',
                'large' => 'large',
                default => 'medium'
            };
        }

        if (!empty($params['thumbnail']) && $params['thumbnail'] === '1') {
            return 'small';
        }

        return 'medium';
    }

    /**
     * Create image response
     */
    private function createImageResponse(string $filePath): ImageResponse
    {
        $mimeType = $this->getMimeType($filePath);
        $fileSize = filesize($filePath);

        if ($fileSize === false) {
            throw new \RuntimeException("Could not get file size for $filePath");
        }

        return new ImageResponse($filePath, $mimeType, $fileSize);
    }

    /**
     * Create error response
     */
    private function createErrorResponse(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write($message);
        return $response->withStatus($status);
    }

    /**
     * Get service configuration
     */
    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get image service statistics
     */
    /** @return array<string, mixed> */
    public function getStatistics(): array
    {
        return [
            'supported_formats' => $this->supportedExtensions,
            'buffer_size' => $this->bufferSize,
            'cache_ttl' => $this->cacheTTL,
            'config' => [
                'coverDir' => $this->config['coverDir'] ?? null,
                'artistImagesDir' => $this->config['artistImagesDir'] ?? null,
                'profileDir' => $this->config['profileDir'] ?? null,
                'playlistCoversDir' => $this->config['playlistCoversDir'] ?? null
            ]
        ];
    }

    /**
     * Validate service configuration
     */
    /** @return array<string> */
    public function validateConfiguration(): array
    {
        $errors = [];

        $requiredDirs = [
            'coverDir' => 'Album cover directory',
            'artistImagesDir' => 'Artist images directory',
            'profileDir' => 'Profile images directory',
            'playlistCoversDir' => 'Playlist covers directory'
        ];

        foreach ($requiredDirs as $key => $description) {
            if (empty($this->config[$key])) {
                $errors[] = "{$description} not configured";
            } elseif (!is_dir($this->config[$key])) {
                $errors[] = "{$description} does not exist: {$this->config[$key]}";
            }
        }

        return $errors;
    }

    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    public function getCacheTTL(): int
    {
        return $this->cacheTTL;
    }
}
