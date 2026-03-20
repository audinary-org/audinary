<?php

// routes/public.php - Public playlist access routes (no authentication required)

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\PublicController;
use Slim\App;
use Psr\Container\ContainerInterface;

/** @var App<ContainerInterface> $app */

$publicController = new PublicController();

// Public Playlist Access Routes (No Authentication Required)
$app->get('/api/public/stream/{uuid}/{songId}', [$publicController, 'streamSong']);
$app->get('/api/public/download/{uuid}/{songId}', [$publicController, 'downloadSong']);

// Generic Public Share Routes
$app->get('/public/share/{uuid}', [$publicController, 'getShareByUuid']);
