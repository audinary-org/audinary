<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database\Connection;
use App\Services\AuthenticationService;
use App\Services\EmailService;
use App\Services\PasswordResetService;
use App\Services\BackupService;
use App\Services\GlobalSettingsService;
use App\Services\StatsService;
use App\Repository\PlaylistRepository;
use App\Repository\SmartPlaylistRepository;
use App\Models\Playlist;
use Exception;
use PDO;
use Slim\Psr7\Stream;

final class AdminController
{
    /**
     * @param array<int|string, mixed> $data
     */
    private function safeJsonEncode(array $data): string
    {
        $json = json_encode($data);
        if ($json === false) {
            $fallback = json_encode(['error' => 'JSON encoding failed']);
            if ($fallback === false) {
                throw new Exception('Critical: JSON encoding failed');
            }
            return $fallback;
        }
        return $json;
    }

    /**
     * @param array<string, mixed>|list<mixed> $data
     */
    private function createJsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write($this->safeJsonEncode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function checkAdmin(Request $request, Response $response): ?Response
    {
        $authToken = $request->getAttribute('auth_token');
        if (!$authToken || !$authToken->isAdmin()) {
            return $this->createJsonResponse($response, ['error' => 'Unauthorized - Admin access required'], 403);
        }
        return null;
    }

    // =========================================================================
    // User Management Methods
    // =========================================================================

    public function listUsers(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();
        try {
            $stmt = $db->query("
                SELECT user_id, username, display_name, email, is_admin, created_at, last_login, image_uuid
                FROM users
                ORDER BY created_at DESC
            ");
            if ($stmt === false) {
                return $this->createJsonResponse($response, ['error' => 'Query failed'], 500);
            }
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->createJsonResponse($response, $users);
        } catch (Exception $e) {
            return $this->createJsonResponse($response, ['error' => 'Database error'], 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function getUser(Request $request, Response $response, array $args): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();
        $userId = $args['id'];

        try {
            $stmt = $db->prepare("
                SELECT user_id, username, display_name, email, is_admin, created_at, last_login, image_uuid
                FROM users
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return $this->createJsonResponse($response, ['error' => 'User not found'], 404);
            }

            return $this->createJsonResponse($response, $user);
        } catch (Exception $e) {
            return $this->createJsonResponse($response, ['error' => 'Database error'], 500);
        }
    }

    public function saveUser(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $authToken = $request->getAttribute('auth_token');
        $db = Connection::getPDO();
        $currentUserId = $authToken->getUserId();

        $parsedBody = $request->getParsedBody();
        $postParams = is_array($parsedBody) ? $parsedBody : $_POST;

        if (empty($postParams['username'])) {
            return $this->createJsonResponse($response, ['error' => 'Username is required'], 400);
        }

        $isUpdate = !empty($postParams['user_id']);
        $userId = $isUpdate ? $postParams['user_id'] : null;

        if ($isUpdate && $userId === $currentUserId && (isset($postParams['is_admin']) && !$postParams['is_admin'])) {
            return $this->createJsonResponse($response, ['error' => 'Du kannst deine eigenen Admin-Rechte nicht entfernen'], 403);
        }

        try {
            if ($isUpdate) {
                $passwordClause = "";
                $params = [
                    ':username' => $postParams['username'],
                    ':display_name' => $postParams['display_name'] ?? '',
                    ':email' => $postParams['email'] ?? '',
                    ':is_admin' => empty($postParams['is_admin']) ? 0 : 1,
                    ':id' => $userId
                ];

                if (!empty($postParams['password'])) {
                    if (strlen($postParams['password']) < 6) {
                        return $this->createJsonResponse($response, ['error' => 'Password must be at least 6 characters'], 400);
                    }
                    $password_hash = password_hash($postParams['password'], PASSWORD_BCRYPT);
                    $passwordClause = ", password_hash = :password_hash";
                    $params[':password_hash'] = $password_hash;
                }

                $stmt = $db->prepare("
                    UPDATE users
                    SET username = :username,
                        display_name = :display_name,
                        email = :email,
                        is_admin = :is_admin
                        $passwordClause
                    WHERE user_id = :id
                ");
                $stmt->execute($params);
            } else {
                if (empty($postParams['password'])) {
                    return $this->createJsonResponse($response, ['error' => 'Password is required for new users'], 400);
                }

                if (strlen($postParams['password']) < 6) {
                    return $this->createJsonResponse($response, ['error' => 'Password must be at least 6 characters'], 400);
                }

                $userId = generateUUID();

                $password_hash = password_hash($postParams['password'], PASSWORD_BCRYPT);

                $stmt = $db->prepare("
                    INSERT INTO users (user_id, username, display_name, email, password_hash, is_admin, created_at)
                    VALUES (:user_id, :username, :display_name, :email, :password_hash, :is_admin, NOW())
                ");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':username' => $postParams['username'],
                    ':display_name' => $postParams['display_name'] ?? '',
                    ':email' => $postParams['email'] ?? '',
                    ':password_hash' => $password_hash,
                    ':is_admin' => empty($postParams['is_admin']) ? 0 : 1
                ]);
            }

            // Handle profile image upload
            $uploadedFiles = $request->getUploadedFiles();
            if (isset($uploadedFiles['profileImage']) && $uploadedFiles['profileImage']->getError() === UPLOAD_ERR_OK) {
                $uploadedFile = $uploadedFiles['profileImage'];

                // Validate file extension
                $clientFilename = $uploadedFile->getClientFilename();
                $ext = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowedExtensions, true)) {
                    return $this->createJsonResponse($response, ['error' => 'Profile image must be JPG, PNG or WebP'], 400);
                }

                // Validate MIME type from actual file content
                $tempStream = $uploadedFile->getStream();
                $tempPath = $tempStream->getMetadata('uri');

                if ($tempPath !== null && file_exists($tempPath)) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($tempPath);
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                    if (!in_array($mimeType, $allowedMimes, true)) {
                        return $this->createJsonResponse($response, ['error' => 'Invalid image file type'], 400);
                    }
                }

                $config = loadConfig();
                $destDir = $config['profileDir'];
                $destPath = $destDir . '/' . $userId . '.webp';
                $tmpPath = $destDir . '/' . $userId . '_tmp.' . $ext;

                $uploadedFile->moveTo($tmpPath);

                if (convertImageToWebp200($tmpPath, $destPath, 200, 80)) {
                    $stmt = $db->prepare("UPDATE users SET image_uuid = :image_uuid WHERE user_id = :user_id");
                    $stmt->execute([':image_uuid' => $userId, ':user_id' => $userId]);
                }

                if (file_exists($tmpPath)) {
                    unlink($tmpPath);
                }
            }

            if (!empty($postParams['removeProfilePic'])) {
                $config = loadConfig();
                $destDir = $config['profileDir'];
                $currentImagePath = $destDir . '/' . $userId . '.webp';
                if (file_exists($currentImagePath)) {
                    unlink($currentImagePath);
                }
                $stmt = $db->prepare("UPDATE users SET image_uuid = NULL WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'message' => $isUpdate ? 'User updated successfully' : 'User created successfully',
                'user_id' => $userId
            ]);
        } catch (Exception $e) {
            error_log("AdminController::saveUser - Database error: " . $e->getMessage());
            return $this->createJsonResponse($response, ['error' => 'Failed to save user. Please try again.'], 500);
        }
    }

    public function createUser(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();
        $postParams = $request->getParsedBody() ?? $_POST;

        if (empty($postParams)) {
            $data = json_decode($request->getBody()->getContents(), true);
            $postParams = $data ?: [];
        }

        if (empty($postParams['username']) || empty($postParams['password'])) {
            return $this->createJsonResponse($response, ['error' => 'Username and password are required'], 400);
        }

        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $postParams['username']]);
        if ($stmt->fetch()) {
            return $this->createJsonResponse($response, ['error' => 'Username already taken'], 400);
        }

        $user_id = generateUUID();
        $password_hash = password_hash($postParams['password'], PASSWORD_BCRYPT);

        $stmt = $db->prepare("
            INSERT INTO users (user_id, username, password_hash, display_name, email, is_admin)
            VALUES (:id, :username, :password_hash, :display_name, :email, :is_admin)
        ");
        $stmt->execute([
            ':id' => $user_id,
            ':username' => $postParams['username'],
            ':password_hash' => $password_hash,
            ':display_name' => $postParams['display_name'] ?? '',
            ':email' => $postParams['email'] ?? '',
            ':is_admin' => empty($postParams['is_admin']) ? 0 : 1
        ]);

        $uploadedFiles = $request->getUploadedFiles();
        if (isset($uploadedFiles['profileImage']) && $uploadedFiles['profileImage']->getError() === UPLOAD_ERR_OK) {
            $profileImage = $uploadedFiles['profileImage'];
            $origName = $profileImage->getClientFilename();
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $config = loadConfig();
                $destDir = $config['profileDir'];
                $destPath = $destDir . '/' . $user_id . '.webp';
                $tmpPath = $destDir . '/' . $user_id . '_tmp.' . $ext;

                $profileImage->moveTo($tmpPath);

                if (convertImageToWebp200($tmpPath, $destPath, 200, 80)) {
                    $stmtPic = $db->prepare("UPDATE users SET image_uuid = :pic WHERE user_id = :id");
                    $stmtPic->execute([':pic' => $user_id, ':id' => $user_id]);
                }
                @unlink($tmpPath);
            }
        }

        return $this->createJsonResponse($response, ['success' => true, 'user_id' => $user_id]);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function updateUser(Request $request, Response $response, array $args): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $authToken = $request->getAttribute('auth_token');
        $db = Connection::getPDO();
        $userId = $args['id'];
        $currentUserId = $authToken->getUserId();

        $parsedBody = $request->getParsedBody();
        $postParams = is_array($parsedBody) ? $parsedBody : $_POST;

        if (empty($postParams['username'])) {
            return $this->createJsonResponse($response, ['error' => 'Username is required'], 400);
        }

        if ($userId === $currentUserId && (isset($postParams['is_admin']) && !$postParams['is_admin'])) {
            return $this->createJsonResponse($response, ['error' => 'Du kannst deine eigenen Admin-Rechte nicht entfernen'], 403);
        }

        $passwordClause = "";
        $params = [
            ':username' => $postParams['username'],
            ':display_name' => $postParams['display_name'] ?? '',
            ':email' => $postParams['email'] ?? '',
            ':is_admin' => empty($postParams['is_admin']) ? 0 : 1,
            ':id' => $userId
        ];

        if (!empty($postParams['password'])) {
            $password_hash = password_hash($postParams['password'], PASSWORD_BCRYPT);
            $passwordClause = ", password_hash = :password_hash";
            $params[':password_hash'] = $password_hash;
        }

        $stmt = $db->prepare("
            UPDATE users
            SET username = :username,
                display_name = :display_name,
                email = :email,
                is_admin = :is_admin
                $passwordClause
            WHERE user_id = :id
            LIMIT 1
        ");
        $stmt->execute($params);

        $uploadedFiles = $request->getUploadedFiles();
        if (isset($uploadedFiles['profileImage']) && $uploadedFiles['profileImage']->getError() === UPLOAD_ERR_OK) {
            $profileImage = $uploadedFiles['profileImage'];
            $origName = $profileImage->getClientFilename();
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $config = loadConfig();
                $destDir = $config['profileDir'];
                $destPath = $destDir . '/' . $userId . '.webp';
                $tmpPath = $destDir . '/' . $userId . '_tmp.' . $ext;

                $profileImage->moveTo($tmpPath);

                if (convertImageToWebp200($tmpPath, $destPath, 200, 80)) {
                    $stmtPic = $db->prepare("UPDATE users SET image_uuid = :pic WHERE user_id = :id");
                    $stmtPic->execute([':pic' => $userId, ':id' => $userId]);
                }
                @unlink($tmpPath);
            }
        }

        if (!empty($postParams['removeProfilePic'])) {
            $stmtPic = $db->prepare("UPDATE users SET image_uuid = NULL WHERE user_id = :id");
            $stmtPic->execute([':id' => $userId]);

            $config = loadConfig();
            $destDir = $config['profileDir'];
            $imagePath = $destDir . '/' . $userId . '.webp';
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }

        return $this->createJsonResponse($response, ['success' => true]);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $authToken = $request->getAttribute('auth_token');
        $db = Connection::getPDO();
        $userId = $args['id'];
        $currentUserId = $authToken->getUserId();

        if ($userId === $currentUserId) {
            return $this->createJsonResponse($response, ['error' => 'Du kannst dich nicht selbst löschen'], 403);
        }

        try {
            $config = loadConfig();
            $destDir = $config['profileDir'];
            $imagePath = $destDir . '/' . $userId . '.webp';
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            $stmt = $db->prepare("DELETE FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);

            if ($stmt->rowCount() === 0) {
                return $this->createJsonResponse($response, ['error' => 'User not found'], 404);
            }

            return $this->createJsonResponse($response, ['success' => true, 'message' => 'User deleted successfully']);
        } catch (Exception $e) {
            return $this->createJsonResponse($response, ['error' => 'Database error'], 500);
        }
    }

    // =========================================================================
    // Settings Methods
    // =========================================================================

    public function getGlobalSettings(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();
        $stmt = $db->query("SELECT setting_key, setting_value FROM global_settings");
        if ($stmt === false) {
            return $this->createJsonResponse($response, ['error' => 'Query failed'], 500);
        }
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $this->createJsonResponse($response, $settings);
    }

    public function updateGlobalSettings(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();
        $data = json_decode($request->getBody()->getContents(), true);
        if (!is_array($data)) {
            return $this->createJsonResponse($response, ['error' => 'Invalid settings data'], 400);
        }

        $stmt = $db->prepare("
            INSERT INTO global_settings (setting_key, setting_value)
            VALUES (:key, :value)
            ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value
        ");

        foreach ($data as $key => $value) {
            $stmt->execute([':key' => $key, ':value' => $value]);
        }

        return $this->createJsonResponse($response, ['success' => true]);
    }

    // =========================================================================
    // Scan Methods
    // =========================================================================

    public function getScanStatus(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();

        try {
            $cleanupStmt = $db->query("SELECT id, process_id, updated_at FROM scan_status WHERE status = 'running'");
            $runningScans = $cleanupStmt !== false ? $cleanupStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            foreach ($runningScans as $scan) {
                $processId = $scan['process_id'];
                $statusId = $scan['id'];
                $updatedAt = $scan['updated_at'];

                $isRunning = false;
                if ($processId) {
                    $updateTime = strtotime((string)$updatedAt);
                    $isRecent = (time() - $updateTime) < 120;

                    if ($isRecent) {
                        $isRunning = true;
                    } else {
                        // Validate process ID before using in shell command
                        $validatedPid = filter_var($processId, FILTER_VALIDATE_INT);
                        if ($validatedPid !== false && $validatedPid > 0) {
                            $output = [];
                            exec("ps -p " . escapeshellarg((string)$validatedPid) . " 2>/dev/null", $output);
                            $isRunning = $output !== [] && count($output) > 1;
                        }
                    }
                }

                $updateTime = strtotime((string)$updatedAt);
                $isStale = (time() - $updateTime) > 600;

                if (!$isRunning || $isStale) {
                    $reason = $isRunning ? "Scan timed out (older than 10 minutes)" : "Process $processId no longer exists";

                    $updateStmt = $db->prepare(
                        "UPDATE scan_status
                        SET status = 'error', error_message = :error_message, end_time = :end_time
                        WHERE id = :id"
                    );
                    if ($updateStmt !== false) {
                        $updateStmt->execute([
                            ':error_message' => $reason,
                            ':end_time' => time(),
                            ':id' => $statusId
                        ]);
                    }

                    error_log("Cleaned up hanging scan process: $reason");
                }
            }

            $stmt = $db->prepare(
                "SELECT status, option_name, full_scan, start_time, end_time,
                       total_albums, processed_albums, current_album, current_step,
                       duration, statistics, error_message, updated_at, process_id
                FROM scan_status
                WHERE status = 'running'
                ORDER BY id DESC
                LIMIT 1"
            );
            $status = false;
            if ($stmt !== false) {
                $stmt->execute();
                $status = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$status) {
                $stmt = $db->prepare(
                    "SELECT status, option_name, full_scan, start_time, end_time,
                           total_albums, processed_albums, current_album, current_step,
                           duration, statistics, error_message, updated_at, process_id
                    FROM scan_status
                    ORDER BY id DESC
                    LIMIT 1"
                );
                if ($stmt !== false) {
                    $stmt->execute();
                    $status = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($status) {
                if (!empty($status['statistics'])) {
                    $status['statistics'] = json_decode((string)$status['statistics'], true);
                }
                $status['full_scan'] = (bool)$status['full_scan'];
                $numericFields = ['start_time', 'end_time', 'total_albums', 'processed_albums', 'duration', 'process_id'];
                foreach ($numericFields as $field) {
                    if (isset($status[$field])) {
                        $status[$field] = (int)$status[$field];
                    }
                }
                return $this->createJsonResponse($response, $status);
            }
            return $this->createJsonResponse($response, ['status' => 'idle']);
        } catch (Exception $e) {
            error_log("Error fetching scan status: " . $e->getMessage());
            return $this->createJsonResponse($response, ['status' => 'idle', 'error' => 'Could not fetch status']);
        }
    }

    public function triggerMusicScan(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $option = $request->getQueryParams()['option'] ?? '';
        $fullScan = isset($request->getQueryParams()['full']) && $request->getQueryParams()['full'] === 'true';

        // Validate scan option against whitelist
        $allowedOptions = [
            'update-artist-image',
            'update-cover-images',
            'list-missing-artist-images',
            'list-missing-cover-images'
        ];

        if ($option !== '' && !in_array($option, $allowedOptions, true)) {
            return $this->createJsonResponse($response, ['error' => 'Invalid scan option'], 400);
        }

        $config = loadConfig();
        $logDir = $config['logDir'];

        $cliScript = escapeshellarg(__DIR__ . '/../../scripts/scan_media_data.php');

        // Build command with properly escaped arguments
        $optionFlag = '';
        if ($option !== '') {
            // Since we validated the option above, we know it's one of the allowed values
            $optionFlag = '--' . $option;
        } elseif ($fullScan) {
            $optionFlag = '--full';
        }

        $logFile = escapeshellarg($logDir . '/music_scanner-' . date('Y-m-d') . '.log');

        // Build command safely
        if ($optionFlag !== '') {
            $command = sprintf('php %s %s >> %s 2>&1 &', $cliScript, escapeshellarg($optionFlag), $logFile);
        } else {
            $command = sprintf('php %s >> %s 2>&1 &', $cliScript, $logFile);
        }

        exec($command);

        $message = match ($option) {
            'update-artist-image' => 'Künstlerbilder-Update wurde gestartet.',
            'update-cover-images' => 'Cover-Bilder-Update wurde gestartet.',
            'list-missing-artist-images' => 'Prüfung auf fehlende Künstlerbilder wurde gestartet.',
            'list-missing-cover-images' => 'Prüfung auf fehlende Cover-Bilder wurde gestartet.',
            default => $fullScan ? 'Vollständiger Scan wurde gestartet.' : 'Normaler Scan wurde gestartet.'
        };

        return $this->createJsonResponse($response, ['message' => $message]);
    }

    public function getScanLogs(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $config = loadConfig();
        $logDir = $config['logDir'];

        $position = (int)($request->getQueryParams()['position'] ?? 0);

        $logFile = $logDir . '/music_scanner-' . date('Y-m-d') . '.log';

        $result = [
            'logs' => [],
            'position' => $position,
            'file_exists' => false,
            'file_size' => 0
        ];

        if (file_exists($logFile)) {
            $result['file_exists'] = true;
            $fileSize = filesize($logFile);
            $result['file_size'] = $fileSize;

            if ($fileSize > $position) {
                $handle = fopen($logFile, 'r');
                if ($handle) {
                    fseek($handle, $position);
                    $readLen = (int)($fileSize - $position);
                    if ($readLen < 1) {
                        $readLen = 1;
                    }
                    $newContent = fread($handle, $readLen);
                    fclose($handle);

                    if ($newContent) {
                        $lines = explode("\n", $newContent);
                        $lines = array_filter($lines, fn($line): bool => trim($line) !== '');
                        $result['logs'] = array_values($lines);
                    }
                    $result['position'] = $fileSize;
                }
            }
        }

        return $this->createJsonResponse($response, $result);
    }

    // =========================================================================
    // Dashboard Statistics Methods
    // =========================================================================

    public function getDashboardStats(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();

        try {
            $stats = [];

            $stmt = $db->query("SELECT COUNT(*) as count FROM songs");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['totalSongs'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            $stmt = $db->query("SELECT COUNT(*) as count FROM albums");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['totalAlbums'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            $stmt = $db->query("SELECT COUNT(*) as count FROM artists");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['totalArtists'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            $stmt = $db->query("SELECT COUNT(*) as count FROM users");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['totalUsers'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            $stmt = $db->query("SELECT COUNT(*) as count FROM playlists");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['totalPlaylists'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['adminUsers'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            $stmt = $db->query("SELECT SUM(duration) as total FROM songs WHERE duration IS NOT NULL");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $totalDuration = $row && isset($row['total']) ? $row['total'] : 0;
            $stats['totalDuration'] = $totalDuration ? (int)$totalDuration : 0;

            $config = loadConfig();
            $musicDir = $config['musicDir'];
            $stats['musicDirSize'] = 0;
            if (is_dir($musicDir)) {
                $stats['musicDirSize'] = getDirSize($musicDir);
            }

            $stmt = $db->query("SELECT end_time FROM scan_status WHERE status = 'completed' ORDER BY id DESC LIMIT 1");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['lastScan'] = $row && isset($row['end_time']) ? date('c', (int)$row['end_time']) : null;

            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE created_at > NOW() - INTERVAL '7 days'");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['newUsersThisWeek'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            return $this->createJsonResponse($response, $stats);
        } catch (Exception $e) {
            error_log("Error getting admin stats: " . $e->getMessage());
            return $this->createJsonResponse($response, ['error' => 'Failed to load statistics'], 500);
        }
    }

    public function getTopGenres(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();

        try {
            $stmt = $db->query("
                SELECT genre, COUNT(*) as count
                FROM songs
                WHERE genre IS NOT NULL AND genre != ''
                GROUP BY genre
                ORDER BY count DESC
                LIMIT 10
            ");
            $genres = [];
            if ($stmt !== false) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $genres[] = [
                        'name' => $row['genre'],
                        'count' => (int)$row['count']
                    ];
                }
            }
            return $this->createJsonResponse($response, ['genres' => $genres]);
        } catch (Exception $e) {
            error_log("Error getting top genres: " . $e->getMessage());
            return $this->createJsonResponse($response, []);
        }
    }

    public function getRecentActivity(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();

        try {
            $activities = [];

            $stmt = $db->query("
                SELECT 'user_created' as type, username, created_at as timestamp
                FROM users
                WHERE created_at >= NOW() - INTERVAL '7 days'
                ORDER BY created_at DESC
                LIMIT 5
            ");
            if ($stmt !== false) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $activities[] = [
                        'id' => 'user_' . uniqid(),
                        'type' => 'user_created',
                        'description' => "Neuer Benutzer '{$row['username']}' erstellt",
                        'timestamp' => date('c', strtotime($row['timestamp']))
                    ];
                }
            }

            $stmt = $db->query("
                SELECT status, option_name, start_time, end_time, statistics
                FROM scan_status
                WHERE start_time >= EXTRACT(EPOCH FROM NOW() - INTERVAL '7 days')::int
                ORDER BY start_time DESC
                LIMIT 5
            ");
            if ($stmt !== false) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $description = "Musik-Scan " . ($row['status'] == 'completed' ? 'abgeschlossen' : $row['status']);
                    if (!empty($row['option_name']) && $row['option_name'] != 'normal') {
                        $description .= " ({$row['option_name']})";
                    }

                    $end = $row['end_time'] ?: $row['start_time'];
                    $activities[] = [
                        'id' => 'scan_' . $row['start_time'],
                        'type' => 'scan_' . $row['status'],
                        'description' => $description,
                        'timestamp' => date('c', (int)$end)
                    ];
                }
            }

            $stmt = $db->query("
                SELECT p.name, u.username, p.created_at
                FROM playlists p
                JOIN users u ON p.user_id = u.user_id
                WHERE p.created_at >= NOW() - INTERVAL '7 days'
                ORDER BY p.created_at DESC
                LIMIT 3
            ");
            if ($stmt !== false) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $activities[] = [
                        'id' => 'playlist_' . uniqid(),
                        'type' => 'playlist_created',
                        'description' => "Playlist '{$row['name']}' von {$row['username']} erstellt",
                        'timestamp' => date('c', strtotime($row['created_at']))
                    ];
                }
            }

            usort($activities, fn(array $a, array $b): int => strtotime($b['timestamp']) - strtotime($a['timestamp']));

            $activities = array_slice($activities, 0, 10);

            return $this->createJsonResponse($response, ['activities' => $activities]);
        } catch (Exception $e) {
            error_log("Error getting recent activity: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                [
                    'id' => 1,
                    'type' => 'system',
                    'description' => 'System gestartet',
                    'timestamp' => date('c')
                ]
            ]);
        }
    }

    public function getSystemInfo(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $systemInfo = [
                'phpVersion' => PHP_VERSION,
                'uptime' => 'N/A',
                'memoryUsage' => 'N/A',
                'diskSpace' => 'N/A'
            ];

            if (function_exists('sys_getloadavg') && is_readable('/proc/uptime')) {
                $uptime = file_get_contents('/proc/uptime');
                if ($uptime) {
                    $uptimeSeconds = (int)floatval(explode(' ', $uptime)[0]);
                    $days = floor($uptimeSeconds / 86400);
                    $hours = floor(($uptimeSeconds % 86400) / 3600);
                    $systemInfo['uptime'] = "{$days} Tage, {$hours} Stunden";
                }
            }

            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            $systemInfo['memoryUsage'] = formatBytes($memoryUsage) . ' / ' . $memoryLimit;

            $config = loadConfig();
            $musicDir = $config['musicDir'] ?? '/var/www/html/server/var/music';
            if (is_dir($musicDir)) {
                $diskTotal = disk_total_space($musicDir);
                $diskFree = disk_free_space($musicDir);
                $diskUsed = $diskTotal - $diskFree;
                $systemInfo['diskSpace'] = formatBytes((int)$diskUsed) . ' / ' . formatBytes((int)$diskTotal);
            }

            return $this->createJsonResponse($response, $systemInfo);
        } catch (Exception $e) {
            error_log("Error getting system info: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'phpVersion' => PHP_VERSION,
                'uptime' => 'N/A',
                'memoryUsage' => 'N/A',
                'diskSpace' => 'N/A'
            ]);
        }
    }

    public function getListeningStats(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();

        try {
            $stmt = $db->query("
                SELECT
                    DATE(played_at) as play_date,
                    COUNT(*) as play_count
                FROM play_history
                WHERE played_at >= NOW() - INTERVAL '7 days'
                GROUP BY DATE(played_at)
                ORDER BY play_date ASC
            ");

            $playData = [];
            $playStmt = $stmt;
            if ($playStmt !== false) {
                while ($row = $playStmt->fetch(PDO::FETCH_ASSOC)) {
                    $playData[$row['play_date']] = (int)$row['play_count'];
                }
            }

            $labels = [];
            $data = [];
            $weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $ts = strtotime($date) ?: 0;
                $dayOfWeek = $weekdays[(int)date('w', $ts)];
                $labels[] = $dayOfWeek;
                $data[] = $playData[$date] ?? 0;
            }

            $chartData = [
                'labels' => $labels,
                'data' => $data
            ];

            return $this->createJsonResponse($response, $chartData);
        } catch (Exception $e) {
            error_log("Error getting listening stats: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'labels' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
                'data' => [45, 120, 190, 300, 500, 200, 150]
            ]);
        }
    }

    public function getLibraryStats(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $db = Connection::getPDO();

        try {
            $stats = [];

            $stmt = $db->query("SELECT COUNT(*) as count FROM songs");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['songs'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            $stmt = $db->query("SELECT COUNT(*) as count FROM albums");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['albums'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            $stmt = $db->query("SELECT COUNT(*) as count FROM artists");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['artists'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            $stmt = $db->query("SELECT COUNT(DISTINCT genre) as count FROM songs WHERE genre IS NOT NULL AND genre != ''");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $stats['genres'] = $row && isset($row['count']) ? (int)$row['count'] : 0;

            return $this->createJsonResponse($response, $stats);
        } catch (Exception $e) {
            error_log("Error getting library stats: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'songs' => 0,
                'albums' => 0,
                'artists' => 0,
                'genres' => 0
            ]);
        }
    }

    public function getPasswordResetStats(Request $request, Response $response): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $token = $this->extractTokenFromHeader($authHeader);

        if ($token === null || $token === '' || $token === '0') {
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Authorization header missing'
            ], 401);
        }

        try {
            $config = loadConfig();

            $authService = new AuthenticationService(
                jwtSecret: $config['jwtSecret'] ?? 'your-secret-key-here',
                jwtIssuer: 'audinary',
                jwtExpirationTime: 31536000
            );

            $authToken = $authService->verifyToken($token);
            if (!$authToken instanceof \App\Models\AuthToken || !$authToken->isAdmin()) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
            }

            $emailService = new EmailService($config['smtp'] ?? []);
            $passwordResetService = new PasswordResetService(
                Connection::getPDO(),
                $emailService,
                $config
            );

            $stats = $passwordResetService->getTokenStats();
            $stats['smtp_enabled'] = $emailService->isEnabled();
            $stats['smtp_config'] = [
                'host' => !empty($config['smtp']['host']),
                'username' => !empty($config['smtp']['username']),
                'password' => !empty($config['smtp']['password']),
                'from_email' => !empty($config['smtp']['from_email'])
            ];

            return $this->createJsonResponse($response, ['success' => true, ...$stats]);
        } catch (Exception $e) {
            error_log("Error getting password reset stats: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    private function extractTokenFromHeader(string $header): ?string
    {
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    // =========================================================================
    // Backup Methods
    // =========================================================================

    public function listBackups(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $config = loadConfig();
            $backupService = new BackupService($config);
            $backups = $backupService->listBackups();

            return $this->createJsonResponse($response, ['success' => true, 'backups' => $backups]);
        } catch (Exception $e) {
            error_log("Error listing backups: " . $e->getMessage());
            return $this->createJsonResponse($response, ['error' => 'Failed to list backups: ' . $e->getMessage()], 500);
        }
    }

    public function createBackup(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $config = loadConfig();
            $backupService = new BackupService($config);
            $result = $backupService->createBackup();

            $statusCode = $result['success'] ? 200 : 500;
            return $this->createJsonResponse($response, $result, $statusCode);
        } catch (Exception $e) {
            error_log("Error creating backup: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Failed to create backup: ' . $e->getMessage()
            ], 500);
        }
    }

    public function restoreBackup(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($data['filename']) || empty($data['filename'])) {
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Backup filename is required'
            ], 400);
        }

        try {
            $config = loadConfig();
            $backupService = new BackupService($config);
            $result = $backupService->restoreBackup($data['filename']);

            $statusCode = $result['success'] ? 200 : 500;
            return $this->createJsonResponse($response, $result, $statusCode);
        } catch (Exception $e) {
            error_log("Error restoring backup: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Failed to restore backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function deleteBackup(Request $request, Response $response, array $args): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $filename = $args['filename'] ?? '';

        if (empty($filename)) {
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Backup filename is required'
            ], 400);
        }

        try {
            $config = loadConfig();
            $backupService = new BackupService($config);
            $result = $backupService->deleteBackup($filename);

            $statusCode = $result['success'] ? 200 : 500;
            return $this->createJsonResponse($response, $result, $statusCode);
        } catch (Exception $e) {
            error_log("Error deleting backup: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Failed to delete backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function downloadBackup(Request $request, Response $response, array $args): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $filename = $args['filename'] ?? '';

        if (empty($filename)) {
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Backup filename is required'
            ], 400);
        }

        // Validate backup filename format
        if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/', $filename) !== 1) {
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Invalid backup filename format'
            ], 400);
        }

        // Prevent path traversal
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Invalid filename - path traversal detected'
            ], 400);
        }

        try {
            $config = loadConfig();
            $backupPath = $config['backupDir'] . '/' . $filename;

            if (!file_exists($backupPath)) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            $fileSize = filesize($backupPath);
            $stream = fopen($backupPath, 'rb');

            if (!$stream) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Failed to open backup file'
                ], 500);
            }

            return $response
                ->withHeader('Content-Type', 'application/gzip')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Content-Length', (string)$fileSize)
                ->withHeader('Cache-Control', 'no-cache')
                ->withBody(new Stream($stream));
        } catch (Exception $e) {
            error_log("Error downloading backup: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Failed to download backup: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadBackup(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $uploadedFiles = $request->getUploadedFiles();

            if (!isset($uploadedFiles['backup']) || !$uploadedFiles['backup']) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'No backup file uploaded'
                ], 400);
            }

            $uploadedFile = $uploadedFiles['backup'];

            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'File upload error: ' . $uploadedFile->getError()
                ], 400);
            }

            $originalFilename = $uploadedFile->getClientFilename();

            // Validate backup filename format
            if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/', $originalFilename) !== 1) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Invalid backup file format. Expected format: backup_YYYY-MM-DD_HH-MM-SS.tar.gz'
                ], 400);
            }

            // Prevent path traversal
            if (strpos($originalFilename, '..') !== false || strpos($originalFilename, '/') !== false || strpos($originalFilename, '\\') !== false) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Invalid filename - path traversal detected'
                ], 400);
            }

            $maxSize = 2 * 1024 * 1024 * 1024; // 2GB
            if ($uploadedFile->getSize() > $maxSize) {
                return $this->createJsonResponse($response, [
                    'success' => false,
                    'message' => 'Backup file too large. Maximum size: 2GB'
                ], 400);
            }

            $config = loadConfig();
            $backupService = new BackupService($config);
            $result = $backupService->uploadBackup($uploadedFile, $originalFilename);

            $statusCode = $result['success'] ? 200 : 500;
            return $this->createJsonResponse($response, $result, $statusCode);
        } catch (Exception $e) {
            error_log("Error uploading backup: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Failed to upload backup: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // Stats Sharing Methods
    // =========================================================================

    public function getStatsSharingConfig(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $db = Connection::getPDO();
            $globalSettingsService = new GlobalSettingsService($db);
            $statsService = new StatsService($db, $globalSettingsService);

            $config = $statsService->getStatsConfig();

            return $this->createJsonResponse($response, ['success' => true, 'config' => $config]);
        } catch (Exception $e) {
            error_log("Error getting stats sharing config: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Failed to get stats sharing configuration'
            ], 500);
        }
    }

    public function updateStatsSharingConfig(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        $data = json_decode($request->getBody()->getContents(), true);
        if (!is_array($data)) {
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Invalid configuration data'
            ], 400);
        }

        try {
            $db = Connection::getPDO();
            $globalSettingsService = new GlobalSettingsService($db);
            $statsService = new StatsService($db, $globalSettingsService);

            $success = $statsService->setStatsConfig($data);

            if ($success) {
                return $this->createJsonResponse($response, [
                    'success' => true,
                    'message' => 'Stats sharing configuration updated successfully'
                ]);
            }
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Failed to update configuration. Please check instance ID format (8-64 alphanumeric characters and hyphens only)'
            ], 400);
        } catch (Exception $e) {
            error_log("Error updating stats sharing config: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Failed to update stats sharing configuration'
            ], 500);
        }
    }

    public function previewStats(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $db = Connection::getPDO();
            $globalSettingsService = new GlobalSettingsService($db);
            $statsService = new StatsService($db, $globalSettingsService);

            $preview = $statsService->getStatsPreview();

            return $this->createJsonResponse($response, $preview);
        } catch (Exception $e) {
            error_log("Error getting stats preview: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Failed to generate stats preview'
            ], 500);
        }
    }

    public function sendStats(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $db = Connection::getPDO();
            $globalSettingsService = new GlobalSettingsService($db);
            $statsService = new StatsService($db, $globalSettingsService);

            $result = $statsService->sendStats();

            $statusCode = $result['success'] ? 200 : 400;
            return $this->createJsonResponse($response, $result, $statusCode);
        } catch (Exception $e) {
            error_log("Error sending stats: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'success' => false,
                'message' => 'Failed to send stats'
            ], 500);
        }
    }

    // =========================================================================
    // Smart Playlist Admin Methods
    // =========================================================================

    public function getSmartPlaylists(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $authToken = $request->getAttribute('auth_token');
            $userId = $authToken->getUserId();
            $playlistRepo = new PlaylistRepository($userId);
            $smartRepo = new SmartPlaylistRepository();
            $playlists = $playlistRepo->findAllSmartAdmin();

            $result = [];
            foreach ($playlists as $playlist) {
                $data = $playlist->toArray();
                $rules = $playlist->getRules();
                if (is_array($rules) && !empty($rules['conditions'])) {
                    $stats = $smartRepo->getSmartPlaylistStats($rules, $userId, $playlist->getSmartLimit());
                    $data['song_count'] = $stats['song_count'];
                    $data['duration'] = $stats['duration'];
                }
                $result[] = $data;
            }

            return $this->createJsonResponse($response, ['success' => true, 'playlists' => $result]);
        } catch (Exception $e) {
            error_log("Error getting smart playlists: " . $e->getMessage());
            return $this->createJsonResponse($response, ['error' => 'Failed to fetch smart playlists'], 500);
        }
    }

    public function createSmartPlaylist(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $data = json_decode($request->getBody()->getContents(), true);

            if (!is_array($data) || empty($data['name'])) {
                return $this->createJsonResponse($response, ['error' => 'Playlist name is required'], 400);
            }

            if (!isset($data['rules']) || !is_array($data['rules']) || empty($data['rules']['conditions'])) {
                return $this->createJsonResponse($response, ['error' => 'At least one rule is required'], 400);
            }

            $authToken = $request->getAttribute('auth_token');
            $data['user_id'] = $authToken->getUserId();
            $data['type'] = 'smart';

            $playlistRepo = new PlaylistRepository($authToken->getUserId());
            $playlist = $playlistRepo->create($data);

            return $this->createJsonResponse($response, [
                'success' => true,
                'playlist' => $playlist->toArray()
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->createJsonResponse($response, ['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            error_log("Error creating smart playlist: " . $e->getMessage());
            return $this->createJsonResponse($response, ['error' => 'Failed to create smart playlist'], 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function updateSmartPlaylist(Request $request, Response $response, array $args): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $playlistId = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);

            if (!is_array($data)) {
                return $this->createJsonResponse($response, ['error' => 'Invalid request data'], 400);
            }

            $authToken = $request->getAttribute('auth_token');
            $playlistRepo = new PlaylistRepository($authToken->getUserId());
            $playlist = $playlistRepo->update($playlistId, $data);

            if (!$playlist instanceof Playlist) {
                return $this->createJsonResponse($response, ['error' => 'Smart playlist not found'], 404);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'playlist' => $playlist->toArray()
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->createJsonResponse($response, ['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            error_log("Error updating smart playlist: " . $e->getMessage());
            return $this->createJsonResponse($response, ['error' => 'Failed to update smart playlist'], 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function deleteSmartPlaylist(Request $request, Response $response, array $args): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $playlistId = $args['id'];
            $playlistRepo = new PlaylistRepository();
            $deleted = $playlistRepo->delete($playlistId);

            if (!$deleted) {
                return $this->createJsonResponse($response, ['error' => 'Smart playlist not found'], 404);
            }

            return $this->createJsonResponse($response, ['success' => true]);
        } catch (Exception $e) {
            error_log("Error deleting smart playlist: " . $e->getMessage());
            return $this->createJsonResponse($response, ['error' => 'Failed to delete smart playlist'], 500);
        }
    }

    public function previewSmartPlaylist(Request $request, Response $response): Response
    {
        if (($error = $this->checkAdmin($request, $response)) instanceof \Psr\Http\Message\ResponseInterface) {
            return $error;
        }

        try {
            $data = json_decode($request->getBody()->getContents(), true);

            if (!is_array($data) || !isset($data['rules']) || !is_array($data['rules'])) {
                return $this->createJsonResponse($response, ['error' => 'Rules are required'], 400);
            }

            $authToken = $request->getAttribute('auth_token');
            $userId = $authToken->getUserId();
            $smartRepo = new SmartPlaylistRepository();
            $previewLimit = isset($data['smart_limit']) && $data['smart_limit'] > 0 ? (int) $data['smart_limit'] : null;
            $stats = $smartRepo->getSmartPlaylistStats($data['rules'], $userId, $previewLimit);

            return $this->createJsonResponse($response, [
                'success' => true,
                'song_count' => $stats['song_count'],
                'duration' => $stats['duration'],
            ]);
        } catch (Exception $e) {
            error_log("Error previewing smart playlist: " . $e->getMessage());
            return $this->createJsonResponse($response, ['error' => 'Failed to preview smart playlist'], 500);
        }
    }
}
