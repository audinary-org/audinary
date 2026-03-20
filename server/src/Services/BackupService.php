<?php

namespace App\Services;

use App\Database\Connection;
use Exception;
use Psr\Http\Message\UploadedFileInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Backup and Restore Service
 * Handles creating and restoring backups of the application
 */
class BackupService
{
    /** @var array<string, mixed> */
    private array $config;
    private string $backupDir;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->backupDir = $config['backupDir'];

        // Ensure backup directory exists
        if (!is_dir($this->backupDir) && !mkdir($this->backupDir, 0755, true)) {
            throw new RuntimeException("Failed to create backup directory: {$this->backupDir}");
        }
    }

    /**
     * List all available backups
     * @return array<int, array<string, mixed>>
     */
    public function listBackups(): array
    {
        $backups = [];
        $files = glob($this->backupDir . '/backup_*.tar.gz');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);
            $created = filemtime($file);

            if ($size === false || $created === false) {
                continue;
            }

            $size = (int)$size;

            // Extract timestamp from filename
            if (preg_match('/backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.tar\.gz/', $filename, $matches)) {
                $backups[] = [
                    'filename' => $filename,
                    'size' => $size,
                    'sizeFormatted' => $this->formatBytes($size),
                    'created' => date('Y-m-d H:i:s', $created),
                    'timestamp' => $matches[1]
                ];
            }
        }

        // Sort by creation time (newest first)
        usort($backups, fn(array $a, array $b): int => strcmp($b['timestamp'], $a['timestamp']));

        return $backups;
    }

    /**
     * Create a new backup
     * @return array<string, mixed>
     */
    public function createBackup(): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFilename = "backup_{$timestamp}.tar.gz";
        $backupPath = $this->backupDir . '/' . $backupFilename;
        $tempDir = $this->backupDir . '/temp_' . $timestamp;

        try {
            // Create temporary directory
            if (!mkdir($tempDir, 0755, true)) {
                throw new RuntimeException("Failed to create temporary directory");
            }

            $this->log("Starting backup creation: {$backupFilename}");

            // Copy database
            $this->copyDatabase($tempDir);

            // Copy directories
            $this->copyDirectories($tempDir);

            // Create compressed archive
            $this->createArchive($tempDir, $backupPath);

            // Clean up temporary directory
            $this->removeDirectory($tempDir);

            // Clean up old backups
            $this->cleanupOldBackups();

            $sizeRaw = filesize($backupPath);
            $size = $sizeRaw === false ? 0 : (int)$sizeRaw;

            $this->log("Backup created successfully: {$backupFilename} ({$this->formatBytes($size)})");

            return [
                'success' => true,
                'filename' => $backupFilename,
                'size' => $size,
                'sizeFormatted' => $this->formatBytes($size),
                'created' => date('Y-m-d H:i:s'),
                'message' => 'Backup created successfully'
            ];
        } catch (Exception $e) {
            // Clean up on error
            if (is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }

            $this->log("Backup failed: " . $e->getMessage(), 'error');

            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Restore from a backup
     * @return array<string, mixed>
     */
    public function restoreBackup(string $filename): array
    {
        $backupPath = $this->backupDir . '/' . $filename;
        $tempDir = $this->backupDir . '/restore_' . date('Y-m-d_H-i-s');

        if (!file_exists($backupPath)) {
            return [
                'success' => false,
                'message' => 'Backup file not found'
            ];
        }

        try {
            $this->log("Starting restore from backup: {$filename}");

            // Create temporary directory
            if (!mkdir($tempDir, 0755, true)) {
                throw new RuntimeException("Failed to create temporary directory");
            }

            // Extract backup
            $this->extractArchive($backupPath, $tempDir);

            // Close database connection before restore
            Connection::closeConnection();

            // Restore database
            $this->restoreDatabase($tempDir);

            // Restore directories
            $this->restoreDirectories($tempDir);

            // Clean up temporary directory
            $this->removeDirectory($tempDir);

            // Reconnect to database

            $this->log("Restore completed successfully from: {$filename}");

            return [
                'success' => true,
                'message' => 'Restore completed successfully'
            ];
        } catch (Exception $e) {
            // Clean up on error
            if (is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }

            // Log the error without trying to reconnect
            $this->log("Restore failed: " . $e->getMessage(), 'error');

            return [
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a backup
     * @return array<string, mixed>
     */
    public function deleteBackup(string $filename): array
    {
        $backupPath = $this->backupDir . '/' . $filename;

        // Validate filename format before checking file existence
        if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/', $filename) !== 1) {
            return [
                'success' => false,
                'message' => 'Invalid backup filename format'
            ];
        }

        // Prevent path traversal
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return [
                'success' => false,
                'message' => 'Invalid filename - path traversal detected'
            ];
        }

        if (!file_exists($backupPath)) {
            return [
                'success' => false,
                'message' => 'Backup file not found'
            ];
        }

        if (unlink($backupPath)) {
            $this->log("Backup deleted: {$filename}");
            return [
                'success' => true,
                'message' => 'Backup deleted successfully'
            ];
        }
        return [
            'success' => false,
            'message' => 'Failed to delete backup file'
        ];
    }

    /**
     * Upload a backup file
     * @return array<string, mixed>
     */
    public function uploadBackup(UploadedFileInterface $uploadedFile, string $originalFilename): array
    {
        try {
            // Validate original filename format
            if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/', $originalFilename) !== 1) {
                return [
                    'success' => false,
                    'message' => 'Invalid backup filename format'
                ];
            }

            // Prevent path traversal
            if (strpos($originalFilename, '..') !== false || strpos($originalFilename, '/') !== false || strpos($originalFilename, '\\') !== false) {
                return [
                    'success' => false,
                    'message' => 'Invalid filename - path traversal detected'
                ];
            }

            // Generate new filename with current timestamp to avoid conflicts
            $timestamp = date('Y-m-d_H-i-s');
            $newFilename = "backup_{$timestamp}.tar.gz";
            $backupPath = $this->backupDir . '/' . $newFilename;

            // Ensure the new filename doesn't exist (very unlikely but safe)
            $counter = 1;
            while (file_exists($backupPath)) {
                $newFilename = "backup_{$timestamp}_{$counter}.tar.gz";
                $backupPath = $this->backupDir . '/' . $newFilename;
                $counter++;
            }

            // Move uploaded file to backup directory
            $uploadedFile->moveTo($backupPath);

            // Verify the uploaded file
            if (!file_exists($backupPath)) {
                return [
                    'success' => false,
                    'message' => 'Failed to save uploaded backup file'
                ];
            }

            // Validate that it's a valid tar.gz file by trying to list its contents
            $command = sprintf('tar -tzf %s >/dev/null 2>&1', escapeshellarg($backupPath));
            $returnVar = 0;
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                // Remove invalid file
                unlink($backupPath);
                return [
                    'success' => false,
                    'message' => 'Uploaded file is not a valid backup archive'
                ];
            }

            $sizeRaw = filesize($backupPath);
            $size = ($sizeRaw === false) ? 0 : (int)$sizeRaw;

            // Clean up old backups after successful upload
            $this->cleanupOldBackups();

            $sizeFormatted = ($size > 0) ? $this->formatBytes($size) : '0 B';
            $this->log("Backup uploaded successfully: {$originalFilename} -> {$newFilename} ({$sizeFormatted})");

            return [
                'success' => true,
                'filename' => $newFilename,
                'originalFilename' => $originalFilename,
                'size' => $size,
                'sizeFormatted' => $sizeFormatted,
                'created' => date('Y-m-d H:i:s'),
                'message' => "Backup uploaded successfully as {$newFilename}"
            ];
        } catch (Exception $e) {
            // Clean up on error
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }

            $this->log("Backup upload failed: " . $e->getMessage(), 'error');

            return [
                'success' => false,
                'message' => 'Failed to upload backup: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Copy database to backup directory
     */
    private function copyDatabase(string $tempDir): void
    {
        $params = $this->getDbParams();
        $backupSqlPath = $tempDir . '/database.sql';

        // Close any open connections before dumping
        Connection::closeConnection();

        $envPrefix = $params['password'] !== ''
            ? 'PGPASSWORD=' . escapeshellarg($params['password']) . ' '
            : '';

        $command = $envPrefix . sprintf(
            'pg_dump --clean --if-exists --no-owner --no-privileges --format=plain -h %s -p %s -U %s -d %s -f %s',
            escapeshellarg($params['host']),
            escapeshellarg((string) $params['port']),
            escapeshellarg($params['user']),
            escapeshellarg($params['name']),
            escapeshellarg($backupSqlPath)
        );

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0 || !file_exists($backupSqlPath)) {
            throw new RuntimeException('Failed to dump PostgreSQL database: ' . implode("\n", $output));
        }

        $this->log("Database dumped to SQL file");
    }

    /**
     * Copy directories to backup
     */
    private function copyDirectories(string $tempDir): void
    {
        $directories = [
            'config' => $this->config['configDir'],
            'covers' => $this->config['coverDir'],
            'artists' => $this->config['artistImagesDir'],
            'playlists' => $this->config['playlistCoversDir'],
            'profiles' => $this->config['profileDir']
        ];

        foreach ($directories as $name => $sourcePath) {
            if (is_dir($sourcePath)) {
                $targetPath = $tempDir . '/' . $name;
                $this->copyDirectoryRecursive($sourcePath, $targetPath);
                $this->log("Directory copied: {$name}");
            } else {
                $this->log("Directory not found, skipping: {$sourcePath}", 'warning');
            }
        }
    }

    /**
     * Recursively copy directory
     */
    private function copyDirectoryRecursive(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $targetPath = $destination . '/' . $relativePath;

            // Skip excluded patterns
            if ($this->shouldExcludeFile($item->getFilename())) {
                continue;
            }

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } elseif (!copy($item->getPathname(), $targetPath)) {
                throw new RuntimeException("Failed to copy file: " . $item->getPathname());
            }
        }
    }

    /**
     * Check if file should be excluded from backup
     */
    private function shouldExcludeFile(string $filename): bool
    {
        $excludePatterns = $this->config['backup']['excludePatterns'] ?? [];

        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create compressed archive
     */
    private function createArchive(string $sourceDir, string $archivePath): void
    {
        $this->log("Creating archive: " . basename($archivePath));

        // Use tar command for better compression and performance
        $command = sprintf(
            'cd %s && tar -czf %s .',
            escapeshellarg($sourceDir),
            escapeshellarg($archivePath)
        );

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new RuntimeException("Failed to create archive. Command output: " . implode("\n", $output));
        }
    }

    /**
     * Extract archive
     */
    private function extractArchive(string $archivePath, string $targetDir): void
    {
        $this->log("Extracting archive: " . basename($archivePath));

        $command = sprintf(
            'tar -xzf %s -C %s',
            escapeshellarg($archivePath),
            escapeshellarg($targetDir)
        );

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new RuntimeException("Failed to extract archive. Command output: " . implode("\n", $output));
        }
    }

    /**
     * Restore database
     */
    private function restoreDatabase(string $tempDir): void
    {
        $params = $this->getDbParams();
        $backupSqlPath = $tempDir . '/database.sql';

        if (!file_exists($backupSqlPath)) {
            // Legacy SQLite backups are not supported in PostgreSQL mode
            throw new RuntimeException('Database SQL backup not found in archive');
        }

        Connection::closeConnection();

        $envPrefix = $params['password'] !== ''
            ? 'PGPASSWORD=' . escapeshellarg($params['password']) . ' '
            : '';

        $command = $envPrefix . sprintf(
            'psql -h %s -p %s -U %s -d %s -v ON_ERROR_STOP=1 -f %s',
            escapeshellarg($params['host']),
            escapeshellarg((string) $params['port']),
            escapeshellarg($params['user']),
            escapeshellarg($params['name']),
            escapeshellarg($backupSqlPath)
        );

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new RuntimeException('Failed to restore PostgreSQL database: ' . implode("\n", $output));
        }

        $this->log("Database restored from SQL backup");
    }

    /**
     * Restore directories
     */
    private function restoreDirectories(string $tempDir): void
    {
        $directories = [
            'config' => $this->config['configDir'],
            'covers' => $this->config['coverDir'],
            'artists' => $this->config['artistImagesDir'],
            'playlists' => $this->config['playlistCoversDir'],
            'profiles' => $this->config['profileDir']
        ];

        foreach ($directories as $name => $targetPath) {
            $sourcePath = $tempDir . '/' . $name;

            if (is_dir($sourcePath)) {
                // Remove existing directory
                if (is_dir($targetPath)) {
                    $this->removeDirectory($targetPath);
                }

                // Restore from backup
                $this->copyDirectoryRecursive($sourcePath, $targetPath);
                $this->log("Directory restored: {$name}");
            } else {
                $this->log("Directory not found in backup, skipping: {$name}", 'warning');
            }
        }
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($path);
    }

    /**
     * Clean up old backups based on retention policy
     */
    private function cleanupOldBackups(): void
    {
        $backups = $this->listBackups();
        $maxBackups = $this->config['backup']['maxBackups'] ?? 10;
        $retentionDays = $this->config['backup']['retentionDays'] ?? 30;
        $retentionTimestamp = time() - ($retentionDays * 24 * 60 * 60);

        $deletedCount = 0;

        // Delete backups exceeding max count
        if (count($backups) > $maxBackups) {
            $excessBackups = array_slice($backups, $maxBackups);
            foreach ($excessBackups as $backup) {
                $backupPath = $this->backupDir . '/' . $backup['filename'];
                if (unlink($backupPath)) {
                    $deletedCount++;
                }
            }
        }

        // Delete backups older than retention period
        foreach ($backups as $backup) {
            $backupPath = $this->backupDir . '/' . $backup['filename'];
            if (filemtime($backupPath) < $retentionTimestamp && unlink($backupPath)) {
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->log("Cleaned up {$deletedCount} old backups");
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Log message
     */
    private function log(string $message, string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}";

        error_log("BackupService: " . $logMessage);

        // Also log to file if log directory exists
        $logDir = $this->config['logDir'] ?? null;
        if ($logDir && is_dir($logDir)) {
            $logFile = $logDir . '/backup.log';
            file_put_contents($logFile, $logMessage . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Resolve database connection parameters with env overrides
     *
     * @return array{driver:string,host:string,port:int,name:string,user:string,password:string}
     */
    private function getDbParams(): array
    {
        $driver = getenv('DB_DRIVER') ?: ($this->config['dbDriver'] ?? 'pgsql');
        if ($driver !== 'pgsql') {
            throw new RuntimeException("Unsupported DB driver: {$driver}. Only PostgreSQL is supported for backups.");
        }

        $host = getenv('DB_HOST') ?: getenv('PGHOST') ?: ($this->config['dbHost'] ?? 'postgres');
        $port = (int) (getenv('DB_PORT') ?: getenv('PGPORT') ?: ($this->config['dbPort'] ?? 5432));
        $name = getenv('DB_NAME') ?: getenv('PGDATABASE') ?: ($this->config['dbName'] ?? 'audinary');
        $user = getenv('DB_USER') ?: getenv('PGUSER') ?: ($this->config['dbUser'] ?? 'audinary');
        $password = getenv('DB_PASSWORD') ?: getenv('PGPASSWORD') ?: ($this->config['dbPassword'] ?? '');

        return [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'password' => $password,
        ];
    }
}
