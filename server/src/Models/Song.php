<?php

namespace App\Models;

class Song
{
    private string $songId;
    private string $filePath;
    private string $title;
    private int $bitrate;
    private int $trackNumber;
    private int $duration;
    private string $genre;
    private int $year;
    private int $discNumber;
    private string $artist;
    private string $albumId;
    private string $albumName;
    private int $albumYear;
    private string $albumGenre;
    private int $albumDuration;
    private string $albumArtist;
    private string $coverArtUrl;
    private ?string $lastPlayed;
    private bool $isFavorite;
    /** @var array<string, mixed>|null */
    private ?array $coverGradient;

    // Additional database fields
    private int $size;
    private int $lastMtime;
    private bool $isDeleted;
    private ?string $createdAt;
    private string $filetype;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->songId = $data['song_id'];
        $this->filePath = $data['file_path'] ?? $data['file'] ?? '';
        $this->title = $data['title'] ?? '';
        $this->bitrate = (int)($data['bitrate'] ?? 0);
        $this->trackNumber = (int)($data['track_number'] ?? 0);
        $this->duration = (int)($data['duration'] ?? 0);
        $this->genre = $data['genre'] ?? '';
        $this->year = (int)($data['year'] ?? 0);
        $this->discNumber = (int)($data['disc_number'] ?? 0);
        $this->artist = $data['track_artist_name'] ?? $data['artist'] ?? '';
        $this->albumId = $data['album_id'] ?? '';
        $this->albumName = $data['album_name'] ?? '';
        $this->albumYear = (int)($data['album_year'] ?? 0);
        $this->albumGenre = $data['album_genre'] ?? '';
        $this->albumDuration = (int)($data['album_duration'] ?? 0);
        $this->albumArtist = $data['album_artist_name'] ?? $data['album_artist'] ?? '';
        $this->coverArtUrl = '/img/userdata/albums/' . $this->albumId . '.webp';
        $this->lastPlayed = $data['lastPlayed'] ?? null;
        $this->isFavorite = (bool)($data['is_favorite'] ?? false);

        // Parse cover_gradient JSON if present
        $this->coverGradient = null;
        if (!empty($data['cover_gradient'])) {
            $decoded = json_decode($data['cover_gradient'], true);
            if (is_array($decoded)) {
                $this->coverGradient = $decoded;
            }
        }

        // Additional database fields
        $this->size = (int)($data['size'] ?? 0);
        $this->lastMtime = (int)($data['last_mtime'] ?? 0);
        $this->isDeleted = (bool)($data['is_deleted'] ?? false);
        $this->createdAt = $data['created_at'] ?? null;
        $this->filetype = $data['filetype'] ?? '';
    }

    public function getSongId(): string
    {
        return $this->songId;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBitrate(): int
    {
        return $this->bitrate;
    }

    public function getTrackNumber(): int
    {
        return $this->trackNumber;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function getGenre(): string
    {
        return $this->genre;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getDiscNumber(): int
    {
        return $this->discNumber;
    }

    public function getArtist(): string
    {
        return $this->artist !== '' && $this->artist !== '0' ? $this->artist : $this->albumArtist;
    }

    public function getAlbumId(): string
    {
        return $this->albumId;
    }

    public function getAlbumName(): string
    {
        return $this->albumName;
    }

    public function getAlbumYear(): int
    {
        return $this->albumYear;
    }

    public function getAlbumGenre(): string
    {
        return $this->albumGenre;
    }

    public function getAlbumDuration(): int
    {
        return $this->albumDuration;
    }

    public function getAlbumArtist(): string
    {
        return $this->albumArtist;
    }

    public function getCoverArtUrl(): string
    {
        return $this->coverArtUrl;
    }

    public function getLastPlayed(): ?string
    {
        return $this->lastPlayed;
    }

    public function isFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getLastMtime(): int
    {
        return $this->lastMtime;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getFiletype(): string
    {
        return $this->filetype;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCoverGradient(): ?array
    {
        return $this->coverGradient;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'song_id' => $this->songId,
            'file' => $this->filePath,
            'title' => $this->title,
            'bitrate' => $this->bitrate,
            'track_number' => $this->trackNumber,
            'duration' => $this->duration,
            'genre' => $this->genre,
            'year' => $this->year,
            'disc_number' => $this->discNumber,
            'artist' => $this->getArtist(),
            'album_id' => $this->albumId,
            'album' => $this->albumName,
            'album_year' => $this->albumYear,
            'album_genre' => $this->albumGenre,
            'album_duration' => $this->albumDuration,
            'coverArtUrl' => $this->coverArtUrl,
            'lastPlayed' => $this->lastPlayed,
            'is_favorite' => $this->isFavorite,
            'coverGradient' => $this->coverGradient,

            // Additional database fields
            'size' => $this->size,
            'last_mtime' => $this->lastMtime,
            'is_deleted' => $this->isDeleted,
            'created_at' => $this->createdAt,
            'filetype' => $this->filetype,
            'format' => $this->filetype, // Alias for frontend compatibility
            'file_path' => $this->filePath, // Add file_path for download functionality
        ];
    }
}
