<?php

namespace App\Services;

use App\Repository\PlayHistoryRepository;
use Exception;
use RuntimeException;

class PlayHistoryService
{
    private PlayHistoryRepository $playHistoryRepository;

    public function __construct(string $userId)
    {
        $this->playHistoryRepository = new PlayHistoryRepository($userId);
    }

    /** @return array<string, mixed> */
    public function logSongPlay(string $songId): array
    {
        try {
            return $this->playHistoryRepository->logSongPlay($songId);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to log song play: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @return array<string, mixed> */
    public function logAlbumPlay(string $albumId): array
    {
        try {
            return $this->playHistoryRepository->logAlbumPlay($albumId);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to log album play: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @return array<string, mixed> */
    public function logArtistPlay(string $artistId): array
    {
        try {
            return $this->playHistoryRepository->logArtistPlay($artistId);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to log artist play: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getRecentPlays(int $limit = 50): array
    {
        try {
            return $this->playHistoryRepository->getRecentPlays($limit);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get recent plays: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getMostPlayedSongs(int $limit = 50): array
    {
        try {
            return $this->playHistoryRepository->getMostPlayedSongs($limit);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get most played songs: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @return array<string, mixed> */
    public function getPlayStatistics(): array
    {
        try {
            return $this->playHistoryRepository->getPlayStats();
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get play statistics: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @return array<int, string> */
    public function validatePlayRequest(string $entityId, string $entityType): array
    {
        $errors = [];

        // Validate UUID format
        if (in_array(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $entityId), [0, false], true)) {
            $errors[] = "Invalid {$entityType} ID format";
        }

        // Validate entity type
        if (!in_array($entityType, ['song', 'album', 'artist'])) {
            $errors[] = "Invalid entity type: {$entityType}";
        }

        return $errors;
    }
}
