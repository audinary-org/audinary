<?php

namespace App\Services;

use PDO;
use Exception;
use App\Services\EmailService;

/**
 * Service for handling password reset functionality
 */
class PasswordResetService
{
    private PDO $db;
    private EmailService $emailService;
    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(PDO $database, EmailService $emailService, array $config)
    {
        $this->db = $database;
        $this->emailService = $emailService;
        $this->config = $config;
    }

    /**
     * Request password reset
     * Always returns success to prevent user enumeration
     */
    /** @return array<string, mixed> */
    public function requestPasswordReset(string $usernameOrEmail, string $clientIp, string $baseUrl): array
    {
        try {
            // Check rate limiting first
            if (!$this->checkRateLimit($clientIp)) {
                return [
                    'success' => false,
                    'message' => 'Too many password reset requests. Please try again later.',
                    'retry_after' => 3600 // 1 hour
                ];
            }

            // Clean up old tokens
            $this->cleanupExpiredTokens();

            // Find user by username or email
            $user = $this->findUserByUsernameOrEmail($usernameOrEmail);

            if ($user && !empty($user['email'])) {
                // Generate and store reset token
                $token = $this->generateResetToken();
                // Use configured validity minutes (default 60)
                $validityMinutes = $this->config['passwordReset']['tokenValidityMinutes'] ?? 60;

                $this->storeResetTokenWithMySQLTime($user['user_id'], $token, $validityMinutes, $clientIp);

                // Generate reset link
                $resetLink = $baseUrl . '/reset-password?token=' . $token;

                // Send email (no verbose logging here)
                $this->emailService->sendPasswordResetEmail(
                    $user['email'],
                    $user['display_name'] ?: $user['username'],
                    $resetLink,
                    $user['username']
                );
            }

            // Always return success to prevent user enumeration
            return [
                'success' => true,
                'message' => 'If an account with this username or email exists, you will receive a password reset email.'
            ];
        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ];
        }
    }

    /**
     * Validate reset token
     */
    /** @return array<string, mixed> */
    public function validateResetToken(string $token): array
    {
        try {
            $stmt = $this->db->prepare("SELECT prt.*, u.username, u.display_name 
                FROM password_reset_tokens prt
                JOIN users u ON prt.user_id = u.user_id
                WHERE prt.token = ? 
                AND prt.expires_at > NOW() 
                AND prt.used_at IS NULL
            ");

            $stmt->execute([$token]);
            $resetToken = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resetToken) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ];
            }

            return [
                'success' => true,
                'user_id' => $resetToken['user_id'],
                'username' => $resetToken['username'],
                'display_name' => $resetToken['display_name'],
                'expires_at' => $resetToken['expires_at']
            ];
        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'An error occurred while validating the token'
            ];
        }
    }

    /**
     * Reset password using token
     */
    /** @return array<string, mixed> */
    public function resetPassword(string $token, string $newPassword): array
    {
        try {
            $this->db->beginTransaction();

            // Validate token
            $tokenData = $this->validateResetToken($token);
            if (!$tokenData['success']) {
                $this->db->rollBack();
                return $tokenData;
            }


            // Hash new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update user password
            $stmt = $this->db->prepare("UPDATE users 
                SET password_hash = ?, updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([$passwordHash, $tokenData['user_id']]);

            // Mark token as used
            $stmt = $this->db->prepare("UPDATE password_reset_tokens 
                SET used_at = NOW() 
                WHERE token = ?
            ");
            $stmt->execute([$token]);

            // Invalidate all existing auth tokens for this user
            $stmt = $this->db->prepare("UPDATE users 
                SET auth_token_hash = NULL 
                WHERE user_id = ?
            ");
            $stmt->execute([$tokenData['user_id']]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Password has been reset successfully. Please log in with your new password.'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'An error occurred while resetting the password'
            ];
        }
    }

    /**
     * Check rate limiting for password reset requests
     */
    private function checkRateLimit(string $clientIp): bool
    {
        try {
            $currentHour = date('Y-m-d H:00:00');
            $maxRequests = $this->config['passwordReset']['maxRequestsPerHour'];

            // Clean up old rate limit records
            $stmt = $this->db->prepare("DELETE FROM password_reset_rate_limit 
                WHERE reset_hour < (NOW() - INTERVAL '2 hours')
            ");
            $stmt->execute();

            // Check current rate limit
            $stmt = $this->db->prepare("SELECT request_count 
                FROM password_reset_rate_limit 
                WHERE ip_address = ? AND reset_hour = ?
            ");
            $stmt->execute([$clientIp, $currentHour]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($record) {
                if ($record['request_count'] >= $maxRequests) {
                    return false;
                }

                // Update count
                $stmt = $this->db->prepare("UPDATE password_reset_rate_limit 
                    SET request_count = request_count + 1, last_request_at = NOW() 
                    WHERE ip_address = ? AND reset_hour = ?
                ");
                $stmt->execute([$clientIp, $currentHour]);
            } else {
                // Create new record
                $stmt = $this->db->prepare("INSERT INTO password_reset_rate_limit (ip_address, request_count, reset_hour) 
                    VALUES (?, 1, ?)
                ");
                $stmt->execute([$clientIp, $currentHour]);
            }

            return true;
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find user by username or email
     */
    /** @return array<string, mixed>|null */
    private function findUserByUsernameOrEmail(string $usernameOrEmail): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT user_id, username, display_name, email 
                FROM users 
                WHERE (username = ? OR email = ?) 
            ");
            $stmt->execute([$usernameOrEmail, $usernameOrEmail]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            error_log("User lookup error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate secure reset token
     */
    private function generateResetToken(): string
    {
        return bin2hex(random_bytes(32)) . '_' . time();
    }

    /**
     * Store reset token in database using MySQL time calculation
     */
    private function storeResetTokenWithMySQLTime(string $userId, string $token, int $validityMinutes, string $clientIp): void
    {
        $stmt = $this->db->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at, ip_address) 
            VALUES (?, ?, NOW() + (? || ' minutes')::interval, ?)
        ");
        $stmt->execute([$userId, $token, $validityMinutes, $clientIp]);
    }

    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens(): int
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM password_reset_tokens 
                WHERE expires_at < NOW() OR used_at IS NOT NULL
            ");
            $stmt->execute();

            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Token cleanup error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get reset token statistics (for admin)
     */
    /** @return array<string, mixed> */
    public function getTokenStats(): array
    {
        try {
            $stats = [];

            // Active tokens
            $stmt = $this->db->query("SELECT COUNT(*) as count
                FROM password_reset_tokens
                WHERE expires_at > NOW() AND used_at IS NULL
            ");
            $stats['active_tokens'] = $stmt !== false ? $stmt->fetchColumn() : 0;

            // Expired tokens
            $stmt = $this->db->query("SELECT COUNT(*) as count
                FROM password_reset_tokens
                WHERE expires_at <= NOW() AND used_at IS NULL
            ");
            $stats['expired_tokens'] = $stmt !== false ? $stmt->fetchColumn() : 0;

            // Used tokens
            $stmt = $this->db->query("SELECT COUNT(*) as count
                FROM password_reset_tokens
                WHERE used_at IS NOT NULL
            ");
            $stats['used_tokens'] = $stmt !== false ? $stmt->fetchColumn() : 0;

            // Today's requests
            $stmt = $this->db->query("SELECT COUNT(*) as count
                FROM password_reset_tokens
                WHERE DATE(created_at) = CURRENT_DATE
            ");
            $stats['todays_requests'] = $stmt !== false ? $stmt->fetchColumn() : 0;

            return $stats;
        } catch (Exception $e) {
            error_log("Token stats error: " . $e->getMessage());
            return [];
        }
    }
}
