<?php

namespace App\Models;

/**
 * Represents media file metadata
 */
class MediaMetadata
{
    public function __construct(
        private string $filePath,
        private string $mimeType,
        private int $fileSize,
        private float $duration,
        private string $extension,
        private ?string $title = null,
        private ?string $artist = null,
        private ?string $album = null,
        private ?int $bitrate = null,
        private ?string $codec = null,
        private ?int $sampleRate = null,
        private ?int $channels = null
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

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function getAlbum(): ?string
    {
        return $this->album;
    }

    public function getBitrate(): ?int
    {
        return $this->bitrate;
    }

    public function getCodec(): ?string
    {
        return $this->codec;
    }

    public function getSampleRate(): ?int
    {
        return $this->sampleRate;
    }

    public function getChannels(): ?int
    {
        return $this->channels;
    }

    public function isBrowserSupported(): bool
    {
        $supportedExtensions = ['mp3', 'mpeg', 'ogg', 'oga', 'wav', 'aac', 'm4a', 'mp4', 'webm', 'flac'];
        return in_array(strtolower($this->extension), $supportedExtensions);
    }

    public function isProblematic(): bool
    {
        $problematicExtensions = ['wma', 'aiff', 'aif', 'ape', 'wv', 'mpc', 'opus', 'ra', 'rm', 'mka'];
        return in_array(strtolower($this->extension), $problematicExtensions);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'duration' => $this->duration,
            'extension' => $this->extension,
            'title' => $this->title,
            'artist' => $this->artist,
            'album' => $this->album,
            'bitrate' => $this->bitrate,
            'codec' => $this->codec,
            'sample_rate' => $this->sampleRate,
            'channels' => $this->channels,
            'is_browser_supported' => $this->isBrowserSupported(),
            'is_problematic' => $this->isProblematic()
        ];
    }
}
