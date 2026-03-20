<?php

/**
 * config/config.php - auto-generated if missing
 * Adjust these values as needed.
 */

// For referencing the project root:
$rootDir = dirname(__DIR__, 2); // e.g. /var/www/html

return [

    // JWT Authentication - MUST be set via JWT_SECRET env var or changed here
    'jwtSecret' => getenv('JWT_SECRET') ?: bin2hex(random_bytes(32)),


    // System Settings
    // NEVER CHANGE THIS SETTINGS -- WILL BREAK THE SYSTEM

    // Database (PostgreSQL only)
    'dbDriver' => 'pgsql',
    'dbHost' => 'postgres',
    'dbPort' => 5432,
    'dbName' => 'audinary',
    'dbUser' => 'audinary',
    'dbPassword' => '',

    // Folders
    'rootDir' => $rootDir,
    'musicDir' => $rootDir . '/var/music',
    'configDir' => $rootDir . '/var/config',
    'logDir' => $rootDir . '/var/logs',
    'coverDir' => $rootDir . '/public/img/userdata/albums',
    'artistImagesDir' => $rootDir . '/public/img/userdata/artists',
    'playlistCoversDir' => $rootDir . '/public/img/userdata/playlists',
    'profileDir' => $rootDir . '/public/img/userdata/profiles',
    'transcodeCache' => $rootDir . '/var/cache/transcode',
    'backupDir' => $rootDir . '/var/backups',
    'nginxInternalPath' => '/protected_music/',


    // IMAGE DEFAULTS
    // Image placeholders
    'albumPlaceholder' => $rootDir . '/public/img/placeholder_audinary.png',
    'artistPlaceholder' => $rootDir . '/public/img/placeholder_audinary.png',
    'profilePlaceholder' => $rootDir . '/public/img/placeholder_audinary.png',

    // Login background images
    'loginBackgroundDir' => $rootDir . '/public/img/login_background',
    'loginBackgroundExtensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'],

    // Image service configuration
    'bufferSize' => 8192,
    'cacheTTL' => 604800 // 7 days
];
