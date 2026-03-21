<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;
use PDO;

final class SmartPlaylistRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getPDO();
    }

    /**
     * Resolve smart playlist rules to actual songs.
     *
     * @param array<string, mixed> $rules
     * @return array<int, array<string, mixed>>
     */
    public function resolveSongs(
        array $rules,
        ?string $sortBy,
        string $sortDirection,
        ?int $limit,
        ?string $userId
    ): array {
        $bindParams = [];
        $whereClause = $this->buildSmartWhereClause($rules, $bindParams, $userId);
        $orderClause = $this->buildSmartOrderClause($sortBy, $sortDirection);
        $maxLimit = 250;
        $effectiveLimit = $limit !== null && $limit > 0 ? min($limit, $maxLimit) : $maxLimit;
        $limitClause = " LIMIT :smart_limit";

        $sql = "
            SELECT
                s.song_id,
                s.file_path as file,
                s.title,
                s.bitrate,
                s.track_number,
                s.duration,
                s.genre,
                s.year,
                s.disc_number,
                s.artist,
                s.size,
                s.last_mtime,
                s.is_deleted,
                s.created_at,
                s.last_played as \"lastPlayed\",
                s.filetype,
                a.album_id,
                a.album_name as album,
                a.album_year,
                a.album_genre,
                a.album_duration,
                CASE WHEN a.cover_path IS NOT NULL
                    THEN '/img/userdata/albums/' || a.album_id || '.webp'
                    ELSE NULL END as \"coverArtUrl\",
                CASE WHEN f.favorite_id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
            FROM songs s
            JOIN albums a ON s.album_id = a.album_id
            LEFT JOIN favorites f ON s.song_id = f.song_id
                AND f.favorite_type = 'song'
                AND f.user_id = :fav_user_id
            WHERE s.is_deleted = 0 AND a.is_deleted = 0
            {$whereClause}
            {$orderClause}
            {$limitClause}
        ";

        $bindParams[':fav_user_id'] = $userId;

        $stmt = $this->db->prepare($sql);

        foreach ($bindParams as $key => $value) {
            if ($key === ':smart_limit') {
                continue; // handled separately
            }
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } elseif (is_bool($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_BOOL);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->bindValue(':smart_limit', $effectiveLimit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get song count and total duration for a smart playlist.
     *
     * @param array<string, mixed> $rules
     * @return array{song_count: int, duration: int}
     */
    public function getSmartPlaylistStats(array $rules, ?string $userId): array
    {
        $bindParams = [];
        $whereClause = $this->buildSmartWhereClause($rules, $bindParams, $userId);

        $sql = "
            SELECT
                COUNT(*) as song_count,
                COALESCE(SUM(s.duration), 0) as duration
            FROM songs s
            JOIN albums a ON s.album_id = a.album_id
            LEFT JOIN favorites f ON s.song_id = f.song_id
                AND f.favorite_type = 'song'
                AND f.user_id = :fav_user_id
            WHERE s.is_deleted = 0 AND a.is_deleted = 0
            {$whereClause}
        ";

        $bindParams[':fav_user_id'] = $userId;

        $stmt = $this->db->prepare($sql);

        foreach ($bindParams as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } elseif (is_bool($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_BOOL);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'song_count' => (int) ($row['song_count'] ?? 0),
            'duration' => (int) ($row['duration'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $rules
     * @param array<string, mixed> &$bindParams
     */
    private function buildSmartWhereClause(array $rules, array &$bindParams, ?string $userId): string
    {
        $conditions = $rules['conditions'] ?? [];
        if (empty($conditions)) {
            return '';
        }

        $match = ($rules['match'] ?? 'all') === 'any' ? ' OR ' : ' AND ';

        $clauses = [];
        foreach ($conditions as $i => $condition) {
            $clause = $this->buildConditionClause($condition, $i, $bindParams, $userId);
            if ($clause !== null) {
                $clauses[] = $clause;
            }
        }

        if (empty($clauses)) {
            return '';
        }

        return ' AND (' . implode($match, $clauses) . ')';
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> &$bindParams
     */
    private function buildConditionClause(array $condition, int $index, array &$bindParams, ?string $userId): ?string
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        return match ($field) {
            'genre' => $this->buildGenreCondition($operator, $value, $index, $bindParams),
            'year' => $this->buildYearCondition($operator, $value, $index, $bindParams),
            'decade' => $this->buildDecadeCondition($value, $index, $bindParams),
            'artist' => $this->buildArtistCondition($operator, $value, $index, $bindParams),
            'is_favorite' => $this->buildFavoriteCondition($index, $bindParams),
            'last_played' => $this->buildLastPlayedCondition($operator, $value, $index, $bindParams),
            'duration' => $this->buildDurationCondition($operator, $value, $index, $bindParams),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> &$bindParams
     */
    private function buildGenreCondition(string $operator, mixed $value, int $index, array &$bindParams): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $param = ":genre_{$index}";

        if ($operator === 'contains') {
            $bindParams[$param] = '%' . $value . '%';
            return "(s.genre ILIKE {$param} OR a.album_genre ILIKE {$param})";
        }

        // equals - handle comma-separated genre fields
        $bindParams[$param] = $value;
        $paramLike = ":genre_like_{$index}";
        $bindParams[$paramLike] = '%' . $value . '%';
        return "(s.genre = {$param} OR s.genre ILIKE {$paramLike} OR a.album_genre = {$param} OR a.album_genre ILIKE {$paramLike})";
    }

    /**
     * @param array<string, mixed> &$bindParams
     */
    private function buildYearCondition(string $operator, mixed $value, int $index, array &$bindParams): ?string
    {
        return match ($operator) {
            'equals' => $this->buildSimpleComparison('s.year', '=', $value, "year_{$index}", $bindParams),
            'greater_than' => $this->buildSimpleComparison('s.year', '>', $value, "year_{$index}", $bindParams),
            'less_than' => $this->buildSimpleComparison('s.year', '<', $value, "year_{$index}", $bindParams),
            'between' => $this->buildBetweenCondition('s.year', $value, "year_{$index}", $bindParams),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> &$bindParams
     */
    private function buildDecadeCondition(mixed $value, int $index, array &$bindParams): ?string
    {
        $startYear = (int) $value;
        if ($startYear <= 0) {
            return null;
        }

        $endYear = $startYear + 9;
        $paramStart = ":decade_start_{$index}";
        $paramEnd = ":decade_end_{$index}";
        $bindParams[$paramStart] = $startYear;
        $bindParams[$paramEnd] = $endYear;

        return "(s.year >= {$paramStart} AND s.year <= {$paramEnd})";
    }

    /**
     * @param array<string, mixed> &$bindParams
     */
    private function buildArtistCondition(string $operator, mixed $value, int $index, array &$bindParams): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $param = ":artist_{$index}";

        if ($operator === 'equals') {
            $bindParams[$param] = $value;
            return "(s.artist = {$param} OR a.album_artist = {$param})";
        }

        // contains (default)
        $bindParams[$param] = '%' . $value . '%';
        return "(s.artist ILIKE {$param} OR a.album_artist ILIKE {$param})";
    }

    /**
     * @param array<string, mixed> &$bindParams
     */
    private function buildFavoriteCondition(int $index, array &$bindParams): string
    {
        return "f.favorite_id IS NOT NULL";
    }

    /**
     * @param array<string, mixed> &$bindParams
     */
    private function buildLastPlayedCondition(string $operator, mixed $value, int $index, array &$bindParams): ?string
    {
        if ($operator === 'within_days') {
            $days = (int) $value;
            if ($days <= 0) {
                return null;
            }
            $param = ":last_played_{$index}";
            $bindParams[$param] = $days;
            return "s.last_played >= NOW() - MAKE_INTERVAL(days => {$param})";
        }

        if ($operator === 'never') {
            return "s.last_played IS NULL";
        }

        return null;
    }

    /**
     * @param array<string, mixed> &$bindParams
     */
    private function buildDurationCondition(string $operator, mixed $value, int $index, array &$bindParams): ?string
    {
        return match ($operator) {
            'less_than' => $this->buildSimpleComparison('s.duration', '<', $value, "duration_{$index}", $bindParams),
            'greater_than' => $this->buildSimpleComparison('s.duration', '>', $value, "duration_{$index}", $bindParams),
            'between' => $this->buildBetweenCondition('s.duration', $value, "duration_{$index}", $bindParams),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> &$bindParams
     */
    private function buildSimpleComparison(string $column, string $op, mixed $value, string $paramName, array &$bindParams): string
    {
        $intValue = (int) $value;
        $param = ":{$paramName}";
        $bindParams[$param] = $intValue;
        return "({$column} {$op} {$param})";
    }

    /**
     * @param array<string, mixed> &$bindParams
     */
    private function buildBetweenCondition(string $column, mixed $value, string $paramName, array &$bindParams): ?string
    {
        if (!is_array($value) || count($value) < 2) {
            return null;
        }

        $paramMin = ":{$paramName}_min";
        $paramMax = ":{$paramName}_max";
        $bindParams[$paramMin] = (int) $value[0];
        $bindParams[$paramMax] = (int) $value[1];

        return "({$column} >= {$paramMin} AND {$column} <= {$paramMax})";
    }

    private function buildSmartOrderClause(?string $sortBy, string $sortDirection): string
    {
        $dir = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';

        return match ($sortBy) {
            'title' => " ORDER BY s.title {$dir}",
            'artist' => " ORDER BY s.artist {$dir}, a.album_name ASC, s.disc_number ASC, s.track_number ASC",
            'year' => " ORDER BY s.year {$dir}, s.artist ASC, a.album_name ASC",
            'album' => " ORDER BY a.album_name {$dir}, s.disc_number ASC, s.track_number ASC",
            'added' => " ORDER BY s.created_at {$dir}",
            'last_played' => " ORDER BY s.last_played {$dir} NULLS LAST",
            'duration' => " ORDER BY s.duration {$dir}",
            'random' => " ORDER BY RANDOM()",
            default => " ORDER BY s.artist ASC, a.album_name ASC, s.disc_number ASC, s.track_number ASC",
        };
    }
}
