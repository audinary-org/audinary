<?php

namespace App\Repository;

use App\Models\User;
use DateTime;
use Exception;
use PDO;

/**
 * Repository for user data access
 */
class UserRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Find user by ID
     */
    public function findById(string $userId): ?User
    {
        $sql = "SELECT user_id, username, password_hash, display_name, email, is_admin, image_uuid, last_login, created_at, updated_at
                FROM users WHERE user_id = ? LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->createUserFromRow($row);
    }

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?User
    {
        $sql = "SELECT user_id, username, password_hash, display_name, email, is_admin, image_uuid, last_login, created_at, updated_at
                FROM users WHERE username = ? LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->createUserFromRow($row);
    }

    /**
     * Create a new user
     */
    public function create(User $user): bool
    {
        $sql = "INSERT INTO users (user_id, username, password_hash, display_name, email, is_admin, image_uuid)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $user->getUserId(),
            $user->getUsername(),
            $user->getPasswordHash(),
            $user->getDisplayName(),
            $user->getEmail(),
            $user->isAdmin() ? 1 : 0,
            $user->getImageUuid()
        ]);
    }

    /**
     * Create a new user with individual parameters (used by AuthenticationService)
     */
    public function createUser(string $username, string $passwordHash, ?string $displayName = null, ?string $email = null, bool $isAdmin = false): string
    {
        $userId = $this->generateUuid();

        $sql = "INSERT INTO users (user_id, username, password_hash, display_name, email, is_admin, image_uuid)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            $userId,
            $username,
            $passwordHash,
            $displayName,
            $email,
            $isAdmin ? 1 : 0,
            null
        ]);

        if (!$success) {
            throw new Exception('Failed to create user');
        }

        return $userId;
    }

    /**
     * Update user
     */
    public function update(User $user): bool
    {
        $sql = "UPDATE users 
                SET display_name = ?, email = ?, is_admin = ?, image_uuid = ?, updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $user->getDisplayName(),
            $user->getEmail(),
            $user->isAdmin() ? 1 : 0,
            $user->getImageUuid(),
            $user->getUserId()
        ]);
    }

    /**
     * Update user password
     */
    public function updatePassword(string $userId, string $passwordHash): bool
    {
        $sql = "UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$passwordHash, $userId]);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(string $userId): bool
    {
        $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId]);
    }

    /**
     * Delete user
     */
    public function delete(string $userId): bool
    {
        $sql = "DELETE FROM users WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId]);
    }

    /**
     * Check if username exists
     */
    public function usernameExists(string $username): bool
    {
        $sql = "SELECT 1 FROM users WHERE username = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$username]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Get user count
     */
    public function getUserCount(): int
    {
        $sql = "SELECT COUNT(*) FROM users";
        $stmt = $this->db->query($sql);
        if ($stmt === false) {
            throw new \RuntimeException("Failed to query user count");
        }
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get all users
     * @return array<int, User>
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT user_id, username, password_hash, display_name, email, is_admin, image_uuid, last_login, created_at, updated_at
                FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit, $offset]);

        $users = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $users[] = $this->createUserFromRow($row);
        }

        return $users;
    }

    /**
     * Update user username
     */
    public function updateUsername(string $userId, string $username): bool
    {
        $sql = "UPDATE users SET username = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$username, $userId]);
    }

    /**
     * Update user display name
     */
    public function updateDisplayName(string $userId, ?string $displayName): bool
    {
        $sql = "UPDATE users SET display_name = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$displayName, $userId]);
    }

    /**
     * Update user profile image
     */
    public function updateProfileImage(string $userId, ?string $imageUuid): bool
    {
        $sql = "UPDATE users SET image_uuid = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$imageUuid, $userId]);
    }

    /**
     * Check if username exists for a different user
     */
    public function usernameExistsForOtherUser(string $username, string $excludeUserId): bool
    {
        $sql = "SELECT 1 FROM users WHERE username = ? AND user_id != ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$username, $excludeUserId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Search users by username or display name (excluding specific user)
     * @return array<int, array<string, mixed>>
     */
    public function searchUsers(string $searchTerm, string $excludeUserId, int $limit = 20): array
    {
        $searchPattern = '%' . $searchTerm . '%';
        $sql = "SELECT user_id, username, display_name, created_at
                FROM users
                WHERE (username ILIKE ? OR display_name ILIKE ?)
                AND user_id != ?
                ORDER BY username ASC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$searchPattern, $searchPattern, $excludeUserId, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get limited users for dropdown (excluding specific user)
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableUsers(string $excludeUserId, int $limit = 5): array
    {
        $sql = "SELECT user_id, username, display_name, created_at
                FROM users
                WHERE user_id != ?
                ORDER BY username ASC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$excludeUserId, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create User object from database row
     */
    /** @param array<string, mixed> $row */
    private function createUserFromRow(array $row): User
    {
        return new User(
            userId: $row['user_id'],
            username: $row['username'],
            passwordHash: $row['password_hash'],
            displayName: $row['display_name'],
            email: $row['email'],
            isAdmin: (bool)$row['is_admin'],
            imageUuid: $row['image_uuid'],
            lastLogin: $row['last_login'] ? new DateTime($row['last_login']) : null,
            createdAt: $row['created_at'] ? new DateTime($row['created_at']) : null,
            updatedAt: $row['updated_at'] ? new DateTime($row['updated_at']) : null
        );
    }
}
