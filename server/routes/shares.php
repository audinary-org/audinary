<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\PublicShareController;
use App\Controllers\PublicStreamController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Container\ContainerInterface;

/** @var App<ContainerInterface> $app */

$shareController = new PublicShareController();
$streamController = new PublicStreamController();

// Share Management Routes (Authenticated)
$app->group('/api/shares', function (RouteCollectorProxy $group) use ($shareController): void {
    // Create new public share
    $group->post('', [$shareController, 'createShare']);

    // Get user's shares (or all shares if admin)
    $group->get('', [$shareController, 'getUserShares']);

    // Get share statistics (must come before /{id} route)
    $group->get('/stats', [$shareController, 'getShareStats']);

    // Get share options (must come before /{id} route)
    $group->get('/options', [$shareController, 'getShareOptions']);

    // Get specific share
    $group->get('/{id}', [$shareController, 'getShare']);

    // Update share
    $group->put('/{id}', [$shareController, 'updateShare']);

    // Delete share
    $group->delete('/{id}', [$shareController, 'deleteShare']);
});

// Public Share Access Routes (No Authentication Required)
$app->group('/api/share', function (RouteCollectorProxy $group) use ($shareController, $streamController): void {
    // Get public share content by UUID
    $group->get('/{uuid}', [$shareController, 'getPublicShareContent']);

    // Verify share password
    $group->post('/{uuid}/verify', [$shareController, 'verifySharePassword']);

    // Stream song from public share
    $group->get(
        '/{uuid}/stream/{songId}',
        fn(Request $request, Response $response, array $args): \Psr\Http\Message\ResponseInterface =>
            $streamController->streamSong($request, $response, $args['uuid'], $args['songId'])
    );

    // Download song from public share
    $group->get(
        '/{uuid}/download/{songId}',
        fn(Request $request, Response $response, array $args): \Psr\Http\Message\ResponseInterface =>
            $streamController->downloadSong($request, $response, $args['uuid'], $args['songId'])
    );

    // Download all songs from public share as ZIP
    $group->get(
        '/{uuid}/download-all',
        fn(Request $request, Response $response, array $args): \Psr\Http\Message\ResponseInterface =>
            $streamController->downloadAllSongs($request, $response, $args['uuid'])
    );
});
