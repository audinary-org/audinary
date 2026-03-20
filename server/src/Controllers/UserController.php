<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Repository\UserRepository;
use App\Repository\UserSettingsRepository;
use App\Repository\GlobalSettingsRepository;
use App\Services\StatsService;
use App\Services\GlobalSettingsService;
use App\Database\Connection;

final class UserController
{
    private UserRepository $userRepo;
    private UserSettingsRepository $settingsRepo;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
        $this->settingsRepo = new UserSettingsRepository();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createJsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON');
        }
        $response->getBody()->write($json);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function createErrorResponse(Response $response, string $message, int $status = 400): Response
    {
        return $this->createJsonResponse($response, ['error' => $message], $status);
    }

    /**
     * Get current user profile
     */
    public function getProfile(Request $request, Response $response): Response
    {
        $authToken = $request->getAttribute('auth_token');

        if (!$authToken) {
            return $this->createErrorResponse($response, 'Authentication required', 401);
        }

        $userId = $authToken->getUserId();

        try {
            $user = $this->userRepo->findById($userId);

            if (!$user instanceof \App\Models\User) {
                // Fallback to JWT data if user not found in database
                $userData = [
                    'user_id' => $authToken->getUserId(),
                    'username' => $authToken->getUsername(),
                    'display_name' => $authToken->getDisplayName() ?? $authToken->getUsername(),
                    'is_admin' => $authToken->isAdmin(),
                    'role' => $authToken->isAdmin() ? 'admin' : 'user',
                    'profileImage' => null,
                    'image_uuid' => null,
                    'created_at' => $authToken->getIssuedAt()->format('Y-m-d H:i:s')
                ];
            } else {
                // Return combined JWT + database data
                $userData = [
                    'user_id' => $user->getUserId(),
                    'username' => $user->getUsername(),
                    'display_name' => $user->getDisplayName() ?? $user->getUsername(),
                    'is_admin' => $user->isAdmin(),
                    'role' => $user->isAdmin() ? 'admin' : 'user',
                    'profileImage' => $user->getImageUuid(),
                    'image_uuid' => $user->getImageUuid(),
                    'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s') ?? $authToken->getIssuedAt()->format('Y-m-d H:i:s')
                ];
            }

            // Auto-send stats if enabled and appropriate (non-blocking)
            try {
                $db = Connection::getPDO();
                $globalSettingsService = new GlobalSettingsService($db);
                $statsService = new StatsService($db, $globalSettingsService);
                $statsService->tryAutoSendStats();
            } catch (Exception $e) {
                // Don't let stats sending interfere with user profile loading
                error_log("Stats auto-send error: " . $e->getMessage());
            }

            return $this->createJsonResponse($response, $userData);
        } catch (Exception $e) {
            error_log("Error fetching user profile: " . $e->getMessage());
            // Fallback to JWT data on error
            $userData = [
                'user_id' => $authToken->getUserId(),
                'username' => $authToken->getUsername(),
                'display_name' => $authToken->getDisplayName() ?? $authToken->getUsername(),
                'is_admin' => $authToken->isAdmin(),
                'role' => $authToken->isAdmin() ? 'admin' : 'user',
                'profileImage' => null,
                'image_uuid' => null,
                'created_at' => $authToken->getIssuedAt()->format('Y-m-d H:i:s')
            ];
            return $this->createJsonResponse($response, $userData);
        }
    }

    /**
     * Get user settings
     */
    public function getSettings(Request $request, Response $response): Response
    {
        $authToken = $request->getAttribute('auth_token');
        $userId = $authToken->getUserId();

        try {
            $user = $this->userRepo->findById($userId);
            if (!$user instanceof \App\Models\User) {
                return $this->createErrorResponse($response, 'User not found', 404);
            }

            // Get user settings
            $settings = $this->settingsRepo->getAllUserSettings($userId);

            // Load MPD enabled from config
            $config = loadConfig();
            $mpdEnabled = $config['mpd']['enabled'] ? 'true' : 'false';

            $data = [
                'username' => $user->getUsername(),
                'displayName' => $user->getDisplayName(),
                'profileImage' => $user->getImageUuid(),
                'transcoding_quality' => $settings['transcoding_quality'] ?? 'medium',
                'transcoding_enabled' => $settings['transcoding_enabled'] ?? '0',
                'transcoding_format' => $settings['transcoding_format'] ?? 'aac',
                'transcoding_mode' => $settings['transcoding_mode'] ?? 'cbr',
                'mpdEnabled' => $mpdEnabled,
                'sessionTimeout' => $settings['session_timeout'] ?? '31536000',
                'language' => $settings['language'] ?? 'auto'
            ];

            return $this->createJsonResponse($response, $data);
        } catch (Exception $e) {
            error_log("Error fetching user settings: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to fetch settings', 500);
        }
    }

    /**
     * Update user settings
     */
    public function updateSettings(Request $request, Response $response): Response
    {
        $authToken = $request->getAttribute('auth_token');
        $userId = $authToken->getUserId();

        $db = Connection::getPDO();
        $db->beginTransaction();

        try {
            // Get POST parameters
            $postParams = $request->getParsedBody() ?? $_POST;
            if (!is_array($postParams)) {
                $postParams = [];
            }

            $username = isset($postParams['username']) ? trim((string)$postParams['username']) : null;
            $displayName = isset($postParams['displayName']) ? trim((string)$postParams['displayName']) : null;
            $newPassword = trim((string)($postParams['newPassword'] ?? ''));
            $removePic = !empty($postParams['removeProfilePic']);

            // Validate username if provided
            if ($username !== null && $username === '') {
                return $this->createErrorResponse($response, 'Username cannot be empty', 400);
            }

            // Get user
            $user = $this->userRepo->findById($userId);
            if (!$user instanceof \App\Models\User) {
                return $this->createErrorResponse($response, 'User not found', 404);
            }

            // Check if username is already taken
            if ($username !== null && $username !== $user->getUsername() && $this->userRepo->usernameExists($username)) {
                return $this->createErrorResponse($response, 'That username is already taken', 400);
            }

            // Update user fields
            $sqlParts = [];
            $params = [':id' => $userId];

            if ($username !== null) {
                $sqlParts[] = "username = :un";
                $params[':un'] = $username;
            }

            if ($displayName !== null) {
                $sqlParts[] = "display_name = :dn";
                $params[':dn'] = $displayName;
            }

            if ($removePic) {
                $sqlParts[] = "image_uuid = NULL";
            }

            $passwordChanged = false;
            if ($newPassword !== '') {
                $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $sqlParts[] = "password_hash = :ph";
                $params[':ph'] = $passwordHash;
                $passwordChanged = true;
            }

            // Execute user table update if there are changes
            if ($sqlParts !== []) {
                $sql = "UPDATE users SET " . implode(', ', $sqlParts) . " WHERE user_id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }

            // Handle profile image upload
            $uploadedFiles = $request->getUploadedFiles();
            if (isset($uploadedFiles['profileImage']) && $uploadedFiles['profileImage']->getError() === UPLOAD_ERR_OK) {
                $profileImage = $uploadedFiles['profileImage'];
                $origName = $profileImage->getClientFilename();
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    return $this->createErrorResponse($response, 'Profile image must be JPG or PNG', 400);
                }

                $config = loadConfig();
                $destDir = $config['profileDir'];
                $destPath = $destDir . '/' . $userId . '.webp';
                $tmpPath = $destDir . '/' . $userId . '_tmp.' . $ext;

                $profileImage->moveTo($tmpPath);

                if (!convertImageToWebp200($tmpPath, $destPath, 200, 80)) {
                    return $this->createErrorResponse($response, 'Failed to convert profile image to WebP', 500);
                }
                unlink($tmpPath);

                $stmt = $db->prepare("UPDATE users SET image_uuid = :pic WHERE user_id = :id");
                $stmt->execute([':pic' => $userId, ':id' => $userId]);
            }

            // Update transcoding settings
            $transcodingSettings = [];

            if (isset($postParams['transcoding_enabled'])) {
                $transcodingSettings['transcoding_enabled'] = trim($postParams['transcoding_enabled']);
            }

            if (isset($postParams['transcoding_quality'])) {
                $quality = strtolower(trim($postParams['transcoding_quality']));
                $validQualities = ['low', 'medium', 'high', 'very_high', 'lossless'];
                $transcodingSettings['transcoding_quality'] = in_array($quality, $validQualities) ? $quality : 'medium';
            }

            if (isset($postParams['transcoding_format'])) {
                $format = strtolower(trim($postParams['transcoding_format']));
                $validFormats = ['aac', 'flac'];
                $transcodingSettings['transcoding_format'] = in_array($format, $validFormats) ? $format : 'aac';
            }

            if (isset($postParams['transcoding_mode'])) {
                $mode = strtolower(trim($postParams['transcoding_mode']));
                $validModes = ['cbr', 'vbr'];
                $transcodingSettings['transcoding_mode'] = in_array($mode, $validModes) ? $mode : 'cbr';
            }

            if ($transcodingSettings !== []) {
                $this->settingsRepo->setUserSettings($userId, $transcodingSettings, false);
            }

            // Update session timeout
            if (isset($postParams['sessionTimeout'])) {
                $this->settingsRepo->setUserSetting($userId, 'session_timeout', $postParams['sessionTimeout']);
            }

            // Update language preference
            if (isset($postParams['language'])) {
                $language = trim($postParams['language']);
                $validLanguages = ['auto', 'de', 'en', 'fr', 'ru'];
                $language = in_array($language, $validLanguages) ? $language : 'auto';
                $this->settingsRepo->setUserSetting($userId, 'language', $language);
            }

            $db->commit();

            // If password changed, require re-login
            if ($passwordChanged) {
                return $this->createJsonResponse($response, [
                    'success' => true,
                    'message' => 'Settings saved. Password changed; please re-login.',
                    'logout' => true
                ]);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'message' => 'Settings saved.'
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error updating user settings: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to save settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get available users (for playlist sharing)
     */
    public function getAvailableUsers(Request $request, Response $response): Response
    {
        $authToken = $request->getAttribute('auth_token');
        $currentUserId = $authToken->getUserId();

        try {
            $db = Connection::getPDO();
            $stmt = $db->prepare("
                SELECT
                    user_id,
                    username,
                    display_name,
                    created_at
                FROM users
                WHERE user_id != :current_user_id
                ORDER BY username ASC
                LIMIT 5
            ");
            $stmt->execute([':current_user_id' => $currentUserId]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->createJsonResponse($response, [
                'success' => true,
                'users' => $users
            ]);
        } catch (Exception $e) {
            error_log("Error loading users: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to load users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search users by username or display name
     */
    public function searchUsers(Request $request, Response $response): Response
    {
        $authToken = $request->getAttribute('auth_token');
        $currentUserId = $authToken->getUserId();

        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';

        if (strlen(trim($query)) < 2) {
            return $this->createErrorResponse($response, 'Query must be at least 2 characters long', 400);
        }

        try {
            $db = Connection::getPDO();
            $searchTerm = '%' . trim($query) . '%';
            $stmt = $db->prepare("
                SELECT
                    user_id,
                    username,
                    display_name,
                    created_at
                FROM users
                WHERE (username LIKE :search_term1 OR display_name LIKE :search_term2)
                AND user_id != :current_user_id
                ORDER BY username ASC
                LIMIT 20
            ");
            $stmt->execute([
                ':search_term1' => $searchTerm,
                ':search_term2' => $searchTerm,
                ':current_user_id' => $currentUserId
            ]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->createJsonResponse($response, [
                'success' => true,
                'users' => $users
            ]);
        } catch (Exception $e) {
            error_log("Error searching users: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to search users: ' . $e->getMessage(), 500);
        }
    }
}
