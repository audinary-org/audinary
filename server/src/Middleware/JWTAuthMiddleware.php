<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;
use App\Services\AuthenticationService;

/**
 * JWT Authentication Middleware
 *
 * Validates JWT tokens from Authorization header and sets user context
 */
class JWTAuthMiddleware implements MiddlewareInterface
{
    private AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Process the middleware
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        return $this->__invoke($request, $handler);
    }

    /**
     * Validate JWT token and set user context
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Extract token from Authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        $token = $this->extractTokenFromHeader($authHeader);

        // If no token in header, check URL parameters for media streaming requests
        if ($token === null || $token === '' || $token === '0') {
            $path = $request->getUri()->getPath();
            $isMediaRequest = strpos($path, '/api/media/play/') === 0;

            if ($isMediaRequest) {
                $queryParams = $request->getQueryParams();
                $token = $queryParams['token'] ?? null;
            }
        }

        if (!$token) {
            return $this->createUnauthorizedResponse('Missing or invalid authorization header');
        }

        // Verify JWT token
        $authToken = $this->authService->verifyToken($token);

        if (!$authToken instanceof \App\Models\AuthToken || !$authToken->isValid()) {
            error_log("JWT Middleware: Token verification failed - " . ($authToken instanceof \App\Models\AuthToken ? 'invalid/expired' : 'null'));
            return $this->createUnauthorizedResponse('Invalid or expired token');
        }

        // Add user information to request attributes for route handlers
        $request = $request->withAttribute('user_id', $authToken->getUserId());
        $request = $request->withAttribute('username', $authToken->getUsername());
        $request = $request->withAttribute('is_admin', $authToken->isAdmin());
        $request = $request->withAttribute('auth_token', $authToken);

        return $handler->handle($request);
    }

    /**
     * Extract JWT token from Authorization header
     * Supports: "Bearer <token>" format
     */
    private function extractTokenFromHeader(string $authHeader): ?string
    {
        if ($authHeader === '' || $authHeader === '0') {
            return null;
        }

        // Check for Bearer token format
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Fallback: treat entire header as token
        return $authHeader;
    }

    /**
     * Create unauthorized response with proper headers
     */
    private function createUnauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $json = json_encode([
            'success' => false,
            'error' => $message,
            'code' => 'UNAUTHORIZED',
            'message' => 'Authentication required. Please log in again.'
        ]);

        if ($json !== false) {
            $response->getBody()->write($json);
        }

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Auth-Error', 'token_invalid');
    }
}
