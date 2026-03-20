<?php

// routes/wishlist.php - Wishlist routes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\WishlistController;
use App\Services\AuthenticationService;
use App\Interfaces\AuthTokenInterface;
use Slim\App;
use Psr\Container\ContainerInterface;

/** @var App<ContainerInterface> $app */

// Get AuthenticationService from container
$container = $app->getContainer();
$authService = $container->get(AuthenticationService::class);
$wishlistController = new WishlistController($authService);

// --------------------------------------------------------------------------
// User Wishlist Routes
// --------------------------------------------------------------------------

// GET /api/wishlist - Get user's wishlist items
$app->get(
    '/api/wishlist',
    fn(Request $request, Response $response): \Psr\Http\Message\ResponseInterface =>
        $wishlistController->getUserWishlist($request, $response)
);

// POST /api/wishlist - Create a new wishlist item
$app->post(
    '/api/wishlist',
    fn(Request $request, Response $response): \Psr\Http\Message\ResponseInterface =>
        $wishlistController->createWishlistItem($request, $response)
);

// PUT /api/wishlist/{id} - Update a wishlist item
$app->put(
    '/api/wishlist/{id}',
    fn(Request $request, Response $response, array $args): \Psr\Http\Message\ResponseInterface =>
        $wishlistController->updateWishlistItem($request, $response, $args)
);

// DELETE /api/wishlist/{id} - Delete a wishlist item
$app->delete(
    '/api/wishlist/{id}',
    fn(Request $request, Response $response, array $args): \Psr\Http\Message\ResponseInterface =>
        $wishlistController->deleteWishlistItem($request, $response, $args)
);

// GET /api/wishlist/search/lastfm - Search Last.fm for artists/albums
$app->get(
    '/api/wishlist/search/lastfm',
    fn(Request $request, Response $response): \Psr\Http\Message\ResponseInterface =>
        $wishlistController->searchLastfm($request, $response)
);

// --------------------------------------------------------------------------
// Admin Wishlist Routes
// --------------------------------------------------------------------------

// GET /api/admin/wishlist - Get all wishlist items (admin only)
$app->get('/api/admin/wishlist', function (Request $request, Response $response) use ($wishlistController) {
    /** @var AuthTokenInterface $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken->isAdmin()) {
        $errorJson = json_encode(['error' => 'Unauthorized - Admin access required']);
        if ($errorJson === false) {
            $errorJson = '{"error":"Failed to encode error message"}';
        }
        $response->getBody()->write($errorJson);
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    return $wishlistController->adminGetAllWishlist($request, $response);
});

// PUT /api/admin/wishlist/{id}/status - Update wishlist item status (admin only)
$app->put('/api/admin/wishlist/{id}/status', function (Request $request, Response $response, array $args) use ($wishlistController) {
    /** @var AuthTokenInterface $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken->isAdmin()) {
        $errorJson = json_encode(['error' => 'Unauthorized - Admin access required']);
        if ($errorJson === false) {
            $errorJson = '{"error":"Failed to encode error message"}';
        }
        $response->getBody()->write($errorJson);
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    return $wishlistController->adminUpdateWishlistStatus($request, $response, $args);
});

// DELETE /api/admin/wishlist/{id} - Delete a wishlist item (admin only)
$app->delete('/api/admin/wishlist/{id}', function (Request $request, Response $response, array $args) use ($wishlistController) {
    /** @var AuthTokenInterface $authToken */
    $authToken = $request->getAttribute('auth_token');

    if (!$authToken->isAdmin()) {
        $errorJson = json_encode(['error' => 'Unauthorized - Admin access required']);
        if ($errorJson === false) {
            $errorJson = '{"error":"Failed to encode error message"}';
        }
        $response->getBody()->write($errorJson);
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    return $wishlistController->adminDeleteWishlistItem($request, $response, $args);
});
