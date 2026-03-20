<?php

// src/ConfigHelper.php

use App\Services\GlobalSettingsService;
use App\Database\Connection;

/**
 * loadConfig
 *
 * Ensures that:
 *  1) We have a project root (one level above "public/")
 *  2) /config folder exists
 *  3) /var/config/config.php exists (creates a default if missing)
 *  4) We load that config and merge with database settings
 *  5) We create all required subfolders if needed
 *
 * @return array<string, mixed> The config array
 */
function loadConfig(): array
{
    // 1) Define the project root
    $rootDir = realpath(__DIR__ . '/../');
    // e.g. If this file is in /var/www/html/src, then __DIR__ . '/../' => /var/www/html

    // 2) Ensure /config folder
    $configDir = $rootDir . '/var/config';
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    // 3) Check if /var/config/config.php exists; if not, copy it from the src folder
    $configFile = $configDir . '/config.php';
    if (!file_exists($configFile)) {
        // Define the source config_sample.php location in the src folder
        $configSample = $rootDir . '/src/config_sample.php';
        if (file_exists($configSample)) {
            if (!copy($configSample, $configFile)) {
                trigger_error("Failed to copy config_sample.php from src folder to config/config.php", E_USER_ERROR);
            }
        } else {
            trigger_error("Configuration file is missing and no config_sample.php found in the src folder.", E_USER_ERROR);
        }
    }

    // 4) Load config
    $config = include $configFile;

    // 5) Merge with database settings (if database is available)
    try {
        $pdo = Connection::getPDO();
        $globalSettings = new GlobalSettingsService($pdo);

        // Override with database settings
        $config['mpd'] = $globalSettings->getMpdConfig();
        $config['smtp'] = $globalSettings->getSmtpConfig();
        $config['passwordReset'] = $globalSettings->getPasswordResetConfig();
        $config['backup'] = $globalSettings->getBackupConfig();

        $musicConfig = $globalSettings->getMusicScanConfig();
        $config['allowedExtensions'] = $musicConfig['allowedExtensions'];
        $config['coverNames'] = $musicConfig['coverNames'];
        $config['coverExtensions'] = $musicConfig['coverExtensions'];
        $config['artistImageNames'] = $musicConfig['artistImageNames'];
        $config['artistImageExtensions'] = $musicConfig['artistImageExtensions'];

        // Add registration setting
        $config['registration'] = [
            'enabled' => $globalSettings->isUserRegistrationEnabled()
        ];
    } catch (Exception $e) {
        // Database not available or error - use config file only
        error_log("Warning: Could not load global settings from database: " . $e->getMessage());
    }


    // 5) Create all required directories if they don't exist
    $directories = [
        'configDir',
        'logDir',
        'coverDir',
        'artistImagesDir',
        'playlistCoversDir',
        'profileDir',
        'transcodeCache',
        'backupDir'
    ];

    foreach ($directories as $dirKey) {
        if (!is_dir($config[$dirKey])) {
            mkdir($config[$dirKey], 0755, true);
        } elseif (!is_writable($config[$dirKey])) {
            // Ensure the directory is writable
            trigger_error("Directory {$config[$dirKey]} is not writable.", E_USER_ERROR);
        }
    }

    return $config;
}
