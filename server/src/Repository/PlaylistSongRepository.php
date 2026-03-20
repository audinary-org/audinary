<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\PlaylistSong;
use App\Database\Connection;
use Exception;
use PDO;

final class PlaylistSongRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::getPDO();
    }

    public function findById(int $id): ?PlaylistSong
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM playlist_songs WHERE id = ?
        ');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new PlaylistSong($data) : null;
    }

    /** @return array<int, array<string, mixed>> */
    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByPlaylistId(string $playlistId): array
    {
        $sql = '
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
                s.last_played as lastPlayed,
                s.filetype,
                a.album_id,
                a.album_name as album,
                a.album_year,
                a.album_genre,
                a.album_duration,
                CASE WHEN a.cover_path IS NOT NULL THEN \'/img/userdata/albums/\' || a.album_id || \'.webp\' ELSE NULL END as coverArtUrl,
                ps.position,
                CASE WHEN f.favorite_id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
            FROM playlist_songs ps
            JOIN songs s ON ps.song_id = s.song_id
            JOIN albums a ON s.album_id = a.album_id
            LEFT JOIN favorites f ON s.song_id = f.song_id AND f.favorite_type = \'song\'
            WHERE ps.playlist_id = ? AND s.is_deleted = 0 AND a.is_deleted = 0
            ORDER BY ps.position ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$playlistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<PlaylistSong>
     */
    public function findBySongId(string $songId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM playlist_songs WHERE song_id = ?
        ');
        $stmt->execute([$songId]);
        /** @var list<array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): PlaylistSong => new PlaylistSong($data), $results);
    }

    public function countByPlaylistId(string $playlistId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM playlist_songs WHERE playlist_id = ?
        ');
        $stmt->execute([$playlistId]);

        return (int) $stmt->fetchColumn();
    }

    public function getNextPosition(string $playlistId): float
    {
        $stmt = $this->pdo->prepare('
            SELECT MAX(position) FROM playlist_songs WHERE playlist_id = ?
        ');
        $stmt->execute([$playlistId]);
        $maxPosition = $stmt->fetchColumn();

        return $maxPosition ? ((float) $maxPosition) + 1.0 : 1.0;
    }

    public function add(string $playlistId, string $songId, ?float $position = null): PlaylistSong
    {
        if ($position === null) {
            $position = $this->getNextPosition($playlistId);
        }

        $data = PlaylistSong::createData([
            'playlist_id' => $playlistId,
            'song_id' => $songId,
            'position' => $position
        ]);

        $stmt = $this->pdo->prepare('
            INSERT INTO playlist_songs (playlist_id, song_id, position)
            VALUES (?, ?, ?)
        ');

        $stmt->execute([
            $data['playlist_id'],
            $data['song_id'],
            $data['position']
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function remove(string $playlistId, string $songId): bool
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM playlist_songs 
            WHERE playlist_id = ? AND song_id = ?
        ');

        return $stmt->execute([$playlistId, $songId]) && $stmt->rowCount() > 0;
    }

    public function removeById(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM playlist_songs WHERE id = ?');
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public function updatePosition(int $id, float $position): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE playlist_songs SET position = ? WHERE id = ?
        ');

        return $stmt->execute([$position, $id]);
    }

    /**
     * @param array<string, float|int> $songPositions
     */
    public function reorderSongs(string $playlistId, array $songPositions): bool
    {
        $this->pdo->beginTransaction();

        try {
            // Step 1: Set all positions to negative values to avoid conflicts
            // This temporarily moves all songs to negative positions
            $tempPosition = -1000;
            foreach (array_keys($songPositions) as $songId) {
                $stmt = $this->pdo->prepare('
                    UPDATE playlist_songs 
                    SET position = ? 
                    WHERE playlist_id = ? AND song_id = ?
                ');
                $stmt->execute([$tempPosition, $playlistId, $songId]);
                $tempPosition--;
            }

            // Step 2: Now set the actual positions (no conflicts possible since all are negative)
            foreach ($songPositions as $songId => $position) {
                $stmt = $this->pdo->prepare('
                    UPDATE playlist_songs 
                    SET position = ? 
                    WHERE playlist_id = ? AND song_id = ?
                ');
                $stmt->execute([$position, $playlistId, $songId]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollback();
            return false;
        }
    }

    public function songExistsInPlaylist(string $playlistId, string $songId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM playlist_songs 
            WHERE playlist_id = ? AND song_id = ?
        ');
        $stmt->execute([$playlistId, $songId]);

        return $stmt->fetchColumn() > 0;
    }

    public function deleteByPlaylistId(string $playlistId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM playlist_songs WHERE playlist_id = ?');
        return $stmt->execute([$playlistId]);
    }
}
