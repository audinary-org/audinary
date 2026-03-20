<?php

namespace App\Repository;

use App\Database\Connection;
use InvalidArgumentException;
use PDO;
use RuntimeException;

abstract class BaseRepository
{
    protected PDO $db;
    protected ?string $userId = null;

    public function __construct(?string $userId = null)
    {
        $this->db = Connection::getPDO();
        $this->userId = $userId;

        if ($userId !== null && in_array(trim($userId), ['', '0'], true)) {
            throw new InvalidArgumentException('User ID cannot be empty');
        }
    }

    protected function validateUuid(string $uuid, string $fieldName): string
    {
        if (in_array(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid), [0, false], true)) {
            throw new InvalidArgumentException("Invalid format for {$fieldName}");
        }
        return $uuid;
    }

    /**
     * Generate cryptographically secure UUID v4
     * @throws \Exception if random_bytes() fails
     */
    protected function generateUuid(): string
    {
        $data = random_bytes(16);

        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set variant to 10xx
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Build WHERE clause from search parameters
     * @param array<string, mixed> $params
     * @param array<string, mixed> $bindParams
     */
    protected function buildWhereClause(array $params, array &$bindParams): string
    {
        $whereClause = '';

        // Artist filter
        if (!empty($params['artist'])) {
            $whereClause .= " AND (s.artist ILIKE :artist OR a.album_artist ILIKE :artist) ";
            $bindParams[':artist'] = '%' . $params['artist'] . '%';
        }

        // Genre filter
        if (!empty($params['genre'])) {
            $whereClause .= " AND (s.genre = :exactGenre OR s.genre ILIKE :startGenre OR s.genre ILIKE :middleGenre OR s.genre ILIKE :endGenre) ";
            $bindParams[':exactGenre'] = $params['genre'];
            $bindParams[':startGenre'] = $params['genre'] . ',%';
            $bindParams[':middleGenre'] = '%, ' . $params['genre'] . ',%';
            $bindParams[':endGenre'] = '%, ' . $params['genre'];
        }

        // Decade filter
        if (!empty($params['decade'])) {
            $startYear = (int)$params['decade'];
            $endYear = $startYear + 9;
            $whereClause .= " AND s.year BETWEEN :startYear AND :endYear ";
            $bindParams[':startYear'] = $startYear;
            $bindParams[':endYear'] = $endYear;
        }

        // Search filter
        if (!empty($params['search'])) {
            $searchTerm = '%' . $params['search'] . '%';
            $whereClause .= " AND (s.title ILIKE :search OR s.artist ILIKE :searchArtist OR a.album_name ILIKE :searchAlbum) ";
            $bindParams[':search'] = $searchTerm;
            $bindParams[':searchArtist'] = $searchTerm;
            $bindParams[':searchAlbum'] = $searchTerm;
        }

        return $whereClause;
    }

    /**
     * Build ORDER BY clause from sort parameters
     * @param array<string, mixed> $params
     */
    protected function buildOrderClause(array $params, bool $isAlbum = false, bool $isArtist = false): string
    {
        $sortDirection = (!empty($params['sortDirection']) && $params['sortDirection'] === 'desc') ? 'DESC' : 'ASC';

        if ($isArtist) {
            $defaultOrder = " ORDER BY ar.artist_name ";
        } elseif ($isAlbum) {
            $defaultOrder = " ORDER BY a.album_artist, a.album_name ";
        } else {
            $defaultOrder = " ORDER BY a.album_artist, a.album_name, s.disc_number, s.track_number, s.title ";
        }

        if (empty($params['sort'])) {
            return $defaultOrder;
        }

        return match ($params['sort']) {
            'name' => $isArtist
                ? " ORDER BY ar.artist_name {$sortDirection} "
                : ($isAlbum
                    ? " ORDER BY a.album_name {$sortDirection} "
                    : " ORDER BY s.artist {$sortDirection}, s.title {$sortDirection} "),
            'artistAndAlbum' => " ORDER BY a.album_artist {$sortDirection}, a.album_name {$sortDirection}"
                . ($isAlbum ? "" : ", s.disc_number, s.track_number "),
            'album' => " ORDER BY a.album_name {$sortDirection}"
                . ($isAlbum ? "" : ", s.disc_number, s.track_number "),
            'year' => $isArtist
                ? " ORDER BY \"firstYear\" {$sortDirection}, ar.artist_name "
                : ($isAlbum
                    ? " ORDER BY a.album_year {$sortDirection}, a.album_name "
                    : " ORDER BY s.year {$sortDirection}, s.artist, s.title "),
            'added' => $isArtist
                ? " ORDER BY ar.created_at {$sortDirection} "
                : ($isAlbum
                    ? " ORDER BY a.created_at {$sortDirection} "
                    : " ORDER BY s.created_at {$sortDirection} "),
            'last_played' => " ORDER BY last_played {$sortDirection} ",
            'albumCount' => " ORDER BY \"albumCount\" {$sortDirection}, ar.artist_name ",
            'songCount' => " ORDER BY \"songCount\" {$sortDirection}, ar.artist_name ",
            'albums' => " ORDER BY \"albumCount\" {$sortDirection}, ar.artist_name ",
            'play_count' => " ORDER BY play_count {$sortDirection}, \"albumCount\" {$sortDirection}, ar.artist_name ",
            'favorites' => " ORDER BY CASE WHEN f.user_id IS NOT NULL THEN 0 ELSE 1 END, " .
                ($isArtist ? "ar.artist_name" : ($isAlbum ? "a.album_name" : "s.title")) . " {$sortDirection} ",
            default => $defaultOrder
        };
    }

    /**
     * Execute SQL query and return all results
     * @param array<string, mixed> $bindParams
     * @return array<int, array<string, mixed>>
     */
    protected function executeQuery(string $sql, array $bindParams): array
    {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $errorInfo = $this->db->errorInfo();
            throw new RuntimeException("Failed to prepare SQL statement: " . $errorInfo[2]);
        }

        foreach ($bindParams as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $result = $stmt->bindValue($key, $value, $paramType);
            if (!$result) {
                throw new RuntimeException("Failed to bind parameter $key");
            }
        }

        $result = $stmt->execute();
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Statement execution failed: " . $errorInfo[2]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute SQL query and return single result
     * @param array<string, mixed> $bindParams
     * @return array<string, mixed>|null
     */
    protected function executeQuerySingle(string $sql, array $bindParams): ?array
    {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $errorInfo = $this->db->errorInfo();
            throw new RuntimeException("Failed to prepare SQL statement: " . $errorInfo[2]);
        }
        foreach ($bindParams as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $result = $stmt->execute();
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Statement execution failed: " . $errorInfo[2]);
        }
        $fetchResult = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fetchResult !== false ? $fetchResult : null;
    }

    /**
     * Execute SQL statement (INSERT/UPDATE/DELETE)
     * @param array<string, mixed> $bindParams
     */
    protected function executeStatement(string $sql, array $bindParams): bool
    {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $errorInfo = $this->db->errorInfo();
            throw new RuntimeException("Failed to prepare SQL statement: " . $errorInfo[2]);
        }
        foreach ($bindParams as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        return $stmt->execute();
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        if (in_array(trim($userId), ['', '0'], true)) {
            throw new InvalidArgumentException('User ID cannot be empty');
        }
        $this->userId = $userId;
    }
}
