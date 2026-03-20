<?php

namespace App\Repository;

use App\Database\Connection;
use Exception;
use PDO;

/**
 * Repository for user settings data access
 */
class UserSettingsRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Get user settings by user ID and setting keys
     * @param array<int, string> $settingKeys
     * @return array<string, mixed>
     */
    public function getUserSettings(string $userId, array $settingKeys): array
    {
        if ($settingKeys === []) {
            return [];
        }

        $placeholders = str_repeat('?,', count($settingKeys) - 1) . '?';
        $sql = "SELECT setting_key, setting_value FROM user_settings 
                WHERE user_id = ? AND setting_key IN ($placeholders)";

        $params = array_merge([$userId], $settingKeys);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $settings = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Get all user settings for a user
     * @return array<string, mixed>
     */
    public function getAllUserSettings(string $userId): array
    {
        $sql = "SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        $settings = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Set a user setting
     */
    public function setUserSetting(string $userId, string $settingKey, string $settingValue): bool
    {
        $sql = "INSERT INTO user_settings (user_id, setting_key, setting_value) 
                VALUES (?, ?, ?) 
                ON CONFLICT(user_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $settingKey, $settingValue]);
    }

    /**
     * Set multiple user settings
     * @param array<string, mixed> $settings
     */
    public function setUserSettings(string $userId, array $settings, bool $useTransaction = true): bool
    {
        if ($settings === []) {
            return true;
        }

        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            foreach ($settings as $key => $value) {
                if (!$this->setUserSetting($userId, $key, $value)) {
                    if ($useTransaction) {
                        $this->db->rollBack();
                    }
                    return false;
                }
            }

            if ($useTransaction) {
                $this->db->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Delete a user setting
     */
    public function deleteUserSetting(string $userId, string $settingKey): bool
    {
        $sql = "DELETE FROM user_settings WHERE user_id = ? AND setting_key = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $settingKey]);
    }

    /**
     * Delete all user settings for a user
     */
    public function deleteAllUserSettings(string $userId): bool
    {
        $sql = "DELETE FROM user_settings WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId]);
    }

    /**
     * Get transcoding settings for a user
     * @return array<string, mixed>
     */
    public function getTranscodingSettings(string $userId): array
    {
        // New transcoding settings structure
        $transcodingKeys = [
            'transcoding_enabled',
            'transcoding_format',
            'transcoding_mode',
            'transcoding_quality'
        ];

        $settings = $this->getUserSettings($userId, $transcodingKeys);

        return [
            'enabled' => ($settings['transcoding_enabled'] ?? '0') === '1',
            'format' => $settings['transcoding_format'] ?? 'aac', // AAC default
            'mode' => $settings['transcoding_mode'] ?? 'cbr',     // CBR default
            'quality' => $settings['transcoding_quality'] ?? 'medium' // Medium default
        ];
    }
}
