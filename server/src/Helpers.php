<?php

// src/Helpers.php
use Ramsey\Uuid\Uuid;

require_once __DIR__ . '/configHelper.php';


// Helper: Generate UUID (for example purposes; use a robust method in production)
if (!function_exists('generateUUID')) {
    function generateUUID(): string
    {
        return Uuid::uuid4()->toString();
    }
}


if (!function_exists('convertImageToWebp200')) {
    /**
     * Converts a JPEG/PNG image to a resized WebP image while preserving its aspect ratio.
     *
     * The output image will have a width of $targetW pixels and its height will be calculated
     * to maintain the original aspect ratio.
     *
     * @param string $srcPath  Path to the source image.
     * @param string $destPath Path to save the WebP image.
     * @param int    $targetW  Target width in pixels (default is 200).
     * @param int    $quality  WebP quality (0-100, default is 80).
     * @return bool  True on success, false on failure.
     */
    function convertImageToWebp200($srcPath, $destPath, $targetW = 200, $quality = 80)
    {
        $info = getimagesize($srcPath);
        if ($info === false) {
            return false;
        }
        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($srcPath);
                break;
            case 'image/png':
                $src = imagecreatefrompng($srcPath);
                break;
            default:
                return false;
        }
        if (!$src) {
            return false;
        }
        $origW = imagesx($src);
        $origH = imagesy($src);
        // Calculate target height to maintain the original aspect ratio.
        $targetH = (int)round($origH * ($targetW / $origW));

        // Ensure dimensions are at least 1
        if ($targetW < 1) {
            $targetW = 1;
        }
        if ($targetH < 1) {
            $targetH = 1;
        }

        $dst = imagecreatetruecolor($targetW, $targetH);
        // For PNG, preserve transparency.
        if ($mime === 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);
        $result = imagewebp($dst, $destPath, $quality);
        imagedestroy($dst);
        imagedestroy($src);
        return $result;
    }
}


/**
 * Generate a UUID v4
 *
 * @return string UUID in canonical format
 */
function uuid_create(): string
{
    // Generate 16 random bytes
    $data = random_bytes(16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Format as string
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Get the MIME type for a file
 *
 * @param string $filePath Path to the file
 * @return string MIME type
 */
function getMimeTypeForFile(string $filePath): string
{
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    $mimeTypes = [
        'mp3' => 'audio/mpeg',
        'flac' => 'audio/flac',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
        'wav' => 'audio/wav',
        'aac' => 'audio/aac',
        'opus' => 'audio/opus',
        'wma' => 'audio/x-ms-wma',
        'alac' => 'audio/alac',
        // Add more as needed
    ];

    return $mimeTypes[$extension] ?? 'application/octet-stream';
}
