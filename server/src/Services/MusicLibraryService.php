<?php

namespace App\Services;

use App\Repository\SongRepository;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Repository\DecadeRepository;
use Exception;
use RuntimeException;

class MusicLibraryService
{
    private SongRepository $songRepository;
    private AlbumRepository $albumRepository;
    private ArtistRepository $artistRepository;
    private DecadeRepository $decadeRepository;

    public function __construct(string $userId)
    {
        $this->songRepository = new SongRepository($userId);
        $this->albumRepository = new AlbumRepository($userId);
        $this->artistRepository = new ArtistRepository($userId);
        $this->decadeRepository = new DecadeRepository($userId);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getSongs(array $params = [], bool $favoriteOnly = false): array
    {
        try {
            $songs = $this->songRepository->findAll($params, $favoriteOnly);
            return array_map(fn($song) => $song->toArray(), $songs);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get songs: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getAlbums(array $params = [], bool $favoriteOnly = false): array
    {
        try {
            $albums = $this->albumRepository->findAll($params, $favoriteOnly);
            return array_map(fn($album) => $album->toArray(), $albums);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get albums: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getArtists(array $params = [], bool $favoriteOnly = false): array
    {
        try {
            $artists = $this->artistRepository->findAll($params, $favoriteOnly);
            return array_map(fn($artist) => $artist->toArray(), $artists);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get artists: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAlbumSongs(string $albumId): array
    {
        try {
            $songs = $this->songRepository->findByAlbumId($albumId);
            return array_map(fn($song) => $song->toArray(), $songs);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get album songs: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getArtistSongs(string $artistIdentifier, bool $random = false, int $maxLimit = 250): array
    {
        try {
            $songs = $this->songRepository->findByArtistIdentifier($artistIdentifier, $random, $maxLimit);
            return array_map(fn($song) => $song->toArray(), $songs);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get artist songs: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllDecades(): array
    {
        try {
            $decades = $this->decadeRepository->findAll();
            return array_map(fn($decade) => $decade->toArray(), $decades);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get decades: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getAlbumsByDecade(int $startYear, array $params = []): array
    {
        try {
            $albums = $this->albumRepository->findByDecade($startYear, $params);
            return array_map(fn($album) => $album->toArray(), $albums);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get albums by decade: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSongById(string $songId): ?array
    {
        try {
            $song = $this->songRepository->findById($songId);
            return $song instanceof \App\Models\Song ? $song->toArray() : null;
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get song: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAlbumById(string $albumId): ?array
    {
        try {
            $album = $this->albumRepository->findById($albumId);
            return $album instanceof \App\Models\Album ? $album->toArray() : null;
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get album: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getArtistById(string $artistId): ?array
    {
        try {
            $artist = $this->artistRepository->findById($artistId);
            return $artist instanceof \App\Models\Artist ? $artist->toArray() : null;
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get artist: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<string, int>
     */
    public function getLibraryStatistics(): array
    {
        // This would need to be implemented in Repository
        // For now, return basic structure
        return [
            'total_songs' => 0,
            'total_albums' => 0,
            'total_artists' => 0,
            'total_genres' => 0,
            'total_decades' => 0
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validateEntityId(string $entityId, string $entityType): array
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

        return ['errors' => $errors];
    }
}
