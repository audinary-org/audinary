<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\AuthenticationService;
use App\Services\PasswordResetService;
use App\Repository\GlobalSettingsRepository;

final class AuthController
{
    private AuthenticationService $authService;
    private PasswordResetService $passwordResetService;

    public function __construct(
        AuthenticationService $authService,
        PasswordResetService $passwordResetService
    ) {
        $this->authService = $authService;
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createJsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new Exception('JSON encoding failed');
        }
        $response->getBody()->write($json);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonBody(Request $request): array
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    private function extractTokenFromHeader(string $header): ?string
    {
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Login endpoint
     */
    public function login(Request $request, Response $response): Response
    {
        try {
            $params = $this->getJsonBody($request);
            $username = trim($params['username'] ?? '');
            $password = trim($params['password'] ?? '');

            // Validate credentials
            $validationErrors = $this->authService->validateCredentials($username, $password);
            if ($validationErrors !== []) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validationErrors
                ], 400);
            }

            // Authenticate user
            $authToken = $this->authService->authenticate($username, $password);
            if (!$authToken instanceof \App\Models\AuthToken) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Invalid username or password'
                ], 401);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'token' => $authToken->getToken(),
                'user' => [
                    'user_id' => $authToken->getUserId(),
                    'id' => $authToken->getUserId(),
                    'username' => $authToken->getUsername(),
                    'display_name' => $authToken->getDisplayName() ?? $authToken->getUsername(),
                    'role' => $authToken->isAdmin() ? 'admin' : 'user',
                    'is_admin' => $authToken->isAdmin(),
                    'image_uuid' => $authToken->getImageUuid()
                ],
                'message' => 'Login successful'
            ]);
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Register endpoint
     */
    public function register(Request $request, Response $response): Response
    {
        try {
            $params = $this->getJsonBody($request);
            $username = trim($params['username'] ?? '');
            $password = trim($params['password'] ?? '');
            $displayName = trim($params['display_name'] ?? '');
            $email = trim($params['email'] ?? '');

            // Register user
            $result = $this->authService->register(
                $username,
                $password,
                $displayName !== '' && $displayName !== '0' ? $displayName : null,
                $email !== '' && $email !== '0' ? $email : null
            );

            if (!$result['success']) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Registration failed',
                    'errors' => $result['errors']
                ], 400);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'token' => $result['token'],
                'user' => $result['user'],
                'message' => 'Registration successful'
            ]);
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update profile endpoint
     */
    public function updateProfile(Request $request, Response $response): Response
    {
        try {
            $authHeader = $request->getHeaderLine('Authorization');
            $token = $this->extractTokenFromHeader($authHeader);

            if ($token === null || $token === '' || $token === '0') {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Authorization header missing'
                ], 401);
            }

            $authToken = $this->authService->verifyToken($token);
            if (!$authToken instanceof \App\Models\AuthToken) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            $userData = [
                'user_id' => $authToken->getUserId(),
                'username' => $authToken->getUsername(),
                'display_name' => $authToken->getDisplayName() ?? $authToken->getUsername(),
                'is_admin' => $authToken->isAdmin(),
                'image_uuid' => $authToken->getImageUuid()
            ];

            return $this->createJsonResponse($response, [
                'success' => true,
                ...$userData
            ]);
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get API version
     */
    public function getVersion(Request $request, Response $response): Response
    {
        try {
            $versionFile = __DIR__ . '/../../VERSION';
            $versionContent = file_exists($versionFile) ? file_get_contents($versionFile) : false;
            $version = $versionContent !== false ? trim($versionContent) : 'unknown';

            return $this->createJsonResponse($response, [
                'success' => true,
                'version' => $version,
                'server' => 'Audinary Music Server',
                'api_version' => '1.0'
            ]);
        } catch (Exception $e) {
            error_log("Version error: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get public configuration
     */
    public function getConfig(Request $request, Response $response): Response
    {
        try {
            $config = loadConfig();
            $versionFile = __DIR__ . '/../../VERSION';
            $versionContent = file_exists($versionFile) ? file_get_contents($versionFile) : false;
            $version = $versionContent !== false ? trim($versionContent) : 'unknown';

            $settingsRepo = new GlobalSettingsRepository();
            $wishlistEnabled = $settingsRepo->isWishlistEnabled();
            $lastfmApiKey = $settingsRepo->getLastfmApiKey();

            $publicConfig = [
                'success' => true,
                'app_name' => 'Audinary Music Server',
                'version' => $version,
                'registrationAllowed' => $config['registration']['enabled'] ?? true,
                'smtp_enabled' => !empty($config['smtp']['enabled']) &&
                    !empty($config['smtp']['host']) &&
                    !empty($config['smtp']['username']) &&
                    !empty($config['smtp']['password']),
                'wishlist' => [
                    'enabled' => $wishlistEnabled,
                    'lastfm_configured' => $lastfmApiKey !== null && $lastfmApiKey !== '' && $lastfmApiKey !== '0'
                ]
            ];

            $authStatus = $this->authService->getAuthStatus();
            return $this->createJsonResponse($response, $publicConfig + $authStatus);
        } catch (Exception $e) {
            error_log("Config error: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Request password reset
     */
    public function forgotPassword(Request $request, Response $response): Response
    {
        try {
            $params = $this->getJsonBody($request);
            $usernameOrEmail = trim($params['username_or_email'] ?? '');

            if ($usernameOrEmail === '' || $usernameOrEmail === '0') {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Username or email is required'
                ], 400);
            }

            $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1';

            $serverParams = $request->getServerParams();
            $scheme = !empty($serverParams['HTTPS']) && $serverParams['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $serverParams['HTTP_HOST'] ?? 'localhost';

            $baseUrl = $scheme . '://' . $host;

            $result = $this->passwordResetService->requestPasswordReset($usernameOrEmail, $clientIp, $baseUrl);

            $statusCode = $result['success'] ? 200 : 429;

            return $this->createJsonResponse($response, $result, $statusCode);
        } catch (Exception $e) {
            error_log("Forgot password error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Validate reset token
     */
    public function validateResetToken(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $token = $queryParams['token'] ?? '';

            if (empty($token)) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Token is required'
                ], 400);
            }

            $result = $this->passwordResetService->validateResetToken($token);

            $statusCode = $result['success'] ? 200 : 400;

            if ($result['success']) {
                $result = [
                    'success' => true,
                    'message' => 'Token is valid',
                    'expires_at' => $result['expires_at']
                ];
            }

            return $this->createJsonResponse($response, $result, $statusCode);
        } catch (Exception $e) {
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        try {
            $params = $this->getJsonBody($request);
            $token = trim($params['token'] ?? '');
            $newPassword = trim($params['new_password'] ?? '');

            if ($token === '' || $token === '0' || ($newPassword === '' || $newPassword === '0')) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Token and new password are required'
                ], 400);
            }

            if (strlen($newPassword) < 6) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Password must be at least 6 characters long'
                ], 400);
            }

            $result = $this->passwordResetService->resetPassword($token, $newPassword);
            $statusCode = $result['success'] ? 200 : 400;

            return $this->createJsonResponse($response, $result, $statusCode);
        } catch (Exception $e) {
            error_log("Reset password error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
