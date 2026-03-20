<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\PlaylistService;
use App\Services\PlaylistCollageService;
use App\Services\AuthenticationService;
use App\Repository\PlaylistRepository;
use App\Repository\PlaylistSongRepository;
use App\Repository\PlaylistPermissionRepository;
use InvalidArgumentException;

final class PlaylistController
{
    private \App\Services\AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    private function getPlaylistService(Request $request): PlaylistService
    {
        $userId = $this->authService->getUserIdFromRequest($request);

        // Load configuration for collage service
        require_once __DIR__ . '/../configHelper.php';
        $config = loadConfig();

        return new PlaylistService(
            new PlaylistRepository($userId),
            new PlaylistSongRepository(),
            new PlaylistPermissionRepository(),
            new PlaylistCollageService(new PlaylistSongRepository(), $config),
            $userId
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createJsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON response');
        }
        $response->getBody()->write($json);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function createErrorResponse(Response $response, string $message, int $status = 400): Response
    {
        return $this->createJsonResponse($response, ['error' => $message], $status);
    }

    public function getUserPlaylists(Request $request, Response $response): Response
    {
        try {
            $playlistService = $this->getPlaylistService($request);
            $params = $request->getQueryParams();

            $offset = (int) ($params['offset'] ?? 0);
            $limit = min((int) ($params['limit'] ?? 50), 100);

            $playlists = $playlistService->getUserPlaylists($offset, $limit);
            $count = $playlistService->countUserPlaylists();

            return $this->createJsonResponse($response, [
                'success' => true,
                'playlists' => array_map(fn($p) => $p->toArray(), $playlists),
                'total' => $count,
                'offset' => $offset,
                'limit' => $limit
            ]);
        } catch (Exception $e) {
            error_log("PlaylistController::getUserPlaylists error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to fetch playlists', 500);
        }
    }

    public function getSharedPlaylists(Request $request, Response $response): Response
    {
        try {
            $playlistService = $this->getPlaylistService($request);
            $params = $request->getQueryParams();

            $offset = (int) ($params['offset'] ?? 0);
            $limit = min((int) ($params['limit'] ?? 50), 100);

            $playlists = $playlistService->getSharedPlaylists($offset, $limit);

            return $this->createJsonResponse($response, [
                'success' => true,
                'playlists' => array_map(fn($p) => $p->toArray(), $playlists),
                'offset' => $offset,
                'limit' => $limit
            ]);
        } catch (Exception $e) {
            error_log("PlaylistController::getSharedPlaylists error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to fetch shared playlists', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function getPlaylist(Request $request, Response $response, array $args): Response
    {
        try {
            $playlistId = $args['id'];
            $playlistService = $this->getPlaylistService($request);
            $params = $request->getQueryParams();
            $includeSongs = !isset($params['include_songs']) || $params['include_songs'] !== 'false';

            if ($includeSongs) {
                $result = $playlistService->getPlaylistWithSongs($playlistId);
            } else {
                $playlist = $playlistService->getPlaylist($playlistId);
                $result = $playlist instanceof \App\Models\Playlist ? ['playlist' => $playlist->toArray()] : null;
            }

            if ($result === null || $result === []) {
                return $this->createErrorResponse($response, 'Playlist not found', 404);
            }

            return $this->createJsonResponse($response, array_merge(['success' => true], $result));
        } catch (Exception $e) {
            error_log("PlaylistController::getPlaylist error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to fetch playlist', 500);
        }
    }

    public function createPlaylist(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            if (!is_array($data) || empty($data['name'])) {
                return $this->createErrorResponse($response, 'Playlist name is required', 400);
            }

            $playlistService = $this->getPlaylistService($request);
            $playlist = $playlistService->createPlaylist($data);

            return $this->createJsonResponse($response, [
                'success' => true,
                'playlist' => $playlist->toArray()
            ], 201);
        } catch (InvalidArgumentException $e) {
            return $this->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            error_log("PlaylistController::createPlaylist error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to create playlist', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function updatePlaylist(Request $request, Response $response, array $args): Response
    {
        try {
            $playlistId = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);

            if (!is_array($data)) {
                return $this->createErrorResponse($response, 'Invalid request data', 400);
            }

            $playlistService = $this->getPlaylistService($request);
            $playlist = $playlistService->updatePlaylist($playlistId, $data);

            if (!$playlist instanceof \App\Models\Playlist) {
                return $this->createErrorResponse($response, 'Playlist not found or access denied', 404);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'playlist' => $playlist->toArray()
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            error_log("PlaylistController::updatePlaylist error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to update playlist', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function deletePlaylist(Request $request, Response $response, array $args): Response
    {
        try {
            $playlistId = $args['id'];
            $playlistService = $this->getPlaylistService($request);

            $deleted = $playlistService->deletePlaylist($playlistId);

            if (!$deleted) {
                return $this->createErrorResponse($response, 'Playlist not found or access denied', 404);
            }

            return $this->createJsonResponse($response, ['success' => true]);
        } catch (Exception $e) {
            error_log("PlaylistController::deletePlaylist error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to delete playlist', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function addSongToPlaylist(Request $request, Response $response, array $args): Response
    {
        try {
            $playlistId = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);

            if (!is_array($data) || empty($data['song_id'])) {
                return $this->createErrorResponse($response, 'Song ID is required', 400);
            }

            $playlistService = $this->getPlaylistService($request);
            $position = isset($data['position']) ? (float) $data['position'] : null;
            $playlistSong = $playlistService->addSongToPlaylist($playlistId, $data['song_id'], $position);

            if (!$playlistSong instanceof \App\Models\PlaylistSong) {
                return $this->createErrorResponse($response, 'Failed to add song to playlist', 400);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'playlist_song' => $playlistSong->toArray()
            ], 201);
        } catch (InvalidArgumentException $e) {
            return $this->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            error_log("PlaylistController::addSongToPlaylist error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to add song to playlist', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function removeSongFromPlaylist(Request $request, Response $response, array $args): Response
    {
        try {
            $playlistId = $args['id'];
            $songId = $args['songId'];

            $playlistService = $this->getPlaylistService($request);
            $removed = $playlistService->removeSongFromPlaylist($playlistId, $songId);

            if (!$removed) {
                return $this->createErrorResponse($response, 'Song not found in playlist or access denied', 404);
            }

            return $this->createJsonResponse($response, ['success' => true]);
        } catch (Exception $e) {
            error_log("PlaylistController::removeSongFromPlaylist error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to remove song from playlist', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function reorderPlaylistSongs(Request $request, Response $response, array $args): Response
    {
        try {
            $playlistId = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);

            if (!is_array($data) || !isset($data['song_positions']) || !is_array($data['song_positions'])) {
                return $this->createErrorResponse($response, 'Song positions are required', 400);
            }

            $playlistService = $this->getPlaylistService($request);
            $reordered = $playlistService->reorderPlaylistSongs($playlistId, $data['song_positions']);

            if (!$reordered) {
                return $this->createErrorResponse($response, 'Failed to reorder songs or access denied', 400);
            }

            return $this->createJsonResponse($response, ['success' => true]);
        } catch (Exception $e) {
            error_log("PlaylistController::reorderPlaylistSongs error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to reorder playlist songs', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function sharePlaylist(Request $request, Response $response, array $args): Response
    {
        try {
            $playlistId = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);

            if (!is_array($data) || empty($data['user_id'])) {
                return $this->createErrorResponse($response, 'User ID is required', 400);
            }

            $permissionType = $data['permission_type'] ?? 'view';
            $playlistService = $this->getPlaylistService($request);
            $permission = $playlistService->sharePlaylist($playlistId, $data['user_id'], $permissionType);

            if (!$permission instanceof \App\Models\PlaylistPermission) {
                return $this->createErrorResponse($response, 'Failed to share playlist or access denied', 400);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'permission' => $permission->toArray()
            ], 201);
        } catch (InvalidArgumentException $e) {
            return $this->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            error_log("PlaylistController::sharePlaylist error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to share playlist', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function unsharePlaylist(Request $request, Response $response, array $args): Response
    {
        try {
            $playlistId = $args['id'];
            $userId = $args['userId'];

            $playlistService = $this->getPlaylistService($request);
            $unshared = $playlistService->unsharePlaylist($playlistId, $userId);

            if (!$unshared) {
                return $this->createErrorResponse($response, 'Failed to unshare playlist or access denied', 400);
            }

            return $this->createJsonResponse($response, ['success' => true]);
        } catch (Exception $e) {
            error_log("PlaylistController::unsharePlaylist error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to unshare playlist', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function getPlaylistPermissions(Request $request, Response $response, array $args): Response
    {
        try {
            $playlistId = $args['id'];
            $playlistService = $this->getPlaylistService($request);
            $permissions = $playlistService->getPlaylistPermissions($playlistId);

            return $this->createJsonResponse($response, [
                'success' => true,
                'permissions' => array_map(fn($p) => $p->toArray(), $permissions)
            ]);
        } catch (Exception $e) {
            error_log("PlaylistController::getPlaylistPermissions error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to fetch playlist permissions', 500);
        }
    }

    public function searchPlaylists(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $query = $params['q'] ?? '';

            if (in_array(trim($query), ['', '0'], true)) {
                return $this->createErrorResponse($response, 'Search query is required', 400);
            }

            $limit = min((int) ($params['limit'] ?? 20), 50);
            $playlistService = $this->getPlaylistService($request);
            $playlists = $playlistService->searchPlaylists($query, $limit);

            return $this->createJsonResponse($response, [
                'success' => true,
                'playlists' => array_map(fn($p) => $p->toArray(), $playlists),
                'query' => $query
            ]);
        } catch (Exception $e) {
            error_log("PlaylistController::searchPlaylists error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to search playlists', 500);
        }
    }
}
