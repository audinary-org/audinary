<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Security Headers Middleware
 *
 * Adds security-related HTTP headers to all responses
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Process the middleware
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        // Add security headers
        return $this->addSecurityHeaders($response);
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders(Response $response): Response
    {
        // Prevent clickjacking attacks
        $response = $response->withHeader('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response = $response->withHeader('X-Content-Type-Options', 'nosniff');

        // Enable XSS filter in browsers
        $response = $response->withHeader('X-XSS-Protection', '1; mode=block');

        // Enforce HTTPS (if on HTTPS)
        if ($this->isHttps()) {
            // HSTS: Enforce HTTPS for 1 year
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Content Security Policy
        // Adjust as needed for your frontend
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'", // Required for Vue.js runtime styles
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "media-src 'self' blob:",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'"
        ]);
        $response = $response->withHeader('Content-Security-Policy', $csp);

        // Referrer Policy
        $response = $response->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (formerly Feature-Policy)
        $permissions = implode(', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()'
        ]);
        $response = $response->withHeader('Permissions-Policy', $permissions);

        return $response;
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
