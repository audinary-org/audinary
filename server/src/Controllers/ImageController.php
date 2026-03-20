<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ImageController
{
    private string $loginBackgroundDir;
    /** @var array<int, string> */
    private array $loginBackgroundExtensions;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->loginBackgroundDir = $config['loginBackgroundDir'];
        $this->loginBackgroundExtensions = $config['loginBackgroundExtensions'];
    }

    /**
     * Get random background image for login page
     */
    public function getRandomBackgroundImage(Request $request, Response $response): Response
    {
        try {
            if (!is_dir($this->loginBackgroundDir)) {
                error_log("Login background directory not found: {$this->loginBackgroundDir}");
                return $response->withStatus(404);
            }

            $files = scandir($this->loginBackgroundDir);
            $images = [];

            foreach ($files as $file) {
                if ($file === '.') {
                    continue;
                }
                if ($file === '..') {
                    continue;
                }
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                if (in_array($extension, $this->loginBackgroundExtensions)) {
                    $images[] = $file;
                }
            }

            if ($images === []) {
                return $response->withStatus(404);
            }

            $randomImage = $images[array_rand($images)];
            $imagePath = $this->loginBackgroundDir . '/' . $randomImage;

            if (!file_exists($imagePath)) {
                return $response->withStatus(404);
            }

            $imageInfo = getimagesize($imagePath);
            $mimeType = $imageInfo['mime'] ?? 'image/jpeg';

            $imageData = file_get_contents($imagePath);
            if ($imageData === false) {
                throw new Exception("Failed to read image file");
            }
            $response->getBody()->write($imageData);

            return $response
                ->withHeader('Content-Type', $mimeType)
                ->withHeader('Content-Length', (string)strlen($imageData))
                ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', '0')
                ->withHeader('X-Image-Name', $randomImage);
        } catch (Exception $e) {
            error_log("Error serving random background image: " . $e->getMessage());
            return $response->withStatus(500);
        }
    }
}
