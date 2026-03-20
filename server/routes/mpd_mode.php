<?php

// routes/mpd_mode.php - API-Routen für MPD-Integration
declare(strict_types=1);

use App\Models\AuthToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database\Connection;
use Slim\App;
use Psr\Container\ContainerInterface;

/** @var App<ContainerInterface> $app */

// MPD-Handler importieren
require_once __DIR__ . '/../src/MPDHandler.php';

/**
 * Helper function for MPD JSON responses
 * @param array<string, mixed> $data
 */
function mpdJsonResponse(Response $response, array $data, int $status = 200): Response
{
    $json = json_encode($data);
    if ($json === false) {
        throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
    }
    $response->getBody()->write($json);
    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}

// MPD-Status abrufen (public endpoint for availability checking)
$app->get('/api/mpd/status', function (Request $request, Response $response): \Psr\Http\Message\MessageInterface {
    // MPD-Handler initialisieren
    $mpd = new MPDHandler('localhost', 6600, '', null);

    // Verbindung herstellen und Status abrufen
    if (!$mpd->connect()) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Verbindung zu MPD fehlgeschlagen'
        ], 500);
    }

    // Status und aktuellen Song abrufen
    $status = $mpd->getStatus();
    $currentSong = $mpd->getCurrentSong();

    // Ausgabegeräte abrufen
    $outputs = $mpd->listOutputs();

    // Verbindung trennen
    $mpd->disconnect();

    // Erfolg zurückgeben
    return mpdJsonResponse($response, [
        'success' => true,
        'status' => $status,
        'currentSong' => $currentSong,
        'outputs' => $outputs
    ]);
});

// Song abspielen
$app->post('/api/mpd/play', function (Request $request, Response $response): \Psr\Http\Message\MessageInterface {
    $db = Connection::getPDO();

    // JWT Authentication check
    /** @var AuthToken|null $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Authentication required'
        ], 401);
    }

    // Request-Body parsen
    $bodyContents = $request->getBody()->getContents();
    $data = json_decode($bodyContents, true);
    if (!is_array($data)) {
        $data = [];
    }
    $songId = $data['songId'] ?? null;
    $position = isset($data['position']) ? (int)$data['position'] : 0;

    if (!$songId) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Song ID ist erforderlich'
        ], 400);
    }

    // Song-Pfad aus der Datenbank abrufen
    $song = null;
    try {
        $stmt = $db->prepare("SELECT s.song_id, s.title, s.artist, s.file_path, s.size,
                               s.duration, a.album_id, a.album_name
                          FROM songs s
                          JOIN albums a ON s.album_id = a.album_id
                          WHERE s.song_id = :sid AND s.is_deleted = 0
                          LIMIT 1");
        $stmt->execute([':sid' => $songId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $song = $result !== false ? $result : null;
    } catch (Exception $e) {
        error_log("Datenbankfehler: " . $e->getMessage());
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Datenbankfehler'
        ], 500);
    }

    if (!$song) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Song nicht gefunden'
        ], 404);
    }

    // MPD-Handler initialisieren
    $mpd = new MPDHandler('localhost', 6600, '', null);

    // Verbindung herstellen
    if (!$mpd->connect()) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Verbindung zu MPD fehlgeschlagen'
        ], 500);
    }

    // Song abspielen
    $success = $mpd->playSong($song['file_path'], $position);

    // Status und aktuellen Song abrufen
    $status = $mpd->getStatus();
    $currentSong = $mpd->getCurrentSong();

    // Verbindung trennen
    $mpd->disconnect();

    // Erfolg zurückgeben
    return mpdJsonResponse($response, [
        'success' => $success,
        'status' => $status,
        'currentSong' => $currentSong,
        'song' => $song
    ]);
});

// Pause/Resume
$app->post('/api/mpd/pause', function (Request $request, Response $response): \Psr\Http\Message\MessageInterface {


    // JWT Authentication check
    /** @var AuthToken|null $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Authentication required'
        ], 401);
    }

    // Request-Body parsen
    $bodyContents = $request->getBody()->getContents();
    $data = json_decode($bodyContents, true);
    if (!is_array($data)) {
        $data = [];
    }
    $pause = isset($data['pause']) ? (bool)$data['pause'] : true;

    // MPD-Handler initialisieren
    $mpd = new MPDHandler('localhost', 6600, '', null);

    // Verbindung herstellen
    if (!$mpd->connect()) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Verbindung zu MPD fehlgeschlagen'
        ], 500);
    }

    // Pause oder Resume
    $success = $pause ? $mpd->pause() : $mpd->resume();

    // Status abrufen
    $status = $mpd->getStatus();

    // Verbindung trennen
    $mpd->disconnect();

    // Erfolg zurückgeben
    return mpdJsonResponse($response, [
        'success' => $success,
        'status' => $status
    ]);
});

// Stop
$app->post('/api/mpd/stop', function (Request $request, Response $response): \Psr\Http\Message\MessageInterface {


    // JWT Authentication check
    /** @var AuthToken|null $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Authentication required'
        ], 401);
    }

    // MPD-Handler initialisieren
    $mpd = new MPDHandler('localhost', 6600, '', null);

    // Verbindung herstellen
    if (!$mpd->connect()) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Verbindung zu MPD fehlgeschlagen'
        ], 500);
    }

    // Wiedergabe stoppen
    $success = $mpd->stop();

    // Status abrufen
    $status = $mpd->getStatus();

    // Verbindung trennen
    $mpd->disconnect();

    // Erfolg zurückgeben
    return mpdJsonResponse($response, [
        'success' => $success,
        'status' => $status
    ]);
});

// Next/Previous
$app->post('/api/mpd/navigate', function (Request $request, Response $response): \Psr\Http\Message\MessageInterface {


    // JWT Authentication check
    /** @var AuthToken|null $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Authentication required'
        ], 401);
    }

    // Request-Body parsen
    $bodyContents = $request->getBody()->getContents();
    $data = json_decode($bodyContents, true);
    if (!is_array($data)) {
        $data = [];
    }
    $direction = $data['direction'] ?? 'next';

    // MPD-Handler initialisieren
    $mpd = new MPDHandler('localhost', 6600, '', null);

    // Verbindung herstellen
    if (!$mpd->connect()) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Verbindung zu MPD fehlgeschlagen'
        ], 500);
    }

    // Nächster oder vorheriger Song
    $success = false;
    if ($direction === 'next') {
        $success = $mpd->next();
    } elseif ($direction === 'prev' || $direction === 'previous') {
        $success = $mpd->previous();
    }

    // Status und aktuellen Song abrufen
    $status = $mpd->getStatus();
    $currentSong = $mpd->getCurrentSong();

    // Verbindung trennen
    $mpd->disconnect();

    // Erfolg zurückgeben
    return mpdJsonResponse($response, [
        'success' => $success,
        'status' => $status,
        'currentSong' => $currentSong
    ]);
});

// Lautstärke einstellen
$app->post('/api/mpd/volume', function (Request $request, Response $response): \Psr\Http\Message\MessageInterface {


    // JWT Authentication check
    /** @var AuthToken|null $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Authentication required'
        ], 401);
    }

    // Request-Body parsen
    $bodyContents = $request->getBody()->getContents();
    $data = json_decode($bodyContents, true);
    if (!is_array($data)) {
        $data = [];
    }
    $volume = isset($data['volume']) ? (int)$data['volume'] : 50;

    // Volume validieren
    if ($volume < 0) {
        $volume = 0;
    }
    if ($volume > 100) {
        $volume = 100;
    }

    // MPD-Handler initialisieren
    $mpd = new MPDHandler('localhost', 6600, '', null);

    // Verbindung herstellen
    if (!$mpd->connect()) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Verbindung zu MPD fehlgeschlagen'
        ], 500);
    }

    // Lautstärke setzen
    $success = $mpd->setVolume($volume);

    // Status abrufen
    $status = $mpd->getStatus();

    // Verbindung trennen
    $mpd->disconnect();

    // Erfolg zurückgeben
    return mpdJsonResponse($response, [
        'success' => $success,
        'volume' => $volume,
        'status' => $status
    ]);
});

// Ausgabegerät einstellen
$app->post('/api/mpd/output', function (Request $request, Response $response): \Psr\Http\Message\MessageInterface {


    // JWT Authentication check
    /** @var AuthToken|null $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Authentication required'
        ], 401);
    }

    // Request-Body parsen
    $bodyContents = $request->getBody()->getContents();
    $data = json_decode($bodyContents, true);
    if (!is_array($data)) {
        $data = [];
    }
    $outputId = isset($data['outputId']) ? (int)$data['outputId'] : 0;
    $enable = isset($data['enable']) ? (bool)$data['enable'] : true;

    // MPD-Handler initialisieren
    $mpd = new MPDHandler('localhost', 6600, '', null);

    // Verbindung herstellen
    if (!$mpd->connect()) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Verbindung zu MPD fehlgeschlagen'
        ], 500);
    }

    // Ausgabegerät aktivieren/deaktivieren
    $success = $mpd->setOutput($outputId, $enable);

    // Geräteliste aktualisieren
    $outputs = $mpd->listOutputs();

    // Verbindung trennen
    $mpd->disconnect();

    // Erfolg zurückgeben
    return mpdJsonResponse($response, [
        'success' => $success,
        'outputId' => $outputId,
        'enabled' => $enable,
        'outputs' => $outputs
    ]);
});

// Playlist abspielen
$app->post('/api/mpd/play-playlist', function (Request $request, Response $response): \Psr\Http\Message\MessageInterface {
    $db = Connection::getPDO();


    // JWT Authentication check
    /** @var AuthToken|null $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Authentication required'
        ], 401);
    }

    // Request-Body parsen
    $bodyContents = $request->getBody()->getContents();
    $data = json_decode($bodyContents, true);
    if (!is_array($data)) {
        $data = [];
    }
    $playlistId = $data['playlistId'] ?? null;
    $startPosition = isset($data['startPosition']) ? (int)$data['startPosition'] : 0;

    if (!$playlistId) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Playlist ID ist erforderlich'
        ], 400);
    }

    // Playlist aus der Datenbank abrufen
    $tracks = [];
    try {
        // Playlist-Songs abrufen
        $stmt = $db->prepare("SELECT s.song_id, s.title, s.artist, s.file_path, s.duration
                              FROM playlist_songs ps
                              JOIN songs s ON ps.song_id = s.song_id
                              WHERE ps.playlist_id = :pid
                              ORDER BY ps.position ASC");
        $stmt->execute([':pid' => $playlistId]);

        while (($result = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $tracks[] = $result;
        }
    } catch (Exception $e) {
        error_log("Datenbankfehler: " . $e->getMessage());
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Datenbankfehler'
        ], 500);
    }

    if ($tracks === []) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Playlist ist leer'
        ], 404);
    }

    // MPD-Handler initialisieren
    $mpd = new MPDHandler('localhost', 6600, '', null);

    // Verbindung herstellen
    if (!$mpd->connect()) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Verbindung zu MPD fehlgeschlagen'
        ], 500);
    }

    // Playlist-Dateipfade extrahieren
    $filePaths = array_map(fn(array $track) => $track['file_path'], $tracks);

    // Playlist abspielen
    $success = $mpd->playPlaylist($filePaths, $startPosition);

    // Status und aktuellen Song abrufen
    $status = $mpd->getStatus();
    $currentSong = $mpd->getCurrentSong();

    // Verbindung trennen
    $mpd->disconnect();

    // Erfolg zurückgeben
    return mpdJsonResponse($response, [
        'success' => $success,
        'playlistId' => $playlistId,
        'trackCount' => count($tracks),
        'status' => $status,
        'currentSong' => $currentSong
    ]);
});

// MPD-Datenbank aktualisieren
$app->post('/api/mpd/update-db', function (Request $request, Response $response): \Psr\Http\Message\MessageInterface {


    // JWT Authentication check
    /** @var AuthToken|null $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Authentication required'
        ], 401);
    }

    // MPD-Handler initialisieren
    $mpd = new MPDHandler('localhost', 6600, '', null);

    // Verbindung herstellen
    if (!$mpd->connect()) {
        return mpdJsonResponse($response, [
            'success' => false,
            'error' => 'Verbindung zu MPD fehlgeschlagen'
        ], 500);
    }

    // Datenbank aktualisieren
    $success = $mpd->updateDatabase();

    // Status abrufen
    $status = $mpd->getStatus();

    // Verbindung trennen
    $mpd->disconnect();

    // Erfolg zurückgeben
    return mpdJsonResponse($response, [
        'success' => $success,
        'message' => 'MPD-Datenbank wird aktualisiert',
        'status' => $status
    ]);
});

// MPD-Status-WebSocket-Endpunkt
// Falls WebSockets unterstützt werden, kann ein Echtzeit-Update-Service implementiert werden
if (class_exists(\Ratchet\Server\IoServer::class)) {
    // Diese Implementierung würde einen WebSocket-Server erstellen,
    // der regelmäßig den MPD-Status abfragt und an alle verbundenen Clients sendet
    // Ähnlich wie der lokale WebSocket-Server, der zuvor implementiert wurde
}
