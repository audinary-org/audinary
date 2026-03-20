<?php

namespace App\Repository;

use App\Models\Song;
use InvalidArgumentException;

class SongRepository extends BaseRepository
{
    public function __construct(?string $userId = null)
    {
        parent::__construct($userId);
    }
    /**
     * @param array<string, mixed> $params
     * @return array<int, Song>
     */
    public function findAll(array $params = [], bool $favoriteOnly = false): array
    {
        $pagination = $this->getPaginationParams($params);
        $joinType = $favoriteOnly ? 'JOIN' : 'LEFT JOIN';

        $whereClause = " WHERE s.is_deleted = 0 AND a.is_deleted = 0 ";
        $bindParams = [
            ':limit' => $pagination['limit'],
            ':start' => $pagination['start']
        ];

        if ($this->userId !== null && $this->userId !== '' && $this->userId !== '0') {
            $bindParams[':uid'] = $this->userId;
        }

        $whereClause .= $this->buildWhereClause($params, $bindParams);
        $orderClause = $this->buildOrderClause($params);

        $needsPlayHistory = !empty($params['sort']) && $params['sort'] === 'last_played';

        if ($needsPlayHistory) {
            $sql = "
                SELECT
                    s.song_id,
                    s.file_path,
                    s.title,
                    s.bitrate,
                    s.track_number,
                    s.duration,
                    s.genre,
                    s.year,
                    s.disc_number,
                    s.filetype,
                    s.artist AS track_artist_name,
                    a.album_artist AS album_artist_name,
                    a.album_id,
                    a.album_name,
                    a.album_year,
                    a.album_genre,
                    a.album_duration,
                    a.cover_gradient,
                    ph_latest.played_at as last_played,
                    CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
                FROM songs s
                JOIN albums a ON s.album_id = a.album_id
                JOIN (
                    SELECT song_id, MAX(played_at) as played_at
                    FROM play_history
                    WHERE user_id = :uid_history
                    GROUP BY song_id
                ) ph_latest ON ph_latest.song_id = s.song_id
                {$joinType} favorites f
                    ON f.user_id = :uid
                    AND f.favorite_type = 'song'
                    AND f.song_id = s.song_id
                {$whereClause}
                {$orderClause}
                LIMIT :limit OFFSET :start
            ";
            $bindParams[':uid_history'] = $this->userId;
        } else {
            $sql = "
                SELECT
                    s.song_id,
                    s.file_path,
                    s.title,
                    s.bitrate,
                    s.track_number,
                    s.duration,
                    s.genre,
                    s.year,
                    s.disc_number,
                    s.filetype,
                    s.artist AS track_artist_name,
                    a.album_artist AS album_artist_name,
                    a.album_id,
                    a.album_name,
                    a.album_year,
                    a.album_genre,
                    a.album_duration,
                    a.cover_gradient,
                    CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
                FROM songs s
                JOIN albums a ON s.album_id = a.album_id
                {$joinType} favorites f
                    ON f.user_id = :uid
                    AND f.favorite_type = 'song'
                    AND f.song_id = s.song_id
                {$whereClause}
                {$orderClause}
                LIMIT :limit OFFSET :start
            ";
        }

        $results = $this->executeQuery($sql, $bindParams);
        return array_map(fn($row): \App\Models\Song => new Song($row), $results);
    }

    /** @return array<int, Song> */
    public function findByAlbumId(string $albumId): array
    {
        $this->validateUuid($albumId, 'album_id');

        $sql = "
            SELECT
                s.song_id,
                s.file_path,
                s.title,
                s.bitrate,
                s.track_number,
                s.duration,
                s.genre,
                s.year,
                s.disc_number,
                s.filetype,
                s.artist AS track_artist_name,
                a.album_artist AS album_artist_name,
                a.album_id,
                a.album_name,
                a.album_year,
                a.album_genre,
                a.album_duration,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM songs s
            JOIN albums a ON s.album_id = a.album_id
            LEFT JOIN favorites f
                ON f.user_id = :uid
                AND f.favorite_type = 'song'
                AND f.song_id = s.song_id
            WHERE s.album_id = :albumId
              AND s.is_deleted = 0
              AND a.is_deleted = 0
            ORDER BY s.disc_number, s.track_number, s.title
        ";

        $bindParams = [
            ':uid' => $this->userId,
            ':albumId' => $albumId
        ];

        $results = $this->executeQuery($sql, $bindParams);
        return array_map(fn($row): \App\Models\Song => new Song($row), $results);
    }

    /** @return array<int, Song> */
    public function findByArtistIdentifier(string $artistIdentifier, bool $random = false, int $maxLimit = 250): array
    {
        if (in_array(trim($artistIdentifier), ['', '0'], true)) {
            throw new InvalidArgumentException('Artist identifier cannot be empty');
        }

        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $artistIdentifier);

        $sql = "
            SELECT
                s.song_id,
                s.file_path,
                s.title,
                s.bitrate,
                s.track_number,
                s.duration,
                s.genre,
                s.year,
                s.disc_number,
                s.filetype,
                s.artist AS track_artist_name,
                a.album_artist AS album_artist_name,
                a.album_id,
                a.album_name,
                a.album_year,
                a.album_genre,
                a.album_duration,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM songs s
            JOIN albums a ON s.album_id = a.album_id
            LEFT JOIN favorites f
                ON f.user_id = :uid
                AND f.favorite_type = 'song'
                AND f.song_id = s.song_id
            WHERE ";

        $bindParams = [':uid' => $this->userId, ':limit' => $maxLimit];

        if ($isUuid) {
            $sql .= "a.artist_id = :artist_id";
            $bindParams[':artist_id'] = $artistIdentifier;
        } else {
            $sql .= "(s.artist ILIKE :artist OR a.album_artist ILIKE :artist)";
            $bindParams[':artist'] = $artistIdentifier;
        }

        $sql .= " AND s.is_deleted = 0 AND a.is_deleted = 0 ";

        if ($random) {
            $sql .= " ORDER BY RANDOM() ";
        } else {
            $sql .= " ORDER BY a.album_year DESC, a.album_name, s.disc_number, s.track_number, s.title ";
        }

        $sql .= " LIMIT :limit ";

        $results = $this->executeQuery($sql, $bindParams);
        return array_map(fn($row): \App\Models\Song => new Song($row), $results);
    }

    /** @return array<int, array<string, mixed>> */
    public function searchQuick(string $like, int $limit): array
    {
        $sql = "
            SELECT
                s.song_id,
                s.title,
                s.artist,
                s.duration,
                s.year,
                s.genre,
                s.filetype,
                a.album_id,
                a.album_name,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM songs s
            JOIN albums a ON s.album_id = a.album_id
            LEFT JOIN favorites f
                ON f.user_id = :uid
                AND f.favorite_type = 'song'
                AND f.song_id = s.song_id
            WHERE s.is_deleted = 0
                AND a.is_deleted = 0
                AND (s.title ILIKE :like1 OR s.artist ILIKE :like2 OR a.album_name ILIKE :like3)
            ORDER BY s.title
            LIMIT :limit
        ";

        $bindParams = [
            ':uid' => $this->userId,
            ':like1' => $like,
            ':like2' => $like,
            ':like3' => $like,
            ':limit' => $limit
        ];

        $results = $this->executeQuery($sql, $bindParams);
        return array_map(function (array $row): array {
            $row['coverArtUrl'] = '/img/userdata/albums/' . $row['album_id'] . '.webp';
            return $row;
        }, $results);
    }

    public function findById(string $songId): ?Song
    {
        $this->validateUuid($songId, 'song_id');

        $sql = "
            SELECT
                s.song_id,
                s.file_path,
                s.title,
                s.bitrate,
                s.track_number,
                s.duration,
                s.genre,
                s.year,
                s.disc_number,
                s.filetype,
                s.artist AS track_artist_name,
                a.album_artist AS album_artist_name,
                a.album_id,
                a.album_name,
                a.album_year,
                a.album_genre,
                a.album_duration,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM songs s
            JOIN albums a ON s.album_id = a.album_id
            LEFT JOIN favorites f
                ON f.user_id = :uid
                AND f.favorite_type = 'song'
                AND f.song_id = s.song_id
            WHERE s.song_id = :songId
              AND s.is_deleted = 0
              AND a.is_deleted = 0
            LIMIT 1
        ";

        $bindParams = [
            ':uid' => $this->userId,
            ':songId' => $songId
        ];

        $result = $this->executeQuerySingle($sql, $bindParams);
        return $result !== null && $result !== [] ? new Song($result) : null;
    }

    public function exists(string $songId): bool
    {
        $this->validateUuid($songId, 'song_id');

        $sql = "SELECT 1 FROM songs WHERE song_id = :songId AND is_deleted = 0 LIMIT 1";
        $result = $this->executeQuerySingle($sql, [':songId' => $songId]);
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
