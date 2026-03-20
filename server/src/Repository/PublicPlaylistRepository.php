<?php

namespace App\Repository;

use PDO;
use App\Database\Connection;
use Exception;

class PublicPlaylistRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getPDO();
    }

    /**
     * Create a public link for a playlist
     *
     * @return array<string, mixed>|null
     */
    public function createPublicLink(string $playlistId, string $createdByUserId, ?string $expiresAt = null, bool $downloadEnabled = false): ?array
    {
        try {
            // Generate cryptographically secure UUID
            $linkUuid = $this->generateSecureUuid();

            // Ensure expires_at is not more than 1 year from now
            if ($expiresAt !== null && $expiresAt !== '' && $expiresAt !== '0') {
                $maxExpiry = date('Y-m-d H:i:s', strtotime('+1 year'));
                if ($expiresAt > $maxExpiry) {
                    $expiresAt = $maxExpiry;
                }
            }

            $stmt = $this->db->prepare("
                INSERT INTO playlist_public_links (
                    link_uuid, 
                    playlist_id, 
                    created_by_user_id, 
                    expires_at, 
                    download_enabled,
                    created_at
                ) VALUES (
                    :link_uuid, 
                    :playlist_id, 
                    :created_by_user_id, 
                    :expires_at, 
                    :download_enabled,
                    NOW()
                )
            ");

            $result = $stmt->execute([
                ':link_uuid' => $linkUuid,
                ':playlist_id' => $playlistId,
                ':created_by_user_id' => $createdByUserId,
                ':expires_at' => $expiresAt,
                ':download_enabled' => $downloadEnabled ? 1 : 0
            ]);

            if ($result) {
                return [
                    'link_uuid' => $linkUuid,
                    'playlist_id' => $playlistId,
                    'created_by_user_id' => $createdByUserId,
                    'expires_at' => $expiresAt,
                    'download_enabled' => $downloadEnabled,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            return null;
        } catch (Exception $e) {
            error_log("Error creating public playlist link: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get public playlist data by UUID
     *
     * @return array<string, mixed>|null
     */
    public function getPublicPlaylist(string $linkUuid): ?array
    {
        try {
            // First validate the link
            if (!$this->validatePublicAccess($linkUuid)) {
                return null;
            }

            $stmt = $this->db->prepare("
                SELECT 
                    ppl.link_uuid,
                    ppl.playlist_id,
                    ppl.download_enabled,
                    ppl.play_count,
                    p.name,
                    p.description,
                    p.cover_image_uuid,
                    p.created_at as playlist_created_at,
                    u.username as creator_username,
                    u.display_name as creator_display_name
                FROM playlist_public_links ppl
                JOIN playlists p ON ppl.playlist_id = p.playlist_id
                JOIN users u ON ppl.created_by_user_id = u.user_id
                WHERE ppl.link_uuid = :link_uuid
                AND ppl.is_active = 1
                AND (ppl.expires_at IS NULL OR ppl.expires_at > NOW())
                AND p.is_shared = 1
            ");

            $stmt->execute([':link_uuid' => $linkUuid]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Update last_accessed
                $this->updateLastAccessed($linkUuid);
                return $result;
            }

            return null;
        } catch (Exception $e) {
            error_log("Error getting public playlist: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get songs for a public playlist
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function getPublicPlaylistSongs(string $linkUuid): ?array
    {
        try {
            // First validate access
            $playlist = $this->getPublicPlaylist($linkUuid);
            if ($playlist === null || $playlist === []) {
                return null;
            }

            $stmt = $this->db->prepare("
                SELECT 
                    pe.song_id,
                    pe.position,
                    s.title,
                    s.artist,
                    s.album_id,
                    a.album_name as album,
                    s.track_number,
                    s.year,
                    s.genre,
                    s.duration,
                    s.file_path,
                    s.size as file_size,
                    s.bitrate,
                    s.filetype as format
                FROM playlist_entries pe
                JOIN songs s ON pe.song_id = s.song_id
                LEFT JOIN albums a ON s.album_id = a.album_id
                WHERE pe.playlist_id = :playlist_id
                ORDER BY pe.position ASC
            ");

            $stmt->execute([':playlist_id' => $playlist['playlist_id']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting public playlist songs: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate if public access is allowed for a UUID
     */
    public function validatePublicAccess(string $linkUuid): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT ppl.id
                FROM playlist_public_links ppl
                JOIN playlists p ON ppl.playlist_id = p.playlist_id
                WHERE ppl.link_uuid = :link_uuid
                AND ppl.is_active = 1
                AND (ppl.expires_at IS NULL OR ppl.expires_at > NOW())
                AND p.is_shared = 1
            ");

            $stmt->execute([':link_uuid' => $linkUuid]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("Error validating public access: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment play count for analytics
     */
    public function incrementPlayCount(string $linkUuid): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE playlist_public_links 
                SET play_count = play_count + 1,
                    last_accessed = NOW()
                WHERE link_uuid = :link_uuid
            ");

            return $stmt->execute([':link_uuid' => $linkUuid]);
        } catch (Exception $e) {
            error_log("Error incrementing play count: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if download is enabled for a public playlist
     */
    public function isDownloadEnabled(string $linkUuid): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT download_enabled
                FROM playlist_public_links
                WHERE link_uuid = :link_uuid
                AND is_active = 1
                AND (expires_at IS NULL OR expires_at > NOW())
            ");

            $stmt->execute([':link_uuid' => $linkUuid]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result && (bool)$result['download_enabled'];
        } catch (Exception $e) {
            error_log("Error checking download permission: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get public links created by a user
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserPublicLinks(string $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ppl.*,
                    p.name as playlist_name,
                    p.description as playlist_description
                FROM playlist_public_links ppl
                JOIN playlists p ON ppl.playlist_id = p.playlist_id
                WHERE ppl.created_by_user_id = :user_id
                AND ppl.is_active = 1
                ORDER BY ppl.created_at DESC
            ");

            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user public links: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Deactivate a public link
     */
    public function deactivatePublicLink(string $linkUuid, string $userId): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE playlist_public_links ppl
                JOIN playlists p ON ppl.playlist_id = p.playlist_id
                SET ppl.is_active = 0
                WHERE ppl.link_uuid = :link_uuid
                AND (ppl.created_by_user_id = :user_id OR p.user_id = :user_id2)
            ");

            return $stmt->execute([
                ':link_uuid' => $linkUuid,
                ':user_id' => $userId,
                ':user_id2' => $userId
            ]);
        } catch (Exception $e) {
            error_log("Error deactivating public link: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up expired links (called by cron job)
     */
    public function cleanupExpiredLinks(): int
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE playlist_public_links 
                SET is_active = 0
                WHERE expires_at <= NOW()
                AND is_active = 1
            ");

            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Error cleaning up expired links: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Generate a cryptographically secure UUID
     */
    private function generateSecureUuid(): string
    {
        try {
            // Generate 16 random bytes (128 bits)
            $data = random_bytes(16);

            // Set version to 4 (random)
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            // Set bits 6-7 to 10
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        } catch (Exception $e) {
            error_log("Error generating secure UUID: " . $e->getMessage());
            // Fallback to less secure method
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff)
            );
        }
    }

    /**
     * Update last accessed timestamp
     */
    private function updateLastAccessed(string $linkUuid): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE playlist_public_links 
                SET last_accessed = NOW()
                WHERE link_uuid = :link_uuid
            ");

            return $stmt->execute([':link_uuid' => $linkUuid]);
        } catch (Exception $e) {
            error_log("Error updating last accessed: " . $e->getMessage());
            return false;
        }
    }
}
