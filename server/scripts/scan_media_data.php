#!/usr/bin/env php
<?php

/**
 * Media Scanner v2 - Refactored with MediaInfo
 *
 * Simplified scanner using MediaInfo CLI instead of getID3
 * - Much faster processing
 * - Better metadata extraction
 * - Lower memory usage
 *
 * @author Daniel Hiller
 */

declare(strict_types=1);

// Bootstrap
require __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use App\Database\Connection;
use App\Services\MediaInfoParser;

// Parse command line arguments
$options = getopt('', [
    'full',
    'help',
    'debug',
    'update-artist-image',
    'update-cover-images',
    'update-gradients',
    'list-missing-artist-images',
    'list-missing-cover-images',
    'fix-filetypes'
]);

$fullRescan = isset($options['full']);
$showHelp = isset($options['help']);
$debugMode = isset($options['debug']);
$updateArtistImage = isset($options['update-artist-image']);
$updateCoverImages = isset($options['update-cover-images']);
$updateGradients = isset($options['update-gradients']);
$listMissingArtistImages = isset($options['list-missing-artist-images']);
$listMissingCoverImages = isset($options['list-missing-cover-images']);
$fixFiletypes = isset($options['fix-filetypes']);

if ($showHelp) {
    echo "Music Scanner v2 - MediaInfo Edition\n";
    echo "Usage: php scan_media_data_v2.php [options]\n\n";
    echo "Options:\n";
    echo "  --full                        Perform a full rescan, ignoring file modification times\n";
    echo "  --debug                       Enable detailed debug logging\n";
    echo "  --update-artist-image         Update all artist images without scanning media\n";
    echo "  --update-cover-images         Update all album cover images without scanning media\n";
    echo "  --update-gradients            Calculate and save gradients for all album covers\n";
    echo "  --list-missing-artist-images  List all artists without images\n";
    echo "  --list-missing-cover-images   List all albums without cover images\n";
    echo "  --fix-filetypes               Normalize all filetype values to lowercase standard names\n";
    echo "  --help                        Show this help message\n";
    exit(0);
}

// Run the scanner
try {
    ini_set('memory_limit', '1G');
    set_time_limit(0);

    $startTime = microtime(true);
    $scanner = new MediaScanner(
        $fullRescan,
        $debugMode,
        $updateArtistImage,
        $updateCoverImages,
        $updateGradients,
        $listMissingArtistImages,
        $listMissingCoverImages,
        $fixFiletypes
    );
    $scanner->run();
    $endTime = microtime(true);

    $executionTime = round($endTime - $startTime, 2);
    echo "Scan completed in $executionTime seconds\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";

    if ($debugMode) {
        echo $e->getTraceAsString() . "\n";
    } else {
        echo "Run with --debug flag for detailed error information\n";
    }

    exit(1);
}

/**
 * Media Scanner - Simplified version using MediaInfo
 */
class MediaScanner
{
    private PDO $db;
    /** @var array<string, mixed> */
    private array $config;
    private bool $tagfirstmode = false;
    private bool $forceFullRescan;
    private bool $debugMode;
    private bool $updateArtistImage;
    private bool $updateCoverImages;
    private bool $updateGradients;
    private bool $listMissingArtistImages;
    private bool $listMissingCoverImages;
    private bool $fixFiletypes;
    private MediaInfoParser $parser;
    private Logger $logger;

    /** @var array<string, string> */
    private array $artistCache = [];
    /** @var array<string, string> */
    private array $albumCache = [];
    /** @var array<string, mixed> */
    private array $existingSongs = [];
    /** @var array<string, string> */
    private array $foundSongIDs = [];

    /** @var array<string, PDOStatement> */
    private array $stmts = [];

    /** @var array<string, int> */
    private array $stats = [
        'albums_created' => 0,
        'albums_updated' => 0,
        'albums_skipped' => 0,
        'songs_created' => 0,
        'songs_updated' => 0,
        'songs_deleted' => 0,
        'files_processed' => 0,
        'errors' => 0,
    ];

    private int $processId = 0;
    private ?int $statusRecordId = null;

    public function __construct(
        bool $forceFullRescan = false,
        bool $debugMode = false,
        bool $updateArtistImage = false,
        bool $updateCoverImages = false,
        bool $updateGradients = false,
        bool $listMissingArtistImages = false,
        bool $listMissingCoverImages = false,
        bool $fixFiletypes = false
    ) {
        if ($debugMode) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
            ini_set('display_errors', '0');
        }

        $this->forceFullRescan = $forceFullRescan;
        $this->debugMode = $debugMode;
        $this->updateArtistImage = $updateArtistImage;
        $this->updateCoverImages = $updateCoverImages;
        $this->updateGradients = $updateGradients;
        $this->listMissingArtistImages = $listMissingArtistImages;
        $this->listMissingCoverImages = $listMissingCoverImages;
        $this->fixFiletypes = $fixFiletypes;

        $pid = getmypid();
        if ($pid !== false) {
            $this->processId = $pid;
        }

        $this->db = Connection::getPDO();
        $this->config = loadConfig();
        $this->initLogger();

        $this->tagfirstmode = !empty($this->config['tagfirstmode']);
        $this->parser = new MediaInfoParser();

        $this->ensureDirectoriesExist();
        $this->addFolderPathColumn();

        if (!$updateArtistImage && !$updateCoverImages && !$updateGradients) {
            $this->loadExistingSongs();
            $this->prepareStatements();
        }

        $this->setScanStatus('running', [
            'option_name' => $this->determineScanOption(),
            'full_scan' => $this->forceFullRescan,
            'start_time' => time(),
        ]);
    }

    private function initLogger(): void
    {
        $logFile = $this->config['logDir'] . '/music_scanner.log';
        $this->logger = new Logger('media_scanner');

        $dateFormat = 'Y-m-d H:i:s';
        $logFormat = "[%datetime%] %level_name%: %message%\n";

        // Console output only in CLI mode
        if (php_sapi_name() === 'cli') {
            $consoleHandler = new StreamHandler('php://stdout', $this->debugMode ? Logger::DEBUG : Logger::INFO);
            $consoleHandler->setFormatter(new LineFormatter($logFormat, $dateFormat));
            $this->logger->pushHandler($consoleHandler);
        }

        $fileHandler = new RotatingFileHandler($logFile, 7, $this->debugMode ? Logger::DEBUG : Logger::INFO);
        $fileHandler->setFormatter(new LineFormatter($logFormat, $dateFormat));
        $this->logger->pushHandler($fileHandler);

        $this->logger->info("Media Scanner v2 initialized with MediaInfo (PostgreSQL backend)");
    }

    private function ensureDirectoriesExist(): void
    {
        $dirs = [
            $this->config['artistImagesDir'],
            $this->config['coverDir'],
            $this->config['logDir']
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                $this->logger->error("Failed to create directory: $dir");
            }
        }
    }

    private function addFolderPathColumn(): void
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column"
            );
            $stmt->execute([
                ':schema' => 'public',
                ':table' => 'albums',
                ':column' => 'folder_path',
            ]);

            $columnExists = (bool) $stmt->fetchColumn();

            if (!$columnExists) {
                $this->logger->info("Adding folder_path column to albums table");
                $this->db->exec("ALTER TABLE albums ADD COLUMN folder_path VARCHAR(255)");
            }
        } catch (Exception $e) {
            $this->logger->error("Error checking/adding folder_path column: " . $e->getMessage());
        }
    }

    private function loadExistingSongs(): void
    {
        $this->logger->info("Loading existing songs from database...");
        $startTime = microtime(true);

        $stmt = $this->db->query("SELECT song_id, file_path, last_mtime FROM songs WHERE is_deleted = 0");
        if ($stmt === false) {
            throw new RuntimeException("Failed to query songs");
        }

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->existingSongs[$row['file_path']] = [
                'song_id' => $row['song_id'],
                'last_mtime' => (int)$row['last_mtime'],
            ];
            $count++;
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->logger->info("Loaded $count songs in $duration seconds");
    }

    private function prepareStatements(): void
    {
        $this->stmts['artist_select'] = $this->db->prepare(
            "SELECT artist_id FROM artists WHERE artist_name = :name AND is_deleted = 0 LIMIT 1"
        );

        $this->stmts['artist_insert'] = $this->db->prepare(
            "INSERT INTO artists (artist_id, artist_name) VALUES (:id, :name)"
        );

        $this->stmts['album_by_path'] = $this->db->prepare(
            "SELECT album_id FROM albums WHERE folder_path = :path AND is_deleted = 0 LIMIT 1"
        );

        $this->stmts['album_create'] = $this->db->prepare(
            "INSERT INTO albums (album_id, artist_id, album_name, album_artist, folder_path)
             VALUES (:id, :artistId, :name, :artist, :path)"
        );

        $this->stmts['album_update'] = $this->db->prepare(
            "UPDATE albums SET
                album_name = :name,
                album_artist = :artist,
                total_discs = :discs,
                total_tracks = :tracks,
                original_year = :origYear,
                album_genre = :genre,
                album_year = :year,
                album_duration = :duration,
                filetype = :filetype
             WHERE album_id = :id"
        );

        $this->stmts['album_cover'] = $this->db->prepare(
            "UPDATE albums SET cover_path = :cover WHERE album_id = :id"
        );

        $this->stmts['song_select'] = $this->db->prepare(
            "SELECT song_id, last_mtime FROM songs WHERE file_path = :path AND is_deleted = 0 LIMIT 1"
        );

        $this->stmts['song_insert'] = $this->db->prepare(
            "INSERT INTO songs (
                song_id, album_id, disc_number, track_number, title, artist,
                genre, year, duration, bitrate, size, last_mtime, file_path, filetype
            ) VALUES (
                :id, :albumId, :disc, :track, :title, :artist,
                :genre, :year, :duration, :bitrate, :size, :mtime, :path, :type
            )"
        );

        $this->stmts['song_update'] = $this->db->prepare(
            "UPDATE songs SET
                album_id = :albumId, disc_number = :disc, track_number = :track,
                title = :title, artist = :artist, genre = :genre, year = :year,
                duration = :duration, bitrate = :bitrate, size = :size,
                last_mtime = :mtime, filetype = :type
             WHERE song_id = :id"
        );

        $this->stmts['album_stats_select'] = $this->db->prepare(
            "SELECT SUM(duration) AS total_duration,
                string_agg(DISTINCT genre, ',') AS genres,
                string_agg(DISTINCT year::text, ',') AS years
             FROM songs
             WHERE album_id = :id AND is_deleted = 0"
        );

        $this->stmts['album_stats_update'] = $this->db->prepare(
            "UPDATE albums
             SET album_duration = :duration, album_genre = :genre, album_year = :year
             WHERE album_id = :id"
        );
    }

    public function run(): void
    {
        try {
            $startTime = microtime(true);

            // Handle list operations
            if ($this->listMissingArtistImages) {
                $this->listMissingArtistImages();
                $this->setScanStatus('idle', ['end_time' => time()]);
                return;
            }

            if ($this->listMissingCoverImages) {
                $this->listMissingCoverImages();
                $this->setScanStatus('idle', ['end_time' => time()]);
                return;
            }

            // Handle update operations
            if ($this->updateArtistImage) {
                $this->logger->info("Updating artist images...");
                $this->updateAllArtistImages();
                $this->setScanStatus('idle', ['end_time' => time()]);
                return;
            }

            if ($this->updateCoverImages) {
                $this->logger->info("Updating album covers...");
                $this->updateAllAlbumCovers();
                $this->setScanStatus('idle', ['end_time' => time()]);
                return;
            }

            if ($this->updateGradients) {
                $this->logger->info("Calculating gradients for album covers and artist images...");
                $this->updateAllAlbumGradients();
                $this->updateAllArtistGradients();
                $this->setScanStatus('idle', ['end_time' => time()]);
                return;
            }

            if ($this->fixFiletypes) {
                $this->logger->info("Fixing filetype values...");
                $this->fixAllFiletypes();
                $this->setScanStatus('idle', ['end_time' => time()]);
                return;
            }

            // Main scan
            $this->logger->info("Starting media scan...");
            $filesByAlbum = $this->collectMusicFiles();
            $totalAlbums = count($filesByAlbum);
            $this->logger->info("Found $totalAlbums albums to process");

            $this->setScanStatus('running', ['total_albums' => $totalAlbums]);

            $albumCount = 0;
            $batchSize = 50;
            $batchCount = 0;
            $this->db->beginTransaction();

            foreach ($filesByAlbum as $albumPath => $files) {
                $albumCount++;
                $this->logger->info("Processing album $albumCount/$totalAlbums");

                try {
                    $this->processAlbum($albumPath, $files);
                    $batchCount++;

                    // Commit every N albums to balance performance vs. memory
                    if ($batchCount >= $batchSize) {
                        $this->db->commit();
                        $this->db->beginTransaction();
                        $batchCount = 0;
                    }
                } catch (Exception $e) {
                    $this->db->rollBack();
                    $this->db->beginTransaction();
                    $batchCount = 0;

                    $this->logger->error("Error processing album '$albumPath': " . $e->getMessage());
                    if ($this->debugMode) {
                        $this->logger->debug("Stack trace: " . $e->getTraceAsString());
                    }
                    $this->stats['errors']++;
                }

                if ($albumCount % 10 === 0) {
                    $this->updateScanProgress(['processed_albums' => $albumCount]);
                    gc_collect_cycles();
                }
            }

            // Commit remaining batch
            if ($batchCount > 0) {
                $this->db->commit();
            }

            $this->cleanup();

            $duration = round(microtime(true) - $startTime, 2);
            $this->logStatistics($duration);

            $this->setScanStatus('idle', [
                'end_time' => time(),
                'duration' => $duration,
                'statistics' => $this->stats
            ]);
        } catch (Exception $e) {
            $this->logger->error("Fatal error: " . $e->getMessage());
            $this->setScanStatus('error', ['error_message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @return array<string, array<string>>
     */
    private function collectMusicFiles(): array
    {
        $musicDir = $this->config['musicDir'];
        $allowedExtensions = $this->config['allowedExtensions'] ??
            ['mp3', 'flac', 'wav', 'ogg', 'm4a', 'aac', 'opus', 'wma'];

        $this->logger->info("Scanning directory: $musicDir");

        if (!is_dir($musicDir) || !is_readable($musicDir)) {
            throw new RuntimeException("Music directory not accessible: $musicDir");
        }

        $filesByAlbum = [];
        $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS;
        $dirIterator = new RecursiveDirectoryIterator($musicDir, $flags);
        $iterator = new RecursiveIteratorIterator($dirIterator);

        $fileCount = 0;
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExtensions)) {
                    $albumDir = dirname($file->getPathname());
                    if (!isset($filesByAlbum[$albumDir])) {
                        $filesByAlbum[$albumDir] = [];
                    }
                    $filesByAlbum[$albumDir][] = $file->getPathname();
                    $fileCount++;
                }
            }

            if ($fileCount % 10000 === 0 && $fileCount > 0) {
                $this->logger->info("Found $fileCount files so far...");
                gc_collect_cycles();
            }
        }

        $this->logger->info("Found $fileCount files in " . count($filesByAlbum) . " albums");
        return $filesByAlbum;
    }

    /**
     * @param array<string> $files
     */
    private function processAlbum(string $albumPath, array $files): void
    {
        $musicDir = $this->config['musicDir'];
        $relPath = substr($albumPath, strlen($musicDir) + 1);
        $parts = explode(DIRECTORY_SEPARATOR, $relPath);

        $folderArtist = !empty($parts[0]) ? $parts[0] : 'Unknown Artist';
        $folderAlbum = !empty($parts[1]) ? $parts[1] : 'Unknown Album';

        if (!$this->tagfirstmode && count($parts) < 2) {
            $this->logger->warning("Skipping invalid structure: $relPath");
            return;
        }

        // Check if files changed
        if (!$this->forceFullRescan && !$this->filesChanged($files)) {
            $this->logger->info("Skipping unchanged album: $folderAlbum");
            $this->stats['albums_skipped']++;

            foreach ($files as $filePath) {
                if (isset($this->existingSongs[$filePath])) {
                    $this->foundSongIDs[$filePath] = $this->existingSongs[$filePath]['song_id'];
                }
            }
            return;
        }

        // Analyze all files
        $metadata = $this->analyzeFiles($files, $folderArtist, $folderAlbum);

        if ($metadata === []) {
            $this->logger->warning("No valid files in: $folderAlbum");
            return;
        }

        // Get/create artist and album
        $artistName = $this->tagfirstmode ?
            ($metadata['album_artist'] ?: $folderArtist) : $folderArtist;

        $artistId = $this->getOrCreateArtist($artistName, $musicDir);

        $albumName = $this->tagfirstmode ?
            ($metadata['album_name'] ?: $folderAlbum) : ($metadata['album_name'] ?: $folderAlbum);

        // Ensure album name is string (safety check)
        $albumName = (string)$albumName;
        if ($albumName === '' || $albumName === '0') {
            $albumName = $folderAlbum;
        }

        $albumId = $this->getOrCreateAlbum($artistId, $albumName, $relPath, (string)$metadata['album_artist']);

        // Update album metadata
        $this->updateAlbumMetadata($albumId, $metadata);

        // Process cover
        $this->processAlbumCover($albumId, $albumPath, $files);

        // Process songs in BULK (much faster!)
        $this->processSongsBulk($albumId, $metadata['files']);

        $this->calculateAlbumStats($albumId);
    }

    /**
     * @param array<string> $files
     * @return array<string, mixed>
     */
    private function analyzeFiles(array $files, string $folderArtist, string $folderAlbum): array
    {
        $albumData = [
            'album_name' => '',
            'album_artist' => '',
            'total_tracks' => 0,
            'total_discs' => 0,
            'original_year' => 0,
            'album_genre' => '',
            'album_year' => 0,
            'album_duration' => 0,
            'filetype' => '',
            'files' => []
        ];

        $albumNames = [];
        $albumArtists = [];
        $genres = [];
        $years = [];
        $highestDisc = 0;
        $highestTrack = 0;

        // BULK PROCESSING: Analyze ALL files in one mediainfo call! (~4-5x faster)
        try {
            $bulkResults = $this->parser->analyzeBulk($files);

            foreach ($bulkResults as $filePath => $data) {
                // Collect album-level data
                if ($data['album'] !== '' && $data['album'] !== '0') {
                    $albumNames[] = $data['album'];
                }
                if ($data['album_artist'] !== '' && $data['album_artist'] !== '0') {
                    $albumArtists[] = $data['album_artist'];
                }
                if ($data['genre'] !== '' && $data['genre'] !== '0') {
                    $genres[] = $data['genre'];
                }
                if ($data['year'] > 0) {
                    $years[] = $data['year'];
                }

                if ($data['disc_number'] > $highestDisc) {
                    $highestDisc = $data['disc_number'];
                }
                if ($data['track_number'] > $highestTrack) {
                    $highestTrack = $data['track_number'];
                }

                $albumData['album_duration'] += $data['duration'];

                if ($albumData['filetype'] === '' || $albumData['filetype'] === '0') {
                    $albumData['filetype'] = $data['format'];
                }

                // Store file data
                $albumData['files'][$filePath] = $data;
            }
        } catch (Exception $e) {
            $this->logger->error("Error analyzing files in bulk: " . $e->getMessage());
            $this->stats['errors']++;
            return $albumData; // Return empty album data
        }

        // Determine album metadata from most common values (ensure strings!)
        $albumData['album_name'] = (string)($this->mostCommon($albumNames) ?: $folderAlbum);
        $albumData['album_artist'] = (string)($this->mostCommon($albumArtists) ?: $folderArtist);
        $albumData['album_genre'] = (string)($this->determineAlbumGenre($genres));
        $albumData['album_year'] = (int)($this->mostCommon($years) ?: 0);
        $albumData['total_discs'] = max($highestDisc, $albumData['files'] === [] ? 0 : 1);
        $albumData['total_tracks'] = $highestTrack;

        // Get original year from first file
        if ($albumData['files'] !== []) {
            $firstFile = reset($albumData['files']);
            $albumData['original_year'] = $firstFile['original_year'] ?? 0;
        }

        return $albumData;
    }

    /**
     * @param array<mixed> $values
     */
    private function mostCommon(array $values): mixed
    {
        if ($values === []) {
            return null;
        }

        $counts = array_count_values($values);
        arsort($counts);
        return key($counts);
    }

    /**
     * Determine album genre intelligently to avoid too many "Mixed" entries
     *
     * Rules:
     * - If no genres: return empty string
     * - If only 1 unique genre: return that genre
     * - If 2-3 unique genres AND one genre is used in 50%+ of tracks: return that genre
     * - If 4+ unique genres: return "Mixed"
     *
     * @param array<string> $genres
     */
    private function determineAlbumGenre(array $genres): string
    {
        if ($genres === []) {
            return '';
        }

        // Count occurrences of each genre
        $counts = array_count_values($genres);
        $uniqueGenres = count($counts);
        $totalTracks = count($genres);

        // Get the most common genre and its count
        arsort($counts);
        /** @var string|null $mostCommonGenreKey */
        $mostCommonGenreKey = key($counts);
        if ($mostCommonGenreKey === null) {
            return '';
        }
        $mostCommonGenre = $mostCommonGenreKey;
        $mostCommonCount = (int)current($counts);

        // Rule 1: Only one genre - use it
        if ($uniqueGenres === 1) {
            return $mostCommonGenre;
        }

        // Rule 2: 2-3 different genres - use most common if it's >= 50% of tracks
        if ($uniqueGenres <= 3) {
            $percentage = ($mostCommonCount / $totalTracks) * 100;
            if ($percentage >= 50) {
                return $mostCommonGenre;
            }
        }

        // Rule 3: 4 or more different genres - mark as Mixed
        if ($uniqueGenres >= 4) {
            return 'Mixed';
        }

        // Fallback: Use most common genre
        return $mostCommonGenre;
    }

    /**
     * @param array<string> $files
     */
    private function filesChanged(array $files): bool
    {
        foreach ($files as $filePath) {
            $mtime = filemtime($filePath) ?: 0;

            if (!isset($this->existingSongs[$filePath])) {
                return true;
            }

            if ($this->existingSongs[$filePath]['last_mtime'] !== $mtime) {
                return true;
            }
        }

        return false;
    }

    private function getOrCreateArtist(string $name, string $musicDir): string
    {
        $name = trim($name);
        if ($name === '' || $name === '0') {
            $name = 'Unknown Artist';
        }

        if (isset($this->artistCache[$name])) {
            return $this->artistCache[$name];
        }

        $stmt = $this->stmts['artist_select'];
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $id = $row['artist_id'];
        } else {
            $id = Uuid::uuid4()->toString();
            $stmt = $this->stmts['artist_insert'];
            $stmt->execute([':id' => $id, ':name' => $name]);
            $this->logger->info("Created artist: $name");
        }

        $this->artistCache[$name] = $id;
        $this->findArtistImage($id, $name, $musicDir);

        return $id;
    }

    private function getOrCreateAlbum(string $artistId, string $name, string $path, string $albumArtist): string
    {
        $cacheKey = 'path:' . $path;

        if (isset($this->albumCache[$cacheKey])) {
            return $this->albumCache[$cacheKey];
        }

        $stmt = $this->stmts['album_by_path'];
        $stmt->execute([':path' => $path]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $id = $row['album_id'];
        } else {
            $id = Uuid::uuid4()->toString();
            $stmt = $this->stmts['album_create'];
            $stmt->execute([
                ':id' => $id,
                ':artistId' => $artistId,
                ':name' => $name,
                ':artist' => $albumArtist,
                ':path' => $path
            ]);
            $this->logger->info("Created album: $name");
            $this->stats['albums_created']++;
        }

        $this->albumCache[$cacheKey] = $id;
        return $id;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function updateAlbumMetadata(string $albumId, array $metadata): void
    {
        $stmt = $this->stmts['album_update'];
        $stmt->execute([
            ':id' => $albumId,
            ':name' => $metadata['album_name'],
            ':artist' => $metadata['album_artist'],
            ':discs' => $metadata['total_discs'],
            ':tracks' => $metadata['total_tracks'],
            ':origYear' => $metadata['original_year'],
            ':genre' => $metadata['album_genre'],
            ':year' => $metadata['album_year'],
            ':duration' => $metadata['album_duration'],
            ':filetype' => $metadata['filetype']
        ]);

        $this->stats['albums_updated']++;
    }

    /**
     * @param array<string> $files
     */
    private function processAlbumCover(string $albumId, string $albumPath, array $files): void
    {
        $coverDir = $this->config['coverDir'];
        $destCover = rtrim($coverDir, '/\\') . "/{$albumId}.webp";
        $thumbDest = rtrim($coverDir, '/\\') . "/{$albumId}_thumbnail.webp";

        // Check if cover already processed
        $coverExists = file_exists($destCover) && file_exists($thumbDest);

        // Check if gradient already calculated
        $stmt = $this->db->prepare("SELECT cover_gradient FROM albums WHERE album_id = :id");
        $stmt->execute([':id' => $albumId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $gradientExists = $row && !empty($row['cover_gradient']);

        if ($coverExists && $gradientExists) {
            return;
        }

        // Try local cover files
        $coverNames = $this->config['coverNames'] ?? ['cover', 'folder', 'front'];
        $coverExts = $this->config['coverExtensions'] ?? ['jpg', 'jpeg', 'png', 'webp'];

        $localCover = $this->locateImage($albumPath, $coverNames, $coverExts);

        if ($localCover !== null && $localCover !== '' && $localCover !== '0') {
            try {
                if (!$coverExists) {
                    $this->convertImageToWebp($localCover, $destCover);
                    $this->convertImageToWebp($localCover, $thumbDest, 250, 75);

                    $stmt = $this->db->prepare("UPDATE albums SET cover_path = :cover WHERE album_id = :id");
                    $stmt->execute([':id' => $albumId, ':cover' => $destCover]);

                    $this->logger->info("Saved album cover");
                }

                // Calculate gradient from cover
                if (!$gradientExists) {
                    $gradient = $this->calculateGradientFromImage($localCover);
                    if ($gradient !== null) {
                        $this->saveAlbumGradient($albumId, $gradient);
                    }
                }

                return;
            } catch (Exception $e) {
                $this->logger->error("Error converting cover: " . $e->getMessage());
            }
        }

        // Try embedded artwork using ffmpeg
        foreach (array_slice($files, 0, 3) as $musicFile) {
            try {
                $tempFile = tempnam(sys_get_temp_dir(), 'cover_') . '.jpg';

                $command = sprintf(
                    'ffmpeg -i %s -an -vcodec copy %s -y 2>&1',
                    escapeshellarg($musicFile),
                    escapeshellarg($tempFile)
                );

                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($tempFile) && filesize($tempFile) > 0) {
                    if (!$coverExists) {
                        $this->convertImageToWebp($tempFile, $destCover);
                        $this->convertImageToWebp($tempFile, $thumbDest, 250, 75);

                        $stmt = $this->db->prepare("UPDATE albums SET cover_path = :cover WHERE album_id = :id");
                        $stmt->execute([':id' => $albumId, ':cover' => $destCover]);

                        $this->logger->info("Extracted embedded cover");
                    }

                    // Calculate gradient from extracted cover
                    if (!$gradientExists) {
                        $gradient = $this->calculateGradientFromImage($tempFile);
                        if ($gradient !== null) {
                            $this->saveAlbumGradient($albumId, $gradient);
                        }
                    }

                    @unlink($tempFile);
                    return;
                }

                @unlink($tempFile);
            } catch (Exception $e) {
                $this->logger->debug("Could not extract cover: " . $e->getMessage());
            }
        }
    }

    /**
     * Calculate dominant colors from image and create gradient
     *
     * @param string $imagePath Path to image file
     * @return array{colors: array<string>, angle: int}|null Gradient data or null on failure
     */
    private function calculateGradientFromImage(string $imagePath): ?array
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        try {
            $info = @getimagesize($imagePath);
            if ($info === false) {
                return null;
            }

            $img = match ($info['mime']) {
                'image/jpeg' => @imagecreatefromjpeg($imagePath),
                'image/png' => @imagecreatefrompng($imagePath),
                'image/webp' => @imagecreatefromwebp($imagePath),
                default => null
            };

            if (!$img) {
                return null;
            }

            // Resize to small size for faster color analysis
            $width = imagesx($img);
            $height = imagesy($img);
            $sampleSize = 50;

            $sample = imagecreatetruecolor($sampleSize, $sampleSize);
            imagecopyresampled($sample, $img, 0, 0, 0, 0, $sampleSize, $sampleSize, $width, $height);

            // Extract dominant colors using k-means-like approach
            $colors = [];
            for ($y = 0; $y < $sampleSize; $y += 5) {
                for ($x = 0; $x < $sampleSize; $x += 5) {
                    $rgb = imagecolorat($sample, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    // Skip very dark or very light colors
                    $brightness = ($r + $g + $b) / 3;
                    if ($brightness > 20 && $brightness < 235) {
                        $colors[] = ['r' => $r, 'g' => $g, 'b' => $b];
                    }
                }
            }

            if (empty($colors)) {
                return null;
            }

            // Find 2-3 dominant colors using simple clustering
            $dominantColors = $this->findDominantColors($colors, 2);

            // Convert to hex
            $hexColors = array_map(function ($color) {
                return sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']);
            }, $dominantColors);

            // Determine gradient angle based on image dimensions
            $angle = $width > $height ? 135 : 180;

            return [
                'colors' => $hexColors,
                'angle' => $angle
            ];
        } catch (Exception $e) {
            $this->logger->debug("Error calculating gradient: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find dominant colors using simple clustering
     *
     * @param array<array{r: int, g: int, b: int}> $colors
     * @param int $numColors
     * @return array<array{r: int, g: int, b: int}>
     */
    private function findDominantColors(array $colors, int $numColors): array
    {
        if (count($colors) <= $numColors) {
            return array_slice($colors, 0, $numColors);
        }

        // Simple k-means: pick first and last colors as seeds
        $clusters = [];
        $step = max(1, (int)floor(count($colors) / $numColors));

        for ($i = 0; $i < $numColors; $i++) {
            $idx = min($i * $step, count($colors) - 1);
            $clusters[] = $colors[$idx];
        }

        // Average similar colors (simplified k-means)
        for ($iteration = 0; $iteration < 3; $iteration++) {
            $groups = array_fill(0, $numColors, []);

            foreach ($colors as $color) {
                $closestCluster = 0;
                $minDistance = PHP_FLOAT_MAX;

                foreach ($clusters as $idx => $cluster) {
                    $distance = abs($color['r'] - $cluster['r']) +
                        abs($color['g'] - $cluster['g']) +
                        abs($color['b'] - $cluster['b']);

                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                        $closestCluster = $idx;
                    }
                }

                $groups[$closestCluster][] = $color;
            }

            // Update cluster centers
            foreach ($groups as $idx => $group) {
                if (count($group) > 0) {
                    $avgR = (int)round(array_sum(array_column($group, 'r')) / count($group));
                    $avgG = (int)round(array_sum(array_column($group, 'g')) / count($group));
                    $avgB = (int)round(array_sum(array_column($group, 'b')) / count($group));
                    $clusters[$idx] = ['r' => $avgR, 'g' => $avgG, 'b' => $avgB];
                }
            }
        }

        return $clusters;
    }

    /**
     * Save gradient data to album
     *
     * @param string $albumId
     * @param array{colors: array<string>, angle: int} $gradient
     */
    private function saveAlbumGradient(string $albumId, array $gradient): void
    {
        try {
            $gradientJson = json_encode($gradient);
            $stmt = $this->db->prepare("UPDATE albums SET cover_gradient = :gradient WHERE album_id = :id");
            $stmt->execute([
                ':gradient' => $gradientJson,
                ':id' => $albumId
            ]);
            $this->logger->debug("Saved gradient for album");
        } catch (Exception $e) {
            $this->logger->error("Failed to save gradient: " . $e->getMessage());
        }
    }

    /**
     * Save gradient data to artist
     *
     * @param string $artistId
     * @param array{colors: array<string>, angle: int} $gradient
     */
    private function saveArtistGradient(string $artistId, array $gradient): void
    {
        try {
            $gradientJson = json_encode($gradient);
            $stmt = $this->db->prepare("UPDATE artists SET artist_gradient = :gradient WHERE artist_id = :id");
            $stmt->execute([
                ':gradient' => $gradientJson,
                ':id' => $artistId
            ]);
            $this->logger->debug("Saved gradient for artist");
        } catch (Exception $e) {
            $this->logger->error("Failed to save artist gradient: " . $e->getMessage());
        }
    }

    /**
     * Process multiple songs in BULK (much faster than individual inserts!)
     *
     * @param string $albumId Album ID
     * @param array<string, array<string, mixed>> $filesData Array of file metadata
     */
    private function processSongsBulk(string $albumId, array $filesData): void
    {
        if ($filesData === []) {
            return;
        }

        $toInsert = [];
        $toUpdate = [];

        // First pass: categorize songs (insert vs update)
        foreach ($filesData as $filePath => $data) {
            $mtime = filemtime($filePath) ?: 0;

            // Check if song exists
            $existing = $this->existingSongs[$filePath] ?? null;

            $songData = [
                'albumId' => $albumId,
                'disc' => $data['disc_number'],
                'track' => $data['track_number'],
                'title' => $data['title'],
                'artist' => $data['artist'],
                'genre' => $data['genre'],
                'year' => $data['year'],
                'duration' => $data['duration'],
                'bitrate' => $data['bitrate'],
                'size' => $data['file_size'],
                'mtime' => $mtime,
                'type' => $data['file_extension'],
                'path' => $filePath
            ];

            if (!$existing) {
                $songId = Uuid::uuid4()->toString();
                $songData['id'] = $songId;
                $toInsert[] = $songData;
                $this->foundSongIDs[$filePath] = $songId;
            } else {
                $songId = $existing['song_id'];
                if ($this->forceFullRescan || $existing['last_mtime'] !== $mtime) {
                    $songData['id'] = $songId;
                    $toUpdate[] = $songData;
                }
                $this->foundSongIDs[$filePath] = $songId;
            }
        }

        // Bulk INSERT
        if ($toInsert !== []) {
            $this->bulkInsertSongs($toInsert);
            $this->stats['songs_created'] += count($toInsert);
            $this->stats['files_processed'] += count($toInsert);
        }

        // Bulk UPDATE
        if ($toUpdate !== []) {
            $this->bulkUpdateSongs($toUpdate);
            $this->stats['songs_updated'] += count($toUpdate);
            $this->stats['files_processed'] += count($toUpdate);
        }

        // Songs that were neither inserted nor updated
        $processedCount = count($toInsert) + count($toUpdate);
        $skippedCount = count($filesData) - $processedCount;
        if ($skippedCount > 0) {
            $this->stats['files_processed'] += $skippedCount;
        }
    }

    /**
     * Bulk INSERT songs using prepared statement (reused for all songs)
     * Much faster than individual inserts
     *
     * @param array<int, array<string, mixed>> $songs Songs to insert
     */
    private function bulkInsertSongs(array $songs): void
    {
        if ($songs === []) {
            return;
        }

        // Build multi-row INSERT for PostgreSQL performance
        $columns = 'song_id, album_id, disc_number, track_number, title, artist, genre, year, duration, bitrate, size, last_mtime, file_path, filetype';
        $placeholders = [];
        $params = [];
        $i = 0;

        foreach ($songs as $song) {
            $placeholders[] = "(:id{$i}, :albumId{$i}, :disc{$i}, :track{$i}, "
                . ":title{$i}, :artist{$i}, :genre{$i}, :year{$i}, "
                . ":duration{$i}, :bitrate{$i}, :size{$i}, :mtime{$i}, "
                . ":path{$i}, :type{$i})";
            $params[":id{$i}"] = $song['id'];
            $params[":albumId{$i}"] = $song['albumId'];
            $params[":disc{$i}"] = $song['disc'];
            $params[":track{$i}"] = $song['track'];
            $params[":title{$i}"] = $song['title'];
            $params[":artist{$i}"] = $song['artist'];
            $params[":genre{$i}"] = $song['genre'];
            $params[":year{$i}"] = $song['year'];
            $params[":duration{$i}"] = $song['duration'];
            $params[":bitrate{$i}"] = $song['bitrate'];
            $params[":size{$i}"] = $song['size'];
            $params[":mtime{$i}"] = $song['mtime'];
            $params[":path{$i}"] = $song['path'];
            $params[":type{$i}"] = $song['type'];
            $i++;
        }

        $sql = "INSERT INTO songs ({$columns}) VALUES " . implode(', ', $placeholders);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        if ($this->debugMode) {
            $this->logger->debug("Bulk inserted " . count($songs) . " songs");
        }
    }

    /**
     * Bulk UPDATE songs using CASE statements
     *
     * @param array<int, array<string, mixed>> $songs Songs to update
     */
    private function bulkUpdateSongs(array $songs): void
    {
        if ($songs === []) {
            return;
        }

        // Build multi-row UPDATE via VALUES clause for PostgreSQL performance
        $valuePlaceholders = [];
        $params = [];
        $i = 0;

        foreach ($songs as $song) {
            $valuePlaceholders[] = "(:id{$i}, :albumId{$i}, :disc{$i}::int, "
                . ":track{$i}::int, :title{$i}, :artist{$i}, :genre{$i}, "
                . ":year{$i}::int, :duration{$i}::int, :bitrate{$i}, "
                . ":size{$i}::int, :mtime{$i}::int, :type{$i})";
            $params[":id{$i}"] = $song['id'];
            $params[":albumId{$i}"] = $song['albumId'];
            $params[":disc{$i}"] = $song['disc'];
            $params[":track{$i}"] = $song['track'];
            $params[":title{$i}"] = $song['title'];
            $params[":artist{$i}"] = $song['artist'];
            $params[":genre{$i}"] = $song['genre'];
            $params[":year{$i}"] = $song['year'];
            $params[":duration{$i}"] = $song['duration'];
            $params[":bitrate{$i}"] = $song['bitrate'];
            $params[":size{$i}"] = $song['size'];
            $params[":mtime{$i}"] = $song['mtime'];
            $params[":type{$i}"] = $song['type'];
            $i++;
        }

        $sql = "UPDATE songs SET
                album_id = t.album_id,
                disc_number = t.disc_number,
                track_number = t.track_number,
                title = t.title,
                artist = t.artist,
                genre = t.genre,
                year = t.year,
                duration = t.duration,
                bitrate = t.bitrate,
                size = t.size,
                last_mtime = t.last_mtime,
                filetype = t.filetype
            FROM (VALUES " . implode(', ', $valuePlaceholders) . ") AS t(song_id, album_id, disc_number,
                track_number, title, artist, genre, year, duration,
                bitrate, size, last_mtime, filetype)
            WHERE songs.song_id = t.song_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        if ($this->debugMode) {
            $this->logger->debug("Bulk updated " . count($songs) . " songs");
        }
    }

    private function calculateAlbumStats(string $albumId): void
    {
        // IMPORTANT: Reuse pre-prepared statements to avoid locks during transaction!
        $stmt = $this->stmts['album_stats_select'];
        $stmt->execute([':id' => $albumId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return;
        }

        $duration = (int)$result['total_duration'];

        $genres = $result['genres'] ? explode(',', $result['genres']) : [];
        $genre = count(array_unique($genres)) === 1 ? $genres[0] : 'Mixed';

        $years = $result['years'] ? array_values(array_filter(explode(',', $result['years']))) : [];
        $year = count(array_unique($years)) === 1 ? (int)$years[0] : 0;

        // IMPORTANT: Reuse pre-prepared statement to avoid locks during transaction!
        $stmt = $this->stmts['album_stats_update'];
        $stmt->execute([
            ':duration' => $duration,
            ':genre' => $genre,
            ':year' => $year,
            ':id' => $albumId
        ]);
    }

    private function cleanup(): void
    {
        $this->logger->info("Cleaning up database...");

        // Mark missing songs as deleted
        $missingIds = [];
        foreach ($this->existingSongs as $path => $data) {
            if (!isset($this->foundSongIDs[$path])) {
                $missingIds[] = $data['song_id'];
            }
        }

        if ($missingIds !== []) {
            $placeholders = rtrim(str_repeat('?,', count($missingIds)), ',');
            $stmt = $this->db->prepare("UPDATE songs SET is_deleted = 1 WHERE song_id IN ($placeholders)");
            $stmt->execute($missingIds);
            $this->stats['songs_deleted'] = count($missingIds);
        }

        // Mark empty albums as deleted
        $stmt = $this->db->prepare("
            UPDATE albums SET is_deleted = 1
            WHERE is_deleted = 0 AND NOT EXISTS (
                SELECT 1 FROM songs WHERE songs.album_id = albums.album_id AND songs.is_deleted = 0
            )
        ");
        $stmt->execute();
    }

    private function findArtistImage(string $artistId, string $name, string $musicDir): void
    {
        $artistImagesDir = $this->config['artistImagesDir'];
        $webpDest = rtrim($artistImagesDir, '/\\') . "/{$artistId}.webp";
        $thumbDest = rtrim($artistImagesDir, '/\\') . "/{$artistId}_thumbnail.webp";

        // Relative path for database storage (to work with ImageService)
        $relativePath = "/artistImages/{$artistId}.webp";

        // Check if both files already exist
        $filesExist = file_exists($webpDest) && file_exists($thumbDest);

        // Check if gradient already calculated
        $stmt = $this->db->prepare("SELECT artist_gradient FROM artists WHERE artist_id = :id");
        $stmt->execute([':id' => $artistId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $gradientExists = $row && !empty($row['artist_gradient']);

        // If files exist, update database and calculate gradient if missing
        if ($filesExist) {
            $this->updateArtistImagePath($artistId, $relativePath);

            if (!$gradientExists && file_exists($webpDest)) {
                $gradient = $this->calculateGradientFromImage($webpDest);
                if ($gradient !== null) {
                    $this->saveArtistGradient($artistId, $gradient);
                }
            }
            return;
        }

        $artistFolder = $musicDir . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($artistFolder)) {
            return;
        }

        $imageNames = $this->config['artistImageNames'] ?? ['artist', 'folder'];
        $imageExts = $this->config['artistImageExtensions'] ?? ['jpg', 'jpeg', 'png'];

        $found = $this->locateImage($artistFolder, $imageNames, $imageExts);

        if ($found !== null && $found !== '' && $found !== '0') {
            try {
                $this->convertImageToSquareWebp($found, $webpDest, 450, 80);
                $this->convertImageToSquareWebp($found, $thumbDest, 250, 75);
                $this->logger->info("Saved artist image for $name");

                // Update database with image path
                $this->updateArtistImagePath($artistId, $relativePath);

                // Calculate and save gradient
                $gradient = $this->calculateGradientFromImage($webpDest);
                if ($gradient !== null) {
                    $this->saveArtistGradient($artistId, $gradient);
                }
            } catch (Exception $e) {
                $this->logger->error("Error processing artist image: " . $e->getMessage());
            }
        }
    }

    /**
     * Update artist image path in database
     */
    private function updateArtistImagePath(string $artistId, string $imagePath): void
    {
        try {
            $stmt = $this->db->prepare("UPDATE artists SET image = :image WHERE artist_id = :artistId");
            $stmt->execute([
                ':image' => $imagePath,
                ':artistId' => $artistId
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to update artist image path in database: " . $e->getMessage());
        }
    }

    /**
     * @param array<string> $names
     * @param array<string> $extensions
     */
    private function locateImage(string $folder, array $names, array $extensions): ?string
    {
        foreach ($names as $name) {
            foreach ($extensions as $ext) {
                $candidate = rtrim($folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . '.' . $ext;
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
        }
        return null;
    }

    private function convertImageToWebp(
        string $source,
        string $dest,
        int $maxSize = 500,
        int $quality = 80
    ): void {
        if (!extension_loaded('gd')) {
            throw new RuntimeException("GD extension required");
        }

        $info = @getimagesize($source);
        if ($info === false) {
            throw new RuntimeException("Invalid image: $source");
        }

        $src = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => @imagecreatefromwebp($source),
            default => throw new RuntimeException("Unsupported format: {$info['mime']}")
        };

        if (!$src) {
            throw new RuntimeException("Failed to create image from: $source");
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        $aspect = $origW / $origH;
        if ($origW >= $origH) {
            $newW = min($maxSize, $origW);
            $newH = (int)round($newW / $aspect);
        } else {
            $newH = min($maxSize, $origH);
            $newW = (int)round($newH * $aspect);
        }

        // Ensure dimensions are at least 1
        $newW = max(1, $newW);
        $newH = max(1, $newH);

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $destDir = dirname($dest);
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            throw new RuntimeException("Failed to create directory: $destDir");
        }

        imagewebp($dst, $dest, $quality);
    }

    private function convertImageToSquareWebp(
        string $source,
        string $dest,
        int $size = 450,
        int $quality = 80
    ): void {
        if (!extension_loaded('gd')) {
            throw new RuntimeException("GD extension required");
        }

        $info = @getimagesize($source);
        if ($info === false) {
            throw new RuntimeException("Invalid image: $source");
        }

        $src = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => @imagecreatefromwebp($source),
            default => throw new RuntimeException("Unsupported format: {$info['mime']}")
        };

        if (!$src) {
            throw new RuntimeException("Failed to create image from: $source");
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        $cropSize = min($origW, $origH);
        $srcX = (int)(($origW - $cropSize) / 2);
        $srcY = (int)(($origH - $cropSize) / 2);

        // Ensure size is at least 1
        $size = max(1, $size);
        $dst = imagecreatetruecolor($size, $size);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $size, $size, $cropSize, $cropSize);

        $destDir = dirname($dest);
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            throw new RuntimeException("Failed to create directory: $destDir");
        }

        imagewebp($dst, $dest, $quality);
    }

    private function updateAllArtistImages(): void
    {
        $this->logger->info("Updating artist images...");
        $stmt = $this->db->query("SELECT artist_id, artist_name FROM artists WHERE is_deleted = 0");
        if ($stmt === false) {
            throw new RuntimeException("Failed to query artists");
        }

        $count = 0;
        while ($artist = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->findArtistImage($artist['artist_id'], $artist['artist_name'], $this->config['musicDir']);
            $count++;

            if ($count % 100 === 0) {
                $this->logger->info("Processed $count artists...");
            }
        }

        $this->logger->info("Processed $count artists");
    }

    private function updateAllAlbumCovers(): void
    {
        $this->logger->info("Updating album covers...");
        $stmt = $this->db->query("
            SELECT album_id, folder_path
            FROM albums
            WHERE is_deleted = 0 AND folder_path IS NOT NULL
        ");
        if ($stmt === false) {
            throw new RuntimeException("Failed to query albums");
        }

        $count = 0;
        $allowedExtensions = $this->config['allowedExtensions'] ?? ['mp3', 'flac', 'm4a', 'ogg'];

        while ($album = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $albumPath = $this->config['musicDir'] . DIRECTORY_SEPARATOR . $album['folder_path'];

            if (!is_dir($albumPath)) {
                continue;
            }

            $files = [];
            foreach ($allowedExtensions as $ext) {
                $found = glob($albumPath . DIRECTORY_SEPARATOR . "*.$ext");
                if ($found !== false) {
                    $files = array_merge($files, $found);
                }
            }

            if ($files !== []) {
                $this->processAlbumCover($album['album_id'], $albumPath, $files);
                $count++;
            }

            if ($count % 100 === 0) {
                $this->logger->info("Processed $count albums...");
            }
        }

        $this->logger->info("Processed $count albums");
    }

    private function updateAllAlbumGradients(): void
    {
        $this->logger->info("Calculating gradients for all albums...");
        $coverDir = $this->config['coverDir'];
        $coverNames = $this->config['coverNames'] ?? ['cover', 'folder', 'front'];
        $coverExts = $this->config['coverExtensions'] ?? ['jpg', 'jpeg', 'png', 'webp'];

        // If --full flag, recalculate all gradients. Otherwise only missing ones
        $whereClause = $this->forceFullRescan
            ? "WHERE is_deleted = 0 AND folder_path IS NOT NULL"
            : "WHERE is_deleted = 0 AND folder_path IS NOT NULL AND (cover_gradient IS NULL OR cover_gradient = '')";

        $stmt = $this->db->query("
            SELECT album_id, folder_path
            FROM albums
            $whereClause
        ");
        if ($stmt === false) {
            throw new RuntimeException("Failed to query albums");
        }

        $count = 0;
        $gradientCount = 0;

        while ($album = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $albumId = $album['album_id'];
            $albumPath = $this->config['musicDir'] . DIRECTORY_SEPARATOR . $album['folder_path'];

            // Try to find cover image
            $coverImage = null;

            // 1. Check if webp cover already exists
            $webpCover = rtrim($coverDir, '/\\') . "/{$albumId}.webp";
            if (file_exists($webpCover)) {
                $coverImage = $webpCover;
            } else {
                // 2. Look for local cover in album folder
                if (is_dir($albumPath)) {
                    $coverImage = $this->locateImage($albumPath, $coverNames, $coverExts);
                }
            }

            // Calculate gradient if cover found
            if ($coverImage !== null && $coverImage !== '' && $coverImage !== '0') {
                $gradient = $this->calculateGradientFromImage($coverImage);
                if ($gradient !== null) {
                    $this->saveAlbumGradient($albumId, $gradient);
                    $gradientCount++;
                }
            }

            $count++;
            if ($count % 100 === 0) {
                $this->logger->info("Processed $count albums, calculated $gradientCount gradients...");
            }
        }

        $this->logger->info("Processed $count albums, calculated $gradientCount gradients");
    }

    private function updateAllArtistGradients(): void
    {
        $this->logger->info("Calculating gradients for all artists...");
        $artistImagesDir = $this->config['artistImagesDir'];

        // If --full flag, recalculate all gradients. Otherwise only missing ones
        $whereClause = $this->forceFullRescan
            ? "WHERE is_deleted = 0 AND image IS NOT NULL"
            : "WHERE is_deleted = 0 AND image IS NOT NULL AND (artist_gradient IS NULL OR artist_gradient = '')";

        $stmt = $this->db->query("
            SELECT artist_id, artist_name
            FROM artists
            $whereClause
        ");
        if ($stmt === false) {
            throw new RuntimeException("Failed to query artists");
        }

        $count = 0;
        $gradientCount = 0;

        while ($artist = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $artistId = $artist['artist_id'];

            // Check if webp artist image exists
            $webpImage = rtrim($artistImagesDir, '/\\') . "/{$artistId}.webp";
            if (file_exists($webpImage)) {
                $gradient = $this->calculateGradientFromImage($webpImage);
                if ($gradient !== null) {
                    $this->saveArtistGradient($artistId, $gradient);
                    $gradientCount++;
                }
            }

            $count++;
            if ($count % 100 === 0) {
                $this->logger->info("Processed $count artists, calculated $gradientCount gradients...");
            }
        }

        $this->logger->info("Processed $count artists, calculated $gradientCount gradients");
    }

    private function fixAllFiletypes(): void
    {
        $this->logger->info("Normalizing filetype values...");

        // Mapping for filetype normalization (case-sensitive!)
        $filetypeMap = [
            'FLAC' => 'flac',
            'MPEG Audio' => 'mp3',
            'MPEG AUDIO' => 'mp3',
            'MP3' => 'mp3',
            'WAVE' => 'wav',
            'WAV' => 'wav',
            'Vorbis' => 'ogg',
            'OGG' => 'ogg',
            'MPEG-4' => 'm4a',
            'M4A' => 'm4a',
            'AAC' => 'aac',
            'WMA' => 'wma',
            'AIFF' => 'aiff',
            'APE' => 'ape',
            'Musepack' => 'mpc',
            'MUSEPACK SV8' => 'mpc',
            'MPC' => 'mpc',
            'Opus' => 'opus',
            'OPUS' => 'opus',
        ];

        // Fix songs table
        $this->logger->info("Fixing songs.filetype...");
        $songsFixed = 0;
        foreach ($filetypeMap as $oldValue => $newValue) {
            $stmt = $this->db->prepare("UPDATE songs SET filetype = :new WHERE filetype = :old");
            $stmt->execute([':old' => $oldValue, ':new' => $newValue]);
            $songsFixed += $stmt->rowCount();
        }
        $this->logger->info("Updated $songsFixed song records");

        // Fix albums table
        $this->logger->info("Fixing albums.filetype...");
        $albumsFixed = 0;
        foreach ($filetypeMap as $oldValue => $newValue) {
            $stmt = $this->db->prepare("UPDATE albums SET filetype = :new WHERE filetype = :old");
            $stmt->execute([':old' => $oldValue, ':new' => $newValue]);
            $albumsFixed += $stmt->rowCount();
        }
        $this->logger->info("Updated $albumsFixed album records");

        $this->logger->info("Filetype normalization complete");
    }

    private function listMissingArtistImages(): void
    {
        $stmt = $this->db->query("
            SELECT artist_name, artist_id
            FROM artists
            WHERE is_deleted = 0 AND image IS NULL
            ORDER BY artist_name
        ");
        if ($stmt === false) {
            throw new RuntimeException("Failed to query artists");
        }

        echo "\nArtists with missing images:\n";
        echo str_repeat("=", 80) . "\n";

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo sprintf("%-40s %s\n", substr($row['artist_name'], 0, 39), $row['artist_id']);
            $count++;
        }

        echo str_repeat("=", 80) . "\n";
        echo "Total: $count artists\n";
    }

    private function listMissingCoverImages(): void
    {
        $stmt = $this->db->query("
            SELECT a.album_name, ar.artist_name, a.album_id
            FROM albums a
            JOIN artists ar ON a.artist_id = ar.artist_id
            WHERE a.is_deleted = 0 AND (a.cover_path IS NULL OR a.cover_path = '')
            ORDER BY ar.artist_name, a.album_name
        ");
        if ($stmt === false) {
            throw new RuntimeException("Failed to query albums");
        }

        echo "\nAlbums without covers:\n";
        echo str_repeat("=", 100) . "\n";

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo sprintf(
                "%-30s %-30s %s\n",
                substr($row['artist_name'], 0, 29),
                substr($row['album_name'], 0, 29),
                $row['album_id']
            );
            $count++;
        }

        echo str_repeat("=", 100) . "\n";
        echo "Total: $count albums\n";
    }

    private function logStatistics(float $duration): void
    {
        $this->logger->info("Scan complete in $duration seconds");
        $this->logger->info("STATISTICS:");
        $this->logger->info("  Albums created: {$this->stats['albums_created']}");
        $this->logger->info("  Albums updated: {$this->stats['albums_updated']}");
        $this->logger->info("  Albums skipped: {$this->stats['albums_skipped']}");
        $this->logger->info("  Songs created: {$this->stats['songs_created']}");
        $this->logger->info("  Songs updated: {$this->stats['songs_updated']}");
        $this->logger->info("  Songs deleted: {$this->stats['songs_deleted']}");
        $this->logger->info("  Files processed: {$this->stats['files_processed']}");
        $this->logger->info("  Errors: {$this->stats['errors']}");
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setScanStatus(string $status, array $data = []): void
    {
        try {
            $fields = array_merge([
                'status' => $status,
                'process_id' => $this->processId,
            ], $data);

            // Normalize numeric fields for Postgres (avoid empty-string to integer errors)
            $intFields = ['process_id', 'full_scan', 'start_time', 'end_time', 'total_albums', 'processed_albums', 'duration'];
            foreach ($fields as $key => $value) {
                if (in_array($key, $intFields, true)) {
                    if ($value === '' || $value === null) {
                        $fields[$key] = null;
                    } else {
                        $fields[$key] = (int)$value;
                    }
                }
            }

            if ($this->statusRecordId !== null && $this->statusRecordId !== 0) {
                $sets = [];
                $params = ['id' => $this->statusRecordId];

                foreach ($fields as $key => $value) {
                    $sets[] = "$key = :$key";
                    $params[$key] = is_array($value) ? json_encode($value) : $value;
                }

                $sql = "UPDATE scan_status SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            } else {
                $keys = array_keys($fields);
                $placeholders = array_map(fn($k): string => ":$k", $keys);

                $sql = "INSERT INTO scan_status (" . implode(', ', $keys) . ", created_at) VALUES (" .
                    implode(', ', $placeholders) . ", NOW())";

                $stmt = $this->db->prepare($sql);
                $params = [];
                foreach ($fields as $key => $value) {
                    $params[$key] = is_array($value) ? json_encode($value) : $value;
                }
                $stmt->execute($params);
                $this->statusRecordId = (int)$this->db->lastInsertId();
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to set scan status: " . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateScanProgress(array $data): void
    {
        if ($this->statusRecordId === null || $this->statusRecordId === 0) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE scan_status
                SET processed_albums = :processed,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'processed' => $data['processed_albums'] ?? 0,
                'id' => $this->statusRecordId
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to update progress: " . $e->getMessage());
        }
    }

    private function determineScanOption(): string
    {
        if ($this->updateArtistImage) {
            return 'update-artist-image';
        }
        if ($this->updateCoverImages) {
            return 'update-cover-images';
        }
        if ($this->updateGradients) {
            return 'update-gradients';
        }
        if ($this->fixFiletypes) {
            return 'fix-filetypes';
        }
        if ($this->listMissingArtistImages) {
            return 'list-missing-artist-images';
        }
        if ($this->listMissingCoverImages) {
            return 'list-missing-cover-images';
        }
        if ($this->forceFullRescan) {
            return 'full-scan';
        }
        return 'normal-scan';
    }
}
