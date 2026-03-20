<?php

namespace App\Interfaces;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Interface for image services
 */
interface ImageServiceInterface
{
    /**
     * Serve album cover image
     */
    public function serveAlbumCover(Request $request, Response $response): Response;

    /**
     * Serve artist image
     */
    public function serveArtistImage(Request $request, Response $response): Response;

    /**
     * Serve profile image
     */
    public function serveProfileImage(Request $request, Response $response): Response;

    /**
     * Serve playlist image
     */
    public function servePlaylistImage(Request $request, Response $response): Response;

    /**
     * Get service configuration
     */
    /** @return array<string, mixed> */
    public function getConfig(): array;

    /**
     * Get image service statistics
     */
    /** @return array<string, mixed> */
    public function getStatistics(): array;

    /**
     * Validate service configuration
     */
    /** @return array<string> */
    public function validateConfiguration(): array;
}
