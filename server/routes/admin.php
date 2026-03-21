<?php

// routes/admin.php - Admin-only routes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\AdminController;
use App\Middleware\RateLimitMiddleware;
use Slim\App;
use Psr\Container\ContainerInterface;

/** @var App<ContainerInterface> $app */

// Note: Admin routes require JWT middleware with admin check
// The middleware sets 'auth_token' attribute with AuthTokenInterface

$adminController = new AdminController();

// =============================================================================
// User Management Routes
// =============================================================================

// List all users (admin only)
$app->get('/api/admin/users', [$adminController, 'listUsers']);
$app->get('/api/users', [$adminController, 'listUsers']); // Alias

// Get specific user (admin only)
$app->get('/api/admin/user/load/{id}', [$adminController, 'getUser']);
$app->get('/api/users/{id}', [$adminController, 'getUser']); // Alias

// Create or update user (admin only)
$app->post('/api/admin/user/save', [$adminController, 'saveUser']);
$app->post('/api/users', [$adminController, 'createUser']);
$app->put('/api/users/{id}', [$adminController, 'updateUser']);

// Delete user (admin only)
$app->delete('/api/admin/user/{id}', [$adminController, 'deleteUser']);
$app->delete('/api/users/{id}', [$adminController, 'deleteUser']); // Alias

// =============================================================================
// Global Settings Routes
// =============================================================================

$app->get('/api/settings', [$adminController, 'getGlobalSettings']);
$app->put('/api/settings', [$adminController, 'updateGlobalSettings']);

// =============================================================================
// Music Scanning Routes
// =============================================================================

$app->get('/api/scan-status', [$adminController, 'getScanStatus']);
$app->post('/api/scan-music', [$adminController, 'triggerMusicScan'])
    ->add(new RateLimitMiddleware(10, 60, 'admin-scan')); // 10 scans per minute (prevent DoS)
$app->get('/api/scan-logs', [$adminController, 'getScanLogs']);

// =============================================================================
// Dashboard Statistics Routes
// =============================================================================

$app->get('/api/admin/stats', [$adminController, 'getDashboardStats']);
$app->get('/api/admin/top-genres', [$adminController, 'getTopGenres']);
$app->get('/api/admin/recent-activity', [$adminController, 'getRecentActivity']);
$app->get('/api/admin/system-info', [$adminController, 'getSystemInfo']);
$app->get('/api/admin/listening-stats', [$adminController, 'getListeningStats']);
$app->get('/api/admin/library-stats', [$adminController, 'getLibraryStats']);
$app->get('/api/admin/password-reset-stats', [$adminController, 'getPasswordResetStats']);

// =============================================================================
// Backup and Restore Routes
// =============================================================================

$app->get('/api/admin/backups', [$adminController, 'listBackups']);
$app->post('/api/admin/backup', [$adminController, 'createBackup']);
$app->post('/api/admin/restore', [$adminController, 'restoreBackup']);
$app->delete('/api/admin/backup/{filename}', [$adminController, 'deleteBackup']);
$app->get('/api/admin/backup/download/{filename}', [$adminController, 'downloadBackup']);
$app->post('/api/admin/backup/upload', [$adminController, 'uploadBackup']);

// =============================================================================
// Stats Sharing Routes (External analytics)
// =============================================================================

$app->get('/api/admin/stats-sharing', [$adminController, 'getStatsSharingConfig']);
$app->put('/api/admin/stats-sharing', [$adminController, 'updateStatsSharingConfig']);
$app->get('/api/admin/stats-sharing/preview', [$adminController, 'previewStats']);
$app->post('/api/admin/stats-sharing/send', [$adminController, 'sendStats']);

// =============================================================================
// Smart Playlist Management Routes
// =============================================================================

$app->get('/api/admin/smart-playlists', [$adminController, 'getSmartPlaylists']);
$app->post('/api/admin/smart-playlists', [$adminController, 'createSmartPlaylist']);
$app->post('/api/admin/smart-playlists/preview', [$adminController, 'previewSmartPlaylist']);
$app->put('/api/admin/smart-playlists/{id}', [$adminController, 'updateSmartPlaylist']);
$app->delete('/api/admin/smart-playlists/{id}', [$adminController, 'deleteSmartPlaylist']);
