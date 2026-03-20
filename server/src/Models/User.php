<?php

namespace App\Models;

use DateTime;

/**
 * User entity
 */
class User
{
    public function __construct(
        private string $userId,
        private string $username,
        private string $passwordHash,
        private ?string $displayName = null,
        private ?string $email = null,
        private bool $isAdmin = false,
        private ?string $imageUuid = null,
        private ?DateTime $lastLogin = null,
        private ?DateTime $createdAt = null,
        private ?DateTime $updatedAt = null
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): void
    {
        $this->isAdmin = $isAdmin;
    }

    public function getImageUuid(): ?string
    {
        return $this->imageUuid;
    }

    public function setImageUuid(?string $imageUuid): void
    {
        $this->imageUuid = $imageUuid;
    }

    public function getLastLogin(): ?DateTime
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?DateTime $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->userId,
            'username' => $this->username,
            'display_name' => $this->displayName ?? $this->username,
            'email' => $this->email,
            'role' => $this->isAdmin ? 'admin' : 'user',
            'is_admin' => $this->isAdmin,
            'image_uuid' => $this->imageUuid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->userId,
            'username' => $this->username,
            'display_name' => $this->displayName ?? $this->username,
            'email' => $this->email,
            'role' => $this->isAdmin ? 'admin' : 'user',
            'is_admin' => $this->isAdmin,
            'image_uuid' => $this->imageUuid
        ];
    }
}
