<?php

require __DIR__ . '/../vendor/autoload.php';

use DI\Container;
use Slim\Factory\AppFactory;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

// Define root directory
$rootDir = realpath(__DIR__ . '/..');

// Create Container using PHP-DI
$container = new Container();

// Add logger definition to the container
$container->set('logger', function () use ($rootDir): \Monolog\Logger {
    $logger = new Logger('app_logger');
    $logFile = $rootDir . '/var/logs/app.log';
    $handler = new RotatingFileHandler($logFile, 7, Logger::DEBUG);
    $handler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message%\n", 'Y-m-d H:i:s'));
    $logger->pushHandler($handler);
    return $logger;
});

// Set container for the AppFactory
AppFactory::setContainer($container);

// Create the Slim App
$app = AppFactory::create();

// JWT Authentication setup
require_once __DIR__ . '/../src/configHelper.php';

use App\Services\AuthenticationService;
use App\Middleware\JWTAuthMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\MusicAPI;
use App\Services\MediaStreamer;
use App\Services\MediaStreamerConfig;

$config = loadConfig();
$authService = new AuthenticationService(
    jwtSecret: $config['jwtSecret'] ?? 'your-secret-key-here',
    jwtIssuer: 'audinary',
    jwtExpirationTime: 31536000 // 1 year
);

// Add AuthenticationService to DI container
$container->set(AuthenticationService::class, fn(): \App\Services\AuthenticationService => new AuthenticationService(
    jwtSecret: $config['jwtSecret'] ?? 'your-secret-key-here',
    jwtIssuer: 'audinary',
    jwtExpirationTime: 31536000 // 1 year
));

// JWT Authentication middleware for protected routes
$jwtAuthMiddleware = new JWTAuthMiddleware($authService);

// Initialize services for authenticated API routes - ADD FIRST so it runs LAST (after JWT)
$app->add(function (Request $request, RequestHandler $handler) {
    $path = $request->getUri()->getPath();

    // Only apply to protected API routes (after JWT auth has run)
    if (
        strpos($path, '/api/') === 0
        && strpos($path, '/api/auth/') !== 0
        && strpos($path, '/api/public/') !== 0
        && strpos($path, '/api/share/') !== 0
        && $path !== '/api/version'
        && $path !== '/api/config'
        && $path !== '/api/mpd/status'
        && $path !== '/api/background-images'
    ) {
        try {
            // Get user ID from JWT token (set by JWTAuthMiddleware)
            $userId = $request->getAttribute('user_id');

            // userId should now be set by JWT middleware which runs before this
            if (!$userId) {
                error_log("Service initialization: user_id not found in request attributes");
                // Return 401 since JWT middleware should have handled this
                $response = new \Slim\Psr7\Response();
                $json = json_encode([
                    'error' => 'Unauthorized',
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Authentication required'
                ]);
                if ($json === false) {
                    throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
                }
                $response->getBody()->write($json);
                return $response->withStatus(401)
                    ->withHeader('Content-Type', 'application/json');
            }

            // Initialize logger if not already set
            if (!isset($GLOBALS['logger'])) {
                $GLOBALS['logger'] = new class {
                    public function error(string $message): void
                    {
                        error_log("[ERROR] " . $message);
                    }
                    public function info(string $message): void
                    {
                        error_log("[INFO] " . $message);
                    }
                    public function debug(string $message): void
                    {
                        error_log("[DEBUG] " . $message);
                    }
                };
            }

            // Initialize services with authenticated user context
            $api = new MusicAPI($userId);
            $config = MediaStreamerConfig::getConfigWithEnvironment();
            $streamer = new MediaStreamer($config);

            $request = $request->withAttribute('api', $api);
            $request = $request->withAttribute('streamer', $streamer);
        } catch (Exception $e) {
            error_log("API Service Initialization Error: " . $e->getMessage());
            $response = new \Slim\Psr7\Response();
            $json = json_encode([
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR',
                'message' => 'An unexpected error occurred. Please try again.'
            ]);
            if ($json === false) {
                throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
            }
            $response->getBody()->write($json);
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    return $handler->handle($request);
});

// Apply JWT authentication to protected API routes - ADD SECOND so it runs FIRST
$app->add(function (Request $request, RequestHandler $handler) use ($jwtAuthMiddleware): \Psr\Http\Message\ResponseInterface {
    $path = $request->getUri()->getPath();

    // Skip JWT auth for public routes
    if (
        strpos($path, '/api/auth/') === 0
        || strpos($path, '/api/public/') === 0
        || strpos($path, '/api/share/') === 0
        || $path === '/api/version'
        || $path === '/api/config'
        || $path === '/api/mpd/status'
        || $path === '/api/background-images'
    ) {
        return $handler->handle($request);
    }

    // Apply JWT authentication to all other /api/* routes
    if (strpos($path, '/api/') === 0) {
        // Run JWT authentication middleware
        return $jwtAuthMiddleware($request, $handler);
    }

    return $handler->handle($request);
});

/**
 * ResponseHelper - Standardizes API responses
 */
class ResponseHelper
{
    /**
     * Create a success response
     * @param array<string, mixed> $data
     */
    public static function success(Response $response, array $data = [], int $status = 200): Response
    {
        $payload = array_merge(['success' => true], $data);
        $json = json_encode($payload);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
        }
        $response->getBody()->write($json);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create an error response
     */
    public static function error(Response $response, string $message, int $status = 400): Response
    {
        $json = json_encode([
            'success' => false,
            'error' => $message
        ]);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
        }
        $response->getBody()->write($json);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}


$app->get('/', function (Request $request, Response $response): \Psr\Http\Message\MessageInterface {
    // Load version from VERSION file if available
    $versionFile = __DIR__ . '/../VERSION';
    $versionContents = file_get_contents($versionFile);
    $version = file_exists($versionFile) && $versionContents !== false ? trim($versionContents) : 'unknown';

    $serverInfo = [
        'server' => 'Audinary Music Server',
        'version' => $version,
        'api' => '/rest/',
        'status' => 'online'
    ];

    $json = json_encode($serverInfo);
    if ($json === false) {
        throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
    }
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

// Load shared utilities first
require __DIR__ . '/../routes/shared_utilities.php';

// Load route files
require __DIR__ . '/../routes/auth.php';
require __DIR__ . '/../routes/media.php';
require __DIR__ . '/../routes/user.php';
require __DIR__ . '/../routes/admin.php';
require __DIR__ . '/../routes/images.php';
require __DIR__ . '/../routes/public.php';
require __DIR__ . '/../routes/shares.php';
require __DIR__ . '/../routes/wishlist.php';

// Add Security Headers Middleware
$app->add(new SecurityHeadersMiddleware());

// CSRF Protection
$app->add(new CsrfMiddleware());

// Add body parsing middleware BEFORE routing middleware
$app->addBodyParsingMiddleware();

$app->addRoutingMiddleware();

// Production: hide error details from clients. Set SLIM_DEBUG=1 to enable.
$displayErrors = (bool)(getenv('SLIM_DEBUG') ?: false);
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);
$app->run();
