<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use JsonSerializable;

final class PlaylistSong implements JsonSerializable
{
    private int $id;
    private string $playlistId;
    private string $songId;
    private float $position;
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
            throw new InvalidArgumentException('Invalid playlist song ID');
        }

        if (!isset($data['playlist_id']) || !is_string($data['playlist_id']) || trim($data['playlist_id']) === '') {
            throw new InvalidArgumentException('Invalid playlist ID');
        }

        if (!isset($data['song_id']) || !is_string($data['song_id']) || trim($data['song_id']) === '') {
            throw new InvalidArgumentException('Invalid song ID');
        }

        if (!isset($data['position']) || !is_numeric($data['position']) || $data['position'] < 0) {
            throw new InvalidArgumentException('Invalid position');
        }

        $this->id = $data['id'];
        $this->playlistId = $data['playlist_id'];
        $this->songId = trim($data['song_id']);
        $this->position = (float) $data['position'];
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

    public function getSongId(): string
    {
        return $this->songId;
    }

    public function getPosition(): float
    {
        return $this->position;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'playlist_id' => $this->playlistId,
            'song_id' => $this->songId,
            'position' => $this->position,
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

        if (!isset($data['song_id']) || !is_string($data['song_id']) || trim($data['song_id']) === '') {
            throw new InvalidArgumentException('Invalid song ID');
        }

        $position = $data['position'] ?? 1.0;
        if (!is_numeric($position) || $position < 0) {
            throw new InvalidArgumentException('Invalid position');
        }

        return [
            'playlist_id' => $data['playlist_id'],
            'song_id' => trim($data['song_id']),
            'position' => (float) $position,
        ];
    }
}
