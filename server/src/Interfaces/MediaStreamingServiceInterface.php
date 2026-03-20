<?php

namespace App\Interfaces;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Interface for media streaming services
 */
interface MediaStreamingServiceInterface
{
    /**
     * Stream a song
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function streamSong(string $songId, array $options = []): array;

    /**
     * Handle range requests for streaming
     */
    public function handleRangeRequest(Request $request, Response $response, string $filePath, string $mimeType): Response;

    /**
     * Get streaming statistics
     *
     * @return array<string, mixed>
     */
    public function getStreamingStats(): array;

    /**
     * Clean up old transcoded files
     */
    public function cleanupCache(int $maxAge = 86400): int;
}
