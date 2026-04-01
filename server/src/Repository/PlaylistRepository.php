<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Playlist;
use App\Database\Connection;
use Exception;
use PDO;
use InvalidArgumentException;

final class PlaylistRepository extends BaseRepository
{
    public function __construct(?string $userId = null)
    {
        parent::__construct($userId);
    }

    public function findById(string $id): ?Playlist
    {
        $stmt = $this->db->prepare('
            SELECT
                p.*,
                COALESCE(sc.cnt, 0) AS song_count,
                COALESCE(sc.dur, 0) AS duration
            FROM playlists p
            LEFT JOIN (
                SELECT ps.playlist_id,
                       COUNT(ps.song_id) AS cnt,
                       COALESCE(SUM(s.duration), 0) AS dur
                FROM playlist_songs ps
                JOIN songs s ON s.song_id = ps.song_id AND s.is_deleted = 0
                GROUP BY ps.playlist_id
            ) sc ON sc.playlist_id = p.id
            WHERE p.id = ?
        ');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new Playlist($data) : null;
    }

    /** @return array<int, Playlist> */
    public function findByUserId(string $userId, int $offset = 0, int $limit = 50): array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.*,
                COALESCE(sc.cnt, 0) AS song_count,
                COALESCE(sc.dur, 0) AS duration,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM playlists p
            LEFT JOIN (
                SELECT ps.playlist_id,
                       COUNT(ps.song_id) AS cnt,
                       COALESCE(SUM(s.duration), 0) AS dur
                FROM playlist_songs ps
                JOIN songs s ON s.song_id = ps.song_id AND s.is_deleted = 0
                GROUP BY ps.playlist_id
            ) sc ON sc.playlist_id = p.id
            LEFT JOIN favorites f
                ON f.user_id = :current_user_id
                AND f.favorite_type = \'playlist\'
                AND f.playlist_id = p.id
            WHERE p.user_id = :playlist_owner_id AND p.type != \'smart\'
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->execute([
            'current_user_id' => $this->userId,
            'playlist_owner_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): \App\Models\Playlist => new Playlist($data), $results);
    }

    /** @return array<int, Playlist> */
    public function findSharedWithUser(string $userId, int $offset = 0, int $limit = 50): array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.*,
                COALESCE(sc.cnt, 0) AS song_count,
                COALESCE(sc.dur, 0) AS duration,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM playlists p
            INNER JOIN playlist_permissions pp ON p.id = pp.playlist_id
            LEFT JOIN (
                SELECT ps.playlist_id,
                       COUNT(ps.song_id) AS cnt,
                       COALESCE(SUM(s.duration), 0) AS dur
                FROM playlist_songs ps
                JOIN songs s ON s.song_id = ps.song_id AND s.is_deleted = 0
                GROUP BY ps.playlist_id
            ) sc ON sc.playlist_id = p.id
            LEFT JOIN favorites f
                ON f.user_id = :current_user_id
                AND f.favorite_type = \'playlist\'
                AND f.playlist_id = p.id
            WHERE pp.user_id = :shared_user_id
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->execute([
            'current_user_id' => $this->userId,
            'shared_user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): \App\Models\Playlist => new Playlist($data), $results);
    }

    public function countByUserId(string $userId): int
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM playlists WHERE user_id = ? AND type != \'smart\'
        ');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, Playlist> */
    public function findAllSmart(): array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.*,
                0 AS song_count,
                0 AS duration,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM playlists p
            LEFT JOIN favorites f
                ON f.user_id = :current_user_id
                AND f.favorite_type = \'playlist\'
                AND f.playlist_id = p.id
            WHERE p.type = \'smart\'
            ORDER BY p.created_at DESC
        ');
        $stmt->execute([
            'current_user_id' => $this->userId,
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): \App\Models\Playlist => new Playlist($data), $results);
    }

    /** @return array<int, Playlist> */
    public function findAllSmartAdmin(): array
    {
        $stmt = $this->db->prepare('
            SELECT p.*, 0 AS song_count, 0 AS duration
            FROM playlists p
            WHERE p.type = \'smart\'
            ORDER BY p.created_at DESC
        ');
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): \App\Models\Playlist => new Playlist($data), $results);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Playlist
    {
        $playlistData = Playlist::createData($data);
        $type = $playlistData['type'] ?? 'user';

        if ($type === 'smart') {
            $stmt = $this->db->prepare('
                INSERT INTO playlists (name, description, user_id, type, rules, smart_sort_by, smart_sort_direction, smart_limit)
                VALUES (?, ?, ?, ?, ?::jsonb, ?, ?, ?)
                RETURNING id
            ');
            $result = $stmt->execute([
                $playlistData['name'],
                $playlistData['description'],
                $playlistData['user_id'],
                'smart',
                json_encode($playlistData['rules']),
                $playlistData['smart_sort_by'] ?? null,
                $playlistData['smart_sort_direction'] ?? 'asc',
                $playlistData['smart_limit'] ?? null,
            ]);
        } else {
            $stmt = $this->db->prepare('
                INSERT INTO playlists (name, description, user_id, type)
                VALUES (?, ?, ?, ?)
                RETURNING id
            ');
            $result = $stmt->execute([
                $playlistData['name'],
                $playlistData['description'],
                $playlistData['user_id'],
                $type,
            ]);
        }

        if (!$result) {
            error_log("Playlist creation failed: " . json_encode($stmt->errorInfo()));
            throw new Exception("Failed to create playlist");
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = (string) $row['id'];

        $playlist = $this->findById($id);
        if (!$playlist instanceof \App\Models\Playlist) {
            error_log("Could not find playlist with ID: " . $id);
            throw new Exception("Failed to retrieve created playlist");
        }

        return $playlist;
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): ?Playlist
    {
        $existing = $this->findById($id);
        if (!$existing instanceof \App\Models\Playlist) {
            return null;
        }

        $updates = [];
        $values = [];

        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $values[] = trim($data['name']);
        }

        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $values[] = empty($data['description']) ? null : trim($data['description']);
        }

        // Smart playlist fields
        if ($existing->isSmartPlaylist()) {
            if (isset($data['rules']) && is_array($data['rules'])) {
                $updates[] = 'rules = ?::jsonb';
                $values[] = json_encode($data['rules']);
            }
            if (array_key_exists('smart_sort_by', $data)) {
                $updates[] = 'smart_sort_by = ?';
                $values[] = $data['smart_sort_by'];
            }
            if (isset($data['smart_sort_direction'])) {
                $updates[] = 'smart_sort_direction = ?';
                $values[] = $data['smart_sort_direction'];
            }
            if (array_key_exists('smart_limit', $data)) {
                $updates[] = 'smart_limit = ?';
                $values[] = $data['smart_limit'] !== null ? (int) $data['smart_limit'] : null;
            }
        }

        if ($updates === []) {
            return $existing;
        }

        $values[] = $id;

        $stmt = $this->db->prepare('
            UPDATE playlists SET ' . implode(', ', $updates) . '
            WHERE id = ?
        ');

        $stmt->execute($values);

        return $this->findById($id);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM playlists WHERE id = ?');
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    /**
     * Recalculate and update song_count and duration from actual playlist_songs
     */
    public function updateCounts(string $playlistId): void
    {
        $stmt = $this->db->prepare('
            UPDATE playlists
            SET song_count = sub.cnt,
                duration = sub.dur,
                updated_at = NOW()
            FROM (
                SELECT
                    COALESCE(COUNT(ps.song_id), 0) AS cnt,
                    COALESCE(SUM(s.duration), 0) AS dur
                FROM playlist_songs ps
                JOIN songs s ON s.song_id = ps.song_id AND s.is_deleted = 0
                WHERE ps.playlist_id = :pid
            ) sub
            WHERE id = :id
        ');
        $stmt->execute([
            'pid' => $playlistId,
            'id' => $playlistId
        ]);
    }

    public function userOwnsPlaylist(string $userId, string $playlistId): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM playlists 
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$playlistId, $userId]);

        return $stmt->fetchColumn() > 0;
    }

    /** @return array<int, Playlist> */
    public function searchByName(string $query, string $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.*,
                COALESCE(sc.cnt, 0) AS song_count,
                COALESCE(sc.dur, 0) AS duration,
                CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM playlists p
            LEFT JOIN (
                SELECT ps.playlist_id,
                       COUNT(ps.song_id) AS cnt,
                       COALESCE(SUM(s.duration), 0) AS dur
                FROM playlist_songs ps
                JOIN songs s ON s.song_id = ps.song_id AND s.is_deleted = 0
                GROUP BY ps.playlist_id
            ) sc ON sc.playlist_id = p.id
            LEFT JOIN favorites f
                ON f.user_id = :current_user_id
                AND f.favorite_type = \'playlist\'
                AND f.playlist_id = p.id
            WHERE (p.user_id = :search_user_id)
            AND p.name ILIKE :search_term
            ORDER BY
                CASE WHEN p.user_id = :sort_user_id THEN 0 ELSE 1 END,
                p.name ASC
            LIMIT :limit
        ');

        $searchTerm = '%' . $query . '%';
        $stmt->execute([
            'current_user_id' => $this->userId,
            'search_user_id' => $userId,
            'search_term' => $searchTerm,
            'sort_user_id' => $userId,
            'limit' => $limit
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): \App\Models\Playlist => new Playlist($data), $results);
    }
}
