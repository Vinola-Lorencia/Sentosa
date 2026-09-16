<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Resizes/recompresses product photos server-side before they ever reach
 * SupabaseStorageService — so karyawan uploading straight off a 200MP phone
 * camera doesn't need to think about resolution at all. Built on PHP's
 * built-in GD + exif extensions (both already present on this server); no
 * new Composer dependency needed for this.
 *
 * Always outputs JPEG (smaller than PNG for photos, and product photos have
 * no transparency to preserve), max width 1600px without ever upscaling a
 * smaller original, ~75-80% JPEG quality, EXIF orientation corrected first
 * so a portrait phone photo doesn't come out sideways.
 */
class ImageCompressionService
{
    private const MAX_WIDTH = 1600;
    private const JPEG_QUALITY = 78;

    /**
     * Returns a new UploadedFile pointing at the compressed temp file, so
     * callers (and SupabaseStorageService::upload()) don't need to change —
     * it's still just an UploadedFile. Falls back to returning the original
     * $file untouched if GD can't decode it, rather than failing the whole
     * request over a single unusual/corrupt image.
     */
    public function compress(UploadedFile $file): UploadedFile
    {
        $source = $this->createImageResource($file);

        if ($source === false) {
            return $file;
        }

        $source = $this->applyExifOrientation($source, $file);

        $originalWidth  = imagesx($source);
        $originalHeight = imagesy($source);

        // min(), never upscale a photo that's already smaller than the cap.
        $targetWidth  = min($originalWidth, self::MAX_WIDTH);
        $targetHeight = (int) round($originalHeight * ($targetWidth / $originalWidth));

        // Always re-drawn onto a fresh truecolor canvas — even when the
        // dimensions don't change — so a PNG with transparency gets
        // flattened onto white instead of imagejpeg() silently turning
        // transparent areas black.
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $originalWidth, $originalHeight);
        imagedestroy($source);

        $tempPath = tempnam(sys_get_temp_dir(), 'product_photo_') . '.jpg';
        imagejpeg($canvas, $tempPath, self::JPEG_QUALITY);
        imagedestroy($canvas);

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg';

        return new UploadedFile($tempPath, $originalName, 'image/jpeg', null, true);
    }

    /**
     * @return \GdImage|false
     */
    private function createImageResource(UploadedFile $file)
    {
        $path = $file->getRealPath();

        return match (strtolower((string) $file->getMimeType())) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    /**
     * Only JPEGs carry EXIF orientation. Handles the four "pure rotation"
     * orientations phone cameras actually produce (3/6/8, plus 1 = normal);
     * the mirrored variants (2/4/5/7) essentially never come from a camera
     * and are left as-is rather than adding imageflip() handling nothing
     * realistically triggers.
     *
     * @param \GdImage $image
     * @return \GdImage
     */
    private function applyExifOrientation($image, UploadedFile $file)
    {
        if (strtolower((string) $file->getMimeType()) !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? 1;

        $angle = $this->rotationAngleForOrientation($orientation);

        if ($angle === null) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        imagedestroy($image);

        return $rotated;
    }

    /**
     * imagerotate() rotates counter-clockwise by the given angle — this is
     * the EXIF-orientation-code -> GD-angle mapping, pulled out on its own
     * so it can be unit tested directly without needing to fabricate real
     * EXIF-tagged JPEG bytes for every case.
     */
    public function rotationAngleForOrientation(int $orientation): ?int
    {
        return match ($orientation) {
            3       => 180,
            6       => -90,
            8       => 90,
            default => null,
        };
    }
}
