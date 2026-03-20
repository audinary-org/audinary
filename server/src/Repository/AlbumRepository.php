<?php

namespace App\Repository;

use App\Models\Album;
use Exception;

class AlbumRepository extends BaseRepository
{
    public function __construct(?string $userId = null)
    {
        parent::__construct($userId);
    }
    /**
     * @param array<string, mixed> $params
     * @return array<int, Album>
     */
    public function findAll(array $params = [], bool $favoriteOnly = false): array
    {
        $pagination = $this->getPaginationParams($params);
        $joinType = $favoriteOnly ? 'JOIN' : 'LEFT JOIN';

        $whereClause = " WHERE a.is_deleted = 0 ";
        $bindParams = [
            ':uid' => $this->userId,
            ':limit' => $pagination['limit'],
            ':start' => $pagination['start']
        ];

        // Artist filter
        if (!empty($params['artist'])) {
            $artistParam = trim($params['artist']);

            // Search both in album_artist field and via artist_id join
            $whereClause .= " AND (a.album_artist = :exactArtist"
                . " OR a.album_artist ILIKE :artist"
                . " OR a.artist_id IN (SELECT artist_id FROM artists"
                . " WHERE artist_name = :exactArtistName"
                . " OR artist_name ILIKE :artistName)";
            $bindParams[':exactArtist'] = $artistParam;
            $bindParams[':artist'] = '%' . $artistParam . '%';
            $bindParams[':exactArtistName'] = $artistParam;
            $bindParams[':artistName'] = '%' . $artistParam . '%';

            // If the artist contains commas, also try variations and individual artists
            if (strpos($artistParam, ',') !== false) {
                // Try variations with different spacing in album_artist
                $whereClause .= " OR a.album_artist ILIKE :artistNoSpaces OR a.album_artist ILIKE :artistExtraSpaces";
                $bindParams[':artistNoSpaces'] = '%' . str_replace(', ', ',', $artistParam) . '%';
                $bindParams[':artistExtraSpaces'] = '%' . str_replace(',', ', ', str_replace(', ', ',', $artistParam)) . '%';

                // Also try individual artists from the comma-separated list in album_artist
                $individualArtists = array_map('trim', explode(',', $artistParam));
                foreach ($individualArtists as $index => $individualArtist) {
                    if (!empty($individualArtist)) {
                        $paramKey = ':individualArtist' . $index;
                        $whereClause .= " OR a.album_artist ILIKE $paramKey";
                        $bindParams[$paramKey] = '%' . $individualArtist . '%';
                    }
                }
            }

            $whereClause .= ")";
        }

        // Genre filter
        if (!empty($params['genre'])) {
            $whereClause .= " AND (a.album_genre = :exactGenre"
                . " OR a.album_genre ILIKE :startGenre"
                . " OR a.album_genre ILIKE :middleGenre"
                . " OR a.album_genre ILIKE :endGenre) ";
            $bindParams[':exactGenre'] = $params['genre'];
            $bindParams[':startGenre'] = $params['genre'] . ',%';
            $bindParams[':middleGenre'] = '%, ' . $params['genre'] . ',%';
            $bindParams[':endGenre'] = '%, ' . $params['genre'];
        }

        // Decade filter
        if (!empty($params['decade'])) {
            $startYear = (int)$params['decade'];
            $endYear = $startYear + 9;
            $whereClause .= " AND a.album_year BETWEEN :startYear AND :endYear ";
            $bindParams[':startYear'] = $startYear;
            $bindParams[':endYear'] = $endYear;
        }

        // Search filter
        if (!empty($params['search'])) {
            $searchTerm = '%' . $params['search'] . '%';
            $whereClause .= " AND (a.album_name ILIKE :search OR a.album_artist ILIKE :searchArtist) ";
            $bindParams[':search'] = $searchTerm;
            $bindParams[':searchArtist'] = $searchTerm;
        }

        $orderClause = $this->buildOrderClause($params, true);

        $needsPlayHistory = !empty($params['sort']) && $params['sort'] === 'last_played';

        if ($needsPlayHistory) {
            $sql = "
                SELECT
                    a.album_id,
                    a.album_name,
                    a.album_year,
                    a.album_genre,
                    a.album_duration,
                    a.total_tracks,
                    a.artist_id,
                    a.album_artist,
                    a.filetype,
                    a.cover_gradient,
                    MAX(ph.played_at) as last_played,
                    MAX(CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END) AS is_favorite
                FROM albums a
                JOIN songs s ON s.album_id = a.album_id
                JOIN play_history ph ON ph.song_id = s.song_id AND ph.user_id = :uid_history
                {$joinType} favorites f
                    ON f.user_id = :uid
                    AND f.favorite_type = 'album'
                    AND f.album_id = a.album_id
                {$whereClause}
                    AND s.is_deleted = 0
                GROUP BY a.album_id, a.album_name, a.album_year, a.album_genre, a.album_duration, a.artist_id, a.album_artist, a.filetype, a.cover_gradient
                {$orderClause}
                LIMIT :limit OFFSET :start
            ";
            $bindParams[':uid_history'] = $this->userId;
        } else {
            $sql = "
                SELECT
                    a.album_id,
                    a.album_name,
                    a.album_year,
                    a.album_genre,
                    a.album_duration,
                    a.total_tracks,
                    a.artist_id,
                    a.album_artist,
                    a.filetype,
                    a.cover_gradient,
                    CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
                FROM albums a
                {$joinType} favorites f
                    ON f.user_id = :uid
                    AND f.favorite_type = 'album'
                    AND f.album_id = a.album_id
                {$whereClause}
                {$orderClause}
                LIMIT :limit OFFSET :start
            ";
        }

        $results = $this->executeQuery($sql, $bindParams);
        return array_map(fn($row): \App\Models\Album => new Album($row), $results);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, Album>
     */
    public function findByGenre(string $genre, array $params = []): array
    {
        $pagination = $this->getPaginationParams($params);

        $sql = "
            SELECT DISTINCT
                a.album_id,
                a.album_name,
                a.album_year,
                a.album_genre,
                a.album_duration,
                a.total_tracks,
                a.artist_id,
                a.album_artist,
                a.filetype,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM albums a
            JOIN songs s ON s.album_id = a.album_id
            LEFT JOIN favorites f
                ON f.user_id = :uid
                AND f.favorite_type = 'album'
                AND f.album_id = a.album_id
            WHERE a.is_deleted = 0
              AND s.is_deleted = 0
              AND (
                  s.genre = :exactGenre
                  OR s.genre ILIKE :startGenre
                  OR s.genre ILIKE :middleGenre
                  OR s.genre ILIKE :endGenre
              )
            ORDER BY a.album_artist, a.album_name
            LIMIT :limit OFFSET :start
        ";

        $bindParams = [
            ':uid' => $this->userId,
            ':exactGenre' => $genre,
            ':startGenre' => $genre . ',%',
            ':middleGenre' => '%, ' . $genre . ',%',
            ':endGenre' => '%, ' . $genre,
            ':limit' => $pagination['limit'],
            ':start' => $pagination['start']
        ];

        $results = $this->executeQuery($sql, $bindParams);
        return array_map(fn($row): \App\Models\Album => new Album($row), $results);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, Album>
     */
    public function findByDecade(int $startYear, array $params = []): array
    {
        $pagination = $this->getPaginationParams($params);
        $endYear = $startYear + 9;

        $sql = "
            SELECT DISTINCT
                a.album_id,
                a.album_name,
                a.album_year,
                a.album_genre,
                a.album_duration,
                a.total_tracks,
                a.artist_id,
                a.album_artist,
                a.filetype,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM albums a
            LEFT JOIN favorites f
                ON f.user_id = :uid
                AND f.favorite_type = 'album'
                AND f.album_id = a.album_id
            WHERE a.is_deleted = 0
              AND a.album_year BETWEEN :startYear AND :endYear
            ORDER BY a.album_year DESC, a.album_artist, a.album_name
            LIMIT :limit OFFSET :start
        ";

        $bindParams = [
            ':uid' => $this->userId,
            ':startYear' => $startYear,
            ':endYear' => $endYear,
            ':limit' => $pagination['limit'],
            ':start' => $pagination['start']
        ];

        $results = $this->executeQuery($sql, $bindParams);
        return array_map(fn($row): \App\Models\Album => new Album($row), $results);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchQuick(string $like, int $limit): array
    {

        $sql = "
            SELECT DISTINCT
                a.album_id,
                a.album_name,
                a.album_artist,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM albums a
            LEFT JOIN favorites f
                ON f.user_id = :uid
                AND f.favorite_type = 'album'
                AND f.album_id = a.album_id
            WHERE a.is_deleted = 0
                AND (a.album_name ILIKE :like1 OR a.album_artist ILIKE :like2)
            ORDER BY a.album_name
            LIMIT :limit
        ";

        $bindParams = [
            ':uid' => $this->userId,
            ':like1' => $like,
            ':like2' => $like,
            ':limit' => $limit
        ];
        $results = $this->executeQuery($sql, $bindParams);
        return array_map(function (array $row): array {
            $row['coverArtUrl'] = '/img/userdata/albums/' . $row['album_id'] . '.webp';
            return $row;
        }, $results);
    }

    public function findById(string $albumId): ?Album
    {
        $this->validateUuid($albumId, 'album_id');

        $sql = "
            SELECT
                a.album_id,
                a.album_name,
                a.album_year,
                a.album_genre,
                a.album_duration,
                a.total_tracks,
                a.artist_id,
                a.album_artist,
                a.filetype,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM albums a
            LEFT JOIN favorites f
                ON f.user_id = :uid
                AND f.favorite_type = 'album'
                AND f.album_id = a.album_id
            WHERE a.album_id = :albumId
              AND a.is_deleted = 0
            LIMIT 1
        ";

        $bindParams = [
            ':uid' => $this->userId,
            ':albumId' => $albumId
        ];

        $result = $this->executeQuerySingle($sql, $bindParams);
        return $result !== null && $result !== [] ? new Album($result) : null;
    }

    public function exists(string $albumId): bool
    {
        $this->validateUuid($albumId, 'album_id');

        $sql = "SELECT 1 FROM albums WHERE album_id = :albumId AND is_deleted = 0 LIMIT 1";
        $result = $this->executeQuerySingle($sql, [':albumId' => $albumId]);
        return $result !== null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, int>
     */
    private function getPaginationParams(array $params): array
    {
        $start = isset($params['start'])
            ? filter_var(
                $params['start'],
                FILTER_VALIDATE_INT,
                ['options' => ['default' => 0, 'min_range' => 0]]
            )
            : 0;
        $limit = isset($params['limit'])
            ? filter_var(
                $params['limit'],
                FILTER_VALIDATE_INT,
                ['options' => ['default' => 50, 'min_range' => 1, 'max_range' => 500]]
            )
            : 50;

        return [
            'start' => $start,
            'limit' => $limit
        ];
    }
}
