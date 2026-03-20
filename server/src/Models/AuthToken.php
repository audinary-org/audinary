<?php

namespace App\Models;

use App\Interfaces\AuthTokenInterface;
use DateTime;

/**
 * Authentication token entity
 */
class AuthToken implements AuthTokenInterface
{
    public function __construct(
        private string $token,
        private string $userId,
        private string $username,
        private bool $isAdmin,
        private DateTime $issuedAt,
        private DateTime $expiresAt,
        private ?string $displayName = null,
        private ?string $imageUuid = null,
        private string $issuer = 'audinary'
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function getIssuedAt(): DateTime
    {
        return $this->issuedAt;
    }

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getImageUuid(): ?string
    {
        return $this->imageUuid;
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function isExpired(): bool
    {
        return new DateTime() > $this->expiresAt;
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return [
            'iss' => $this->issuer,
            'iat' => $this->issuedAt->getTimestamp(),
            'exp' => $this->expiresAt->getTimestamp(),
            'user_id' => $this->userId,
            'username' => $this->username,
            'is_admin' => $this->isAdmin,
            'display_name' => $this->displayName ?? $this->username,
            'image_uuid' => $this->imageUuid
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'user_id' => $this->userId,
            'username' => $this->username,
            'is_admin' => $this->isAdmin,
            'display_name' => $this->displayName,
            'image_uuid' => $this->imageUuid,
            'issued_at' => $this->issuedAt->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
            'is_expired' => $this->isExpired(),
            'is_valid' => $this->isValid()
        ];
    }
}
