<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use JsonSerializable;

final class PlaylistPermission implements JsonSerializable
{
    private const PERMISSION_VIEW = 'view';
    private const PERMISSION_EDIT = 'edit';

    private const ALLOWED_PERMISSIONS = [self::PERMISSION_VIEW, self::PERMISSION_EDIT];

    private int $id;
    private string $playlistId;
    private string $userId;
    private string $permissionType;
    private string $createdAt;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->validateAndSetData($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateAndSetData(array $data): void
    {
        if (!isset($data['id']) || !is_int($data['id']) || $data['id'] <= 0) {
            throw new InvalidArgumentException('Invalid permission ID');
        }

        if (!isset($data['playlist_id']) || !is_string($data['playlist_id']) || trim($data['playlist_id']) === '') {
            throw new InvalidArgumentException('Invalid playlist ID');
        }

        if (!isset($data['user_id']) || !is_string($data['user_id']) || trim($data['user_id']) === '') {
            throw new InvalidArgumentException('Invalid user ID');
        }

        if (!isset($data['permission_type']) || !in_array($data['permission_type'], self::ALLOWED_PERMISSIONS, true)) {
            throw new InvalidArgumentException('Invalid permission type');
        }

        $this->id = $data['id'];
        $this->playlistId = $data['playlist_id'];
        $this->userId = trim($data['user_id']);
        $this->permissionType = $data['permission_type'];
        $this->createdAt = $data['created_at'] ?? '';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPlaylistId(): string
    {
        return $this->playlistId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getPermissionType(): string
    {
        return $this->permissionType;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function canView(): bool
    {
        return true;
    }

    public function canEdit(): bool
    {
        return $this->permissionType === self::PERMISSION_EDIT;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'playlist_id' => $this->playlistId,
            'user_id' => $this->userId,
            'permission_type' => $this->permissionType,
            'can_view' => $this->canView(),
            'can_edit' => $this->canEdit(),
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function createData(array $data): array
    {
        if (!isset($data['playlist_id']) || !is_string($data['playlist_id']) || trim($data['playlist_id']) === '') {
            throw new InvalidArgumentException('Invalid playlist ID');
        }

        if (!isset($data['user_id']) || !is_string($data['user_id']) || trim($data['user_id']) === '') {
            throw new InvalidArgumentException('Invalid user ID');
        }

        $permissionType = $data['permission_type'] ?? self::PERMISSION_VIEW;
        if (!in_array($permissionType, self::ALLOWED_PERMISSIONS, true)) {
            throw new InvalidArgumentException('Invalid permission type');
        }

        return [
            'playlist_id' => $data['playlist_id'],
            'user_id' => trim($data['user_id']),
            'permission_type' => $permissionType,
        ];
    }

    public static function isValidPermissionType(string $permissionType): bool
    {
        return in_array($permissionType, self::ALLOWED_PERMISSIONS, true);
    }
}
