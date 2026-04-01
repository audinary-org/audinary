<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\PlaylistSongRepository;
use Exception;

/**
 * Service to generate playlist cover collages from album covers
 * Generates collages as WebP images for playlists based on their songs' album covers
 */
final class PlaylistCollageService
{
    private PlaylistSongRepository $playlistSongRepository;
    /** @var array<string, mixed> */
    private array $config;
    private int $maxSize;
    private string $playlistCoversDir;
    /** @var array<int, string> */
    private array $supportedExtensions = ['webp', 'jpg', 'jpeg', 'png'];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        PlaylistSongRepository $playlistSongRepository,
        array $config,
        int $maxSize = 500
    ) {
        $this->playlistSongRepository = $playlistSongRepository;
        $this->config = $config;
        $this->maxSize = $maxSize;
        $this->playlistCoversDir = $config['playlistCoversDir'] ?? '';

        // Ensure playlist covers directory exists
        if ($this->playlistCoversDir !== '' && $this->playlistCoversDir !== '0' && !is_dir($this->playlistCoversDir)) {
            mkdir($this->playlistCoversDir, 0755, true);
        }
    }

    /**
     * Generate collage for a playlist
     */
    public function generateCollage(string $playlistId): bool
    {
        if ($this->playlistCoversDir === '' || $this->playlistCoversDir === '0') {
            error_log("PlaylistCollageService: playlistCoversDir not configured");
            return false;
        }

        // Get unique album covers from playlist songs
        $albumCovers = $this->getUniqueAlbumCovers($playlistId);

        if ($albumCovers === []) {
            // Delete existing collage if playlist is empty
            $this->deleteCollage($playlistId);
            return true;
        }

        try {
            $outputPath = $this->playlistCoversDir . '/playlist_' . $playlistId . '.webp';
            return $this->createCollage($albumCovers, $outputPath);
        } catch (Exception $e) {
            error_log("PlaylistCollageService: Error creating collage for playlist {$playlistId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete collage for a playlist
     */
    public function deleteCollage(string $playlistId): bool
    {
        if ($this->playlistCoversDir === '' || $this->playlistCoversDir === '0') {
            return true;
        }

        $collageFile = $this->playlistCoversDir . '/playlist_' . $playlistId . '.webp';

        if (file_exists($collageFile)) {
            return unlink($collageFile);
        }

        return true;
    }

    /**
     * Get unique album covers from playlist songs
     */
    /** @return array<int, string> */
    private function getUniqueAlbumCovers(string $playlistId): array
    {
        $songs = $this->playlistSongRepository->findByPlaylistId($playlistId);
        $albumIds = [];
        $albumCovers = [];
        $coverDir = $this->config['coverDir'] ?? '';

        if (empty($coverDir)) {
            return [];
        }

        foreach ($songs as $song) {
            $albumId = $song['album_id'] ?? null;

            if ($albumId && !in_array($albumId, $albumIds)) {
                $albumIds[] = $albumId;

                // Try to find album cover file
                $coverPath = $this->findAlbumCoverPath($coverDir, $albumId);
                if ($coverPath !== null && $coverPath !== '' && $coverPath !== '0') {
                    $albumCovers[] = $coverPath;
                }
            }
        }

        // Limit to maximum 9 covers for optimal grid layout
        return array_slice($albumCovers, 0, 9);
    }

    /**
     * Find album cover file path
     */
    private function findAlbumCoverPath(string $coverDir, string $albumId): ?string
    {
        // Try with prefix first (current format), then without (future format)
        $coverPath = $this->findImageFile($coverDir, 'album_' . $albumId);
        if ($coverPath !== null && $coverPath !== '' && $coverPath !== '0') {
            return $coverPath;
        }

        return $this->findImageFile($coverDir, $albumId);
    }

    /**
     * Find image file with supported extensions
     */
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

    /**
     * Calculate optimal grid layout for the number of albums
     */
    /** @return array{0: int, 1: int} */
    private function calculateGrid(int $albumCount): array
    {
        if ($albumCount <= 1) {
            return [1, 1];
        }
        if ($albumCount <= 4) {
            return [2, 2];
        }
        if ($albumCount <= 6) {
            return [3, 2];
        }
        if ($albumCount <= 9) {
            return [3, 3];
        }

        $cols = (int)ceil(sqrt($albumCount));
        $rows = (int)ceil($albumCount / $cols);
        return [$cols, $rows];
    }

    /**
     * Create collage from album covers
     * @param array<int, string> $albumCovers
     */
    private function createCollage(array $albumCovers, string $outputPath): bool
    {
        [$cols, $rows] = $this->calculateGrid(count($albumCovers));

        // Calculate tile size
        $tileSize = (int) floor($this->maxSize / max($cols, $rows));
        $canvasWidth = max(1, (int)($cols * $tileSize));
        $canvasHeight = max(1, (int)($rows * $tileSize));

        // Create main canvas
        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        if (!$canvas) {
            throw new Exception("Failed to create image canvas");
        }

        $backgroundColor = imagecolorallocate($canvas, 240, 240, 240);
        if ($backgroundColor === false) {
            $backgroundColor = 0;
        }
        imagefill($canvas, 0, 0, $backgroundColor);

        // Place covers on canvas
        foreach ($albumCovers as $index => $coverPath) {
            $x = ($index % $cols) * $tileSize;
            $y = (int)(floor($index / $cols) * $tileSize);

            try {
                $this->placeCoverOnCanvas($canvas, $coverPath, $x, $y, $tileSize);
            } catch (Exception $e) {
                error_log("PlaylistCollageService: Failed to place cover {$coverPath}: " . $e->getMessage());
                // Create placeholder for failed cover
                $this->createPlaceholder($canvas, $x, $y, $tileSize, $index + 1);
            }
        }

        // Save as WebP
        $success = imagewebp($canvas, $outputPath, 90);

        if (!$success) {
            throw new Exception("Failed to save WebP image");
        }

        return true;
    }

    /**
     * Place an album cover on the canvas
     */
    private function placeCoverOnCanvas(\GdImage $canvas, string $coverPath, int $x, int $y, int $size): void
    {
        // Load cover image based on format
        $imageInfo = getimagesize($coverPath);
        if ($imageInfo === false) {
            throw new Exception("Invalid image format");
        }

        $cover = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($coverPath),
            IMAGETYPE_PNG => imagecreatefrompng($coverPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($coverPath),
            IMAGETYPE_GIF => imagecreatefromgif($coverPath),
            default => throw new Exception("Unsupported image format")
        };

        if (!$cover) {
            throw new Exception("Could not load image");
        }

        // Resize image to tile size
        $resized = imagescale($cover, $size, $size, IMG_BICUBIC);
        if (!$resized) {
            throw new Exception("Could not resize image");
        }

        // Copy to canvas
        imagecopy($canvas, $resized, $x, $y, 0, 0, $size, $size);
    }

    /**
     * Create placeholder for missing cover
     */
    private function createPlaceholder(\GdImage $canvas, int $x, int $y, int $size, int $number): void
    {
        // Placeholder background
        $placeholderColor = imagecolorallocate($canvas, 200, 200, 200);
        if ($placeholderColor === false) {
            $placeholderColor = 0;
        }
        imagefilledrectangle($canvas, $x, $y, $x + $size, $y + $size, $placeholderColor);

        // Border
        $borderColor = imagecolorallocate($canvas, 150, 150, 150);
        if ($borderColor === false) {
            $borderColor = 0;
        }
        imagerectangle($canvas, $x, $y, $x + $size - 1, $y + $size - 1, $borderColor);

        // Simple number display (fallback without TTF)
        $textColor = imagecolorallocate($canvas, 100, 100, 100);
        if ($textColor === false) {
            $textColor = 0;
        }
        $numberString = (string)$number;
        $fontSize = 5; // Built-in font size

        // Center the text
        $textWidth = imagefontwidth($fontSize) * strlen($numberString);
        $textHeight = imagefontheight($fontSize);
        $textX = $x + ($size - $textWidth) / 2;
        $textY = $y + ($size - $textHeight) / 2;

        imagestring($canvas, $fontSize, (int)$textX, (int)$textY, $numberString, $textColor);
    }

    /**
     * Check if collage exists for playlist
     */
    public function collageExists(string $playlistId): bool
    {
        if ($this->playlistCoversDir === '' || $this->playlistCoversDir === '0') {
            return false;
        }

        $collageFile = $this->playlistCoversDir . '/playlist_' . $playlistId . '.webp';
        return file_exists($collageFile);
    }

    /**
     * Get collage file path for playlist
     */
    public function getCollagePath(string $playlistId): ?string
    {
        if ($this->playlistCoversDir === '' || $this->playlistCoversDir === '0') {
            return null;
        }

        $collageFile = $this->playlistCoversDir . '/playlist_' . $playlistId . '.webp';
        return file_exists($collageFile) ? $collageFile : null;
    }
}
