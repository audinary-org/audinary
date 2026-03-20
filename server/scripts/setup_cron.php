<?php

/**
 * Cron Setup Helper Script
 * This script helps users set up automated backup tasks
 *
 * Usage: php setup_cron.php [--install|--uninstall|--status]
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/configHelper.php';

function showUsage(): void
{
    echo "Backup Cron Setup Helper\n";
    echo "========================\n\n";
    echo "Usage: php setup_cron.php [option]\n\n";
    echo "Options:\n";
    echo "  --install    Install cron jobs for automated backups\n";
    echo "  --uninstall  Remove backup cron jobs\n";
    echo "  --status     Show current cron status\n";
    echo "  --help       Show this help message\n\n";
}

/**
 * @return array<string>
 */
function getCurrentCronJobs(): array
{
    exec('crontab -l 2>/dev/null', $output, $returnVar);
    return $returnVar === 0 ? $output : [];
}

function showCronStatus(): void
{
    echo "Current Cron Status\n";
    echo "===================\n\n";

    $currentJobs = getCurrentCronJobs();
    $backupJobs = array_filter($currentJobs, fn($job): bool => strpos($job, 'create_backup.php') !== false || strpos($job, 'cleanup_backups.php') !== false);

    if ($backupJobs === []) {
        echo "❌ No backup cron jobs found\n";
        echo "Run with --install to set up automated backups\n\n";
    } else {
        echo "✅ Found backup cron jobs:\n";
        foreach ($backupJobs as $job) {
            if (strpos($job, '#') === 0) {
                echo "   (disabled) " . substr($job, 1) . "\n";
            } else {
                echo "   " . $job . "\n";
            }
        }
        echo "\n";
    }

    // Show backup directory status
    try {
        $config = loadConfig();
        $backupDir = $config['backupDir'];

        if (!is_dir($backupDir)) {
            echo "⚠️  Backup directory does not exist: {$backupDir}\n";
        } else {
            $backups = glob($backupDir . '/backup_*.tar.gz');
            if ($backups === false) {
                $backups = [];
            }
            $backupCount = count($backups);
            echo "📁 Backup directory: {$backupDir}\n";
            echo "📦 Current backups: {$backupCount}\n";

            if ($backupCount > 0) {
                // Show most recent backup
                $latestBackup = end($backups);
                $latestTime = filemtime($latestBackup);
                if ($latestTime !== false) {
                    echo "🕒 Latest backup: " . basename($latestBackup) . " (" . date('Y-m-d H:i:s', $latestTime) . ")\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "❌ Error checking backup directory: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

function installCronJobs(): void
{
    echo "Installing Backup Cron Jobs\n";
    echo "===========================\n\n";

    $scriptDir = __DIR__;
    $phpBinary = exec('which php') ?: '/usr/bin/php';

    echo "PHP Binary: {$phpBinary}\n";
    echo "Script Directory: {$scriptDir}\n\n";

    // Define cron jobs
    $cronJobs = [
        "# Audinary Backup Jobs - Created by setup_cron.php",
        "# Create daily backup at 1:00 AM",
        "0 1 * * * {$phpBinary} {$scriptDir}/create_backup.php >> /var/log/audinary-backup.log 2>&1",
        "# Cleanup old backups daily at 2:00 AM",
        "0 2 * * * {$phpBinary} {$scriptDir}/cleanup_backups.php >> /var/log/audinary-backup.log 2>&1",
        ""
    ];

    // Get current crontab
    $currentJobs = getCurrentCronJobs();

    // Remove existing backup jobs
    $filteredJobs = array_filter($currentJobs, fn($job): bool => strpos($job, 'create_backup.php') === false &&
        strpos($job, 'cleanup_backups.php') === false &&
        strpos($job, 'Audinary Backup Jobs') === false);

    // Add new jobs
    $newCronContent = array_merge($filteredJobs, $cronJobs);

    // Create temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'cron');
    file_put_contents($tempFile, implode("\n", $newCronContent));

    // Install new crontab
    exec("crontab {$tempFile}", $output, $returnVar);
    unlink($tempFile);

    if ($returnVar === 0) {
        echo "✅ Cron jobs installed successfully!\n\n";
        echo "Scheduled tasks:\n";
        echo "  - Daily backup creation: 1:00 AM\n";
        echo "  - Daily cleanup: 2:00 AM\n\n";
        echo "Log file: /var/log/audinary-backup.log\n";
        echo "Monitor: tail -f /var/log/audinary-backup.log\n\n";

        // Create log file with proper permissions
        $logFile = '/var/log/audinary-backup.log';
        if (!file_exists($logFile)) {
            touch($logFile);
            chmod($logFile, 0644);
            echo "✅ Created log file: {$logFile}\n";
        }
    } else {
        echo "❌ Failed to install cron jobs\n";
        echo "Error output: " . implode("\n", $output) . "\n";
        exit(1);
    }
}

function uninstallCronJobs(): void
{
    echo "Uninstalling Backup Cron Jobs\n";
    echo "=============================\n\n";

    // Get current crontab
    $currentJobs = getCurrentCronJobs();

    // Remove backup-related jobs
    $filteredJobs = array_filter($currentJobs, fn($job): bool => strpos($job, 'create_backup.php') === false &&
        strpos($job, 'cleanup_backups.php') === false &&
        strpos($job, 'Audinary Backup Jobs') === false);

    if (count($filteredJobs) === count($currentJobs)) {
        echo "ℹ️  No backup cron jobs found to remove\n\n";
        return;
    }

    // Create temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'cron');
    file_put_contents($tempFile, implode("\n", $filteredJobs));

    // Install new crontab
    exec("crontab {$tempFile}", $output, $returnVar);
    unlink($tempFile);

    if ($returnVar === 0) {
        echo "✅ Backup cron jobs removed successfully!\n\n";

        $removedCount = count($currentJobs) - count($filteredJobs);
        echo "Removed {$removedCount} backup-related cron job(s)\n\n";
    } else {
        echo "❌ Failed to remove cron jobs\n";
        echo "Error output: " . implode("\n", $output) . "\n";
        exit(1);
    }
}

// Parse command line arguments
$option = $argv[1] ?? '--status';

switch ($option) {
    case '--install':
        installCronJobs();
        break;

    case '--uninstall':
        uninstallCronJobs();
        break;

    case '--status':
        showCronStatus();
        break;

    case '--help':
        showUsage();
        break;

    default:
        echo "Unknown option: {$option}\n\n";
        showUsage();
        exit(1);
}
