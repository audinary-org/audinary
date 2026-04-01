<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Playlist model for the new playlist system
 */
final class Playlist implements JsonSerializable
{
    private const TYPE_USER = 'user';
    private const TYPE_GLOBAL = 'global';
    private const TYPE_SMART = 'smart';

    private const ALLOWED_TYPES = [self::TYPE_USER, self::TYPE_GLOBAL, self::TYPE_SMART];

    private string $id;
    private string $userId;
    private string $type;
    private string $name;
    private ?string $description;
    private int $songCount;
    private int $duration;
    private ?string $coverImageUuid;
    private string $createdAt;
    private string $updatedAt;
    private bool $isFavorite;
    /** @var array<string, mixed>|null */
    private ?array $rules;
    private ?string $smartSortBy;
    private string $smartSortDirection;
    private ?int $smartLimit;

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
        if (!isset($data['id']) || (string) $data['id'] === '') {
            throw new InvalidArgumentException('Invalid playlist ID');
        }
        $data['id'] = (string) $data['id'];

        if (!isset($data['user_id']) || !is_string($data['user_id']) || trim($data['user_id']) === '') {
            throw new InvalidArgumentException('Invalid user ID');
        }

        if (!isset($data['type']) || !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid playlist type');
        }

        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            throw new InvalidArgumentException('Playlist name cannot be empty');
        }

        if (strlen(trim($data['name'])) > 255) {
            throw new InvalidArgumentException('Playlist name cannot exceed 255 characters');
        }

        $this->id = trim($data['id']);
        $this->userId = trim($data['user_id']);
        $this->type = $data['type'];
        $this->name = trim($data['name']);
        $this->description = isset($data['description']) ? trim($data['description']) : null;
        $this->songCount = max(0, (int)($data['song_count'] ?? 0));
        $this->duration = max(0, (int)($data['duration'] ?? 0));
        $this->coverImageUuid = isset($data['cover_image_uuid']) ? trim($data['cover_image_uuid']) : null;
        $this->createdAt = $data['created_at'] ?? '';
        $this->updatedAt = $data['updated_at'] ?? '';
        $this->isFavorite = !empty($data['is_favorite']);

        // Smart playlist fields
        $rules = $data['rules'] ?? null;
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }
        $this->rules = is_array($rules) ? $rules : null;
        $this->smartSortBy = isset($data['smart_sort_by']) && $data['smart_sort_by'] !== '' ? $data['smart_sort_by'] : null;
        $this->smartSortDirection = $data['smart_sort_direction'] ?? 'asc';
        $this->smartLimit = isset($data['smart_limit']) ? (int) $data['smart_limit'] : null;
        if ($this->smartLimit !== null && $this->smartLimit <= 0) {
            $this->smartLimit = null;
        }

        if ($this->description !== null && strlen($this->description) > 1000) {
            throw new InvalidArgumentException('Playlist description cannot exceed 1000 characters');
        }

        if ($this->description === '') {
            $this->description = null;
        }

        if ($this->coverImageUuid === '') {
            $this->coverImageUuid = null;
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSongCount(): int
    {
        return $this->songCount;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function getCoverImageUuid(): ?string
    {
        return $this->coverImageUuid;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    public function isFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function isUserPlaylist(): bool
    {
        return $this->type === self::TYPE_USER;
    }

    public function isGlobalPlaylist(): bool
    {
        return $this->type === self::TYPE_GLOBAL;
    }

    public function isSmartPlaylist(): bool
    {
        return $this->type === self::TYPE_SMART;
    }

    /** @return array<string, mixed>|null */
    public function getRules(): ?array
    {
        return $this->rules;
    }

    public function getSmartSortBy(): ?string
    {
        return $this->smartSortBy;
    }

    public function getSmartSortDirection(): string
    {
        return $this->smartSortDirection;
    }

    public function getSmartLimit(): ?int
    {
        return $this->smartLimit;
    }

    public function hasCoverImage(): bool
    {
        return $this->coverImageUuid !== null;
    }

    public function getCoverUrl(): ?string
    {
        if ($this->coverImageUuid === null) {
            return null;
        }

        return "/img/userdata/playlists/playlist_{$this->coverImageUuid}.webp";
    }

    public function getDurationFormatted(): string
    {
        if ($this->duration <= 0) {
            return '0:00';
        }

        $hours = intval($this->duration / 3600);
        $minutes = intval(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'user_id' => $this->userId,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'song_count' => $this->songCount,
            'duration' => $this->duration,
            'duration_formatted' => $this->getDurationFormatted(),
            'cover_image_uuid' => $this->coverImageUuid,
            'cover_url' => $this->getCoverUrl(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'is_favorite' => $this->isFavorite,
        ];

        if ($this->isSmartPlaylist()) {
            $data['rules'] = $this->rules;
            $data['smart_sort_by'] = $this->smartSortBy;
            $data['smart_sort_direction'] = $this->smartSortDirection;
            $data['smart_limit'] = $this->smartLimit;
        }

        return $data;
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
        if (!isset($data['user_id']) || !is_string($data['user_id']) || trim($data['user_id']) === '') {
            throw new InvalidArgumentException('Invalid user ID');
        }

        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            throw new InvalidArgumentException('Playlist name cannot be empty');
        }

        $name = trim($data['name']);
        if (strlen($name) > 255) {
            throw new InvalidArgumentException('Playlist name cannot exceed 255 characters');
        }

        $description = isset($data['description']) ? trim($data['description']) : null;
        if ($description === '') {
            $description = null;
        }

        if ($description !== null && strlen($description) > 1000) {
            throw new InvalidArgumentException('Playlist description cannot exceed 1000 characters');
        }

        $type = $data['type'] ?? self::TYPE_USER;
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid playlist type');
        }

        $result = [
            'user_id' => trim($data['user_id']),
            'type' => $type,
            'name' => $name,
            'description' => $description,
        ];

        if ($type === self::TYPE_SMART) {
            $rules = $data['rules'] ?? null;
            if (!is_array($rules) || empty($rules['conditions'])) {
                throw new InvalidArgumentException('Smart playlists require at least one rule');
            }
            $result['rules'] = $rules;
            $result['smart_sort_by'] = $data['smart_sort_by'] ?? null;
            $result['smart_sort_direction'] = $data['smart_sort_direction'] ?? 'asc';
            $result['smart_limit'] = isset($data['smart_limit']) ? (int) $data['smart_limit'] : null;
        }

        return $result;
    }
}
