<?php

namespace App\Models;

class Genre
{
    private string $name;
    private int $trackCount;
    private int $albumCount;
    private int $artistCount;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->name = $data['name'] ?? $data['genre'] ?? '';
        $this->trackCount = (int)($data['track_count'] ?? 0);
        $this->albumCount = (int)($data['album_count'] ?? 0);
        $this->artistCount = (int)($data['artist_count'] ?? 0);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTrackCount(): int
    {
        return $this->trackCount;
    }

    public function getAlbumCount(): int
    {
        return $this->albumCount;
    }

    public function getArtistCount(): int
    {
        return $this->artistCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'track_count' => $this->trackCount,
            'album_count' => $this->albumCount,
            'artist_count' => $this->artistCount,
        ];
    }
}
