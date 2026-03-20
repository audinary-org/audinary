<?php

// routes/media.php - Music library and search routes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\MediaController;
use App\Controllers\PlaylistController;
use App\Services\AuthenticationService;
use Slim\App;
use Psr\Container\ContainerInterface;

/** @var App<ContainerInterface> $app */

$config = loadConfig();

// Initialize services
$authService = new AuthenticationService(
    jwtSecret: $config['jwtSecret'] ?? 'your-secret-key-here',
);

$mediaController = new MediaController();
$playlistController = new PlaylistController($authService);

// --------------------------------------------------------------------------
// Music Library Routes
// --------------------------------------------------------------------------

$app->get('/api/media/songs', [$mediaController, 'getSongs']);
$app->get('/api/media/albums', [$mediaController, 'getAlbums']);
$app->get('/api/media/artists', [$mediaController, 'getArtists']);
$app->get('/api/media/album-songs', [$mediaController, 'getAlbumSongs']);
$app->get('/api/media/artist-songs', [$mediaController, 'getArtistSongs']);

// --------------------------------------------------------------------------
// Search Route
// --------------------------------------------------------------------------

$app->get('/api/media/search', [$mediaController, 'search']);

// --------------------------------------------------------------------------
// Playlist Routes - New System
// --------------------------------------------------------------------------

// ALL STATIC ROUTES MUST COME FIRST - before any variable routes with {id}

// Search playlists
$app->get('/api/media/playlists/search', [$playlistController, 'searchPlaylists']);

// Get shared playlists
$app->get('/api/media/playlists/shared', [$playlistController, 'getSharedPlaylists']);

// Legacy owned playlists route for backward compatibility
$app->get('/api/media/playlists/owned', [$mediaController, 'getOwnedPlaylists']);

// Get user playlists (combines owned + shared)
$app->get('/api/media/playlists', [$playlistController, 'getUserPlaylists']);

// Get specific playlist details
$app->get('/api/media/playlists/{id}', [$playlistController, 'getPlaylist']);

// Create new playlist
$app->post('/api/media/playlists', [$playlistController, 'createPlaylist']);

// Update playlist
$app->put('/api/media/playlists/{id}', [$playlistController, 'updatePlaylist']);

// Delete playlist
$app->delete('/api/media/playlists/{id}', [$playlistController, 'deletePlaylist']);

// Add song to playlist
$app->post('/api/media/playlists/{id}/songs', [$playlistController, 'addSongToPlaylist']);

// Remove song from playlist
$app->delete('/api/media/playlists/{id}/songs/{songId}', [$playlistController, 'removeSongFromPlaylist']);

// Bulk reorder songs in playlist
$app->put('/api/media/playlists/{id}/songs/reorder', [$playlistController, 'reorderPlaylistSongs']);

// Share playlist with user
$app->post('/api/media/playlists/{id}/share', [$playlistController, 'sharePlaylist']);

// Unshare playlist from user
$app->delete('/api/media/playlists/{id}/share/{userId}', [$playlistController, 'unsharePlaylist']);

// Get playlist permissions
$app->get('/api/media/playlists/{id}/permissions', [$playlistController, 'getPlaylistPermissions']);

// --------------------------------------------------------------------------
// Favorites Routes
// --------------------------------------------------------------------------

$app->get('/api/media/fav', [$mediaController, 'getFavorites']);
$app->post('/api/media/fav', [$mediaController, 'addFavorite']);
$app->delete('/api/media/fav', [$mediaController, 'removeFavorite']);

// --------------------------------------------------------------------------
// Streaming Routes
// --------------------------------------------------------------------------

$app->get('/api/media/play/{uuid}', [$mediaController, 'playSong']);
$app->post('/api/media/played', [$mediaController, 'recordPlayed']);

// --------------------------------------------------------------------------
// Genre and Decade Routes
// --------------------------------------------------------------------------

$app->get('/api/genres', [$mediaController, 'getGenres']);
$app->get('/api/decades', [$mediaController, 'getDecades']);
