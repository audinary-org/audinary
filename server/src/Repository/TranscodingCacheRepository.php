<?php

namespace App\Repository;

use App\Models\TranscodingJob;

/**
 * Repository for transcoding cache operations
 */
class TranscodingCacheRepository extends BaseRepository
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        parent::__construct();
        $this->cacheDir = rtrim($cacheDir, '/');
    }

    /**
     * Check if cached version exists and is valid
     */
    public function getCachedFile(TranscodingJob $job): ?string
    {
        $cachePath = $this->getCachePath($job);

        if (!file_exists($cachePath)) {
            return null;
        }

        // Check if cache is newer than original
        $originalModTime = filemtime($job->getOriginalPath());
        $cacheModTime = filemtime($cachePath);

        if ($cacheModTime >= $originalModTime) {
            return $cachePath;
        }

        // Cache is outdated, remove it
        @unlink($cachePath);
        return null;
    }

    /**
     * Get the cache file path for a transcoding job
     */
    public function getCachePath(TranscodingJob $job): string
    {
        $filename = $job->getCacheKey() . '.' . $job->getFormat();
        return $this->cacheDir . '/' . $filename;
    }

    /**
     * Ensure cache directory exists
     */
    public function ensureCacheDirectory(): bool
    {
        if (!is_dir($this->cacheDir)) {
            return mkdir($this->cacheDir, 0755, true);
        }
        return true;
    }

    /**
     * Clean up old cache files
     */
    public function cleanupOldFiles(int $maxAge = 86400): int
    {
        $deleted = 0;
        $cutoffTime = time() - $maxAge;

        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        $files = glob($this->cacheDir . '/*') ?: [];
        /** @var list<string> $files */
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            if (filemtime($file) >= $cutoffTime) {
                continue;
            }
            if (!@unlink($file)) {
                continue;
            }
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Get cache directory size
     */
    public function getCacheSize(): int
    {
        $size = 0;

        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        $files = glob($this->cacheDir . '/*') ?: [];
        /** @var list<string> $files */
        foreach ($files as $file) {
            if (is_file($file)) {
                $size += filesize($file);
            }
        }

        return $size;
    }

    /**
     * Get cache statistics
     */
    /** @return array<string, mixed> */
    public function getCacheStats(): array
    {
        if (!is_dir($this->cacheDir)) {
            return [
                'file_count' => 0,
                'total_size' => 0,
                'average_size' => 0
            ];
        }

        $files = glob($this->cacheDir . '/*') ?: [];
        /** @var list<string> $files */
        $fileCount = 0;
        $totalSize = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $fileCount++;
                $totalSize += filesize($file);
            }
        }

        return [
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'average_size' => $fileCount > 0 ? round($totalSize / $fileCount) : 0
        ];
    }

    /**
     * Clear all cache files
     */
    public function clearCache(): int
    {
        $deleted = 0;

        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        $files = glob($this->cacheDir . '/*') ?: [];
        /** @var list<string> $files */
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
