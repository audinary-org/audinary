<?php

namespace App\Services;

use Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use App\Services\MusicLibraryService;
use App\Services\MusicSearchService;
use App\Services\PlaylistService;
use App\Services\PlaylistCollageService;
use App\Services\FavoriteService;
use App\Services\PlayHistoryService;
use App\Services\GenreService;
use App\Services\MediaStreamer;
use App\Repository\PlaylistRepository;
use App\Repository\PlaylistSongRepository;
use App\Repository\PlaylistPermissionRepository;
use App\Interfaces\MusicAPIInterface;

/**
 * Refactored MusicAPI - Now acts as a coordinator between services
 *
 * This class maintains the original API interface while delegating
 * actual work to specialized services and Repository.
 */
class MusicAPI implements MusicAPIInterface
{
    private string $userId;
    private MusicLibraryService $musicLibraryService;
    private MusicSearchService $musicSearchService;
    private PlaylistService $playlistService;
    private FavoriteService $favoriteService;
    private PlayHistoryService $playHistoryService;
    private GenreService $genreService;
    private MediaStreamer $streamingService;

    public function __construct(string $userId)
    {
        if (in_array(trim($userId), ['', '0'], true)) {
            throw new InvalidArgumentException('User ID cannot be empty');
        }

        $this->userId = $userId;
        $this->musicLibraryService = new MusicLibraryService($userId);
        $this->musicSearchService = new MusicSearchService($userId);

        // Load configuration for collage service
        require_once __DIR__ . '/../configHelper.php';
        $config = loadConfig();

        $this->playlistService = new PlaylistService(
            new PlaylistRepository($userId),
            new PlaylistSongRepository(),
            new PlaylistPermissionRepository(),
            new PlaylistCollageService(new PlaylistSongRepository(), $config),
            $userId
        );
        $this->favoriteService = new FavoriteService($userId);
        $this->playHistoryService = new PlayHistoryService($userId);
        $this->genreService = new GenreService($userId);
        $this->streamingService = new MediaStreamer($config);
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, int>
     */
    public function getPaginationParams(array $params): array
    {
        $start = isset($params['start'])
            ? filter_var(
                $params['start'],
                FILTER_VALIDATE_INT,
                ['options' => ['default' => 0, 'min_range' => 0]]
            )
            : 0;
        $limit = isset($params['limit'])
            ? filter_var(
                $params['limit'],
                FILTER_VALIDATE_INT,
                ['options' => ['default' => 50, 'min_range' => 1, 'max_range' => 500]]
            )
            : 50;

        return ['start' => $start, 'limit' => $limit];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createJsonResponse(Response $response, array $data): Response
    {
        $json = json_encode($data);
        if ($json === false) {
            $json = '{"error":"Failed to encode JSON"}';
        }
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function createErrorResponse(Response $response, string $message, int $status = 400): Response
    {
        $json = json_encode(['error' => $message]);
        if ($json === false) {
            $json = '{"error":"Failed to encode error message"}';
        }
        $response->getBody()->write($json);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    // Song-related methods
    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getSongs(array $params, bool $favoriteOnly = false): array
    {
        return $this->musicLibraryService->getSongs($params, $favoriteOnly);
    }

    /** @return array<int, array<string, mixed>> */
    public function getAlbumSongs(string $albumId): array
    {
        return $this->musicLibraryService->getAlbumSongs($albumId);
    }

    /** @return array<int, array<string, mixed>> */
    public function getArtistSongs(string $artistIdentifier, bool $random = false, int $maxLimit = 250): array
    {
        return $this->musicLibraryService->getArtistSongs($artistIdentifier, $random, $maxLimit);
    }

    // Album-related methods
    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getAlbums(array $params, bool $favoriteOnly = false): array
    {
        return $this->musicLibraryService->getAlbums($params, $favoriteOnly);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getAlbumsByGenre(string $genre, array $params = []): array
    {
        return $this->genreService->getAlbumsByGenre($genre, $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getAlbumsByDecade(int $startYear, array $params = []): array
    {
        return $this->musicLibraryService->getAlbumsByDecade($startYear, $params);
    }

    // Artist-related methods
    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getArtists(array $params, bool $favoriteOnly = false): array
    {
        return $this->musicLibraryService->getArtists($params, $favoriteOnly);
    }

    // Search methods
    /**
     * @return array<string, mixed>
     */
    public function search(string $query, int $limit = 5): array
    {
        try {
            $results = $this->musicSearchService->searchAll($query, $limit);
            error_log("DEBUG: MusicAPI::search completed successfully");
            return $results;
        } catch (Exception $e) {
            error_log("DEBUG: MusicAPI::search failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            throw $e;
        }
    }

    // Playlist methods - using new playlist system
    /**
     * @return array<string, mixed>
     */
    public function getPlaylists(): array
    {
        try {
            $playlists = $this->playlistService->getUserPlaylists();
            return [
                'success' => true,
                'playlists' => array_map(fn($p) => $p->toArray(), $playlists)
            ];
        } catch (Exception $e) {
            error_log("MusicAPI::getPlaylists error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to fetch playlists'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getOwnedPlaylists(): array
    {
        return $this->getPlaylists();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSharedPlaylists(): array
    {
        try {
            $playlists = $this->playlistService->getSharedPlaylists();
            return [
                'success' => true,
                'playlists' => array_map(fn($p) => $p->toArray(), $playlists)
            ];
        } catch (Exception $e) {
            error_log("MusicAPI::getSharedPlaylists error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to fetch shared playlists'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlaylist(string $playlistId): array
    {
        try {
            $result = $this->playlistService->getPlaylistWithSongs($playlistId);
            if ($result === null || $result === []) {
                return ['success' => false, 'error' => 'Playlist not found'];
            }
            return ['success' => true] + $result;
        } catch (Exception $e) {
            error_log("MusicAPI::getPlaylist error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to fetch playlist'];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function savePlaylist(array $data): array
    {
        try {
            if (isset($data['id']) && $data['id']) {
                $playlist = $this->playlistService->updatePlaylist((string) $data['id'], $data);
                if (!$playlist instanceof \App\Models\Playlist) {
                    return ['success' => false, 'error' => 'Failed to update playlist'];
                }
            } else {
                $playlist = $this->playlistService->createPlaylist($data);
            }

            return [
                'success' => true,
                'playlist' => $playlist->toArray()
            ];
        } catch (Exception $e) {
            error_log("MusicAPI::savePlaylist error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to save playlist'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function deletePlaylist(string $playlistId): array
    {
        try {
            $deleted = $this->playlistService->deletePlaylist($playlistId);
            return [
                'success' => $deleted,
                'error' => $deleted ? null : 'Failed to delete playlist'
            ];
        } catch (Exception $e) {
            error_log("MusicAPI::deletePlaylist error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete playlist'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function addSongToPlaylist(string $playlistId, string $songId): array
    {
        try {
            $playlistSong = $this->playlistService->addSongToPlaylist($playlistId, $songId);
            if (!$playlistSong instanceof \App\Models\PlaylistSong) {
                return ['success' => false, 'error' => 'Failed to add song to playlist'];
            }

            return [
                'success' => true,
                'playlist_song' => $playlistSong->toArray()
            ];
        } catch (Exception $e) {
            error_log("MusicAPI::addSongToPlaylist error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to add song to playlist'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function removeSongFromPlaylist(string $playlistId, string $songId): array
    {
        try {
            $removed = $this->playlistService->removeSongFromPlaylist($playlistId, $songId);
            return [
                'success' => $removed,
                'error' => $removed ? null : 'Failed to remove song from playlist'
            ];
        } catch (Exception $e) {
            error_log("MusicAPI::removeSongFromPlaylist error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to remove song from playlist'];
        }
    }

    /**
     * @param array<int, string> $songIds
     * @return array<string, mixed>
     */
    public function reorderPlaylistSongs(string $playlistId, array $songIds): array
    {
        try {
            /** @var array<string, mixed> $songPositions */
            $songPositions = $songIds;
            $reordered = $this->playlistService->reorderPlaylistSongs($playlistId, $songPositions);
            return [
                'success' => $reordered,
                'error' => $reordered ? null : 'Failed to reorder playlist songs'
            ];
        } catch (Exception $e) {
            error_log("MusicAPI::reorderPlaylistSongs error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to reorder playlist songs'];
        }
    }

    // Playlist favorites methods
    public function togglePlaylistFavorite(string $playlistId): bool
    {
        try {
            $favorites = $this->favoriteService->getFavorites('playlist');
            $isFavorite = in_array($playlistId, array_column($favorites, 'item_id'));

            if ($isFavorite) {
                $result = $this->favoriteService->removeFavorite([
                    'type' => 'playlist',
                    'item_id' => $playlistId
                ]);
            } else {
                $result = $this->favoriteService->addFavorite([
                    'type' => 'playlist',
                    'item_id' => $playlistId
                ]);
            }

            return $result['success'] ?? false;
        } catch (Exception $e) {
            error_log("MusicAPI::togglePlaylistFavorite error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getFavoritePlaylistsForNavbar(int $limit = 5): array
    {
        try {
            $favorites = $this->favoriteService->getFavorites('playlist');
            $playlistIds = array_column($favorites, 'item_id');

            if ($playlistIds === []) {
                return ['success' => true, 'playlists' => []];
            }

            $playlists = [];
            foreach ($playlistIds as $playlistId) {
                $playlist = $this->playlistService->getPlaylist($playlistId);
                if ($playlist instanceof \App\Models\Playlist) {
                    $playlists[] = $playlist->toArray();
                }
                if (count($playlists) >= $limit) {
                    break;
                }
            }

            return ['success' => true, 'playlists' => $playlists];
        } catch (Exception $e) {
            error_log("MusicAPI::getFavoritePlaylistsForNavbar error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to fetch favorite playlists'];
        }
    }

    // Favorite methods
    /** @return array<int, array<string, mixed>> */
    public function getFavorites(?string $type = null): array
    {
        return $this->favoriteService->getFavorites($type);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addFavorite(array $data): array
    {
        return $this->favoriteService->addFavorite($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function removeFavorite(array $data): array
    {
        return $this->favoriteService->removeFavorite($data);
    }

    // Play history methods
    /**
     * @return array<string, mixed>
     */
    public function logSongPlay(string $songId): array
    {
        return $this->playHistoryService->logSongPlay($songId);
    }

    /**
     * @return array<string, mixed>
     */
    public function logAlbumPlay(string $albumId): array
    {
        return $this->playHistoryService->logAlbumPlay($albumId);
    }

    /**
     * @return array<string, mixed>
     */
    public function logArtistPlay(string $artistId): array
    {
        return $this->playHistoryService->logArtistPlay($artistId);
    }

    // Genre methods
    /** @return array<int, array<string, mixed>> */
    public function getAllGenres(): array
    {
        return $this->genreService->getAllGenres();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchGenres(string $like, int $limit = 20): array
    {
        return $this->genreService->searchGenres($like, $limit);
    }

    // Decade methods
    /** @return array<int, array<string, mixed>> */
    public function getAllDecades(): array
    {
        return $this->musicLibraryService->getAllDecades();
    }

    // Utility methods for statistics and validation
    /**
     * @return array<string, mixed>
     */
    public function getLibraryStatistics(): array
    {
        return $this->musicLibraryService->getLibraryStatistics();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFavoriteStatistics(): array
    {
        return $this->favoriteService->getAllFavoriteCounts();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlayStatistics(): array
    {
        return $this->playHistoryService->getPlayStatistics();
    }

    /**
     * @return array<string, mixed>
     */
    public function getGenreStatistics(): array
    {
        return $this->genreService->getGenreStatistics();
    }

    // Additional methods required by interface
    public function playSong(string $uuid, \Psr\Http\Message\ServerRequestInterface $request, Response $response): Response
    {
        try {
            // Optimize server for streaming
            ini_set('output_buffering', 'off');
            ini_set('zlib.output_compression', 'off');
            ob_implicit_flush(true);
            while (ob_get_level()) {
                ob_end_flush();
            }

            set_time_limit(0);
            ignore_user_abort(true);

            // Get user preferences - use the userId from this API instance
            $userOptions = $this->streamingService->loadUserSettings($this->userId);
            $options = array_merge(['user_id' => $this->userId], $userOptions);

            // Stream the song
            $result = $this->streamingService->streamSong($uuid, $options);

            if (!$result['success']) {
                $handle = fopen('php://temp', 'r+');
                if ($handle === false) {
                    throw new \RuntimeException("Failed to create temporary stream");
                }
                $stream = new \Slim\Psr7\Stream($handle);
                $json = json_encode(['error' => $result['error']]);
                if ($json !== false) {
                    $stream->write($json);
                }
                return $response->withStatus($result['code'])
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($stream);
            }

            // Check if this is a transcoded stream request
            if ($result['transcode'] ?? false) {
                // Use progressive streaming transcoding
                return $this->streamingService->streamTranscodedAudio(
                    $response,
                    $result['original_file_path'],
                    $result['transcode_format'] ?? 'aac',
                    $result['transcode_params'] ?? ['bitrate' => 192, 'mode' => 'cbr'],
                    $result['duration'] ?? null
                );
            }

            // Try server acceleration if available
            $acceleration = $this->streamingService->tryServerAcceleration(
                $result['file_path'],
                $result['mime_type'],
                $result['size'],
                $result['duration'] ?? null
            );
            if ($acceleration && $acceleration['success']) {
                // Apply headers for server acceleration
                foreach ($acceleration['headers'] as $name => $value) {
                    $response = $response->withHeader($name, $value);
                }
                return $response;
            }

            // Set response headers
            foreach ($result['headers'] as $name => $value) {
                $response = $response->withHeader($name, $value);
            }

            // Add headers to support media streaming
            $response = $response->withHeader('Accept-Ranges', 'bytes');
            $response = $response->withHeader('Cache-Control', 'public, max-age=3600');

            // Add duration header for ALL streams
            if (isset($result['duration']) && $result['duration'] > 0) {
                $response = $response->withHeader('X-Content-Duration', (string)$result['duration']);
                $response = $response->withHeader('X-Media-Duration', (string)$result['duration']);
            }

            // Handle range requests
            $range = $request->getHeaderLine('Range');
            if ($range !== '' && $range !== '0') {
                $rangeResponse = $this->streamingService->handleRangeRequest(
                    $request,
                    $response,
                    $result['file_path'],
                    $result['mime_type']
                );

                // Add duration headers to range response if available
                if (isset($result['duration']) && $result['duration'] > 0) {
                    $rangeResponse = $rangeResponse->withHeader('X-Content-Duration', (string)$result['duration']);
                    $rangeResponse = $rangeResponse->withHeader('X-Media-Duration', (string)$result['duration']);
                }

                return $rangeResponse;
            }

            // For media files, use chunked streaming for immediate playback
            $fileHandle = fopen($result['file_path'], 'rb');
            if (!$fileHandle) {
                $tempHandle = fopen('php://temp', 'r+');
                if ($tempHandle === false) {
                    throw new \RuntimeException("Failed to create temporary stream");
                }
                $errorStream = new \Slim\Psr7\Stream($tempHandle);
                $errorJson = json_encode(['error' => "Could not open file: {$result['file_path']}"]);
                if ($errorJson !== false) {
                    $errorStream->write($errorJson);
                }
                return $response->withStatus(500)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($errorStream);
            }

            $stream = new \Slim\Psr7\Stream($fileHandle);
            return $response->withBody($stream);
        } catch (Exception $e) {
            error_log("Error in playSong: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Error streaming song: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function recordPlayed(string $songId): array
    {
        return $this->playHistoryService->logSongPlay($songId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getGenres(): array
    {
        /** @var array<string, mixed> */
        return $this->getAllGenres();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDecades(): array
    {
        /** @var array<string, mixed> */
        return $this->getAllDecades();
    }
}
