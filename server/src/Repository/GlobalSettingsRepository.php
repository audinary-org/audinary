<?php

namespace App\Repository;

use PDO;

/**
 * Repository for global settings data access
 */
class GlobalSettingsRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Get a setting value
     */
    public function getSetting(string $key): ?string
    {
        $sql = "SELECT setting_value FROM global_settings WHERE setting_key = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare query");
        }
        $stmt->execute([$key]);

        $result = $stmt->fetchColumn();
        return $result !== false ? (string)$result : null;
    }

    /**
     * Set a setting value
     */
    public function setSetting(string $key, string $value): bool
    {
        $sql = "INSERT INTO global_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$key, $value]);
    }

    /**
     * Get multiple settings
     * @param array<int, string> $keys
     * @return array<string, string>
     */
    public function getSettings(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $placeholders = str_repeat('?,', count($keys) - 1) . '?';
        $sql = "SELECT setting_key, setting_value FROM global_settings WHERE setting_key IN ($placeholders)";

        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare query");
        }
        $stmt->execute($keys);

        $settings = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Get all settings
     * @return array<string, string>
     */
    public function getAllSettings(): array
    {
        $sql = "SELECT setting_key, setting_value FROM global_settings";
        $stmt = $this->db->query($sql);
        if ($stmt === false) {
            throw new \RuntimeException("Failed to query global settings");
        }

        $settings = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Delete a setting
     */
    public function deleteSetting(string $key): bool
    {
        $sql = "DELETE FROM global_settings WHERE setting_key = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$key]);
    }

    /**
     * Check if registration is allowed
     */
    public function isRegistrationAllowed(): bool
    {
        $value = $this->getSetting('registrationAllowed');
        return $value === '1';
    }

    /**
     * Set registration allowed
     */
    public function setRegistrationAllowed(bool $allowed): bool
    {
        return $this->setSetting('registrationAllowed', $allowed ? '1' : '0');
    }

    /**
     * Get app version
     */
    public function getAppVersion(): ?string
    {
        return $this->getSetting('app_version');
    }

    /**
     * Set app version
     */
    public function setAppVersion(string $version): bool
    {
        return $this->setSetting('app_version', $version);
    }

    /**
     * Get Last.fm API key
     */
    public function getLastfmApiKey(): ?string
    {
        return $this->getSetting('lastfm_api_key');
    }

    /**
     * Set Last.fm API key
     */
    public function setLastfmApiKey(string $key): bool
    {
        return $this->setSetting('lastfm_api_key', $key);
    }

    /**
     * Check if wishlist feature is enabled
     */
    public function isWishlistEnabled(): bool
    {
        $value = $this->getSetting('wishlist_enabled');
        return $value === '1' || $value === 'true';
    }

    /**
     * Set wishlist enabled
     */
    public function setWishlistEnabled(bool $enabled): bool
    {
        return $this->setSetting('wishlist_enabled', $enabled ? '1' : '0');
    }
}
