<?php

/**
 * Cleanup script for expired rate limit entries
 *
 * This script should be run periodically (e.g., via cron) to clean up
 * expired rate limit entries from the database.
 *
 * Recommended cron schedule: every 15 minutes (cron: "star-slash-15 * * * *")
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/configHelper.php';

use App\Middleware\RateLimitMiddleware;

echo "[" . date('Y-m-d H:i:s') . "] Starting rate limits cleanup...\n";

try {
    RateLimitMiddleware::cleanup();
    echo "[" . date('Y-m-d H:i:s') . "] Rate limits cleanup completed successfully\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
