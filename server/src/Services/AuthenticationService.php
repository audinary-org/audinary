<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuthToken;
use App\Repository\UserRepository;
use App\Repository\GlobalSettingsRepository;
use App\Repository\UserSettingsRepository;
use DateInterval;
use DateTime;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Consolidated Authentication Service - Handles all authentication, JWT, registration, and user settings operations
 * Combines functionality from JWTService, UserRegistrationService, UserSettingsService
 */
class AuthenticationService
{
    private UserRepository $userRepository;
    private GlobalSettingsRepository $settingsRepository;
    private UserSettingsRepository $userSettingsRepository;

    // JWT Configuration
    private string $jwtSecret;
    private string $jwtIssuer;
    private int $jwtExpirationTime;

    public function __construct(string $jwtSecret, string $jwtIssuer = 'audinary', int $jwtExpirationTime = 31536000) // 1 year default
    {
        $this->userRepository = new UserRepository();
        $this->settingsRepository = new GlobalSettingsRepository();
        $this->userSettingsRepository = new UserSettingsRepository();

        $this->jwtSecret = $jwtSecret;
        $this->jwtIssuer = $jwtIssuer;
        $this->jwtExpirationTime = $jwtExpirationTime;
    }

    // ========================================
    // AUTHENTICATION OPERATIONS
    // ========================================

    /**
     * Authenticate user with username and password
     */
    public function authenticate(string $username, string $password): ?AuthToken
    {
        $user = $this->userRepository->findByUsername($username);

        if (!$user instanceof \App\Models\User || !$user->verifyPassword($password)) {
            return null;
        }

        // Update last login
        $this->userRepository->updateLastLogin($user->getUserId());

        // Generate JWT token
        return $this->generateToken($user);
    }

    /**
     * Verify JWT token and return user data
     */
    public function verifyToken(string $token): ?AuthToken
    {
        $authToken = $this->verifyJwtToken($token);

        if (!$authToken instanceof \App\Models\AuthToken || !$authToken->isValid()) {
            return null;
        }

        // Verify user still exists
        $user = $this->userRepository->findById($authToken->getUserId());
        if (!$user instanceof \App\Models\User) {
            return null;
        }

        return $authToken;
    }

    /**
     * Get user by auth token
     */
    public function getUserByToken(string $token): ?User
    {
        $authToken = $this->verifyToken($token);

        if (!$authToken instanceof \App\Models\AuthToken) {
            return null;
        }

        return $this->userRepository->findById($authToken->getUserId());
    }

    /**
     * Refresh auth token
     */
    public function refreshToken(string $token): ?AuthToken
    {
        $authToken = $this->verifyToken($token);

        if (!$authToken instanceof \App\Models\AuthToken) {
            return null;
        }

        $user = $this->userRepository->findById($authToken->getUserId());
        if (!$user instanceof \App\Models\User) {
            return null;
        }

        return $this->generateToken($user);
    }

    /**
     * Logout (for JWT this is mainly client-side, but we could implement a blacklist)
     */
    public function logout(string $token): bool
    {
        // For JWT, logout is typically handled client-side by removing the token
        // We could implement a token blacklist here if needed
        // Token parameter kept for future blacklist implementation
        return true;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(string $token): bool
    {
        $authToken = $this->verifyToken($token);
        return $authToken && $authToken->isAdmin();
    }

    /**
     * Validate authentication credentials
     * @return string[]
     */
    public function validateCredentials(string $username, string $password): array
    {
        $errors = [];

        if (in_array(trim($username), ['', '0'], true)) {
            $errors[] = 'Username is required';
        }

        if (in_array(trim($password), ['', '0'], true)) {
            $errors[] = 'Password is required';
        }

        if (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        }

        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }

        return $errors;
    }

    /**
     * Get authentication status
     * @return array<string, mixed>
     */
    public function getAuthStatus(): array
    {
        $userCount = $this->userRepository->getUserCount();
        return [
            'allowAdminRegistration' => $userCount === 0,
        ];
    }

    // ========================================
    // JWT OPERATIONS (from JWTService)
    // ========================================

    /**
     * Generate JWT token from user
     */
    public function generateToken(User $user): AuthToken
    {
        $issuedAt = new DateTime();
        $expiresAt = (clone $issuedAt)->add(new DateInterval('PT' . $this->jwtExpirationTime . 'S'));

        $authToken = new AuthToken(
            token: '',
            userId: $user->getUserId(),
            username: $user->getUsername(),
            isAdmin: $user->isAdmin(),
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            displayName: $user->getDisplayName(),
            imageUuid: $user->getImageUuid(),
            issuer: $this->jwtIssuer
        );

        $payload = $authToken->getPayload();
        $token = JWT::encode($payload, $this->jwtSecret, 'HS256');

        return new AuthToken(
            token: $token,
            userId: $user->getUserId(),
            username: $user->getUsername(),
            isAdmin: $user->isAdmin(),
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            displayName: $user->getDisplayName(),
            imageUuid: $user->getImageUuid(),
            issuer: $this->jwtIssuer
        );
    }

    /**
     * Verify JWT token and return AuthToken
     */
    private function verifyJwtToken(string $token): ?AuthToken
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            // Create AuthToken from decoded payload
            $payload = (array)$decoded;

            return new AuthToken(
                token: $token,
                userId: $payload['user_id'] ?? '',
                username: $payload['username'] ?? '',
                isAdmin: $payload['is_admin'] ?? false,
                issuedAt: new DateTime('@' . ($payload['iat'] ?? time())),
                expiresAt: new DateTime('@' . ($payload['exp'] ?? time())),
                displayName: $payload['display_name'] ?? '',
                imageUuid: $payload['image_uuid'] ?? null,
                issuer: $payload['iss'] ?? $this->jwtIssuer
            );
        } catch (Exception $e) {
            error_log("JWT Verification: Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify JWT token and return decoded payload
     */
    /** @return array<string, mixed> */
    public function verifyJWT(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return (array)$decoded;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Check if JWT token is expired
     */
    public function isTokenExpired(string $token): bool
    {
        $authToken = $this->verifyJwtToken($token);
        return !$authToken instanceof \App\Models\AuthToken || !$authToken->isValid();
    }

    /**
     * Get remaining token lifetime in seconds
     */
    public function getTokenLifetime(string $token): int
    {
        $authToken = $this->verifyJwtToken($token);

        if (!$authToken instanceof \App\Models\AuthToken) {
            return 0;
        }

        $now = new DateTime();
        $expiresAt = $authToken->getExpiresAt();

        return max(0, $expiresAt->getTimestamp() - $now->getTimestamp());
    }

    // ========================================
    // USER REGISTRATION (from UserRegistrationService)
    // ========================================

    /**
     * Register a new user
     * @return array<string, mixed>
     */
    public function register(string $username, string $password, ?string $displayName = null, ?string $email = null): array
    {
        // Validate input
        $validationErrors = $this->validateRegistrationData($username, $password, $email);
        if ($validationErrors !== []) {
            return [
                'success' => false,
                'errors' => $validationErrors
            ];
        }

        // Check if registration is allowed
        if (!$this->isRegistrationAllowed()) {
            return [
                'success' => false,
                'errors' => ['Registration is currently disabled']
            ];
        }

        // Check if username exists
        if ($this->userRepository->findByUsername($username) instanceof \App\Models\User) {
            return [
                'success' => false,
                'errors' => ['Username already exists']
            ];
        }

        // Check if email exists (if provided)
        // Note: Email uniqueness check disabled until findByEmail is implemented in UserRepository
        // if ($email && $this->userRepository->findByEmail($email)) {
        //     return [
        //         'success' => false,
        //         'errors' => ['Email already exists']
        //     ];
        // }

        // Determine if this should be an admin user (first user)
        $isAdmin = $this->userRepository->getUserCount() === 0;

        // Create the user - simplified for now, needs proper implementation
        try {
            $resolvedDisplayName = $displayName !== null
                && $displayName !== ''
                && $displayName !== '0'
                ? $displayName
                : $username;
            $userId = $this->userRepository->createUser(
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $resolvedDisplayName,
                $email,
                $isAdmin
            );
            $user = $this->userRepository->findById($userId);

            if (!$user instanceof \App\Models\User) {
                return [
                    'success' => false,
                    'errors' => ['Failed to create user']
                ];
            }

            // Generate auth token
            $authToken = $this->generateToken($user);

            return [
                'success' => true,
                'user' => [
                    'userId' => $user->getUserId(),
                    'username' => $user->getUsername(),
                    'displayName' => $user->getDisplayName(),
                    'isAdmin' => $user->isAdmin()
                ],
                'token' => $authToken->getToken(),
                'message' => $isAdmin ? 'Admin user created successfully' : 'User registered successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => ['Registration failed: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Validate registration data
     * @return string[]
     */
    private function validateRegistrationData(string $username, string $password, ?string $email = null): array
    {
        $errors = [];

        // Username validation
        if (in_array(trim($username), ['', '0'], true)) {
            $errors[] = 'Username is required';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        } elseif (strlen($username) > 50) {
            $errors[] = 'Username must not exceed 50 characters';
        } elseif (in_array(preg_match('/^[a-zA-Z0-9_-]+$/', $username), [0, false], true)) {
            $errors[] = 'Username can only contain letters, numbers, underscores, and hyphens';
        }

        // Password validation
        if (in_array(trim($password), ['', '0'], true)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        } elseif (strlen($password) > 255) {
            $errors[] = 'Password must not exceed 255 characters';
        }

        // Email validation (if provided)
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        return $errors;
    }

    /**
     * Check if registration is allowed
     */
    private function isRegistrationAllowed(): bool
    {
        // Always allow registration if no users exist (admin creation)
        if ($this->userRepository->getUserCount() === 0) {
            return true;
        }

        return $this->settingsRepository->isRegistrationAllowed();
    }

    // ========================================
    // USER SETTINGS (from UserSettingsService)
    // ========================================

    /**
     * Get transcoding options for a user
     * @return array<string, mixed>
     */
    public function getTranscodingOptions(string $userId): array
    {
        return $this->userSettingsRepository->getTranscodingSettings($userId);
    }

    /**
     * Update user transcoding settings
     * @param array<string, mixed> $settings
     */
    public function updateTranscodingSettings(string $userId, array $settings): bool
    {
        if (!method_exists($this->userSettingsRepository, 'updateTranscodingSettings')) {
            return false; // Method not implemented yet
        }

        $validSettings = [
            'enabled' => $settings['enabled'] ?? false,
            'quality' => $settings['quality'] ?? 'medium',
            'format' => $settings['format'] ?? 'aac',
            'mode' => $settings['mode'] ?? 'cbr'
        ];

        return $this->userSettingsRepository->setUserSettings($userId, [
            'transcoding_enabled' => $validSettings['enabled'] ? '1' : '0',
            'transcoding_quality' => $validSettings['quality'],
            'transcoding_format' => $validSettings['format'],
            'transcoding_mode' => $validSettings['mode']
        ]);
    }

    /**
     * Get all user settings
     * @return array<string, mixed>
     */
    public function getUserSettings(string $userId): array
    {
        return $this->userSettingsRepository->getAllUserSettings($userId);
    }

    /**
     * Update user setting
     * @param mixed $value
     */
    public function updateUserSetting(string $userId, string $key, $value): bool
    {
        return $this->userSettingsRepository->setUserSetting($userId, $key, (string)$value);
    }

    /**
     * Get user setting
     * @param mixed $default
     * @return mixed
     */
    public function getUserSetting(string $userId, string $key, $default = null)
    {
        $settings = $this->userSettingsRepository->getUserSettings($userId, [$key]);
        return $settings[$key] ?? $default;
    }

    /**
     * Reset user settings to defaults
     */
    public function resetUserSettings(string $userId): bool
    {
        return $this->userSettingsRepository->deleteAllUserSettings($userId);
    }

    // ========================================
    // SERVICE CONFIGURATION
    // ========================================

    /**
     * Get JWT configuration
     * @return array<string, mixed>
     */
    public function getJwtConfig(): array
    {
        return [
            'issuer' => $this->jwtIssuer,
            'expiration_time' => $this->jwtExpirationTime,
            'algorithm' => 'HS256'
        ];
    }

    /**
     * Update JWT configuration
     */
    public function updateJwtConfig(?string $issuer = null, ?int $expirationTime = null): void
    {
        if ($issuer !== null) {
            $this->jwtIssuer = $issuer;
        }

        if ($expirationTime !== null) {
            $this->jwtExpirationTime = $expirationTime;
        }
    }

    /**
     * Get service statistics
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        // Count admin users manually since there's no getAdminCount method
        $users = $this->userRepository->findAll(1000); // Get up to 1000 users
        $adminCount = count(array_filter($users, fn($user) => $user->isAdmin()));

        return [
            'total_users' => $this->userRepository->getUserCount(),
            'admin_users' => $adminCount,
            'registration_enabled' => $this->settingsRepository->isRegistrationAllowed(),
            'jwt_expiration_time' => $this->jwtExpirationTime,
            'jwt_issuer' => $this->jwtIssuer
        ];
    }

    /**
     * Get user ID from request token
     */
    public function getUserIdFromRequest(ServerRequestInterface $request): string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader === '' || $authHeader === '0') {
            throw new InvalidArgumentException('No authorization header provided');
        }

        if (!str_starts_with($authHeader, 'Bearer ')) {
            throw new InvalidArgumentException('Invalid authorization header format');
        }

        $token = substr($authHeader, 7);
        $authToken = $this->verifyToken($token);

        if (!$authToken instanceof \App\Models\AuthToken) {
            throw new InvalidArgumentException('Invalid or expired token');
        }

        return $authToken->getUserId();
    }

    /**
     * Check if user can create public shares
     */
    public function canCreatePublicShare(string $userId): bool
    {
        $user = $this->userRepository->findById($userId);
        if (!$user instanceof \App\Models\User) {
            return false;
        }

        // Admins can always create public shares
        if ($user->isAdmin()) {
            return true;
        }

        // Check user's permission setting (default to true)
        $setting = $this->userSettingsRepository->getAllUserSettings($userId);
        return ($setting['can_create_public_share'] ?? '1') === '1';
    }
}
