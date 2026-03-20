<?php

namespace App\Repository;

use App\Models\Genre;

class GenreRepository extends BaseRepository
{
    public function __construct(?string $userId = null)
    {
        parent::__construct($userId);
    }
    /** @return array<int, Genre> */
    public function findAll(): array
    {
        $sql = "
            SELECT 
                s.genre,
                COUNT(DISTINCT a.album_id) as album_count,
                COUNT(DISTINCT a.album_artist) as artist_count,
                COUNT(DISTINCT s.song_id) as track_count
            FROM songs s
            JOIN albums a ON s.album_id = a.album_id
            WHERE s.is_deleted = 0 
              AND a.is_deleted = 0
              AND s.genre != ''
            GROUP BY s.genre
            ORDER BY s.genre ASC
        ";

        $results = $this->executeQuery($sql, []);
        return $this->processGenres($results);
    }

    /** @return array<int, array<string, mixed>> */
    public function search(string $query, int $limit = 20): array
    {
        $sql = "
            SELECT DISTINCT
                genre as full_genre,
                COUNT(*) as track_count
            FROM songs
            WHERE is_deleted = 0
                AND genre ILIKE :like
                AND genre != ''
            GROUP BY genre
            ORDER BY track_count DESC
            LIMIT :max_raw_results
        ";

        $bindParams = [
            ':like' => '%' . $query . '%',
            ':max_raw_results' => $limit * 3
        ];

        $results = $this->executeQuery($sql, $bindParams);
        return $this->processGenreSearch($results, $query, $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function searchQuick(string $query, int $limit): array
    {
        $sql = "
            SELECT DISTINCT
                genre as full_genre,
                COUNT(*) as track_count
            FROM songs
            WHERE is_deleted = 0
                AND genre ILIKE :like
                AND genre != ''
            GROUP BY genre
            ORDER BY track_count DESC
            LIMIT :max_raw_results
        ";

        $bindParams = [
            ':like' => '%' . $query . '%',
            ':max_raw_results' => $limit * 3
        ];

        $results = $this->executeQuery($sql, $bindParams);
        return $this->processGenreSearch($results, $query, $limit);
    }

    /**
     * @param array<int, array<string, mixed>> $rawGenres
     * @return array<int, Genre>
     */
    private function processGenres(array $rawGenres): array
    {
        $genreStats = [];

        foreach ($rawGenres as $row) {
            $genreString = trim($row['genre']);

            if ($genreString === '' || $genreString === '0') {
                continue;
            }

            $genreParts = explode(',', $genreString);

            foreach ($genreParts as $part) {
                $genre = trim($part);

                if ($genre === '' || $genre === '0') {
                    continue;
                }

                if (!isset($genreStats[$genre])) {
                    $genreStats[$genre] = [
                        'genre' => $genre,
                        'album_count' => 0,
                        'artist_count' => 0,
                        'track_count' => 0
                    ];
                }

                $genreStats[$genre]['album_count'] += (int)$row['album_count'];
                $genreStats[$genre]['artist_count'] += (int)$row['artist_count'];
                $genreStats[$genre]['track_count'] += (int)$row['track_count'];
            }
        }

        ksort($genreStats);

        $processedGenres = [];
        foreach ($genreStats as $genre) {
            $processedGenres[] = new Genre($genre);
        }

        return $processedGenres;
    }

    /**
     * @param array<int, array<string, mixed>> $rawGenres
     * @return array<int, array<string, mixed>>
     */
    private function processGenreSearch(array $rawGenres, string $query, int $limit): array
    {
        $genreStats = [];

        foreach ($rawGenres as $row) {
            $genreString = trim($row['full_genre']);

            if ($genreString === '' || $genreString === '0') {
                continue;
            }

            $genreParts = explode(',', $genreString);

            foreach ($genreParts as $part) {
                $genre = trim($part);

                if ($genre === '' || $genre === '0') {
                    continue;
                }

                if (stripos($genre, $query) === false) {
                    continue;
                }

                if (!isset($genreStats[$genre])) {
                    $genreStats[$genre] = [
                        'name' => $genre,
                        'track_count' => 0
                    ];
                }

                $genreStats[$genre]['track_count'] += (int)$row['track_count'];
            }
        }

        uasort($genreStats, fn($a, $b) => $b['track_count'] - $a['track_count']);

        $processedGenres = [];
        $count = 0;
        foreach ($genreStats as $genre) {
            $processedGenres[] = $genre;
            $count++;
            if ($count >= $limit) {
                break;
            }
        }

        return $processedGenres;
    }
}
