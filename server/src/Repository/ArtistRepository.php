<?php

namespace App\Repository;

use App\Models\Artist;

class ArtistRepository extends BaseRepository
{
    public function __construct(?string $userId = null)
    {
        parent::__construct($userId);
    }
    /**
     * @param array<string, mixed> $params
     * @return array<int, Artist>
     */
    public function findAll(array $params = [], bool $favoriteOnly = false): array
    {
        $pagination = $this->getPaginationParams($params);
        $joinType = $favoriteOnly ? 'JOIN' : 'LEFT JOIN';

        $whereClause = " WHERE ar.is_deleted = 0 AND a.is_deleted = 0 AND s.is_deleted = 0 ";
        $bindParams = [
            ':uid' => $this->userId,
            ':limit' => $pagination['limit'],
            ':start' => $pagination['start']
        ];

        // Artist name filter
        if (!empty($params['artist'])) {
            $whereClause .= " AND ar.artist_name ILIKE :artist ";
            $bindParams[':artist'] = '%' . $params['artist'] . '%';
        }

        // Genre filter (through albums and songs)
        if (!empty($params['genre'])) {
            $whereClause .= " AND (a.album_genre = :exactGenre"
                . " OR a.album_genre ILIKE :startGenre"
                . " OR a.album_genre ILIKE :middleGenre"
                . " OR a.album_genre ILIKE :endGenre"
                . " OR s.genre = :songExactGenre"
                . " OR s.genre ILIKE :songStartGenre"
                . " OR s.genre ILIKE :songMiddleGenre"
                . " OR s.genre ILIKE :songEndGenre) ";
            $bindParams[':exactGenre'] = $params['genre'];
            $bindParams[':startGenre'] = $params['genre'] . ',%';
            $bindParams[':middleGenre'] = '%, ' . $params['genre'] . ',%';
            $bindParams[':endGenre'] = '%, ' . $params['genre'];
            $bindParams[':songExactGenre'] = $params['genre'];
            $bindParams[':songStartGenre'] = $params['genre'] . ',%';
            $bindParams[':songMiddleGenre'] = '%, ' . $params['genre'] . ',%';
            $bindParams[':songEndGenre'] = '%, ' . $params['genre'];
        }

        // Decade filter (through albums and songs)
        if (!empty($params['decade'])) {
            $startYear = (int)$params['decade'];
            $endYear = $startYear + 9;
            $whereClause .= " AND (a.album_year BETWEEN :startYear AND :endYear OR s.year BETWEEN :songStartYear AND :songEndYear) ";
            $bindParams[':startYear'] = $startYear;
            $bindParams[':endYear'] = $endYear;
            $bindParams[':songStartYear'] = $startYear;
            $bindParams[':songEndYear'] = $endYear;
        }

        // Search filter
        if (!empty($params['search'])) {
            $searchTerm = '%' . $params['search'] . '%';
            $whereClause .= " AND ar.artist_name ILIKE :search ";
            $bindParams[':search'] = $searchTerm;
        }

        $orderClause = $this->buildOrderClause($params, false, true);

        $needsPlayCount = !empty($params['sort']) && $params['sort'] === 'play_count';

        if ($needsPlayCount) {
            $sql = "
                SELECT
                    ar.artist_id,
                    ar.artist_name,
                    ar.artist_gradient,
                    COUNT(DISTINCT a.album_id) AS \"albumCount\",
                    COUNT(s.song_id) AS \"songCount\",
                    MIN(a.album_year) AS \"firstYear\",
                    COALESCE(SUM(ph.play_count), 0) AS play_count,
                    MAX(CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END) AS is_favorite
                FROM artists ar
                JOIN albums a ON a.artist_id = ar.artist_id
                JOIN songs s ON s.album_id = a.album_id
                LEFT JOIN play_history ph ON ph.song_id = s.song_id AND ph.user_id = :uid_history
                {$joinType} favorites f
                    ON f.user_id = :uid
                    AND f.favorite_type = 'artist'
                    AND f.artist_id = ar.artist_id
                {$whereClause}
                GROUP BY ar.artist_id, ar.artist_name, ar.artist_gradient
                {$orderClause}
                LIMIT :limit OFFSET :start
            ";
            $bindParams[':uid_history'] = $this->userId;
        } else {
            $sql = "
                SELECT
                    ar.artist_id,
                    ar.artist_name,
                    ar.artist_gradient,
                    COUNT(DISTINCT a.album_id) AS \"albumCount\",
                    COUNT(s.song_id) AS \"songCount\",
                    MIN(a.album_year) AS \"firstYear\",
                    MAX(CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END) AS is_favorite
                FROM artists ar
                JOIN albums a ON a.artist_id = ar.artist_id
                JOIN songs s ON s.album_id = a.album_id
                {$joinType} favorites f
                    ON f.user_id = :uid
                    AND f.favorite_type = 'artist'
                    AND f.artist_id = ar.artist_id
                {$whereClause}
                GROUP BY ar.artist_id, ar.artist_name, ar.artist_gradient
                {$orderClause}
                LIMIT :limit OFFSET :start
            ";
        }

        $results = $this->executeQuery($sql, $bindParams);
        return array_map(fn($row): \App\Models\Artist => new Artist($row), $results);
    }

    /**
     * @return array<int, Artist>
     */
    public function searchQuick(string $like, int $limit): array
    {
        $sql = "
            SELECT DISTINCT
                ar.artist_id,
                ar.artist_name as artistName,
                COUNT(DISTINCT a.album_id) as album_count,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM artists ar
            LEFT JOIN albums a ON ar.artist_id = a.artist_id AND a.is_deleted = 0
            LEFT JOIN favorites f
                ON f.user_id = :uid
                AND f.favorite_type = 'artist'
                AND f.artist_id = ar.artist_id
            WHERE ar.is_deleted = 0
                AND ar.artist_name ILIKE :like
            GROUP BY ar.artist_id, ar.artist_name, f.user_id
            ORDER BY ar.artist_name
            LIMIT :limit
        ";

        $bindParams = [
            ':uid' => $this->userId,
            ':like' => $like,
            ':limit' => $limit
        ];

        $results = $this->executeQuery($sql, $bindParams);
        return array_map(fn($row): \App\Models\Artist => new Artist($row), $results);
    }

    public function findById(string $artistId): ?Artist
    {
        $this->validateUuid($artistId, 'artist_id');

        $sql = "
            SELECT 
                ar.artist_id,
                ar.artist_name,
                COUNT(DISTINCT a.album_id) AS \"albumCount\",
                COUNT(DISTINCT s.song_id) AS \"songCount\",
                MIN(a.album_year) AS \"firstYear\",
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM artists ar
            LEFT JOIN albums a ON a.artist_id = ar.artist_id AND a.is_deleted = 0
            LEFT JOIN songs s ON s.album_id = a.album_id AND s.is_deleted = 0
            LEFT JOIN favorites f
                ON f.user_id = :uid
                AND f.favorite_type = 'artist'
                AND f.artist_id = ar.artist_id
            WHERE ar.artist_id = :artistId
              AND ar.is_deleted = 0
            GROUP BY ar.artist_id, ar.artist_name, f.user_id
            LIMIT 1
        ";

        $bindParams = [
            ':uid' => $this->userId,
            ':artistId' => $artistId
        ];

        $result = $this->executeQuerySingle($sql, $bindParams);
        return $result !== null && $result !== [] ? new Artist($result) : null;
    }

    public function exists(string $artistId): bool
    {
        $this->validateUuid($artistId, 'artist_id');

        $sql = "SELECT 1 FROM artists WHERE artist_id = :artistId AND is_deleted = 0 LIMIT 1";
        $result = $this->executeQuerySingle($sql, [':artistId' => $artistId]);
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
