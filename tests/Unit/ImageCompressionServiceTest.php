<?php

namespace Tests\Unit;

use App\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class ImageCompressionServiceTest extends TestCase
{
    private ImageCompressionService $service;

    /** @var string[] temp files to clean up after each test */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageCompressionService();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        parent::tearDown();
    }

    private function makeJpegUpload(int $width, int $height, int $quality = 100): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        // Noisy-ish fill so JPEG compression can't trivially flatten it to
        // nothing — a plain solid fill would compress unrealistically well
        // regardless of quality/size settings, weakening the "smaller than
        // original" assertions.
        for ($x = 0; $x < $width; $x += 4) {
            for ($y = 0; $y < $height; $y += 4) {
                $color = imagecolorallocate($image, ($x * 7) % 256, ($y * 13) % 256, (($x + $y) * 5) % 256);
                imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $color);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_src_') . '.jpg';
        imagejpeg($image, $path, $quality);
        imagedestroy($image);

        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'test.jpg', 'image/jpeg', null, true);
    }

    private function buildExifApp1(int $orientation): string
    {
        $tiff = "II\x2A\x00" . pack('V', 8)
            . pack('v', 1)
            . pack('v', 0x0112) . pack('v', 3) . pack('V', 1) . pack('v', $orientation) . pack('v', 0)
            . pack('V', 0);

        $content = "Exif\x00\x00" . $tiff;

        return "\xFF\xE1" . pack('n', strlen($content) + 2) . $content;
    }

    private function makeJpegUploadWithOrientation(int $width, int $height, int $orientation): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 180, 60, 60));

        ob_start();
        imagejpeg($image, null, 90);
        $jpegBytes = ob_get_clean();
        imagedestroy($image);

        // Splice the EXIF APP1 segment in right after the SOI marker (FF D8).
        $withExif = substr($jpegBytes, 0, 2) . $this->buildExifApp1($orientation) . substr($jpegBytes, 2);

        $path = tempnam(sys_get_temp_dir(), 'test_exif_') . '.jpg';
        file_put_contents($path, $withExif);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'test.jpg', 'image/jpeg', null, true);
    }

    public function test_large_image_is_resized_to_max_width_and_shrinks_in_file_size()
    {
        $upload = $this->makeJpegUpload(3000, 2000, quality: 100);
        $originalSize = filesize($upload->getRealPath());

        $result = $this->service->compress($upload);
        $this->tempFiles[] = $result->getRealPath();

        [$width, $height] = getimagesize($result->getRealPath());

        $this->assertEquals(1600, $width);
        $this->assertEquals(1067, $height); // 2000 * (1600/3000), rounded
        $this->assertLessThan($originalSize, filesize($result->getRealPath()));
    }

    public function test_small_image_is_not_upscaled()
    {
        $upload = $this->makeJpegUpload(400, 300);

        $result = $this->service->compress($upload);
        $this->tempFiles[] = $result->getRealPath();

        [$width, $height] = getimagesize($result->getRealPath());

        $this->assertEquals(400, $width);
        $this->assertEquals(300, $height);
    }

    public function test_compressed_output_is_always_jpeg()
    {
        $upload = $this->makeJpegUpload(800, 600);

        $result = $this->service->compress($upload);
        $this->tempFiles[] = $result->getRealPath();

        $this->assertEquals('image/jpeg', mime_content_type($result->getRealPath()));
        $this->assertEquals('image/jpeg', $result->getMimeType());
    }

    public function test_rotation_angle_mapping_for_exif_orientations()
    {
        // The exact GD-angle mapping used by applyExifOrientation() —
        // isolated here so every orientation code is verifiable without
        // needing to fabricate real EXIF-tagged JPEG bytes for each one.
        $this->assertNull($this->service->rotationAngleForOrientation(1));   // normal
        $this->assertEquals(180, $this->service->rotationAngleForOrientation(3));
        $this->assertEquals(-90, $this->service->rotationAngleForOrientation(6));
        $this->assertEquals(90, $this->service->rotationAngleForOrientation(8));
    }

    public function test_exif_orientation_6_rotates_landscape_photo_to_portrait()
    {
        // A phone held in portrait but whose sensor data is stored
        // landscape (200x100) with Orientation=6 must come out portrait
        // (100x200) after correction — proving the fix actually applies
        // end-to-end, not just that the angle mapping is correct in isolation.
        $upload = $this->makeJpegUploadWithOrientation(200, 100, orientation: 6);

        $result = $this->service->compress($upload);
        $this->tempFiles[] = $result->getRealPath();

        [$width, $height] = getimagesize($result->getRealPath());

        $this->assertEquals(100, $width);
        $this->assertEquals(200, $height);
    }

    public function test_exif_orientation_1_leaves_dimensions_unrotated()
    {
        $upload = $this->makeJpegUploadWithOrientation(200, 100, orientation: 1);

        $result = $this->service->compress($upload);
        $this->tempFiles[] = $result->getRealPath();

        [$width, $height] = getimagesize($result->getRealPath());

        $this->assertEquals(200, $width);
        $this->assertEquals(100, $height);
    }
}
