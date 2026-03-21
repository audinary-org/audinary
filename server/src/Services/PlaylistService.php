<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Playlist;
use App\Models\PlaylistSong;
use App\Models\PlaylistPermission;
use App\Repository\PlaylistRepository;
use App\Repository\PlaylistSongRepository;
use App\Repository\PlaylistPermissionRepository;
use App\Repository\SmartPlaylistRepository;
use App\Services\PlaylistCollageService;
use Exception;
use InvalidArgumentException;

final class PlaylistService
{
    private PlaylistRepository $playlistRepository;
    private PlaylistSongRepository $playlistSongRepository;
    private PlaylistPermissionRepository $permissionRepository;
    private PlaylistCollageService $collageService;
    private SmartPlaylistRepository $smartPlaylistRepository;
    private string $userId;

    public function __construct(
        PlaylistRepository $playlistRepository,
        PlaylistSongRepository $playlistSongRepository,
        PlaylistPermissionRepository $permissionRepository,
        PlaylistCollageService $collageService,
        string $userId,
        ?SmartPlaylistRepository $smartPlaylistRepository = null
    ) {
        $this->playlistRepository = $playlistRepository;
        $this->playlistSongRepository = $playlistSongRepository;
        $this->permissionRepository = $permissionRepository;
        $this->collageService = $collageService;
        $this->smartPlaylistRepository = $smartPlaylistRepository ?? new SmartPlaylistRepository();
        $this->userId = $userId;
    }

    /**
     * Get user's playlists
     * @return array<int, Playlist>
     */
    public function getUserPlaylists(int $offset = 0, int $limit = 50): array
    {
        return $this->playlistRepository->findByUserId($this->userId, $offset, $limit);
    }

    /**
     * Get playlists shared with user
     * @return array<int, Playlist>
     */
    public function getSharedPlaylists(int $offset = 0, int $limit = 50): array
    {
        return $this->playlistRepository->findSharedWithUser($this->userId, $offset, $limit);
    }

    public function getPlaylist(string $playlistId): ?Playlist
    {
        $playlist = $this->playlistRepository->findById($playlistId);

        if (!$playlist instanceof \App\Models\Playlist) {
            return null;
        }

        if (!$this->canUserAccessPlaylist($playlistId, 'view')) {
            return null;
        }

        return $playlist;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPlaylistWithSongs(string $playlistId): ?array
    {
        $playlist = $this->getPlaylist($playlistId);

        if (!$playlist instanceof \App\Models\Playlist) {
            return null;
        }

        if ($playlist->isSmartPlaylist()) {
            $rules = $playlist->getRules();
            if (!is_array($rules) || empty($rules['conditions'])) {
                return [
                    'playlist' => $playlist->toArray(),
                    'songs' => [],
                ];
            }

            $songs = $this->smartPlaylistRepository->resolveSongs(
                $rules,
                $playlist->getSmartSortBy(),
                $playlist->getSmartSortDirection(),
                $playlist->getSmartLimit(),
                $this->userId
            );

            $stats = $this->smartPlaylistRepository->getSmartPlaylistStats($rules, $this->userId, $playlist->getSmartLimit());
            $playlistArray = $playlist->toArray();
            $playlistArray['song_count'] = $stats['song_count'];
            $playlistArray['duration'] = $stats['duration'];

            return [
                'playlist' => $playlistArray,
                'songs' => $songs,
            ];
        }

        $songs = $this->playlistSongRepository->findByPlaylistId($playlistId);

        return [
            'playlist' => $playlist->toArray(),
            'songs' => $songs,
        ];
    }

    /**
     * Get all smart playlists (for all users to see)
     * @return array<int, Playlist>
     */
    public function getSmartPlaylists(): array
    {
        return $this->playlistRepository->findAllSmart();
    }

    /**
     * Get stats (song_count, duration) for a smart playlist's rules
     * @param array<string, mixed> $rules
     * @return array{song_count: int, duration: int}
     */
    public function getSmartPlaylistStats(array $rules, ?int $limit = null): array
    {
        return $this->smartPlaylistRepository->getSmartPlaylistStats($rules, $this->userId, $limit);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createPlaylist(array $data): Playlist
    {
        $data['user_id'] = $this->userId;
        return $this->playlistRepository->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updatePlaylist(string $playlistId, array $data): ?Playlist
    {
        if (!$this->canUserAccessPlaylist($playlistId, 'edit')) {
            return null;
        }

        return $this->playlistRepository->update($playlistId, $data);
    }

    public function deletePlaylist(string $playlistId): bool
    {
        if (!$this->playlistRepository->userOwnsPlaylist($this->userId, $playlistId)) {
            return false;
        }

        // Delete collage before deleting playlist
        $this->collageService->deleteCollage($playlistId);

        $this->playlistSongRepository->deleteByPlaylistId($playlistId);
        $this->permissionRepository->deleteByPlaylistId($playlistId);

        return $this->playlistRepository->delete($playlistId);
    }

    public function addSongToPlaylist(string $playlistId, string $songId, ?float $position = null): ?PlaylistSong
    {
        $playlist = $this->playlistRepository->findById($playlistId);
        if ($playlist && $playlist->isSmartPlaylist()) {
            throw new InvalidArgumentException('Cannot modify songs in a smart playlist');
        }

        if (!$this->canUserAccessPlaylist($playlistId, 'edit')) {
            error_log("PlaylistService::addSongToPlaylist - Access denied for playlist $playlistId");
            return null;
        }

        if ($this->playlistSongRepository->songExistsInPlaylist($playlistId, $songId)) {
            error_log("PlaylistService::addSongToPlaylist - Song already exists in playlist");
            return null;
        }


        try {
            $result = $this->playlistSongRepository->add($playlistId, $songId, $position);

            // Update song_count and duration in playlists table
            $this->playlistRepository->updateCounts($playlistId);

            // Generate new collage with updated playlist
            $this->collageService->generateCollage($playlistId);

            return $result;
        } catch (Exception $e) {
            error_log("PlaylistService::addSongToPlaylist - Exception: " . $e->getMessage());
            throw $e;
        }
    }

    public function removeSongFromPlaylist(string $playlistId, string $songId): bool
    {
        $playlist = $this->playlistRepository->findById($playlistId);
        if ($playlist && $playlist->isSmartPlaylist()) {
            throw new InvalidArgumentException('Cannot modify songs in a smart playlist');
        }

        if (!$this->canUserAccessPlaylist($playlistId, 'edit')) {
            return false;
        }

        $success = $this->playlistSongRepository->remove($playlistId, $songId);

        if ($success) {
            // Update song_count and duration in playlists table
            $this->playlistRepository->updateCounts($playlistId);

            // Regenerate collage after removing song
            $this->collageService->generateCollage($playlistId);
        }

        return $success;
    }

    /**
     * @param array<string, mixed> $songPositions
     */
    public function reorderPlaylistSongs(string $playlistId, array $songPositions): bool
    {
        $playlist = $this->playlistRepository->findById($playlistId);
        if ($playlist && $playlist->isSmartPlaylist()) {
            throw new InvalidArgumentException('Cannot reorder songs in a smart playlist');
        }

        if (!$this->canUserAccessPlaylist($playlistId, 'edit')) {
            return false;
        }

        $success = $this->playlistSongRepository->reorderSongs($playlistId, $songPositions);

        if ($success) {
            // Regenerate collage after reordering (order might affect which covers are shown)
            $this->collageService->generateCollage($playlistId);
        }

        return $success;
    }

    public function sharePlaylist(string $playlistId, string $targetUserId, string $permissionType = 'view'): ?PlaylistPermission
    {
        if (!$this->playlistRepository->userOwnsPlaylist($this->userId, $playlistId)) {
            return null;
        }

        if (!PlaylistPermission::isValidPermissionType($permissionType)) {
            throw new InvalidArgumentException('Invalid permission type');
        }

        $existingPermission = $this->permissionRepository->findUserPermission($playlistId, $targetUserId);
        if ($existingPermission instanceof \App\Models\PlaylistPermission) {
            return $this->permissionRepository->update($existingPermission->getId(), $permissionType);
        }

        /** @var array<string, string> $permissionData */
        $permissionData = [
            'playlist_id' => $playlistId,
            'user_id' => $targetUserId,
            'permission_type' => $permissionType
        ];
        return $this->permissionRepository->create($permissionData);
    }

    public function unsharePlaylist(string $playlistId, string $targetUserId): bool
    {
        if (!$this->playlistRepository->userOwnsPlaylist($this->userId, $playlistId)) {
            return false;
        }

        return $this->permissionRepository->deleteByPlaylistAndUser($playlistId, $targetUserId);
    }

    /**
     * @return array<int, PlaylistPermission>
     */
    public function getPlaylistPermissions(string $playlistId): array
    {
        if (!$this->playlistRepository->userOwnsPlaylist($this->userId, $playlistId)) {
            return [];
        }

        return $this->permissionRepository->findByPlaylistId($playlistId);
    }

    /**
     * @return array<int, Playlist>
     */
    public function searchPlaylists(string $query, int $limit = 20): array
    {
        return $this->playlistRepository->searchByName($query, $this->userId, $limit);
    }

    public function countUserPlaylists(): int
    {
        return $this->playlistRepository->countByUserId($this->userId);
    }

    private function canUserAccessPlaylist(string $playlistId, string $requiredPermission = 'view'): bool
    {
        if ($this->playlistRepository->userOwnsPlaylist($this->userId, $playlistId)) {
            return true;
        }

        $playlist = $this->playlistRepository->findById($playlistId);
        if ($playlist && ($playlist->isGlobalPlaylist() || $playlist->isSmartPlaylist()) && $requiredPermission === 'view') {
            return true;
        }

        return $this->permissionRepository->userHasPermission($playlistId, $this->userId, $requiredPermission);
    }
}
