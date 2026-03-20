<?php

// routes/user.php - User profile and settings routes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\UserController;
use Slim\App;
use Psr\Container\ContainerInterface;

/** @var App<ContainerInterface> $app */

$userController = new UserController();

// --------------------------------------------------------------------------
// User Profile Routes
// --------------------------------------------------------------------------

$app->get('/api/user', fn(Request $request, Response $response): \Psr\Http\Message\ResponseInterface => $userController->getProfile($request, $response));

// --------------------------------------------------------------------------
// User Settings Routes
// --------------------------------------------------------------------------

$app->get(
    '/api/user/settings',
    fn(Request $request, Response $response): \Psr\Http\Message\ResponseInterface =>
        $userController->getSettings($request, $response)
);

$app->post(
    '/api/user/settings',
    fn(Request $request, Response $response): \Psr\Http\Message\ResponseInterface =>
        $userController->updateSettings($request, $response)
);

// --------------------------------------------------------------------------
// User Search Routes (for playlist sharing)
// --------------------------------------------------------------------------

$app->get(
    '/api/users/available',
    fn(Request $request, Response $response): \Psr\Http\Message\ResponseInterface =>
        $userController->getAvailableUsers($request, $response)
);

$app->get(
    '/api/users/search',
    fn(Request $request, Response $response): \Psr\Http\Message\ResponseInterface =>
        $userController->searchUsers($request, $response)
);
