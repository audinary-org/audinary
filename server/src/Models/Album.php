<?php

namespace App\Models;

class Album
{
    private string $albumId;
    private string $albumName;
    private int $albumYear;
    private string $albumGenre;
    private int $albumDuration;
    private int $albumTotalTracks;
    private string $artistId;
    private string $albumArtist;
    private string $coverArtUrl;
    private ?string $lastPlayed;
    private bool $isFavorite;
    private ?string $filetype;
    /** @var array<string, mixed>|null */
    private ?array $coverGradient;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->albumId = $data['album_id'];
        $this->albumName = $data['album_name'] ?? '';
        $this->albumYear = (int)($data['album_year'] ?? 0);
        $this->albumGenre = $data['album_genre'] ?? '';
        $this->albumDuration = (int)($data['album_duration'] ?? 0);
        $this->albumTotalTracks = (int)($data['total_tracks'] ?? 0);
        $this->artistId = $data['artist_id'] ?? '';
        $this->albumArtist = $data['album_artist'] ?? $data['artist_name'] ?? '';
        $this->coverArtUrl = '/img/userdata/albums/' . $this->albumId . '.webp';
        $this->lastPlayed = $data['lastPlayed'] ?? $data['last_played'] ?? null;
        $this->isFavorite = (bool)($data['is_favorite'] ?? false);
        $this->filetype = $data['filetype'] ?? null;

        // Parse cover_gradient JSON if present
        $this->coverGradient = null;
        if (!empty($data['cover_gradient'])) {
            $decoded = json_decode($data['cover_gradient'], true);
            if (is_array($decoded)) {
                $this->coverGradient = $decoded;
            }
        }
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

    public function getAlbumTotalTracks(): int
    {
        return $this->albumTotalTracks;
    }

    public function getArtistId(): string
    {
        return $this->artistId;
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

    public function getFiletype(): ?string
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
            'album_id' => $this->albumId,
            'albumName' => $this->albumName,
            'albumYear' => $this->albumYear,
            'albumGenre' => $this->albumGenre,
            'albumDuration' => $this->albumDuration,
            'albumTracks' => $this->albumTotalTracks,
            'artist_id' => $this->artistId,
            'albumArtist' => $this->albumArtist,
            'coverArtUrl' => $this->coverArtUrl,
            'albumLastPlayed' => $this->lastPlayed,
            'albumIsFavorite' => $this->isFavorite,
            'albumFiletype' => $this->filetype,
            'coverGradient' => $this->coverGradient,
        ];
    }
}
