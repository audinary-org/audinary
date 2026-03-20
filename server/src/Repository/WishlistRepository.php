<?php

namespace App\Repository;

use PDO;

/**
 * Repository for wishlist data access
 */
class WishlistRepository extends BaseRepository
{
    /**
     * Get all wishlist items for a user
     * @return array<int, array<string, mixed>>
     */
    public function getUserWishlist(?string $userId = null): array
    {
        $userId ??= $this->userId;

        $sql = "SELECT * FROM wishlist WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all wishlist items (admin view)
     * @return array<int, array<string, mixed>>
     */
    public function getAllWishlist(): array
    {
        $sql = "SELECT w.*, u.username
                FROM wishlist w
                LEFT JOIN users u ON w.user_id = u.user_id
                ORDER BY w.created_at DESC";
        $stmt = $this->db->query($sql);
        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get wishlist items by status (admin view)
     * @return array<int, array<string, mixed>>
     */
    public function getWishlistByStatus(string $status): array
    {
        $sql = "SELECT w.*, u.username
                FROM wishlist w
                LEFT JOIN users u ON w.user_id = u.user_id
                WHERE w.status = ?
                ORDER BY w.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single wishlist item by ID
     * @return array<string, mixed>|null
     */
    public function getWishlistItem(int $id): ?array
    {
        $sql = "SELECT * FROM wishlist WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create a new wishlist item
     */
    public function createWishlistItem(
        string $userId,
        string $artist,
        ?string $album = null,
        ?string $userComment = null,
        ?string $lastfmArtistMbid = null,
        ?string $lastfmAlbumMbid = null
    ): int {
        $sql = "INSERT INTO wishlist
                (user_id, artist, album, user_comment, lastfm_artist_mbid, lastfm_album_mbid, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $userId,
            $artist,
            $album,
            $userComment,
            $lastfmArtistMbid,
            $lastfmAlbumMbid
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update wishlist item status (admin)
     */
    public function updateWishlistStatus(
        int $id,
        string $status,
        ?string $adminComment = null
    ): bool {
        $sql = "UPDATE wishlist
                SET status = ?, admin_comment = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $adminComment, $id]);
    }

    /**
     * Update user wishlist item (user can edit their own items)
     */
    public function updateUserWishlistItem(
        int $id,
        string $userId,
        string $artist,
        ?string $album = null,
        ?string $userComment = null
    ): bool {
        $sql = "UPDATE wishlist
                SET artist = ?, album = ?, user_comment = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$artist, $album, $userComment, $id, $userId]);
    }

    /**
     * Delete a wishlist item (user can only delete their own)
     */
    public function deleteWishlistItem(int $id, string $userId): bool
    {
        $sql = "DELETE FROM wishlist WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id, $userId]);
    }

    /**
     * Delete a wishlist item (admin can delete any)
     */
    public function adminDeleteWishlistItem(int $id): bool
    {
        $sql = "DELETE FROM wishlist WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Get wishlist statistics
     * @return array<string, int>
     */
    public function getWishlistStats(): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM wishlist";

        $stmt = $this->db->query($sql);
        if ($stmt === false) {
            return [];
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
