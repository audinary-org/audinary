<?php

namespace App\Models;

/**
 * Represents a transcoding job with parameters and results
 */
class TranscodingJob
{
    public function __construct(
        private string $jobId,
        private string $songId,
        private string $originalPath,
        private string $format,
        private int $maxBitrate,
        private string $bitrateMode,
        private ?string $cachePath = null,
        private bool $isCompleted = false,
        private ?string $error = null,
        private ?int $outputSize = null,
        private ?float $duration = null
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getSongId(): string
    {
        return $this->songId;
    }

    public function getOriginalPath(): string
    {
        return $this->originalPath;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getMaxBitrate(): int
    {
        return $this->maxBitrate;
    }

    public function getBitrateMode(): string
    {
        return $this->bitrateMode;
    }

    public function getCachePath(): ?string
    {
        return $this->cachePath;
    }

    public function setCachePath(string $cachePath): void
    {
        $this->cachePath = $cachePath;
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function markCompleted(): void
    {
        $this->isCompleted = true;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(string $error): void
    {
        $this->error = $error;
    }

    public function getOutputSize(): ?int
    {
        return $this->outputSize;
    }

    public function setOutputSize(int $outputSize): void
    {
        $this->outputSize = $outputSize;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function setDuration(float $duration): void
    {
        $this->duration = $duration;
    }

    public function getCacheKey(): string
    {
        return md5($this->songId . '_' . $this->format . '_' . $this->maxBitrate . '_' . $this->bitrateMode);
    }

    /**
     * @return array{
     *     job_id: string,
     *     song_id: string,
     *     original_path: string,
     *     format: string,
     *     max_bitrate: int,
     *     bitrate_mode: string,
     *     cache_path: string|null,
     *     is_completed: bool,
     *     error: string|null,
     *     output_size: int|null,
     *     duration: float|null
     * }
     */
    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'song_id' => $this->songId,
            'original_path' => $this->originalPath,
            'format' => $this->format,
            'max_bitrate' => $this->maxBitrate,
            'bitrate_mode' => $this->bitrateMode,
            'cache_path' => $this->cachePath,
            'is_completed' => $this->isCompleted,
            'error' => $this->error,
            'output_size' => $this->outputSize,
            'duration' => $this->duration
        ];
    }
}
