<?php

// routes/shared_utilities.php - Shared utilities for route files

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Helper function to create JSON response
 * @param array<string, mixed> $data
 */
function createJsonResponse(Response $response, array $data, int $statusCode = 200): Response
{
    $json = json_encode($data);
    if ($json === false) {
        throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
    }
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
}

/**
 * Helper function to get JSON body from request
 * @return array<string, mixed>
 */
function getJsonBody(Request $request): array
{
    $body = $request->getBody()->getContents();
    return json_decode($body, true) ?? [];
}

/**
 * Helper function to create error response
 */
function createErrorResponse(Response $response, string $message, int $statusCode = 400): Response
{
    return createJsonResponse($response, ['error' => $message], $statusCode);
}

/**
 * Helper function to validate admin permissions
 * @param \App\Interfaces\AuthTokenInterface $authToken
 */
function requireAdmin($authToken, Response $response): ?Response
{
    if (!$authToken->isAdmin()) {
        return createErrorResponse($response, 'Unauthorized - Admin access required', 403);
    }
    return null;
}
/**
 * Helper function to extract JWT token from Authorization header
 */
function extractTokenFromHeader(string $authHeader): ?string
{
    if ($authHeader === '' || $authHeader === '0') {
        return null;
    }

    // Remove "Bearer " prefix if present
    if (str_starts_with($authHeader, 'Bearer ')) {
        return substr($authHeader, 7);
    }

    return $authHeader;
}
/**
 * Helper function to format bytes
 * @param int|float $bytes
 * @param int $precision
 */
function formatBytes($bytes, $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Helper function to get directory size
 * @param string $directory
 * @return int
 */
function getDirSize($directory): int
{
    $size = 0;

    if (is_dir($directory)) {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Exception $e) {
            error_log("Error calculating directory size: " . $e->getMessage());
        }
    }

    return $size;
}
