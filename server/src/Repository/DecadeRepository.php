<?php

namespace App\Repository;

use App\Models\Decade;

class DecadeRepository extends BaseRepository
{
    public function __construct(?string $userId = null)
    {
        parent::__construct($userId);
    }
    /**
     * @return array<int, Decade>
     */
    public function findAll(): array
    {
        $sql = "
            SELECT 
                FLOOR(a.album_year/10)*10 as decade_start,
                COUNT(DISTINCT a.album_id) as album_count,
                COUNT(DISTINCT a.album_artist) as artist_count,
                COUNT(DISTINCT s.song_id) as track_count
            FROM albums a
            LEFT JOIN songs s ON a.album_id = s.album_id
            WHERE a.is_deleted = 0 
              AND a.album_year > 0
            GROUP BY decade_start
            ORDER BY decade_start DESC
        ";

        $results = $this->executeQuery($sql, []);

        return array_map(function (array $row): \App\Models\Decade {
            $decadeStart = (int)$row['decade_start'];
            return new Decade([
                'decade' => $decadeStart . 'er',
                'start_year' => $decadeStart,
                'end_year' => $decadeStart + 9,
                'album_count' => (int)$row['album_count'],
                'artist_count' => (int)$row['artist_count'],
                'track_count' => (int)$row['track_count']
            ]);
        }, $results);
    }

    public function findByStartYear(int $startYear): ?Decade
    {
        $sql = "
            SELECT 
                FLOOR(a.album_year/10)*10 as decade_start,
                COUNT(DISTINCT a.album_id) as album_count,
                COUNT(DISTINCT a.album_artist) as artist_count,
                COUNT(DISTINCT s.song_id) as track_count
            FROM albums a
            LEFT JOIN songs s ON a.album_id = s.album_id
            WHERE a.is_deleted = 0 
              AND a.album_year >= :startYear
              AND a.album_year <= :endYear
            GROUP BY decade_start
            LIMIT 1
        ";

        $result = $this->executeQuerySingle($sql, [
            ':startYear' => $startYear,
            ':endYear' => $startYear + 9
        ]);

        if ($result === null || $result === []) {
            return null;
        }

        return new Decade([
            'decade' => $startYear . 'er',
            'start_year' => $startYear,
            'end_year' => $startYear + 9,
            'album_count' => (int)$result['album_count'],
            'artist_count' => (int)$result['artist_count'],
            'track_count' => (int)$result['track_count']
        ]);
    }

    /**
     * @return array{min_year: int, max_year: int}
     */
    public function getYearRange(): array
    {
        $sql = "
            SELECT 
                MIN(album_year) as min_year,
                MAX(album_year) as max_year
            FROM albums
            WHERE is_deleted = 0 
              AND album_year > 0
        ";

        $result = $this->executeQuerySingle($sql, []);

        return [
            'min_year' => (int)($result['min_year'] ?? 0),
            'max_year' => (int)($result['max_year'] ?? 0)
        ];
    }
}
