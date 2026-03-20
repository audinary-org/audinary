<?php

namespace App\Interfaces;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Interface for MusicAPI implementations
 *
 * This interface defines the core methods that any MusicAPI implementation should provide.
 * It helps ensure consistency between the original and refactored implementations.
 */
interface MusicAPIInterface
{
    /**
     * Get the current user ID
     */
    public function getUserId(): string;

    /**
     * Get pagination parameters from request data
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getPaginationParams(array $params): array;

    /**
     * Create a JSON response
     *
     * @param array<string, mixed> $data
     */
    public function createJsonResponse(Response $response, array $data): Response;

    /**
     * Create an error response
     */
    public function createErrorResponse(Response $response, string $message, int $status = 400): Response;

    // Core music library methods
    /** @param array<string, mixed> $params
     * @return array<int, array<string, mixed>> */
    public function getSongs(array $params, bool $favoriteOnly = false): array;
    /** @param array<string, mixed> $params
     * @return array<int, array<string, mixed>> */
    public function getAlbums(array $params, bool $favoriteOnly = false): array;
    /** @param array<string, mixed> $params
     * @return array<int, array<string, mixed>> */
    public function getArtists(array $params, bool $favoriteOnly = false): array;
    /** @return array<int, array<string, mixed>> */
    public function getAlbumSongs(string $albumId): array;
    /** @return array<int, array<string, mixed>> */
    public function getArtistSongs(string $artistIdentifier, bool $random = false, int $maxLimit = 250): array;

    // Search methods
    /** @return array<string, mixed> */
    public function search(string $query, int $limit = 5): array;

    // Playlist methods
    /** @return array<string, mixed> */
    public function getPlaylists(): array;
    /** @return array<string, mixed> */
    public function getOwnedPlaylists(): array;
    /** @return array<string, mixed> */
    public function getSharedPlaylists(): array;
    /** @return array<string, mixed> */
    public function getPlaylist(string $playlistId): array;
    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    public function savePlaylist(array $data): array;
    /** @return array<string, mixed> */
    public function deletePlaylist(string $playlistId): array;
    /** @return array<string, mixed> */
    public function addSongToPlaylist(string $playlistId, string $songId): array;
    /** @return array<string, mixed> */
    public function removeSongFromPlaylist(string $playlistId, string $songId): array;
    /** @param array<int, string> $songIds
     * @return array<string, mixed> */
    public function reorderPlaylistSongs(string $playlistId, array $songIds): array;

    // Playlist favorites methods
    public function togglePlaylistFavorite(string $playlistId): bool;
    /** @return array<string, mixed> */
    public function getFavoritePlaylistsForNavbar(int $limit = 5): array;

    // Favorite methods
    /** @return array<int, array<string, mixed>> */
    public function getFavorites(?string $type = null): array;
    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    public function addFavorite(array $data): array;
    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    public function removeFavorite(array $data): array;

    // Play history methods
    /** @return array<string, mixed> */
    public function logSongPlay(string $songId): array;
    /** @return array<string, mixed> */
    public function logAlbumPlay(string $albumId): array;
    /** @return array<string, mixed> */
    public function logArtistPlay(string $artistId): array;

    // Genre and decade methods
    /** @return array<int, array<string, mixed>> */
    public function getAllGenres(): array;
    /** @return array<int, array<string, mixed>> */
    public function getAllDecades(): array;
    /** @return array<int, array<string, mixed>> */
    public function searchGenres(string $like, int $limit = 20): array;
    /** @param array<string, mixed> $params
     * @return array<int, array<string, mixed>> */
    public function getAlbumsByGenre(string $genre, array $params = []): array;
    /** @param array<string, mixed> $params
     * @return array<int, array<string, mixed>> */
    public function getAlbumsByDecade(int $startYear, array $params = []): array;

    // Statistics methods
    /** @return array<string, mixed> */
    public function getLibraryStatistics(): array;
    /** @return array<string, mixed> */
    public function getFavoriteStatistics(): array;
    /** @return array<string, mixed> */
    public function getPlayStatistics(): array;
    /** @return array<string, mixed> */
    public function getGenreStatistics(): array;

    // Streaming and playback methods
    public function playSong(string $uuid, \Psr\Http\Message\ServerRequestInterface $request, Response $response): Response;
    /** @return array<string, mixed> */
    public function recordPlayed(string $songId): array;

    // Genre and decade methods (additional)
    /** @return array<string, mixed> */
    public function getGenres(): array;
    /** @return array<string, mixed> */
    public function getDecades(): array;
}
