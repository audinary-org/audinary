<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Rate Limiting Middleware
 *
 * Implements token bucket algorithm for rate limiting
 * Stores rate limit data in database for persistence
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $windowSeconds;
    private string $identifier;
    private ?\PDO $db = null;

    /**
     * @param int $maxAttempts Maximum number of attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @param string $identifier Unique identifier for this rate limit (e.g., 'login', 'register')
     */
    public function __construct(int $maxAttempts = 5, int $windowSeconds = 300, string $identifier = 'default')
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
        $this->identifier = $identifier;
    }

    /**
     * Process the middleware
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $clientIp = $this->getClientIp($request);
        $key = $this->identifier . ':' . $clientIp;

        // Check rate limit
        if (!$this->checkRateLimit($key)) {
            return $this->createRateLimitResponse();
        }

        // Process request
        $response = $handler->handle($request);

        // Increment counter after successful request
        $this->incrementCounter($key);

        // Add rate limit headers to response
        $remaining = $this->getRemainingAttempts($key);
        $resetTime = $this->getResetTime($key);

        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string)max(0, $remaining))
            ->withHeader('X-RateLimit-Reset', (string)$resetTime);
    }

    /**
     * Check if request is within rate limit
     */
    private function checkRateLimit(string $key): bool
    {
        $this->initDatabase();

        $now = time();
        $windowStart = $now - $this->windowSeconds;

        // Clean up old entries
        $stmt = $this->db->prepare('DELETE FROM rate_limits WHERE expires_at < :now');
        $stmt->execute([':now' => $now]);

        // Get current attempts
        $stmt = $this->db->prepare('
            SELECT attempts, created_at
            FROM rate_limits
            WHERE key = :key AND expires_at > :now
        ');
        $stmt->execute([':key' => $key, ':now' => $now]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            return true; // No record, allow request
        }

        return $record['attempts'] < $this->maxAttempts;
    }

    /**
     * Increment attempt counter
     */
    private function incrementCounter(string $key): void
    {
        $this->initDatabase();

        $now = time();
        $expiresAt = $now + $this->windowSeconds;

        $stmt = $this->db->prepare('
            INSERT INTO rate_limits (key, attempts, created_at, expires_at)
            VALUES (:key, 1, :now, :expires)
            ON CONFLICT(key) DO UPDATE SET
                attempts = rate_limits.attempts + 1,
                expires_at = :expires
        ');

        $stmt->execute([
            ':key' => $key,
            ':now' => $now,
            ':expires' => $expiresAt
        ]);
    }

    /**
     * Get remaining attempts
     */
    private function getRemainingAttempts(string $key): int
    {
        $this->initDatabase();

        $stmt = $this->db->prepare('
            SELECT attempts
            FROM rate_limits
            WHERE key = :key AND expires_at > :now
        ');
        $stmt->execute([':key' => $key, ':now' => time()]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            return $this->maxAttempts;
        }

        return $this->maxAttempts - (int)$record['attempts'];
    }

    /**
     * Get reset timestamp
     */
    private function getResetTime(string $key): int
    {
        $this->initDatabase();

        $stmt = $this->db->prepare('
            SELECT expires_at
            FROM rate_limits
            WHERE key = :key
        ');
        $stmt->execute([':key' => $key]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            return time() + $this->windowSeconds;
        }

        return (int)$record['expires_at'];
    }

    /**
     * Create rate limit exceeded response
     */
    private function createRateLimitResponse(): Response
    {
        $response = new \Slim\Psr7\Response();

        $retryAfter = $this->windowSeconds;

        $json = json_encode([
            'success' => false,
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $retryAfter
        ]);

        if ($json !== false) {
            $response->getBody()->write($json);
        }

        return $response
            ->withStatus(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string)$retryAfter)
            ->withHeader('X-RateLimit-Limit', (string)$this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', '0')
            ->withHeader('X-RateLimit-Reset', (string)(time() + $retryAfter));
    }

    /**
     * Get client IP address
     */
    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();

        // Check for proxy headers (in order of preference)
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Standard proxy header
            'HTTP_X_REAL_IP',        // Nginx proxy
            'REMOTE_ADDR'            // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];

                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Initialize database and create table if needed
     */
    private function initDatabase(): void
    {
        if ($this->db !== null) {
            return;
        }

        // Use the same database as the application
        $this->db = \App\Database\Connection::getPDO();

        // Create rate_limits table if it doesn't exist
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS rate_limits (
                key TEXT PRIMARY KEY,
                attempts INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL,
                expires_at INTEGER NOT NULL
            )
        ');

        // Create index for cleanup queries
        $this->db->exec('
            CREATE INDEX IF NOT EXISTS idx_rate_limits_expires
            ON rate_limits(expires_at)
        ');
    }

    /**
     * Clean up expired rate limit records (call this periodically)
     */
    public static function cleanup(): void
    {
        $db = \App\Database\Connection::getPDO();
        $db->exec('DELETE FROM rate_limits WHERE expires_at < ' . time());
    }
}
