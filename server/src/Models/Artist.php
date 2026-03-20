<?php

namespace App\Models;

class Artist
{
    private string $artistId;
    private string $artistName;
    private string $artistImageUrl;
    private ?string $lastPlayed;
    private ?string $dateAdded;
    private int $albumCount;
    private int $songCount;
    private int $playCount;
    private bool $isFavorite;
    /** @var array<string, mixed>|null */
    private ?array $artistGradient;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->artistId = $data['artist_id'];
        $this->artistName = $data['artist_name'] ?? $data['artistName'] ?? '';
        $this->artistImageUrl = '/img/userdata/artists/' . $this->artistId . '.webp';
        $this->lastPlayed = $data['lastPlayed'] ?? $data['last_played'] ?? null;
        $this->dateAdded = $data['dateAdded'] ?? $data['created_at'] ?? null;
        $this->albumCount = (int)($data['albumCount'] ?? $data['album_count'] ?? 0);
        $this->songCount = (int)($data['songCount'] ?? $data['song_count'] ?? 0);
        $this->playCount = (int)($data['play_count'] ?? 0);
        $this->isFavorite = (bool)($data['is_favorite'] ?? false);

        // Parse artist_gradient JSON if present
        $this->artistGradient = null;
        if (!empty($data['artist_gradient'])) {
            $decoded = json_decode($data['artist_gradient'], true);
            if (is_array($decoded)) {
                $this->artistGradient = $decoded;
            }
        }
    }

    public function getArtistId(): string
    {
        return $this->artistId;
    }

    public function getArtistName(): string
    {
        return $this->artistName;
    }

    public function getArtistImageUrl(): string
    {
        return $this->artistImageUrl;
    }

    public function getLastPlayed(): ?string
    {
        return $this->lastPlayed;
    }

    public function getDateAdded(): ?string
    {
        return $this->dateAdded;
    }

    public function getAlbumCount(): int
    {
        return $this->albumCount;
    }

    public function getSongCount(): int
    {
        return $this->songCount;
    }

    public function getPlayCount(): int
    {
        return $this->playCount;
    }

    public function isFavorite(): bool
    {
        return $this->isFavorite;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getArtistGradient(): ?array
    {
        return $this->artistGradient;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'artist_id' => $this->artistId,
            'artistName' => $this->artistName,
            'artistImageUrl' => $this->artistImageUrl,
            'lastPlayed' => $this->lastPlayed,
            'dateAdded' => $this->dateAdded,
            'albumCount' => $this->albumCount,
            'songCount' => $this->songCount,
            'is_favorite' => $this->isFavorite,
            'artistGradient' => $this->artistGradient,
        ];
    }
}
