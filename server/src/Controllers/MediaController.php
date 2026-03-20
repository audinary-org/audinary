<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Interfaces\MusicAPIInterface;

final class MediaController
{
    /**
     * Get all songs
     * @return Response
     */
    public function getSongs(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $params = $request->getQueryParams();
        $favoriteOnly = !empty($params['favorite']) && $params['favorite'] === '1';

        try {
            $songs = $api->getSongs($params, $favoriteOnly);
            return $api->createJsonResponse($response, ['songs' => $songs]);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * Get all albums
     * @return Response
     */
    public function getAlbums(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $params = $request->getQueryParams();
        $favoriteOnly = !empty($params['favorite']) && $params['favorite'] === '1';

        try {
            $albums = $api->getAlbums($params, $favoriteOnly);
            return $api->createJsonResponse($response, ['albums' => $albums]);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * Get all artists
     * @return Response
     */
    public function getArtists(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $params = $request->getQueryParams();
        $favoriteOnly = !empty($params['favorite']) && $params['favorite'] === '1';

        try {
            $artists = $api->getArtists($params, $favoriteOnly);
            return $api->createJsonResponse($response, ['artists' => $artists]);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * Get songs for an album
     * @return Response
     */
    public function getAlbumSongs(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $params = $request->getQueryParams();
        if (empty($params['albumId'])) {
            return $api->createErrorResponse($response, 'Album-ID missing', 400);
        }

        try {
            $songs = $api->getAlbumSongs($params['albumId']);

            $result = [
                'success' => true,
                'tracks' => $songs
            ];

            return $api->createJsonResponse($response, $result);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * Get songs for an artist
     * @return Response
     */
    public function getArtistSongs(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $params = $request->getQueryParams();
        if (empty($params['artist'])) {
            return $api->createErrorResponse($response, 'Artist name is required', 400);
        }

        $random = !empty($params['random']) && $params['random'] === '1';

        try {
            $songs = $api->getArtistSongs($params['artist'], $random, 250);
            return $api->createJsonResponse($response, ['songs' => $songs]);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * Search media
     * @return Response
     */
    public function search(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $params = $request->getQueryParams();
        $query = trim($params['q'] ?? '');
        $limit = isset($params['limit'])
            ? filter_var(
                $params['limit'],
                FILTER_VALIDATE_INT,
                ['options' => ['default' => 5, 'min_range' => 1, 'max_range' => 100]]
            )
            : 5;

        try {
            $results = $api->search($query, $limit);
            return $api->createJsonResponse($response, $results);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            return $api->createErrorResponse($response, 'Error occurred while searching: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get owned playlists (legacy)
     * @return Response
     */
    public function getOwnedPlaylists(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        try {
            $playlists = $api->getOwnedPlaylists();
            return $api->createJsonResponse($response, $playlists);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            return $api->createErrorResponse($response, 'Fehler beim Abrufen der eigenen Playlists: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get favorites
     * @return Response
     */
    public function getFavorites(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $params = $request->getQueryParams();
        $type = $params['type'] ?? null;

        try {
            $favorites = $api->getFavorites($type);
            return $api->createJsonResponse($response, ['favorites' => $favorites]);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            return $api->createErrorResponse($response, 'Fehler beim Abrufen der Favoriten: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add favorite
     * @return Response
     */
    public function addFavorite(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $decoded = json_decode($request->getBody()->getContents(), true);
        if ($decoded === false || $decoded === null || !is_array($decoded)) {
            return $api->createErrorResponse($response, 'Invalid JSON data', 400);
        }
        $jsonData = $decoded;
        if (empty($jsonData['favorite_type'])) {
            return $api->createErrorResponse($response, 'Favoriten-Typ ist erforderlich', 400);
        }

        $data = [
            'favorite_type' => $jsonData['favorite_type']
        ];

        switch ($jsonData['favorite_type']) {
            case 'song':
                if (empty($jsonData['song_id'])) {
                    return $api->createErrorResponse($response, 'Song-ID fehlt', 400);
                }
                $data['song_id'] = $jsonData['song_id'];
                break;
            case 'album':
                if (empty($jsonData['album_id'])) {
                    return $api->createErrorResponse($response, 'Album-ID fehlt', 400);
                }
                $data['album_id'] = $jsonData['album_id'];
                break;
            case 'artist':
                if (empty($jsonData['artist_id'])) {
                    return $api->createErrorResponse($response, 'Künstler-ID fehlt', 400);
                }
                $data['artist_id'] = $jsonData['artist_id'];
                break;
            case 'playlist':
                if (empty($jsonData['playlist_id'])) {
                    return $api->createErrorResponse($response, 'Playlist-ID fehlt', 400);
                }
                $data['playlist_id'] = $jsonData['playlist_id'];
                break;
            default:
                return $api->createErrorResponse($response, 'Ungültiger Favoriten-Typ', 400);
        }

        try {
            $result = $api->addFavorite($data);
            return $api->createJsonResponse($response, $result);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            return $api->createErrorResponse($response, 'Fehler beim Hinzufügen des Favoriten: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove favorite
     * @return Response
     */
    public function removeFavorite(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $decoded = json_decode($request->getBody()->getContents(), true);
        if ($decoded === false || $decoded === null || !is_array($decoded)) {
            return $api->createErrorResponse($response, 'Invalid JSON data', 400);
        }
        $jsonData = $decoded;
        if (empty($jsonData['favorite_type'])) {
            return $api->createErrorResponse($response, 'Favoriten-Typ ist erforderlich', 400);
        }

        $data = [
            'favorite_type' => $jsonData['favorite_type']
        ];

        switch ($jsonData['favorite_type']) {
            case 'song':
                if (empty($jsonData['song_id'])) {
                    return $api->createErrorResponse($response, 'Song-ID fehlt', 400);
                }
                $data['song_id'] = $jsonData['song_id'];
                break;
            case 'album':
                if (empty($jsonData['album_id'])) {
                    return $api->createErrorResponse($response, 'Album-ID fehlt', 400);
                }
                $data['album_id'] = $jsonData['album_id'];
                break;
            case 'artist':
                if (empty($jsonData['artist_id'])) {
                    return $api->createErrorResponse($response, 'Künstler-ID fehlt', 400);
                }
                $data['artist_id'] = $jsonData['artist_id'];
                break;
            case 'playlist':
                if (empty($jsonData['playlist_id'])) {
                    return $api->createErrorResponse($response, 'Playlist-ID fehlt', 400);
                }
                $data['playlist_id'] = $jsonData['playlist_id'];
                break;
            default:
                return $api->createErrorResponse($response, 'Ungültiger Favoriten-Typ', 400);
        }

        try {
            $result = $api->removeFavorite($data);
            return $api->createJsonResponse($response, $result);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            return $api->createErrorResponse($response, 'Fehler beim Entfernen des Favoriten: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Play song
     * @param array<string, mixed> $args
     */
    public function playSong(Request $request, Response $response, array $args): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $uuid = $args['uuid'];

        try {
            return $api->playSong($uuid, $request, $response);
        } catch (InvalidArgumentException $e) {
            return $api->createErrorResponse($response, $e->getMessage(), 404);
        } catch (Exception $e) {
            error_log("Error playing song $uuid: " . $e->getMessage());
            return $api->createErrorResponse($response, 'Error playing song: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Record played song
     * @return Response
     */
    public function recordPlayed(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        $decoded = json_decode($request->getBody()->getContents(), true);
        if ($decoded === false || $decoded === null || !is_array($decoded)) {
            return $api->createErrorResponse($response, 'Invalid JSON data', 400);
        }
        $jsonData = $decoded;
        if (empty($jsonData['song_id'])) {
            return $api->createErrorResponse($response, 'Song-ID ist erforderlich', 400);
        }

        try {
            $result = $api->recordPlayed($jsonData['song_id']);
            return $api->createJsonResponse($response, $result);
        } catch (Exception $e) {
            error_log("Error recording played song: " . $e->getMessage());
            return $api->createErrorResponse($response, 'Fehler beim Aufzeichnen des gespielten Songs: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get genres
     * @return Response
     */
    public function getGenres(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        try {
            $genres = $api->getGenres();
            return $api->createJsonResponse($response, $genres);
        } catch (Exception $e) {
            error_log("Error fetching genres: " . $e->getMessage());
            return $api->createErrorResponse($response, 'Fehler beim Abrufen der Genres: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get decades
     * @return Response
     */
    public function getDecades(Request $request, Response $response): Response
    {
        /** @var MusicAPIInterface $api */
        $api = $request->getAttribute('api');

        try {
            $decades = $api->getDecades();
            return $api->createJsonResponse($response, $decades);
        } catch (Exception $e) {
            error_log("Error fetching decades: " . $e->getMessage());
            return $api->createErrorResponse($response, 'Fehler beim Abrufen der Jahrzehnte: ' . $e->getMessage(), 500);
        }
    }
}
