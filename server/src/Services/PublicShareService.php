<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PublicShare;
use App\Repository\PublicShareRepository;
use App\Repository\SongRepository;
use App\Repository\AlbumRepository;
use App\Repository\PlaylistRepository;
use App\Repository\PlaylistSongRepository;
use DateTime;
use InvalidArgumentException;
use Exception;

final class PublicShareService
{
    private PublicShareRepository $publicShareRepository;
    private PlaylistRepository $playlistRepository;
    private PlaylistSongRepository $playlistSongRepository;

    public function __construct()
    {
        $this->publicShareRepository = new PublicShareRepository();
        $this->playlistRepository = new PlaylistRepository();
        $this->playlistSongRepository = new PlaylistSongRepository();
    }

    /** @param array<string, mixed> $data */
    public function createShare(array $data): PublicShare
    {
        $this->validateShareData($data);

        // Check if item exists
        if (!$this->itemExists($data['type'], $data['item_id'])) {
            throw new InvalidArgumentException('Item not found');
        }

        // Check if share already exists for this item
        $existingShares = $this->publicShareRepository->findByTypeAndItemId($data['type'], $data['item_id']);
        if ($existingShares !== []) {
            throw new InvalidArgumentException('Public share already exists for this item');
        }

        return $this->publicShareRepository->create($data);
    }

    /** @return array{share: array<string, mixed>, content: array<string, mixed>} */
    public function getShareContent(string $uuid, ?string $password = null): array
    {
        $share = $this->publicShareRepository->findByUuid($uuid);

        if (!$share instanceof \App\Models\PublicShare) {
            throw new InvalidArgumentException('Share not found');
        }

        if ($share->isExpired()) {
            throw new InvalidArgumentException('Share has expired');
        }

        // Check password if required
        if ($share->getPasswordHash() && ($password === null || $password === '' || $password === '0')) {
            throw new InvalidArgumentException('Password required');
        }

        if ($share->getPasswordHash() && !$this->publicShareRepository->verifyPassword($uuid, $password)) {
            throw new InvalidArgumentException('Invalid password');
        }

        // Increment access count
        $this->publicShareRepository->incrementAccessCount($uuid);

        // Get content based on type
        $content = $this->getContentByType($share->getType(), $share->getItemId());

        return [
            'share' => $share->toArray(),
            'content' => $content
        ];
    }

    /** @return array<string, mixed> */
    public function getShareStats(string $userId, bool $isAdmin): array
    {
        if ($isAdmin) {
            return [
                'total_shares' => $this->publicShareRepository->countAll(),
                'active_shares' => $this->publicShareRepository->countActive(),
                'expired_shares' => $this->publicShareRepository->countExpired(),
                'password_protected' => $this->publicShareRepository->countPasswordProtected(),
                'by_type' => $this->publicShareRepository->countByType()
            ];
        }
        return [
            'user_shares' => $this->publicShareRepository->countByCreatedBy($userId),
            'user_by_type' => $this->publicShareRepository->countByTypeAndUser($userId)
        ];
    }

    public function validateExpirationDate(string $expiresAt): bool
    {
        try {
            $expirationDate = new DateTime($expiresAt);
            $now = new DateTime();
            $maxDate = new DateTime('+1 year');

            return $expirationDate > $now && $expirationDate <= $maxDate;
        } catch (Exception $e) {
            return false;
        }
    }

    /** @return array<int, array{label: string, value: string|null}> */
    public function getExpirationOptions(): array
    {
        return [
            [
                'label' => 'Never',
                'value' => null
            ],
            [
                'label' => '1 Hour',
                'value' => '+1 hour'
            ],
            [
                'label' => '24 Hours',
                'value' => '+1 day'
            ],
            [
                'label' => '7 Days',
                'value' => '+1 week'
            ],
            [
                'label' => '30 Days',
                'value' => '+1 month'
            ],
            [
                'label' => '3 Months',
                'value' => '+3 months'
            ],
            [
                'label' => '6 Months',
                'value' => '+6 months'
            ],
            [
                'label' => '1 Year',
                'value' => '+1 year'
            ]
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateShareData(array $data): void
    {
        $allowedTypes = ['song', 'album', 'playlist'];

        if (!isset($data['type']) || !in_array($data['type'], $allowedTypes)) {
            throw new InvalidArgumentException('Invalid share type');
        }

        if (!isset($data['item_id']) || in_array(trim($data['item_id']), ['', '0'], true)) {
            throw new InvalidArgumentException('Item ID is required');
        }

        if (!isset($data['created_by']) || in_array(trim($data['created_by']), ['', '0'], true)) {
            throw new InvalidArgumentException('Creator user ID is required');
        }

        if (isset($data['expires_at']) && $data['expires_at'] && !$this->validateExpirationDate($data['expires_at'])) {
            throw new InvalidArgumentException('Invalid expiration date');
        }
    }

    private function itemExists(string $type, string $itemId): bool
    {
        switch ($type) {
            case 'song':
                $songRepo = new SongRepository(null);
                return $songRepo->findById($itemId) instanceof \App\Models\Song;
            case 'album':
                $albumRepo = new AlbumRepository(null);
                return $albumRepo->findById($itemId) instanceof \App\Models\Album;
            case 'playlist':
                return $this->playlistRepository->findById($itemId) instanceof \App\Models\Playlist;
            default:
                return false;
        }
    }

    /** @return array{type: string, item: array<string, mixed>, songs: array<int, array<string, mixed>>} */
    private function getContentByType(string $type, string $itemId): array
    {
        switch ($type) {
            case 'song':
                // Use repository without user ID for public access
                $songRepo = new SongRepository(null);
                $song = $songRepo->findById($itemId);
                if (!$song instanceof \App\Models\Song) {
                    throw new InvalidArgumentException('Song not found');
                }
                return [
                    'type' => 'song',
                    'item' => $song->toArray(),
                    'songs' => [$song->toArray()] // For consistency with other types
                ];

            case 'album':
                // Use repositories without user ID for public access
                $albumRepo = new AlbumRepository(null);
                $songRepo = new SongRepository(null);
                $album = $albumRepo->findById($itemId);
                if (!$album instanceof \App\Models\Album) {
                    throw new InvalidArgumentException('Album not found');
                }
                $songs = $songRepo->findByAlbumId($itemId);
                return [
                    'type' => 'album',
                    'item' => $album->toArray(),
                    'songs' => array_map(fn($song) => $song->toArray(), $songs)
                ];

            case 'playlist':
                $playlist = $this->playlistRepository->findById($itemId);
                if (!$playlist instanceof \App\Models\Playlist) {
                    throw new InvalidArgumentException('Playlist not found');
                }

                // Convert to array and handle both id formats
                $playlistArray = $playlist->toArray();
                $playlistId = $playlistArray['playlist_id'] ?? $playlistArray['id'] ?? $itemId;
                $songs = $this->playlistSongRepository->findByPlaylistId($playlistId);
                return [
                    'type' => 'playlist',
                    'item' => $playlistArray,
                    'songs' => $songs
                ];

            default:
                throw new InvalidArgumentException('Invalid share type');
        }
    }

    public function cleanupExpiredShares(): int
    {
        return $this->publicShareRepository->cleanupExpiredShares();
    }

    public function generateShareUrl(string $baseUrl, string $uuid): string
    {
        return rtrim($baseUrl, '/') . '/share/' . $uuid;
    }
}
