<?php

/**
 * Automated Backup Creation Script
 * This script should be run periodically (e.g., via cron) to create system backups
 *
 * Usage: php create_backup.php
 * Recommended cron: Run daily at 1 AM
 * Example cron entry: 0 1 * * * /usr/bin/php /var/www/html/server/scripts/create_backup.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/configHelper.php';

use App\Services\BackupService;

try {
    $config = loadConfig();
    $backupService = new BackupService($config);

    echo "Starting automated backup creation...\n";
    echo "====================================\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

    // Get current backup count before creation
    $backupsBefore = $backupService->listBackups();
    $countBefore = count($backupsBefore);

    echo "Current backups: {$countBefore}\n";

    // Show retention settings
    $retentionDays = $config['backup']['retentionDays'] ?? 30;
    $maxBackups = $config['backup']['maxBackups'] ?? 10;
    echo "Retention policy: {$retentionDays} days, max {$maxBackups} backups\n\n";

    echo "Creating backup...\n";
    $startTime = microtime(true);

    // Create the backup
    $result = $backupService->createBackup();

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    if ($result['success']) {
        echo "✓ Backup created successfully!\n";
        echo "  Filename: {$result['filename']}\n";
        echo "  Size: {$result['sizeFormatted']}\n";
        echo "  Duration: {$duration} seconds\n\n";

        // Show updated backup list
        $backupsAfter = $backupService->listBackups();
        $countAfter = count($backupsAfter);

        echo "Total backups after creation: {$countAfter}\n";

        if ($countAfter > 0) {
            echo "\nRecent backups:\n";
            $recentBackups = array_slice($backupsAfter, 0, 5); // Show 5 most recent
            foreach ($recentBackups as $backup) {
                echo "  - {$backup['filename']} ({$backup['sizeFormatted']}) - {$backup['created']}\n";
            }
        }

        // Check if any old backups were cleaned up
        $cleanedUp = $countBefore + 1 - $countAfter;
        if ($cleanedUp > 0) {
            echo "\n🗑️  Cleaned up {$cleanedUp} old backup(s) due to retention policy\n";
        }

        echo "\n✅ Automated backup completed successfully.\n";

        // Log success to application log
        error_log("Automated backup created successfully: {$result['filename']} ({$result['sizeFormatted']}) in {$duration}s");

        // Exit with success code
        exit(0);
    }
    echo "❌ Backup creation failed!\n";
    echo "Error: {$result['message']}\n";
    // Log failure to application log
    error_log("Automated backup creation failed: {$result['message']}");
    // Exit with error code
    exit(1);
} catch (Exception $e) {
    echo "❌ Script error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";

    // Log script error
    error_log("Automated backup script error: " . $e->getMessage());

    exit(1);
}
