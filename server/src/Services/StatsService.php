<?php

namespace App\Services;

use Exception;
use PDO;
use Ramsey\Uuid\Uuid;

class StatsService
{
    private PDO $pdo;
    private GlobalSettingsService $globalSettingsService;

    private const STATS_API_URL = 'https://audinary.org/api/stats.php';
    private const RATE_LIMIT_HOURS = 24;

    public function __construct(PDO $pdo, GlobalSettingsService $globalSettingsService)
    {
        $this->pdo = $pdo;
        $this->globalSettingsService = $globalSettingsService;
    }

    /**
     * Check if stats sharing is enabled
     */
    public function isStatsEnabled(): bool
    {
        return $this->globalSettingsService->get('stats_enabled', false);
    }

    /**
     * Get stats sharing configuration
     * @return array<string, mixed>
     */
    public function getStatsConfig(): array
    {
        return [
            'enabled' => $this->globalSettingsService->get('stats_enabled', false),
            'instanceId' => $this->globalSettingsService->get('stats_instance_id', ''),
            'lastSent' => $this->globalSettingsService->get('stats_last_sent', null),
        ];
    }

    /**
     * Set stats sharing configuration
     * @param array<string, mixed> $config
     */
    public function setStatsConfig(array $config): bool
    {
        $success = true;

        if (isset($config['enabled'])) {
            // If enabling stats for the first time, generate instance ID automatically
            if ($config['enabled'] && empty($this->globalSettingsService->get('stats_instance_id'))) {
                $instanceId = $this->generateInstanceId();
                $instanceIdSuccess = $this->globalSettingsService->set('stats_instance_id', $instanceId);
                if (!$instanceIdSuccess) {
                    $success = false;
                }
            }

            $enabledSuccess = $this->globalSettingsService->set('stats_enabled', $config['enabled']);
            if (!$enabledSuccess) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Generate a secure, anonymous instance ID
     */
    private function generateInstanceId(): string
    {
        // Use UUID4 which is completely random and cannot be reverse-engineered
        $uuid = Uuid::uuid4()->toString();

        // Make it shorter and more anonymous by using only parts of the UUID
        // This creates a 32-character string that's still unique but harder to trace back
        return 'aud-' . substr(str_replace('-', '', $uuid), 0, 28);
    }

    /**
     * Check if we can send stats (rate limit check)
     */
    public function canSendStats(): bool
    {
        if (!$this->isStatsEnabled()) {
            return false;
        }

        $instanceId = $this->globalSettingsService->get('stats_instance_id', '');
        if (empty($instanceId)) {
            // If stats are enabled but no instance ID exists, generate one
            $instanceId = $this->generateInstanceId();
            $this->globalSettingsService->set('stats_instance_id', $instanceId);
        }

        $lastSent = $this->globalSettingsService->get('stats_last_sent', null);
        if ($lastSent === null) {
            return true;
        }

        $lastSentTime = is_numeric($lastSent) ? (int)$lastSent : strtotime($lastSent);
        $timeSinceLastSent = time() - $lastSentTime;

        return $timeSinceLastSent >= (self::RATE_LIMIT_HOURS * 3600);
    }

    /**
     * Collect stats from the database
     * @return array<string, mixed>
     */
    public function collectStats(): array
    {
        $stats = [];

        try {
            // Get or generate instance ID
            $instanceId = $this->globalSettingsService->get('stats_instance_id', '');
            if (empty($instanceId)) {
                $instanceId = $this->generateInstanceId();
                $this->globalSettingsService->set('stats_instance_id', $instanceId);
            }
            $stats['instance_id'] = $instanceId;

            // Count total users
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users");
            if ($stmt === false) {
                throw new \RuntimeException("Failed to query users count");
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result === false || !is_array($result)) {
                throw new \RuntimeException("Failed to fetch users count");
            }
            $stats['total_users'] = min((int)$result['count'], 1000000); // Cap at 1M

            // Count total songs
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM songs");
            if ($stmt === false) {
                throw new \RuntimeException("Failed to query songs count");
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result === false || !is_array($result)) {
                throw new \RuntimeException("Failed to fetch songs count");
            }
            $stats['total_songs'] = min((int)$result['count'], 10000000); // Cap at 10M

            // Count total albums
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM albums");
            if ($stmt === false) {
                throw new \RuntimeException("Failed to query albums count");
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result === false || !is_array($result)) {
                throw new \RuntimeException("Failed to fetch albums count");
            }
            $stats['total_albums'] = min((int)$result['count'], 1000000); // Cap at 1M

            // Count total artists
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM artists");
            if ($stmt === false) {
                throw new \RuntimeException("Failed to query artists count");
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result === false || !is_array($result)) {
                throw new \RuntimeException("Failed to fetch artists count");
            }
            $stats['total_artists'] = min((int)$result['count'], 1000000); // Cap at 1M
        } catch (Exception $e) {
            error_log("Error collecting stats: " . $e->getMessage());
            throw $e;
        }

        return $stats;
    }

    /**
     * Send stats to the remote API
     * @return array<string, mixed>
     */
    public function sendStats(): array
    {
        if (!$this->canSendStats()) {
            return [
                'success' => false,
                'message' => 'Stats sending is disabled or rate limited'
            ];
        }

        try {
            $stats = $this->collectStats();

            $jsonData = json_encode($stats);
            if ($jsonData === false) {
                error_log("Failed to encode stats as JSON");
                return [
                    'success' => false,
                    'message' => 'Failed to encode statistics data'
                ];
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => self::STATS_API_URL,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonData,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Audinary/1.0',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if (!is_string($response)) {
                error_log("cURL failed to execute");
                return [
                    'success' => false,
                    'message' => 'Network error: Request failed'
                ];
            }

            if ($error !== '' && $error !== '0') {
                error_log("cURL error sending stats: " . $error);
                return [
                    'success' => false,
                    'message' => 'Network error: ' . $error
                ];
            }
            if ($httpCode === 200) {
                // Successfully sent, update last sent time
                $this->globalSettingsService->set('stats_last_sent', time());
                error_log("Stats successfully sent to Audinary homepage");
                return [
                    'success' => true,
                    'message' => 'Stats sent successfully',
                    'stats' => $stats
                ];
            }

            if ($httpCode === 429) {
                error_log("Stats rate limited - will retry tomorrow");
                return [
                    'success' => false,
                    'message' => 'Rate limited - can only send stats once per day'
                ];
            } else {
                $responseData = json_decode($response, true);
                if (!is_array($responseData)) {
                    $responseData = [];
                }
                $errorMessage = $responseData['error'] ?? "HTTP $httpCode";
                error_log("Failed to send stats: HTTP $httpCode - " . $response);

                return [
                    'success' => false,
                    'message' => 'API error: ' . $errorMessage,
                    'httpCode' => $httpCode
                ];
            }
        } catch (Exception $e) {
            error_log("Exception sending stats: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get stats preview (what would be sent)
     * @return array<string, mixed>
     */
    public function getStatsPreview(): array
    {
        try {
            $stats = $this->collectStats();
            $config = $this->getStatsConfig();

            return [
                'success' => true,
                'stats' => $stats,
                'config' => $config,
                'canSend' => $this->canSendStats(),
                'nextSendAllowed' => $this->getNextSendTime()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error generating preview: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get the next time stats can be sent
     */
    private function getNextSendTime(): ?string
    {
        $lastSent = $this->globalSettingsService->get('stats_last_sent', null);
        if ($lastSent === null) {
            return null;
        }

        $lastSentTime = is_numeric($lastSent) ? (int)$lastSent : strtotime($lastSent);
        $nextSendTime = $lastSentTime + (self::RATE_LIMIT_HOURS * 3600);

        return date('c', $nextSendTime);
    }

    /**
     * Try to send stats if appropriate (called on user activity)
     */
    public function tryAutoSendStats(): void
    {
        if (!$this->canSendStats()) {
            return;
        }

        // Only auto-send if it's been more than 6 hours since last attempt
        $lastAttempt = $this->globalSettingsService->get('stats_last_attempt', null);
        if ($lastAttempt !== null) {
            $lastAttemptTime = is_numeric($lastAttempt) ? (int)$lastAttempt : strtotime($lastAttempt);
            $timeSinceLastAttempt = time() - $lastAttemptTime;

            if ($timeSinceLastAttempt < (6 * 3600)) { // 6 hours
                return;
            }
        }

        // Update last attempt time regardless of success
        $this->globalSettingsService->set('stats_last_attempt', time());

        // Send stats in the background (don't block the user request)
        $result = $this->sendStats();

        if ($result['success']) {
            error_log("Auto-sent stats successfully");
        } else {
            error_log("Auto-send stats failed: " . $result['message']);
        }
    }
}
