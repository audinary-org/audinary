<?php

/**
 * Backup Cleanup Script
 * This script should be run periodically (e.g., via cron) to clean up old backups
 *
 * Usage: php cleanup_backups.php
 * Recommended cron: Run daily at 2 AM
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/configHelper.php';

use App\Services\BackupService;

try {
    $config = loadConfig();
    $backupService = new BackupService($config);

    echo "Starting backup cleanup...\n";
    echo "========================\n";

    // Get current backup list
    $backupsBefore = $backupService->listBackups();
    $countBefore = count($backupsBefore);

    echo "Backups before cleanup: {$countBefore}\n";

    // Force cleanup by creating a fake backup (which will trigger cleanup)
    // We'll do this by directly calling the cleanup method if we add it as public
    // For now, let's just list the backups and show what would be cleaned

    $retentionDays = $config['backup']['retentionDays'] ?? 30;
    $maxBackups = $config['backup']['maxBackups'] ?? 10;
    $retentionTimestamp = time() - ($retentionDays * 24 * 60 * 60);

    $toDelete = [];

    // Check for backups exceeding max count
    if ($countBefore > $maxBackups) {
        $excess = array_slice($backupsBefore, $maxBackups);
        foreach ($excess as $backup) {
            $toDelete[] = $backup['filename'];
        }
    }

    // Check for backups older than retention period
    foreach ($backupsBefore as $backup) {
        $backupPath = $config['backupDir'] . '/' . $backup['filename'];
        if (file_exists($backupPath) && filemtime($backupPath) < $retentionTimestamp && !in_array($backup['filename'], $toDelete)) {
            $toDelete[] = $backup['filename'];
        }
    }

    // Delete expired backups
    $deletedCount = 0;
    foreach ($toDelete as $filename) {
        $backupPath = $config['backupDir'] . '/' . $filename;
        if (file_exists($backupPath) && unlink($backupPath)) {
            echo "Deleted backup: {$filename}\n";
            $deletedCount++;
        }
    }

    echo "Deleted {$deletedCount} old backups\n";

    // Show remaining backups
    $backupsAfter = $backupService->listBackups();
    $countAfter = count($backupsAfter);

    echo "Backups remaining: {$countAfter}\n";

    if ($countAfter > 0) {
        echo "\nRemaining backups:\n";
        foreach ($backupsAfter as $backup) {
            echo "  - {$backup['filename']} ({$backup['sizeFormatted']}) - {$backup['created']}\n";
        }
    }

    echo "\nCleanup completed successfully.\n";

    // Log to application log
    error_log("Backup cleanup: deleted {$deletedCount} old backups, {$countAfter} remaining");
} catch (Exception $e) {
    echo "Error during backup cleanup: " . $e->getMessage() . "\n";
    error_log("Backup cleanup error: " . $e->getMessage());
    exit(1);
}
