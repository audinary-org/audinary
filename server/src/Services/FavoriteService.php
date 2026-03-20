<?php

namespace App\Services;

use App\Repository\FavoriteRepository;
use App\Models\Favorite;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class FavoriteService
{
    private FavoriteRepository $favoriteRepository;

    public function __construct(string $userId)
    {
        $this->favoriteRepository = new FavoriteRepository($userId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFavorites(?string $type = null): array
    {
        try {
            $favorites = $this->favoriteRepository->findAllForUser($type);
            return array_map(fn($favorite) => $favorite->toArray(), $favorites);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get favorites: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addFavorite(array $data): array
    {
        try {
            $validationErrors = $this->validateFavoriteData($data);
            if ($validationErrors !== []) {
                throw new InvalidArgumentException(implode(', ', $validationErrors));
            }

            $success = $this->favoriteRepository->add($data);

            if (!$success) {
                throw new RuntimeException('Failed to add favorite');
            }

            return ["success" => true];
        } catch (Exception $e) {
            throw new RuntimeException("Failed to add favorite: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function removeFavorite(array $data): array
    {
        try {
            $validationErrors = $this->validateFavoriteData($data);
            if ($validationErrors !== []) {
                throw new InvalidArgumentException(implode(', ', $validationErrors));
            }

            $success = $this->favoriteRepository->remove($data);

            if (!$success) {
                throw new InvalidArgumentException("Favorit nicht gefunden");
            }

            return ["success" => true];
        } catch (Exception $e) {
            throw new RuntimeException("Failed to remove favorite: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function isFavorite(string $type, string $entityId): bool
    {
        try {
            return $this->favoriteRepository->isFavorite($type, $entityId);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to check favorite status: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getFavoriteCount(string $type): int
    {
        try {
            return $this->favoriteRepository->getCountByType($type);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get favorite count: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<string, int>
     */
    public function getAllFavoriteCounts(): array
    {
        try {
            return [
                'songs' => $this->favoriteRepository->getCountByType('song'),
                'albums' => $this->favoriteRepository->getCountByType('album'),
                'artists' => $this->favoriteRepository->getCountByType('artist'),
                'playlists' => $this->favoriteRepository->getCountByType('playlist'),
            ];
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get favorite counts: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function validateFavoriteData(array $data): array
    {
        $errors = [];

        if (empty($data['favorite_type'])) {
            $errors[] = "Favoriten-Typ fehlt";
        }

        $type = $data['favorite_type'] ?? '';
        if (!in_array($type, ['song', 'album', 'artist', 'playlist'])) {
            $errors[] = "Ungültiger Favoriten-Typ: $type";
        }

        switch ($type) {
            case 'song':
                if (empty($data['song_id'])) {
                    $errors[] = "Song-ID fehlt";
                }
                break;
            case 'album':
                if (empty($data['album_id'])) {
                    $errors[] = "Album-ID fehlt";
                }
                break;
            case 'artist':
                if (empty($data['artist_id'])) {
                    $errors[] = "Künstler-ID fehlt";
                }
                break;
            case 'playlist':
                if (empty($data['playlist_id'])) {
                    $errors[] = "Playlist-ID fehlt";
                }
                break;
        }

        return $errors;
    }
}
