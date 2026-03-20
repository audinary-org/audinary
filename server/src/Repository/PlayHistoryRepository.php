<?php

namespace App\Repository;

use Exception;
use InvalidArgumentException;
use RuntimeException;

class PlayHistoryRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>
     */
    public function logSongPlay(string $songId): array
    {
        $this->validateUuid($songId, 'song_id');

        // Check if song exists
        $songExists = $this->executeQuerySingle(
            "SELECT song_id FROM songs WHERE song_id = :songId AND is_deleted = 0",
            [':songId' => $songId]
        );

        if ($songExists === null || $songExists === []) {
            throw new InvalidArgumentException('Song not found');
        }

        $this->db->beginTransaction();

        try {
            // Lock and check for duplicate plays within the last 30 seconds
            $duplicateCheckSql = "
                SELECT COUNT(*) as count, MAX(played_at) as last_played
                FROM play_history 
                WHERE user_id = :userId 
                AND song_id = :songId 
                AND played_at >= (NOW() - INTERVAL '30 seconds')
            ";

            $duplicateResult = $this->executeQuerySingle($duplicateCheckSql, [
                ':userId' => $this->userId,
                ':songId' => $songId
            ]);

            if ($duplicateResult['count'] > 0) {
                $this->db->commit();
                return [
                    'success' => true,
                    'message' => 'Song already logged recently'
                ];
            }

            // Insert the play record
            $sql = "INSERT INTO play_history (user_id, song_id, played_at, play_count) VALUES (:userId, :songId, NOW(), 1)";
            $success = $this->executeStatement($sql, [
                ':userId' => $this->userId,
                ':songId' => $songId
            ]);

            if ($success) {
                $this->db->commit();
                return ['success' => true];
            }
            $this->db->rollBack();
            throw new RuntimeException('Failed to log song play');
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return array<string, mixed> */
    public function logAlbumPlay(string $albumId): array
    {
        $this->validateUuid($albumId, 'album_id');

        // Check if album exists
        $albumExists = $this->executeQuerySingle(
            "SELECT album_id FROM albums WHERE album_id = :albumId AND is_deleted = 0",
            [':albumId' => $albumId]
        );

        if ($albumExists === null || $albumExists === []) {
            throw new InvalidArgumentException('Album not found');
        }

        $this->db->beginTransaction();

        try {
            // Check for recent album play within the last 10 minutes
            $duplicateCheckSql = "
                SELECT COUNT(*) as count
                FROM album_play_history 
                WHERE user_id = :userId 
                AND album_id = :albumId 
                AND played_at >= (NOW() - INTERVAL '10 minutes')
            ";

            $duplicateResult = $this->executeQuerySingle($duplicateCheckSql, [
                ':userId' => $this->userId,
                ':albumId' => $albumId
            ]);

            if ($duplicateResult['count'] > 0) {
                // Update existing entry
                $updateSql = "
                    UPDATE album_play_history 
                    SET play_count = play_count + 1, played_at = NOW()
                    WHERE user_id = :userId AND album_id = :albumId
                ";
                $this->executeStatement($updateSql, [
                    ':userId' => $this->userId,
                    ':albumId' => $albumId
                ]);

                $this->db->commit();
                return ['success' => true, 'message' => 'Album play count updated'];
            }

            // Insert new album play history
            $sql = "
                INSERT INTO album_play_history (user_id, album_id, play_count, played_at)
                VALUES (:userId, :albumId, 1, NOW())
            ";

            $success = $this->executeStatement($sql, [
                ':userId' => $this->userId,
                ':albumId' => $albumId
            ]);

            if ($success) {
                $this->db->commit();
                return ['success' => true];
            }
            $this->db->rollBack();
            throw new RuntimeException('Failed to log album play');
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return array<string, mixed> */
    public function logArtistPlay(string $artistId): array
    {
        $this->validateUuid($artistId, 'artist_id');

        // Check if artist exists
        $artistExists = $this->executeQuerySingle(
            "SELECT artist_id FROM artists WHERE artist_id = :artistId AND is_deleted = 0",
            [':artistId' => $artistId]
        );

        if ($artistExists === null || $artistExists === []) {
            throw new InvalidArgumentException('Artist not found');
        }

        $this->db->beginTransaction();

        try {
            // Check for recent artist play within the last 10 minutes
            $duplicateCheckSql = "
                SELECT COUNT(*) as count
                FROM artist_play_history 
                WHERE user_id = :userId 
                AND artist_id = :artistId 
                AND played_at >= (NOW() - INTERVAL '10 minutes')
            ";

            $duplicateResult = $this->executeQuerySingle($duplicateCheckSql, [
                ':userId' => $this->userId,
                ':artistId' => $artistId
            ]);

            if ($duplicateResult['count'] > 0) {
                // Update existing entry
                $updateSql = "
                    UPDATE artist_play_history 
                    SET play_count = play_count + 1, played_at = NOW()
                    WHERE user_id = :userId AND artist_id = :artistId
                ";
                $this->executeStatement($updateSql, [
                    ':userId' => $this->userId,
                    ':artistId' => $artistId
                ]);

                $this->db->commit();
                return ['success' => true, 'message' => 'Artist play count updated'];
            }

            // Insert new artist play history
            $sql = "
                INSERT INTO artist_play_history (user_id, artist_id, play_count, played_at)
                VALUES (:userId, :artistId, 1, NOW())
            ";

            $success = $this->executeStatement($sql, [
                ':userId' => $this->userId,
                ':artistId' => $artistId
            ]);

            if ($success) {
                $this->db->commit();
                return ['success' => true];
            }
            $this->db->rollBack();
            throw new RuntimeException('Failed to log artist play');
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getRecentPlays(int $limit = 50): array
    {
        $sql = "
            SELECT 
                ph.song_id,
                ph.played_at,
                s.title,
                s.artist,
                s.duration,
                a.album_id,
                a.album_name,
                a.album_artist
            FROM play_history ph
            JOIN songs s ON ph.song_id = s.song_id
            JOIN albums a ON s.album_id = a.album_id
            WHERE ph.user_id = :userId
              AND s.is_deleted = 0
              AND a.is_deleted = 0
            ORDER BY ph.played_at DESC
            LIMIT :limit
        ";

        return $this->executeQuery($sql, [
            ':userId' => $this->userId,
            ':limit' => $limit
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getMostPlayedSongs(int $limit = 50): array
    {
        $sql = "
            SELECT 
                s.song_id,
                s.title,
                s.artist,
                s.duration,
                a.album_id,
                a.album_name,
                a.album_artist,
                COUNT(ph.song_id) as play_count,
                MAX(ph.played_at) as last_played
            FROM play_history ph
            JOIN songs s ON ph.song_id = s.song_id
            JOIN albums a ON s.album_id = a.album_id
            WHERE ph.user_id = :userId
              AND s.is_deleted = 0
              AND a.is_deleted = 0
            GROUP BY s.song_id, s.title, s.artist, s.duration, a.album_id, a.album_name, a.album_artist
            ORDER BY play_count DESC, last_played DESC
            LIMIT :limit
        ";

        return $this->executeQuery($sql, [
            ':userId' => $this->userId,
            ':limit' => $limit
        ]);
    }

    /** @return array<string, mixed> */
    public function getPlayStats(): array
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT song_id) as unique_songs,
                COUNT(*) as total_plays,
                COUNT(DISTINCT DATE(played_at)) as days_with_plays
            FROM play_history
            WHERE user_id = :userId
        ";

        $result = $this->executeQuerySingle($sql, [':userId' => $this->userId]);

        return [
            'unique_songs' => (int)($result['unique_songs'] ?? 0),
            'total_plays' => (int)($result['total_plays'] ?? 0),
            'days_with_plays' => (int)($result['days_with_plays'] ?? 0)
        ];
    }
}
