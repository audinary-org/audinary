<?php

namespace App\Services;

use PDO;

class GlobalSettingsService
{
    private PDO $pdo;
    /** @var array<string, mixed> */
    private array $cache = [];
    private bool $cacheLoaded = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get a setting value by key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadCache();

        if (!isset($this->cache[$key])) {
            return $default;
        }

        $value = $this->cache[$key];

        // Try to decode JSON values
        if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Convert string booleans
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        // Convert numeric strings
        if (is_numeric($value)) {
            return str_contains((string)$value, '.') ? (float)$value : (int)$value;
        }

        return $value;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, mixed $value): bool
    {
        // Convert arrays/objects to JSON
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value);
            if ($encoded === false) {
                throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
            }
            $value = $encoded;
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } else {
            $value = (string)$value;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO global_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value
        ');

        $result = $stmt->execute([$key, $value]);

        if ($result) {
            $this->cache[$key] = $value;
        }

        return $result;
    }

    /**
     * Get multiple settings by key prefix
     */
    /** @return array<string, mixed> */
    public function getByPrefix(string $prefix): array
    {
        $this->loadCache();

        $result = [];
        foreach ($this->cache as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                // Process the cached value directly instead of calling get() to avoid recursion
                if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $result[$key] = $decoded;
                        continue;
                    }
                }

                // Convert string booleans
                if ($value === 'true') {
                    $result[$key] = true;
                    continue;
                }
                if ($value === 'false') {
                    $result[$key] = false;
                    continue;
                }

                // Convert numeric strings
                if (is_numeric($value)) {
                    $result[$key] = str_contains((string)$value, '.') ? (float)$value : (int)$value;
                    continue;
                }

                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get all settings
     */
    /** @return array<string, mixed> */
    public function getAll(): array
    {
        $this->loadCache();

        $result = [];
        foreach ($this->cache as $key => $value) {
            // Process the cached value directly instead of calling get() to avoid recursion
            if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $result[$key] = $decoded;
                    continue;
                }
            }

            // Convert string booleans
            if ($value === 'true') {
                $result[$key] = true;
                continue;
            }
            if ($value === 'false') {
                $result[$key] = false;
                continue;
            }

            // Convert numeric strings
            if (is_numeric($value)) {
                $result[$key] = str_contains((string)$value, '.') ? (float)$value : (int)$value;
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Get MPD configuration
     */
    /** @return array<string, mixed> */
    public function getMpdConfig(): array
    {
        return [
            'enabled' => $this->get('mpd_enabled', false),
            'host' => $this->get('mpd_host', ''),
            'port' => $this->get('mpd_port', 6600),
            'password' => $this->get('mpd_password', ''),
            'defaultVolume' => $this->get('mpd_default_volume', 80),
            'replaygain' => $this->get('mpd_replaygain', 'auto'),
            'outputDevice' => $this->get('mpd_output_device', 0),
        ];
    }

    /**
     * Set MPD configuration
     * @param array<string, mixed> $config
     */
    public function setMpdConfig(array $config): bool
    {
        $success = $this->set('mpd_enabled', $config['enabled'] ?? false);
        $success = $this->set('mpd_host', $config['host'] ?? '') && $success;
        $success = $this->set('mpd_port', $config['port'] ?? 6600) && $success;
        $success = $this->set('mpd_password', $config['password'] ?? '') && $success;
        $success = $this->set('mpd_default_volume', $config['defaultVolume'] ?? 80) && $success;
        $success = $this->set('mpd_replaygain', $config['replaygain'] ?? 'auto') && $success;

        return $this->set('mpd_output_device', $config['outputDevice'] ?? 0) && $success;
    }

    /**
     * Get SMTP configuration
     */
    /** @return array<string, mixed> */
    public function getSmtpConfig(): array
    {
        return [
            'enabled' => $this->get('smtp_enabled', true),
            'host' => $this->get('smtp_host', ''),
            'port' => $this->get('smtp_port', 465),
            'encryption' => $this->get('smtp_encryption', 'ssl'),
            'username' => $this->get('smtp_username', ''),
            'password' => $this->get('smtp_password', ''),
            'from_email' => $this->get('smtp_from_email', ''),
            'from_name' => $this->get('smtp_from_name', 'Audinary Music Server'),
            'debug' => $this->get('smtp_debug', false),
        ];
    }

    /**
     * Set SMTP configuration
     * @param array<string, mixed> $config
     */
    public function setSmtpConfig(array $config): bool
    {
        $success = $this->set('smtp_enabled', $config['enabled'] ?? true);
        $success = $this->set('smtp_host', $config['host'] ?? '') && $success;
        $success = $this->set('smtp_port', $config['port'] ?? 465) && $success;
        $success = $this->set('smtp_encryption', $config['encryption'] ?? 'ssl') && $success;
        $success = $this->set('smtp_username', $config['username'] ?? '') && $success;
        $success = $this->set('smtp_password', $config['password'] ?? '') && $success;
        $success = $this->set('smtp_from_email', $config['from_email'] ?? '') && $success;
        $success = $this->set('smtp_from_name', $config['from_name'] ?? 'Audinary Music Server') && $success;

        return $this->set('smtp_debug', $config['debug'] ?? false) && $success;
    }

    /**
     * Get password reset configuration
     */
    /** @return array<string, mixed> */
    public function getPasswordResetConfig(): array
    {
        return [
            'tokenValidityMinutes' => $this->get('password_reset_token_validity_minutes', 15),
            'maxRequestsPerHour' => $this->get('password_reset_max_requests_per_hour', 10),
            'cleanupIntervalHours' => $this->get('password_reset_cleanup_interval_hours', 24),
        ];
    }

    /**
     * Get backup configuration
     */
    /** @return array<string, mixed> */
    public function getBackupConfig(): array
    {
        return [
            'retentionDays' => $this->get('backup_retention_days', 30),
            'maxBackups' => $this->get('backup_max_backups', 10),
            'compression' => $this->get('backup_compression', 'gzip'),
            'excludePatterns' => $this->get('backup_exclude_patterns', ['.tmp', '.log', 'Thumbs.db', '.DS_Store']),
        ];
    }

    /**
     * Get music scanning configuration
     */
    /** @return array<string, mixed> */
    public function getMusicScanConfig(): array
    {
        return [
            'allowedExtensions' => $this->get('allowed_extensions', [
                'mp3', 'wav', 'flac', 'ogg', 'm4a', 'aac', 'wma', 'aiff',
                'aif', 'ape', 'wv', 'mpc', 'opus', 'ra', 'rm', 'mka',
            ]),
            'coverNames' => $this->get('cover_names', ['cover', 'folder', 'front']),
            'coverExtensions' => $this->get('cover_extensions', ['jpg', 'jpeg', 'png']),
            'artistImageNames' => $this->get('artist_image_names', ['artist', 'band', 'photo', 'folder']),
            'artistImageExtensions' => $this->get('artist_image_extensions', ['jpg', 'jpeg', 'png']),
        ];
    }

    /**
     * Set music scanning configuration
     * @param array<string, mixed> $config
     */
    public function setMusicScanConfig(array $config): bool
    {
        $results = [];

        if (array_key_exists('allowedExtensions', $config)) {
            $results[] = $this->set('allowed_extensions', $config['allowedExtensions']);
        }
        if (array_key_exists('coverNames', $config)) {
            $results[] = $this->set('cover_names', $config['coverNames']);
        }
        if (array_key_exists('coverExtensions', $config)) {
            $results[] = $this->set('cover_extensions', $config['coverExtensions']);
        }
        if (isset($config['artistImageNames'])) {
            $results[] = $this->set('artist_image_names', $config['artistImageNames']);
        }
        if (isset($config['artistImageExtensions'])) {
            $results[] = $this->set('artist_image_extensions', $config['artistImageExtensions']);
        }

        // Return true if no settings were updated or if all updates succeeded
        return count($results) === 0 ? true : !in_array(false, $results, true);
    }

    /**
     * Check if user registration is enabled
     */
    public function isUserRegistrationEnabled(): bool
    {
        return $this->get('user_registration_enabled', true);
    }

    /**
     * Set user registration enabled status
     */
    public function setUserRegistrationEnabled(bool $enabled): bool
    {
        return $this->set('user_registration_enabled', $enabled);
    }

    /**
     * Load all settings into cache
     */
    private function loadCache(): void
    {
        if ($this->cacheLoaded) {
            return;
        }

        $stmt = $this->pdo->query('SELECT setting_key, setting_value FROM global_settings');

        if ($stmt !== false) {
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (!is_array($row)) {
                    continue;
                }
                $this->cache[$row['setting_key']] = $row['setting_value'];
            }
        }

        $this->cacheLoaded = true;
    }

    /**
     * Clear cache (useful for testing)
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->cacheLoaded = false;
    }
}
