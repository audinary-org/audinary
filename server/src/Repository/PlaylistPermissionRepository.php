<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\PlaylistPermission;
use App\Database\Connection;
use InvalidArgumentException;
use PDO;

final class PlaylistPermissionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::getPDO();
    }

    public function findById(int $id): ?PlaylistPermission
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM playlist_permissions WHERE id = ?
        ');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new PlaylistPermission($data) : null;
    }

    /**
     * @return list<PlaylistPermission>
     */
    public function findByPlaylistId(string $playlistId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM playlist_permissions 
            WHERE playlist_id = ?
            ORDER BY created_at ASC
        ');
        $stmt->execute([$playlistId]);
        /** @var list<array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): PlaylistPermission => new PlaylistPermission($data), $results);
    }

    /**
     * @return list<PlaylistPermission>
     */
    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM playlist_permissions WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
        /** @var list<array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data): PlaylistPermission => new PlaylistPermission($data), $results);
    }

    public function findUserPermission(string $playlistId, string $userId): ?PlaylistPermission
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM playlist_permissions 
            WHERE playlist_id = ? AND user_id = ?
        ');
        $stmt->execute([$playlistId, $userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new PlaylistPermission($data) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): PlaylistPermission
    {
        $permissionData = PlaylistPermission::createData($data);

        $stmt = $this->pdo->prepare('
            INSERT INTO playlist_permissions (playlist_id, user_id, permission_type)
            VALUES (?, ?, ?)
        ');

        $stmt->execute([
            $permissionData['playlist_id'],
            $permissionData['user_id'],
            $permissionData['permission_type']
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, string $permissionType): ?PlaylistPermission
    {
        if (!PlaylistPermission::isValidPermissionType($permissionType)) {
            throw new InvalidArgumentException('Invalid permission type');
        }

        $stmt = $this->pdo->prepare('
            UPDATE playlist_permissions 
            SET permission_type = ? 
            WHERE id = ?
        ');

        $stmt->execute([$permissionType, $id]);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM playlist_permissions WHERE id = ?');
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public function deleteByPlaylistAndUser(string $playlistId, string $userId): bool
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM playlist_permissions 
            WHERE playlist_id = ? AND user_id = ?
        ');
        return $stmt->execute([$playlistId, $userId]) && $stmt->rowCount() > 0;
    }

    public function deleteByPlaylistId(string $playlistId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM playlist_permissions WHERE playlist_id = ?');
        return $stmt->execute([$playlistId]);
    }

    public function userHasPermission(string $playlistId, string $userId, string $requiredPermission = 'view'): bool
    {
        $permission = $this->findUserPermission($playlistId, $userId);

        if (!$permission instanceof \App\Models\PlaylistPermission) {
            return false;
        }

        if ($requiredPermission === 'view') {
            return $permission->canView();
        }

        if ($requiredPermission === 'edit') {
            return $permission->canEdit();
        }

        return false;
    }

    public function countByPlaylistId(string $playlistId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM playlist_permissions WHERE playlist_id = ?
        ');
        $stmt->execute([$playlistId]);

        return (int) $stmt->fetchColumn();
    }
}
