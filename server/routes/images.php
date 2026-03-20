<?php

// routes/images.php - Image serving routes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\ImageController;
use Slim\App;
use Psr\Container\ContainerInterface;

/** @var App<ContainerInterface> $app */

$config = loadConfig();
$imageController = new ImageController([
    'loginBackgroundDir' => $config['loginBackgroundDir'],
    'loginBackgroundExtensions' => $config['loginBackgroundExtensions']
]);

$app->get(
    '/api/background-images',
    fn(Request $request, Response $response): \Psr\Http\Message\ResponseInterface =>
        $imageController->getRandomBackgroundImage($request, $response)
);
