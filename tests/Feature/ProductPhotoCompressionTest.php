<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end: real HTTP request through ProductController::store()/
 * updatePhoto(), real GD compression, real upload to (and cleanup from)
 * Supabase Storage — not just the isolated ImageCompressionService unit
 * tests. Confirms the compression step is actually wired into the
 * controller, not just correct in isolation.
 */
class ProductPhotoCompressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var string[] Supabase Storage paths to delete after each test. */
    private array $uploadedPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['role_name' => 'Pemilik']);
        $this->user = User::create([
            'name'     => 'Test User',
            'email'    => 'test@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->uploadedPaths) {
            $storage = app(SupabaseStorageService::class);
            foreach ($this->uploadedPaths as $path) {
                $storage->delete($path);
            }
        }

        parent::tearDown();
    }

    /**
     * A real, noisy (not blank) JPEG — a blank/solid fake compresses so
     * well on its own that it wouldn't meaningfully prove the server
     * actually resized/recompressed anything.
     */
    private function makeLargeJpegUpload(int $width = 3000, int $height = 2000): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x += 4) {
            for ($y = 0; $y < $height; $y += 4) {
                $color = imagecolorallocate($image, ($x * 7) % 256, ($y * 13) % 256, (($x + $y) * 5) % 256);
                imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $color);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_upload_') . '.jpg';
        imagejpeg($image, $path, 100);
        imagedestroy($image);

        return new UploadedFile($path, 'produk_besar.jpg', 'image/jpeg', null, true);
    }

    public function test_large_photo_is_compressed_when_creating_a_product()
    {
        $upload = $this->makeLargeJpegUpload(3000, 2000);
        $originalSize = filesize($upload->getRealPath());

        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name' => 'Produk Foto Besar',
            'stock_qty'    => 10,
            'stock_unit'   => 'Buah',
            'image'        => $upload,
        ]);

        $response->assertRedirect(route('products.index'));

        $product = Product::where('product_name', 'Produk Foto Besar')->firstOrFail();
        $this->assertNotNull($product->image_path);
        $this->uploadedPaths[] = $product->image_path;

        $storage = app(SupabaseStorageService::class);
        $downloaded = Http::get($storage->getPublicUrl($product->image_path))->body();

        $this->assertLessThan($originalSize, strlen($downloaded));

        [$width] = getimagesizefromstring($downloaded);
        $this->assertLessThanOrEqual(1600, $width);
    }

    public function test_small_photo_is_not_upscaled_when_updating_a_product_photo()
    {
        $product = Product::create([
            'product_name'         => 'Produk Foto Kecil',
            'sale_price'           => 15000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => 10,
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
        ]);

        $upload = $this->makeLargeJpegUpload(400, 300);

        $response = $this->actingAs($this->user)
            ->patch(route('products.update-photo', $product), ['image' => $upload]);

        $response->assertStatus(200);

        $product->refresh();
        $this->uploadedPaths[] = $product->image_path;

        $storage = app(SupabaseStorageService::class);
        $downloaded = Http::get($storage->getPublicUrl($product->image_path))->body();

        [$width, $height] = getimagesizefromstring($downloaded);
        $this->assertEquals(400, $width);
        $this->assertEquals(300, $height);
    }
}
