#!/usr/bin/env php
<?php

/**
 * clear_media_data.php
 *
 * This script empties all media-related tables in your database by temporarily disabling
 * foreign key checks, and also removes all album cover and artist image files.
 *
 * WARNING: This action is irreversible. Ensure you have backups if needed.
 *
 * Usage: php clear_media_data.php
 */

use App\Database\Connection;

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include autoloader and helper functions (providing getPDO() and loadConfig())
require __DIR__ . '/../vendor/autoload.php';

// Get configuration
$config = loadConfig();

// Log setup
$logFile = $config['logDir'] . '/empty_db_and_clear_images.log';
if (file_exists($logFile)) {
    unlink($logFile);
}
function logMessage(string $msg): void
{
    global $logFile;
    $formatted = date('[Y-m-d H:i:s] ') . $msg . "\n";
    file_put_contents($logFile, $formatted, FILE_APPEND);
    echo $formatted;
}

 // Include autoloader and helper functions (providing getPDO() and loadConfig())
 require __DIR__ . '/../vendor/autoload.php';

 // Get configuration and database connection
 $db     = Connection::getPDO();

 // Directories for images
 $artistImagesDir = $config['artistImagesDir'];
 $coverDir        = $config['coverDir'];

 logMessage("Starting to empty the media database and clear image files...");

try {
    // Disable foreign key checks to avoid constraint errors.
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    // List of tables to empty; adjust table names as necessary.
    $tables = [
       'playlists',
        'playlist_songs',
        'play_history',
        'favorites',
        'songs',
        'albums',
        'artists'
    ];

    foreach ($tables as $table) {
        $db->exec("TRUNCATE TABLE `$table`");
        logMessage("Emptied table: $table");
    }

    // Re-enable foreign key checks.
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    logMessage("Media database has been emptied successfully.");
} catch (Exception $e) {
    logMessage("Error emptying media database entries: " . $e->getMessage());
    exit(1);
}

 // Helper: Recursively delete a directory and its contents.
function deleteDirectory(string $dir): void
{
    if (!file_exists($dir)) {
        return;
    }
    if (!is_dir($dir)) {
        unlink($dir);
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.') {
            continue;
        }
        if ($item === '..') {
            continue;
        }
        deleteDirectory($dir . DIRECTORY_SEPARATOR . $item);
    }
    rmdir($dir);
}

 // Remove image directories.
 deleteDirectory($coverDir);
 deleteDirectory($artistImagesDir);
 logMessage("All album cover and artist image files have been removed.");

 // Optionally, recreate empty directories.
if (!is_dir($coverDir)) {
    mkdir($coverDir, 0755, true);
    logMessage("Recreated album covers directory.");
}
if (!is_dir($artistImagesDir)) {
    mkdir($artistImagesDir, 0755, true);
    logMessage("Recreated artist images directory.");
}

 logMessage("The media database and associated images have been cleared.");
 exit();
