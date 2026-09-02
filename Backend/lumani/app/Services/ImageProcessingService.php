<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resizes and compresses uploaded question images before storage.
 *
 * Uses PHP's built-in GD extension (no additional composer package required).
 * Target: max 1200 px wide, JPEG at quality 80 — suitable for low-connectivity users.
 */
class ImageProcessingService
{
    private const MAX_WIDTH = 1200;

    private const JPEG_QUALITY = 80;

    private const STORAGE_DISK = 'public';

    private const STORAGE_DIR = 'question-images';

    /**
     * Process an uploaded image: resize if wider than 1200 px, encode as JPEG q80,
     * store on the public disk, and return the stored relative path.
     *
     * @throws RuntimeException if GD cannot decode the file.
     */
    public function processAndStore(UploadedFile $file, string $directory = self::STORAGE_DIR): string
    {
        $content = file_get_contents($file->getRealPath());

        if ($content === false) {
            throw new RuntimeException('Failed to read uploaded image file.');
        }

        // Suppress GD warnings — we check for false ourselves
        $image = @imagecreatefromstring($content);

        if ($image === false) {
            throw new RuntimeException('Uploaded file could not be decoded as an image. Supported formats: JPEG, PNG, GIF, WebP.');
        }

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        // Only downscale — never upscale
        if ($origWidth > self::MAX_WIDTH) {
            $scale = self::MAX_WIDTH / $origWidth;
            $newWidth = self::MAX_WIDTH;
            $newHeight = (int) round($origHeight * $scale);

            $resized = imagescale($image, $newWidth, $newHeight, IMG_BICUBIC);
            imagedestroy($image);

            if ($resized === false) {
                throw new RuntimeException('Failed to resize image.');
            }

            $image = $resized;
        }

        // Capture JPEG output via output buffering (avoids temp files)
        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);
        $jpegData = ob_get_clean();
        imagedestroy($image);

        if ($jpegData === false || $jpegData === '') {
            throw new RuntimeException('Failed to encode image as JPEG.');
        }

        $filename = $directory . '/' . (string) Str::uuid() . '.jpg';

        Storage::disk(self::STORAGE_DISK)->put($filename, $jpegData);

        return $filename;
    }
}
