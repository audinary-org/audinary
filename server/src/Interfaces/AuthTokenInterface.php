<?php

namespace App\Interfaces;

use DateTime;

/**
 * Interface for authentication tokens
 */
interface AuthTokenInterface
{
    public function getToken(): string;
    public function getUserId(): string;
    public function getUsername(): string;
    public function isAdmin(): bool;
    public function getIssuedAt(): DateTime;
    public function getExpiresAt(): DateTime;
    public function getDisplayName(): ?string;
    public function getImageUuid(): ?string;
    public function getIssuer(): string;
    public function isExpired(): bool;
}
