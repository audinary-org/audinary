<?php

namespace App\Models;

class Decade
{
    private int $startYear;
    private int $endYear;
    private string $decade;
    private int $albumCount;
    private int $artistCount;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->startYear = (int)($data['start_year'] ?? $data['decade_start'] ?? 0);
        $this->endYear = (int)($data['end_year'] ?? $this->startYear + 9);
        $this->decade = $data['decade'] ?? $this->startYear . 'er';
        $this->albumCount = (int)($data['album_count'] ?? 0);
        $this->artistCount = (int)($data['artist_count'] ?? 0);
    }

    public function getStartYear(): int
    {
        return $this->startYear;
    }

    public function getEndYear(): int
    {
        return $this->endYear;
    }

    public function getDecade(): string
    {
        return $this->decade;
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
            'decade' => $this->decade,
            'start_year' => $this->startYear,
            'end_year' => $this->endYear,
            'album_count' => $this->albumCount,
            'artist_count' => $this->artistCount,
        ];
    }
}
