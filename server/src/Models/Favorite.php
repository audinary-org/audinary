<?php

namespace App\Models;

class Favorite
{
    private string $favoriteType;
    private ?string $songId;
    private ?string $albumId;
    private ?string $artistId;
    private ?string $playlistId;
    private string $createdAt;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->favoriteType = $data['favorite_type'];
        $this->songId = $data['song_id'] ?? null;
        $this->albumId = $data['album_id'] ?? null;
        $this->artistId = $data['artist_id'] ?? null;
        $this->playlistId = $data['playlist_id'] ?? null;
        $this->createdAt = $data['created_at'] ?? '';
    }

    public function getFavoriteType(): string
    {
        return $this->favoriteType;
    }

    public function getSongId(): ?string
    {
        return $this->songId;
    }

    public function getAlbumId(): ?string
    {
        return $this->albumId;
    }

    public function getArtistId(): ?string
    {
        return $this->artistId;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getEntityId(): ?string
    {
        return match ($this->favoriteType) {
            'song' => $this->songId,
            'album' => $this->albumId,
            'artist' => $this->artistId,
            'playlist' => $this->playlistId,
            default => null
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'favorite_type' => $this->favoriteType,
            'song_id' => $this->songId,
            'album_id' => $this->albumId,
            'artist_id' => $this->artistId,
            'playlist_id' => $this->playlistId,
            'created_at' => $this->createdAt,
        ];
    }
}
