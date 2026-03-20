<?php

declare(strict_types=1);

namespace App\Models;

use Exception;
use InvalidArgumentException;
use JsonSerializable;
use DateTime;

final class PublicShare implements JsonSerializable
{
    private const TYPE_SONG = 'song';
    private const TYPE_ALBUM = 'album';
    private const TYPE_PLAYLIST = 'playlist';

    private const ALLOWED_TYPES = [self::TYPE_SONG, self::TYPE_ALBUM, self::TYPE_PLAYLIST];

    private string $id;
    private string $type;
    private string $itemId;
    private string $shareUuid;
    private ?string $name;
    private bool $downloadEnabled;
    private ?string $expiresAt;
    private int $accessCount;
    private ?string $passwordHash;
    private string $createdAt;
    private string $createdBy;

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
        if (!isset($data['id']) || !is_string($data['id']) || trim($data['id']) === '') {
            throw new InvalidArgumentException('Invalid public share ID');
        }

        if (!isset($data['type']) || !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid share type');
        }

        if (!isset($data['item_id']) || !is_string($data['item_id']) || trim($data['item_id']) === '') {
            throw new InvalidArgumentException('Invalid item ID');
        }

        if (!isset($data['share_uuid']) || !is_string($data['share_uuid']) || trim($data['share_uuid']) === '') {
            throw new InvalidArgumentException('Invalid share UUID');
        }

        if (!isset($data['created_by']) || !is_string($data['created_by']) || trim($data['created_by']) === '') {
            throw new InvalidArgumentException('Invalid creator user ID');
        }

        $this->id = trim($data['id']);
        $this->type = $data['type'];
        $this->itemId = trim($data['item_id']);
        $this->shareUuid = trim($data['share_uuid']);
        $this->name = empty($data['name']) ? null : trim($data['name']);
        $this->downloadEnabled = (bool) ($data['download_enabled'] ?? false);
        $this->expiresAt = empty($data['expires_at']) ? null : trim($data['expires_at']);
        $this->accessCount = max(0, (int) ($data['access_count'] ?? 0));
        $this->passwordHash = empty($data['password_hash']) ? null : trim($data['password_hash']);
        $this->createdAt = $data['created_at'] ?? '';
        $this->createdBy = trim($data['created_by']);

        if ($this->expiresAt === '') {
            $this->expiresAt = null;
        }

        if ($this->passwordHash === '') {
            $this->passwordHash = null;
        }

        if ($this->name === '') {
            $this->name = null;
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getShareUuid(): string
    {
        return $this->shareUuid;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function isDownloadEnabled(): bool
    {
        return $this->downloadEnabled;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function getAccessCount(): int
    {
        return $this->accessCount;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        try {
            $expirationDate = new DateTime($this->expiresAt);
            $now = new DateTime();
            return $now > $expirationDate;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'item_id' => $this->itemId,
            'share_uuid' => $this->shareUuid,
            'name' => $this->name,
            'download_enabled' => $this->downloadEnabled,
            'expires_at' => $this->expiresAt,
            'access_count' => $this->accessCount,
            'has_password' => $this->passwordHash !== null,
            'is_expired' => $this->isExpired(),
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
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
        if (!isset($data['type']) || !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid share type');
        }

        if (!isset($data['item_id']) || !is_string($data['item_id']) || trim($data['item_id']) === '') {
            throw new InvalidArgumentException('Invalid item ID');
        }

        if (!isset($data['created_by']) || !is_string($data['created_by']) || trim($data['created_by']) === '') {
            throw new InvalidArgumentException('Invalid creator user ID');
        }

        $downloadEnabled = (bool) ($data['download_enabled'] ?? false);
        $expiresAt = empty($data['expires_at']) ? null : trim($data['expires_at']);

        if ($expiresAt !== null && $expiresAt !== '') {
            try {
                new DateTime($expiresAt);
            } catch (Exception $e) {
                throw new InvalidArgumentException('Invalid expiration date format', $e->getCode(), $e);
            }
        } else {
            $expiresAt = null;
        }

        $passwordHash = null;
        if (isset($data['password']) && is_string($data['password']) && trim($data['password']) !== '') {
            $passwordHash = password_hash(trim($data['password']), PASSWORD_DEFAULT);
        }

        return [
            'type' => $data['type'],
            'item_id' => trim($data['item_id']),
            'share_uuid' => self::generateUuid(),
            'name' => isset($data['name']) && is_string($data['name']) && trim($data['name']) !== '' ? trim($data['name']) : null,
            'download_enabled' => $downloadEnabled,
            'expires_at' => $expiresAt,
            'password_hash' => $passwordHash,
            'created_by' => trim($data['created_by']),
        ];
    }

    private static function generateUuid(): string
    {
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
