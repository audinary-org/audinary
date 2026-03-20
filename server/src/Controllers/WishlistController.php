<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\AuthenticationService;
use App\Repository\WishlistRepository;
use App\Repository\GlobalSettingsRepository;

final class WishlistController
{
    private AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createJsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $jsonString = json_encode($data);
        if ($jsonString === false) {
            $jsonString = '{"error": "Failed to encode response"}';
        }
        $response->getBody()->write($jsonString);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function createErrorResponse(Response $response, string $message, int $status = 400): Response
    {
        return $this->createJsonResponse($response, ['error' => $message], $status);
    }

    private function isWishlistEnabled(): bool
    {
        $settingsRepo = new GlobalSettingsRepository();
        return $settingsRepo->isWishlistEnabled();
    }

    /**
     * Get user's wishlist items
     */
    public function getUserWishlist(Request $request, Response $response): Response
    {
        try {
            if (!$this->isWishlistEnabled()) {
                return $this->createErrorResponse($response, 'Wishlist feature is disabled', 403);
            }

            $userId = $this->authService->getUserIdFromRequest($request);
            $wishlistRepo = new WishlistRepository($userId);

            $items = $wishlistRepo->getUserWishlist($userId);

            return $this->createJsonResponse($response, [
                'success' => true,
                'items' => $items
            ]);
        } catch (Exception $e) {
            error_log("WishlistController::getUserWishlist error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to fetch wishlist', 500);
        }
    }

    /**
     * Create a new wishlist item
     */
    public function createWishlistItem(Request $request, Response $response): Response
    {
        try {
            if (!$this->isWishlistEnabled()) {
                return $this->createErrorResponse($response, 'Wishlist feature is disabled', 403);
            }

            $userId = $this->authService->getUserIdFromRequest($request);
            $data = $request->getParsedBody();

            if (!is_array($data) || !isset($data['artist']) || trim($data['artist']) === '') {
                return $this->createErrorResponse($response, 'Artist is required', 400);
            }

            $wishlistRepo = new WishlistRepository($userId);

            $id = $wishlistRepo->createWishlistItem(
                $userId,
                $data['artist'],
                $data['album'] ?? null,
                $data['user_comment'] ?? null,
                $data['lastfm_artist_mbid'] ?? null,
                $data['lastfm_album_mbid'] ?? null
            );

            return $this->createJsonResponse($response, [
                'success' => true,
                'id' => $id,
                'message' => 'Wishlist item created successfully'
            ], 201);
        } catch (Exception $e) {
            error_log("WishlistController::createWishlistItem error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to create wishlist item', 500);
        }
    }

    /**
     * Update a wishlist item (user can edit their own)
     * @param array<string, mixed> $args
     */
    public function updateWishlistItem(Request $request, Response $response, array $args): Response
    {
        try {
            if (!$this->isWishlistEnabled()) {
                return $this->createErrorResponse($response, 'Wishlist feature is disabled', 403);
            }

            $userId = $this->authService->getUserIdFromRequest($request);
            $id = (int)$args['id'];
            $data = $request->getParsedBody();

            if (!is_array($data) || empty($data['artist'])) {
                return $this->createErrorResponse($response, 'Artist is required', 400);
            }

            $wishlistRepo = new WishlistRepository($userId);

            // Check if item belongs to user
            $item = $wishlistRepo->getWishlistItem($id);
            if ($item === null || $item === [] || $item['user_id'] != $userId) {
                return $this->createErrorResponse($response, 'Wishlist item not found or access denied', 404);
            }

            $success = $wishlistRepo->updateUserWishlistItem(
                $id,
                $userId,
                $data['artist'],
                $data['album'] ?? null,
                $data['user_comment'] ?? null
            );

            if ($success) {
                return $this->createJsonResponse($response, [
                    'success' => true,
                    'message' => 'Wishlist item updated successfully'
                ]);
            }
            return $this->createErrorResponse($response, 'Failed to update wishlist item', 500);
        } catch (Exception $e) {
            error_log("WishlistController::updateWishlistItem error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to update wishlist item', 500);
        }
    }

    /**
     * Delete a wishlist item (user can only delete their own)
     * @param array<string, mixed> $args
     */
    public function deleteWishlistItem(Request $request, Response $response, array $args): Response
    {
        try {
            if (!$this->isWishlistEnabled()) {
                return $this->createErrorResponse($response, 'Wishlist feature is disabled', 403);
            }

            $userId = $this->authService->getUserIdFromRequest($request);
            $id = (int)$args['id'];

            $wishlistRepo = new WishlistRepository($userId);

            $success = $wishlistRepo->deleteWishlistItem($id, $userId);

            if ($success) {
                return $this->createJsonResponse($response, [
                    'success' => true,
                    'message' => 'Wishlist item deleted successfully'
                ]);
            }
            return $this->createErrorResponse($response, 'Wishlist item not found or access denied', 404);
        } catch (Exception $e) {
            error_log("WishlistController::deleteWishlistItem error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to delete wishlist item', 500);
        }
    }

    /**
     * Get all wishlist items (admin only)
     */
    public function adminGetAllWishlist(Request $request, Response $response): Response
    {
        try {
            if (!$this->isWishlistEnabled()) {
                return $this->createErrorResponse($response, 'Wishlist feature is disabled', 403);
            }

            $wishlistRepo = new WishlistRepository();
            $params = $request->getQueryParams();

            if (isset($params['status'])) {
                $items = $wishlistRepo->getWishlistByStatus($params['status']);
            } else {
                $items = $wishlistRepo->getAllWishlist();
            }

            $stats = $wishlistRepo->getWishlistStats();

            return $this->createJsonResponse($response, [
                'success' => true,
                'items' => $items,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            error_log("WishlistController::adminGetAllWishlist error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to fetch wishlist', 500);
        }
    }

    /**
     * Update wishlist item status (admin only)
     * @param array<string, mixed> $args
     */
    public function adminUpdateWishlistStatus(Request $request, Response $response, array $args): Response
    {
        try {
            if (!$this->isWishlistEnabled()) {
                return $this->createErrorResponse($response, 'Wishlist feature is disabled', 403);
            }

            $id = (int)$args['id'];
            $data = $request->getParsedBody();

            if (!is_array($data) || empty($data['status'])) {
                return $this->createErrorResponse($response, 'Status is required', 400);
            }

            $validStatuses = ['pending', 'in_progress', 'completed', 'rejected'];
            if (!in_array($data['status'], $validStatuses)) {
                return $this->createErrorResponse($response, 'Invalid status', 400);
            }

            $wishlistRepo = new WishlistRepository();

            $success = $wishlistRepo->updateWishlistStatus(
                $id,
                $data['status'],
                $data['admin_comment'] ?? null
            );

            if ($success) {
                return $this->createJsonResponse($response, [
                    'success' => true,
                    'message' => 'Wishlist status updated successfully'
                ]);
            }
            return $this->createErrorResponse($response, 'Failed to update wishlist status', 500);
        } catch (Exception $e) {
            error_log("WishlistController::adminUpdateWishlistStatus error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to update wishlist status', 500);
        }
    }

    /**
     * Delete a wishlist item (admin only)
     * @param array<string, mixed> $args
     */
    public function adminDeleteWishlistItem(Request $request, Response $response, array $args): Response
    {
        try {
            if (!$this->isWishlistEnabled()) {
                return $this->createErrorResponse($response, 'Wishlist feature is disabled', 403);
            }

            $id = (int)$args['id'];

            $wishlistRepo = new WishlistRepository();

            $success = $wishlistRepo->adminDeleteWishlistItem($id);

            if ($success) {
                return $this->createJsonResponse($response, [
                    'success' => true,
                    'message' => 'Wishlist item deleted successfully'
                ]);
            }
            return $this->createErrorResponse($response, 'Wishlist item not found', 404);
        } catch (Exception $e) {
            error_log("WishlistController::adminDeleteWishlistItem error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to delete wishlist item', 500);
        }
    }

    /**
     * Search Last.fm for artists/albums
     */
    public function searchLastfm(Request $request, Response $response): Response
    {
        try {
            if (!$this->isWishlistEnabled()) {
                return $this->createErrorResponse($response, 'Wishlist feature is disabled', 403);
            }

            $params = $request->getQueryParams();
            $query = $params['q'] ?? '';
            $type = $params['type'] ?? 'artist'; // artist or album

            if (empty($query)) {
                return $this->createErrorResponse($response, 'Query parameter is required', 400);
            }

            $settingsRepo = new GlobalSettingsRepository();
            $apiKey = $settingsRepo->getLastfmApiKey();

            if ($apiKey === null || $apiKey === '' || $apiKey === '0') {
                return $this->createErrorResponse($response, 'Last.fm API key not configured', 503);
            }

            // Make request to Last.fm API
            $method = $type === 'album' ? 'album.search' : 'artist.search';
            $url = "http://ws.audioscrobbler.com/2.0/?method={$method}&{$type}=" . urlencode($query) . "&api_key={$apiKey}&format=json&limit=20";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Audinary/1.0');

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || ($result === false || ($result === '' || $result === '0'))) {
                error_log("Last.fm API error: HTTP {$httpCode}");
                return $this->createErrorResponse($response, 'Failed to search Last.fm', 503);
            }

            if (!is_string($result)) {
                error_log("Last.fm API returned invalid response type");
                return $this->createErrorResponse($response, 'Failed to search Last.fm', 503);
            }

            $data = json_decode($result, true);
            if (!is_array($data)) {
                error_log("Last.fm API returned invalid JSON");
                return $this->createErrorResponse($response, 'Failed to search Last.fm', 503);
            }

            return $this->createJsonResponse($response, [
                'success' => true,
                'results' => $data
            ]);
        } catch (Exception $e) {
            error_log("WishlistController::searchLastfm error: " . $e->getMessage());
            return $this->createErrorResponse($response, 'Failed to search Last.fm', 500);
        }
    }
}
