<?php

namespace App\Models;

/**
 * Represents an image response with metadata
 */
class ImageResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $filePath,
        private string $mimeType,
        private int $fileSize,
        private bool $success = true,
        private ?string $error = null,
        private int $statusCode = 200,
        private array $headers = []
    ) {
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

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
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

    public function setError(string $error, int $statusCode = 400): void
    {
        $this->error = $error;
        $this->statusCode = $statusCode;
        $this->success = false;
    }

    public function generateETag(): string
    {
        $etag = md5_file($this->filePath);
        if ($etag === false) {
            throw new \RuntimeException("Failed to generate ETag for file: " . $this->filePath);
        }
        return $etag;
    }

    public function getLastModified(): string
    {
        $mtime = filemtime($this->filePath);
        if ($mtime === false) {
            throw new \RuntimeException("Failed to get modification time for file: " . $this->filePath);
        }
        return gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'file_path' => $this->filePath,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'error' => $this->error,
            'status_code' => $this->statusCode,
            'headers' => $this->headers
        ];
    }
}
