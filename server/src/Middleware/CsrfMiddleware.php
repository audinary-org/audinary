<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;

/**
 * CSRF Protection Middleware
 *
 * Protects against Cross-Site Request Forgery attacks
 * Uses double-submit cookie pattern with HTTPOnly and SameSite
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private const COOKIE_NAME = 'csrf_token';
    private const HEADER_NAME = 'X-CSRF-Token';
    private const TOKEN_LENGTH = 32;

    /**
     * Process the middleware
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $method = $request->getMethod();

        // Only check CSRF for state-changing requests
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            // Skip CSRF for auth routes (login, register, etc.)
            $path = $request->getUri()->getPath();
            $isAuthRoute = strpos($path, '/api/auth/') === 0;

            // Skip CSRF for API clients using Bearer token authentication
            // CSRF attacks cannot forge Authorization headers, so Bearer token
            // requests are inherently protected against CSRF
            $authHeader = $request->getHeaderLine('Authorization');
            $hasBearerToken = stripos($authHeader, 'Bearer ') === 0;

            if (!$isAuthRoute && !$hasBearerToken && !$this->validateCsrfToken($request)) {
                return $this->createCsrfErrorResponse();
            }
        }

        // Generate new token for this request
        $token = $this->generateToken();

        // Process request
        $response = $handler->handle($request);

        // Set CSRF cookie
        return $this->setCsrfCookie($response, $token);
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrfToken(Request $request): bool
    {
        // Get token from header
        $headerToken = $request->getHeaderLine(self::HEADER_NAME);

        // Get token from cookie
        $cookies = $request->getCookieParams();
        $cookieToken = $cookies[self::COOKIE_NAME] ?? '';

        // Both must be present and match
        if ($headerToken === '' || $cookieToken === '') {
            return false;
        }

        // Timing-safe comparison to prevent timing attacks
        return hash_equals($cookieToken, $headerToken);
    }

    /**
     * Generate cryptographically secure CSRF token
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * Set CSRF token cookie
     */
    private function setCsrfCookie(Response $response, string $token): Response
    {
        // Cookie options
        $options = [
            'expires' => time() + 3600, // 1 hour
            'path' => '/',
            'domain' => '',
            'secure' => $this->isHttps(), // Only send over HTTPS
            'httponly' => false, // JavaScript needs to read this for the header
            'samesite' => 'Strict' // Strict SameSite policy
        ];

        // Build Set-Cookie header
        $cookie = sprintf(
            '%s=%s; Path=%s; Max-Age=%d; SameSite=%s%s',
            self::COOKIE_NAME,
            $token,
            $options['path'],
            $options['expires'] - time(),
            $options['samesite'],
            $options['secure'] ? '; Secure' : ''
        );

        return $response->withAddedHeader('Set-Cookie', $cookie);
    }

    /**
     * Create CSRF error response
     */
    private function createCsrfErrorResponse(): Response
    {
        $response = new \Slim\Psr7\Response();

        $json = json_encode([
            'success' => false,
            'error' => 'CSRF Validation Failed',
            'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.',
            'code' => 'CSRF_TOKEN_INVALID'
        ]);

        if ($json !== false) {
            $response->getBody()->write($json);
        }

        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Check if request is over HTTPS
     */
    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return true;
        }

        return false;
    }
}
