<?php

// routes/auth.php - Authentication routes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\AuthController;
use App\Services\AuthenticationService;
use App\Services\EmailService;
use App\Services\PasswordResetService;
use App\Database\Connection;
use App\Middleware\RateLimitMiddleware;
use Slim\App;
use Psr\Container\ContainerInterface;

/** @var App<ContainerInterface> $app */

$config = loadConfig();

// Initialize services
$authService = new AuthenticationService(
    jwtSecret: $config['jwtSecret'] ?? 'your-secret-key-here',
    jwtIssuer: 'audinary',
    jwtExpirationTime: 31536000 // 1 year
);

$emailService = new EmailService($config['smtp'] ?? []);

$passwordResetService = new PasswordResetService(
    Connection::getPDO(),
    $emailService,
    $config
);

// Initialize controller
$authController = new AuthController($authService, $passwordResetService);

// Authentication routes with rate limiting
$app->post('/api/auth/login', [$authController, 'login'])
    ->add(new RateLimitMiddleware(5, 300, 'login')); // 5 attempts per 5 minutes

$app->post('/api/auth/register', [$authController, 'register'])
    ->add(new RateLimitMiddleware(3, 3600, 'register')); // 3 registrations per hour

$app->put('/api/auth/profile', [$authController, 'updateProfile']);

// Public routes
$app->get('/api/version', [$authController, 'getVersion']);
$app->get('/api/config', [$authController, 'getConfig']);

// Password reset routes with rate limiting
$app->post('/api/auth/forgot-password', [$authController, 'forgotPassword'])
    ->add(new RateLimitMiddleware(3, 3600, 'password-reset')); // 3 requests per hour

$app->get('/api/auth/validate-reset-token', [$authController, 'validateResetToken']);

$app->post('/api/auth/reset-password', [$authController, 'resetPassword'])
    ->add(new RateLimitMiddleware(5, 3600, 'password-change')); // 5 password changes per hour
