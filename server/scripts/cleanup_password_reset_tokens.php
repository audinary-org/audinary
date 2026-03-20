<?php

/**
 * Password Reset Token Cleanup Script
 * This script should be run periodically (e.g., via cron) to clean up expired and used tokens
 *
 * Usage: php cleanup_password_reset_tokens.php
 * Recommended cron: Run every 6 hours
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/configHelper.php';

use App\Database\Connection;

// Load configuration
$config = loadConfig();

try {
    $pdo = Connection::getPDO();

    // Clean up expired and used password reset tokens
    $stmt = $pdo->prepare("
        DELETE FROM password_reset_tokens 
        WHERE expires_at < NOW() OR used_at IS NOT NULL
    ");
    $stmt->execute();
    $deletedTokens = $stmt->rowCount();

    // Clean up old rate limit records (older than 24 hours)
    $stmt = $pdo->prepare("
        DELETE FROM password_reset_rate_limit 
        WHERE reset_hour < (NOW() - INTERVAL '24 hours')
    ");
    $stmt->execute();
    $deletedRateLimits = $stmt->rowCount();

    // Get current statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as active_tokens,
            MIN(expires_at) as earliest_expiry,
            MAX(expires_at) as latest_expiry
        FROM password_reset_tokens 
        WHERE expires_at > NOW() AND used_at IS NULL
    ");
    if ($stmt === false) {
        throw new RuntimeException("Failed to query statistics");
    }
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $timestamp = date('Y-m-d H:i:s');

    echo "Password Reset Token Cleanup - {$timestamp}\n";
    echo "==============================================\n";
    echo "Deleted expired/used tokens: {$deletedTokens}\n";
    echo "Deleted old rate limit records: {$deletedRateLimits}\n";
    echo "Active tokens remaining: {$stats['active_tokens']}\n";

    if ($stats['active_tokens'] > 0) {
        echo "Earliest token expires: {$stats['earliest_expiry']}\n";
        echo "Latest token expires: {$stats['latest_expiry']}\n";
    }

    echo "\nCleanup completed successfully.\n";

    // Log cleanup to application log
    error_log("Password reset cleanup: deleted {$deletedTokens} tokens, {$deletedRateLimits} rate limit records");
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    error_log("Password reset cleanup error: " . $e->getMessage());
    exit(1);
}
