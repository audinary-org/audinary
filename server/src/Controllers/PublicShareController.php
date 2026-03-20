<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Repository\PublicShareRepository;
use App\Services\PublicShareService;
use App\Repository\UserRepository;
use InvalidArgumentException;
use Exception;

final class PublicShareController
{
    private PublicShareRepository $publicShareRepository;
    private PublicShareService $publicShareService;
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->publicShareRepository = new PublicShareRepository();
        $this->publicShareService = new PublicShareService();
        $this->userRepository = new UserRepository();
    }

    public function createShare(Request $request, Response $response): Response
    {
        try {
            $user = $this->getUserFromRequest($request);
            if ($user === null || $user === []) {
                return $this->createErrorResponse($response, 'Unauthorized', 401);
            }

            if (!$this->canCreateShare($user)) {
                return $this->createErrorResponse($response, 'No permission to create public shares', 403);
            }

            $data = json_decode($request->getBody()->getContents(), true);
            if (!is_array($data)) {
                return $this->createErrorResponse($response, 'Invalid JSON data', 400);
            }

            $requiredFields = ['type', 'item_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || in_array(trim($data[$field]), ['', '0'], true)) {
                    return $this->createErrorResponse($response, "Missing required field: $field", 400);
                }
            }

            // Set the creator user ID
            $data['created_by'] = $user['user_id'] ?? $user['id'] ?? null;

            if (!$data['created_by']) {
                return $this->createErrorResponse($response, 'Unable to determine user ID', 500);
            }

            // Only admin can enable downloads by default
            if (!$user['is_admin'] && isset($data['download_enabled'])) {
                $data['download_enabled'] = false;
            }

            $share = $this->publicShareService->createShare($data);

            return $this->createJsonResponse($response, [
                'success' => true,
                'share' => $share->toArray()
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            error_log("Error creating public share: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function getShare(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $this->getUserFromRequest($request);
            if ($user === null || $user === []) {
                return $this->createErrorResponse($response, 'Unauthorized', 401);
            }

            $shareId = $args['id'];
            $share = $this->publicShareRepository->findById($shareId);

            if (!$share instanceof \App\Models\PublicShare) {
                return $this->createErrorResponse($response, 'Share not found', 404);
            }

            if (!$this->canManageShare($user, $share)) {
                return $this->createErrorResponse($response, 'No permission to access this share', 403);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'share' => $share->toArray()
            ]);
        } catch (Exception $e) {
            error_log("Error fetching share: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error', 500);
        }
    }

    public function getUserShares(Request $request, Response $response): Response
    {
        try {
            $user = $this->getUserFromRequest($request);
            if ($user === null || $user === []) {
                return $this->createErrorResponse($response, 'Unauthorized', 401);
            }

            $queryParams = $request->getQueryParams();
            $offset = (int) ($queryParams['offset'] ?? 0);
            $limit = min(100, (int) ($queryParams['limit'] ?? 50));

            if ($user['is_admin'] && isset($queryParams['all']) && $queryParams['all'] === 'true') {
                // Admin can see all shares
                $shares = $this->publicShareRepository->findAll($offset, $limit);
                $totalCount = $this->publicShareRepository->countAll();
            } else {
                // Regular users see only their shares
                $userId = $user['user_id'] ?? $user['id'] ?? null;
                if (!$userId) {
                    return $this->createErrorResponse($response, 'Unable to determine user ID', 500);
                }
                $shares = $this->publicShareRepository->findByCreatedBy($userId, $offset, $limit);
                $totalCount = $this->publicShareRepository->countByCreatedBy($userId);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'shares' => array_map(fn($share) => $share->toArray(), $shares),
                'pagination' => [
                    'offset' => $offset,
                    'limit' => $limit,
                    'total' => $totalCount
                ]
            ]);
        } catch (Exception $e) {
            error_log("Error fetching user shares: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function updateShare(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $this->getUserFromRequest($request);
            if ($user === null || $user === []) {
                return $this->createErrorResponse($response, 'Unauthorized', 401);
            }

            $shareId = $args['id'];
            $share = $this->publicShareRepository->findById($shareId);

            if (!$share instanceof \App\Models\PublicShare) {
                return $this->createErrorResponse($response, 'Share not found', 404);
            }

            if (!$this->canManageShare($user, $share)) {
                return $this->createErrorResponse($response, 'No permission to update this share', 403);
            }

            $data = json_decode($request->getBody()->getContents(), true);
            if (!is_array($data)) {
                return $this->createErrorResponse($response, 'Invalid JSON data', 400);
            }

            // Only admin can modify download permissions
            if (!$user['is_admin'] && isset($data['download_enabled'])) {
                unset($data['download_enabled']);
            }

            $updatedShare = $this->publicShareRepository->update($shareId, $data);

            return $this->createJsonResponse($response, [
                'success' => true,
                'share' => $updatedShare->toArray()
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->createErrorResponse($response, $e->getMessage(), 400);
        } catch (Exception $e) {
            error_log("Error updating share: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error', 500);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function deleteShare(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $this->getUserFromRequest($request);
            if ($user === null || $user === []) {
                return $this->createErrorResponse($response, 'Unauthorized', 401);
            }

            $shareId = $args['id'];
            $share = $this->publicShareRepository->findById($shareId);

            if (!$share instanceof \App\Models\PublicShare) {
                return $this->createErrorResponse($response, 'Share not found', 404);
            }

            if (!$this->canManageShare($user, $share)) {
                return $this->createErrorResponse($response, 'No permission to delete this share', 403);
            }

            $deleted = $this->publicShareRepository->delete($shareId);

            if (!$deleted) {
                return $this->createErrorResponse($response, 'Failed to delete share', 500);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'message' => 'Share deleted successfully'
            ]);
        } catch (Exception $e) {
            error_log("Error deleting share: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error', 500);
        }
    }

    public function getShareStats(Request $request, Response $response): Response
    {
        try {
            $user = $this->getUserFromRequest($request);
            if ($user === null || $user === []) {
                return $this->createErrorResponse($response, 'Unauthorized', 401);
            }

            $userId = $user['user_id'] ?? $user['id'] ?? null;
            if (!$userId) {
                return $this->createErrorResponse($response, 'Unable to determine user ID', 500);
            }
            $stats = $this->publicShareService->getShareStats($userId, $user['is_admin']);

            return $this->createJsonResponse($response, [
                'success' => true,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            error_log("Error fetching share stats: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error', 500);
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    private function canCreateShare(array $user): bool
    {
        $isAdmin = ($user['is_admin'] ?? false) || ($user['role'] === 'admin');
        $canShare = $user['can_create_public_share'] ?? false;

        return $isAdmin || $canShare;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function canManageShare(array $user, \App\Models\PublicShare $share): bool
    {
        $userId = $user['user_id'] ?? $user['id'] ?? null;
        return $user['is_admin'] || ($userId && $share->getCreatedBy() === $userId);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createJsonResponse(Response $response, array $data, int $statusCode = 200): Response
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON');
        }
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }

    private function createErrorResponse(Response $response, string $message, int $statusCode = 400): Response
    {
        return $this->createJsonResponse($response, ['error' => $message], $statusCode);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getUserFromRequest(Request $request): ?array
    {
        // The JWT middleware has already validated the token and set auth_token
        $authToken = $request->getAttribute('auth_token');
        if (!$authToken) {
            return null;
        }

        $userId = $authToken->getUserId();
        $user = $this->userRepository->findById($userId);
        return $user instanceof \App\Models\User ? $user->toArray() : null;
    }

    /**
     * Get share options (expiration options)
     */
    public function getShareOptions(Request $request, Response $response): Response
    {
        try {
            return $this->createJsonResponse($response, [
                'success' => true,
                'expiration_options' => $this->publicShareService->getExpirationOptions()
            ]);
        } catch (Exception $e) {
            error_log("Error fetching share options: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error', 500);
        }
    }

    /**
     * Get public share content by UUID
     * @param array<string, mixed> $args
     */
    public function getPublicShareContent(Request $request, Response $response, array $args): Response
    {
        try {
            $uuid = $args['uuid'];
            $queryParams = $request->getQueryParams();
            $password = $queryParams['password'] ?? null;

            $content = $this->publicShareService->getShareContent($uuid, $password);

            return $this->createJsonResponse($response, [
                'success' => true,
                'data' => $content
            ]);
        } catch (InvalidArgumentException $e) {
            $statusCode = match ($e->getMessage()) {
                'Share not found' => 404,
                'Share has expired' => 410,
                'Password required' => 401,
                'Invalid password' => 403,
                default => 400
            };

            return $this->createJsonResponse($response, [
                'error' => $e->getMessage(),
                'code' => strtoupper(str_replace(' ', '_', $e->getMessage()))
            ], $statusCode);
        } catch (Exception $e) {
            error_log("Error accessing public share {$args['uuid']}: " . $e->getMessage());
            return $this->createJsonResponse($response, [
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Verify share password
     * @param array<string, mixed> $args
     */
    public function verifySharePassword(Request $request, Response $response, array $args): Response
    {
        try {
            $uuid = $args['uuid'];
            $data = json_decode($request->getBody()->getContents(), true);
            $password = $data['password'] ?? '';

            // Try to get content with password
            $this->publicShareService->getShareContent($uuid, $password);

            return $this->createJsonResponse($response, [
                'success' => true,
                'message' => 'Password verified'
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->createJsonResponse($response, [
                'error' => $e->getMessage(),
                'code' => strtoupper(str_replace(' ', '_', $e->getMessage()))
            ], 403);
        } catch (Exception $e) {
            error_log("Error verifying share password {$args['uuid']}: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Internal server error', 500);
        }
    }
}
