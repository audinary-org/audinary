<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\PublicShare;
use Exception;
use PDO;

final class PublicShareRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct();
    }

    public function findById(string $id): ?PublicShare
    {
        $stmt = $this->db->prepare('
            SELECT * FROM public_shares WHERE id = ?
        ');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new PublicShare($data) : null;
    }

    public function findByUuid(string $uuid): ?PublicShare
    {
        $stmt = $this->db->prepare('
            SELECT * FROM public_shares WHERE share_uuid = ?
        ');
        $stmt->execute([$uuid]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new PublicShare($data) : null;
    }

    /**
     * @return list<PublicShare>
     */
    public function findByTypeAndItemId(string $type, string $itemId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM public_shares 
            WHERE type = ? AND item_id = ?
            ORDER BY created_at DESC
        ');
        $stmt->execute([$type, $itemId]);
        /** @var list<array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): PublicShare => new PublicShare($data), $results);
    }

    /**
     * @return list<PublicShare>
     */
    public function findByCreatedBy(string $userId, int $offset = 0, int $limit = 50): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM public_shares 
            WHERE created_by = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->execute([$userId, $limit, $offset]);
        /** @var list<array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): PublicShare => new PublicShare($data), $results);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): PublicShare
    {
        $shareData = PublicShare::createData($data);

        // Generate UUID for the ID
        $id = $this->generateUuid();

        $stmt = $this->db->prepare('
            INSERT INTO public_shares (id, type, item_id, share_uuid, download_enabled, expires_at, password_hash, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $id,
            $shareData['type'],
            $shareData['item_id'],
            $shareData['share_uuid'],
            $shareData['download_enabled'] ? 1 : 0,
            $shareData['expires_at'],
            $shareData['password_hash'],
            $shareData['created_by']
        ]);

        // Fetch the created record from database
        $stmt = $this->db->prepare('
            SELECT * FROM public_shares WHERE id = ?
        ');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            throw new Exception('Failed to retrieve created public share');
        }

        return new PublicShare($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): ?PublicShare
    {
        $existing = $this->findById($id);
        if (!$existing instanceof \App\Models\PublicShare) {
            return null;
        }

        $updates = [];
        $values = [];

        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $values[] = empty($data['name']) ? null : trim($data['name']);
        }

        if (isset($data['download_enabled'])) {
            $updates[] = 'download_enabled = ?';
            $values[] = (bool) $data['download_enabled'] ? 1 : 0;
        }

        if (isset($data['expires_at'])) {
            $updates[] = 'expires_at = ?';
            $values[] = $data['expires_at'];
        }

        if (isset($data['password'])) {
            $updates[] = 'password_hash = ?';
            $values[] = empty($data['password']) ? null : password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if ($updates === []) {
            return $existing;
        }

        $values[] = $id;

        $stmt = $this->db->prepare('
            UPDATE public_shares SET ' . implode(', ', $updates) . '
            WHERE id = ?
        ');

        $stmt->execute($values);

        return $this->findById($id);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM public_shares WHERE id = ?');
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public function deleteByUuid(string $uuid): bool
    {
        $stmt = $this->db->prepare('DELETE FROM public_shares WHERE share_uuid = ?');
        return $stmt->execute([$uuid]) && $stmt->rowCount() > 0;
    }

    public function deleteByTypeAndItemId(string $type, string $itemId): bool
    {
        $stmt = $this->db->prepare('
            DELETE FROM public_shares WHERE type = ? AND item_id = ?
        ');
        return $stmt->execute([$type, $itemId]);
    }

    public function incrementAccessCount(string $uuid): bool
    {
        $stmt = $this->db->prepare('
            UPDATE public_shares 
            SET access_count = access_count + 1 
            WHERE share_uuid = ?
        ');
        return $stmt->execute([$uuid]);
    }

    public function userOwnsShare(string $userId, string $shareId): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM public_shares 
            WHERE id = ? AND created_by = ?
        ');
        $stmt->execute([$shareId, $userId]);

        return $stmt->fetchColumn() > 0;
    }

    public function verifyPassword(string $uuid, string $password): bool
    {
        $share = $this->findByUuid($uuid);

        if (!$share instanceof \App\Models\PublicShare || in_array($share->getPasswordHash(), [null, '', '0'], true)) {
            return false;
        }

        return password_verify($password, $share->getPasswordHash());
    }

    public function cleanupExpiredShares(): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM public_shares 
            WHERE expires_at IS NOT NULL 
            AND expires_at < NOW()
        ');
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function countByCreatedBy(string $userId): int
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM public_shares WHERE created_by = ?
        ');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<PublicShare>
     */
    public function findAll(int $offset = 0, int $limit = 50): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM public_shares 
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->execute([$limit, $offset]);
        /** @var list<array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): PublicShare => new PublicShare($data), $results);
    }

    public function countAll(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM public_shares');
        if ($stmt === false) {
            throw new \RuntimeException("Failed to count shares");
        }
        return (int) $stmt->fetchColumn();
    }

    public function countActive(): int
    {
        $stmt = $this->db->query('
            SELECT COUNT(*) FROM public_shares 
            WHERE expires_at IS NULL OR expires_at > NOW()
        ');
        if ($stmt === false) {
            throw new \RuntimeException("Failed to count active shares");
        }
        return (int) $stmt->fetchColumn();
    }

    public function countExpired(): int
    {
        $stmt = $this->db->query('
            SELECT COUNT(*) FROM public_shares 
            WHERE expires_at IS NOT NULL AND expires_at <= NOW()
        ');
        if ($stmt === false) {
            throw new \RuntimeException("Failed to count expired shares");
        }
        return (int) $stmt->fetchColumn();
    }

    public function countPasswordProtected(): int
    {
        $stmt = $this->db->query('
            SELECT COUNT(*) FROM public_shares 
            WHERE password_hash IS NOT NULL
        ');
        if ($stmt === false) {
            throw new \RuntimeException("Failed to count password protected shares");
        }
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, int>
     */
    public function countByType(): array
    {
        $stmt = $this->db->query('
            SELECT type, COUNT(*) as count 
            FROM public_shares 
            GROUP BY type
        ');
        if ($stmt === false) {
            throw new \RuntimeException("Failed to count shares by type");
        }
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['type']] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function countByTypeAndUser(string $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT type, COUNT(*) as count 
            FROM public_shares 
            WHERE created_by = ?
            GROUP BY type
        ');
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['type']] = (int) $result['count'];
        }

        return $counts;
    }
}
