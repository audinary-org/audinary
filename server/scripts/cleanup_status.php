#!/usr/bin/env php
<?php

/**
 * Cleanup script to fix hanging scan status records
 */

declare(strict_types=1);

// Bootstrap
require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;

try {
    echo "Cleaning up hanging scan status records...\n";

    $db = Connection::getPDO();

    // Get all running scan status records
    $stmt = $db->query("SELECT id, process_id, updated_at FROM scan_status WHERE status = 'running'");
    if ($stmt === false) {
        throw new RuntimeException("Failed to query scan status");
    }
    $runningScans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cleanedUp = 0;

    foreach ($runningScans as $scan) {
        $processId = $scan['process_id'];
        $statusId = $scan['id'];
        $updatedAt = $scan['updated_at'];

        // Check if process is still running
        $isRunning = false;
        if ($processId) {
            // In Docker environments, ps might not work correctly across containers
            // Instead, check if the status was updated recently (within last 2 minutes)
            $updateTime = strtotime($updatedAt);
            $isRecent = (time() - $updateTime) < 120; // 2 minutes

            if ($isRecent) {
                // If updated recently, assume process is running
                $isRunning = true;
            } else {
                // Try ps command as fallback (works in non-Docker environments)
                $output = [];
                $result = exec("ps -p $processId 2>/dev/null", $output);
                $isRunning = $output !== [] && count($output) > 1; // More than just header line
            }
        }

        // Also check if scan is older than 10 minutes (timeout) - increased from 5 minutes
        $updateTime = strtotime($updatedAt);
        $isStale = (time() - $updateTime) > 600; // 10 minutes

        if (!$isRunning || $isStale) {
            $reason = $isRunning ? "Scan timed out (older than 10 minutes)" : "Process $processId no longer exists";
            echo "Cleaning up scan record $statusId: $reason\n";

            // Update the status to error
            $updateStmt = $db->prepare("
                UPDATE scan_status 
                SET status = 'error', 
                    error_message = :error_message, 
                    end_time = :end_time
                WHERE id = :id
            ");
            $updateStmt->execute([
                'error_message' => $reason,
                'end_time' => time(),
                'id' => $statusId
            ]);

            $cleanedUp++;
        }
    }

    echo "Cleanup complete. Fixed $cleanedUp hanging scan records.\n";
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}
