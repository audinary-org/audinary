<?php

namespace App\Repository;

use App\Models\Favorite;
use Exception;
use InvalidArgumentException;
use PDO;

class FavoriteRepository extends BaseRepository
{
    public function __construct(?string $userId = null)
    {
        parent::__construct($userId);
    }

    /**
     * @return array<Favorite>
     */
    public function findAllForUser(?string $type = null): array
    {
        $sql = "
            SELECT favorite_type, song_id, album_id, artist_id, playlist_id, created_at
            FROM favorites
            WHERE user_id = :userId
        ";

        $bindParams = [':userId' => $this->userId];

        if ($type !== null) {
            if (!in_array($type, ['song', 'album', 'artist', 'playlist'])) {
                throw new InvalidArgumentException("Ungültiger Favoriten-Typ: $type");
            }

            $sql .= " AND favorite_type = :type";
            $bindParams[':type'] = $type;
        }

        $sql .= " ORDER BY created_at DESC";

        $results = $this->executeQuery($sql, $bindParams);
        return array_map(fn($row): Favorite => new Favorite($row), $results);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function add(array $data): bool
    {
        if (empty($data['favorite_type'])) {
            throw new InvalidArgumentException("Favoriten-Typ fehlt");
        }

        $type = $data['favorite_type'];
        if (!in_array($type, ['song', 'album', 'artist', 'playlist'])) {
            throw new InvalidArgumentException("Ungültiger Favoriten-Typ: $type");
        }

        $songId = $albumId = $artistId = $playlistId = null;

        switch ($type) {
            case 'song':
                if (empty($data['song_id'])) {
                    throw new InvalidArgumentException("Song-ID fehlt");
                }
                $songId = $this->validateUuid($data['song_id'], 'song_id');
                break;

            case 'album':
                if (empty($data['album_id'])) {
                    throw new InvalidArgumentException("Album-ID fehlt");
                }
                $albumId = $this->validateUuid($data['album_id'], 'album_id');
                break;

            case 'artist':
                if (empty($data['artist_id'])) {
                    throw new InvalidArgumentException("Künstler-ID fehlt");
                }
                $artistId = $this->validateUuid($data['artist_id'], 'artist_id');
                break;

            case 'playlist':
                if (empty($data['playlist_id'])) {
                    throw new InvalidArgumentException("Playlist-ID fehlt");
                }
                $playlistId = (string) $data['playlist_id'];
                break;
        }

        try {
            $this->db->beginTransaction();

            // Check if the referenced entity exists
            $this->validateEntityExists($type, $songId, $albumId, $artistId, $playlistId);

            // Insert the favorite
            $sql = "
                INSERT INTO favorites 
                    (user_id, favorite_type, song_id, album_id, artist_id, playlist_id, created_at) 
                VALUES 
                    (:userId, :type, :songId, :albumId, :artistId, :playlistId, CURRENT_TIMESTAMP)
                ON CONFLICT(user_id, favorite_type, song_id, album_id, artist_id, playlist_id) DO UPDATE SET 
                    created_at = CURRENT_TIMESTAMP
            ";

            $bindParams = [
                ':userId' => $this->userId,
                ':type' => $type,
                ':songId' => $songId,
                ':albumId' => $albumId,
                ':artistId' => $artistId,
                ':playlistId' => $playlistId
            ];

            $this->executeStatement($sql, $bindParams);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function remove(array $data): bool
    {
        if (empty($data['favorite_type'])) {
            throw new InvalidArgumentException("Favoriten-Typ fehlt");
        }

        $type = $data['favorite_type'];
        if (!in_array($type, ['song', 'album', 'artist', 'playlist'])) {
            throw new InvalidArgumentException("Ungültiger Favoriten-Typ: $type");
        }

        $songId = $albumId = $artistId = $playlistId = null;

        switch ($type) {
            case 'song':
                if (empty($data['song_id'])) {
                    throw new InvalidArgumentException("Song-ID fehlt");
                }
                $songId = $this->validateUuid($data['song_id'], 'song_id');
                break;

            case 'album':
                if (empty($data['album_id'])) {
                    throw new InvalidArgumentException("Album-ID fehlt");
                }
                $albumId = $this->validateUuid($data['album_id'], 'album_id');
                break;

            case 'artist':
                if (empty($data['artist_id'])) {
                    throw new InvalidArgumentException("Künstler-ID fehlt");
                }
                $artistId = $this->validateUuid($data['artist_id'], 'artist_id');
                break;

            case 'playlist':
                if (empty($data['playlist_id'])) {
                    throw new InvalidArgumentException("Playlist-ID fehlt");
                }
                $playlistId = (string) $data['playlist_id'];
                break;
        }

        $sql = "
            DELETE FROM favorites 
            WHERE user_id = :userId 
              AND favorite_type = :type
        ";

        $bindParams = [
            ':userId' => $this->userId,
            ':type' => $type
        ];

        // Add the appropriate ID condition
        switch ($type) {
            case 'song':
                $sql .= " AND song_id = :id";
                $bindParams[':id'] = $songId;
                break;
            case 'album':
                $sql .= " AND album_id = :id";
                $bindParams[':id'] = $albumId;
                break;
            case 'artist':
                $sql .= " AND artist_id = :id";
                $bindParams[':id'] = $artistId;
                break;
            case 'playlist':
                $sql .= " AND playlist_id = :id";
                $bindParams[':id'] = $playlistId;
                break;
        }

        $stmt = $this->db->prepare($sql);
        foreach ($bindParams as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function isFavorite(string $type, string $entityId): bool
    {
        if (!in_array($type, ['song', 'album', 'artist', 'playlist'])) {
            throw new InvalidArgumentException("Ungültiger Favoriten-Typ: $type");
        }

        if ($type !== 'playlist') {
            $this->validateUuid($entityId, $type . '_id');
        }

        $sql = "
            SELECT 1
            FROM favorites 
            WHERE user_id = :userId 
              AND favorite_type = :type
        ";

        $bindParams = [
            ':userId' => $this->userId,
            ':type' => $type
        ];

        switch ($type) {
            case 'song':
                $sql .= " AND song_id = :id";
                break;
            case 'album':
                $sql .= " AND album_id = :id";
                break;
            case 'artist':
                $sql .= " AND artist_id = :id";
                break;
            case 'playlist':
                $sql .= " AND playlist_id = :id";
                break;
        }

        $bindParams[':id'] = $entityId;

        $result = $this->executeQuerySingle($sql, $bindParams);
        return $result !== null;
    }

    public function getCountByType(string $type): int
    {
        if (!in_array($type, ['song', 'album', 'artist', 'playlist'])) {
            throw new InvalidArgumentException("Ungültiger Favoriten-Typ: $type");
        }

        $sql = "
            SELECT COUNT(*) as count
            FROM favorites
            WHERE user_id = :userId AND favorite_type = :type
        ";

        $bindParams = [
            ':userId' => $this->userId,
            ':type' => $type
        ];

        $result = $this->executeQuerySingle($sql, $bindParams);
        return (int)($result['count'] ?? 0);
    }

    private function validateEntityExists(string $type, ?string $songId, ?string $albumId, ?string $artistId, ?string $playlistId = null): void
    {
        switch ($type) {
            case 'song':
                $sql = "SELECT 1 FROM songs WHERE song_id = :id AND is_deleted = 0 LIMIT 1";
                $param = $songId;
                break;
            case 'album':
                $sql = "SELECT 1 FROM albums WHERE album_id = :id AND is_deleted = 0 LIMIT 1";
                $param = $albumId;
                break;
            case 'artist':
                $sql = "SELECT 1 FROM artists WHERE artist_id = :id AND is_deleted = 0 LIMIT 1";
                $param = $artistId;
                break;
            case 'playlist':
                $sql = "SELECT 1 FROM playlists WHERE id = :id LIMIT 1";
                $param = $playlistId;
                break;
            default:
                return;
        }

        $result = $this->executeQuerySingle($sql, [':id' => $param]);

        if ($result === null) {
            throw new InvalidArgumentException("Der angegebene {$type} existiert nicht oder wurde gelöscht");
        }
    }
}
