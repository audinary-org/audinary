<?php

namespace App\Models;

/**
 * Represents a streaming session with metadata
 */
class StreamingSession
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $sessionId,
        private string $songId,
        private string $userId,
        private string $filePath,
        private string $mimeType,
        private int $fileSize,
        private float $duration,
        private bool $isTranscoded = false,
        private ?string $format = null,
        private ?int $bitrate = null,
        private ?string $bitrateMode = null,
        private array $headers = []
    ) {
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getSongId(): string
    {
        return $this->songId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function isTranscoded(): bool
    {
        return $this->isTranscoded;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getBitrate(): ?int
    {
        return $this->bitrate;
    }

    public function getBitrateMode(): ?string
    {
        return $this->bitrateMode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function addHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'song_id' => $this->songId,
            'user_id' => $this->userId,
            'file_path' => $this->filePath,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'duration' => $this->duration,
            'is_transcoded' => $this->isTranscoded,
            'format' => $this->format,
            'bitrate' => $this->bitrate,
            'bitrate_mode' => $this->bitrateMode,
            'headers' => $this->headers
        ];
    }
}
